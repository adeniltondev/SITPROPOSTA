<?php
/**
 * Logout – encerra sessão e redireciona para login
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
logout();

header('Location: ' . APP_URL . '/login.php');
exit;
