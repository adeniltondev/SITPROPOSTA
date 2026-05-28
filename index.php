<?php
/**
 * Redireciona raiz do site para o painel admin (ou login)
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/admin/index.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
