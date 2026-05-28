<?php
/**
 * Exclusão de formulário (POST only)
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/forms.php');
    exit;
}

if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
    setFlash('Token de segurança inválido.', 'error');
    header('Location: ' . APP_URL . '/admin/forms.php');
    exit;
}

$formId = (int) filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT);

if (!$formId) {
    setFlash('ID inválido.', 'error');
    header('Location: ' . APP_URL . '/admin/forms.php');
    exit;
}

$db = Database::getInstance();

// Remove PDFs dos envios antes de excluir (por CASCADE, submissions são removidas pelo FK)
$pdfs = $db->fetchAll('SELECT pdf_path FROM submissions WHERE form_id = ? AND pdf_path IS NOT NULL', [$formId]);
foreach ($pdfs as $pdf) {
    if (!empty($pdf['pdf_path'])) {
        deleteUploadedFile($pdf['pdf_path']);
    }
}

// Exclui (submissions em cascade)
$db->query('DELETE FROM forms WHERE id = ?', [$formId]);

setFlash('Formulário excluído com sucesso.', 'success');
header('Location: ' . APP_URL . '/admin/forms.php');
exit;
