<?php
// bootstrap.php — incluído por TODOS os endpoints. Headers JSON, helpers de I/O,
// autenticação por bearer, lock de origem e purga lazy de sessões expiradas.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Mesma origem: não enviamos CORS. Se ALLOWED_ORIGIN estiver definido, ecoamos-no.
if (ALLOWED_ORIGIN !== '') {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if ($origin === ALLOWED_ORIGIN) {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Vary: Origin');
  }
}

// Bloqueia cross-site: se vier Origin e não for o permitido, rejeita.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && ALLOWED_ORIGIN !== '' && $origin !== ALLOWED_ORIGIN) {
  http_response_code(403);
  echo json_encode(['error' => 'origin_forbidden']);
  exit;
}

function json_out($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function json_in(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') return [];
  $len = strlen($raw);
  if ($len > MAX_BLOB_BYTES + 65536) {
    json_out(['error' => 'payload_too_large'], 413);
  }
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function bearer_token(): string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
  if ($h === '' && function_exists('apache_request_headers')) {
    $hs = apache_request_headers();
    $h = $hs['Authorization'] ?? $hs['authorization'] ?? '';
  }
  if (stripos($h, 'bearer ') === 0) return trim(substr($h, 7));
  return '';
}

// Purga lazy (sem cron no InfinityFree): apaga um punhado de sessões expiradas.
function lazy_purge(): void {
  try {
    db()->prepare('DELETE FROM sessions WHERE expires_at < ? LIMIT 50')->execute([time()]);
  } catch (Throwable $e) { /* silencioso: é só limpeza */ }
}

// Autentica o bearer e devolve ['account_id'=>int,'token_version'=>int]. 401 caso contrário.
function require_auth(): array {
  $token = bearer_token();
  if ($token === '') json_out(['error' => 'unauthorized'], 401);
  $sid = hash('sha256', $token);
  $st = db()->prepare(
    'SELECT s.account_id, s.token_version, s.expires_at, a.token_version AS live_version
       FROM sessions s JOIN accounts a ON a.id = s.account_id
      WHERE s.id = ?'
  );
  $st->execute([$sid]);
  $row = $st->fetch();
  if (!$row
      || (int)$row['expires_at'] < time()
      || (int)$row['token_version'] !== (int)$row['live_version']) {
    json_out(['error' => 'unauthorized'], 401);
  }
  lazy_purge();
  return ['account_id' => (int)$row['account_id']];
}

// IP do cliente (proxy partilhado do InfinityFree pode não ser o real — por isso
// o lockout por fingerprint do código é o controlo principal, não este).
function client_ip(): string {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  return (string)inet_pton($ip) ?: random_bytes(4);
}
