<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/../../app/LoginThrottle.php';

function expectThrottle(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE office_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT,
    updated_by INTEGER,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_hash TEXT NOT NULL,
    ip_hash TEXT NOT NULL,
    successful INTEGER NOT NULL DEFAULT 0,
    attempted_at TEXT NOT NULL
)');

$email = 'alya@example.test';
$ip = '203.0.113.10';

expectThrottle(LoginThrottle::retryAfter($pdo, $email, $ip) === null, 'a fresh installation must allow sign-in');

for ($attempt = 1; $attempt < LoginThrottle::MAX_EMAIL_FAILURES; $attempt++) {
    LoginThrottle::recordFailure($pdo, $email, $ip);
    expectThrottle(
        LoginThrottle::retryAfter($pdo, $email, $ip) === null,
        "attempt $attempt must still be allowed below the limit"
    );
}

LoginThrottle::recordFailure($pdo, $email, $ip);
$wait = LoginThrottle::retryAfter($pdo, $email, $ip);
expectThrottle($wait !== null, 'reaching the email limit must lock sign-in');
expectThrottle($wait > 0 && $wait <= LoginThrottle::WINDOW_MINUTES * 60, 'the wait must sit inside the window');
expectThrottle(LoginThrottle::describeWait(90) === '2 minutes', 'wait must be described in whole minutes');

$raw = $pdo->query('SELECT email_hash, ip_hash FROM login_attempts LIMIT 1')->fetch(PDO::FETCH_ASSOC);
expectThrottle($raw['email_hash'] !== $email, 'the email must never be stored in plain text');
expectThrottle(strlen($raw['email_hash']) === 64, 'the email must be stored as a sha256 hash');
expectThrottle(strlen($raw['ip_hash']) === 64, 'the IP must be stored as a sha256 hash');
expectThrottle(
    (string) $pdo->query("SELECT setting_value FROM office_settings WHERE setting_key = 'security_salt'")->fetchColumn() !== '',
    'a per-install salt must be created'
);

LoginThrottle::recordSuccess($pdo, $email);
expectThrottle(LoginThrottle::retryAfter($pdo, $email, $ip) === null, 'a successful sign-in must clear the email failures');

// A different email from the same address still counts toward the IP limit.
for ($attempt = 0; $attempt < LoginThrottle::MAX_IP_FAILURES; $attempt++) {
    LoginThrottle::recordFailure($pdo, 'other' . $attempt . '@example.test', $ip);
}
expectThrottle(
    LoginThrottle::retryAfter($pdo, 'brand-new@example.test', $ip) !== null,
    'many failures from one address must lock that address'
);
expectThrottle(
    LoginThrottle::retryAfter($pdo, 'brand-new@example.test', '198.51.100.7') === null,
    'a different address must not be locked by another address'
);

echo "LoginThrottle checks: PASS\n";
