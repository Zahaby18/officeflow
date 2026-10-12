<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/../../app/AuthService.php';

function expect(bool $condition, string $message): void
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
    is_active INTEGER NOT NULL DEFAULT 1
)");

$insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)');
$insert->execute(['Alya Admin', 'alya@example.test', password_hash('correct horse battery staple', PASSWORD_DEFAULT), 'admin', 1]);
$insert->execute(['Inactive Staff', 'inactive@example.test', password_hash('staff-password', PASSWORD_DEFAULT), 'staff', 0]);

session_name('officeflow_test');
session_id(bin2hex(random_bytes(16)));
session_start();

$user = AuthService::attempt($pdo, '  ALYA@example.test ', 'correct horse battery staple');
expect(is_array($user), 'valid credentials must authenticate');
expect($user['id'] === 1, 'authenticated user id must be returned');
expect($user['role'] === 'admin', 'authenticated role must be returned');
expect(!array_key_exists('password_hash', $user), 'password hash must not be returned to callers');
expect(($_SESSION['user_id'] ?? null) === 1, 'user id must be stored in the session');

AuthService::logout();
expect(!isset($_SESSION['user_id']), 'logout must clear the authenticated session');

expect(AuthService::attempt($pdo, 'alya@example.test', 'wrong-password') === null, 'wrong password must fail');
expect(!isset($_SESSION['user_id']), 'failed login must not set an authenticated session');
expect(AuthService::attempt($pdo, 'inactive@example.test', 'staff-password') === null, 'inactive accounts must not authenticate');

session_id(bin2hex(random_bytes(16)));
session_start();
$_SESSION['user_id'] = 1;
$current = AuthService::currentUser($pdo);
expect(is_array($current) && $current['email'] === 'alya@example.test', 'current user must be loaded from the database');
$pdo->exec('UPDATE users SET is_active = 0 WHERE id = 1');
expect(AuthService::currentUser($pdo) === null, 'deactivated user sessions must be rejected');
expect(!isset($_SESSION['user_id']), 'deactivated user session must be cleared');

session_destroy();
echo "AuthService integration checks: PASS\n";
