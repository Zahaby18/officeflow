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
    error_log('OfficeFlow inventory preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow database is not ready</h1><p>Check the database configuration and import the schema first.</p><p><a href="/install.php">Open setup</a></p></section></main></body></html>';
    exit;
}

$user = officeflow_require_user($pdo);
$officeName = SettingsService::get($pdo, 'office_name', 'Office workspace');
$isAdmin = $user['role'] === 'admin';
$search = trim((string) ($_GET['search'] ?? ''));
$viewId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT) ?: null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!officeflow_verify_post()) {
        officeflow_flash('error', 'This form expired. Reload the page and try again.');
        officeflow_redirect('/inventory.php');
    }

    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            InventoryService::create($pdo, $user['id'], $_POST);
            NotificationService::notifyAdmins($pdo, $user['id'], 'record_created', 'New inventory item added', (string) ($_POST['name'] ?? ''), '/inventory.php');
            officeflow_flash('success', 'Inventory item added.');
        } elseif ($action === 'update') {
            if (!$isAdmin) {
                throw new InvalidArgumentException('Only an administrator can edit inventory items.');
            }
            InventoryService::update($pdo, (int) ($_POST['id'] ?? 0), $user['id'], $_POST);
            officeflow_flash('success', 'Inventory item updated.');
        } elseif ($action === 'adjust') {
            InventoryService::adjust(
                $pdo,
                (int) ($_POST['item_id'] ?? 0),
                $user['id'],
                (string) ($_POST['transaction_type'] ?? ''),
                (string) ($_POST['quantity'] ?? ''),
                (string) ($_POST['note'] ?? '')
            );
            officeflow_flash('success', 'Stock movement recorded.');
        } else {
            throw new InvalidArgumentException('Choose a valid action.');
        }
    } catch (InvalidArgumentException $exception) {
        officeflow_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('OfficeFlow inventory action failed: ' . $exception->getMessage());
        officeflow_flash('error', 'The inventory change could not be saved.');
    }

    officeflow_redirect('/inventory.php');
}

$items = InventoryService::list($pdo, $search);
$lowStock = InventoryService::lowStock($pdo);
$editing = ($isAdmin && $viewId !== null) ? InventoryService::find($pdo, $viewId) : null;
$movements = $viewId !== null ? InventoryService::movements($pdo, $viewId) : InventoryService::recentMovements($pdo);
$currentPage = 'Inventory';
require __DIR__ . '/app/views/app-header.php';
?>
<section class="app-page-heading">
  <div><p class="eyebrow">OFFICE RESOURCES</p><h1>Inventory</h1><p>Track everyday supplies before the cupboard runs empty.</p></div>
  <div class="app-heading-actions">
    <form method="get" action="/inventory.php" class="app-search-form">
      <label class="visually-hidden" for="inventory-search">Search inventory</label>
      <input id="inventory-search" name="search" type="search" value="<?= officeflow_e($search) ?>" placeholder="Search items">
      <button class="button button-secondary" type="submit">Search</button>
    </form>
  </div>
</section>

<section class="app-metrics" aria-label="Inventory summary">
  <article class="app-metric"><span class="app-metric-label">Items tracked</span><strong class="app-metric-value"><?= InventoryService::count($pdo) ?></strong><div class="app-metric-note">All inventory records</div></article>
  <article class="app-metric"><span class="app-metric-label">At or below reorder level</span><strong class="app-metric-value"><?= count($lowStock) ?></strong><div class="app-metric-note">Worth reordering</div></article>
</section>

