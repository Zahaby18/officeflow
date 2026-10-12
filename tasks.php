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
    error_log('OfficeFlow tasks preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$isAdmin = $user['role'] === 'admin';
$filter = (string) ($_GET['status'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/tasks.php');
    }

    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            TaskService::create($pdo, $user['id'], $_POST);
            officeflow_flash('success', 'Task created.');
        } elseif ($action === 'status') {
            TaskService::setStatus($pdo, (int) ($_POST['task_id'] ?? 0), (string) ($_POST['status'] ?? ''), $user['id']);
            officeflow_flash('success', 'Task updated.');
        } elseif ($action === 'delete') {
            if (!$isAdmin) {
                throw new InvalidArgumentException('Only an administrator can delete tasks.');
            }
            TaskService::delete($pdo, (int) ($_POST['task_id'] ?? 0), $user['id']);
            officeflow_flash('success', 'Task deleted.');
        } else {
            throw new InvalidArgumentException('Choose a valid action.');
        }
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow task action failed: ' . $exception->getMessage());
        officeflow_flash('error', 'The task could not be saved.');
    }

    officeflow_redirect('/tasks.php');
}

$summary = TaskService::summary($pdo);
$tasks = TaskService::list($pdo, $filter);
$assignees = TaskService::assignableUsers($pdo);
$currentPage = 'Tasks & Checklists';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">OFFICE WORKSPACE</p><h1>Tasks &amp; checklists</h1><p>Keep recurring routines and one-off office tasks clear for the team.</p></div>
  <div class="app-heading-actions">
    <a class="button button-secondary<?= $filter === '' ? ' is-active-filter' : '' ?>" href="/tasks.php">All</a>
    <a class="button button-secondary" href="/tasks.php?status=open">Open</a>
    <a class="button button-secondary" href="/tasks.php?status=completed">Completed</a>
  </div>
</section>

<section class="app-metrics" aria-label="Task summary">
  <article class="app-metric"><span class="app-metric-label">Open</span><strong class="app-metric-value"><?= (int) $summary['open'] ?></strong><div class="app-metric-note">Not started</div></article>
  <article class="app-metric"><span class="app-metric-label">In progress</span><strong class="app-metric-value"><?= (int) $summary['in_progress'] ?></strong><div class="app-metric-note">Being worked on</div></article>
  <article class="app-metric"><span class="app-metric-label">Due today</span><strong class="app-metric-value"><?= TaskService::dueToday($pdo) ?></strong><div class="app-metric-note">Deadline today</div></article>
  <article class="app-metric"><span class="app-metric-label">Overdue</span><strong class="app-metric-value"><?= TaskService::overdue($pdo) ?></strong><div class="app-metric-note">Past the deadline</div></article>
</section>

<section class="app-surface">
  <header class="app-surface-head"><div><h2>Add a task</h2><p>Assign it to a teammate and set a recurrence if it repeats.</p></div></header>
  <form method="post" action="/tasks.php" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <div class="app-form-grid">
      <div class="app-field"><label for="task-title">Task title</label><input id="task-title" name="title" required minlength="4" maxlength="180" placeholder="e.g. Weekly safety walk-through"></div>
      <div class="app-field"><label for="task-category">Category</label><select id="task-category" name="category"><?php foreach (TaskService::CATEGORIES as $category): ?><option value="<?= officeflow_e($category) ?>"<?= $category === 'General' ? ' selected' : '' ?>><?= officeflow_e($category) ?></option><?php endforeach; ?></select></div>
      <div class="app-field"><label for="task-recurrence">Repeats</label><select id="task-recurrence" name="recurrence"><?php foreach (TaskService::RECURRENCES as $recurrence): ?><option value="<?= officeflow_e($recurrence) ?>"><?= officeflow_e(ucfirst($recurrence)) ?></option><?php endforeach; ?></select></div>
      <div class="app-field"><label for="task-due">Due <span>Optional</span></label><input id="task-due" name="due_at" type="datetime-local"></div>
      <div class="app-field"><label for="task-assignee">Assign to <span>Optional</span></label><select id="task-assignee" name="assigned_to"><option value="">Unassigned</option><?php foreach ($assignees as $id => $name): ?><option value="<?= (int) $id ?>"><?= officeflow_e($name) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="app-field"><label for="task-details">Details <span>Optional</span></label><textarea id="task-details" name="details" rows="2" maxlength="5000" placeholder="Add any context the team needs"></textarea></div>
    <div class="app-form-actions"><button class="button button-primary" type="submit"><svg class="icon"><use href="#i-plus"/></svg><span>Add task</span></button></div>
  </form>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Task list</h2><p><?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?><?= $filter !== '' ? ' filtered by ' . officeflow_e($filter) : '' ?></p></div></header>
  <?php if ($tasks === []): ?>
    <div class="app-empty"><strong>No tasks yet</strong>Add the first task using the form above.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table">
      <thead><tr><th>Task</th><th>Category</th><th>Assignee</th><th>Due</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $task): ?>
        <?php
          $statusClass = $task['status'] === 'completed' ? 'complete' : ($task['status'] === 'open' ? 'review' : ($task['status'] === 'in_progress' ? 'progress' : 'overdue'));
          $overdue = $task['due_at'] !== null && $task['status'] !== 'completed' && strtotime((string) $task['due_at']) < time();
        ?>
        <tr>
          <td><span class="app-request-title"><strong><?= officeflow_e($task['title']) ?></strong><small><?= officeflow_e(ucfirst($task['recurrence'] === 'none' ? 'one-time' : $task['recurrence'] . ' · recurring')) ?></small></span></td>
          <td><?= officeflow_e($task['category']) ?></td>
          <td><?= officeflow_e($task['assignee_name'] ?? 'Unassigned') ?></td>
          <td><?= $task['due_at'] === null ? '—' : officeflow_e(date('M j, Y H:i', strtotime((string) $task['due_at']))) ?><?= $overdue ? ' <span class="app-priority app-priority-high">overdue</span>' : '' ?></td>
          <td><span class="status status-<?= officeflow_e($statusClass) ?>"><?= officeflow_e(str_replace('_', ' ', $task['status'])) ?></span></td>
          <td class="module-actions">
            <form method="post" action="/tasks.php" class="status-update-form">
              <?= officeflow_csrf_input() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
              <label class="visually-hidden" for="task-status-<?= (int) $task['id'] ?>">Status for <?= officeflow_e($task['title']) ?></label>
              <select class="app-status-select" id="task-status-<?= (int) $task['id'] ?>" name="status"><?php foreach (TaskService::STATUSES as $status): ?><option value="<?= officeflow_e($status) ?>"<?= $status === $task['status'] ? ' selected' : '' ?>><?= officeflow_e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select>
              <button class="text-button" type="submit">Save</button>
            </form>
            <?php if ($isAdmin): ?>
              <form method="post" action="/tasks.php" onsubmit="return confirm('Delete this task?');">
                <?= officeflow_csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                <button class="text-button text-danger" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
