<?php
/**
 * Painel de Configurações do Sistema
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db          = Database::getInstance();
$sysSettings = getAllSettings();
$errors      = [];

// -------------------------------------------------------
// Salva configurações
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        // Modo de envio de e-mail
        $mailDriver = in_array($_POST['mail_driver'] ?? '', ['smtp', 'php_mail']) ? $_POST['mail_driver'] : 'smtp';
        setSetting('mail_driver', $mailDriver);
        $sysSettings['mail_driver'] = $mailDriver;

        // Campos de texto simples
        $textFields = [
            'app_name', 'app_url', 'primary_color',
            'email_recipient',
            'smtp_host', 'smtp_port', 'smtp_user',
            'smtp_from_name', 'smtp_from_email', 'smtp_secure',
        ];

        foreach ($textFields as $key) {
            if (isset($_POST[$key])) {
                $val = trim(strip_tags($_POST[$key]));
                // Valida e-mail
                if (in_array($key, ['email_recipient', 'smtp_from_email', 'smtp_user']) && !empty($val)) {
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "O campo \"" . $key . "\" não contém um e-mail válido.";
                        continue;
                    }
                }
                // Valida cor
                if ($key === 'primary_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                    $val = '#2563EB';
                }
                // Valida porta SMTP
                if ($key === 'smtp_port') {
                    $val = (string) max(1, min(65535, (int) $val));
                }
                setSetting($key, $val);
                $sysSettings[$key] = $val;
            }
        }

        // Senha SMTP: só atualiza se foi preenchida
        if (!empty($_POST['smtp_pass'])) {
            $pass = trim($_POST['smtp_pass']);
            setSetting('smtp_pass', $pass);
            $sysSettings['smtp_pass'] = $pass;
        }

        // Upload de logo
        if (!empty($_FILES['logo_upload']['name'])) {
            $logoFile = uploadFile($_FILES['logo_upload'], LOGO_PATH, ALLOWED_IMG_TYPES);

            if ($logoFile) {
                // Remove logo antigo
                $oldLogo = $sysSettings['logo_path'] ?? '';
                if ($oldLogo && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $oldLogo)) {
                    @unlink(LOGO_PATH . DIRECTORY_SEPARATOR . $oldLogo);
                }
                setSetting('logo_path', $logoFile);
                $sysSettings['logo_path'] = $logoFile;
            } else {
                $errors[] = 'Falha ao fazer upload do logo. Verifique o tipo (JPG, PNG, GIF, WEBP) e tamanho (máx. 2 MB).';
            }
        }

        if (empty($errors)) {
            setFlash('Configurações salvas com sucesso!', 'success');
            header('Location: ' . APP_URL . '/admin/settings.php');
            exit;
        }
    }
}

// -------------------------------------------------------
// Remover logo
// -------------------------------------------------------
if (isset($_GET['remove_logo']) && validateCSRF($_GET['csrf'] ?? '')) {
    $old = $sysSettings['logo_path'] ?? '';
    if ($old && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $old)) {
        @unlink(LOGO_PATH . DIRECTORY_SEPARATOR . $old);
    }
    setSetting('logo_path', '');
    setFlash('Logo removido.', 'success');
    header('Location: ' . APP_URL . '/admin/settings.php');
    exit;
}

$appUrl     = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$logoFile   = $sysSettings['logo_path'] ?? '';
$pageTitle  = 'Configurações';
$activeMenu = 'settings';
require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h2>Configurações do Sistema</h2>
        <p>Personalize o nome, visual, e-mail e SMTP do sistema.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-16">
        <?= implode('<br>', array_map('e', $errors)) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="save_settings" value="1">

    <div class="grid-2col">

        <!-- Coluna esquerda -->
        <div>
            <!-- Geral -->
            <div class="card mb-16">
                <div class="card-header"><h3 class="card-title">Configurações Gerais</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="app_name">Nome do Sistema</label>
                        <input class="form-control" type="text" id="app_name" name="app_name"
                               value="<?= e($sysSettings['app_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="app_url">URL Base do Site</label>
                        <input class="form-control" type="url" id="app_url" name="app_url"
                               value="<?= e($sysSettings['app_url'] ?? '') ?>"
                               placeholder="https://seudominio.com.br">
                        <p class="form-text">Sem barra final. Usado para gerar links de formulários.</p>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label" for="primary_color">Cor Principal</label>
                        <div style="display:flex;gap:10px;align-items:center;">
                            <input class="form-control" type="color" id="primary_color" name="primary_color"
                                   value="<?= e($sysSettings['primary_color'] ?? '#2563EB') ?>"
                                   style="width:60px;height:40px;padding:3px 5px;">
                            <input class="form-control" type="text" id="primary_color_text"
                                   value="<?= e($sysSettings['primary_color'] ?? '#2563EB') ?>"
                                   maxlength="7" placeholder="#2563EB" style="max-width:120px;">
                        </div>
                        <p class="form-text">Cor utilizada no cabeçalho, botões e PDFs.</p>
                    </div>
                </div>
            </div>

            <!-- Logo -->
            <div class="card mb-16">
                <div class="card-header"><h3 class="card-title">Logo da Empresa</h3></div>
                <div class="card-body">
                    <?php if ($logoFile && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $logoFile)): ?>
                        <div style="margin-bottom:12px;">
                            <p class="form-label" style="margin-bottom:6px;">Logo Atual:</p>
                            <div class="logo-preview" style="width:auto;max-width:200px;height:70px;">
                                <img id="logo_preview_img" src="<?= $appUrl ?>/uploads/logos/<?= e($logoFile) ?>" alt="Logo">
                            </div>
                            <a href="?remove_logo=1&csrf=<?= urlencode(generateCSRF()) ?>"
                               class="btn btn-danger btn-sm" style="margin-top:8px;"
                               data-confirm="Remover o logo atual?">Remover Logo</a>
                        </div>
                    <?php else: ?>
                        <div class="logo-preview mb-8" style="width:120px;height:60px;">
                            <img id="logo_preview_img" src="" alt="" style="display:none;">
                            <span class="no-logo">Sem logo</span>
                        </div>
                    <?php endif; ?>

                    <div class="form-group mb-0">
                        <label class="form-label" for="logo_upload">Upload de Logo</label>
                        <input class="form-control" type="file" id="logo_upload" name="logo_upload"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <p class="form-text">JPG, PNG, GIF ou WEBP. Máx. 2 MB. Recomendado: fundo transparente (PNG).</p>
                    </div>
                </div>
            </div>

            <!-- E-mail receptor -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">E-mail de Recebimento</h3></div>
                <div class="card-body">
                    <div class="form-group mb-0">
                        <label class="form-label" for="email_recipient">Destinatário das Notificações</label>
                        <input class="form-control" type="email" id="email_recipient" name="email_recipient"
                               value="<?= e($sysSettings['email_recipient'] ?? '') ?>"
                               placeholder="admin@suaimobiliaria.com.br">
                        <p class="form-text">Cada novo envio de formulário será enviado para este e-mail.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna direita: SMTP -->
        <div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Configuração de E-mail</h3></div>
                <div class="card-body">

                    <!-- Modo de envio -->
                    <div class="form-group">
                        <label class="form-label" for="mail_driver">Modo de Envio</label>
                        <select class="form-control" id="mail_driver" name="mail_driver" onchange="toggleSmtpFields(this.value)">
                            <option value="smtp"     <?= ($sysSettings['mail_driver'] ?? 'smtp') === 'smtp'     ? 'selected' : '' ?>>SMTP (PHPMailer)</option>
                            <option value="php_mail" <?= ($sysSettings['mail_driver'] ?? 'smtp') === 'php_mail' ? 'selected' : '' ?>>PHP mail() nativo</option>
                        </select>
                        <p class="form-text">Use <strong>PHP mail()</strong> se o servidor já está configurado com sendmail/exim. Use <strong>SMTP</strong> para autenticação externa (Gmail, Hostinger, etc).</p>
                    </div>

                    <div id="smtp_fields_wrapper">
                    <div class="form-group">
                        <label class="form-label" for="smtp_host">Servidor SMTP</label>
                        <input class="form-control" type="text" id="smtp_host" name="smtp_host"
                               value="<?= e($sysSettings['smtp_host'] ?? '') ?>"
                               placeholder="smtp.hostinger.com">
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="smtp_port">Porta</label>
                            <input class="form-control" type="number" id="smtp_port" name="smtp_port"
                                   value="<?= e($sysSettings['smtp_port'] ?? '465') ?>"
                                   min="1" max="65535">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="smtp_secure">Criptografia</label>
                            <select class="form-control" id="smtp_secure" name="smtp_secure">
                                <option value="ssl"  <?= ($sysSettings['smtp_secure'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL (porta 465)</option>
                                <option value="tls"  <?= ($sysSettings['smtp_secure'] ?? '') === 'tls'  ? 'selected' : '' ?>>TLS (porta 587)</option>
                                <option value="none" <?= ($sysSettings['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>Sem criptografia</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="smtp_user">Usuário SMTP (e-mail)</label>
                        <input class="form-control" type="text" id="smtp_user" name="smtp_user"
                               value="<?= e($sysSettings['smtp_user'] ?? '') ?>"
                               placeholder="noreply@suaimobiliaria.com.br"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="smtp_pass">Senha SMTP</label>
                        <input class="form-control" type="password" id="smtp_pass" name="smtp_pass"
                               placeholder="Deixe em branco para manter a senha atual"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="smtp_from_name">Nome do Remetente</label>
                        <input class="form-control" type="text" id="smtp_from_name" name="smtp_from_name"
                               value="<?= e($sysSettings['smtp_from_name'] ?? '') ?>"
                               placeholder="Forma4 Imobiliária">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label" for="smtp_from_email">E-mail do Remetente</label>
                        <input class="form-control" type="email" id="smtp_from_email" name="smtp_from_email"
                               value="<?= e($sysSettings['smtp_from_email'] ?? '') ?>"
                               placeholder="noreply@suaimobiliaria.com.br">
                        <p class="form-text">Deve ser o mesmo e-mail cadastrado no SMTP para evitar erros de autenticação.</p>
                    </div>

                    </div><!-- /#smtp_fields_wrapper -->

                </div>
            </div>
        </div>

    </div><!-- /grid -->

    <div style="margin-top:20px;display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary" style="padding:11px 32px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Salvar Configurações
        </button>
    </div>
</form>

<script>
// Sincroniza o color picker com o campo de texto
var colorPicker = document.getElementById('primary_color');
var colorText   = document.getElementById('primary_color_text');

if (colorPicker && colorText) {
    colorPicker.addEventListener('input', function () { colorText.value = this.value; });
    colorText.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
            colorPicker.value = this.value;
        }
    });
}

// Mostra/esconde campos SMTP conforme o modo selecionado
function toggleSmtpFields(driver) {
    var wrapper = document.getElementById('smtp_fields_wrapper');
    if (!wrapper) return;
    wrapper.style.display = driver === 'smtp' ? '' : 'none';
}

// Aplica o estado inicial ao carregar a página
(function () {
    var sel = document.getElementById('mail_driver');
    if (sel) toggleSmtpFields(sel.value);
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
