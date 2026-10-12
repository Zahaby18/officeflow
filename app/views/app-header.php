<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

$officeName = $officeName ?? 'Office workspace';
$currentPage = $currentPage ?? 'Home';
$user = $user ?? ['name' => 'Team member', 'role' => 'staff', 'id' => 0];

if (!isset($unreadNotifications)) {
    try {
        $unreadNotifications = NotificationService::unreadCount(officeflow_db(), (int) $user['id']);
    } catch (Throwable) {
        $unreadNotifications = 0;
    }
}

$navigation = Modules::navigation();
$activeSlug = $activeSlug ?? '';
if ($activeSlug === '') {
    foreach ($navigation as $slug => $item) {
        if ($item['label'] === $currentPage) {
            $activeSlug = $slug;
            break;
        }
    }
}
$initials = strtoupper(mb_substr(trim($user['name']), 0, 1, 'UTF-8'));
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
      <?php foreach (Modules::SECTIONS as $sectionKey => $section): ?>
        <p class="nav-heading"><?= officeflow_e($section['label']) ?></p>
        <?php foreach ($section['items'] as $itemSlug): ?>
          <?php $item = $navigation[$itemSlug] ?? null; if ($item === null) { continue; } ?>
          <a class="nav-link <?= $activeSlug === $itemSlug ? 'is-active' : '' ?>" href="<?= officeflow_e($item['href']) ?>">
            <svg class="icon"><use href="#<?= officeflow_e($item['icon']) ?>"/></svg><span><?= officeflow_e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php $settingsItem = $navigation['settings']; ?>
      <a class="nav-link settings-link <?= $activeSlug === 'settings' ? 'is-active' : '' ?>" href="<?= officeflow_e($settingsItem['href']) ?>">
        <svg class="icon"><use href="#<?= officeflow_e($settingsItem['icon']) ?>"/></svg><span><?= officeflow_e($settingsItem['label']) ?></span>
      </a>
    </nav>
    <div class="sidebar-user">
      <span class="avatar"><?= officeflow_e($initials) ?></span>
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
        <a class="icon-button notification-button" href="/notifications.php" aria-label="Notifications<?= $unreadNotifications > 0 ? ', ' . (int) $unreadNotifications . ' unread' : '' ?>">
          <svg class="icon"><use href="#i-bell"/></svg>
          <?php if ($unreadNotifications > 0): ?><span class="notification-count"><?= $unreadNotifications > 99 ? '99+' : (int) $unreadNotifications ?></span><?php endif; ?>
        </a>
        <span class="top-profile" aria-label="Signed in as <?= officeflow_e($user['name']) ?>"><span class="avatar"><?= officeflow_e($initials) ?></span></span>
      </div>
    </header>
    <section class="app-content">
<?php
$flash = officeflow_take_flash();
if ($flash !== null):
?>
      <div class="app-flash app-flash-<?= officeflow_e($flash['type']) ?>" role="status"><?= officeflow_e($flash['message']) ?></div>
<?php endif; ?>
