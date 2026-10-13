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
    error_log('OfficeFlow requests preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database config and initial schema migration before opening Requests.</p><p><a href="/install.php">Open setup instructions</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = officeflow_setting($pdo, 'office_name', 'Office workspace');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/requests.php');
    }

    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            RequestService::create($pdo, $user['id'], $_POST);
            officeflow_flash('success', 'Request created. The office team can now track it.');
        } elseif ($action === 'status') {
            RequestService::updateStatus(
                $pdo,
                filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
                (string) ($_POST['status'] ?? ''),
                $user['role']
            );
            officeflow_flash('success', 'Request status updated.');
        } else {
            throw new InvalidArgumentException('Choose a valid request action.');
        }
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow request action failed: ' . $exception->getMessage());
        officeflow_flash('error', 'The request could not be saved. Check the site logs and try again.');
    }

    officeflow_redirect('/requests.php');
}

$requests = RequestService::list($pdo, 200);
$currentPage = 'Requests';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">OFFICE WORKSPACE</p><h1>Requests</h1><p>Capture office needs and keep every request moving to the right person.</p></div>
  <div class="app-heading-actions"><a class="button button-secondary" href="/">Back to home</a></div>
</section>
<section class="app-surface" id="new-request">
  <header class="app-surface-head"><div><h2>Create a request</h2><p>New requests start in the “new” status and are tied to your signed-in account.</p></div></header>
  <form method="post" action="/requests.php#new-request" class="request-create-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <div class="app-form-grid">
      <div class="app-field"><label for="request-title">Request title</label><input id="request-title" name="title" required minlength="4" maxlength="180" placeholder="e.g. Replace the meeting room light"></div>
      <div class="app-field"><label for="request-category">Category</label><select id="request-category" name="category" required><option value="">Choose a category</option><option>Facilities</option><option>Office supplies</option><option>Equipment</option><option>Other</option></select></div>
      <div class="app-field"><label for="request-priority">Priority</label><select id="request-priority" name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
      <div class="app-field"><label for="request-due">Due date <span>Optional</span></label><input id="request-due" name="due_at" type="date"></div>
    </div>
    <div class="app-field"><label for="request-details">Details <span>Optional</span></label><textarea id="request-details" name="details" maxlength="5000" rows="3" placeholder="Add the location, quantity, or other useful context"></textarea></div>
    <div class="app-form-actions"><button class="button button-primary" type="submit"><svg class="icon"><use href="#i-plus"/></svg><span>Create request</span></button></div>
  </form>
</section>
<section class="app-surface app-requests-list">
  <header class="app-surface-head"><div><h2>All requests</h2><p><?= count($requests) ?> most recent records · Staff can submit requests; Admin can update status.</p></div></header>
  <?php if ($requests === []): ?>
    <div class="app-empty"><strong>No requests yet</strong>Use the form above to create the first one.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Request</th><th>Category</th><th>Priority</th><th>Status</th><th>Requested by</th><?php if ($user['role'] === 'admin'): ?><th>Update</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($requests as $request): ?>
      <?php $statusClass = $request['status'] === 'new' || $request['status'] === 'waiting' ? 'review' : ($request['status'] === 'in_progress' ? 'progress' : ($request['status'] === 'completed' ? 'complete' : 'overdue')); ?>
      <tr>
        <td><span class="app-request-title"><a class="row-link" href="/request.php?id=<?= (int) $request['id'] ?>"><strong><?= officeflow_e($request['title']) ?></strong></a><small class="app-code"><?= officeflow_e($request['request_code']) ?> · <?= officeflow_e(date('M j, Y', strtotime($request['created_at']))) ?></small><?php if (trim((string) ($request['details'] ?? '')) !== ''): ?><small class="row-detail"><?= officeflow_e(mb_strimwidth(trim((string) $request['details']), 0, 120, '…', 'UTF-8')) ?></small><?php endif; ?></span></td>
        <td><?= officeflow_e($request['category']) ?></td>
        <td><span class="app-priority app-priority-<?= officeflow_e($request['priority']) ?>"><?= officeflow_e($request['priority']) ?></span></td>
        <td><span class="status status-<?= officeflow_e($statusClass) ?>"><?= officeflow_e(str_replace('_', ' ', $request['status'])) ?></span></td>
        <td><?= officeflow_e($request['requester_name']) ?></td>
        <?php if ($user['role'] === 'admin'): ?>
          <td><form method="post" action="/requests.php" class="status-update-form"><?= officeflow_csrf_input() ?><input type="hidden" name="action" value="status"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><label class="visually-hidden" for="status-<?= (int) $request['id'] ?>">Status for <?= officeflow_e($request['request_code']) ?></label><select class="app-status-select" id="status-<?= (int) $request['id'] ?>" name="status"><?php foreach (['new', 'in_progress', 'waiting', 'completed', 'cancelled'] as $statusOption): ?><option value="<?= officeflow_e($statusOption) ?>" <?= $statusOption === $request['status'] ? 'selected' : '' ?>><?= officeflow_e(ucwords(str_replace('_', ' ', $statusOption))) ?></option><?php endforeach; ?></select><button class="text-button" type="submit">Save</button></form></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
