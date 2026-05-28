<?php
/**
 * Página pública do formulário
 * URL: /form.php?slug=nome-do-formulario
 *
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// Obtém o slug via GET (sanitizado)
$slug = trim(preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? '')));

if (empty($slug)) {
    http_response_code(404);
    die('Formulário não encontrado.');
}

$db   = Database::getInstance();
$form = $db->fetchOne(
    'SELECT * FROM forms WHERE slug = ? AND is_active = 1 LIMIT 1',
    [$slug]
);

if (!$form) {
    http_response_code(404);
    die('Formulário não encontrado ou inativo.');
}

$fields    = decodeFields($form['fields']);
$settings  = getAllSettings();
$appName   = e($settings['app_name'] ?? APP_NAME);
$appUrl    = rtrim($settings['app_url'] ?? APP_URL, '/');
$logoFile  = $settings['logo_path'] ?? '';
$primaryColor = e($settings['primary_color'] ?? '#2563EB');

// Mostra estado de sucesso quando redirecionado após envio
$submitted = !empty($_GET['sent']) && (int) $_GET['sent'] === 1;

// Verifica se algum campo é do tipo file (para definir enctype correto)
$hasFileField = false;
foreach ($fields as $f) {
    if (($f['type'] ?? '') === 'file') {
        $hasFileField = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($form['title']) ?> — <?= $appName ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $appUrl ?>/assets/css/style.css">
    <style>
        :root { --primary: <?= $primaryColor ?>; }

        /* ---- Grid do formulário público ---- */
        .pf-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 18px;
        }
        .pf-grid .form-group           { margin-bottom: 18px; }
        .pf-grid .form-group.col-full  { grid-column: 1 / -1; }
        .pf-grid .form-group.col-half  { grid-column: span 1; }

        /* File upload estilizado */
        .file-upload-wrapper {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 18px 16px;
            background: #f8fafc;
            text-align: center;
            transition: border-color .18s;
        }
        .file-upload-wrapper:hover { border-color: var(--primary); }
        .file-upload-wrapper input[type=file] {
            display: block;
            width: 100%;
            font-size: 13px;
            color: #334155;
            cursor: pointer;
        }
        .file-upload-icon {
            width: 38px; height: 38px;
            background: var(--primary-light);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 8px;
        }
        .file-upload-icon svg { width: 20px; height: 20px; color: var(--primary); }
        .file-upload-title {
            font-size: 13px; font-weight: 600;
            color: #334155; margin-bottom: 2px;
        }

        /* Separável por seção */
        .pf-section-title {
            grid-column: 1 / -1;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--muted, #94a3b8);
            border-bottom: 1px solid var(--border, #e2e8f0);
            padding-bottom: 6px;
            margin-bottom: 4px;
            margin-top: 8px;
        }

        @media (max-width: 560px) {
            .pf-grid { grid-template-columns: 1fr; }
            .pf-grid .form-group.col-half { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>
<div class="public-form-wrapper">

    <!-- Logo (acima do card) -->
    <?php if ($logoFile && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $logoFile)): ?>
    <div class="public-form-logo" style="margin-bottom:16px;">
        <img src="<?= $appUrl ?>/uploads/logos/<?= e($logoFile) ?>" alt="<?= $appName ?>" style="max-height:55px;">
    </div>
    <?php endif; ?>

    <div class="public-form-card">
        <!-- Cabeçalho colorido -->
        <div class="public-form-header">
            <h1><?= e($form['title']) ?></h1>
            <?php if (!empty($form['description'])): ?>
                <p><?= e($form['description']) ?></p>
            <?php endif; ?>
        </div>

        <div class="public-form-body">
            <!-- Estado de sucesso (mostrado após redirect do submit.php) -->
            <div id="successState" class="form-success-state" style="display:<?= $submitted ? 'block' : 'none' ?>;">
                <div class="form-success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h2>Formulário enviado!</h2>
                <p>Seus dados foram recebidos com sucesso.<br>Em breve você receberá uma confirmação.</p>
            </div>

            <!-- Formulário principal -->
            <form id="publicForm" method="POST" action="<?= $appUrl ?>/submit.php"
                  <?= $hasFileField ? 'enctype="multipart/form-data"' : '' ?>
                  novalidate <?= $submitted ? 'style="display:none;"' : '' ?>>
                <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                <?= csrfField() ?>

                <div class="pf-grid">

                <?php foreach ($fields as $field): ?>
                    <?php
                    $fName        = preg_replace('/[^a-zA-Z0-9_]/', '', $field['name'] ?? '');
                    $fLabel       = e($field['label'] ?? $fName);
                    $fType        = $field['type'] ?? 'text';
                    $fRequired    = !empty($field['required']);
                    $fPlaceholder = e($field['placeholder'] ?? '');
                    $fOptions     = array_filter(array_map('trim', explode(',', $field['options'] ?? '')));

                    // Tipos que ocupam largura total
                    $isFullWidth = in_array($fType, ['textarea', 'file', 'checkbox'], true);
                    $colClass    = $isFullWidth ? 'col-full' : 'col-half';

                    // Máscara automática
                    $dataMask = '';
                    if ($fName === 'contratante_cpf')          $dataMask = 'data-mask="cpf"';
                    elseif ($fName === 'contratante_telefone') $dataMask = 'data-mask="phone"';
                    elseif ($fName === 'cep')                  $dataMask = 'data-mask="cep"';
                    ?>
                    <div class="form-group <?= $colClass ?>">
                        <?php if ($fType !== 'checkbox'): ?>
                        <label class="form-label" for="field_<?= e($fName) ?>">
                            <?= $fLabel ?>
                            <?php if ($fRequired): ?><span class="required">*</span><?php endif; ?>
                        </label>
                        <?php endif; ?>

                        <?php if ($fType === 'textarea'): ?>
                            <textarea
                                class="form-control"
                                id="field_<?= e($fName) ?>"
                                name="<?= e($fName) ?>"
                                placeholder="<?= $fPlaceholder ?>"
                                rows="3"
                                <?= $fRequired ? 'required' : '' ?>
                            ></textarea>

                        <?php elseif ($fType === 'select'): ?>
                            <select
                                class="form-control"
                                id="field_<?= e($fName) ?>"
                                name="<?= e($fName) ?>"
                                <?= $fRequired ? 'required' : '' ?>
                            >
                                <option value="">Selecione...</option>
                                <?php foreach ($fOptions as $opt): ?>
                                    <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($fType === 'checkbox'): ?>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    id="field_<?= e($fName) ?>"
                                    name="<?= e($fName) ?>"
                                    value="1"
                                    <?= $fRequired ? 'required' : '' ?>
                                >
                                <label for="field_<?= e($fName) ?>"><?= $fPlaceholder ?: $fLabel ?></label>
                            </div>

                        <?php elseif ($fType === 'file'): ?>
                            <div class="file-upload-wrapper">
                                <div class="file-upload-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                                </div>
                                <p class="file-upload-title">Anexar arquivo</p>
                                <input
                                    type="file"
                                    id="field_<?= e($fName) ?>"
                                    name="<?= e($fName) ?>"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                                    <?= $fRequired ? 'required' : '' ?>
                                >
                                <p class="form-text" style="margin-top:6px;">
                                    PDF, Word, Excel, JPG, PNG &mdash; máx. 10&nbsp;MB
                                </p>
                            </div>

                        <?php else: ?>
                            <input
                                class="form-control"
                                type="<?= e($fType) ?>"
                                id="field_<?= e($fName) ?>"
                                name="<?= e($fName) ?>"
                                placeholder="<?= $fPlaceholder ?>"
                                <?= $dataMask ?>
                                <?= $fRequired ? 'required' : '' ?>
                                <?= $fType === 'number' ? 'min="0" step="any"' : '' ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                </div><!-- /.pf-grid -->

                <div style="margin-top:24px;">
                    <button type="submit" class="btn btn-primary w-full" id="submitBtn" style="justify-content:center;padding:14px;font-size:15px;">
                        Enviar Formulário
                    </button>
                </div>

                <p class="text-muted text-sm text-center" style="margin-top:10px;">
                    <span class="required">*</span> Campos obrigatórios
                </p>
            </form>
        </div>
    </div>

    <p class="text-muted text-sm" style="margin-top:20px;">
        © <?= date('Y') ?> <?= $appName ?>
    </p>
</div>

<script src="<?= $appUrl ?>/assets/js/app.js"></script>
<script>
document.getElementById('publicForm').addEventListener('submit', function (e) {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
});
</script>
</body>
</html>
