<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/app/bootstrap.php';

/** Render one input for a module field. */
function officeflow_field_input(array $field, string $name, mixed $value, array $referenceOptions = []): string
{
    $id = 'field-' . preg_replace('/[^a-z0-9_]/i', '', $name);
    $required = ($field['required'] ?? false) === true ? ' required' : '';
    $label = officeflow_e($field['label']);

    $control = match ($field['type']) {
        'textarea' => sprintf(
            '<textarea id="%s" name="%s" rows="3" maxlength="%d"%s>%s</textarea>',
            $id,
            officeflow_e($name),
            (int) ($field['max'] ?? 5000),
            $required,
            officeflow_e($value)
        ),
        'select' => (static function () use ($field, $id, $name, $value, $required): string {
            $options = '';
            if (($field['required'] ?? false) !== true) {
                $options .= '<option value="">None</option>';
            }
            foreach ($field['options'] as $option) {
                $options .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    officeflow_e($option),
                    (string) $value === (string) $option ? ' selected' : '',
                    officeflow_e(ucwords(str_replace('_', ' ', $option)))
                );
            }
            return sprintf('<select id="%s" name="%s"%s>%s</select>', $id, officeflow_e($name), $required, $options);
        })(),
        'reference' => (static function () use ($field, $id, $name, $value, $required, $referenceOptions): string {
            $html = '<option value="">None</option>';
            foreach ($referenceOptions[$name] ?? [] as $optionId => $optionLabel) {
                $html .= sprintf(
                    '<option value="%d"%s>%s</option>',
                    $optionId,
                    (int) $value === (int) $optionId ? ' selected' : '',
                    officeflow_e($optionLabel)
                );
            }
            return sprintf('<select id="%s" name="%s"%s>%s</select>', $id, officeflow_e($name), $required, $html);
        })(),
        'number' => sprintf(
            '<input id="%s" name="%s" type="number" step="%s" min="%s"%s value="%s">',
            $id,
            officeflow_e($name),
            officeflow_e((string) ($field['step'] ?? 'any')),
            officeflow_e((string) ($field['min'] ?? '0')),
            $required,
            officeflow_e($value)
        ),
        'date' => sprintf('<input id="%s" name="%s" type="date"%s value="%s">', $id, officeflow_e($name), $required, officeflow_e($value)),
        'datetime' => sprintf('<input id="%s" name="%s" type="datetime-local"%s value="%s">', $id, officeflow_e($name), $required, officeflow_e($value)),
        default => sprintf(
            '<input id="%s" name="%s" type="%s" maxlength="%d"%s value="%s">',
            $id,
            officeflow_e($name),
            officeflow_e($field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text')),
            (int) ($field['max'] ?? 255),
            $required,
            officeflow_e($value)
        ),
    };

    return sprintf(
        '<div class="app-field"><label for="%s">%s%s</label>%s</div>',
        $id,
        $label,
        ($field['required'] ?? false) === true ? '' : ' <span>Optional</span>',
        $control
    );
}

function officeflow_display_value(array $module, string $column, mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $type = $module['fields'][$column]['type'] ?? 'text';
    if ($type === 'datetime') {
        $time = strtotime((string) $value);
        return $time === false ? '—' : date('M j, Y H:i', $time);
    }
    if ($type === 'date') {
        $time = strtotime((string) $value);
        return $time === false ? '—' : date('M j, Y', $time);
    }
    if ($type === 'select') {
        return ucwords(str_replace('_', ' ', (string) $value));
    }
    return (string) $value;
}

