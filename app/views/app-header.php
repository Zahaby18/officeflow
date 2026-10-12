<?php

declare(strict_types=1);

$officeName = $officeName ?? 'Office workspace';
$currentPage = $currentPage ?? 'Home';
$user = $user ?? ['name' => 'Team member', 'role' => 'staff'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153436">
  <title><?= officeflow_e($currentPage) ?> · OfficeFlow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= officeflow_e(officeflow_asset('css/officeflow.css')) ?>">
  <link rel="stylesheet" href="<?= officeflow_e(officeflow_asset('css/officeflow-app.css')) ?>">
</head>
<body>
<?php require __DIR__ . '/icons.php'; ?>
<div class="app-layout">
  <aside class="sidebar" aria-label="Main navigation">
    <a class="brand" href="/" aria-label="OfficeFlow home">
      <span class="brand-mark"><svg class="icon"><use href="#i-mark"/></svg></span>
      <span><strong>OfficeFlow</strong><small><?= officeflow_e($officeName) ?></small></span>
    </a>
    <nav class="navigation">
      <p class="nav-heading">Workspace</p>
      <a class="nav-link <?= $currentPage === 'Home' ? 'is-active' : '' ?>" href="/"><svg class="icon"><use href="#i-home"/></svg><span>Home</span></a>
      <a class="nav-link <?= $currentPage === 'Requests' ? 'is-active' : '' ?>" href="/requests.php"><svg class="icon"><use href="#i-inbox"/></svg><span>Requests</span></a>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-checklist"/></svg><span>Tasks &amp; Checklists</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-megaphone"/></svg><span>Announcements</span><span class="app-nav-soon">Soon</span></button>
      <p class="nav-heading">Office resources</p>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-box"/></svg><span>Assets</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-package"/></svg><span>Inventory</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-tool"/></svg><span>Maintenance</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-building"/></svg><span>Rooms &amp; Facilities</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-users"/></svg><span>Vendors</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-file"/></svg><span>Documents &amp; SOPs</span><span class="app-nav-soon">Soon</span></button>
      <p class="nav-heading">Management</p>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-users"/></svg><span>Team</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-chart"/></svg><span>Reports</span><span class="app-nav-soon">Soon</span></button>
      <button class="nav-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-clock"/></svg><span>Activity log</span><span class="app-nav-soon">Soon</span></button>
    </nav>
    <button class="nav-link settings-link" type="button" disabled aria-disabled="true" title="Module planned for a later build"><svg class="icon"><use href="#i-settings"/></svg><span>Settings</span><span class="app-nav-soon">Soon</span></button>
    <div class="sidebar-user">
      <span class="avatar"><?= officeflow_e(strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'))) ?></span>
      <span class="user-copy"><strong><?= officeflow_e($user['name']) ?></strong><small><?= officeflow_e(ucfirst($user['role'])) ?></small></span>
      <form class="app-user-form" method="post" action="/logout.php">
        <?= officeflow_csrf_input() ?>
        <button class="app-logout" type="submit">Sign out</button>
      </form>
    </div>
  </aside>
  <main class="app-main">
    <header class="topbar">
      <div class="breadcrumb"><span><?= officeflow_e($officeName) ?></span><span class="slash">/</span><strong><?= officeflow_e($currentPage) ?></strong></div>
      <div class="topbar-actions">
        <button class="icon-button" type="button" aria-label="Notifications are planned, not active yet" disabled title="Notifications are planned for a later build"><svg class="icon"><use href="#i-bell"/></svg></button>
        <span class="top-profile" aria-label="Signed in as <?= officeflow_e($user['name']) ?>"><span class="avatar"><?= officeflow_e(strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'))) ?></span></span>
      </div>
    </header>
    <section class="app-content">
<?php
$flash = officeflow_take_flash();
if ($flash !== null):
?>
      <div class="app-flash app-flash-<?= officeflow_e($flash['type']) ?>" role="status"><?= officeflow_e($flash['message']) ?></div>
<?php endif; ?>
