<?php
// login.php — verifica o código de acesso e emite uma sessão. Rate-limited.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reauth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'method'], 405);

$in  = json_in();
$raw = (string)($in['code'] ?? '');
if ($raw === '') json_out(['error' => 'missing_code'], 400);

$norm = normalize_code($raw);
if (strlen($norm) < CODE_LENGTH) json_out(['error' => 'invalid_code'], 401); // curto demais, nem consultamos

$ip   = client_ip();
$fpr  = fingerprint_code($norm);
$bucket = code_bucket($fpr);

// Lockout primeiro por código (load-bearing), depois por IP.
if (login_blocked($bucket, LOGIN_MAX_PER_CODE) || login_blocked($ip, LOGIN_MAX_PER_IP)) {
  json_out(['error' => 'too_many_attempts'], 429);
}

$st = db()->prepare('SELECT id, code_hash, token_version FROM accounts WHERE code_fingerprint = ? LIMIT 1');
$st->execute([$fpr]);
$acct = $st->fetch();

if (!$acct || !password_verify($norm, $acct['code_hash'])) {
  login_record($ip);
  login_record($bucket);
  // Mensagem genérica: não revelamos se o código existe.
  json_out(['error' => 'invalid_code'], 401);
}

// Sucesso: limpa o contador do bucket deste código.
$window = intdiv(time(), LOGIN_WINDOW) * LOGIN_WINDOW;
db()->prepare('DELETE FROM login_attempts WHERE ip = ? AND window_start = ?')->execute([$bucket, $window]);

$token = mint_session((int)$acct['id'], (int)$acct['token_version']);

$d = db()->prepare('SELECT data, rev FROM accounts WHERE id = ?');
$d->execute([$acct['id']]);
$row = $d->fetch();

json_out([
  'token' => $token,
  'data'  => $row['data'] !== null ? json_decode($row['data'], true) : null,
  'rev'   => (int)($row['rev'] ?? 0),
]);
