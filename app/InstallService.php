<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class InstallService
{
    /** True when the OfficeFlow tables exist at all. */
    public static function schemaExists(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM users LIMIT 1');
            return true;
        } catch (PDOException $exception) {
            // Table missing: the schema has not been created yet.
            return false;
        }
    }

    /**
     * True when an active administrator exists, which is what "installed" means.
     *
     * Deliberately not "any user exists": an installation may legitimately carry
     * an inactive placeholder account (for example the Sample data account that
     * ships with the example content). That must not close the setup screen.
     */
    public static function isInstalled(PDO $pdo): bool
    {
        if (!self::schemaExists($pdo)) {
            return false;
        }

        $statement = $pdo->query(
            "SELECT 1 FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1"
        );
        return (bool) $statement->fetchColumn();
    }

    /** True when the tables exist but no example content has been loaded. */
    public static function hasExampleContent(PDO $pdo): bool
    {
        try {
            return (bool) $pdo->query('SELECT 1 FROM assets LIMIT 1')->fetchColumn();
        } catch (PDOException $exception) {
            return false;
        }
    }

    /** Create the only first-admin account. Returns null once setup has already happened. */
    public static function createFirstAdmin(
        PDO $pdo,
        string $name,
        string $email,
        string $password,
        string $officeName
    ): ?array {
        $name = trim($name);
        $email = mb_strtolower(trim($email), 'UTF-8');
        $officeName = trim($officeName);

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 120) {
            throw new InvalidArgumentException('Name must be between 2 and 120 characters.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 190) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (strlen($password) < 12 || strlen($password) > 200) {
            throw new InvalidArgumentException('Password must be between 12 and 200 characters.');
        }
        if ($officeName === '' || mb_strlen($officeName, 'UTF-8') > 160) {
            throw new InvalidArgumentException('Office name must be between 1 and 160 characters.');
        }

        $hasMysqlLock = false;
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lock = $pdo->query("SELECT GET_LOCK('officeflow_first_admin', 10)")->fetchColumn();
            if ((string) $lock !== '1') {
                throw new RuntimeException('Could not obtain the initial setup lock. Try again.');
            }
            $hasMysqlLock = true;
        }

        try {
            $pdo->beginTransaction();
            if (self::isInstalled($pdo)) {
                $pdo->rollBack();
                return null;
            }

            $statement = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, is_active)
                 VALUES (:name, :email, :password_hash, 'admin', 1)"
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $setting = $pdo->prepare(
                'INSERT INTO office_settings (setting_key, setting_value, updated_by)
                 VALUES (:key, :value, :updated_by)'
            );
            $setting->execute([
                'key' => 'office_name',
                'value' => $officeName,
                'updated_by' => $userId,
            ]);

            $pdo->commit();

            return [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'admin',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } finally {
            if ($hasMysqlLock) {
                $pdo->query("SELECT RELEASE_LOCK('officeflow_first_admin')");
            }
        }
    }
}
