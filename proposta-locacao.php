<?php
/**
 * Formulário público – Proposta de Locação
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

$slug = 'proposta-locacao';
$form = $db->fetchOne('SELECT * FROM forms WHERE slug = ? LIMIT 1', [$slug]);
if (!$form) {
    $db->query(
        "INSERT INTO forms (title, slug, description, fields, pdf_template, is_active) VALUES (?, ?, ?, ?, ?, 1)",
        ['Proposta de Locação', $slug, 'Proposta de locação de imóvel – A4 Imobiliária.', '[]', 'locacao']
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
            'codigo_imovel','prazo_meses','valor_rs','destinacao','data_vencimento','tipo_fianca',
            'nome','nascimento','rg','exp','cpf','nacionalidade','estado_civil',
            'endereco_residencial','bairro','cidade_uf','cep',
            'whatsapp','residencial_fixo','celular','email_contato',
            'conjuge','tipo_residencia','valor_aluguel',
            'tempo_reside_anos','num_dependentes','cria_animal',
            'empresa_trabalha','cargo_funcao',
            'endereco_comercial','bairro_comercial','cidade_uf_comercial','cep_comercial',
            'telefone_fixo_comercial','celular_comercial','email_comercial',
            'tempo_trabalha','renda_mensal',
            'ref1_nome','ref1_relacao','ref1_telefone',
            'ref2_nome','ref2_relacao','ref2_telefone',
            'observacoes',
        ];
        $data = [];
        foreach ($textFields as $f) {
            $data[$f] = trim(strip_tags($_POST[$f] ?? ''));
        }
        $required = ['nome','rg','cpf','endereco_residencial','bairro','cidade_uf','cep','whatsapp','email_contato','empresa_trabalha','renda_mensal'];
        foreach ($required as $r) {
            if (empty($data[$r])) {
                $errors[] = ucfirst(str_replace('_', ' ', $r)) . ' é obrigatório.';
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
            } catch (Exception $e) { error_log('[FORMA4 PDF PROP-LOC] ' . $e->getMessage()); }
            try {
                require_once __DIR__ . '/includes/mailer.php';
                $submission['pdf_path'] = $pdfRelPath;
                $sent = sendSubmissionEmail($submission, $form, $pdfRelPath ?? '', $settings);
                if ($sent) $db->query('UPDATE submissions SET email_sent = 1 WHERE id = ?', [$submId]);
            } catch (Exception $e) { error_log('[FORMA4 MAIL PROP-LOC] ' . $e->getMessage()); }
            header('Location: ' . APP_URL . '/proposta-locacao.php?sucesso=1');
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
    <title>Proposta de Locação — <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: <?= e($primaryColor) ?>;
            --border: #b0bec5;
            --label: #546e7a;
            --text: #1a2332;
        }

        body { font-family: 'Inter', sans-serif; background: #e8edf2; min-height: 100vh; padding: 20px 10px 60px; }

        .doc-wrap { max-width: 940px; margin: 0 auto; background: #fff; }

        /* ── Banner header ── */
        .doc-header {
            background: linear-gradient(145deg, #f8fcff 0%, #edf5fa 100%);
            position: relative; overflow: hidden;
            border: 1px solid #d0e2ec;
            border-bottom: 4px solid #0f6788;
        }
        .doc-header::before, .doc-header::after { content: none; }

        .header-ribbon {
            background: linear-gradient(90deg, #08384d 0%, #0c5b78 65%, #117398 100%);
            color: rgba(255,255,255,.92);
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 9px 28px; font-size: 11px; letter-spacing: .35px;
            text-transform: uppercase; font-weight: 600;
        }
        .header-ribbon .dot {
            display: inline-block; width: 4px; height: 4px;
            background: rgba(255,255,255,.75); border-radius: 50%;
            margin: 0 6px; vertical-align: middle;
        }
        .header-ribbon strong { color: #fff; font-weight: 800; }

        .header-main { display: flex; align-items: center; gap: 24px; padding: 22px 30px; }

        .doc-header .logo-box {
            border-radius: 10px; padding: 10px 14px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            min-width: 148px; min-height: 92px;
        }
        .doc-header .logo-box img { max-height: 147px; max-width: 186px; object-fit: contain; }
        .doc-header .logo-box .logo-text { color: #12465d; font-size: 18px; font-weight: 800; letter-spacing: -.5px; line-height: 1.15; text-align: center; }
        .doc-header .logo-box .logo-text span { font-size: 10px; font-weight: 600; color: #3a6474; display: block; opacity: .92; letter-spacing: .4px; text-transform: uppercase; }

        .doc-header .doc-title { flex: 1; text-align: right; }
        .doc-title .kicker {
            display: inline-block; padding: 5px 9px; border-radius: 4px;
            border: 1px solid #c8dde8; background: #e8f3f9; color: #0f607e;
            font-size: 10px; font-weight: 800; letter-spacing: .75px;
            text-transform: uppercase; margin-bottom: 9px;
        }
        .doc-title h1 { color: #163d4f; font-size: 34px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; line-height: 1.08; }
        .doc-title h1 span { display: block; margin-top: 5px; font-size: 15px; font-weight: 600; letter-spacing: 1.8px; color: #2f6880; }
        .doc-title p { color: #4a6978; font-size: 12.5px; margin-top: 7px; letter-spacing: .1px; }
        .doc-meta { margin-top: 11px; color: #53798b; font-size: 11px; font-weight: 600; letter-spacing: .45px; text-transform: uppercase; }

        /* ── Body ── */
        .doc-body { padding: 28px 36px 24px; }

        /* ── Erros ── */
        .error-box { background: #fff5f5; border: 1px solid #feb2b2; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .error-box p { color: #c53030; font-size: 13px; line-height: 1.7; }

        /* ── Seção ── */
        .section { margin-bottom: 22px; }
        .section-title {
            font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            color: var(--text); border-bottom: 2px solid var(--text); padding-bottom: 5px; margin-bottom: 0;
        }

        /* ── Grade ── */
        .fg { border: 1px solid var(--border); border-collapse: collapse; width: 100%; }
        .fr { display: flex; border-bottom: 1px solid var(--border); }
        .fr:last-child { border-bottom: none; }
        .fc { flex: 1; border-right: 1px solid var(--border); padding: 4px 8px 5px; min-width: 0; display: flex; flex-direction: column; }
        .fc:last-child { border-right: none; }
        .fc label { font-size: 9.5px; color: var(--label); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; margin-bottom: 1px; }
        .fc input[type=text], .fc input[type=email], .fc input[type=date], .fc input[type=number] {
            border: none; outline: none; font-size: 13px; font-family: 'Inter', sans-serif;
            color: var(--text); background: transparent; width: 100%; padding: 2px 0;
        }
        .fc select { border: none; outline: none; font-size: 13px; font-family: 'Inter', sans-serif; color: var(--text); background: transparent; width: 100%; padding: 2px 0; }
        .fc textarea { border: none; outline: none; font-size: 12.5px; font-family: 'Inter', sans-serif; color: var(--text); background: transparent; width: 100%; resize: none; min-height: 72px; padding: 2px 0; }
        .fc-xs  { flex: 0 0 80px; }
        .fc-sm  { flex: 0 0 140px; }
        .fc-md  { flex: 0 0 200px; }
        .fc-lg  { flex: 0 0 260px; }
        .fc-full { flex: 1 1 100%; }

        /* ── Checkbox / Radio rows ── */
        .check-row { display: flex; flex-wrap: wrap; gap: 6px 18px; padding: 7px 10px; border: 1px solid var(--border); border-top: none; background: #fff; align-items: center; }
        .check-row.first { border-top: 1px solid var(--border); }
        .check-row label { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text); cursor: pointer; white-space: nowrap; }
        .check-row input[type=checkbox], .check-row input[type=radio] { width: 13px; height: 13px; cursor: pointer; accent-color: var(--primary); }
        .check-row .row-label { font-size: 10px; font-weight: 700; color: var(--label); text-transform: uppercase; letter-spacing: .3px; margin-right: 6px; }

        .obs-note { font-size: 11px; color: #c0392b; padding: 4px 10px; border: 1px solid var(--border); border-top: none; background: #fff8f8; }

        /* ── Upload ── */
        .docs-grid { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 12px; }
        .doc-upload-item { background: #f8fafc; border: 1px dashed #b0bec5; border-radius: 6px; padding: 16px 18px; text-align: center; }
        .doc-upload-item label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--label); display: block; margin-bottom: 8px; }
        .doc-upload-item input[type=file] { font-size: 12px; width: 100%; color: #374151; }
        .doc-upload-item p { font-size: 10px; color: #94a3b8; margin-top: 4px; }
        .upload-btn-label {
            display: inline-block; background: var(--primary); color: #fff;
            padding: 9px 22px; font-size: 13px; font-weight: 600; border-radius: 5px;
            cursor: pointer; margin-bottom: 8px; font-family: 'Inter', sans-serif;
        }

        /* ── Rodapé documento ── */
        .doc-footer-bar { background: #0a3d52; color: rgba(255,255,255,.8); font-size: 10.5px; text-align: center; padding: 10px 20px; line-height: 1.7; }
        .doc-footer-bar a { color: rgba(255,255,255,.85); }

        /* ── Botão submit ── */
        .form-actions { padding: 20px 36px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .form-actions p { font-size: 12px; color: #64748b; }
        .btn-enviar { background: var(--primary); color: #fff; border: none; padding: 14px 44px; font-size: 15px; font-weight: 600; border-radius: 7px; cursor: pointer; font-family: 'Inter', sans-serif; letter-spacing: .3px; transition: opacity .15s; }
        .btn-enviar:hover { opacity: .88; }

        /* ── Sucesso ── */
        .success-wrap { text-align: center; padding: 70px 40px; }
        .success-icon { width: 72px; height: 72px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; }
        .success-wrap h2 { font-size: 24px; font-weight: 700; color: #15803d; margin-bottom: 8px; }
        .success-wrap p { color: #64748b; font-size: 14px; line-height: 1.7; }

        /* ── Responsivo tablet ── */
        @media (max-width: 860px) {
            body { padding: 12px 6px 48px; }
            .header-main { padding: 18px 20px; gap: 16px; }
            .doc-title h1 { font-size: 28px; }
            .doc-body { padding: 22px 20px 20px; }
            .form-actions { padding: 16px 20px; }
            .fr { flex-wrap: wrap; }
            .fc-xs { flex: 1 1 70px; } .fc-sm { flex: 1 1 120px; } .fc-md { flex: 1 1 160px; } .fc-lg { flex: 1 1 200px; }
        }

        /* ── Responsivo mobile ── */
        @media (max-width: 640px) {
            body { padding: 0; background: #fff; }
            .doc-body { padding: 16px 14px 18px; }
            .doc-header { border-left: none; border-right: none; border-top: none; }
            .header-ribbon { padding: 8px 12px; font-size: 9.5px; justify-content: center; flex-wrap: wrap; gap: 4px; }
            .header-main { flex-direction: column; align-items: center; text-align: center; padding: 14px 14px 16px; gap: 12px; }
            .doc-header .doc-title { text-align: center; }
            .doc-title h1 { font-size: 22px; }
            .doc-header .logo-box { min-width: 110px; min-height: unset; padding: 8px 10px; }
            .doc-header .logo-box img { max-height: 80px; max-width: 140px; }
            .form-actions { flex-direction: column; padding: 16px 14px; text-align: center; }
            .btn-enviar { width: 100%; padding: 14px 20px; }
            .fr { flex-direction: column; }
            .fc, .fc-xs, .fc-sm, .fc-md, .fc-lg, .fc-full { flex: 1 1 100%; border-right: none; border-bottom: 1px solid var(--border); padding: 7px 10px; }
            .fc:last-child, .fc-xs:last-child, .fc-sm:last-child, .fc-md:last-child, .fc-lg:last-child, .fc-full:last-child { border-bottom: none; }
            .fc input[type=text], .fc input[type=email], .fc input[type=date], .fc input[type=number] { font-size: 14px; padding: 4px 0; }
            .fc textarea { font-size: 13px; min-height: 64px; }
            .check-row { flex-wrap: wrap; gap: 8px 14px; padding: 10px 10px; }
            .check-row label { font-size: 13px; }
            .success-wrap { padding: 48px 20px; }
            .success-wrap h2 { font-size: 20px; }
            .docs-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 380px) {
            .doc-title h1 { font-size: 18px; }
            .header-ribbon { font-size: 8.5px; }
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
                <h1>Proposta de Locação</h1>
                <p>Preencha os dados abaixo para solicitar a locação do imóvel</p>
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

            <!-- ══ IMÓVEL DESEJADO ══ -->
            <div class="section">
                <div class="section-title">Imóvel Desejado</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-sm">
                            <label>Código n° <span style="color:#c0392b">**</span></label>
                            <input type="text" name="codigo_imovel" value="<?= fv('codigo_imovel') ?>">
                        </div>
                        <div class="fc fc-sm">
                            <label>Prazo a contratar (Meses) <span style="color:#c0392b">**</span></label>
                            <select name="prazo_meses">
                                <?php foreach ([12,24,30,36,48,60] as $m): ?>
                                    <option value="<?= $m ?>" <?= fv('prazo_meses','12') == $m ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fc fc-full">
                            <label>Valor R$ <span style="color:#c0392b">**</span></label>
                            <input type="text" name="valor_rs" value="<?= fv('valor_rs') ?>" placeholder="0,00">
                        </div>
                    </div>
                </div>
                <div class="obs-note">Obs: a opção de 60 meses, só está disponível para destinação comercial.</div>

                <div class="check-row" style="border-top:1px solid var(--border);margin-top:2px;">
                    <span class="row-label">Destinação <span style="color:#c0392b">**</span></span>
                    <?php foreach (['Residencial','Comercial','Misto'] as $d): ?>
                        <label><input type="radio" name="destinacao" value="<?= $d ?>" <?= fRadio('destinacao',$d) ?>><?= $d ?></label>
                    <?php endforeach; ?>
                </div>

                <div class="fg" style="margin-top:2px;">
                    <div class="fr">
                        <div class="fc fc-sm">
                            <label>Melhor data de vencimento <span style="color:#c0392b">**</span></label>
                            <select name="data_vencimento">
                                <?php for ($d=1;$d<=30;$d++): ?>
                                    <option value="<?= sprintf('%02d',$d) ?>" <?= fv('data_vencimento','01') == sprintf('%02d',$d) ? 'selected' : '' ?>><?= sprintf('%02d',$d) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="check-row" style="border-top:1px solid var(--border);margin-top:2px;">
                    <span class="row-label">Tipo de fiança oferecida <span style="color:#c0392b">**</span></span>
                    <?php foreach (['Fiador pedido','Credpago','Caução locatícia'] as $tf): ?>
                        <label><input type="radio" name="tipo_fianca" value="<?= $tf ?>" <?= fRadio('tipo_fianca',$tf) ?>><?= $tf ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ══ PRETENDENTE A LOCATÁRIO ══ -->
            <div class="section">
                <div class="section-title">Pretendente a Locatário</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome <span style="color:#c0392b">**</span></label>
                            <input type="text" name="nome" value="<?= fv('nome') ?>" required>
                        </div>
                        <div class="fc fc-md">
                            <label>Nascimento <span style="color:#c0392b">**</span></label>
                            <input type="date" name="nascimento" value="<?= fv('nascimento') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Rg <span style="color:#c0392b">**</span></label>
                            <input type="text" name="rg" value="<?= fv('rg') ?>" required>
                        </div>
                        <div class="fc fc-sm">
                            <label>Exp</label>
                            <input type="text" name="exp" value="<?= fv('exp') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Cpf <span style="color:#c0392b">**</span></label>
                            <input type="text" name="cpf" value="<?= fv('cpf') ?>" data-mask="cpf" placeholder="000.000.000-00" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Nacionalidade <span style="color:#c0392b">**</span></label>
                            <input type="text" name="nacionalidade" value="<?= fv('nacionalidade','Brasileiro(a)') ?>">
                        </div>
                    </div>
                </div>

                <div class="check-row first">
                    <span class="row-label">Estado Civil <span style="color:#c0392b">**</span></span>
                    <?php foreach (['Solteiro','Casado','União Estável','Viúvo','Separado judicialmente'] as $ec): ?>
                        <label><input type="radio" name="estado_civil" value="<?= $ec ?>" <?= fRadio('estado_civil',$ec) ?>><?= $ec ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="obs-note">Obs: Caso a opção marcada for casado, preencher as informações do cônjuge.</div>

                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Endereço residencial atual <span style="color:#c0392b">**</span></label>
                            <input type="text" name="endereco_residencial" value="<?= fv('endereco_residencial') ?>" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Bairro <span style="color:#c0392b">**</span></label>
                            <input type="text" name="bairro" value="<?= fv('bairro') ?>" required>
                        </div>
                        <div class="fc fc-lg">
                            <label>Cidade/UF <span style="color:#c0392b">**</span></label>
                            <input type="text" name="cidade_uf" value="<?= fv('cidade_uf') ?>" required>
                        </div>
                        <div class="fc fc-sm">
                            <label>Cep <span style="color:#c0392b">**</span></label>
                            <input type="text" name="cep" value="<?= fv('cep') ?>" data-mask="cep" placeholder="00000-000" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>WhatsApp <span style="color:#c0392b">**</span></label>
                            <input type="text" name="whatsapp" value="<?= fv('whatsapp') ?>" data-mask="phone" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Residencial fixo</label>
                            <input type="text" name="residencial_fixo" value="<?= fv('residencial_fixo') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Celular</label>
                            <input type="text" name="celular" value="<?= fv('celular') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>E-mail de contato <span style="color:#c0392b">**</span></label>
                            <input type="email" name="email_contato" value="<?= fv('email_contato') ?>" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Cônjuge</label>
                            <input type="text" name="conjuge" value="<?= fv('conjuge') ?>">
                        </div>
                    </div>
                </div>

                <div class="check-row" style="border-top:1px solid var(--border);margin-top:2px;">
                    <span class="row-label">Tipo de residência <span style="color:#c0392b">**</span></span>
                    <?php foreach (['Própria','Com os pais','Com parentes'] as $tr): ?>
                        <label><input type="radio" name="tipo_residencia" value="<?= $tr ?>" <?= fRadio('tipo_residencia',$tr) ?>><?= $tr ?></label>
                    <?php endforeach; ?>
                    <label>
                        <input type="radio" name="tipo_residencia" value="Alugado" <?= fRadio('tipo_residencia','Alugado') ?>> Alugado — Valor do aluguel R$
                    </label>
                    <input type="text" name="valor_aluguel" value="<?= fv('valor_aluguel') ?>" placeholder="0,00" style="width:90px;font-size:13px;border:none;border-bottom:1px solid var(--border);outline:none;padding:1px 4px;font-family:inherit;color:var(--text);">
                </div>

                <div class="fg" style="margin-top:2px;">
                    <div class="fr">
                        <div class="fc">
                            <label>Tempo que reside (anos) <span style="color:#c0392b">**</span></label>
                            <input type="number" name="tempo_reside_anos" value="<?= fv('tempo_reside_anos') ?>" min="0">
                        </div>
                        <div class="fc">
                            <label>N° de dependentes <span style="color:#c0392b">**</span></label>
                            <input type="number" name="num_dependentes" value="<?= fv('num_dependentes') ?>" min="0">
                        </div>
                        <div class="fc fc-md">
                            <label>Cria animal <span style="color:#c0392b">**</span></label>
                            <div style="display:flex;gap:12px;padding:3px 0;">
                                <?php foreach (['Sim','Não'] as $v): ?>
                                    <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text);font-weight:400">
                                        <input type="radio" name="cria_animal" value="<?= $v ?>" <?= fRadio('cria_animal',$v) ?>><?= $v ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Empresa onde trabalha <span style="color:#c0392b">**</span></label>
                            <input type="text" name="empresa_trabalha" value="<?= fv('empresa_trabalha') ?>" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Cargo/Função <span style="color:#c0392b">**</span></label>
                            <input type="text" name="cargo_funcao" value="<?= fv('cargo_funcao') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Endereço comercial <span style="color:#c0392b">**</span></label>
                            <input type="text" name="endereco_comercial" value="<?= fv('endereco_comercial') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Bairro <span style="color:#c0392b">**</span></label>
                            <input type="text" name="bairro_comercial" value="<?= fv('bairro_comercial') ?>">
                        </div>
                        <div class="fc fc-lg">
                            <label>Cidade/UF <span style="color:#c0392b">**</span></label>
                            <input type="text" name="cidade_uf_comercial" value="<?= fv('cidade_uf_comercial') ?>">
                        </div>
                        <div class="fc fc-sm">
                            <label>Cep <span style="color:#c0392b">**</span></label>
                            <input type="text" name="cep_comercial" value="<?= fv('cep_comercial') ?>" data-mask="cep" placeholder="00000-000">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Telefone fixo <span style="color:#c0392b">**</span></label>
                            <input type="text" name="telefone_fixo_comercial" value="<?= fv('telefone_fixo_comercial') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Celular <span style="color:#c0392b">**</span></label>
                            <input type="text" name="celular_comercial" value="<?= fv('celular_comercial') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>E-mail <span style="color:#c0392b">**</span></label>
                            <input type="email" name="email_comercial" value="<?= fv('email_comercial') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Tempo que trabalha <span style="color:#c0392b">**</span></label>
                            <input type="text" name="tempo_trabalha" value="<?= fv('tempo_trabalha') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Renda mensal R$ <span style="color:#c0392b">**</span></label>
                            <input type="text" name="renda_mensal" value="<?= fv('renda_mensal') ?>" placeholder="0,00" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ REFERÊNCIAS PESSOAIS ══ -->
            <div class="section">
                <div class="section-title">Referências Pessoais</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome <span style="color:#c0392b">**</span></label>
                            <input type="text" name="ref1_nome" value="<?= fv('ref1_nome') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Qual relação <span style="color:#c0392b">**</span></label>
                            <input type="text" name="ref1_relacao" value="<?= fv('ref1_relacao') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Telefone <span style="color:#c0392b">**</span></label>
                            <input type="text" name="ref1_telefone" value="<?= fv('ref1_telefone') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome <span style="color:#c0392b">**</span></label>
                            <input type="text" name="ref2_nome" value="<?= fv('ref2_nome') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Qual relação <span style="color:#c0392b">**</span></label>
                            <input type="text" name="ref2_relacao" value="<?= fv('ref2_relacao') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Telefone <span style="color:#c0392b">**</span></label>
                            <input type="text" name="ref2_telefone" value="<?= fv('ref2_telefone') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Observações</label>
                            <textarea name="observacoes" rows="4"><?= fv('observacoes') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ DOCUMENTOS ══ -->
            <div class="section">
                <div class="section-title">Anexar Documentos <span style="font-weight:400;text-transform:none;font-size:11px;color:#64748b"> (obrigatório)</span></div>
                <div class="docs-grid">
                    <div class="doc-upload-item">
                        <label>Documentos</label>
                        <p style="margin-bottom:10px;">Solte os arquivos aqui ou</p>
                        <label class="upload-btn-label" for="doc_anexo_id">Anexar arquivos</label>
                        <input id="doc_anexo_id" type="file" name="doc_anexo[]" accept=".jpg,.gif,.mp4,.pdf,.png,.doc,.docx,.xls,.xlsx" multiple style="display:none;">
                        <div id="doc_anexo_preview" style="margin:6px 0;min-height:18px;"></div>
                        <p>Tipos de arquivo aceitos: jpg, gif, mp4, pdf, png. Máx. tamanho do arquivo: 10 MB.</p>
                    </div>
                </div>
            </div>

            <p style="font-size:11px;color:#64748b;text-align:justify;margin-top:14px;line-height:1.7;padding:12px 14px;background:#f8fafc;border-left:3px solid var(--primary);">
                A presente proposta é apenas de interesse de participação na locação, não tendo valor contratual. Com seja aprovada, os dados nela contidos serão utilizados para confecção do contrato de locação, onde estarão estabelecidas as cláusulas contratuais.
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
