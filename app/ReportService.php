<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class ReportService
{
    /** Everything the Reports screen and the Home dashboard need, in one pass. */
    public static function overview(PDO $pdo): array
    {
        return [
            'requests' => RequestService::summary($pdo),
            'requests_open' => RequestService::countOpen($pdo),
            'requests_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM requests'),
            'tasks' => TaskService::summary($pdo),
            'tasks_due_today' => TaskService::dueToday($pdo),
            'tasks_overdue' => TaskService::overdue($pdo),
            'assets' => self::statusBreakdown($pdo, 'assets'),
            'assets_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM assets'),
            'inventory_items' => InventoryService::count($pdo),
            'inventory_low' => InventoryService::lowStockCount($pdo),
            'maintenance' => self::statusBreakdown($pdo, 'maintenance_records'),
            'maintenance_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM maintenance_records'),
            'maintenance_open' => self::scalar(
                $pdo,
                "SELECT COUNT(*) FROM maintenance_records WHERE status NOT IN ('done','cancelled')"
            ),
            'rooms' => self::statusBreakdown($pdo, 'rooms'),
            'rooms_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM rooms'),
            'vendors_active' => self::scalar($pdo, "SELECT COUNT(*) FROM vendors WHERE status = 'active'"),
            'vendors_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM vendors'),
            'documents_published' => self::scalar($pdo, "SELECT COUNT(*) FROM documents WHERE status = 'published'"),
            'documents_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM documents'),
            'team_active' => TeamService::countActive($pdo),
            'team_total' => self::scalar($pdo, 'SELECT COUNT(*) FROM users'),
            'activity_total' => ActivityService::count($pdo),
        ];
    }

    /** Requests per week for the last eight weeks, for the Reports trend table. */
    public static function requestsPerWeek(PDO $pdo, int $weeks = 8): array
    {
        $weeks = max(1, min($weeks, 26));
        $statement = $pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%x-W%v') AS bucket, COUNT(*) AS total
             FROM requests
             WHERE created_at >= (CURRENT_DATE - INTERVAL :days DAY)
             GROUP BY bucket
             ORDER BY bucket ASC"
        );
        $statement->bindValue(':days', $weeks * 7, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function requestsByCategory(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT category, COUNT(*) AS total FROM requests GROUP BY category ORDER BY total DESC, category ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function scalar(PDO $pdo, string $sql): int
    {
        return (int) $pdo->query($sql)->fetchColumn();
    }

    private static function statusBreakdown(PDO $pdo, string $table): array
    {
        $breakdown = [];
        $rows = $pdo->query(sprintf('SELECT status, COUNT(*) AS total FROM `%s` GROUP BY status', $table))
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $breakdown[$row['status']] = (int) $row['total'];
        }
        return $breakdown;
    }
}
