<?php
/**
 * Funções de autenticação, sessão e proteção de rotas
 *
 * @package FORMA4
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ============================================================
// INICIALIZAÇÃO DE SESSÃO
// ============================================================

/**
 * Inicia a sessão com configurações seguras.
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        session_name(SESSION_NAME);
        session_set_cookie_params($cookieParams);
        session_start();

        // Regenera ID a cada SESSION_LIFETIME/2 para evitar fixação
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > SESSION_LIFETIME / 2) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }
}

// ============================================================
// LOGIN / LOGOUT
// ============================================================

/**
 * Tenta autenticar o usuário.
 *
 * @param string $email
 * @param string $password
 * @return bool
 */
function login(string $email, string $password): bool
{
    $db   = Database::getInstance();
    $user = $db->fetchOne(
        'SELECT id, name, email, password, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1',
        [trim(strtolower($email))]
    );

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        return false;
    }

    // Persiste dados na sessão
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['_created']   = time();

    session_regenerate_id(true);

    return true;
}

/**
 * Encerra a sessão do usuário autenticado.
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();
}

// ============================================================
// VERIFICAÇÕES DE AUTENTICAÇÃO
// ============================================================

/**
 * Verifica se há um usuário logado.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Retorna os dados do usuário logado ou null.
 *
 * @return array|null
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

/**
 * Exige autenticação; redireciona para login caso contrário.
 */
function requireLogin(): void
{
    startSecureSession();

    if (!isLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . APP_URL . '/login.php?redirect=' . $redirect);
        exit;
    }
}

// ============================================================
// CSRF
// ============================================================

/**
 * Gera e armazena um token CSRF único na sessão.
 *
 * @return string Token hexadecimal
 */
function generateCSRF(): string
{
    startSecureSession();

    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Valida o token CSRF recebido na requisição.
 *
 * @param string $token Token recebido do formulário
 * @return bool
 */
function validateCSRF(string $token): bool
{
    startSecureSession();

    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }

    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Renderiza o campo hidden do CSRF em formulários HTML.
 *
 * @return string HTML do input hidden
 */
function csrfField(): string
{
    $token = generateCSRF();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
