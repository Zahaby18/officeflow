<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class SettingsService
{
    /** Keys the settings screen is allowed to write. */
    public const EDITABLE = ['office_name', 'office_location', 'office_timezone', 'support_email'];

    public static function all(PDO $pdo): array
    {
        $settings = array_fill_keys(self::EDITABLE, '');
        foreach ($pdo->query('SELECT setting_key, setting_value FROM office_settings')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        }
        return $settings;
    }

    public static function get(PDO $pdo, string $key, string $fallback = ''): string
    {
        if (!in_array($key, self::EDITABLE, true)) {
            return $fallback;
        }
        $statement = $pdo->prepare('SELECT setting_value FROM office_settings WHERE setting_key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();
        return $value === false || $value === null || $value === '' ? $fallback : (string) $value;
    }

    public static function save(PDO $pdo, int $userId, array $input): void
    {
        $officeName = trim((string) ($input['office_name'] ?? ''));
        if ($officeName === '' || mb_strlen($officeName, 'UTF-8') > 160) {
            throw new InvalidArgumentException('Office name is required and must be 160 characters or fewer.');
        }

        $location = trim((string) ($input['office_location'] ?? ''));
        if (mb_strlen($location, 'UTF-8') > 160) {
            throw new InvalidArgumentException('Location must be 160 characters or fewer.');
        }

        $timezone = trim((string) ($input['office_timezone'] ?? ''));
        if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Choose a valid timezone.');
        }

        $supportEmail = trim((string) ($input['support_email'] ?? ''));
        if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Support email must be a valid email address.');
        }

        $values = [
            'office_name' => $officeName,
            'office_location' => $location,
            'office_timezone' => $timezone,
            'support_email' => $supportEmail,
        ];

        $statement = $pdo->prepare(
            'INSERT INTO office_settings (setting_key, setting_value, updated_by)
             VALUES (:key, :value, :updated_by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );

        foreach ($values as $key => $value) {
            $statement->execute([
                'key' => $key,
                'value' => $value === '' ? null : $value,
                'updated_by' => $userId,
            ]);
        }

        ActivityService::record($pdo, $userId, 'updated', 'settings', null, 'Updated office settings');
    }

    /** A short list of common timezones so the select stays usable. */
    public static function commonTimezones(): array
    {
        return [
            'Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Singapore', 'Asia/Kuala_Lumpur',
            'Asia/Bangkok', 'Asia/Manila', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London',
            'Europe/Amsterdam', 'Europe/Berlin', 'America/New_York', 'America/Chicago',
            'America/Los_Angeles', 'Australia/Sydney', 'UTC',
        ];
    }
}