<?php if ($lowStock !== []): ?>
<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Needs reordering</h2><p>Quantity has reached the reorder level</p></div></header>
  <div class="app-table-wrap"><table class="app-table"><thead><tr><th>Item</th><th>On hand</th><th>Reorder level</th></tr></thead><tbody>
    <?php foreach ($lowStock as $item): ?>
      <tr><td><?= officeflow_e($item['name']) ?></td><td><?= officeflow_e(rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.')) ?> <?= officeflow_e($item['unit']) ?></td><td><?= officeflow_e(rtrim(rtrim(number_format((float) $item['reorder_level'], 2, '.', ''), '0'), '.')) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<section class="app-surface">
  <header class="app-surface-head"><div><h2><?= $editing ? 'Edit item' : 'Add an item' ?></h2><p><?= $editing ? 'Quantity is changed through stock movements, not here.' : 'Set the starting quantity, then use stock movements to keep it accurate.' ?></p></div><?php if ($editing): ?><a class="text-button" href="/inventory.php">Cancel edit</a><?php endif; ?></header>
  <form method="post" action="/inventory.php" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <div class="app-form-grid">
      <div class="app-field"><label for="item-name">Item name</label><input id="item-name" name="name" required minlength="2" maxlength="180" value="<?= officeflow_e($editing['name'] ?? '') ?>" placeholder="e.g. A4 copy paper"></div>
      <div class="app-field"><label for="item-sku">SKU <span>Optional</span></label><input id="item-sku" name="sku" maxlength="80" value="<?= officeflow_e($editing['sku'] ?? '') ?>"></div>
      <div class="app-field"><label for="item-category">Category</label><input id="item-category" name="category" required maxlength="80" value="<?= officeflow_e($editing['category'] ?? 'Office supplies') ?>"></div>
      <div class="app-field"><label for="item-unit">Unit</label><input id="item-unit" name="unit" required maxlength="30" value="<?= officeflow_e($editing['unit'] ?? 'pcs') ?>"></div>
      <?php if (!$editing): ?>
        <div class="app-field"><label for="item-quantity">Starting quantity</label><input id="item-quantity" name="quantity" type="number" step="0.01" min="0" value="0"></div>
      <?php endif; ?>
      <div class="app-field"><label for="item-reorder">Reorder level</label><input id="item-reorder" name="reorder_level" type="number" step="0.01" min="0" value="<?= officeflow_e($editing['reorder_level'] ?? '0') ?>"></div>
      <div class="app-field"><label for="item-location">Location <span>Optional</span></label><input id="item-location" name="location" maxlength="120" value="<?= officeflow_e($editing['location'] ?? '') ?>"></div>
    </div>
    <div class="app-form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Add item' ?></button></div>
  </form>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Stock movement</h2><p>Every movement is written to the history below.</p></div></header>
  <form method="post" action="/inventory.php" class="module-form">
    <?= officeflow_csrf_input() ?>
    <input type="hidden" name="action" value="adjust">
    <div class="app-form-grid">
      <div class="app-field"><label for="movement-item">Item</label><select id="movement-item" name="item_id" required><option value="">Choose an item</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $viewId === (int) $item['id'] ? ' selected' : '' ?>><?= officeflow_e($item['name']) ?></option><?php endforeach; ?></select></div>
      <div class="app-field"><label for="movement-type">Movement</label><select id="movement-type" name="transaction_type"><option value="received">Received</option><option value="issued">Issued</option><option value="adjustment">Adjustment</option></select></div>
      <div class="app-field"><label for="movement-quantity">Quantity</label><input id="movement-quantity" name="quantity" type="number" step="0.01" min="0.01" required placeholder="e.g. 5"></div>
      <div class="app-field"><label for="movement-note">Note <span>Optional</span></label><input id="movement-note" name="note" maxlength="255" placeholder="e.g. Received from supplier"></div>
    </div>
    <div class="app-form-actions"><button class="button button-primary" type="submit">Record movement</button></div>
  </form>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2>Inventory list</h2><p><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?><?= $search !== '' ? ' matching “' . officeflow_e($search) . '”' : '' ?></p></div></header>
  <?php if ($items === []): ?>
    <div class="app-empty"><strong>No inventory yet</strong>Add the first item using the form above.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table">
      <thead><tr><th>Item</th><th>Category</th><th>On hand</th><th>Reorder level</th><th>Location</th><th>Manage</th></tr></thead>
      <tbody>
      <?php foreach ($items as $item): ?>
        <?php $low = (float) $item['quantity'] <= (float) $item['reorder_level']; ?>
        <tr>
          <td><span class="app-request-title"><strong><?= officeflow_e($item['name']) ?></strong><small><?= officeflow_e($item['sku'] ?? '—') ?></small></span></td>
          <td><?= officeflow_e($item['category']) ?></td>
          <td><?= officeflow_e(rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.')) ?> <?= officeflow_e($item['unit']) ?><?= $low ? ' <span class="watch-tag tag-low">reorder</span>' : '' ?></td>
          <td><?= officeflow_e(rtrim(rtrim(number_format((float) $item['reorder_level'], 2, '.', ''), '0'), '.')) ?></td>
          <td><?= officeflow_e($item['location'] ?? '—') ?></td>
          <td class="module-actions">
            <a class="text-button" href="/inventory.php?view=<?= (int) $item['id'] ?>">History</a>
            <?php if ($isAdmin): ?><a class="text-button" href="/inventory.php?view=<?= (int) $item['id'] ?>#item-name">Edit</a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<section class="app-surface app-records">
  <header class="app-surface-head"><div><h2><?= $viewId !== null ? 'Movement history for item #' . (int) $viewId : 'Recent movements' ?></h2><p><?= count($movements) ?> entr<?= count($movements) === 1 ? 'y' : 'ies' ?></p></div><?php if ($viewId !== null): ?><a class="text-button" href="/inventory.php">Show all recent</a><?php endif; ?></header>
  <?php if ($movements === []): ?>
    <div class="app-empty"><strong>No movements yet</strong>Record a stock movement to start the history.</div>
  <?php else: ?>
    <div class="app-table-wrap"><table class="app-table">
      <thead><tr><th><?= $viewId !== null ? 'When' : 'Item' ?></th><?php if ($viewId === null): ?><th>When</th><?php endif; ?><th>Movement</th><th>Quantity</th><th>Note</th><th>By</th></tr></thead>
      <tbody>
      <?php foreach ($movements as $movement): ?>
        <tr>
          <td><?= officeflow_e($viewId !== null ? date('M j, Y H:i', strtotime((string) $movement['created_at'])) : ($movement['item_name'] ?? '—')) ?></td>
          <?php if ($viewId === null): ?><td><?= officeflow_e(date('M j, Y H:i', strtotime((string) $movement['created_at']))) ?></td><?php endif; ?>
          <td><?= officeflow_e(ucfirst($movement['transaction_type'])) ?></td>
          <td><?= officeflow_e(rtrim(rtrim(number_format((float) $movement['quantity'], 2, '.', ''), '0'), '.')) ?></td>
          <td><?= officeflow_e($movement['note'] ?? '—') ?></td>
          <td><?= officeflow_e($movement['actor_name'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/app/views/app-footer.php'; ?>
