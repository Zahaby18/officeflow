<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/app/bootstrap.php';

if (!officeflow_verify_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

AuthService::logout();
officeflow_redirect('/login.php');
