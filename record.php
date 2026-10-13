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
    error_log('OfficeFlow record detail preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$isAdmin = $user['role'] === 'admin';
$slug = (string) ($_GET['m'] ?? '');
$module = Modules::find($slug);
$recordId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$listUrl = '/module.php?m=' . urlencode($slug);

if ($module === null) {
    http_response_code(404);
    $currentPage = 'Not found';
    require __DIR__ . '/app/views/app-header.php';
    echo '<section class="app-surface page-placeholder"><div><span class="placeholder-icon"><svg class="icon"><use href="#i-file"/></svg></span><h2>Module not found</h2><p>That office module does not exist.</p><a class="button button-secondary" href="/">Back to home</a></div></section>';
    require __DIR__ . '/app/views/app-footer.php';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect($listUrl);
    }
    try {
        if (!$isAdmin) {
            throw new InvalidArgumentException('Only an administrator can delete records.');
        }
        if (($_POST['action'] ?? '') !== 'delete') {
            throw new InvalidArgumentException('Choose a valid action.');
        }
        CrudService::delete($pdo, $module, $recordId, $user['id']);
        officeflow_flash('success', ucfirst($module['singular']) . ' deleted.');
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow record delete failed: ' . $exception->getMessage());
        officeflow_flash('error', $exception->getMessage() ?: 'The record could not be deleted.');
    }
    officeflow_redirect($listUrl);
}

$record = CrudService::find($pdo, $module, $recordId);
$currentPage = $module['label'];

if ($record === null) {
    http_response_code(404);
    require __DIR__ . '/app/views/app-header.php';
    echo '<section class="app-surface page-placeholder"><div><span class="placeholder-icon"><svg class="icon"><use href="#' . officeflow_e($module['icon']) . '"/></svg></span><h2>Record not found</h2><p>It may have been deleted.</p><a class="button button-secondary" href="' . officeflow_e($listUrl) . '">Back to ' . officeflow_e($module['label']) . '</a></div></section>';
    require __DIR__ . '/app/views/app-footer.php';
    exit;
}

$title = (string) ($record[$module['title_field']] ?? ('#' . $recordId));
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div>
    <p class="eyebrow"><?= officeflow_e(strtoupper($module['label'])) ?> · RECORD #<?= (int) $record['id'] ?></p>
    <h1><?= officeflow_e($title) ?></h1>
    <p><?= officeflow_e($module['description']) ?></p>
  </div>
  <div class="app-heading-actions">
    <a class="button button-secondary" href="<?= officeflow_e($listUrl) ?>">Back to list</a>
    <?php if ($isAdmin): ?><a class="button button-primary" href="<?= officeflow_e($listUrl) ?>&amp;edit=<?= (int) $record['id'] ?>">Edit this record</a><?php endif; ?>
  </div>
</section>

<section class="app-surface">
  <header class="app-surface-head"><div><h2>All fields</h2><p>Everything stored for this record, including the notes that the list only summarises</p></div></header>
  <div class="app-table-wrap"><table class="app-table"><tbody>
    <?php foreach ($module['fields'] as $column => $field): ?>
      <?php
        $raw = $record[$column] ?? null;
        $isLong = in_array($field['type'], ['textarea'], true);
        $value = officeflow_display_value($module, $column, $raw);
      ?>
      <tr>
        <th><?= officeflow_e($field['label']) ?></th>
        <td>
          <?php if ($isLong): ?>
            <?php if (trim((string) $raw) === ''): ?><span class="detail-muted">Not filled in</span>
            <?php else: ?><span class="detail-text"><?= nl2br(officeflow_e((string) $raw)) ?></span><?php endif; ?>
          <?php elseif ($field['type'] === 'url' && trim((string) $raw) !== ''): ?>
            <a class="text-button" href="<?= officeflow_e((string) $raw) ?>" rel="noopener noreferrer" target="_blank"><?= officeflow_e((string) $raw) ?></a>
          <?php else: ?>
            <?= $value === '—' ? '<span class="detail-muted">Not filled in</span>' : officeflow_e($value) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <tr><th>Added by</th><td><?= officeflow_e((string) ($record['created_by'] ?? '—')) ?></td></tr>
    <tr><th>Created</th><td><?= officeflow_e(date('M j, Y H:i', strtotime((string) $record['created_at']))) ?></td></tr>
    <tr><th>Last changed</th><td><?= officeflow_e(date('M j, Y H:i', strtotime((string) $record['updated_at']))) ?></td></tr>
  </tbody></table></div>
</section>

<?php if ($isAdmin): ?>
<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Delete</h2><p>This cannot be undone. Records still referenced by other office data are protected.</p></div></header>
  <form method="post" action="/record.php?m=<?= officeflow_e($slug) ?>&amp;id=<?= (int) $record['id'] ?>" class="module-form" onsubmit="return confirm('Delete this record?');">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="delete">
    <div class="app-form-actions"><button class="button button-danger" type="submit">Delete record</button></div>
  </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
