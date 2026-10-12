<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

/*
 * Copy this file to config/database.php and fill in the database credentials.
 * Never commit config/database.php. Point the web server document root at public/.
 */
return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'officeflow',
    'username' => 'officeflow_user',
    'password' => 'CHANGE_THIS_LOCALLY',
    'charset' => 'utf8mb4',
];
