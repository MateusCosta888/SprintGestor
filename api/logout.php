<?php
// logout.php — apaga a sessão corrente (não as outras do mesmo utilizador).
require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'method'], 405);
require_auth(); // garante token válido

$token = bearer_token();
$sid = hash('sha256', $token);
db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$sid]);

http_response_code(204);
