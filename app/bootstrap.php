<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/InstallService.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/LoginThrottle.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/ActivityService.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/RequestService.php';
require_once __DIR__ . '/TaskService.php';
require_once __DIR__ . '/InventoryService.php';
require_once __DIR__ . '/TeamService.php';
require_once __DIR__ . '/SettingsService.php';
require_once __DIR__ . '/ReportService.php';
require_once __DIR__ . '/Modules.php';
require_once __DIR__ . '/CrudService.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('officeflow_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function officeflow_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $configPath = dirname(__DIR__) . '/config/database.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Database config is missing. Copy config/database.example.php to config/database.php and fill in the database connection values.');
    }
    $config = require $configPath;
    foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('Database config is missing the required ' . $key . ' value.');
        }
    }

    $charset = $config['charset'] ?? 'utf8mb4';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) $config['port'],
        $config['database'],
        $charset
    );

    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}

function officeflow_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function officeflow_redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function officeflow_csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . officeflow_e(Csrf::token()) . '">';
}

function officeflow_verify_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && Csrf::verify($_POST['_csrf'] ?? null);
}

function officeflow_current_user(PDO $pdo): ?array
{
    return AuthService::currentUser($pdo);
}

function officeflow_require_user(PDO $pdo): array
{
    $user = officeflow_current_user($pdo);
    if (!$user) {
        officeflow_redirect('/login.php');
    }
    return $user;
}

function officeflow_is_installed(PDO $pdo): bool
{
    return InstallService::isInstalled($pdo);
}

function officeflow_asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

/**
 * The connecting address, used for rate limiting.
 *
 * Deliberately reads REMOTE_ADDR and not X-Forwarded-For: a client can send any
 * value in that header, which would let an attacker reset the throttle on every
 * request. If the app is later placed behind a trusted reverse proxy, whitelist
 * the proxy here instead of trusting the header blindly.
 */
function officeflow_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? 'unknown' : $ip;
}

function officeflow_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error', 'info'], true)) {
        $type = 'info';
    }
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function officeflow_take_flash(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
        return null;
    }
    return $flash;
}

function officeflow_setting(PDO $pdo, string $key, string $fallback = ''): string
{
    $statement = $pdo->prepare('SELECT setting_value FROM office_settings WHERE setting_key = :key LIMIT 1');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false || $value === null ? $fallback : (string) $value;
}
