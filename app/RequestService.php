<?php

declare(strict_types=1);

final class RequestService
{
    private const CATEGORIES = ['Facilities', 'Office supplies', 'Equipment', 'Other'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const STATUSES = ['new', 'in_progress', 'waiting', 'completed', 'cancelled'];

    /** Create a request on behalf of the authenticated user. */
    public static function create(PDO $pdo, int $requesterId, array $input): int
    {
        if ($requesterId < 1) {
            throw new InvalidArgumentException('A signed-in requester is required.');
        }

        $title = trim((string) ($input['title'] ?? ''));
        $details = trim((string) ($input['details'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $priority = trim((string) ($input['priority'] ?? 'normal'));
        $dueAt = self::normalizeDueAt($input['due_at'] ?? null);

        if (mb_strlen($title, 'UTF-8') < 4 || mb_strlen($title, 'UTF-8') > 180) {
            throw new InvalidArgumentException('Request title must be between 4 and 180 characters.');
        }
        if (mb_strlen($details, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('Request details must be 5000 characters or fewer.');
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Choose a valid request category.');
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('Choose a valid priority.');
        }

        $requestCode = 'OF-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $statement = $pdo->prepare(
            "INSERT INTO requests
                (request_code, title, details, category, priority, status, requester_id, due_at)
             VALUES
                (:request_code, :title, :details, :category, :priority, 'new', :requester_id, :due_at)"
        );
        $statement->execute([
            'request_code' => $requestCode,
            'title' => $title,
            'details' => $details !== '' ? $details : null,
            'category' => $category,
            'priority' => $priority,
            'requester_id' => $requesterId,
            'due_at' => $dueAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** List newest requests with names for the admin/staff screen. */
    public static function list(PDO $pdo, int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));
        $statement = $pdo->prepare(
            'SELECT r.id, r.request_code, r.title, r.details, r.category, r.priority,
                    r.status, r.due_at, r.completed_at, r.created_at,
                    requester.name AS requester_name, assignee.name AS assignee_name
             FROM requests r
             INNER JOIN users requester ON requester.id = r.requester_id
             LEFT JOIN users assignee ON assignee.id = r.assignee_id
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function summary(PDO $pdo): array
    {
        $summary = array_fill_keys(self::STATUSES, 0);
        $rows = $pdo->query('SELECT status, COUNT(*) AS total FROM requests GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (array_key_exists($row['status'], $summary)) {
                $summary[$row['status']] = (int) $row['total'];
            }
        }
        return $summary;
    }

    public static function countOpen(PDO $pdo): int
    {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM requests WHERE status NOT IN ('completed', 'cancelled')"
        )->fetchColumn();
    }

    /** Only an administrator may transition a request's status. */
    public static function updateStatus(PDO $pdo, int $requestId, string $status, string $actorRole): void
    {
        if ($actorRole !== 'admin') {
            throw new InvalidArgumentException('Only an administrator can update request status.');
        }
        if ($requestId < 1 || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Choose a valid request status.');
        }

        $statement = $pdo->prepare(
            'UPDATE requests
             SET status = :status,
                 completed_at = CASE WHEN :completed = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->bindValue(':status', $status, PDO::PARAM_STR);
        $statement->bindValue(':completed', $status === 'completed' ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':id', $requestId, PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM requests WHERE id = :id');
            $exists->execute(['id' => $requestId]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Request not found.');
            }
        }
    }

    private static function normalizeDueAt(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Due date must be a valid calendar date.');
        }

        return $date->format('Y-m-d 17:00:00');
    }
}
