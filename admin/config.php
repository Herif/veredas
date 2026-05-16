<?php
declare(strict_types=1);

const ADMIN_EMAIL = 'admin@veredasdoaraguaia.com.br';
const ADMIN_PASSWORD_SALT = 'beca8af2b752420210cbd3c3004c5123';
const ADMIN_PASSWORD_HASH = '3c5471964430427745cbe02d85b22f42339686b39eabcdea5d007d1780da5027';
const ADMIN_SESSION_TIMEOUT = 3600;

const DB_HOST = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'veredas1_veredas';
const DB_USER = 'veredas1_codex';
const DB_PASS = 'code1001!@#$';

session_name('veredas_admin');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
]);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('A extensao pdo_pgsql nao esta ativa no PHP.');
    }

    $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_leads_schema(): void
{
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS leads (
            id BIGSERIAL PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            telefone VARCHAR(60) NOT NULL,
            cidade VARCHAR(120),
            interesse VARCHAR(160),
            mensagem TEXT,
            origem VARCHAR(120),
            ip VARCHAR(64),
            user_agent TEXT,
            status VARCHAR(40) NOT NULL DEFAULT 'Novo',
            admin_notes TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ
        )"
    );
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'Novo'");
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS admin_notes TEXT");
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ");
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_last_seen'])) {
        return false;
    }

    if ((time() - (int)$_SESSION['admin_last_seen']) > ADMIN_SESSION_TIMEOUT) {
        logout_admin();
        return false;
    }

    $_SESSION['admin_last_seen'] = time();
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function login_admin(string $email, string $password): bool
{
    $hash = hash_pbkdf2('sha256', $password, ADMIN_PASSWORD_SALT, 150000, 64);

    if (strtolower(trim($email)) !== strtolower(ADMIN_EMAIL)) {
        return false;
    }

    if (!hash_equals(ADMIN_PASSWORD_HASH, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = ADMIN_EMAIL;
    $_SESSION['admin_last_seen'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Token de seguranca invalido.');
    }
}
