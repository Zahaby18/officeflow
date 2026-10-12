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
    error_log('OfficeFlow activity preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$entries = ActivityService::recent($pdo, 200);
$currentPage = 'Activity log';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">MANAGEMENT</p><h1>Activity log</h1><p>See a record of important changes made in the workspace.</p></div>
</section>
<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Recent activity</h2><p><?= count($entries) ?> most recent entr<?= count($entries) === 1 ? 'y' : 'ies' ?>. Summaries never include sensitive field values.</p></div></header>
  <?php if ($entries === []): ?>
    <div class="app-empty"><strong>Nothing recorded yet</strong>Activity appears here as the team works.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table">
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Area</th><th>Summary</th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td><?= officeflow_e(date('M j, Y H:i', strtotime((string) $entry['created_at']))) ?></td>
          <td><?= officeflow_e($entry['actor_name'] ?? 'System') ?></td>
          <td><span class="status status-review"><?= officeflow_e(ucwords(str_replace('_', ' ', (string) $entry['action']))) ?></span></td>
          <td><?= officeflow_e(ucwords(str_replace('_', ' ', (string) $entry['entity_type']))) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?></td>
          <td><?= officeflow_e($entry['summary']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
