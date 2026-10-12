<?php

declare(strict_types=1);

final class AuthService
{
    /**
     * Authenticate an active office user. Returns a safe user record without the password hash.
     * Email matching is case-insensitive to behave consistently across SQLite tests and MySQL.
     */
    public static function attempt(PDO $pdo, string $email, string $password): ?array
    {
        $normalizedEmail = mb_strtolower(trim($email), 'UTF-8');
        if ($normalizedEmail === '' || $password === '') {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT id, name, email, password_hash, role, is_active
             FROM users
             WHERE LOWER(email) = LOWER(:email)
             LIMIT 1'
        );
        $statement->execute(['email' => $normalizedEmail]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['is_active'] !== 1 || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        if (!in_array($row['role'], ['admin', 'staff'], true)) {
            return null;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];

        return self::safeUser($row);
    }

    /** Return a fresh active user record so deactivation takes effect on the next request. */
    public static function currentUser(PDO $pdo): ?array
    {
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$userId || $userId < 1) {
            unset($_SESSION['user_id']);
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT id, name, email, role, is_active
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['is_active'] !== 1 || !in_array($row['role'], ['admin', 'staff'], true)) {
            unset($_SESSION['user_id']);
            return null;
        }

        return self::safeUser($row);
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $cookie = session_get_cookie_params();
        if (!headers_sent() && ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'] ?: '/',
                'domain' => $cookie['domain'],
                'secure' => (bool) $cookie['secure'],
                'httponly' => (bool) $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function safeUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
        ];
    }
}
