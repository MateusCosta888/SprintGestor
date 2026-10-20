<?php
// ai_manage.php — gestão do link de Acesso de IA (sessão do dono, bearer).
// generate: cria token (409 se já existir) · regenerate: substitui · revoke: mata.
// O token em claro sai UMA vez, nesta resposta; na BD vive apenas o sha256.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reauth.php';
require_once __DIR__ . '/ai_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'method'], 405);
$auth = require_auth();
$in = json_in();
$action = (string)($in['action'] ?? '');
$aid = $auth['account_id'];
$pdo = db();

$cur = $pdo->prepare('SELECT agent_hash FROM accounts WHERE id = ?');
$cur->execute([$aid]);
$has = $cur->fetchColumn() ? true : false;

if ($action === 'generate' && $has) {
  json_out(['ok' => false, 'error' => 'already_active', 'hint' => 'revoke or regenerate first'], 409);
}
if ($action === 'revoke') {
  $pdo->prepare('UPDATE accounts SET agent_hash = NULL, agent_created_at = NULL WHERE id = ?')->execute([$aid]);
  json_out(['ok' => true]);
}
if ($action === 'generate' || $action === 'regenerate') {
  $token = generate_code(AI_TOKEN_LEN);
  $hash = hash('sha256', $token);
  $pdo->prepare('UPDATE accounts SET agent_hash = ?, agent_created_at = NOW() WHERE id = ?')->execute([$hash, $aid]);
  $scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
  $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/ai/' . $token;
  json_out(['ok' => true, 'token' => $token, 'url' => $url]);
}
json_out(['ok' => false, 'error' => 'bad_payload', 'hint' => 'action must be generate|regenerate|revoke'], 400);
