<?php
/**
 * Script de instalação – FORMA4 Imobiliária
 *
 * Executa APENAS UMA VEZ:
 *  1. Cria as tabelas do banco
 *  2. Insere configurações padrão
 *  3. Cria o usuário administrador
 *  4. Cria o formulário padrão de Autorização de Venda
 *  5. Apaga a si mesmo (ou instrui o usuário a apagá-lo)
 *
 * AVISO: Delete este arquivo após a instalação!
 *
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';

// ------------------------------------------------------------------
// Evita re-execução acidental se já existir um usuário no banco
// ------------------------------------------------------------------
$error   = '';
$success = '';
$step    = 0; // 0 = formulário, 1 = executando, 2 = concluído

// Conecta ao MySQL sem selecionar banco (para criar caso não exista)
function getPDO(): PDO
{
    $dsn     = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName  = trim($_POST['admin_name']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = trim($_POST['admin_pass']  ?? '');
    $adminPass2 = trim($_POST['admin_pass2'] ?? '');

    // Validações
    if (strlen($adminName) < 2)  $error = 'Nome deve ter ao menos 2 caracteres.';
    elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $error = 'E-mail inválido.';
    elseif (strlen($adminPass) < 6)  $error = 'Senha deve ter ao menos 6 caracteres.';
    elseif ($adminPass !== $adminPass2) $error = 'As senhas não coincidem.';

    if (!$error) {
        try {
            $pdo = getPDO();

            // 1. Cria banco caso não exista
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . DB_NAME . '`');

            // 2. Cria tabelas
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name`       VARCHAR(100) NOT NULL,
              `email`      VARCHAR(150) NOT NULL,
              `password`   VARCHAR(255) NOT NULL,
              `role`       ENUM('admin','user') NOT NULL DEFAULT 'admin',
              `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_users_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `forms` (
              `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `title`        VARCHAR(200) NOT NULL,
              `slug`         VARCHAR(200) NOT NULL,
              `description`  TEXT,
              `fields`       LONGTEXT NOT NULL,
              `pdf_template` VARCHAR(50) NOT NULL DEFAULT 'default',
              `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
              `created_by`   INT UNSIGNED,
              `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_forms_slug` (`slug`),
              CONSTRAINT `fk_forms_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `submissions` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `form_id`    INT UNSIGNED NOT NULL,
              `data`       LONGTEXT NOT NULL,
              `pdf_path`   VARCHAR(500) DEFAULT NULL,
              `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
              `ip_address` VARCHAR(45) DEFAULT NULL,
              `user_agent` VARCHAR(500) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk_sub_form` FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `key_name`   VARCHAR(100) NOT NULL,
              `value`      TEXT,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_settings_key` (`key_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 3. Configurações padrão
            $defaultSettings = [
                'app_name'        => 'Forma4 Imobiliária',
                'app_url'         => APP_URL,
                'primary_color'   => '#2563EB',
                'logo_path'       => '',
                'email_recipient' => $adminEmail,
                'smtp_host'       => 'smtp.hostinger.com',
                'smtp_port'       => '465',
                'smtp_user'       => '',
                'smtp_pass'       => '',
                'smtp_from_name'  => 'Forma4 Imobiliária',
                'smtp_from_email' => '',
                'smtp_secure'     => 'ssl',
            ];

            $stmtSetting = $pdo->prepare(
                'INSERT INTO `settings` (`key_name`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            foreach ($defaultSettings as $k => $v) {
                $stmtSetting->execute([$k, $v]);
            }

            // 4. Usuário admin
            $hash   = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmtU  = $pdo->prepare(
                'INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `password` = VALUES(`password`)'
            );
            $stmtU->execute([$adminName, strtolower($adminEmail), $hash, 'admin']);
            $userId = $pdo->lastInsertId() ?: 1;

            // 5. Formulário padrão – Autorização de Venda com Exclusividade
            $defaultFields = json_encode([
                ['id'=>'f1',  'name'=>'tipo_imovel',          'label'=>'Tipo de Imóvel',                   'type'=>'select',   'required'=>true,  'placeholder'=>'',                          'options'=>'Casa,Apartamento,Terreno,Sala Comercial,Galpão,Chácara,Outro'],
                ['id'=>'f2',  'name'=>'endereco',              'label'=>'Endereço Completo',                'type'=>'text',     'required'=>true,  'placeholder'=>'Rua, número, complemento',  'options'=>''],
                ['id'=>'f3',  'name'=>'bairro',                'label'=>'Bairro',                           'type'=>'text',     'required'=>true,  'placeholder'=>'',                          'options'=>''],
                ['id'=>'f4',  'name'=>'cidade',                'label'=>'Cidade',                           'type'=>'text',     'required'=>true,  'placeholder'=>'',                          'options'=>''],
                ['id'=>'f5',  'name'=>'estado',                'label'=>'Estado (UF)',                      'type'=>'text',     'required'=>true,  'placeholder'=>'Ex: SP',                    'options'=>''],
                ['id'=>'f6',  'name'=>'cep',                   'label'=>'CEP',                              'type'=>'text',     'required'=>true,  'placeholder'=>'00000-000',                 'options'=>''],
                ['id'=>'f7',  'name'=>'area_total',            'label'=>'Área Total (m²)',                  'type'=>'number',   'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f8',  'name'=>'area_construida',       'label'=>'Área Construída (m²)',             'type'=>'number',   'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f9',  'name'=>'quartos',               'label'=>'Quartos',                          'type'=>'number',   'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f10', 'name'=>'suites',                'label'=>'Suítes',                           'type'=>'number',   'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f11', 'name'=>'banheiros',             'label'=>'Banheiros',                        'type'=>'number',   'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f12', 'name'=>'garagem',               'label'=>'Vagas de Garagem',                 'type'=>'number',   'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f13', 'name'=>'descricao_imovel',      'label'=>'Descrição do Imóvel',              'type'=>'textarea', 'required'=>false, 'placeholder'=>'Características adicionais','options'=>''],
                ['id'=>'f14', 'name'=>'contratante_nome',      'label'=>'Nome Completo do Proprietário',    'type'=>'text',     'required'=>true,  'placeholder'=>'',                          'options'=>''],
                ['id'=>'f15', 'name'=>'contratante_cpf',       'label'=>'CPF do Proprietário',              'type'=>'text',     'required'=>true,  'placeholder'=>'000.000.000-00',            'options'=>''],
                ['id'=>'f16', 'name'=>'contratante_rg',        'label'=>'RG do Proprietário',               'type'=>'text',     'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f17', 'name'=>'contratante_estado_civil','label'=>'Estado Civil',                   'type'=>'select',   'required'=>false, 'placeholder'=>'',                          'options'=>'Solteiro(a),Casado(a),Divorciado(a),Viúvo(a),União Estável'],
                ['id'=>'f18', 'name'=>'contratante_telefone',  'label'=>'Telefone / Celular',               'type'=>'text',     'required'=>true,  'placeholder'=>'(00) 00000-0000',           'options'=>''],
                ['id'=>'f19', 'name'=>'contratante_email',     'label'=>'E-mail do Proprietário',           'type'=>'text',     'required'=>false, 'placeholder'=>'',                          'options'=>''],
                ['id'=>'f20', 'name'=>'valor_minimo',          'label'=>'Valor Mínimo de Venda (R$)',        'type'=>'number',   'required'=>true,  'placeholder'=>'',                          'options'=>''],
                ['id'=>'f21', 'name'=>'comissao',              'label'=>'Comissão Imobiliária (%)',          'type'=>'number',   'required'=>true,  'placeholder'=>'Ex: 6',                     'options'=>''],
                ['id'=>'f22', 'name'=>'prazo_exclusividade',   'label'=>'Prazo de Exclusividade (dias)',     'type'=>'number',   'required'=>true,  'placeholder'=>'Ex: 90',                    'options'=>''],
                ['id'=>'f23', 'name'=>'forma_pagamento',       'label'=>'Formas de Pagamento Aceitas',      'type'=>'textarea', 'required'=>false, 'placeholder'=>'Ex: À vista, financiamento','options'=>''],
                ['id'=>'f24', 'name'=>'data_assinatura',       'label'=>'Data da Autorização',              'type'=>'date',     'required'=>true,  'placeholder'=>'',                          'options'=>''],
                ['id'=>'f25', 'name'=>'observacoes',           'label'=>'Observações Adicionais',           'type'=>'textarea', 'required'=>false, 'placeholder'=>'',                          'options'=>''],
            ], JSON_UNESCAPED_UNICODE);

            $stmtF = $pdo->prepare(
                'INSERT IGNORE INTO `forms` (`title`, `slug`, `description`, `fields`, `pdf_template`, `is_active`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, 1, ?)'
            );
            $stmtF->execute([
                'Autorização de Venda com Exclusividade',
                'autorizacao-venda-exclusividade',
                'Contrato de autorização de venda de imóvel com exclusividade para a imobiliária.',
                $defaultFields,
                'authorization',
                $userId,
            ]);

            $step    = 2;
            $success = 'Instalação concluída com sucesso!';

        } catch (PDOException $e) {
            $error = 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        } catch (Exception $e) {
            $error = 'Erro: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Instalação – FORMA4 Imobiliária</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.1); max-width: 520px; width: 100%; padding: 36px 32px; }
        .logo-area { text-align: center; margin-bottom: 24px; }
        .logo-area h1 { font-size: 22px; font-weight: 700; color: #2563eb; }
        .logo-area p  { font-size: 12.5px; color: #94a3b8; margin-top: 4px; }
        h2 { font-size: 16px; font-weight: 700; margin-bottom: 20px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #334155; }
        input { width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13.5px; font-family: inherit; outline: none; transition: border-color .15s; }
        input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .btn { display: block; width: 100%; padding: 11px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 8px; font-family: inherit; transition: background .15s; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 12px 15px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
        .alert-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .info-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px 15px; font-size: 12.5px; color: #92400e; margin-bottom: 20px; }
        .step-list { list-style: none; padding: 0; counter-reset: step; }
        .step-list li { counter-increment: step; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; font-size: 13.5px; }
        .step-list li::before { content: counter(step); background: #2563eb; color: #fff; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
        .btn-link { display: inline-block; margin-top: 14px; background: #2563eb; color: #fff; padding: 10px 22px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 13.5px; }
        .btn-link:hover { background: #1d4ed8; text-decoration: none; }
        .warn-delete { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px 15px; font-size: 12.5px; color: #991b1b; margin-top: 16px; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo-area">
        <h1>⚙️ FORMA4 Imobiliária</h1>
        <p>Assistente de instalação do sistema</p>
    </div>

    <?php if ($step === 2): ?>
        <!-- SUCESSO -->
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <ul class="step-list">
            <li>Banco de dados configurado com as tabelas necessárias.</li>
            <li>Configurações padrão inseridas.</li>
            <li>Usuário administrador criado com o e-mail informado.</li>
            <li>Formulário padrão <strong>"Autorização de Venda com Exclusividade"</strong> criado.</li>
        </ul>
        <div class="warn-delete">
            ⚠️ IMPORTANTE: Delete ou renomeie o arquivo <code>install.php</code> agora!<br>
            Deixá-lo acessível representa um risco de segurança.
        </div>
        <a href="<?= htmlspecialchars(APP_URL) ?>/login.php" class="btn-link">Ir para o Login →</a>

    <?php else: ?>
        <!-- FORMULÁRIO DE INSTALAÇÃO -->
        <div class="info-box">
            ℹ️ Certifique-se de ter configurado o arquivo <code>includes/config.php</code> com os dados do banco antes de continuar.
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <h2>Criar Administrador</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="admin_name">Nome completo</label>
                <input type="text" id="admin_name" name="admin_name" required
                       value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"
                       placeholder="Ex: João Silva">
            </div>
            <div class="form-group">
                <label for="admin_email">E-mail do administrador</label>
                <input type="email" id="admin_email" name="admin_email" required
                       value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                       placeholder="admin@suaimobiliaria.com.br">
            </div>
            <div class="form-group">
                <label for="admin_pass">Senha (mín. 6 caracteres)</label>
                <input type="password" id="admin_pass" name="admin_pass" required minlength="6">
            </div>
            <div class="form-group">
                <label for="admin_pass2">Confirmar senha</label>
                <input type="password" id="admin_pass2" name="admin_pass2" required minlength="6">
            </div>
            <button type="submit" class="btn">Instalar o Sistema</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
