<?php
// regenerate.php — emite um NOVO código a partir de uma sessão ativa.
// Rota token_version: mata todas as sessões antigas (código/token roubados ficam mortos).
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reauth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'method'], 405);
$auth = require_auth();

$code = generate_code();
$norm = normalize_code($code);
$hash = hash_code($norm);
$fpr  = fingerprint_code($norm);

$st = db()->prepare('UPDATE accounts SET code_hash = ?, code_fingerprint = ?, token_version = token_version + 1 WHERE id = ?');
$st->execute([$hash, $fpr, $auth['account_id']]);

// Versão nova = live_version + 1; sessões antigas (com version antiga) deixam de validar.
$ver = db()->prepare('SELECT token_version FROM accounts WHERE id = ?');
$ver->execute([$auth['account_id']]);
$version = (int)$ver->fetch()['token_version'];

// Remove todas as sessões desta conta e emite uma nova para o dispositivo atual.
db()->prepare('DELETE FROM sessions WHERE account_id = ?')->execute([$auth['account_id']]);
$token = mint_session($auth['account_id'], $version);

json_out(['code' => format_code($code), 'token' => $token]);
