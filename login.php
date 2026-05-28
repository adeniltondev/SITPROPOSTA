<?php
/**
 * Página de login
 * @package FORMA4
 */

define('APP_PATH', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// Se já logado, redireciona para o painel
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

$error    = '';
$redirect = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_URL) ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida CSRF
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($csrfToken)) {
        $error = 'Token inválido. Recarregue a página e tente novamente.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Preencha e-mail e senha.';
        } elseif (login($email, $password)) {
            // Regenera token CSRF após login
            unset($_SESSION[CSRF_TOKEN_NAME]);

            $dest = (strpos($redirect, APP_URL) === 0 || strpos($redirect, '/') === 0)
                ? $redirect
                : APP_URL . '/admin/index.php';

            header('Location: ' . $dest);
            exit;
        } else {
            $error = 'E-mail ou senha incorretos.';
        }
    }
}

// Carrega settings para logo e nome
$sysName         = 'Forma4 Imobiliária';
$sysLogo         = '';
$sysPrimaryColor = '#2563EB';

try {
    require_once __DIR__ . '/includes/db.php';
    $db              = Database::getInstance();
    $settingsRows    = $db->fetchAll('SELECT key_name, value FROM settings');
    $settings        = [];
    foreach ($settingsRows as $r) { $settings[$r['key_name']] = $r['value']; }
    $sysName         = $settings['app_name']      ?? $sysName;
    $sysLogo         = $settings['logo_path']      ?? '';
    $sysPrimaryColor = $settings['primary_color']  ?? $sysPrimaryColor;
} catch (Exception $e) { /* banco não configurado ainda */ }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars($sysName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>:root { --primary: <?= htmlspecialchars($sysPrimaryColor) ?>; }</style>
</head>
<body class="login-page">
<div class="login-card">
    <div class="login-logo">
        <?php if ($sysLogo && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $sysLogo)): ?>
            <img src="<?= APP_URL ?>/uploads/logos/<?= htmlspecialchars($sysLogo) ?>" alt="<?= htmlspecialchars($sysName) ?>">
        <?php endif; ?>
        <h1><?= htmlspecialchars($sysName) ?></h1>
        <p>Acesso ao Painel Administrativo</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>
        <?php if ($redirect): ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input
                class="form-control"
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                placeholder="seu@email.com"
                required
                autofocus
            >
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Senha</label>
            <input
                class="form-control"
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
            Entrar
        </button>
    </form>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
