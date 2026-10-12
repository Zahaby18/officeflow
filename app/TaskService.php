<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class TaskService
{
    public const CATEGORIES = ['Facilities', 'Inventory', 'Supplies', 'Equipment', 'General'];
    public const RECURRENCES = ['none', 'daily', 'weekly', 'monthly'];
    public const STATUSES = ['open', 'in_progress', 'completed', 'skipped'];

    public static function create(PDO $pdo, int $userId, array $input): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        $details = trim((string) ($input['details'] ?? ''));
        $category = trim((string) ($input['category'] ?? 'General'));
        $recurrence = trim((string) ($input['recurrence'] ?? 'none'));
        $dueAt = self::dateTimeOrNull($input['due_at'] ?? null, 'Due date');
        $assignedTo = self::optionalUserId($pdo, $input['assigned_to'] ?? null);

        if (mb_strlen($title, 'UTF-8') < 4 || mb_strlen($title, 'UTF-8') > 180) {
            throw new InvalidArgumentException('Task title must be between 4 and 180 characters.');
        }
        if (mb_strlen($details, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('Task details must be 5000 characters or fewer.');
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Choose a valid task category.');
        }
        if (!in_array($recurrence, self::RECURRENCES, true)) {
            throw new InvalidArgumentException('Choose a valid recurrence.');
        }

        $statement = $pdo->prepare(
            "INSERT INTO tasks (title, details, category, status, recurrence, due_at, assigned_to, created_by)
             VALUES (:title, :details, :category, 'open', :recurrence, :due_at, :assigned_to, :created_by)"
        );
        $statement->execute([
            'title' => $title,
            'details' => $details !== '' ? $details : null,
            'category' => $category,
            'recurrence' => $recurrence,
            'due_at' => $dueAt,
            'assigned_to' => $assignedTo,
            'created_by' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        ActivityService::record($pdo, $userId, 'created', 'tasks', $id, 'Created task #' . $id);
        if ($assignedTo !== null) {
            NotificationService::notify($pdo, $assignedTo, $userId, 'task_assigned', 'A task was assigned to you', $title, '/tasks.php');
        }
        return $id;
    }

    public static function list(PDO $pdo, string $status = '', int $limit = 300): array
    {
        $limit = max(1, min($limit, 500));
        $sql = 'SELECT t.*, u.name AS assignee_name, c.name AS creator_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN users c ON c.id = t.created_by';
        $params = [];
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $sql .= ' WHERE t.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY (t.status IN (\'open\',\'in_progress\')) DESC, t.due_at IS NULL, t.due_at ASC, t.id DESC LIMIT ' . $limit;

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function setStatus(PDO $pdo, int $id, string $status, int $userId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Choose a valid task status.');
        }

        $task = self::find($pdo, $id);
        if ($task === null) {
            throw new RuntimeException('That task no longer exists.');
        }

        $isDone = $status === 'completed';
        $statement = $pdo->prepare(
            'UPDATE tasks
             SET status = :status,
                 completed_by = :completed_by,
                 completed_at = CASE WHEN :done = 1 THEN CURRENT_TIMESTAMP ELSE NULL END
             WHERE id = :id'
        );
        $statement->bindValue(':status', $status, PDO::PARAM_STR);
        $statement->bindValue(':completed_by', $isDone ? $userId : null, $isDone ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $statement->bindValue(':done', $isDone ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        ActivityService::record($pdo, $userId, 'status_changed', 'tasks', $id, sprintf('Task #%d set to %s', $id, $status));

        if ($isDone && $task['recurrence'] !== 'none' && $task['assigned_to'] !== null) {
            NotificationService::notify(
                $pdo,
                (int) $task['assigned_to'],
                $userId,
                'task_recurring',
                'A recurring task was completed',
                $task['title'],
                '/tasks.php'
            );
        }
    }

    public static function delete(PDO $pdo, int $id, int $userId): void
    {
        if (self::find($pdo, $id) === null) {
            throw new RuntimeException('That task no longer exists.');
        }
        $statement = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $statement->execute(['id' => $id]);
        ActivityService::record($pdo, $userId, 'deleted', 'tasks', $id, 'Deleted task #' . $id);
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM tasks WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function summary(PDO $pdo): array
    {
        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM tasks GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['status'], $summary)) {
                $summary[$row['status']] = (int) $row['total'];
            }
        }
        return $summary;
    }

    public static function dueToday(PDO $pdo): int
    {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM tasks
             WHERE status IN ('open','in_progress') AND due_at IS NOT NULL AND DATE(due_at) = CURRENT_DATE"
        )->fetchColumn();
    }

    public static function overdue(PDO $pdo): int
    {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM tasks
             WHERE status IN ('open','in_progress') AND due_at IS NOT NULL AND due_at < CURRENT_TIMESTAMP"
        )->fetchColumn();
    }

    public static function assignableUsers(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }
        return $options;
    }

    private static function optionalUserId(PDO $pdo, mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (!ctype_digit($value)) {
            throw new InvalidArgumentException('Choose a valid assignee.');
        }
        $statement = $pdo->prepare('SELECT 1 FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $statement->execute(['id' => (int) $value]);
        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('Choose a valid assignee.');
        }
        return (int) $value;
    }

    private static function dateTimeOrNull(mixed $value, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        foreach (['!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $format === '!Y-m-d' ? $parsed->format('Y-m-d 17:00:00') : $parsed->format('Y-m-d H:i:s');
            }
        }
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }
}
