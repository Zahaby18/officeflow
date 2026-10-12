<?php

declare(strict_types=1);

define('OFFICEFLOW_APP', true);

require_once __DIR__ . '/../../app/RequestService.php';

function expectRequest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectInvalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
)");
$pdo->exec("CREATE TABLE requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    details TEXT,
    category TEXT NOT NULL,
    priority TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    requester_id INTEGER NOT NULL,
    assignee_id INTEGER,
    due_at TEXT,
    completed_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("INSERT INTO users (name, email, role) VALUES ('Alya Admin', 'alya@example.test', 'admin'), ('Rafi Staff', 'rafi@example.test', 'staff')");

$id = RequestService::create($pdo, 2, [
    'title' => '  Printer paper for meeting room  ',
    'details' => 'Five boxes, A4 size.',
    'category' => 'Office supplies',
    'priority' => 'high',
]);
expectRequest($id > 0, 'request creation should return a database id');

$request = $pdo->query('SELECT * FROM requests WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
expectRequest($request['title'] === 'Printer paper for meeting room', 'title should be trimmed');
expectRequest($request['status'] === 'new', 'new requests should begin in new status');
expectRequest($request['requester_id'] === '2' || $request['requester_id'] === 2, 'requester must be tied to the signed-in user');
expectRequest(str_starts_with($request['request_code'], 'OF-'), 'request code should use the OfficeFlow prefix');

$list = RequestService::list($pdo);
expectRequest(count($list) === 1, 'request should appear in the list');
expectRequest($list[0]['requester_name'] === 'Rafi Staff', 'list should include requester display name');
expectRequest(RequestService::countOpen($pdo) === 1, 'new request should count as open');
$summary = RequestService::summary($pdo);
expectRequest($summary['new'] === 1 && $summary['in_progress'] === 0, 'summary should include counts for each known status');

RequestService::updateStatus($pdo, (int) $id, 'in_progress', 'admin');
expectRequest($pdo->query('SELECT status FROM requests WHERE id = ' . (int) $id)->fetchColumn() === 'in_progress', 'admin should update request status');
expectInvalid(fn() => RequestService::updateStatus($pdo, (int) $id, 'completed', 'staff'), 'staff should not change request status');
expectInvalid(fn() => RequestService::updateStatus($pdo, (int) $id, 'invented', 'admin'), 'unknown status must be rejected');
expectInvalid(fn() => RequestService::create($pdo, 2, ['title' => 'x', 'category' => 'Office supplies']), 'short titles must be rejected');
expectInvalid(fn() => RequestService::create($pdo, 2, ['title' => 'Valid title', 'category' => 'Unlisted']), 'unknown categories must be rejected');
expectRequest(RequestService::countOpen($pdo) === 1, 'status transition should keep an in-progress request open');

RequestService::updateStatus($pdo, (int) $id, 'completed', 'admin');
expectRequest(RequestService::countOpen($pdo) === 0, 'completed request should not count as open');
expectRequest($pdo->query('SELECT completed_at FROM requests WHERE id = ' . (int) $id)->fetchColumn() !== null, 'completion time should be recorded');

echo "RequestService integration checks: PASS\n";
