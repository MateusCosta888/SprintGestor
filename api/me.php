<?php
// me.php — valida sessão no arranque do cliente (auto-login). Devolve só o rev.
require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') json_out(['error' => 'method'], 405);
$auth = require_auth();

$st = db()->prepare('SELECT rev FROM accounts WHERE id = ?');
$st->execute([$auth['account_id']]);
$row = $st->fetch();

json_out(['ok' => true, 'rev' => (int)($row['rev'] ?? 0)]);
