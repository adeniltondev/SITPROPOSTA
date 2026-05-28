<?php
/**
 * Exclusão de envio (POST only)
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/submissions.php');
    exit;
}

if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
    setFlash('Token de segurança inválido.', 'error');
    header('Location: ' . APP_URL . '/admin/submissions.php');
    exit;
}

$submId   = (int) filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
$redirect = filter_input(INPUT_POST, 'redirect', FILTER_SANITIZE_URL) ?? APP_URL . '/admin/submissions.php';

// Valida redirect (só permite URLs do próprio app)
if (strpos($redirect, APP_URL) !== 0) {
    $redirect = APP_URL . '/admin/submissions.php';
}

if (!$submId) {
    setFlash('ID inválido.', 'error');
    header('Location: ' . $redirect);
    exit;
}

$db         = Database::getInstance();
$submission = $db->fetchOne('SELECT pdf_path FROM submissions WHERE id = ? LIMIT 1', [$submId]);

if ($submission && !empty($submission['pdf_path'])) {
    deleteUploadedFile($submission['pdf_path']);
}

$db->query('DELETE FROM submissions WHERE id = ?', [$submId]);

setFlash('Envio excluído com sucesso.', 'success');
header('Location: ' . $redirect);
exit;
