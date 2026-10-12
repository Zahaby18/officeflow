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
    error_log('OfficeFlow reports preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$overview = ReportService::overview($pdo);
$byCategory = ReportService::requestsByCategory($pdo);
$perWeek = ReportService::requestsPerWeek($pdo);
$currentPage = 'Reports';
require __DIR__ . '/app/views/app-header.php';

$cards = [
    ['Requests open', $overview['requests_open'], $overview['requests_total'] . ' total'],
    ['Tasks open', $overview['tasks']['open'] + $overview['tasks']['in_progress'], $overview['tasks_overdue'] . ' overdue'],
    ['Maintenance open', $overview['maintenance_open'], $overview['maintenance_total'] . ' total'],
    ['Low stock items', $overview['inventory_low'], $overview['inventory_items'] . ' tracked'],
];
?>
<section class="app-page-heading">
  <div><p class="eyebrow">MANAGEMENT</p><h1>Reports</h1><p>Review office workload and operational trends.</p></div>
</section>

<section class="app-metrics" aria-label="Headline numbers">
  <?php foreach ($cards as [$label, $value, $note]): ?>
    <article class="app-metric"><span class="app-metric-label"><?= officeflow_e($label) ?></span><strong class="app-metric-value"><?= (int) $value ?></strong><div class="app-metric-note"><?= officeflow_e($note) ?></div></article>
  <?php endforeach; ?>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Requests by status</h2><p>Where office requests currently sit</p></div></header>
  <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
    <?php foreach ($overview['requests'] as $status => $count): ?>
      <tr><td><?= officeflow_e(ucwords(str_replace('_', ' ', $status))) ?></td><td><?= (int) $count ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Requests by category</h2><p>What the office asks for most</p></div></header>
  <?php if ($byCategory === []): ?>
    <div class="app-empty"><strong>No requests yet</strong>Category totals appear once requests exist.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Category</th><th>Count</th></tr></thead><tbody>
      <?php foreach ($byCategory as $row): ?>
        <tr><td><?= officeflow_e($row['category']) ?></td><td><?= (int) $row['total'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Requests per week</h2><p>Last eight weeks</p></div></header>
  <?php if ($perWeek === []): ?>
    <div class="app-empty"><strong>No trend yet</strong>Weekly totals appear once requests exist.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Week</th><th>Requests</th></tr></thead><tbody>
      <?php foreach ($perWeek as $row): ?>
        <tr><td><?= officeflow_e($row['bucket']) ?></td><td><?= (int) $row['total'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Office resources at a glance</h2><p>Counts across every module</p></div></header>
  <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Area</th><th>Total</th><th>Detail</th></tr></thead><tbody>
    <tr><td>Assets</td><td><?= (int) $overview['assets_total'] ?></td><td><?= officeflow_e(implode(', ', array_map(static fn ($k, $v): string => ucfirst($k) . ' ' . $v, array_keys($overview['assets']), array_values($overview['assets']))) ?: '—') ?></td></tr>
    <tr><td>Rooms &amp; facilities</td><td><?= (int) $overview['rooms_total'] ?></td><td><?= officeflow_e(implode(', ', array_map(static fn ($k, $v): string => ucfirst(str_replace('_', ' ', $k)) . ' ' . $v, array_keys($overview['rooms']), array_values($overview['rooms']))) ?: '—') ?></td></tr>
    <tr><td>Vendors</td><td><?= (int) $overview['vendors_total'] ?></td><td><?= (int) $overview['vendors_active'] ?> active</td></tr>
    <tr><td>Documents &amp; SOPs</td><td><?= (int) $overview['documents_total'] ?></td><td><?= (int) $overview['documents_published'] ?> published</td></tr>
    <tr><td>Team</td><td><?= (int) $overview['team_total'] ?></td><td><?= (int) $overview['team_active'] ?> active</td></tr>
    <tr><td>Activity entries</td><td><?= (int) $overview['activity_total'] ?></td><td><a class="text-button" href="/activity.php">Open activity log →</a></td></tr>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
