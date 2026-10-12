<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new LogicException('Start the session before requesting a CSRF token.');
        }

        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function verify(mixed $candidate): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !is_string($candidate) || $candidate === '') {
            return false;
        }

        $stored = $_SESSION['_csrf_token'] ?? null;
        return is_string($stored) && $stored !== '' && hash_equals($stored, $candidate);
    }
}
