<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/app/bootstrap.php';

try {
    $pdo = officeflow_db();
    if (!InstallService::isInstalled($pdo)) {
        officeflow_redirect('/install.php');
    }
} catch (Throwable $exception) {
    error_log('OfficeFlow notifications preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/notifications.php');
    }
    if (($_POST['action'] ?? '') === 'read_all') {
        NotificationService::markAllRead($pdo, $user['id']);
        officeflow_flash('success', 'All notifications marked as read.');
    }
    officeflow_redirect('/notifications.php');
}

$notifications = NotificationService::forUser($pdo, $user['id'], 50);
$unread = NotificationService::unreadCount($pdo, $user['id']);
$currentPage = 'Notifications';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">WORKSPACE</p><h1>Notifications</h1><p>Updates addressed to your account in this office workspace.</p></div>
  <div class="app-heading-actions">
    <?php if ($unread > 0): ?>
      <form method="post" action="/notifications.php"><?= officeflow_csrf_input() ?><input type="hidden" name="action" value="read_all"><button class="button button-secondary" type="submit">Mark all read</button></form>
    <?php endif; ?>
  </div>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Inbox</h2><p><?= $unread ?> unread of <?= count($notifications) ?> shown</p></div></header>
  <?php if ($notifications === []): ?>
    <div class="app-empty"><strong>No notifications yet</strong>You are notified when requests, tasks, or office records involve you.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table">
      <thead><tr><th>Notification</th><th>From</th><th>When</th><th>State</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($notifications as $notification): ?>
        <tr>
          <td><span class="app-request-title"><strong><?= officeflow_e($notification['title']) ?></strong><small><?= officeflow_e($notification['body'] ?? '') ?></small></span></td>
          <td><?= officeflow_e($notification['actor_name'] ?? 'System') ?></td>
          <td><?= officeflow_e(NotificationService::relativeTime((string) $notification['created_at'])) ?></td>
          <td><span class="status status-<?= $notification['read_at'] === null ? 'review' : 'complete' ?>"><?= $notification['read_at'] === null ? 'Unread' : 'Read' ?></span></td>
          <td><?php if ($notification['target_url'] !== null): ?><a class="text-button" href="<?= officeflow_e($notification['target_url']) ?>">Open →</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
