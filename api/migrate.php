<?php
// migrate.php — importa um blob local para uma conta AINDA VAZIA (primeira entrada).
// Não sobrescreve dados de servidor: se já existir blob ou rev>0, devolve 409.
require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'method'], 405);
$auth = require_auth();

$in   = json_in();
$data = $in['data'] ?? null;
if (!is_array($data) || !isset($data['projects']) || !is_array($data['projects'])) {
  json_out(['error' => 'bad_shape'], 400);
}
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($json === false) json_out(['error' => 'bad_json'], 400);
if (strlen($json) > MAX_BLOB_BYTES) json_out(['error' => 'payload_too_large'], 413);

// Só escreve se a conta estiver vazia (data NULL e rev=0). Atómico.
$st = db()->prepare('UPDATE accounts SET data = ?, rev = 1 WHERE id = ? AND data IS NULL AND rev = 0');
$st->execute([$json, $auth['account_id']]);

if ($st->rowCount() === 0) {
  $cur = db()->prepare('SELECT data, rev FROM accounts WHERE id = ?');
  $cur->execute([$auth['account_id']]);
  $row = $cur->fetch();
  json_out([
    'error' => 'server_not_empty',
    'rev'   => (int)($row['rev'] ?? 0),
    'data'  => ($row && $row['data'] !== null) ? json_decode($row['data'], true) : null,
  ], 409);
}

json_out(['rev' => 1]);
