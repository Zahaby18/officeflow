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
    error_log('OfficeFlow team preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$isAdmin = $user['role'] === 'admin';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/team.php');
    }

    try {
        if (!$isAdmin) {
            throw new InvalidArgumentException('Only an administrator can manage team accounts.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $targetId = (int) ($_POST['user_id'] ?? 0);

        if ($action === 'create') {
            TeamService::create($pdo, $user['id'], $_POST);
            officeflow_flash('success', 'Team account created. Share the email and password with that person over a private channel.');
        } elseif ($action === 'role') {
            TeamService::setRole($pdo, $targetId, (string) ($_POST['role'] ?? ''), $user['id']);
            officeflow_flash('success', 'Role updated.');
        } elseif ($action === 'toggle') {
            $target = TeamService::find($pdo, $targetId);
            if ($target === null) {
                throw new RuntimeException('That account no longer exists.');
            }
            TeamService::setActive($pdo, $targetId, (int) $target['is_active'] !== 1, $user['id']);
            officeflow_flash('success', 'Account access updated.');
        } elseif ($action === 'password') {
            TeamService::resetPassword($pdo, $targetId, (string) ($_POST['password'] ?? ''), $user['id']);
            officeflow_flash('success', 'Password reset. Share the new password over a private channel.');
        } else {
            throw new InvalidArgumentException('Choose a valid action.');
        }
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow team action failed: ' . $exception->getMessage());
        officeflow_flash('error', 'The team change could not be saved.');
    }

    officeflow_redirect('/team.php');
}

$members = TeamService::list($pdo);
$currentPage = 'Team';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">MANAGEMENT</p><h1>Team</h1><p>Manage office members and their access to OfficeFlow.</p></div>
</section>

<?php if (!$isAdmin): ?>
  <section class="app-surface"><div class="app-empty"><strong>Administrator only</strong>Only an administrator can add or change team accounts. You can still see who is on the team below.</div></section>
<?php else: ?>
<section class="app-surface">
  <header class="app-surface-head"><div><h2>Add a team member</h2><p>Create the account, then share the email and password privately. They can sign in from any device on this URL.</p></div></header>
  <form method="post" action="/team.php" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <div class="app-form-grid">
      <div class="app-field"><label for="team-name">Full name</label><input id="team-name" name="name" required minlength="2" maxlength="120" placeholder="e.g. Rafi Pratama"></div>
      <div class="app-field"><label for="team-email">Work email</label><input id="team-email" name="email" type="email" required maxlength="190" placeholder="name@company.com"></div>
      <div class="app-field"><label for="team-role">Role</label><select id="team-role" name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
      <div class="app-field"><label for="team-password">Temporary password</label><input id="team-password" name="password" type="password" required minlength="12" maxlength="200" autocomplete="new-password" aria-describedby="teamPasswordHelp"><small id="teamPasswordHelp" class="auth-note">At least 12 characters. Stored as a secure hash.</small></div>
    </div>
    <div class="app-form-actions"><button class="button button-primary" type="submit">Create account</button></div>
  </form>
</section>
<?php endif; ?>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>What each role can do</h2><p>OfficeFlow has two roles, and the difference is only about editing</p></div></header>
  <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Role</th><th>Can do</th><th>Cannot do</th></tr></thead><tbody>
    <tr><th>Staff</th><td>Create requests, add tasks, add office records, move tasks along, comment on requests</td><td>Edit or delete records, manage team accounts, change office settings</td></tr>
    <tr><th>Admin</th><td>Everything staff can do, plus edit and delete records, update request status, add and disable accounts, reset passwords, change settings</td><td>Remove the last active administrator, or lock themselves out</td></tr>
  </tbody></table></div>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Team members</h2><p><?= count($members) ?> account<?= count($members) === 1 ? '' : 's' ?></p></div></header>
  <div class="app-table-wrap"><table class="app-table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Access</th><th>Last sign-in</th><?php if ($isAdmin): ?><th>Manage</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($members as $member): ?>
      <tr>
        <td><?= officeflow_e($member['name']) ?><?= (int) $member['id'] === (int) $user['id'] ? ' <span class="watch-tag tag-low">you</span>' : '' ?></td>
        <td><?= officeflow_e($member['email']) ?></td>
        <td><span class="status status-<?= $member['role'] === 'admin' ? 'progress' : 'review' ?>"><?= officeflow_e(ucfirst($member['role'])) ?></span></td>
        <td><span class="status status-<?= (int) $member['is_active'] === 1 ? 'complete' : 'overdue' ?>"><?= (int) $member['is_active'] === 1 ? 'Active' : 'Disabled' ?></span></td>
        <td><?= $member['last_login_at'] === null ? 'Never' : officeflow_e(date('M j, Y H:i', strtotime((string) $member['last_login_at']))) ?></td>
        <?php if ($isAdmin): ?>
          <td class="module-actions">
            <form method="post" action="/team.php" class="inline-form">
              <?= officeflow_csrf_input() ?>
              <input type="hidden" name="action" value="role">
              <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
              <label class="visually-hidden" for="role-<?= (int) $member['id'] ?>">Role for <?= officeflow_e($member['name']) ?></label>
              <select class="app-status-select" id="role-<?= (int) $member['id'] ?>" name="role"><option value="staff"<?= $member['role'] === 'staff' ? ' selected' : '' ?>>Staff</option><option value="admin"<?= $member['role'] === 'admin' ? ' selected' : '' ?>>Admin</option></select>
              <button class="text-button" type="submit">Save role</button>
            </form>
            <form method="post" action="/team.php" class="inline-form">
              <?= officeflow_csrf_input() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
              <button class="text-button" type="submit"><?= (int) $member['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="post" action="/team.php" class="inline-form">
              <?= officeflow_csrf_input() ?>
              <input type="hidden" name="action" value="password">
              <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
              <label class="visually-hidden" for="pw-<?= (int) $member['id'] ?>">New password for <?= officeflow_e($member['name']) ?></label>
              <input id="pw-<?= (int) $member['id'] ?>" name="password" type="password" minlength="12" maxlength="200" placeholder="New password" autocomplete="new-password">
              <button class="text-button" type="submit">Reset</button>
            </form>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
