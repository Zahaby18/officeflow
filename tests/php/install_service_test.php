<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/InstallService.php';

function expectInstall(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE office_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT,
    updated_by INTEGER,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$result = InstallService::createFirstAdmin(
    $pdo,
    'Alya Dwi',
    'alya@example.test',
    'A sufficiently long passphrase',
    'Studio Example'
);
expectInstall(is_array($result), 'first valid admin should be created');
expectInstall($result['role'] === 'admin', 'first account must receive the admin role');
expectInstall(!array_key_exists('password_hash', $result), 'installer must return no password hash');

$stored = $pdo->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);
expectInstall(password_verify('A sufficiently long passphrase', $stored['password_hash']), 'password must be stored as a secure hash');
expectInstall((int) $stored['is_active'] === 1, 'first admin must be active');
expectInstall($pdo->query("SELECT setting_value FROM office_settings WHERE setting_key = 'office_name'")->fetchColumn() === 'Studio Example', 'office name should be saved as a setting');
expectInstall(InstallService::createFirstAdmin($pdo, 'Second Admin', 'second@example.test', 'Another long password', 'Other') === null, 'second setup attempt must be rejected');
expectInstall((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'installer must never create a second first admin');

try {
    InstallService::createFirstAdmin($pdo, 'Bad', 'not-an-email', 'short', 'Office');
    throw new RuntimeException('invalid install input should throw');
} catch (InvalidArgumentException) {
}

echo "InstallService checks: PASS\n";
