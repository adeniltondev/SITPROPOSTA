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
$db->query("UPDATE forms SET pdf_template = 'proposta-fiador' WHERE slug = ?", [$slug]);
$form = $db->fetchOne('SELECT * FROM forms WHERE slug = ? LIMIT 1', [$slug]);
if (!$form) {
    $db->query(
        "INSERT INTO forms (title, slug, description, fields, pdf_template, is_active) VALUES (?, ?, ?, ?, ?, 1)",
        ['Proposta para Fiança de Locação', $slug, 'Proposta de fiador para locação – A4 Imobiliária.', '[]', 'proposta-fiador']
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
            'imovel_situado','codigo','bairro_imovel','valor_mensal','renda_familiar','destinacao',
            'p1_nome','p1_rg','p1_orgao_emissor','p1_nascimento','p1_cpf_cnpj',
            'p1_profissao','p1_empresa','p1_estado_civil',
            'p1_conjuge','p1_conjuge_nascimento','p1_conjuge_rg','p1_conjuge_orgao','p1_conjuge_cpf',
            'p1_endereco','p1_complemento','p1_bairro','p1_cidade','p1_cep','p1_uf',
            'p1_telefone1','p1_telefone2','p1_telefone3','p1_email1','p1_email2',
            'p2_nome','p2_rg','p2_orgao_emissor','p2_nascimento','p2_cpf_cnpj',
            'p2_profissao','p2_empresa','p2_estado_civil',
            'p2_conjuge','p2_conjuge_nascimento','p2_conjuge_rg','p2_conjuge_orgao','p2_conjuge_cpf',
            'p2_endereco','p2_complemento','p2_bairro','p2_cidade','p2_cep','p2_uf',
            'p2_telefone1','p2_telefone2','p2_telefone3','p2_email1','p2_email2',
            'info1_nome','info1_contatos','info2_nome','info2_contatos',
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
            $data['doc_anexo'] = '';
            $uploadedFiles = $_FILES['doc_anexo'] ?? null;
            if ($uploadedFiles && is_array($uploadedFiles['name'])) {
                $savedPaths = [];
                foreach ($uploadedFiles['name'] as $i => $name) {
                    if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK && $uploadedFiles['size'][$i] > 0) {
                        $singleFile = [
                            'name'     => $uploadedFiles['name'][$i],
                            'type'     => $uploadedFiles['type'][$i],
                            'tmp_name' => $uploadedFiles['tmp_name'][$i],
                            'error'    => $uploadedFiles['error'][$i],
                            'size'     => $uploadedFiles['size'][$i],
                        ];
                        $saved = uploadFile($singleFile, DOCS_PATH, ALLOWED_DOC_TYPES);
                        if ($saved) $savedPaths[] = 'docs/' . $saved;
                    }
                }
                $data['doc_anexo'] = implode(', ', $savedPaths);
            }
            $ip = getClientIP();
            $db->query(
                'INSERT INTO submissions (form_id, data, ip_address, created_at) VALUES (?, ?, ?, NOW())',
                [(int)$form['id'], json_encode($data, JSON_UNESCAPED_UNICODE), $ip]
            );
            $submId = $db->lastInsertId();
            $pdfRelPath = null;
            try {
                require_once __DIR__ . '/includes/pdf.php';
                $submission = ['id' => $submId, 'data' => $data, 'created_at' => date('Y-m-d H:i:s'), 'ip_address' => $ip];
                $pdfRelPath = generatePDF($form, $submission, $settings);
                if ($pdfRelPath) $db->query('UPDATE submissions SET pdf_path = ? WHERE id = ?', [$pdfRelPath, $submId]);
            } catch (Exception $e) { error_log('[FORMA4 PDF FIADOR] ' . $e->getMessage()); }
            try {
                require_once __DIR__ . '/includes/mailer.php';
                $submission['pdf_path'] = $pdfRelPath;
                $sent = sendSubmissionEmail($submission, $form, $pdfRelPath ?? '', $settings);
                if ($sent) $db->query('UPDATE submissions SET email_sent = 1 WHERE id = ?', [$submId]);
            } catch (Exception $e) { error_log('[FORMA4 MAIL FIADOR] ' . $e->getMessage()); }
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
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary:
                <?= e($primaryColor) ?>
            ;
            --border: #b0bec5;
            --label: #546e7a;
            --text: #1a2332;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #e8edf2;
            min-height: 100vh;
            padding: 20px 10px 60px;
        }

        .doc-wrap {
            max-width: 940px;
            margin: 0 auto;
            background: #fff;
        }

        /* ── Banner header ── */
        .doc-header {
            background: linear-gradient(145deg, #f8fcff 0%, #edf5fa 100%);
            position: relative;
            overflow: hidden;
            border: 1px solid #d0e2ec;
            border-bottom: 4px solid #0f6788;
        }

        .doc-header::before {
            content: none;
        }

        .doc-header::after {
            content: none;
        }

        .header-ribbon {
            background: linear-gradient(90deg, #08384d 0%, #0c5b78 65%, #117398 100%);
            color: rgba(255, 255, 255, .92);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 28px;
            font-size: 11px;
            letter-spacing: .35px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .header-main {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 22px 30px;
        }

        .header-ribbon .dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, .75);
            border-radius: 50%;
            margin: 0 6px;
            vertical-align: middle;
        }

        .header-ribbon strong {
            color: #fff;
            font-weight: 800;
        }

        .doc-header .logo-box {
            position: absolute;
            border-radius: 10px;
            padding: 10px 14px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 204px;
            min-height: 208px;
        }

        .doc-header .logo-box img {
            max-height: 147px;
            max-width: 186px;
            object-fit: contain;
        }

        .doc-header .logo-box .logo-text {
            color: #12465d;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.15;
            text-align: center;
        }

        .doc-header .logo-box .logo-text span {
            font-size: 10px;
            font-weight: 600;
            color: #3a6474;
            display: block;
            opacity: .92;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .doc-header .doc-title {
            flex: 1;
            text-align: right;
        }

        .doc-title .kicker {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 4px;
            border: 1px solid #c8dde8;
            background: #e8f3f9;
            color: #0f607e;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .75px;
            text-transform: uppercase;
            margin-bottom: 9px;
        }

        .doc-title h1 {
            color: #163d4f;
            font-size: 34px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .8px;
            line-height: 1.08;
            text-wrap: balance;
        }

        .doc-title h1 span {
            display: block;
            margin-top: 5px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 1.8px;
            color: #2f6880;
        }

        .doc-title p {
            color: #4a6978;
            font-size: 12.5px;
            margin-top: 7px;
            letter-spacing: .1px;
        }

        .doc-meta {
            margin-top: 11px;
            color: #53798b;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .45px;
            text-transform: uppercase;
        }

        /* ── Form body ── */
        .doc-body {
            padding: 28px 36px 24px;
        }

        /* ── Erros ── */
        .error-box {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .error-box p {
            color: #c53030;
            font-size: 13px;
            line-height: 1.7;
        }

        /* ── Seção ── */
        .section {
            margin-bottom: 22px;
        }

        .section-title {
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text);
            border-bottom: 2px solid var(--text);
            padding-bottom: 5px;
            margin-bottom: 0;
        }

        /* ── Grade de campos ── */
        .fg {
            border: 1px solid var(--border);
            border-collapse: collapse;
            width: 100%;
        }

        .fr {
            display: flex;
            border-bottom: 1px solid var(--border);
        }

        .fr:last-child {
            border-bottom: none;
        }

        .fc {
            flex: 1;
            border-right: 1px solid var(--border);
            padding: 4px 8px 5px;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .fc:last-child {
            border-right: none;
        }

        .fc label {
            font-size: 9.5px;
            color: var(--label);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
            margin-bottom: 1px;
        }

        .fc input[type=text],
        .fc input[type=email],
        .fc input[type=date],
        .fc input[type=number] {
            border: none;
            outline: none;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background: transparent;
            width: 100%;
            padding: 2px 0;
        }

        .fc textarea {
            border: none;
            outline: none;
            font-size: 12.5px;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background: transparent;
            width: 100%;
            resize: none;
            min-height: 54px;
            padding: 2px 0;
        }

        /* Tamanhos de coluna */
        .fc-xs {
            flex: 0 0 80px;
        }

        .fc-sm {
            flex: 0 0 140px;
        }

        .fc-md {
            flex: 0 0 200px;
        }

        .fc-lg {
            flex: 0 0 260px;
        }

        .fc-full {
            flex: 1 1 100%;
        }

        /* ── Checkboxes / radios ── */
        .check-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 18px;
            padding: 7px 10px;
            border-bottom: 1px solid var(--border);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
            background: #fff;
            align-items: center;
        }

        .check-row.first,
        .check-row:first-child {
            border-top: 1px solid var(--border);
        }

        .check-row label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--text);
            cursor: pointer;
            white-space: nowrap;
        }

        .check-row input[type=checkbox],
        .check-row input[type=radio] {
            width: 13px;
            height: 13px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .check-row .row-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--label);
            text-transform: uppercase;
            letter-spacing: .3px;
            margin-right: 6px;
        }

        .obs-note {
            font-size: 11px;
            color: #c0392b;
            padding: 4px 10px;
            border-bottom: 1px solid var(--border);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
            background: #fff8f8;
        }

        /* ── Upload de documentos ── */
        .docs-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 12px;
        }

        .doc-upload-item {
            background: #f8fafc;
            border: 1px dashed #b0bec5;
            border-radius: 6px;
            padding: 12px 14px;
            text-align: center;
        }

        .doc-upload-item label.doc-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--label);
            display: block;
            margin-bottom: 6px;
        }

        .doc-upload-item input[type=file] {
            font-size: 12px;
            width: 100%;
            color: #374151;
        }

        .doc-upload-item p {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .upload-btn-label {
            display: inline-block;
            background: var(--primary);
            color: #fff !important;
            padding: 9px 22px;
            font-size: 13px;
            font-weight: 600;
            text-transform: none !important;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 8px;
            font-family: 'Inter', sans-serif;
        }

        /* ── Rodapé do documento ── */
        .doc-footer-bar {
            background: #0a3d52;
            color: rgba(255, 255, 255, .8);
            font-size: 10.5px;
            text-align: center;
            padding: 10px 20px;
            line-height: 1.7;
        }

        .doc-footer-bar a {
            color: rgba(255, 255, 255, .85);
        }

        /* ── Botão submit ── */
        .form-actions {
            padding: 20px 36px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .form-actions p {
            font-size: 12px;
            color: #64748b;
        }

        .btn-enviar {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 14px 44px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 7px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            letter-spacing: .3px;
            transition: opacity .15s;
        }

        .btn-enviar:hover {
            opacity: .88;
        }

        /* ── Sucesso ── */
        .success-wrap {
            text-align: center;
            padding: 70px 40px;
        }

        .success-icon {
            width: 72px;
            height: 72px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
        }

        .success-wrap h2 {
            font-size: 24px;
            font-weight: 700;
            color: #15803d;
            margin-bottom: 8px;
        }

        .success-wrap p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        /* ── RESPONSIVO ── */

        /* Tablet */
        @media (max-width: 860px) {
            body {
                padding: 12px 6px 48px;
            }

            .doc-wrap {
                border-radius: 0;
            }

            .header-main {
                padding: 18px 20px;
                gap: 16px;
            }

            .doc-title h1 {
                font-size: 28px;
            }

            .doc-body {
                padding: 22px 20px 20px;
            }

            .form-actions {
                padding: 16px 20px;
            }

            .fr {
                flex-wrap: wrap;
            }

            .fc-xs {
                flex: 1 1 70px;
            }

            .fc-sm {
                flex: 1 1 120px;
            }

            .fc-md {
                flex: 1 1 160px;
            }

            .fc-lg {
                flex: 1 1 200px;
            }
        }

        /* Mobile */
        @media (max-width: 640px) {
            body {
                padding: 0;
                background: #fff;
            }

            .doc-wrap {
                box-shadow: none;
            }

            .doc-body {
                padding: 16px 14px 18px;
            }

            .doc-header {
                border-left: none;
                border-right: none;
                border-top: none;
                border-radius: 0;
            }

            .header-ribbon {
                padding: 8px 12px;
                font-size: 9.5px;
                justify-content: center;
                flex-wrap: wrap;
                gap: 4px;
            }

            .header-main {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 14px 14px 16px;
                gap: 12px;
            }

            .doc-header .doc-title {
                text-align: center;
            }

            .doc-title h1 {
                font-size: 22px;
            }

            .doc-title h1 span {
                font-size: 11px;
                letter-spacing: 1px;
            }

            .doc-title p {
                font-size: 11px;
            }

            .doc-header .logo-box {
                position: relative;
                min-width: 110px;
                min-height: unset;
                padding: 8px 10px;
            }

            .doc-header .logo-box img {
                max-height: 80px;
                max-width: 140px;
            }

            .doc-meta {
                margin-top: 8px;
                font-size: 10px;
            }

            .form-actions {
                flex-direction: column;
                padding: 16px 14px;
                text-align: center;
            }

            .btn-enviar {
                width: 100%;
                padding: 14px 20px;
            }

            .fr {
                flex-direction: column;
                border-bottom: 1px solid var(--border);
            }

            .fr:last-child {
                border-bottom: none;
            }

            .fc,
            .fc-xs,
            .fc-sm,
            .fc-md,
            .fc-lg,
            .fc-full {
                flex: 1 1 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding: 7px 10px;
            }

            .fc:last-child,
            .fc-xs:last-child,
            .fc-sm:last-child,
            .fc-md:last-child,
            .fc-lg:last-child,
            .fc-full:last-child {
                border-bottom: none;
            }

            .fc input[type=text],
            .fc input[type=email],
            .fc input[type=date],
            .fc input[type=number] {
                font-size: 14px;
                padding: 4px 0;
            }

            .fc textarea {
                font-size: 13px;
                min-height: 64px;
            }

            .check-row {
                flex-wrap: wrap;
                gap: 8px 14px;
                padding: 10px 10px;
            }

            .check-row label {
                font-size: 13px;
            }

            .docs-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 12px;
            }

            .success-wrap {
                padding: 48px 20px;
            }

            .success-wrap h2 {
                font-size: 20px;
            }
        }

        /* Extra pequeno */
        @media (max-width: 380px) {
            .doc-title h1 {
                font-size: 18px;
            }

            .header-ribbon {
                font-size: 8.5px;
            }
        }
    </style>
</head>
<body>

<div class="doc-wrap">

    <!-- HEADER -->
    <div class="doc-header">
        <div class="header-ribbon">
            <span>Formulário Oficial</span>
            <span><span class="dot"></span> Preenchimento Online <span class="dot"></span></span>
            <span><strong><?= e($appName) ?></strong></span>
        </div>
        <div class="header-main">
            <div class="logo-box">
                <?php if ($logoSrc): ?>
                    <img src="<?= e($logoSrc) ?>" alt="<?= e($appName) ?>">
                <?php else: ?>
                    <div class="logo-text"><?= e($appName) ?><span>Imobiliária</span></div>
                <?php endif; ?>
            </div>
            <div class="doc-title">
                <span class="kicker">Formulário Oficial</span>
                <h1>Proposta para Fiança<span>de Locação</span></h1>
                <p>Preencha os dados do(s) fiador(es) para a proposta de locação</p>
                <div class="doc-meta"><span style="color:#c0392b">**</span> indica campos obrigatórios</div>
            </div>
        </div>
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
                <div class="fg">
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
                <div class="check-row" style="border-top:1px solid var(--border);">
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
                            <label>Complemento</label>
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
                            <label>Telefone 2</label>
                            <input type="text" name="p1_telefone2" value="<?= fv('p1_telefone2') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Telefone 3</label>
                            <input type="text" name="p1_telefone3" value="<?= fv('p1_telefone3') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>E-mail 1 <span style="color:#c0392b">**</span></label>
                            <input type="email" name="p1_email1" value="<?= fv('p1_email1') ?>" required>
                        </div>
                        <div class="fc fc-full">
                            <label>E-mail 2</label>
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

            <!-- ══ INFORMAÇÕES COMPLEMENTARES 1º ══ -->
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

            <!-- ══ INFORMAÇÕES COMPLEMENTARES 2º ══ -->
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
                <div class="section-title">Anexar Documentos <span style="font-weight:400;text-transform:none;font-size:11px;color:#64748b"> (obrigatório)</span></div>
                <div class="docs-grid">
                    <div class="doc-upload-item">
                        <label class="doc-label">Documentos</label>
                        <p style="margin-bottom:10px;">Solte os arquivos aqui ou</p>
                        <label class="upload-btn-label" for="doc_anexo_id">Anexar arquivos</label>
                        <input id="doc_anexo_id" type="file" name="doc_anexo[]" accept=".jpg,.gif,.mp4,.pdf,.png,.doc,.docx,.xls,.xlsx" multiple style="display:none;">
                        <div id="doc_anexo_preview" style="margin:6px 0;min-height:18px;"></div>
                        <p>Tipos de arquivo aceitos: jpg, gif, mp4, pdf, png. Máx. tamanho do arquivo: 10 MB.</p>
                    </div>
                </div>
            </div>

            <p style="font-size:11px;color:#374151;text-align:justify;margin-top:14px;line-height:1.7;padding:12px 14px;background:#f8fafc;border-left:3px solid var(--primary);">
                A presente proposta é apenas de interesse de participação na locação como fiador, não tendo valor contratual. Com seja aprovada, os dados nela contidos serão utilizados para confecção do contrato de locação, onde estarão estabelecidas as cláusulas contratuais.
            </p>

        </div><!-- /.doc-body -->

        <div class="form-actions">
            <p>Campos com <span style="color:#c0392b">**</span> são obrigatórios.</p>
            <button type="submit" class="btn-enviar">Enviar</button>
        </div>
    </form>

    <?php endif; ?>

    <div class="doc-footer-bar">
        <?= e($appName) ?> &nbsp;|&nbsp; Av. Hermes Fontes, nº 1524, Bairro Luzia – CEP 49.048.010 – Aracaju/SE
        &nbsp;|&nbsp; (79) 3304-0000 / 99691-0000 &nbsp;|&nbsp;
        <a href="mailto:contato@a4imobiliaria.com.br">contato@a4imobiliaria.com.br</a>
    </div>
</div>

<script>
document.getElementById('doc_anexo_id').addEventListener('change', function() {
    var preview = document.getElementById('doc_anexo_preview');
    if (!this.files.length) { preview.innerHTML = ''; return; }
    var html = '';
    Array.from(this.files).forEach(function(f) {
        html += '<div style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:#1a2332;background:#e8f3f9;border:1px solid #c8dde8;border-radius:4px;padding:3px 8px;margin:2px 0;">'
              + '<span style="color:#0f607e;font-size:13px;">&#128206;</span>'
              + '<span>' + f.name + '</span>'
              + '<span style="color:#94a3b8;margin-left:auto;">' + (f.size > 1048576 ? (f.size/1048576).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB') + '</span>'
              + '</div>';
    });
    preview.innerHTML = html;
});

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
