<?php
// data_put.php — grava o blob com concorrência otimista via rev.
// Se o cliente está desatualizado mas a divergência vem só de escritas do agente
// (ai_ops com item_rev > rev do cliente), faz merge aditivo server-side em vez de
// 409: criações da IA sobrevivem, o cliente vence nos itens comuns (spec §6).
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ai_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PUT') json_out(['error' => 'method'], 405);
$auth = require_auth();

$in   = json_in();
$rev  = isset($in['rev']) ? (int)$in['rev'] : -1;
$data = $in['data'] ?? null;

if (!is_array($data) || !isset($data['projects']) || !is_array($data['projects'])) {
  json_out(['error' => 'bad_shape'], 400);
}
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($json === false) json_out(['error' => 'bad_json'], 400);
if (strlen($json) > MAX_BLOB_BYTES) json_out(['error' => 'payload_too_large'], 413);

// Atómico: só escreve se o rev bater certo. rev=rev+1 garante "changed row" no sucesso.
$st = db()->prepare('UPDATE accounts SET data = ?, rev = rev + 1 WHERE id = ? AND rev = ?');
$st->execute([$json, $auth['account_id'], $rev]);

if ($st->rowCount() === 0) {
  // rev divergente — tenta merge aditivo das escritas do agente antes do 409.
  $cur = db()->prepare('SELECT data, rev FROM accounts WHERE id = ?');
  $cur->execute([$auth['account_id']]);
  $row = $cur->fetch();
  $serverRev = (int)($row['rev'] ?? 0);
  if ($rev >= 0 && $rev < $serverRev && $row && $row['data'] !== null) {
    $server = json_decode($row['data'], true);
    if (!is_array($server)) $server = ['projects' => []];
    $ops = [];
    try {
      // todas as escritas do agente no intervalo (create+status consomem rev)
      $l = db()->prepare("SELECT op, target, item_rev, item_id FROM ai_ops
                          WHERE account_id = ? AND op IN ('create','status') AND item_rev > ?
                          ORDER BY id ASC");
      $l->execute([$auth['account_id'], $rev]);
      $ops = $l->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $ops = []; }
    // só funde quando o diário explica todos os increments; senão 409 (escritas humanas no intervalo)
    if ($ops && count($ops) === $serverRev - $rev) {
      $creates = array_values(array_filter($ops, fn ($o) => $o['op'] === 'create' && !empty($o['item_id'])));
      $merged = ai_merge_new($server, $data, $creates);
      $mj = json_encode($merged, JSON_UNESCAPED_UNICODE);
      if ($mj !== false && strlen($mj) <= MAX_BLOB_BYTES) {
        $u = db()->prepare('UPDATE accounts SET data = ?, rev = rev + 1, updated_at = NOW() WHERE id = ? AND rev = ?');
        $u->execute([$mj, $auth['account_id'], $serverRev]);
        if ($u->rowCount() === 1) json_out(['rev' => $serverRev + 1, 'merged' => true]);
      }
    }
  }
  json_out([
    'error' => 'conflict',
    'rev'   => $serverRev,
    'data'  => ($row && $row['data'] !== null) ? json_decode($row['data'], true) : null,
  ], 409);
}

json_out(['rev' => $rev + 1]);
