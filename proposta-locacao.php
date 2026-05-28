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
            // Imóvel desejado
            'codigo_imovel','prazo_meses','valor_rs','destinacao','data_vencimento','tipo_fianca',
            // Pretendente
            'nome','nascimento','rg','exp','cpf','nacionalidade','estado_civil',
            'endereco_residencial','bairro','cidade_uf','cep',
            'whatsapp','residencial_fixo','celular','email_contato',
            'conjuge','tipo_residencia','valor_aluguel',
            'tempo_reside_anos','num_dependentes','cria_animal',
            'empresa_trabalha','cargo_funcao',
            'endereco_comercial','bairro_comercial','cidade_uf_comercial','cep_comercial',
            'telefone_fixo_comercial','celular_comercial','email_comercial',
            'tempo_trabalha','renda_mensal',
            // Referências
            'ref1_nome','ref1_relacao','ref1_telefone',
            'ref2_nome','ref2_relacao','ref2_telefone',
            'observacoes',
        ];

        $data = [];
        foreach ($textFields as $f) {
            $data[$f] = trim(strip_tags($_POST[$f] ?? ''));
        }

        $required = ['nome','rg','cpf','endereco_residencial','bairro','cidade_uf','cep',
                     'whatsapp','email_contato','empresa_trabalha','renda_mensal'];
        foreach ($required as $r) {
            if (empty($data[$r])) {
                $errors[] = ucfirst(str_replace('_', ' ', $r)) . ' é obrigatório.';
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
                error_log('[FORMA4 PDF PROP-LOC] ' . $e->getMessage());
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
                error_log('[FORMA4 MAIL PROP-LOC] ' . $e->getMessage());
            }

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
        .section-title {
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px;
            color: #fff; background: var(--text); padding: 5px 10px; margin-bottom: 0;
        }

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
        .fc select { border: none; outline: none; font-size: 13px; font-family: 'Inter', sans-serif; color: var(--text); background: transparent; width: 100%; padding: 2px 0; }
        .fc textarea { border: none; outline: none; font-size: 12.5px; font-family: 'Inter', sans-serif; color: var(--text); background: transparent; width: 100%; resize: none; min-height: 72px; padding: 2px 0; }
        .fc-xs  { flex: 0 0 80px; }
        .fc-sm  { flex: 0 0 130px; }
        .fc-md  { flex: 0 0 190px; }
        .fc-lg  { flex: 0 0 250px; }
        .fc-full { flex: 1 1 100%; }

        /* Radio / check rows */
        .check-row { display: flex; flex-wrap: wrap; gap: 5px 16px; padding: 6px 10px; border: 1px solid var(--border); border-top: none; align-items: center; }
        .check-row.first { border-top: 1px solid var(--border); }
        .check-row label { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text); cursor: pointer; }
        .check-row input[type=radio], .check-row input[type=checkbox] { width: 13px; height: 13px; accent-color: var(--primary); cursor: pointer; }
        .check-row .row-label { font-size: 10px; font-weight: 700; color: var(--label); text-transform: uppercase; margin-right: 6px; }
        .obs-note { font-size: 11px; color: #c0392b; padding: 4px 10px; border: 1px solid var(--border); border-top: none; background: #fff8f8; }

        /* Upload */
        .upload-area {
            border: 2px dashed var(--border); border-radius: 6px; padding: 20px;
            text-align: center; background: #f8fafc; margin-top: 10px;
        }
        .upload-area input[type=file] { font-size: 13px; color: var(--text); }
        .upload-area p { font-size: 11px; color: #94a3b8; margin-top: 6px; }

        /* Actions */
        .form-actions { padding: 20px 28px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .form-actions p { font-size: 12px; color: #64748b; }
        .btn-enviar { background: var(--primary); color: #fff; border: none; padding: 13px 44px; font-size: 15px; font-weight: 600; border-radius: 7px; cursor: pointer; font-family: 'Inter', sans-serif; transition: opacity .15s; }
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
        @media (max-width: 640px) {
            body { padding: 0; background: #fff; }
            .doc-body { padding: 16px 14px; }
            .fr { flex-direction: column; }
            .fc, .fc-xs, .fc-sm, .fc-md, .fc-lg, .fc-full {
                flex: 1 1 100%; border-right: none; border-bottom: 1px solid var(--border); padding: 7px 10px;
            }
            .fc:last-child, .fc-xs:last-child, .fc-sm:last-child, .fc-md:last-child, .fc-lg:last-child, .fc-full:last-child { border-bottom: none; }
            .form-actions { flex-direction: column; padding: 16px 14px; text-align: center; }
            .btn-enviar { width: 100%; }
        }
        @media (max-width: 860px) {
            .fr { flex-wrap: wrap; }
            .fc-xs { flex: 1 1 70px; } .fc-sm { flex: 1 1 110px; } .fc-md { flex: 1 1 150px; } .fc-lg { flex: 1 1 200px; }
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
        <h1>Proposta de Locação</h1>
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

            <!-- ══ IMÓVEL DESEJADO ══ -->
            <div class="section">
                <div class="section-title">Imóvel Desejado?</div>
                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Código n° <span style="color:#c0392b">*</span></label>
                            <input type="text" name="codigo_imovel" value="<?= fv('codigo_imovel') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Prazo a contratar (Meses) <span style="color:#c0392b">*</span></label>
                            <select name="prazo_meses">
                                <?php foreach ([12,24,30,36,48,60] as $m): ?>
                                    <option value="<?= $m ?>" <?= fv('prazo_meses','12') == $m ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fc fc-full">
                            <label>Valor R$ <span style="color:#c0392b">*</span></label>
                            <input type="text" name="valor_rs" value="<?= fv('valor_rs') ?>" placeholder="0,00">
                        </div>
                    </div>
                </div>
                <div class="obs-note">Obs: a opção de 60 meses, só está disponível para destinação comercial.</div>

                <div class="check-row" style="border-top:1px solid var(--border);margin-top:4px;">
                    <span class="row-label">Destinação <span style="color:#c0392b">*</span></span>
                    <?php foreach (['Residencial','Comercial','Misto'] as $d): ?>
                        <label><input type="radio" name="destinacao" value="<?= $d ?>" <?= fRadio('destinacao',$d) ?>><?= $d ?></label>
                    <?php endforeach; ?>
                </div>

                <div class="fg" style="margin-top:4px;">
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Melhor data para vencimento <span style="color:#c0392b">*</span></label>
                            <select name="data_vencimento">
                                <?php for ($d=1;$d<=30;$d++): ?>
                                    <option value="<?= sprintf('%02d',$d) ?>" <?= fv('data_vencimento','01') == sprintf('%02d',$d) ? 'selected' : '' ?>><?= sprintf('%02d',$d) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="check-row" style="border-top:1px solid var(--border);margin-top:4px;">
                    <span class="row-label">Tipo de fiança oferecida <span style="color:#c0392b">*</span></span>
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
                            <label>Nome <span style="color:#c0392b">*</span></label>
                            <input type="text" name="nome" value="<?= fv('nome') ?>" required>
                        </div>
                        <div class="fc fc-md">
                            <label>Nascimento <span style="color:#c0392b">*</span></label>
                            <input type="date" name="nascimento" value="<?= fv('nascimento') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-md">
                            <label>Rg <span style="color:#c0392b">*</span></label>
                            <input type="text" name="rg" value="<?= fv('rg') ?>" required>
                        </div>
                        <div class="fc fc-sm">
                            <label>Exp</label>
                            <input type="text" name="exp" value="<?= fv('exp') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Cpf <span style="color:#c0392b">*</span></label>
                            <input type="text" name="cpf" value="<?= fv('cpf') ?>" data-mask="cpf" placeholder="000.000.000-00" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Nacionalidade <span style="color:#c0392b">*</span></label>
                            <input type="text" name="nacionalidade" value="<?= fv('nacionalidade','Brasileiro(a)') ?>">
                        </div>
                    </div>
                </div>

                <div class="check-row first">
                    <span class="row-label">Estado Civil <span style="color:#c0392b">*</span></span>
                    <?php foreach (['Solteiro','Casado','União Estável','Viúvo','Separado judicialmente'] as $ec): ?>
                        <label><input type="radio" name="estado_civil" value="<?= $ec ?>" <?= fRadio('estado_civil',$ec) ?>><?= $ec ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="obs-note">Obs: Caso a opção marcada for casado, preencher as informações do cônjuge.</div>

                <div class="fg">
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Endereço residencial atual <span style="color:#c0392b">*</span></label>
                            <input type="text" name="endereco_residencial" value="<?= fv('endereco_residencial') ?>" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Bairro <span style="color:#c0392b">*</span></label>
                            <input type="text" name="bairro" value="<?= fv('bairro') ?>" required>
                        </div>
                        <div class="fc fc-lg">
                            <label>Cidade/UF <span style="color:#c0392b">*</span></label>
                            <input type="text" name="cidade_uf" value="<?= fv('cidade_uf') ?>" required>
                        </div>
                        <div class="fc fc-sm">
                            <label>Cep <span style="color:#c0392b">*</span></label>
                            <input type="text" name="cep" value="<?= fv('cep') ?>" data-mask="cep" placeholder="00000-000" required>
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>WhatsApp <span style="color:#c0392b">*</span></label>
                            <input type="text" name="whatsapp" value="<?= fv('whatsapp') ?>" data-mask="phone" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Residencial fixo <span style="color:#c0392b">*</span></label>
                            <input type="text" name="residencial_fixo" value="<?= fv('residencial_fixo') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Celular <span style="color:#c0392b">*</span></label>
                            <input type="text" name="celular" value="<?= fv('celular') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>E-mail de contato <span style="color:#c0392b">*</span></label>
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

                <div class="check-row" style="border-top:1px solid var(--border);margin-top:4px;">
                    <span class="row-label">Tipo de residência <span style="color:#c0392b">*</span></span>
                    <?php foreach (['Própria','Com os pais','Com parentes'] as $tr): ?>
                        <label><input type="radio" name="tipo_residencia" value="<?= $tr ?>" <?= fRadio('tipo_residencia',$tr) ?>><?= $tr ?></label>
                    <?php endforeach; ?>
                    <label style="margin-left:10px;">
                        <input type="radio" name="tipo_residencia" value="Alugado" <?= fRadio('tipo_residencia','Alugado') ?>> Alugado — Valor do aluguel R$
                    </label>
                    <input type="text" name="valor_aluguel" value="<?= fv('valor_aluguel') ?>" placeholder="0,00" style="width:90px;font-size:13px;border:none;border-bottom:1px solid var(--border);outline:none;padding:1px 4px;font-family:inherit;color:var(--text);">
                </div>

                <div class="fg" style="margin-top:4px;">
                    <div class="fr">
                        <div class="fc">
                            <label>Tempo que reside (anos) <span style="color:#c0392b">*</span></label>
                            <input type="number" name="tempo_reside_anos" value="<?= fv('tempo_reside_anos') ?>" min="0">
                        </div>
                        <div class="fc">
                            <label>N° de dependentes <span style="color:#c0392b">*</span></label>
                            <input type="number" name="num_dependentes" value="<?= fv('num_dependentes') ?>" min="0">
                        </div>
                        <div class="fc fc-md">
                            <label>Cria animal <span style="color:#c0392b">*</span></label>
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
                            <label>Empresa onde trabalha <span style="color:#c0392b">*</span></label>
                            <input type="text" name="empresa_trabalha" value="<?= fv('empresa_trabalha') ?>" required>
                        </div>
                        <div class="fc fc-full">
                            <label>Cargo/Função <span style="color:#c0392b">*</span></label>
                            <input type="text" name="cargo_funcao" value="<?= fv('cargo_funcao') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Endereço comercial <span style="color:#c0392b">*</span></label>
                            <input type="text" name="endereco_comercial" value="<?= fv('endereco_comercial') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Bairro <span style="color:#c0392b">*</span></label>
                            <input type="text" name="bairro_comercial" value="<?= fv('bairro_comercial') ?>">
                        </div>
                        <div class="fc fc-lg">
                            <label>Cidade/UF <span style="color:#c0392b">*</span></label>
                            <input type="text" name="cidade_uf_comercial" value="<?= fv('cidade_uf_comercial') ?>">
                        </div>
                        <div class="fc fc-sm">
                            <label>Cep <span style="color:#c0392b">*</span></label>
                            <input type="text" name="cep_comercial" value="<?= fv('cep_comercial') ?>" data-mask="cep" placeholder="00000-000">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Telefone fixo <span style="color:#c0392b">*</span></label>
                            <input type="text" name="telefone_fixo_comercial" value="<?= fv('telefone_fixo_comercial') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>Celular <span style="color:#c0392b">*</span></label>
                            <input type="text" name="celular_comercial" value="<?= fv('celular_comercial') ?>" data-mask="phone">
                        </div>
                        <div class="fc fc-full">
                            <label>E-mail <span style="color:#c0392b">*</span></label>
                            <input type="email" name="email_comercial" value="<?= fv('email_comercial') ?>">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Tempo que trabalha <span style="color:#c0392b">*</span></label>
                            <input type="text" name="tempo_trabalha" value="<?= fv('tempo_trabalha') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Renda mensal R$ <span style="color:#c0392b">*</span></label>
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
                            <label>Nome <span style="color:#c0392b">*</span></label>
                            <input type="text" name="ref1_nome" value="<?= fv('ref1_nome') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Qual relação <span style="color:#c0392b">*</span></label>
                            <input type="text" name="ref1_relacao" value="<?= fv('ref1_relacao') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Telefone <span style="color:#c0392b">*</span></label>
                            <input type="text" name="ref1_telefone" value="<?= fv('ref1_telefone') ?>" data-mask="phone">
                        </div>
                    </div>
                    <div class="fr">
                        <div class="fc fc-full">
                            <label>Nome <span style="color:#c0392b">*</span></label>
                            <input type="text" name="ref2_nome" value="<?= fv('ref2_nome') ?>">
                        </div>
                        <div class="fc fc-full">
                            <label>Qual relação <span style="color:#c0392b">*</span></label>
                            <input type="text" name="ref2_relacao" value="<?= fv('ref2_relacao') ?>">
                        </div>
                        <div class="fc fc-md">
                            <label>Telefone <span style="color:#c0392b">*</span></label>
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
                <div class="section-title">Anexar Documentos <span style="font-weight:400;text-transform:none;font-size:11px"> *</span></div>
                <div class="upload-area">
                    <p style="font-size:13px;font-weight:600;color:#334155;margin-bottom:8px;">Solte os arquivos aqui ou</p>
                    <input type="file" name="doc_anexo" accept=".jpg,.gif,.mp4,.pdf,.png,.doc,.docx,.xls,.xlsx">
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
            <p>Campos com <span style="color:#c0392b">*</span> são obrigatórios.</p>
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
