<?php
/**
 * Formulário público – Proposta para Fiança de Locação
 * A4 Imobiliária
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$db       = Database::getInstance();
$settings = getAllSettings();
$appName  = $settings['app_name'] ?? APP_NAME;
$logoPath = $settings['logo_path'] ?? '';
$primaryColor = $settings['primary_color'] ?? '#0e4f6c';

$slug = 'proposta-fiador';
$form = $db->fetchOne('SELECT * FROM forms WHERE slug = ? LIMIT 1', [$slug]);
if (!$form) {
    $db->query(
        "INSERT INTO forms (title, slug, description, fields, pdf_template, is_active) VALUES (?, ?, ?, ?, ?, 1)",
        ['Proposta para Fiança de Locação', $slug, 'Proposta de fiador para locação de imóvel – A4 Imobiliária.', '[]', 'locacao']
    );
    $form = $db->fetchOne('SELECT * FROM forms WHERE slug = ? LIMIT 1', [$slug]);
}
if (!$form) {
    http_response_code(500);
    die('<h2 style="font-family:sans-serif;padding:40px">Erro ao carregar formulário.</h2>');
}

$success = isset($_GET['sucesso']);
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($csrfToken)) {
        $errors[] = 'Token de segurança inválido. Recarregue a página e tente novamente.';
    } else {
        $textFields = [
            // Imóvel
            'imovel_situado','codigo','bairro_imovel','valor_mensal','renda_familiar','destinacao',
            // 1º Proponente
            'p1_nome','p1_rg','p1_orgao_emissor','p1_nascimento','p1_cpf_cnpj',
            'p1_profissao','p1_empresa','p1_estado_civil',
            'p1_conjuge','p1_conjuge_nascimento','p1_conjuge_rg','p1_conjuge_orgao','p1_conjuge_cpf',
            'p1_endereco','p1_complemento','p1_bairro','p1_cidade','p1_cep','p1_uf',
            'p1_telefone1','p1_telefone2','p1_telefone3','p1_email1','p1_email2',
            // 2º Proponente
            'p2_nome','p2_rg','p2_orgao_emissor','p2_nascimento','p2_cpf_cnpj',
            'p2_profissao','p2_empresa','p2_estado_civil',
            'p2_conjuge','p2_conjuge_nascimento','p2_conjuge_rg','p2_conjuge_orgao','p2_conjuge_cpf',
            'p2_endereco','p2_complemento','p2_bairro','p2_cidade','p2_cep','p2_uf',
            'p2_telefone1','p2_telefone2','p2_telefone3','p2_email1','p2_email2',
            // Informações complementares
            'info1_nome','info1_contatos',
            'info2_nome','info2_contatos',
        ];

        $data = [];
        foreach ($textFields as $f) {
            $data[$f] = trim(strip_tags($_POST[$f] ?? ''));
        }

        $required = ['p1_nome','p1_rg','p1_cpf_cnpj','p1_endereco','p1_bairro','p1_cidade','p1_cep','p1_uf','p1_telefone1','p1_email1'];
        foreach ($required as $r) {
            if (empty($data[$r])) {
                $label = str_replace(['p1_','_'], ['', ' '], $r);
                $errors[] = ucfirst(trim($label)) . ' (1º Proponente) é obrigatório.';
            }
        }

        if (empty($errors)) {
            $uploaded = $_FILES['doc_anexo'] ?? null;
            if ($uploaded && $uploaded['error'] === UPLOAD_ERR_OK && $uploaded['size'] > 0) {
                $saved = uploadFile($uploaded, DOCS_PATH, ALLOWED_DOC_TYPES);
                $data['doc_anexo'] = $saved ? 'docs/' . $saved : '';
            } else {
                $data['doc_anexo'] = '';
            }

            $ip = getClientIP();
            $db->query(
                'INSERT INTO submissions (form_id, data, ip_address, created_at) VALUES (?, ?, ?, NOW())',
                [(int)$form['id'], json_encode($data, JSON_UNESCAPED_UNICODE), $ip]
            );
            $submId = $db->lastInsertId();

            try {
                require_once __DIR__ . '/includes/pdf.php';
                $submission = ['id' => $submId, 'data' => $data, 'created_at' => date('Y-m-d H:i:s'), 'ip_address' => $ip];
                $pdfRelPath = generatePDF($form, $submission, $settings);
                if ($pdfRelPath) {
                    $db->query('UPDATE submissions SET pdf_path = ? WHERE id = ?', [$pdfRelPath, $submId]);
                }
            } catch (Exception $e) {
                error_log('[FORMA4 PDF FIADOR] ' . $e->getMessage());
                $pdfRelPath = null;
            }

            try {
                require_once __DIR__ . '/includes/mailer.php';
                $submission['pdf_path'] = $pdfRelPath ?? null;
                $sent = sendSubmissionEmail($submission, $form, $pdfRelPath ?? '', $settings);
                if ($sent) {
                    $db->query('UPDATE submissions SET email_sent = 1 WHERE id = ?', [$submId]);
                }
            } catch (Exception $e) {
                error_log('[FORMA4 MAIL FIADOR] ' . $e->getMessage());
            }

            header('Location: ' . APP_URL . '/proposta-fiador.php?sucesso=1');
            exit;
        }
    }
}

$logoSrc = '';
if ($logoPath && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $logoPath)) {
    $logoSrc = APP_URL . '/uploads/logos/' . rawurlencode($logoPath);
}

$old = $_POST ?? [];
function fv(string $k, string $d = ''): string {
    global $old; return htmlspecialchars($old[$k] ?? $d, ENT_QUOTES, 'UTF-8');
}
function fRadio(string $n, string $v): string {
    global $old; return ($old[$n] ?? '') === $v ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta para Fiança de Locação — <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --primary: <?= e($primaryColor) ?>; --border: #b0bec5; --label: #546e7a; --text: #1a2332; }
        body { font-family: 'Inter', sans-serif; background: #e8edf2; min-height: 100vh; padding: 20px 10px 60px; }
        .doc-wrap { max-width: 820px; margin: 0 auto; background: #fff; }

        /* Header */
        .doc-header { background: #fff; border-bottom: 3px solid var(--primary); padding: 24px 28px 18px; text-align: center; }
        .doc-header img { max-height: 80px; margin-bottom: 12px; }
        .doc-header h1 { font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text); }
        .req-note { font-size: 11.5px; color: #c0392b; margin-top: 6px; }

        /* Body */
        .doc-body { padding: 24px 28px; }

        /* Erros */
        .error-box { background: #fff5f5; border: 1px solid #feb2b2; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .error-box p { color: #c53030; font-size: 13px; line-height: 1.7; }

        /* Seção */
        .section { margin-bottom: 20px; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #fff; background: var(--text); padding: 5px 10px; margin-bottom: 0; }

        /* Grade */
        .fg { border: 1px solid var(--border); width: 100%; }
        .fr { display: flex; border-bottom: 1px solid var(--border); }
        .fr:last-child { border-bottom: none; }
        .fc { flex: 1; border-right: 1px solid var(--border); padding: 4px 8px 5px; min-width: 0; display: flex; flex-direction: column; }
        .fc:last-child { border-right: none; }
        .fc label { font-size: 9.5px; color: var(--label); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; margin-bottom: 1px; }
        .fc input[type=text], .fc input[type=email], .fc input[type=date], .fc input[type=number] {
            border: none; outline: none; font-size: 13px; font-family: 'Inter', sans-serif;
            color: var(--text); background: transparent; width: 100%; padding: 2px 0;
        }

        .fc-xs  { flex: 0 0 70px; }
        .fc-sm  { flex: 0 0 120px; }
        .fc-md  { flex: 0 0 180px; }
        .fc-lg  { flex: 0 0 240px; }
        .fc-full { flex: 1 1 100%; }

        /* Radio / check rows */
        .check-row { display: flex; flex-wrap: wrap; gap: 5px 16px; padding: 6px 10px; border: 1px solid var(--border); border-top: none; align-items: center; }
        .check-row.first { border-top: 1px solid var(--border); }
        .check-row label { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text); cursor: pointer; }
        .check-row input[type=radio] { width: 13px; height: 13px; accent-color: var(--primary); cursor: pointer; }
        .check-row .row-label { font-size: 10px; font-weight: 700; color: var(--label); text-transform: uppercase; margin-right: 6px; }
        .obs-note { font-size: 11px; color: #c0392b; padding: 4px 10px; border: 1px solid var(--border); border-top: none; background: #fff8f8; }

        /* Upload */
        .upload-area { border: 2px dashed var(--border); border-radius: 6px; padding: 20px; text-align: center; background: #f8fafc; margin-top: 10px; }
        .upload-area input[type=file] { font-size: 13px; color: var(--text); }
        .upload-area p { font-size: 11px; color: #94a3b8; margin-top: 6px; }
        .upload-btn { display: inline-block; background: var(--primary); color: #fff; border: none; padding: 9px 22px; font-size: 13px; font-weight: 600; border-radius: 5px; cursor: pointer; font-family: 'Inter',sans-serif; margin-bottom: 8px; }

        /* Actions */
        .form-actions { padding: 20px 28px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .form-actions p { font-size: 12px; color: #64748b; }
        .btn-enviar { background: var(--primary); color: #fff; border: none; padding: 12px 44px; font-size: 15px; font-weight: 600; border-radius: 7px; cursor: pointer; font-family: 'Inter', sans-serif; transition: opacity .15s; }
        .btn-enviar:hover { opacity: .88; }

        /* Sucesso */
        .success-wrap { text-align: center; padding: 70px 40px; }
        .success-icon { width: 72px; height: 72px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; }
        .success-wrap h2 { font-size: 22px; font-weight: 700; color: #15803d; margin-bottom: 8px; }
        .success-wrap p { color: #64748b; font-size: 14px; line-height: 1.7; }

        /* Footer */
        .doc-footer-bar { background: #0a3d52; color: rgba(255,255,255,.8); font-size: 10.5px; text-align: center; padding: 10px 20px; line-height: 1.7; }
        .doc-footer-bar a { color: rgba(255,255,255,.85); }

        /* Responsivo */
        @media (max-width: 860px) {
            .fr { flex-wrap: wrap; }
            .fc-xs { flex: 1 1 60px; } .fc-sm { flex: 1 1 100px; } .fc-md { flex: 1 1 140px; } .fc-lg { flex: 1 1 180px; }
        }
        @media (max-width: 640px) {
            body { padding: 0; background: #fff; }
            .doc-body { padding: 16px 14px; }
            .fr { flex-direction: column; }
            .fc, .fc-xs, .fc-sm, .fc-md, .fc-lg, .fc-full { flex: 1 1 100%; border-right: none; border-bottom: 1px solid var(--border); padding: 7px 10px; }
            .fc:last-child, .fc-xs:last-child, .fc-sm:last-child, .fc-md:last-child, .fc-lg:last-child, .fc-full:last-child { border-bottom: none; }
            .form-actions { flex-direction: column; padding: 16px 14px; text-align: center; }
            .btn-enviar { width: 100%; }
        }
    </style>
</head>
<body>
<div class="doc-wrap">

    <!-- HEADER -->
    <div class="doc-header">
        <?php if ($logoSrc): ?>
            <img src="<?= e($logoSrc) ?>" alt="<?= e($appName) ?>">
        <?php endif; ?>
        <h1>Proposta para Fiança de Locação</h1>
        <p class="req-note">** indica campos obrigatórios</p>
    </div>

    <?php if ($success): ?>
    <div class="success-wrap">
        <div class="success-icon">✓</div>
        <h2>Proposta enviada com sucesso!</h2>
        <p>Seus dados foram registrados.<br>Em breve entraremos em contato. Obrigado!</p>
    </div>
    <?php else: ?>

    <form method="POST" action="" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>
        <div class="doc-body">

            <?php if ($errors): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?><p>&#9888; <?= e($err) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ══ DADOS DO IMÓVEL ══ -->
            <div class="section">
                <div class="fg" style="margin-bottom:4px;">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Proponho-me a alcançar o imóvel situado <span style="color:#c0392b">**</span></label>
                            <input type="text" name="imovel_situado" value="<?= fv('imovel_situado') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-sm">
                            <label>Código n° <span style="color:#c0392b">**</span></label>
                            <input type="text" name="codigo" value="<?= fv('codigo') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Bairro <span style="color:#c0392b">**</span></label>
                            <input type="text" name="bairro_imovel" value="<?= fv('bairro_imovel') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Valor mensal R$ <span style="color:#c0392b">**</span></label>
                            <input type="text" name="valor_mensal" value="<?= fv('valor_mensal') ?>" placeholder="0,00">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Renda familiar R$ <span style="color:#c0392b">**</span></label>
                            <input type="text" name="renda_familiar" value="<?= fv('renda_familiar') ?>" placeholder="0,00">
                        </div>
                    </div>
                </div>
                <div class="check-row first">
                    <span class="row-label">Destinação a que se deve o imóvel <span style="color:#c0392b">**</span></span>
                    <?php foreach (['Residencial','Comercial','Misto'] as $d): ?>
                        <label><input type="radio" name="destinacao" value="<?= $d ?>" <?= fRadio('destinacao',$d) ?>><?= $d ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ══ DADOS DO PRIMEIRO PROPONENTE ══ -->
            <div class="section">
                <div class="section-title">Dados do Primeiro Proponente</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome/Razão social <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_nome" value="<?= fv('p1_nome') ?>" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Rg <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_rg" value="<?= fv('p1_rg') ?>" required>
                        </div>
                        <div class="fc fc-md">
                            <label>Órgão emissor <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_orgao_emissor" value="<?= fv('p1_orgao_emissor') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Nascimento <span style="color:#c0392b">**</span></label>
                            <input type="date" name="p1_nascimento" value="<?= fv('p1_nascimento') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>CPF/CNPJ <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_cpf_cnpj" value="<?= fv('p1_cpf_cnpj') ?>" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Profissão/Atividade <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_profissao" value="<?= fv('p1_profissao') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Empresa onde trabalha <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_empresa" value="<?= fv('p1_empresa') ?>">
                        </div>
                    </div>
                </div>

                <div class="check-row first">
                    <span class="row-label">Estado Civil <span style="color:#c0392b">**</span></span>
                    <?php foreach (['Solteiro','Casado','Divorciado'] as $ec): ?>
                        <label><input type="radio" name="p1_estado_civil" value="<?= $ec ?>" <?= fRadio('p1_estado_civil',$ec) ?>><?= $ec ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="obs-note">Obs: Caso a opção marcada for casado, preencher as informações do cônjuge.</div>

                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Cônjuge</label>
                            <input type="text" name="p1_conjuge" value="<?= fv('p1_conjuge') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Nascimento</label>
                            <input type="date" name="p1_conjuge_nascimento" value="<?= fv('p1_conjuge_nascimento') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Rg</label>
                            <input type="text" name="p1_conjuge_rg" value="<?= fv('p1_conjuge_rg') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Órgão emissor</label>
                            <input type="text" name="p1_conjuge_orgao" value="<?= fv('p1_conjuge_orgao') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Cpf</label>
                            <input type="text" name="p1_conjuge_cpf" value="<?= fv('p1_conjuge_cpf') ?>" data-mask="cpf" placeholder="000.000.000-00">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Endereço atual <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_endereco" value="<?= fv('p1_endereco') ?>" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Complemento <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_complemento" value="<?= fv('p1_complemento') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Bairro <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_bairro" value="<?= fv('p1_bairro') ?>" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Cidade <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_cidade" value="<?= fv('p1_cidade') ?>" required>
                        </div>
                        <div class="fc fc-sm">
                            <label>Cep <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_cep" value="<?= fv('p1_cep') ?>" data-mask="cep" placeholder="00000-000" required>
                        </div>
                        <div class="fc fc-xs">
                            <label>UF <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_uf" value="<?= fv('p1_uf') ?>" maxlength="2" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Telefone 1 <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_telefone1" value="<?= fv('p1_telefone1') ?>" data-mask="phone" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Telefone 2 <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_telefone2" value="<?= fv('p1_telefone2') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Telefone 3 <span style="color:#c0392b">**</span></label>
                            <input type="text" name="p1_telefone3" value="<?= fv('p1_telefone3') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>E-mail 1 <span style="color:#c0392b">**</span></label>
                            <input type="email" name="p1_email1" value="<?= fv('p1_email1') ?>" required>
                        </div>
                        <div class="fc fc-full">
                            <label>E-mail 2 <span style="color:#c0392b">**</span></label>
                            <input type="email" name="p1_email2" value="<?= fv('p1_email2') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ DADOS DO SEGUNDO PROPONENTE ══ -->
            <div class="section">
                <div class="section-title">Dados do Segundo Proponente</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome/Razão social</label>
                            <input type="text" name="p2_nome" value="<?= fv('p2_nome') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Rg</label>
                            <input type="text" name="p2_rg" value="<?= fv('p2_rg') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Órgão emissor</label>
                            <input type="text" name="p2_orgao_emissor" value="<?= fv('p2_orgao_emissor') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Nascimento</label>
                            <input type="date" name="p2_nascimento" value="<?= fv('p2_nascimento') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>CPF/CNPJ</label>
                            <input type="text" name="p2_cpf_cnpj" value="<?= fv('p2_cpf_cnpj') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Profissão/Atividade</label>
                            <input type="text" name="p2_profissao" value="<?= fv('p2_profissao') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Empresa onde trabalha</label>
                            <input type="text" name="p2_empresa" value="<?= fv('p2_empresa') ?>">
                        </div>
                    </div>
                </div>

                <div class="check-row first">
                    <span class="row-label">Estado Civil</span>
                    <?php foreach (['Solteiro','Casado','Divorciado','Viúvo'] as $ec): ?>
                        <label><input type="radio" name="p2_estado_civil" value="<?= $ec ?>" <?= fRadio('p2_estado_civil',$ec) ?>><?= $ec ?></label>
                    <?php endforeach; ?>
                </div>

                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Cônjuge</label>
                            <input type="text" name="p2_conjuge" value="<?= fv('p2_conjuge') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Nascimento</label>
                            <input type="date" name="p2_conjuge_nascimento" value="<?= fv('p2_conjuge_nascimento') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Rg</label>
                            <input type="text" name="p2_conjuge_rg" value="<?= fv('p2_conjuge_rg') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Órgão emissor</label>
                            <input type="text" name="p2_conjuge_orgao" value="<?= fv('p2_conjuge_orgao') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Cpf</label>
                            <input type="text" name="p2_conjuge_cpf" value="<?= fv('p2_conjuge_cpf') ?>" data-mask="cpf" placeholder="000.000.000-00">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Endereço atual</label>
                            <input type="text" name="p2_endereco" value="<?= fv('p2_endereco') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Complemento</label>
                            <input type="text" name="p2_complemento" value="<?= fv('p2_complemento') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Bairro</label>
                            <input type="text" name="p2_bairro" value="<?= fv('p2_bairro') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Cidade</label>
                            <input type="text" name="p2_cidade" value="<?= fv('p2_cidade') ?>">
                        </div>
                        <div class="fc fc-sm">
                            <label>Cep</label>
                            <input type="text" name="p2_cep" value="<?= fv('p2_cep') ?>" data-mask="cep" placeholder="00000-000">
                        </div>
                        <div class="fc fc-xs">
                            <label>UF</label>
                            <input type="text" name="p2_uf" value="<?= fv('p2_uf') ?>" maxlength="2">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Telefone 1</label>
                            <input type="text" name="p2_telefone1" value="<?= fv('p2_telefone1') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Telefone 2</label>
                            <input type="text" name="p2_telefone2" value="<?= fv('p2_telefone2') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Telefone 3</label>
                            <input type="text" name="p2_telefone3" value="<?= fv('p2_telefone3') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>E-mail 1</label>
                            <input type="email" name="p2_email1" value="<?= fv('p2_email1') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>E-mail 2</label>
                            <input type="email" name="p2_email2" value="<?= fv('p2_email2') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ INFORMAÇÕES COMPLEMENTARES 1º PROPONENTE ══ -->
            <div class="section">
                <div class="section-title">Informações Complementares do Primeiro Proponente</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome <span style="color:#c0392b">**</span></label>
                            <input type="text" name="info1_nome" value="<?= fv('info1_nome') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Contatos <span style="color:#c0392b">**</span></label>
                            <input type="text" name="info1_contatos" value="<?= fv('info1_contatos') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ INFORMAÇÕES COMPLEMENTARES 2º PROPONENTE ══ -->
            <div class="section">
                <div class="section-title">Informações Complementares do Segundo Proponente</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome <span style="color:#c0392b">**</span></label>
                            <input type="text" name="info2_nome" value="<?= fv('info2_nome') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Contatos <span style="color:#c0392b">**</span></label>
                            <input type="text" name="info2_contatos" value="<?= fv('info2_contatos') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ DOCUMENTOS ══ -->
            <div class="section">
                <div class="section-title">Anexar Documentos <span style="font-weight:400;text-transform:none;font-size:11px"> **</span></div>
                <div class="upload-area">
                    <p style="font-size:13px;font-weight:600;color:#334155;margin-bottom:8px;">Solte os arquivos aqui ou</p>
                    <label class="upload-btn" for="doc_anexo_input">Anexar arquivos</label>
                    <input id="doc_anexo_input" type="file" name="doc_anexo" accept=".jpg,.gif,.mp4,.pdf,.png,.doc,.docx,.xls,.xlsx" style="display:none;" onchange="this.previousElementSibling.previousElementSibling.textContent = this.files[0]?.name || 'Solte os arquivos aqui ou'">
                    <p>Tipos de arquivo aceitos: jpg, gif, mp4, pdf, png. Máx. tamanho do arquivo: 10 MB.</p>
                </div>
            </div>

            <!-- Nota legal -->
            <p style="font-size:11px;color:#64748b;text-align:center;margin-top:10px;line-height:1.6;">
                A presente proposta é apenas de interesse de participação na locação como fiador, não tendo valor contratual.
                Com seja aprovada, os dados nela contidos serão utilizados para confecção do contrato de locação, onde estarão estabelecidas as cláusulas contratuais.
            </p>

        </div><!-- /.doc-body -->

        <div class="form-actions">
            <p>Campos com <span style="color:#c0392b">**</span> são obrigatórios.</p>
            <button type="submit" class="btn-enviar">Enviar</button>
        </div>
    </form>

    <?php endif; ?>

    <div class="doc-footer-bar">
        <?= e($appName) ?> &nbsp;|&nbsp; Orgulhosamente desenvolvido com Adenilton.
    </div>
</div>

<script>
document.querySelectorAll('[data-mask]').forEach(function(el) {
    el.addEventListener('input', function() {
        var v = el.value.replace(/\D/g, '');
        if (el.dataset.mask === 'cpf') {
            v = v.slice(0,11).replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3-$4');
        } else if (el.dataset.mask === 'cep') {
            v = v.slice(0,8).replace(/(\d{5})(\d)/,'$1-$2');
        } else if (el.dataset.mask === 'phone') {
            v = v.slice(0,11);
            v = v.length <= 10 ? v.replace(/(\d{2})(\d)/,'($1) $2').replace(/(\d{4})(\d)/,'$1-$2') : v.replace(/(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2');
        }
        el.value = v;
    });
});
</script>
</body>
</html>
