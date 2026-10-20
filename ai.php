<?php
// =============================================================================
// ai.php — PONTO DE ENTRADA DA AGENT ACCESS (GET-only, pública-por-token).
// Espelha a secção AGENT API de dev-server.py (mock) — manter em sincronia.
// O segredo é o token no path (/ai/<32chars>); o hash é comparado na BD.
// NÃO inclui api/bootstrap.php de propósito: a verificação de origem ali
// bloquearia CLIs/ferramentas cross-origin — aqui o CORS é aberto só para GET.
// =============================================================================
require __DIR__ . '/api/config.php';
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/ai_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

function ai_out($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function ai_bad_token(): void { ai_out(['ok' => false, 'error' => 'invalid_token'], 404); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') ai_bad_token();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (!preg_match('#^/?ai/([23456789ABCDEFGHJKMNPQRS]{' . AI_TOKEN_LEN . '})$#', $path, $m)) ai_bad_token();
$token = $m[1];
$hash = hash('sha256', $token);

$pdo = db();
$stmt = $pdo->prepare('SELECT id, display_name, data, rev FROM accounts WHERE agent_hash = ?');
$stmt->execute([$hash]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) ai_bad_token();
$aid = (int)$account['id'];

// throttle por IP (todos os ops) — conta o próprio ai_ops, sem tocar em login_attempts
$ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: "\x00\x00\x00\x00";
$t = $pdo->prepare('SELECT COUNT(*) FROM ai_ops WHERE ip = ? AND ts >= NOW() - INTERVAL 1 HOUR');
$t->execute([$ip]);
if ((int)($t->fetchColumn() ?: 0) > AI_HOURLY_IP) ai_out(['ok' => false, 'error' => 'rate_limited', 'hint' => 'too many requests from this IP; slow down'], 429);

function ai_log_op(PDO $pdo, int $aid, string $op, ?string $target, ?int $rev = null, ?string $item_id = null): void {
  $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') ?: "\x00\x00\x00\x00";
  $pdo->prepare('INSERT INTO ai_ops (account_id, ts, op, target, ip, item_rev, item_id) VALUES (?, NOW(), ?, ?, ?, ?, ?)')
      ->execute([$aid, $op, $target, $ip, $rev, $item_id]);
}

$op = (string)($_GET['op'] ?? '');
$blob = json_decode((string)($account['data'] ?? ''), true);
if (!is_array($blob) || !isset($blob['projects'])) $blob = ['projects' => []];
$scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/ai/' . $token;

if ($op === '') {
  ai_log_op($pdo, $aid, 'manual', null);
  if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>SprintG — Agent Access</title><style>body{background:#05070d;color:#e8edf6;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0}'
       . '.c{text-align:center;padding:32px;max-width:520px}h1{font-weight:400;font-size:28px}p{color:#93a0b8;line-height:1.6}a{color:#60a5fa}</style></head>'
       . '<body><div class="c"><h1>SprintG — Agent Access</h1><p>Isto é um ponto de acesso de máquina (chatbots e CLIs). '
       . 'Abre este URL a partir de uma ferramenta com <code>Accept: application/json</code> para obter o manual.</p>'
       . '<p><a href="/">← Gerir acesso no SprintG</a></p></div></body></html>';
    exit;
  }
  ai_out(ai_manual(['display_name' => $account['display_name']], $base, $token));
}

if ($op === 'board') {
  ai_log_op($pdo, $aid, 'board', null);
  ai_out(['ok' => true, 'projects' => ai_board($blob)]);
}

if ($op !== 'create' && $op !== 'status') {
  ai_out(['ok' => false, 'error' => 'bad_payload', 'hint' => 'unknown op; use board|create|status'], 400);
}

// escrita: limite diário por conta
$c = $pdo->prepare("SELECT COUNT(*) FROM ai_ops WHERE account_id = ? AND op IN ('create','status') AND ts >= CURDATE()");
$c->execute([$aid]);
if ((int)$c->fetchColumn() >= AI_DAILY_WRITES) {
  ai_out(['ok' => false, 'error' => 'rate_limited', 'hint' => 'daily limit reached; try tomorrow or ask the owner to reset'], 429);
}

$v = ai_validate_d((string)($_GET['d'] ?? ''));
if (!$v['ok']) ai_out(['ok' => false, 'error' => $v['error'], 'hint' => $v['hint'] ?? ''], 400);

// transação com lock de linha: serializa agentes concorrentes na mesma conta
$pdo->beginTransaction();
try {
  $s = $pdo->prepare('SELECT data, rev FROM accounts WHERE id = ? FOR UPDATE');
  $s->execute([$aid]);
  $row = $s->fetch(PDO::FETCH_ASSOC);
  if (!$row) { $pdo->rollBack(); ai_bad_token(); }
  $live = json_decode((string)($row['data'] ?? ''), true);
  if (!is_array($live) || !isset($live['projects'])) $live = ['projects' => []];

  $res = $op === 'create' ? ai_create($live, $v['json']) : ai_status($live, $v['json']);
  if (!$res['ok']) { $pdo->rollBack(); ai_out(['ok' => false, 'error' => $res['error'], 'hint' => $res['hint'] ?? ''], $res['error'] === 'not_found' ? 404 : 400); }

  $json = json_encode($live, JSON_UNESCAPED_UNICODE);
  if (strlen($json) > MAX_BLOB_BYTES) { $pdo->rollBack(); ai_out(['ok' => false, 'error' => 'blob_full', 'hint' => 'account storage limit reached'], 507); }
  $newRev = (int)$row['rev'] + 1;
  $u = $pdo->prepare('UPDATE accounts SET data = ?, rev = ?, updated_at = NOW() WHERE id = ?');
  $u->execute([$json, $newRev, $aid]);
  ai_log_op($pdo, $aid, $op, ($res['res']['type'] ?? $op) . ':' . ($res['res']['title'] ?? $res['res']['id'] ?? ''),
            $newRev, $op === 'create' ? ($res['res']['id'] ?? null) : null);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('ai.php: ' . $e->getMessage());
  ai_out(['ok' => false, 'error' => 'server_error', 'hint' => 'unexpected error; retry once, then report to the owner'], 500);
}

ai_out(['ok' => true, ($op === 'create' ? 'created' : 'updated') => $res['res']]);
