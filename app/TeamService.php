<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class TeamService
{
    public const ROLES = ['admin', 'staff'];

    public static function list(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT id, name, email, role, is_active, last_login_at, created_at
             FROM users
             ORDER BY is_active DESC, role ASC, name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(PDO $pdo, int $actorId, array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8');
        $role = trim((string) ($input['role'] ?? 'staff'));
        $password = (string) ($input['password'] ?? '');

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 120) {
            throw new InvalidArgumentException('Name must be between 2 and 120 characters.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 190) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException('Choose a valid role.');
        }
        self::assertPassword($password);

        $exists = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(email) = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn()) {
            throw new InvalidArgumentException('That email already has an account.');
        }

        $statement = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :password_hash, :role, 1)"
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        $id = (int) $pdo->lastInsertId();

        ActivityService::record($pdo, $actorId, 'created', 'team', $id, 'Created team account #' . $id);
        NotificationService::notify($pdo, $id, $actorId, 'account_created', 'Your OfficeFlow account is ready', 'Sign in with the email and password your administrator shared with you.', '/login.php');
        return $id;
    }

    public static function setRole(PDO $pdo, int $id, string $role, int $actorId): void
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException('Choose a valid role.');
        }
        $user = self::find($pdo, $id);
        if ($user === null) {
            throw new RuntimeException('That account no longer exists.');
        }
        if ($id === $actorId && $role !== 'admin') {
            throw new InvalidArgumentException('You cannot remove your own administrator access.');
        }
        if ($user['role'] === 'admin' && $role !== 'admin') {
            self::assertAnotherActiveAdminExists($pdo, $id);
        }

        $statement = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $statement->execute(['role' => $role, 'id' => $id]);
        ActivityService::record($pdo, $actorId, 'role_changed', 'team', $id, sprintf('Account #%d is now %s', $id, $role));
    }

    public static function setActive(PDO $pdo, int $id, bool $active, int $actorId): void
    {
        $user = self::find($pdo, $id);
        if ($user === null) {
            throw new RuntimeException('That account no longer exists.');
        }
        if (!$active && $id === $actorId) {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }
        if (!$active && $user['role'] === 'admin') {
            self::assertAnotherActiveAdminExists($pdo, $id);
        }

        $statement = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $statement->bindValue(':is_active', $active ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        ActivityService::record($pdo, $actorId, $active ? 'activated' : 'deactivated', 'team', $id, 'Account #' . $id);
    }

    public static function resetPassword(PDO $pdo, int $id, string $password, int $actorId): void
    {
        if (self::find($pdo, $id) === null) {
            throw new RuntimeException('That account no longer exists.');
        }
        self::assertPassword($password);

        $statement = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $statement->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
        ActivityService::record($pdo, $actorId, 'password_reset', 'team', $id, 'Reset the password for account #' . $id);
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function countActive(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    }

    private static function assertPassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 200) {
            throw new InvalidArgumentException('Password must be between 12 and 200 characters.');
        }
    }

    /** An installation must never lose its last active administrator. */
    private static function assertAnotherActiveAdminExists(PDO $pdo, int $excludingId): void
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> :id"
        );
        $statement->execute(['id' => $excludingId]);
        if ((int) $statement->fetchColumn() === 0) {
            throw new InvalidArgumentException('OfficeFlow needs at least one active administrator.');
        }
    }
}
