<?php
/**
 * Formulário público – Autorização de Locação com Exclusividade
 * A4 Imobiliária
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$db = Database::getInstance();
$settings = getAllSettings();
$appName = $settings['app_name'] ?? APP_NAME;
$logoPath = $settings['logo_path'] ?? '';
$primaryColor = $settings['primary_color'] ?? '#0e4f6c';

// Busca ou cria o formulário no banco
$form = $db->fetchOne(
    'SELECT * FROM forms WHERE slug = ? LIMIT 1',
    ['autorizacao-locacao-exclusividade']
);
if (!$form) {
    // Cria o registro do formulário automaticamente
    $db->query(
        "INSERT INTO forms (title, slug, description, fields, pdf_template, is_active)
         VALUES (?, ?, ?, ?, ?, 1)",
        [
            'Autorização de Locação com Exclusividade',
            'autorizacao-locacao-exclusividade',
            'Contrato de autorização de locação de imóvel para a A4 Imobiliária.',
            '[]',
            'locacao',
        ]
    );
    $form = $db->fetchOne('SELECT * FROM forms WHERE slug = ? LIMIT 1', ['autorizacao-locacao-exclusividade']);
}
if (!$form) {
    http_response_code(500);
    die('<h2 style="font-family:sans-serif;padding:40px">Erro ao carregar formulário. Verifique o banco de dados.</h2>');
}

$success = isset($_GET['sucesso']);
$errors = [];

// -------------------------------------------------------
// POST: Processar submissão
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($csrfToken)) {
        $errors[] = 'Token de segurança inválido. Recarregue a página e tente novamente.';
    } else {
        $textFields = [
            // Contratante
            'nome_razao_social',
            'sexo',
            'data_nascimento',
            'rg',
            'orgao_expedidor',
            'cpf',
            'naturalidade',
            'nacionalidade',
            'cnpj',
            'nome_fantasia',
            'estado_civil',
            'conjuge',
            'telefones',
            'endereco_residencial',
            'bairro_residencial',
            'cidade_uf_residencial',
            'cep_residencial',
            'telefone_fixo',
            'celular',
            'endereco_comercial',
            'bairro_comercial',
            'cidade_uf_comercial',
            'cep_comercial',
            'emails',
            // Exclusividade
            'com_exclusividade',
            // Imóvel
            'situacao_imovel',
            'endereco_imovel',
            'bairro_imovel',
            'cidade_uf_imovel',
            'cep_imovel',
            'ponto_referencia',
            'registro_imovel',
            'matricula_iptu',
            'energisa_uc',
            'deso',
            'energisa_uc_num',
            'deso_matricula_num',
            // Descrição
            'num_dormitorios',
            'num_salas',
            'num_suites',
            'garagens',
            'area_privativa',
            'tem_varanda',
            'tem_elevador',
            'lazer_completo',
            'obs_descricao',
            // Valor
            'valor_locacao',
            'valor_locacao_extenso',
            'obs_preco',
            'valor_condominio',
            'data_vencimento',
            'valor_iptu_anual',
            'porcentagem_comissao',
            'prazo_exclusividade',
            'prazo_minimo',
            // Assinaturas
            'nome_corretor',
            'testemunha_1_nome',
            'testemunha_1_cpf',
            'testemunha_2_nome',
            'testemunha_2_cpf',
        ];

        $data = [];
        foreach ($textFields as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
        }

        // Tipo de imóvel (checkboxes múltiplos)
        $tipos = $_POST['tipo_imovel'] ?? [];
        $data['tipo_imovel'] = is_array($tipos) ? implode(', ', array_map('trim', $tipos)) : '';

        // Validações mínimas
        if (empty($data['nome_razao_social'])) {
            $errors[] = 'Nome / Razão Social é obrigatório.';
        }
        if (empty($data['cpf']) && empty($data['cnpj'])) {
            $errors[] = 'Informe o CPF ou CNPJ.';
        }
        if (empty($data['endereco_imovel'])) {
            $errors[] = 'Endereço do imóvel é obrigatório.';
        }

        if (empty($errors)) {
            // Processa uploads de documentos
            $docFields = ['doc_cpf_rg', 'doc_iptu', 'doc_matricula', 'doc_outros'];
            foreach ($docFields as $docField) {
                $uploadedFile = $_FILES[$docField] ?? null;
                if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK && $uploadedFile['size'] > 0) {
                    $savedName = uploadFile($uploadedFile, DOCS_PATH, ALLOWED_DOC_TYPES);
                    $data[$docField] = $savedName ? 'docs/' . $savedName : '';
                } else {
                    $data[$docField] = '';
                }
            }

            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
            $ip = getClientIP();

            $db->query(
                'INSERT INTO submissions (form_id, data, ip_address, created_at) VALUES (?, ?, ?, NOW())',
                [(int) $form['id'], $jsonData, $ip]
            );
            $submId = $db->lastInsertId();

            // Gera PDF
            $pdfRelPath = null;
            try {
                require_once __DIR__ . '/includes/pdf.php';
                $submission = [
                    'id' => $submId,
                    'data' => $data,
                    'created_at' => date('Y-m-d H:i:s'),
                    'ip_address' => $ip,
                ];
                $formForPdf = $form;
                $formForPdf['pdf_template'] = 'locacao';
                $pdfRelPath = generatePDF($formForPdf, $submission, $settings);
                if ($pdfRelPath) {
                    $db->query('UPDATE submissions SET pdf_path = ? WHERE id = ?', [$pdfRelPath, $submId]);
                }
            } catch (Exception $e) {
                error_log('[FORMA4 PDF LOCACAO] ' . $e->getMessage());
            }

            // Envia e-mail
            try {
                require_once __DIR__ . '/includes/mailer.php';
                $submission['pdf_path'] = $pdfRelPath;
                $sent = sendSubmissionEmail($submission, $form, $pdfRelPath, $settings);
                if ($sent) {
                    $db->query('UPDATE submissions SET email_sent = 1 WHERE id = ?', [$submId]);
                }
            } catch (Exception $e) {
                error_log('[FORMA4 MAIL LOCACAO] ' . $e->getMessage());
            }

            header('Location: ' . APP_URL . '/autorizacao-locacao.php?sucesso=1');
            exit;
        }
    }
}

// Logo URL
$logoSrc = '';
if ($logoPath && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $logoPath)) {
    $logoSrc = APP_URL . '/uploads/logos/' . rawurlencode($logoPath);
}

// Helper de repopulação
$old = $_POST ?? [];
function fv(string $key, string $default = ''): string
{
    global $old;
    return htmlspecialchars($old[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
function fRadio(string $name, string $value): string
{
    global $old;
    return ($old[$name] ?? '') === $value ? 'checked' : '';
}
function fCheck(string $name, string $value): string
{
    global $old;
    $arr = $old[$name] ?? [];
    return is_array($arr) && in_array($value, $arr, true) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorização de Locação com Exclusividade — <?= e($appName) ?></title>
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
            /* box-shadow: 0 4px 40px rgba(0, 0, 0, .18); */
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
    /* background: #fff; */
    /* border: 1px solid #dbe8ef; */
    border-radius: 10px;
    padding: 10px 14px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 204px;
    min-height: 208px;
    /* box-shadow: 0 8px 20px rgba(14, 57, 78, .1); */
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

        /* ── Exclusividade ── */
        .exclusividade-bar {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 10px 14px;
            background: linear-gradient(90deg, #e8f4fd, #f0f9ff);
            border: 2px solid var(--primary);
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .exclusividade-bar .exc-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .exclusividade-bar label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
        }

        .exclusividade-bar input[type=radio] {
            width: 15px;
            height: 15px;
            accent-color: var(--primary);
        }

        /* ── Texto legal ── */
        .legal {
            font-size: 11.5px;
            line-height: 1.85;
            color: #374151;
            text-align: justify;
            margin: 16px 0;
            padding: 14px 16px;
            background: #f8fafc;
            border-left: 3px solid var(--primary);
        }

        .legal strong {
            font-weight: 700;
        }

        /* ── Cláusulas ── */
        .clauses {
            margin: 12px 0;
        }

        .clause {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 11.5px;
            line-height: 1.8;
            color: #374151;
            text-align: justify;
        }

        .clause-letter {
            font-weight: 700;
            flex-shrink: 0;
        }

        /* ── Assinaturas ── */
        .sign-date {
            text-align: right;
            font-size: 13px;
            margin: 24px 0 28px;
            color: #374151;
        }

        .signatures {
            display: flex;
            flex-wrap: wrap;
            gap: 28px 48px;
            margin-bottom: 24px;
        }

        .sig-block {
            min-width: 180px;
            flex: 1;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #374151;
            margin-bottom: 6px;
            padding-top: 3px;
        }

        .sig-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sig-sub {
            font-size: 10px;
            color: #64748b;
            font-style: italic;
        }

        .sig-input {
            border: none;
            outline: none;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            color: var(--text);
            background: transparent;
            text-align: center;
            width: 100%;
            border-bottom: 1px dashed #b0bec5;
            margin-bottom: 3px;
            padding: 1px 4px;
        }

        /* ── Upload de documentos ── */
        .docs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }

        .doc-upload-item {
            background: #f8fafc;
            border: 1px dashed #b0bec5;
            border-radius: 6px;
            padding: 12px 14px;
        }

        .doc-upload-item label {
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

            /* Linhas com muitas colunas fixas: deixa fluir */
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

            /* Form actions */
            .form-actions {
                flex-direction: column;
                padding: 16px 14px;
                text-align: center;
            }

            .btn-enviar {
                width: 100%;
                padding: 14px 20px;
            }

            /* Linhas de campos: empilha */
            .fr {
                flex-direction: column;
                border-bottom: 1px solid var(--border);
            }

            .fr:last-child {
                border-bottom: none;
            }

            /* Todos os fc tornam-se full em mobile */
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

            /* Inputs maiores para touch */
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

            /* Check rows */
            .check-row {
                flex-wrap: wrap;
                gap: 8px 14px;
                padding: 10px 10px;
            }

            .check-row label {
                font-size: 13px;
            }

            /* Exclusividade bar */
            .exclusividade-bar {
                flex-wrap: wrap;
                gap: 10px 20px;
                padding: 10px 12px;
            }

            /* Legal text */
            .legal {
                font-size: 12px;
                padding: 12px 12px;
                text-align: left;
            }

            /* Clauses */
            .clause {
                font-size: 12px;
            }

            /* Signatures */
            .signatures {
                flex-direction: column;
                gap: 20px;
            }

            .sig-block {
                min-width: unset;
            }

            /* Upload grid */
            .docs-grid {
                grid-template-columns: 1fr;
            }

            /* Section title */
            .section-title {
                font-size: 12px;
            }

            /* Sign date */
            .sign-date {
                font-size: 12px;
                text-align: center;
            }

            /* Success */
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

        <!-- ===================== HEADER BANNER ===================== -->
        <div class="doc-header">
            <div class="header-ribbon">
                <span>Documento Contratual</span>
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
                    <h1>Autorização de Locação</h1>
                    <p>Contrato de intermediação imobiliária — via eletrônica</p>
                    <div class="doc-meta">Validade jurídica mediante preenchimento completo e aceitação das cláusulas</div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <!-- ===================== SUCESSO ===================== -->
            <div class="success-wrap">
                <div class="success-icon">✓</div>
                <h2>Autorização enviada com sucesso!</h2>
                <p>
                    Seus dados foram registrados e o contrato foi gerado.<br>
                    Em breve entraremos em contato. Obrigado!
                </p>
            </div>

        <?php else: ?>
            <!-- ===================== FORMULÁRIO ===================== -->
            <form method="POST" action="" enctype="multipart/form-data" novalidate>
                <?= csrfField() ?>

                <div class="doc-body">

                    <?php if ($errors): ?>
                        <div class="error-box">
                            <?php foreach ($errors as $err): ?>
                                <p>&#9888; <?= e($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ═══════════════════════════════════════
                 DADOS DO CONTRATANTE
            ═══════════════════════════════════════ -->
                    <div class="section">
                        <div class="section-title">Dados do Contratante</div>

                        <div class="fg">
                            <!-- Nome / Sexo -->
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Nome / Razão Social <span style="color:#c0392b">*</span></label>
                                    <input type="text" name="nome_razao_social" value="<?= fv('nome_razao_social') ?>"
                                        required>
                                </div>
                                <div class="fc fc-md" style="justify-content:flex-end;">
                                    <label>Sexo</label>
                                    <div style="display:flex;gap:14px;padding:3px 0;align-items:center;">
                                        <label
                                            style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text);font-weight:400">
                                            <input type="radio" name="sexo" value="Masculino" <?= fRadio('sexo', 'Masculino') ?>> Masculino
                                        </label>
                                        <label
                                            style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text);font-weight:400">
                                            <input type="radio" name="sexo" value="Feminino" <?= fRadio('sexo', 'Feminino') ?>> Feminino
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Nascimento / RG / Órgão -->
                            <div class="fr">
                                <div class="fc fc-md">
                                    <label>Data de Nascimento</label>
                                    <input type="date" name="data_nascimento" value="<?= fv('data_nascimento') ?>">
                                </div>
                                <div class="fc fc-md">
                                    <label>RG nº</label>
                                    <input type="text" name="rg" value="<?= fv('rg') ?>">
                                </div>
                                <div class="fc fc-full">
                                    <label>Órgão Expedidor</label>
                                    <input type="text" name="orgao_expedidor" value="<?= fv('orgao_expedidor') ?>">
                                </div>
                            </div>

                            <!-- CPF / Naturalidade / Nacionalidade -->
                            <div class="fr">
                                <div class="fc fc-md">
                                    <label>CPF nº</label>
                                    <input type="text" name="cpf" value="<?= fv('cpf') ?>" data-mask="cpf"
                                        placeholder="000.000.000-00">
                                </div>
                                <div class="fc fc-md">
                                    <label>Naturalidade</label>
                                    <input type="text" name="naturalidade" value="<?= fv('naturalidade') ?>">
                                </div>
                                <div class="fc fc-full">
                                    <label>Nacionalidade</label>
                                    <input type="text" name="nacionalidade" value="<?= fv('nacionalidade') ?>"
                                        placeholder="Brasileiro(a)">
                                </div>
                            </div>

                            <!-- CNPJ / Nome Fantasia -->
                            <div class="fr">
                                <div class="fc fc-md">
                                    <label>CNPJ nº</label>
                                    <input type="text" name="cnpj" value="<?= fv('cnpj') ?>"
                                        placeholder="00.000.000/0000-00">
                                </div>
                                <div class="fc fc-full">
                                    <label>Nome de Fantasia</label>
                                    <input type="text" name="nome_fantasia" value="<?= fv('nome_fantasia') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Estado Civil -->
                        <div class="check-row">
                            <span class="row-label">Estado Civil:</span>
                            <?php foreach (['Solteiro', 'Casado', 'União Estável', 'Viúvo', 'Divorciado', 'Separado judicialmente'] as $ec): ?>
                                <label>
                                    <input type="radio" name="estado_civil" value="<?= e($ec) ?>" <?= fRadio('estado_civil', $ec) ?>>
                                    <?= e($ec) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="fg">
                            <!-- Cônjuge / Telefones -->
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Cônjuge</label>
                                    <input type="text" name="conjuge" value="<?= fv('conjuge') ?>">
                                </div>
                                <div class="fc fc-lg">
                                    <label>Telefones</label>
                                    <input type="text" name="telefones" value="<?= fv('telefones') ?>" data-mask="phone">
                                </div>
                            </div>

                            <!-- Endereço residencial -->
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Endereço Residencial</label>
                                    <input type="text" name="endereco_residencial"
                                        value="<?= fv('endereco_residencial') ?>">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Bairro</label>
                                    <input type="text" name="bairro_residencial" value="<?= fv('bairro_residencial') ?>">
                                </div>
                                <div class="fc fc-lg">
                                    <label>Cidade / UF</label>
                                    <input type="text" name="cidade_uf_residencial"
                                        value="<?= fv('cidade_uf_residencial') ?>">
                                </div>
                                <div class="fc fc-sm">
                                    <label>CEP</label>
                                    <input type="text" name="cep_residencial" value="<?= fv('cep_residencial') ?>"
                                        data-mask="cep" placeholder="00000-000">
                                </div>
                            </div>

                            <!-- Telefone / Celular -->
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Telefone Fixo</label>
                                    <input type="text" name="telefone_fixo" value="<?= fv('telefone_fixo') ?>"
                                        data-mask="phone">
                                </div>
                                <div class="fc fc-full">
                                    <label>Celular / WhatsApp</label>
                                    <input type="text" name="celular" value="<?= fv('celular') ?>" data-mask="phone">
                                </div>
                            </div>

                            <!-- Endereço comercial -->
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Endereço Comercial</label>
                                    <input type="text" name="endereco_comercial" value="<?= fv('endereco_comercial') ?>">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Bairro</label>
                                    <input type="text" name="bairro_comercial" value="<?= fv('bairro_comercial') ?>">
                                </div>
                                <div class="fc fc-lg">
                                    <label>Cidade / UF</label>
                                    <input type="text" name="cidade_uf_comercial" value="<?= fv('cidade_uf_comercial') ?>">
                                </div>
                                <div class="fc fc-sm">
                                    <label>CEP</label>
                                    <input type="text" name="cep_comercial" value="<?= fv('cep_comercial') ?>"
                                        data-mask="cep" placeholder="00000-000">
                                </div>
                            </div>

                            <!-- E-mails -->
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>E-mail(s)</label>
                                    <input type="email" name="emails" value="<?= fv('emails') ?>"
                                        placeholder="contato@exemplo.com.br">
                                </div>
                            </div>
                        </div>
                    </div><!-- /section contratante -->

                    <!-- Parágrafo legal -->
                    <div class="legal">
                        O CONTRATANTE acima, proprietário(a) e legítimo(a) possuidor(a) do imóvel abaixo relacionado,
                        contrata a <strong><?= e($appName) ?></strong>, inscrita no CRECI nº 218 PJ, para promover, de
                        forma <strong>EXCLUSIVA</strong>, a <strong>LOCAÇÃO</strong> do seu imóvel acima descrito, pelo
                        prazo mínimo de <input type="number" name="prazo_minimo" value="<?= fv('prazo_minimo') ?>" min="1" placeholder="  " style="width:56px;display:inline-block;padding:1px 4px;font-size:inherit;font-weight:700;color:inherit;border:none;border-bottom:2px solid #b48a00;background:transparent;text-align:center;-moz-appearance:textfield;" title="Informe o prazo mínimo em dias"> dias, prorrogável automaticamente por
                        período igual e sucessivo, até que uma das partes se manifeste em contrário, por escrito, pelo
                        preço e pelas condições estipuladas nesta autorização de <strong>LOCAÇÃO</strong>.
                    </div>

                    <!-- ═══════════════════════════════════════
                 DADOS DO IMÓVEL
            ═══════════════════════════════════════ -->
                    <div class="section">
                        <div class="section-title">Dados do Imóvel</div>

                        <!-- Tipo do imóvel -->
                        <div class="check-row">
                            <span class="row-label">Tipo:</span>
                            <?php
                            $tipos = ['Apartamento', 'Casa Residencial', 'Casa em Andar Superior', 'Kitnete', 'Galpão', 'Terreno', 'Sala/Loja Comercial'];
                            foreach ($tipos as $t): ?>
                                <label>
                                    <input type="checkbox" name="tipo_imovel[]" value="<?= e($t) ?>" <?= fCheck('tipo_imovel', $t) ?>>
                                    <?= e($t) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="fg">
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Endereço Completo <span style="color:#c0392b">*</span></label>
                                    <input type="text" name="endereco_imovel" value="<?= fv('endereco_imovel') ?>" required>
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Bairro</label>
                                    <input type="text" name="bairro_imovel" value="<?= fv('bairro_imovel') ?>">
                                </div>
                                <div class="fc fc-lg">
                                    <label>Cidade / UF</label>
                                    <input type="text" name="cidade_uf_imovel" value="<?= fv('cidade_uf_imovel') ?>">
                                </div>
                                <div class="fc fc-sm">
                                    <label>CEP</label>
                                    <input type="text" name="cep_imovel" value="<?= fv('cep_imovel') ?>" data-mask="cep"
                                        placeholder="00000-000">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Ponto de Referência</label>
                                    <input type="text" name="ponto_referencia" value="<?= fv('ponto_referencia') ?>">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-md">
                                    <label>Nº e Registro do Imóvel</label>
                                    <input type="text" name="registro_imovel" value="<?= fv('registro_imovel') ?>">
                                </div>
                                <div class="fc fc-md">
                                    <label>Matrícula de IPTU</label>
                                    <input type="text" name="matricula_iptu" value="<?= fv('matricula_iptu') ?>">
                                </div>
                                <div class="fc fc-md">
                                    <label>Energisa / UC</label>
                                    <input type="text" name="energisa_uc" value="<?= fv('energisa_uc') ?>">
                                </div>
                                <div class="fc fc-md">
                                    <label>Iguá</label>
                                    <input type="text" name="deso" value="<?= fv('deso') ?>">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Energisa/UC Nº</label>
                                    <input type="text" name="energisa_uc_num" value="<?= fv('energisa_uc_num') ?>">
                                </div>
                                <div class="fc fc-full">
                                    <label>Iguá Matrícula Nº</label>
                                    <input type="text" name="deso_matricula_num" value="<?= fv('deso_matricula_num') ?>">
                                </div>
                            </div>
                        </div>
                    </div><!-- /dados imóvel -->

                    <!-- ═══════════════════════════════════════
                 DESCRIÇÃO DO IMÓVEL
            ═══════════════════════════════════════ -->
                    <div class="section">
                        <div class="section-title">Descrição do Imóvel</div>

                        <div class="fg">
                            <div class="fr">
                                <div class="fc fc-xs">
                                    <label>Dormitórios</label>
                                    <input type="number" name="num_dormitorios" value="<?= fv('num_dormitorios') ?>" min="0"
                                        max="99">
                                </div>
                                <div class="fc fc-xs">
                                    <label>Salas</label>
                                    <input type="number" name="num_salas" value="<?= fv('num_salas') ?>" min="0" max="99">
                                </div>
                                <div class="fc fc-xs">
                                    <label>Suítes</label>
                                    <input type="number" name="num_suites" value="<?= fv('num_suites') ?>" min="0" max="99">
                                </div>
                                <div class="fc fc-xs">
                                    <label>Garagens</label>
                                    <input type="number" name="garagens" value="<?= fv('garagens') ?>" min="0" max="99">
                                </div>
                                <div class="fc fc-full">
                                    <label>Área Privativa (m²)</label>
                                    <input type="number" name="area_privativa" value="<?= fv('area_privativa') ?>" min="0"
                                        step="0.01">
                                </div>
                            </div>

                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Varanda?</label>
                                    <div style="display:flex;gap:12px;padding:3px 0;">
                                        <?php foreach (['Sim', 'Não'] as $v): ?>
                                            <label
                                                style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text);font-weight:400">
                                                <input type="radio" name="tem_varanda" value="<?= $v ?>"
                                                    <?= fRadio('tem_varanda', $v) ?>> <?= $v ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="fc fc-full">
                                    <label>Elevador?</label>
                                    <div style="display:flex;gap:12px;padding:3px 0;">
                                        <?php foreach (['Sim', 'Não'] as $v): ?>
                                            <label
                                                style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text);font-weight:400">
                                                <input type="radio" name="tem_elevador" value="<?= $v ?>"
                                                    <?= fRadio('tem_elevador', $v) ?>> <?= $v ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="fc fc-full">
                                    <label>Lazer Completo?</label>
                                    <div style="display:flex;gap:12px;padding:3px 0;">
                                        <?php foreach (['Sim', 'Não'] as $v): ?>
                                            <label
                                                style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text);font-weight:400">
                                                <input type="radio" name="lazer_completo" value="<?= $v ?>"
                                                    <?= fRadio('lazer_completo', $v) ?>> <?= $v ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Observações sobre as descrições do imóvel</label>
                                    <textarea name="obs_descricao" rows="2"><?= fv('obs_descricao') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div><!-- /descricao imovel -->

                    <!-- ═══════════════════════════════════════
                 VALOR DA LOCAÇÃO
            ═══════════════════════════════════════ -->
                    <div class="section">
                        <div class="section-title">Valor da Locação</div>

                        <div class="fg">
                            <div class="fr">
                                <div class="fc fc-md">
                                    <label>Valor Pretendido R$</label>
                                    <input type="text" name="valor_locacao" value="<?= fv('valor_locacao') ?>"
                                        placeholder="0,00">
                                </div>
                                <div class="fc fc-full">
                                    <label>Por Extenso</label>
                                    <input type="text" name="valor_locacao_extenso"
                                        value="<?= fv('valor_locacao_extenso') ?>" placeholder="Ex: dois mil reais">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Observações do Preço</label>
                                    <input type="text" name="obs_preco" value="<?= fv('obs_preco') ?>">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Valor do Condomínio R$</label>
                                    <input type="text" name="valor_condominio" value="<?= fv('valor_condominio') ?>"
                                        placeholder="0,00">
                                </div>
                                <div class="fc fc-md">
                                    <label>Data de Vencimento</label>
                                    <input type="text" name="data_vencimento" value="<?= fv('data_vencimento') ?>"
                                        placeholder="Ex: todo dia 10">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Valor Anual do IPTU R$</label>
                                    <input type="text" name="valor_iptu_anual" value="<?= fv('valor_iptu_anual') ?>"
                                        placeholder="0,00">
                                </div>
                                <div class="fc fc-sm">
                                    <label>Comissão (%)</label>
                                    <input type="number" name="porcentagem_comissao"
                                        value="<?= fv('porcentagem_comissao') ?>" min="0" max="100" step="0.1"
                                        placeholder="Ex: 10">
                                </div>
                                <div class="fc fc-md">
                                    <label>Prazo Exclusividade (dias)</label>
                                    <input type="number" name="prazo_exclusividade" value="<?= fv('prazo_exclusividade') ?>"
                                        min="0" placeholder="Ex: 90">
                                </div>
                            </div>
                        </div>
                    </div><!-- /valor locação -->

                    <!-- ═══════════════════════════════════════
                 ASSINATURAS
            ═══════════════════════════════════════ -->
                    <div class="section">
                        <div class="section-title">Assinaturas</div>

                        <div class="fg" style="margin-bottom:18px;">
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Nome do Corretor(a)</label>
                                    <input type="text" name="nome_corretor" value="<?= fv('nome_corretor') ?>">
                                </div>
                            </div>
                        </div>

                        <p style="font-size:11.5px;color:#64748b;margin-bottom:12px;">
                            Testemunhas:
                        </p>
                        <div class="fg">
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Testemunha 1 — Nome</label>
                                    <input type="text" name="testemunha_1_nome" value="<?= fv('testemunha_1_nome') ?>">
                                </div>
                                <div class="fc fc-md">
                                    <label>CPF</label>
                                    <input type="text" name="testemunha_1_cpf" value="<?= fv('testemunha_1_cpf') ?>"
                                        data-mask="cpf" placeholder="000.000.000-00">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fc fc-full">
                                    <label>Testemunha 2 — Nome</label>
                                    <input type="text" name="testemunha_2_nome" value="<?= fv('testemunha_2_nome') ?>">
                                </div>
                                <div class="fc fc-md">
                                    <label>CPF</label>
                                    <input type="text" name="testemunha_2_cpf" value="<?= fv('testemunha_2_cpf') ?>"
                                        data-mask="cpf" placeholder="000.000.000-00">
                                </div>
                            </div>
                        </div>
                    </div><!-- /assinaturas -->

                    <!-- ═══════════════════════════════════════
                 DOCUMENTOS
            ═══════════════════════════════════════ -->
                    <div class="section">
                        <div class="section-title">Documentos Anexos <span
                                style="font-weight:400;text-transform:none;font-size:11px;color:#64748b">(opcional)</span>
                        </div>
                        <div class="docs-grid">
                            <div class="doc-upload-item">
                                <label>RG / CPF do Proprietário</label>
                                <input type="file" name="doc_cpf_rg" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <p>PDF, JPG ou PNG — máx. 10 MB</p>
                            </div>
                            <div class="doc-upload-item">
                                <label>Carnê / Comprovante de IPTU</label>
                                <input type="file" name="doc_iptu" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <p>PDF, JPG ou PNG — máx. 10 MB</p>
                            </div>
                            <div class="doc-upload-item">
                                <label>Matrícula do Imóvel</label>
                                <input type="file" name="doc_matricula" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <p>PDF, JPG ou PNG — máx. 10 MB</p>
                            </div>
                            <div class="doc-upload-item">
                                <label>Outros Documentos</label>
                                <input type="file" name="doc_outros" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                                <p>PDF, Word, JPG ou PNG — máx. 10 MB</p>
                            </div>
                        </div>
                    </div><!-- /docs -->

                </div><!-- /.doc-body -->

                <!-- ===================== BOTÃO SUBMIT ===================== -->
                <div class="form-actions">
                    <p>Campos marcados com <span style="color:#c0392b">*</span> são obrigatórios.</p>
                    <button type="submit" class="btn-enviar">Enviar Autorização</button>
                </div>

            </form>
        <?php endif; ?>

        <!-- Footer do documento -->
        <div class="doc-footer-bar">
            <?= e($appName) ?> &nbsp;|&nbsp; Av. Hermes Fontes, nº 1524, Bairro Luzia – CEP 49.048.010 – Aracaju/SE
            &nbsp;|&nbsp; (79) 3304-0000 / 99691-0000 &nbsp;|&nbsp;
            <a href="mailto:contato@a4imobiliaria.com.br">contato@a4imobiliaria.com.br</a>
        </div>
    </div>

    <script>
        /* Máscaras simples */
        document.querySelectorAll('[data-mask]').forEach(function (el) {
            el.addEventListener('input', function () {
                var v = el.value.replace(/\D/g, '');
                if (el.dataset.mask === 'cpf') {
                    v = v.slice(0, 11);
                    v = v.replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                        .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
                } else if (el.dataset.mask === 'cep') {
                    v = v.slice(0, 8);
                    v = v.replace(/(\d{5})(\d)/, '$1-$2');
                } else if (el.dataset.mask === 'phone') {
                    v = v.slice(0, 11);
                    if (v.length <= 10) {
                        v = v.replace(/(\d{2})(\d)/, '($1) $2')
                            .replace(/(\d{4})(\d)/, '$1-$2');
                    } else {
                        v = v.replace(/(\d{2})(\d)/, '($1) $2')
                            .replace(/(\d{5})(\d)/, '$1-$2');
                    }
                }
                el.value = v;
            });
        });

        /* Atualiza o prazo no parágrafo legal */
        document.querySelector('[name="prazo_exclusividade"]')?.addEventListener('input', function () {
            document.querySelectorAll('.prazo-ref').forEach(function (el) {
                el.textContent = this.value ? '(' + this.value + ')' : '(     )';
            }.bind(this));
        });
    </script>

</body>

</html>