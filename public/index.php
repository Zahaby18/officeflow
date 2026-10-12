<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = officeflow_db();
    $isInstalled = InstallService::isInstalled($pdo);
} catch (Throwable $exception) {
    error_log('OfficeFlow home preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    $configExists = is_file(dirname(__DIR__) . '/config/database.php');
    $title = $configExists ? 'Database setup needs attention' : 'Connect the OfficeFlow database';
    $detail = $configExists
        ? 'Check the database connection and confirm that database/migrations/001_initial_schema.sql has been imported.'
        : 'Copy config/database.example.php to config/database.php and enter the database connection values for this installation.';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . officeflow_e($title) . '</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><p class="eyebrow">OFFICEFLOW SETUP</p><h1>' . officeflow_e($title) . '</h1><p>' . officeflow_e($detail) . '</p><p><a href="/install.php">Open setup page</a></p></section></main></body></html>';
    exit;
}

if (!$isInstalled) {
    officeflow_redirect('/install.php');
}
$user = officeflow_require_user($pdo);
$officeName = officeflow_setting($pdo, 'office_name', 'Office workspace');
$summary = RequestService::summary($pdo);
$openCount = RequestService::countOpen($pdo);
$latestRequests = RequestService::list($pdo, 5);
$currentPage = 'Home';
$firstName = preg_split('/\s+/', trim($user['name']))[0] ?? $user['name'];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
require dirname(__DIR__) . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow"><?= officeflow_e(strtoupper(date('l · j F Y'))) ?></p><h1><?= officeflow_e($greeting) ?>, <?= officeflow_e($firstName) ?></h1><p>Here’s what needs your attention around the office.</p></div>
  <div class="app-heading-actions"><a class="button button-primary" href="/requests.php#new-request"><svg class="icon"><use href="#i-inbox"/></svg><span>New request</span></a></div>
</section>
<section class="welcome-panel" aria-label="Office overview">
  <div><p class="welcome-kicker">OFFICE OPERATIONS</p><h2>One clear view of today’s work.</h2><p>Requests below are connected to this office’s database.</p></div>
  <div class="readiness"><span>Requests needing attention</span><strong><?= (int) $openCount ?></strong><span>Across all active statuses</span></div>
  <span class="orbit orbit-one" aria-hidden="true"></span><span class="orbit orbit-two" aria-hidden="true"></span>
</section>
<section class="app-metrics" aria-label="Request summary">
  <article class="app-metric"><span class="app-metric-label">Open requests</span><strong class="app-metric-value"><?= (int) $openCount ?></strong><div class="app-metric-note">New, in progress, or waiting</div></article>
  <article class="app-metric"><span class="app-metric-label">New</span><strong class="app-metric-value"><?= (int) $summary['new'] ?></strong><div class="app-metric-note">Not reviewed yet</div></article>
  <article class="app-metric"><span class="app-metric-label">In progress</span><strong class="app-metric-value"><?= (int) $summary['in_progress'] ?></strong><div class="app-metric-note">Being handled by the team</div></article>
  <article class="app-metric"><span class="app-metric-label">Waiting</span><strong class="app-metric-value"><?= (int) $summary['waiting'] ?></strong><div class="app-metric-note">Needs a follow-up</div></article>
</section>
<section class="app-surface">
  <header class="app-surface-head"><div><h2>Latest requests</h2><p>Live records from the OfficeFlow database</p></div><a class="text-button" href="/requests.php">View all →</a></header>
  <?php if ($latestRequests === []): ?>
    <div class="app-empty"><strong>No requests yet</strong>Create the first request to start tracking office work.<br><a class="text-button" href="/requests.php#new-request">Create a request →</a></div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Request</th><th>Category</th><th>Priority</th><th>Status</th><th>Requested by</th></tr></thead><tbody>
    <?php foreach ($latestRequests as $request): ?>
      <tr><td><span class="app-request-title"><strong><?= officeflow_e($request['title']) ?></strong><small class="app-code"><?= officeflow_e($request['request_code']) ?></small></span></td><td><?= officeflow_e($request['category']) ?></td><td><span class="app-priority app-priority-<?= officeflow_e($request['priority']) ?>"><?= officeflow_e($request['priority']) ?></span></td><td><span class="status status-<?= officeflow_e($request['status'] === 'new' ? 'review' : ($request['status'] === 'in_progress' ? 'progress' : ($request['status'] === 'waiting' ? 'review' : 'progress'))) ?>"><?= officeflow_e(str_replace('_', ' ', $request['status'])) ?></span></td><td><?= officeflow_e($request['requester_name']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/app/views/app-footer.php'; ?>
