<?php
// data_get.php — devolve o blob JSON da conta + o rev atual.
require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') json_out(['error' => 'method'], 405);
$auth = require_auth();

$st = db()->prepare('SELECT data, rev FROM accounts WHERE id = ?');
$st->execute([$auth['account_id']]);
$row = $st->fetch();

json_out([
  'rev'  => (int)($row['rev'] ?? 0),
  'data' => $row['data'] !== null ? json_decode($row['data'], true) : null,
]);
