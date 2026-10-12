<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Generic list / create / update / delete for the modules declared in Modules.
 *
 * Only columns declared in the module definition are ever written, and every
 * value is validated against its declared type, so a request cannot reach a
 * column that the screen does not own.
 */
final class CrudService
{
    public static function list(PDO $pdo, array $module, string $search = '', int $limit = 300): array
    {
        $limit = max(1, min($limit, 500));
        $where = '';
        $params = [];

        $search = trim($search);
        if ($search !== '' && $module['search'] !== []) {
            $clauses = [];
            foreach ($module['search'] as $index => $column) {
                $key = ':search' . $index;
                $clauses[] = "`$column` LIKE $key";
                $params['search' . $index] = '%' . $search . '%';
            }
            $where = ' WHERE (' . implode(' OR ', $clauses) . ')';
        }

        $statement = $pdo->prepare(
            sprintf('SELECT * FROM `%s`%s ORDER BY %s LIMIT %d', $module['table'], $where, $module['order_by'], $limit)
        );
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function count(PDO $pdo, array $module): int
    {
        return (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $module['table']))->fetchColumn();
    }

    /** Options for reference fields (asset_id, vendor_id, ...). */
    public static function referenceOptions(PDO $pdo, array $field): array
    {
        $table = $field['table'];
        $display = $field['display'];
        $rows = $pdo->query(sprintf('SELECT id, `%s` AS label FROM `%s` ORDER BY `%s` ASC', $display, $table, $display))
            ->fetchAll(PDO::FETCH_ASSOC);

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) $row['label'];
        }
        return $options;
    }

    public static function find(PDO $pdo, array $module, int $id): ?array
    {
        $statement = $pdo->prepare(sprintf('SELECT * FROM `%s` WHERE id = :id LIMIT 1', $module['table']));
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(PDO $pdo, array $module, int $userId, array $input): int
    {
        $values = self::validate($pdo, $module, $input, null);

        $columns = array_keys($values);
        $columns[] = 'created_by';
        $values['created_by'] = $userId;

        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $module['table'],
            implode(', ', array_map(static fn (string $c): string => "`$c`", $columns)),
            implode(', ', $placeholders)
        ));
        $statement->execute($values);
        $id = (int) $pdo->lastInsertId();

        ActivityService::record($pdo, $userId, 'created', $module['slug'], $id, sprintf('Created %s #%d', $module['singular'], $id));
        return $id;
    }

    public static function update(PDO $pdo, array $module, int $id, array $input, int $userId): void
    {
        if (self::find($pdo, $module, $id) === null) {
            throw new RuntimeException('That record no longer exists.');
        }

        $values = self::validate($pdo, $module, $input, $id);
        if ($values === []) {
            return;
        }

        $assignments = [];
        foreach (array_keys($values) as $column) {
            $assignments[] = "`$column` = :$column";
        }
        $values['id'] = $id;

        $statement = $pdo->prepare(sprintf(
            'UPDATE `%s` SET %s WHERE id = :id',
            $module['table'],
            implode(', ', $assignments)
        ));
        $statement->execute($values);

        ActivityService::record($pdo, $userId, 'updated', $module['slug'], $id, sprintf('Updated %s #%d', $module['singular'], $id));
    }

    public static function delete(PDO $pdo, array $module, int $id, int $userId): void
    {
        if (self::find($pdo, $module, $id) === null) {
            throw new RuntimeException('That record no longer exists.');
        }

        try {
            $statement = $pdo->prepare(sprintf('DELETE FROM `%s` WHERE id = :id', $module['table']));
            $statement->execute(['id' => $id]);
        } catch (PDOException $exception) {
            throw new RuntimeException('That record is still referenced by other office data and cannot be deleted.');
        }

        ActivityService::record($pdo, $userId, 'deleted', $module['slug'], $id, sprintf('Deleted %s #%d', $module['singular'], $id));
    }

    /**
     * Turn raw request input into a column => value map, rejecting anything the
     * module does not declare or that fails its type rules.
     */
    private static function validate(PDO $pdo, array $module, array $input, ?int $id): array
    {
        $values = [];

        foreach ($module['fields'] as $column => $field) {
            $raw = $input[$column] ?? null;
            $isOptional = ($field['optional'] ?? false) === true || ($field['required'] ?? false) !== true;

            if (is_array($raw)) {
                $raw = null;
            }
            $raw = $raw === null ? '' : trim((string) $raw);

            if ($raw === '') {
                if (($field['required'] ?? false) === true) {
                    throw new InvalidArgumentException($field['label'] . ' is required.');
                }
                $values[$column] = $isOptional ? null : $raw;
                continue;
            }

            $max = $field['max'] ?? null;
            if ($max !== null && mb_strlen($raw, 'UTF-8') > $max) {
                throw new InvalidArgumentException(sprintf('%s must be %d characters or fewer.', $field['label'], $max));
            }

            $values[$column] = match ($field['type']) {
                'select' => in_array($raw, $field['options'], true)
                    ? $raw
                    : throw new InvalidArgumentException($field['label'] . ' has an unsupported value.'),
                'email' => filter_var($raw, FILTER_VALIDATE_EMAIL) !== false
                    ? $raw
                    : throw new InvalidArgumentException($field['label'] . ' must be a valid email address.'),
                'url' => filter_var($raw, FILTER_VALIDATE_URL) !== false
                    ? $raw
                    : throw new InvalidArgumentException($field['label'] . ' must be a valid link.'),
                'number' => self::number($raw, $field),
                'date' => self::date($raw, $field),
                'datetime' => self::datetime($raw, $field),
                'reference' => self::reference($pdo, $raw, $field),
                default => $raw,
            };
        }

        return $values;
    }

    private static function number(string $raw, array $field): string
    {
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException($field['label'] . ' must be a number.');
        }
        $value = (float) $raw;
        if (isset($field['min']) && $value < (float) $field['min']) {
            throw new InvalidArgumentException($field['label'] . ' is below the allowed minimum.');
        }
        if (isset($field['max']) && $value > (float) $field['max']) {
            throw new InvalidArgumentException($field['label'] . ' is above the allowed maximum.');
        }
        return (string) $value;
    }

    private static function date(string $raw, array $field): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException($field['label'] . ' must be a valid date.');
        }
        return $parsed->format('Y-m-d');
    }

    private static function datetime(string $raw, array $field): string
    {
        foreach (['!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $raw);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }
        throw new InvalidArgumentException($field['label'] . ' must be a valid date and time.');
    }

    private static function reference(PDO $pdo, string $raw, array $field): int
    {
        if (!ctype_digit($raw)) {
            throw new InvalidArgumentException($field['label'] . ' is not a valid selection.');
        }
        $id = (int) $raw;
        $statement = $pdo->prepare(sprintf('SELECT 1 FROM `%s` WHERE id = :id LIMIT 1', $field['table']));
        $statement->execute(['id' => $id]);
        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException($field['label'] . ' is not a valid selection.');
        }
        return $id;
    }
}
