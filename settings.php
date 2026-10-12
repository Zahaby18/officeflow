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
    error_log('OfficeFlow settings preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$isAdmin = $user['role'] === 'admin';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/settings.php');
    }
    try {
        if (!$isAdmin) {
            throw new InvalidArgumentException('Only an administrator can change office settings.');
        }
        SettingsService::save($pdo, $user['id'], $_POST);
        officeflow_flash('success', 'Office settings saved.');
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow settings action failed: ' . $exception->getMessage());
        officeflow_flash('error', 'The settings could not be saved.');
    }
    officeflow_redirect('/settings.php');
}

$settings = SettingsService::all($pdo);
$officeName = $settings['office_name'] !== '' ? $settings['office_name'] : 'Office workspace';
$currentPage = 'Settings';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">MANAGEMENT</p><h1>Settings</h1><p>Set up your workspace, categories, roles, and preferences.</p></div>
</section>

<?php if (!$isAdmin): ?>
  <section class="app-surface"><div class="app-empty"><strong>Administrator only</strong>Only an administrator can change office settings. Ask an administrator if something needs updating.</div></section>
<?php else: ?>
<section class="app-surface">
  <header class="app-surface-head"><div><h2>Office profile</h2><p>These values appear across the workspace and in the sidebar.</p></div></header>
  <form method="post" action="/settings.php" class="module-form">
    <?= officeflow_csrf_input() ?>
    <div class="app-form-grid">
      <div class="app-field"><label for="office-name">Office name</label><input id="office-name" name="office_name" required maxlength="160" value="<?= officeflow_e($settings['office_name']) ?>"></div>
      <div class="app-field"><label for="office-location">Location <span>Optional</span></label><input id="office-location" name="office_location" maxlength="160" value="<?= officeflow_e($settings['office_location']) ?>" placeholder="e.g. Jakarta"></div>
      <div class="app-field"><label for="office-timezone">Timezone</label><select id="office-timezone" name="office_timezone"><option value="">Not set</option><?php foreach (SettingsService::commonTimezones() as $zone): ?><option value="<?= officeflow_e($zone) ?>"<?= $settings['office_timezone'] === $zone ? ' selected' : '' ?>><?= officeflow_e($zone) ?></option><?php endforeach; ?></select></div>
      <div class="app-field"><label for="support-email">Support email <span>Optional</span></label><input id="support-email" name="support_email" type="email" maxlength="190" value="<?= officeflow_e($settings['support_email']) ?>" placeholder="office@company.com"></div>
    </div>
    <div class="app-form-actions"><button class="button button-primary" type="submit">Save settings</button></div>
  </form>
</section>
<?php endif; ?>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Workspace information</h2><p>Read-only details about this installation</p></div></header>
  <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Item</th><th>Value</th></tr></thead><tbody>
    <tr><td>Signed in as</td><td><?= officeflow_e($user['name']) ?> (<?= officeflow_e($user['email']) ?>, <?= officeflow_e(ucfirst($user['role'])) ?>)</td></tr>
    <tr><td>Installation model</td><td>Self-hosted, one office per installation</td></tr>
    <tr><td>Active accounts</td><td><?= TeamService::countActive($pdo) ?></td></tr>
    <tr><td>PHP version</td><td><?= officeflow_e(PHP_VERSION) ?></td></tr>
    <tr><td>Modules enabled</td><td>Requests, Tasks, Announcements, Assets, Inventory, Maintenance, Rooms, Vendors, Documents, Team, Reports, Activity, Settings</td></tr>
    <tr><td>Not included yet</td><td>Email notifications, backup and restore tooling, and automatic updates</td></tr>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
