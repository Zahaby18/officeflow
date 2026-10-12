<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class ActivityService
{
    /** Record a high-level event. Never store sensitive field values in the summary. */
    public static function record(
        PDO $pdo,
        ?int $actorId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $summary
    ): void {
        $statement = $pdo->prepare(
            'INSERT INTO activity_logs (actor_id, action, entity_type, entity_id, summary)
             VALUES (:actor_id, :action, :entity_type, :entity_id, :summary)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => mb_substr($action, 0, 80, 'UTF-8'),
            'entity_type' => mb_substr($entityType, 0, 60, 'UTF-8'),
            'entity_id' => $entityId,
            'summary' => mb_substr($summary, 0, 500, 'UTF-8'),
        ]);
    }

    public static function recent(PDO $pdo, int $limit = 150): array
    {
        $limit = max(1, min($limit, 500));
        $statement = $pdo->prepare(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.summary, a.created_at,
                    u.name AS actor_name
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.actor_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function count(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    }
}
