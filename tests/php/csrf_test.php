<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/../../app/Csrf.php';

function expectCsrf(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

session_name('officeflow_csrf_test');
session_id(bin2hex(random_bytes(16)));
session_start();

$token = Csrf::token();
expectCsrf(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 'CSRF token must be a cryptographically random hex token');
expectCsrf(Csrf::verify($token), 'matching token should validate');
expectCsrf(!Csrf::verify($token . 'x'), 'tampered token should fail');
expectCsrf(!Csrf::verify(''), 'empty token should fail');
expectCsrf(Csrf::token() === $token, 'token should remain stable for the current session');

session_destroy();
echo "CSRF checks: PASS\n";
