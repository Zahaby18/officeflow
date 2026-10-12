<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class NotificationService
{
    public static function notify(
        PDO $pdo,
        int $userId,
        ?int $actorId,
        string $type,
        string $title,
        string $body = '',
        string $targetUrl = ''
    ): void {
        if ($actorId !== null && $actorId === $userId) {
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO notifications (user_id, actor_id, notification_type, title, body, target_url)
             VALUES (:user_id, :actor_id, :type, :title, :body, :target_url)'
        );
        $statement->execute([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'type' => mb_substr($type, 0, 60, 'UTF-8'),
            'title' => mb_substr($title, 0, 180, 'UTF-8'),
            'body' => $body === '' ? null : mb_substr($body, 0, 500, 'UTF-8'),
            'target_url' => $targetUrl === '' ? null : mb_substr($targetUrl, 0, 500, 'UTF-8'),
        ]);
    }

    /** Notify every active administrator except the person who caused the event. */
    public static function notifyAdmins(
        PDO $pdo,
        int $actorId,
        string $type,
        string $title,
        string $body = '',
        string $targetUrl = ''
    ): void {
        $statement = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            self::notify($pdo, (int) $adminId, $actorId, $type, $title, $body, $targetUrl);
        }
    }

    public static function forUser(PDO $pdo, int $userId, int $limit = 12): array
    {
        $limit = max(1, min($limit, 50));
        $statement = $pdo->prepare(
            'SELECT n.id, n.notification_type, n.title, n.body, n.target_url, n.read_at, n.created_at,
                    u.name AS actor_name
             FROM notifications n
             LEFT JOIN users u ON u.id = n.actor_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function unreadCount(PDO $pdo, int $userId): int
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public static function markAllRead(PDO $pdo, int $userId): void
    {
        $statement = $pdo->prepare('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND read_at IS NULL');
        $statement->execute(['user_id' => $userId]);
    }

    public static function markRead(PDO $pdo, int $id, int $userId): void
    {
        $statement = $pdo->prepare('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id AND read_at IS NULL');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
    }

    /** Human wording for a relative timestamp, used by the notification list. */
    public static function relativeTime(string $timestamp): string
    {
        $then = strtotime($timestamp);
        if ($then === false) {
            return '';
        }
        $seconds = max(0, time() - $then);
        if ($seconds < 60) {
            return 'just now';
        }
        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
        }
        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
        }
        $days = intdiv($hours, 24);
        if ($days < 7) {
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }
        return date('M j, Y', $then);
    }
}
