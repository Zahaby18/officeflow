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
    error_log('OfficeFlow notification detail preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$notificationId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

// Opening a notification marks it read, which is what the badge counts.
NotificationService::markRead($pdo, $notificationId, $user['id']);
$notification = NotificationService::find($pdo, $notificationId, $user['id']);
$currentPage = 'Notifications';

if ($notification === null) {
    http_response_code(404);
    require __DIR__ . '/app/views/app-header.php';
    echo '<section class="app-surface page-placeholder"><div><span class="placeholder-icon"><svg class="icon"><use href="#i-bell"/></svg></span><h2>Notification not found</h2><p>It may have been removed, or it belongs to another account.</p><a class="button button-secondary" href="/notifications.php">Back to notifications</a></div></section>';
    require __DIR__ . '/app/views/app-footer.php';
    exit;
}

require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div>
    <p class="eyebrow">NOTIFICATION · <?= officeflow_e(strtoupper(str_replace('_', ' ', (string) $notification['notification_type']))) ?></p>
    <h1><?= officeflow_e($notification['title']) ?></h1>
    <p><?= officeflow_e($notification['actor_name'] ?? 'System') ?> · <?= officeflow_e(date('M j, Y H:i', strtotime((string) $notification['created_at']))) ?></p>
  </div>
  <div class="app-heading-actions"><a class="button button-secondary" href="/notifications.php">Back to notifications</a></div>
</section>

<section class="app-surface">
  <header class="app-surface-head"><div><h2>Message</h2><p>The full text of this notification</p></div><span class="status status-complete">Read</span></header>
  <div class="detail-body">
    <p class="detail-text"><?= $notification['body'] === null || trim((string) $notification['body']) === '' ? 'This notification has no extra description.' : nl2br(officeflow_e($notification['body'])) ?></p>
  </div>
  <div class="app-table-wrap"><table class="app-table"><tbody>
    <tr><th>From</th><td><?= officeflow_e($notification['actor_name'] ?? 'System') ?></td></tr>
    <tr><th>Received</th><td><?= officeflow_e(date('M j, Y H:i', strtotime((string) $notification['created_at']))) ?></td></tr>
    <tr><th>Type</th><td><?= officeflow_e(ucwords(str_replace('_', ' ', (string) $notification['notification_type']))) ?></td></tr>
  </tbody></table></div>
  <?php if ($notification['target_url'] !== null): ?>
    <div class="detail-actions"><a class="button button-primary" href="<?= officeflow_e($notification['target_url']) ?>">Open the related record <span aria-hidden="true">→</span></a></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
