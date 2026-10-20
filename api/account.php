<?php
// account.php — info da conta (GET) e nome de utilizador (POST).
require_once __DIR__ . '/bootstrap.php';

$auth = require_auth();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
  $in = json_in();
  $name = isset($in['name']) ? trim((string)$in['name']) : '';
  $name = preg_replace('/\s+/u', ' ', $name);           // colapsa espaços
  if (function_exists('filter_var') && function_exists('strip_tags')) $name = strip_tags($name);
  if (mb_strlen($name) > 40) $name = mb_substr($name, 0, 40);
  $st = db()->prepare('UPDATE accounts SET display_name = ? WHERE id = ?');
  $st->execute([$name === '' ? null : $name, $auth['account_id']]);
  json_out(['ok' => true, 'name' => $name === '' ? null : $name]);
}

if ($method !== 'GET') json_out(['error' => 'method'], 405);

$st = db()->prepare('SELECT display_name, data, created_at, agent_hash, agent_created_at FROM accounts WHERE id = ?');
$st->execute([$auth['account_id']]);
$row = $st->fetch();

$projectCount = 0; $sprintCount = 0;
if (!empty($row['data'])) {
  $data = json_decode($row['data'], true);
  if (is_array($data) && isset($data['projects']) && is_array($data['projects'])) {
    $projectCount = count($data['projects']);
    foreach ($data['projects'] as $p) {
      if (isset($p['sprints']) && is_array($p['sprints'])) $sprintCount += count($p['sprints']);
    }
  }
}

// estado do Acesso de IA (spec §7) — só metadados; o token vive como hash
$agent = ['active' => false, 'created_at' => null, 'ops_today' => 0, 'last_ops' => []];
if (!empty($row['agent_hash'])) {
  $agent['active'] = true;
  $agent['created_at'] = $row['agent_created_at'] ?? null;
}
try {
  $c = db()->prepare("SELECT COUNT(*) FROM ai_ops WHERE account_id = ? AND op IN ('create','status') AND ts >= CURDATE()");
  $c->execute([$auth['account_id']]);
  $agent['ops_today'] = (int)($c->fetchColumn() ?: 0);
  $l = db()->prepare('SELECT ts, op, target FROM ai_ops WHERE account_id = ? ORDER BY id DESC LIMIT 5');
  $l->execute([$auth['account_id']]);
  $agent['last_ops'] = $l->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* tabela ainda não criada (deploy parcial) — não parte a modal */ }

json_out([
  'name'         => $row['display_name'] ?? null,
  'projectCount' => $projectCount,
  'sprintCount'  => $sprintCount,
  'createdAt'    => $row['created_at'] ?? null,
  'agent'        => $agent,
]);
