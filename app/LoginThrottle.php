<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Brute-force protection for the sign-in form.
 *
 * Failures are counted per email and per IP inside a rolling window. The email
 * and IP are stored as salted hashes so a database leak does not hand over a
 * list of addresses, and the raw values never reach the table.
 *
 * All timestamps are written and compared using PHP's clock rather than
 * CURRENT_TIMESTAMP, so the window cannot drift when the database server runs in
 * a different timezone, and the logic stays portable to SQLite for the tests.
 */
final class LoginThrottle
{
    public const MAX_EMAIL_FAILURES = 5;
    public const MAX_IP_FAILURES = 20;
    public const WINDOW_MINUTES = 15;
    private const RETENTION_HOURS = 48;

    /** Seconds the caller must wait, or null when sign-in may proceed. */
    public static function retryAfter(PDO $pdo, string $email, string $ip): ?int
    {
        $emailHash = self::hash($pdo, $email);
        if (self::countFailures($pdo, 'email_hash', $emailHash) >= self::MAX_EMAIL_FAILURES) {
            return self::remainingSeconds($pdo, 'email_hash', $emailHash);
        }

        $ipHash = self::hash($pdo, $ip);
        if (self::countFailures($pdo, 'ip_hash', $ipHash) >= self::MAX_IP_FAILURES) {
            return self::remainingSeconds($pdo, 'ip_hash', $ipHash);
        }

        return null;
    }

    public static function recordFailure(PDO $pdo, string $email, string $ip): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO login_attempts (email_hash, ip_hash, successful, attempted_at)
             VALUES (:email, :ip, 0, :now)'
        );
        $statement->execute([
            'email' => self::hash($pdo, $email),
            'ip' => self::hash($pdo, $ip),
            'now' => self::now(),
        ]);

        self::cleanup($pdo);
    }

    /** A successful sign-in clears that email's failures but keeps the IP counter. */
    public static function recordSuccess(PDO $pdo, string $email): void
    {
        $statement = $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = :email AND successful = 0');
        $statement->execute(['email' => self::hash($pdo, $email)]);
    }

    public static function cleanup(PDO $pdo): void
    {
        // Only sweep occasionally so a busy sign-in page does not delete on every hit.
        if (random_int(1, 50) !== 1) {
            return;
        }

        $statement = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff');
        $statement->execute(['cutoff' => self::now(-self::RETENTION_HOURS * 3600)]);
    }

    public static function describeWait(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));
        return $minutes === 1 ? '1 minute' : $minutes . ' minutes';
    }

    private static function countFailures(PDO $pdo, string $column, string $hash): int
    {
        $statement = $pdo->prepare(sprintf(
            'SELECT COUNT(*) FROM login_attempts
             WHERE %s = :hash AND successful = 0 AND attempted_at >= :cutoff',
            $column
        ));
        $statement->execute(['hash' => $hash, 'cutoff' => self::now(-self::WINDOW_MINUTES * 60)]);
        return (int) $statement->fetchColumn();
    }

    private static function remainingSeconds(PDO $pdo, string $column, string $hash): int
    {
        $statement = $pdo->prepare(sprintf(
            'SELECT MAX(attempted_at) FROM login_attempts
             WHERE %s = :hash AND successful = 0 AND attempted_at >= :cutoff',
            $column
        ));
        $statement->execute(['hash' => $hash, 'cutoff' => self::now(-self::WINDOW_MINUTES * 60)]);
        $newest = $statement->fetchColumn();

        if ($newest === false || $newest === null) {
            return 0;
        }

        $unlockAt = strtotime((string) $newest) + (self::WINDOW_MINUTES * 60);
        return max(1, $unlockAt - time());
    }

    private static function now(int $offsetSeconds = 0): string
    {
        return date('Y-m-d H:i:s', time() + $offsetSeconds);
    }

    /**
     * Salted hash. The salt lives in office_settings so it is per installation;
     * it is created on first use.
     */
    private static function hash(PDO $pdo, string $value): string
    {
        return hash('sha256', self::salt($pdo) . '|' . mb_strtolower(trim($value), 'UTF-8'));
    }

    private static function salt(PDO $pdo): string
    {
        static $cached = null;
        if (is_string($cached)) {
            return $cached;
        }

        $statement = $pdo->prepare("SELECT setting_value FROM office_settings WHERE setting_key = 'security_salt' LIMIT 1");
        $statement->execute();
        $existing = $statement->fetchColumn();

        if (is_string($existing) && $existing !== '') {
            return $cached = $existing;
        }

        $generated = bin2hex(random_bytes(32));
        $insert = $pdo->prepare(
            "INSERT INTO office_settings (setting_key, setting_value, updated_by)
             VALUES ('security_salt', :value, NULL)"
        );
        $insert->execute(['value' => $generated]);

        // Re-read so a concurrent request cannot leave two different salts in play.
        $statement->execute();
        $stored = $statement->fetchColumn();
        return $cached = (is_string($stored) && $stored !== '') ? $stored : $generated;
    }
}
