<?php
// register.php — cria conta e devolve o código de acesso UMA vez + token de sessão.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reauth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'method'], 405);

$code = generate_code();                 // já só contém caracteres do alfabeto
$norm = normalize_code($code);           // no-op defensivo
$hash = hash_code($norm);
$fpr  = fingerprint_code($norm);

$st = db()->prepare('INSERT INTO accounts (code_hash, code_fingerprint) VALUES (?, ?)');
$st->execute([$hash, $fpr]);
$accountId = (int)db()->lastInsertId();

$token = mint_session($accountId, 1);

// O código em claro só existe nesta resposta. Não é guardado nem registado.
json_out([
  'code'  => format_code($code),
  'token' => $token,
  'data'  => null,
  'rev'   => 0,
]);
