<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = officeflow_db();
    if (!InstallService::isInstalled($pdo)) {
        officeflow_redirect('/install.php');
    }
} catch (Throwable $exception) {
    error_log('OfficeFlow login preflight failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OfficeFlow setup required</title><link rel="stylesheet" href="/assets/css/officeflow-app.css"><body class="auth-page"><main class="auth-main"><section class="auth-problem"><h1>OfficeFlow setup is not ready</h1><p>Check the database config, then import the initial schema. No connection details are shown on this page.</p><p><a href="/install.php">Open setup instructions</a></p></section></main></body></html>';
    exit;
}

if (AuthService::currentUser($pdo)) {
    officeflow_redirect('/');
}

$error = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!officeflow_verify_post()) {
        $error = 'This form expired. Reload the page and try again.';
    } elseif (AuthService::attempt($pdo, $email, $password) === null) {
        $error = 'Email or password is incorrect, or this account is inactive.';
    } else {
        officeflow_redirect('/');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153436">
  <title>Sign in · OfficeFlow</title>
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
    <div class="auth-brand-copy"><p class="eyebrow">YOUR OFFICE, IN GOOD ORDER</p><h1>Keep the day moving.</h1><p>Requests, tasks, and office resources in one workspace hosted by your team.</p></div>
    <span class="auth-orbit" aria-hidden="true"></span><small class="auth-brand-foot">Private workspace · Hosted by your office</small>
  </aside>
  <main class="auth-main">
    <section class="auth-card" aria-labelledby="loginTitle">
      <p class="eyebrow">WELCOME BACK</p><h2 id="loginTitle">Sign in to OfficeFlow</h2><p class="auth-card-intro">Use the work account for this office workspace.</p>
      <?php if ($error !== ''): ?><div class="app-flash app-flash-error" role="alert"><?= officeflow_e($error) ?></div><?php endif; ?>
      <form method="post" action="/login.php" autocomplete="on">
        <?= officeflow_csrf_input() ?>
        <div class="app-field"><label for="email">Work email</label><input id="email" name="email" type="email" required maxlength="190" value="<?= officeflow_e($email) ?>" autocomplete="username" autofocus></div>
        <div class="app-field"><label for="password">Password</label><input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password"></div>
        <div class="app-form-actions"><button class="button button-primary" type="submit">Sign in</button></div>
      </form>
      <p class="auth-note">This is a self-hosted OfficeFlow instance. Contact your office administrator if you need an account.</p>
    </section>
  </main>
</div>
</body>
</html>
