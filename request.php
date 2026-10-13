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
    error_log('OfficeFlow request detail preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$isAdmin = $user['role'] === 'admin';
$requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/request.php?id=' . $requestId);
    }
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'comment') {
            RequestService::addComment($pdo, $requestId, $user['id'], (string) ($_POST['body'] ?? ''));
            officeflow_flash('success', 'Comment added.');
        } elseif ($action === 'status') {
            RequestService::updateStatus($pdo, $requestId, (string) ($_POST['status'] ?? ''), $user['role']);
            officeflow_flash('success', 'Request status updated.');
        } else {
            throw new InvalidArgumentException('Choose a valid action.');
        }
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow request detail action failed: ' . $exception->getMessage());
        officeflow_flash('error', 'The change could not be saved.');
    }
    officeflow_redirect('/request.php?id=' . $requestId);
}

$request = RequestService::find($pdo, $requestId);
$currentPage = 'Requests';

if ($request === null) {
    http_response_code(404);
    require __DIR__ . '/app/views/app-header.php';
    echo '<section class="app-surface page-placeholder"><div><span class="placeholder-icon"><svg class="icon"><use href="#i-inbox"/></svg></span><h2>Request not found</h2><p>That request does not exist or was removed.</p><a class="button button-secondary" href="/requests.php">Back to requests</a></div></section>';
    require __DIR__ . '/app/views/app-footer.php';
    exit;
}

$comments = RequestService::comments($pdo, $requestId);
$statusClass = $request['status'] === 'new' || $request['status'] === 'waiting'
    ? 'review'
    : ($request['status'] === 'in_progress' ? 'progress' : ($request['status'] === 'completed' ? 'complete' : 'overdue'));
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div>
    <p class="eyebrow">REQUEST · <?= officeflow_e($request['request_code']) ?></p>
    <h1><?= officeflow_e($request['title']) ?></h1>
    <p>Raised by <?= officeflow_e($request['requester_name']) ?> on <?= officeflow_e(date('M j, Y H:i', strtotime((string) $request['created_at']))) ?></p>
  </div>
  <div class="app-heading-actions"><a class="button button-secondary" href="/requests.php">Back to requests</a></div>
</section>

<section class="app-surface">
  <header class="app-surface-head"><div><h2>Details</h2><p>The full description the requester submitted</p></div><span class="status status-<?= officeflow_e($statusClass) ?>"><?= officeflow_e(str_replace('_', ' ', $request['status'])) ?></span></header>
  <div class="detail-body">
    <p class="detail-text"><?= $request['details'] === null || trim((string) $request['details']) === '' ? 'No extra details were provided.' : nl2br(officeflow_e($request['details'])) ?></p>
  </div>
  <div class="app-table-wrap"><table class="app-table"><tbody>
    <tr><th>Reference</th><td><?= officeflow_e($request['request_code']) ?></td></tr>
    <tr><th>Category</th><td><?= officeflow_e($request['category']) ?></td></tr>
    <tr><th>Priority</th><td><span class="app-priority app-priority-<?= officeflow_e($request['priority']) ?>"><?= officeflow_e($request['priority']) ?></span></td></tr>
    <tr><th>Requested by</th><td><?= officeflow_e($request['requester_name']) ?> · <?= officeflow_e($request['requester_email']) ?></td></tr>
    <tr><th>Assigned to</th><td><?= officeflow_e($request['assignee_name'] ?? 'Not assigned yet') ?></td></tr>
    <tr><th>Due</th><td><?= $request['due_at'] === null ? 'No due date' : officeflow_e(date('M j, Y H:i', strtotime((string) $request['due_at']))) ?></td></tr>
    <tr><th>Completed</th><td><?= $request['completed_at'] === null ? 'Not completed' : officeflow_e(date('M j, Y H:i', strtotime((string) $request['completed_at']))) ?></td></tr>
    <tr><th>Last change</th><td><?= officeflow_e(date('M j, Y H:i', strtotime((string) $request['updated_at']))) ?></td></tr>
  </tbody></table></div>
</section>

<?php if ($isAdmin): ?>
<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Update status</h2><p>Only an administrator can move a request through its states.</p></div></header>
  <form method="post" action="/request.php?id=<?= (int) $request['id'] ?>" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="status">
    <div class="app-field"><label for="detail-status">Status</label>
      <select id="detail-status" name="status">
        <?php foreach (['new', 'in_progress', 'waiting', 'completed', 'cancelled'] as $option): ?>
          <option value="<?= officeflow_e($option) ?>"<?= $option === $request['status'] ? ' selected' : '' ?>><?= officeflow_e(ucwords(str_replace('_', ' ', $option))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="app-form-actions"><button class="button button-primary" type="submit">Save status</button></div>
  </form>
</section>
<?php endif; ?>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Conversation</h2><p><?= count($comments) ?> comment<?= count($comments) === 1 ? '' : 's' ?> on this request</p></div></header>
  <div class="comment-list">
    <?php if ($comments === []): ?>
      <div class="app-empty"><strong>No comments yet</strong>Use the box below to add context, a decision, or a follow-up.</div>
    <?php else: ?>
      <?php foreach ($comments as $comment): ?>
        <article class="comment">
          <div class="comment-head"><span class="mini-avatar"><?= officeflow_e(strtoupper(mb_substr((string) ($comment['author_name'] ?? '?'), 0, 1, 'UTF-8'))) ?></span><strong><?= officeflow_e($comment['author_name'] ?? 'Removed user') ?></strong><span class="comment-role"><?= officeflow_e(ucfirst((string) ($comment['author_role'] ?? 'staff'))) ?></span><time><?= officeflow_e(date('M j, Y H:i', strtotime((string) $comment['created_at']))) ?></time></div>
          <p class="comment-body"><?= nl2br(officeflow_e($comment['body'])) ?></p>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <form method="post" action="/request.php?id=<?= (int) $request['id'] ?>" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="comment">
    <div class="app-field"><label for="comment-body">Add a comment</label><textarea id="comment-body" name="body" rows="3" maxlength="2000" required placeholder="Add an update the office team can read"></textarea></div>
    <div class="app-form-actions"><button class="button button-primary" type="submit">Post comment</button></div>
  </form>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