try {
    $pdo = officeflow_db();
    if (!InstallService::isInstalled($pdo)) {
        officeflow_redirect('/install.php');
    }
} catch (Throwable $exception) {
    error_log('OfficeFlow module preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema before opening this module.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$slug = (string) ($_GET['m'] ?? '');
$module = Modules::find($slug);

if ($module === null) {
    http_response_code(404);
    $currentPage = 'Not found';
    require __DIR__ . '/app/views/app-header.php';
    echo '<section class="app-surface page-placeholder"><div><span class="placeholder-icon"><svg class="icon"><use href="#i-file"/></svg></span><h2>Module not found</h2><p>That office module does not exist. Pick one from the sidebar.</p><a class="button button-secondary" href="/">Back to home</a></div></section>';
    require __DIR__ . '/app/views/app-footer.php';
    exit;
}

$isAdmin = $user['role'] === 'admin';
$search = trim((string) ($_GET['search'] ?? ''));
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;

$referenceOptions = [];
foreach ($module['fields'] as $name => $field) {
    if ($field['type'] === 'reference') {
        $referenceOptions[$name] = CrudService::referenceOptions($pdo, $field);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/module.php?m=' . urlencode($slug));
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $newId = CrudService::create($pdo, $module, $user['id'], $_POST);
            NotificationService::notifyAdmins($pdo, $user['id'], 'record_created', 'New ' . $module['singular'] . ' added', (string) ($_POST['title'] ?? $_POST['name'] ?? ''), '/module.php?m=' . $slug);
            officeflow_flash('success', ucfirst($module['singular']) . ' saved.');
        } elseif ($action === 'update') {
            if (!$isAdmin) {
                throw new InvalidArgumentException('Only an administrator can edit records.');
            }
            CrudService::update($pdo, $module, (int) ($_POST['id'] ?? 0), $_POST, $user['id']);
            officeflow_flash('success', ucfirst($module['singular']) . ' updated.');
        } elseif ($action === 'delete') {
            if (!$isAdmin) {
                throw new InvalidArgumentException('Only an administrator can delete records.');
            }
            CrudService::delete($pdo, $module, (int) ($_POST['id'] ?? 0), $user['id']);
            officeflow_flash('success', ucfirst($module['singular']) . ' deleted.');
        } else {
            throw new InvalidArgumentException('Choose a valid action.');
        }
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow module action failed: ' . $exception->getMessage());
        officeflow_flash('error', $exception->getMessage() ?: 'The change could not be saved.');
    }

    officeflow_redirect('/module.php?m=' . urlencode($slug));
}

$records = CrudService::list($pdo, $module, $search);
$editing = ($editId !== null && $isAdmin) ? CrudService::find($pdo, $module, $editId) : null;
$currentPage = $module['label'];
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">OFFICE RESOURCES</p><h1><?= officeflow_e($module['label']) ?></h1><p><?= officeflow_e($module['description']) ?></p></div>
  <div class="app-heading-actions">
    <form method="get" action="/module.php" class="app-search-form">
      <input type="hidden" name="m" value="<?= officeflow_e($slug) ?>">
      <label class="visually-hidden" for="module-search">Search <?= officeflow_e($module['label']) ?></label>
      <input id="module-search" name="search" type="search" value="<?= officeflow_e($search) ?>" placeholder="Search <?= officeflow_e(strtolower($module['label'])) ?>">
      <button class="button button-secondary" type="submit">Search</button>
    </form>
  </div>
</section>

<section class="app-surface">
  <header class="app-surface-head"><div><h2><?= $editing ? 'Edit ' . officeflow_e($module['singular']) : 'Add ' . officeflow_e($module['singular']) ?></h2><p><?= $editing ? 'Update the record and save.' : 'New records are added by any team member; editing and deleting are administrator actions.' ?></p></div><?php if ($editing): ?><a class="text-button" href="/module.php?m=<?= officeflow_e($slug) ?>">Cancel edit</a><?php endif; ?></header>
  <form method="post" action="/module.php?m=<?= officeflow_e($slug) ?>" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <div class="app-form-grid">
      <?php foreach ($module['fields'] as $name => $field): ?>
        <?= officeflow_field_input($field, $name, $editing[$name] ?? ($field['default'] ?? ''), $referenceOptions) ?>
      <?php endforeach; ?>
    </div>
    <div class="app-form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Add ' . officeflow_e($module['singular']) ?></button></div>
  </form>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2><?= officeflow_e($module['label']) ?> list</h2><p><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?><?= $search !== '' ? ' matching “' . officeflow_e($search) . '”' : '' ?></p></div></header>
  <?php if ($records === []): ?>
    <div class="app-empty"><strong>Nothing here yet</strong><?= $search !== '' ? 'No records match that search.' : 'Add the first record using the form above.' ?></div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table">
      <thead><tr><?php foreach ($module['columns'] as $column => $label): ?><th><?= officeflow_e($label) ?></th><?php endforeach; ?><?php if ($isAdmin): ?><th>Manage</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($records as $record): ?>
        <tr>
          <?php foreach ($module['columns'] as $column => $label): ?>
            <td><?= officeflow_e(officeflow_display_value($module, $column, $record[$column] ?? null)) ?></td>
          <?php endforeach; ?>
          <?php if ($isAdmin): ?>
            <td class="module-actions">
              <a class="text-button" href="/module.php?m=<?= officeflow_e($slug) ?>&amp;edit=<?= (int) $record['id'] ?>">Edit</a>
              <form method="post" action="/module.php?m=<?= officeflow_e($slug) ?>" onsubmit="return confirm('Delete this record?');">
                <?= officeflow_csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                <button class="text-button text-danger" type="submit">Delete</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
