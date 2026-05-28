<?php
/**
 * Handler de envio de formulário público
 * Aceita POST de /form.php, salva no banco, gera PDF e envia e-mail.
 *
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/');
    exit;
}

// Valida CSRF
$csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
if (!validateCSRF($csrfToken)) {
    die('Token de segurança inválido. Volte e tente novamente.');
}

$db     = Database::getInstance();
$formId = (int) filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT);

if (!$formId) {
    die('Formulário inválido.');
}

// Carrega o formulário
$form = $db->fetchOne(
    'SELECT * FROM forms WHERE id = ? AND is_active = 1 LIMIT 1',
    [$formId]
);

if (!$form) {
    die('Formulário não encontrado ou inativo.');
}

$fields   = decodeFields($form['fields']);
$settings = getAllSettings();

// -------------------------------------------------------
// Coleta e sanitiza os dados do POST
// -------------------------------------------------------
$submData  = [];
$hasErrors = false;
$errors    = [];

foreach ($fields as $field) {
    $name     = preg_replace('/[^a-zA-Z0-9_]/', '', $field['name'] ?? '');
    $type     = $field['type'] ?? 'text';
    $required = !empty($field['required']);

    // ---- Campo de arquivo ----
    if ($type === 'file') {
        $uploadedFile = $_FILES[$name] ?? null;
        $hasFile      = $uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK && $uploadedFile['size'] > 0;

        if ($required && !$hasFile) {
            $hasErrors = true;
            $errors[]  = ($field['label'] ?? $name) . ' é obrigatório.';
            $submData[$name] = '';
            continue;
        }

        if ($hasFile) {
            $savedName = uploadFile($uploadedFile, DOCS_PATH, ALLOWED_DOC_TYPES);
            if ($savedName === false) {
                $hasErrors = true;
                $errors[]  = 'Arquivo "' . ($field['label'] ?? $name) . '" inválido ou muito grande (máx. 10 MB).';
                $submData[$name] = '';
            } else {
                // Salva o caminho relativo ao diretório uploads
                $submData[$name] = 'docs/' . $savedName;
            }
        } else {
            $submData[$name] = '';
        }
        continue;
    }

    $rawValue = $_POST[$name] ?? '';

    // Sanitiza conforme tipo
    if ($type === 'number') {
        $value = filter_var($rawValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    } elseif ($type === 'checkbox') {
        $value = !empty($rawValue) ? '1' : '0';
    } else {
        $value = trim(strip_tags((string) $rawValue));
        // Limita tamanho para prevenir inputs gigantes
        $value = mb_substr($value, 0, 2000);
    }

    // Validação de campo obrigatório
    if ($required && ($value === '' || $value === '0')) {
        $hasErrors = true;
        $errors[]  = ($field['label'] ?? $name) . ' é obrigatório.';
    }

    $submData[$name] = $value;
}

// Se há erros, redireciona de volta ao formulário
if ($hasErrors) {
    $slug = $form['slug'] ?? '';
    setFlash('Preencha os campos obrigatórios: ' . implode(', ', $errors), 'error');
    header('Location: ' . APP_URL . '/form.php?slug=' . urlencode($slug));
    exit;
}

// -------------------------------------------------------
// Salva no banco
// -------------------------------------------------------
$clientIP = getClientIP();
$geo      = getIPGeoLocation($clientIP);

$db->query(
    'INSERT INTO submissions (form_id, data, ip_address, city, state, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
    [
        $formId,
        json_encode($submData, JSON_UNESCAPED_UNICODE),
        $clientIP,
        $geo['city'],
        $geo['state'],
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]
);
$submissionId = (int) $db->lastInsertId();

// Recarrega submission para garantir created_at correto
$submission = $db->fetchOne('SELECT * FROM submissions WHERE id = ? LIMIT 1', [$submissionId]);

// -------------------------------------------------------
// Gera PDF
// -------------------------------------------------------
$pdfRelPath = false;
try {
    require_once __DIR__ . '/includes/pdf.php';
    $submissionData         = $submission;
    $submissionData['data'] = $submData; // array já decodificado

    $pdfRelPath = generatePDF($form, $submissionData, $settings);

    if ($pdfRelPath) {
        $db->query(
            'UPDATE submissions SET pdf_path = ? WHERE id = ?',
            [$pdfRelPath, $submissionId]
        );
    }
} catch (Exception $e) {
    error_log('[FORMA4 SUBMIT] Erro ao gerar PDF: ' . $e->getMessage());
}

// -------------------------------------------------------
// Envia e-mail
// -------------------------------------------------------
$emailSent = false;
if ($pdfRelPath) {
    try {
        require_once __DIR__ . '/includes/mailer.php';
        $emailSent = sendSubmissionEmail(
            $submission,
            $form,
            $pdfRelPath,
            $settings
        );

        if ($emailSent) {
            $db->query(
                'UPDATE submissions SET email_sent = 1 WHERE id = ?',
                [$submissionId]
            );
        }
    } catch (Exception $e) {
        error_log('[FORMA4 SUBMIT] Erro ao enviar e-mail: ' . $e->getMessage());
    }
}

// -------------------------------------------------------
// Redireciona para página de sucesso
// -------------------------------------------------------
$slug = $form['slug'] ?? '';
header('Location: ' . APP_URL . '/form.php?slug=' . urlencode($slug) . '&sent=1&id=' . $submissionId);
exit;
