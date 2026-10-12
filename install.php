<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/app/bootstrap.php';

function installationProblem(string $title, string $detail, ?string $command = null): never
{
    http_response_code(503);
    $escapedTitle = officeflow_e($title);
    $escapedDetail = officeflow_e($detail);
    $commandHtml = $command === null ? '' : '<pre>' . officeflow_e($command) . '</pre>';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>OfficeFlow setup</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"></head><body class="auth-page"><main class="auth-main"><section class="auth-problem"><p class="eyebrow">OFFICEFLOW SETUP</p><h1>' . $escapedTitle . '</h1><p>' . $escapedDetail . '</p>' . $commandHtml . '<p>After the database is ready, reload this page. Do not share your database password in chat or commit it to Git.</p></section></main></body></html>';
    exit;
}

try {
    $pdo = officeflow_db();
} catch (RuntimeException $exception) {
    installationProblem(
        'Add the database connection',
        'Copy config/database.example.php to config/database.php, then enter the database name, user, and password created for this site in CloudPanel.'
    );
} catch (PDOException $exception) {
    error_log('OfficeFlow database connection failed: ' . $exception->getMessage());
    installationProblem(
        'Could not connect to the database',
        'Check the database name, user, password, and host in config/database.php. The connection details are not shown here for security.'
    );
}

try {
    $isInstalled = InstallService::isInstalled($pdo);
} catch (PDOException $exception) {
    error_log('OfficeFlow schema check failed: ' . $exception->getMessage());
    installationProblem(
        'Import the database schema first',
        'The database is reachable, but the OfficeFlow tables are missing. Import database/migrations/001_initial_schema.sql into the empty OfficeFlow database, then reload this page.',
        'clpctl db:import --databaseName=YOUR_DATABASE --file=/home/YOUR_SITE_USER/tmp/001_initial_schema.sql'
    );
}

if ($isInstalled) {
    officeflow_redirect('/login.php');
}

$errors = [];
$name = '';
$email = '';
$officeName = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $officeName = trim((string) ($_POST['office_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (!officeflow_verify_post()) {
        $errors[] = 'This form expired. Reload the page and try again.';
    } elseif (!hash_equals($password, $passwordConfirm)) {
        $errors[] = 'The passwords do not match.';
    } else {
        try {
            $admin = InstallService::createFirstAdmin($pdo, $name, $email, $password, $officeName);
            if ($admin === null) {
                officeflow_redirect('/login.php');
            }
            AuthService::attempt($pdo, $email, $password);
            officeflow_redirect('/');
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('OfficeFlow initial admin setup failed: ' . $exception->getMessage());
            $errors[] = 'Setup could not be completed. Check the site logs and database permissions, then try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153436">
  <title>First-time setup · OfficeFlow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/officeflow.css">
  <link rel="stylesheet" href="/assets/css/officeflow-app.css">
</head>
<body>
<div class="auth-page">
  <aside class="auth-brand-panel">
    <a class="auth-brand" href="/preview.html"><span class="brand-mark">OF</span><span><strong>OfficeFlow</strong><small>Operations workspace</small></span></a>
    <div class="auth-brand-copy"><p class="eyebrow">FIRST-TIME SETUP</p><h1>Make this workspace yours.</h1><p>Create the office administrator account. This setup page locks after the first account is created.</p></div>
    <span class="auth-orbit" aria-hidden="true"></span><small class="auth-brand-foot">Your office data stays in your hosting account.</small>
  </aside>
  <main class="auth-main">
    <section class="auth-card" aria-labelledby="setupTitle">
      <p class="eyebrow">OFFICEFLOW INSTALLATION</p><h2 id="setupTitle">Create your admin account</h2><p class="auth-card-intro">This becomes the first administrator for this OfficeFlow installation.</p>
      <?php if ($errors !== []): ?><div class="app-flash app-flash-error" role="alert"><?= officeflow_e(implode(' ', $errors)) ?></div><?php endif; ?>
      <form method="post" action="/install.php" autocomplete="on">
        <?= officeflow_csrf_input() ?>
        <div class="app-field"><label for="office_name">Office name</label><input id="office_name" name="office_name" required maxlength="160" value="<?= officeflow_e($officeName) ?>" autocomplete="organization" placeholder="e.g. Northstar Studio"></div>
        <div class="app-field"><label for="name">Your name</label><input id="name" name="name" required minlength="2" maxlength="120" value="<?= officeflow_e($name) ?>" autocomplete="name" placeholder="Alya Dwi"></div>
        <div class="app-field"><label for="email">Work email</label><input id="email" name="email" type="email" required maxlength="190" value="<?= officeflow_e($email) ?>" autocomplete="email" placeholder="you@company.com"></div>
        <div class="app-field"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="12" maxlength="200" autocomplete="new-password" aria-describedby="passwordHelp"><small id="passwordHelp" class="auth-note">Use at least 12 characters. This password is stored as a secure hash.</small></div>
        <div class="app-field"><label for="password_confirm">Confirm password</label><input id="password_confirm" name="password_confirm" type="password" required minlength="12" maxlength="200" autocomplete="new-password"></div>
        <div class="app-form-actions"><button class="button button-primary" type="submit">Create admin account</button></div>
      </form>
    </section>
  </main>
</div>
</body>
</html>
