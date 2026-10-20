<?php
// reauth.php — lógica partilhada por register/login/regenerate: geração de código,
// normalização, hash bcrypt + fingerprint HMAC, e emissão de sessão. Um só caminho
// para não divergir.

// Normaliza entrada do utilizador antes de verificar: maiúsculas e apenas caracteres
// válidos do alfabeto (descarta espaços, traços e qualquer letra/dígito ambíguo que
// alguém possa ter reescrito à mão, ex.: O/0/I/1/L).
function normalize_code(string $raw): string {
  $s = strtoupper($raw);
  $valid = '';
  for ($i = 0, $n = strlen($s); $i < $n; $i++) {
    if (strpos(CODE_ALPHABET, $s[$i]) !== false) $valid .= $s[$i];
  }
  return $valid;
}

// Gera um código de CODE_LENGTH caracteres do alfabeto, com entropia de random_bytes.
function generate_code(int $len = CODE_LENGTH): string {
  $alpha = CODE_ALPHABET;
  $n = strlen($alpha);               // 24 (não potência de 2 → rejection sampling sem viés)
  $out = '';
  while (strlen($out) < $len) {
    $bytes = random_bytes($len * 2);
    for ($i = 0; $i < strlen($bytes) && strlen($out) < $len; $i++) {
      $b = ord($bytes[$i]);
      if ($b < 240) {                // 240 = 10*24 → múltiplo do alfabeto, logo uniforme
        $out .= $alpha[$b % $n];
      }
    }
  }
  return $out;
}

function format_code(string $code): string {
  return implode('-', str_split($code, 4));
}

function hash_code(string $normalized): string {
  return password_hash($normalized, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function fingerprint_code(string $normalized): string {
  return hash_hmac('sha256', $normalized, PEPPER);
}

// Emite uma sessão: token aleatório de 256 bits; guardamos só sha256(token).
// Devolve o token em claro (uma vez) para o cliente.
function mint_session(int $accountId, int $tokenVersion): string {
  $token = bin2hex(random_bytes(32));
  $sid = hash('sha256', $token);
  $expires = time() + SESSION_TTL;
  $st = db()->prepare(
    'INSERT INTO sessions (id, account_id, token_version, expires_at) VALUES (?, ?, ?, ?)'
  );
  $st->execute([$sid, $accountId, $tokenVersion, $expires]);
  return $token;
}

// Rate-limit de login: janela fixa sobre uma chave empacotada (IP real ou code_bucket).
function login_blocked(string $packed, int $max): bool {
  $window = intdiv(time(), LOGIN_WINDOW) * LOGIN_WINDOW;
  $st = db()->prepare(
    'SELECT attempts FROM login_attempts WHERE ip = ? AND window_start = ?'
  );
  $st->execute([$packed, $window]);
  $row = $st->fetch();
  if ($row && (int)$row['attempts'] >= $max) return true;
  return false;
}

function login_record(string $ipPacked): void {
  $window = intdiv(time(), LOGIN_WINDOW) * LOGIN_WINDOW;
  $st = db()->prepare(
    'INSERT INTO login_attempts (ip, window_start, attempts) VALUES (?, ?, 1)
       ON DUPLICATE KEY UPDATE attempts = attempts + 1'
  );
  $st->execute([$ipPacked, $window]);
}

// Lockout por fingerprint de código: usa a mesma tabela mas com uma chave derivada do
// fingerprint (empacotada como IPv6 sintética) — assim um atacante distribuído por IPs
// continua a bater no limite por código. É o controlo load-bearing.
function code_bucket(string $fingerprint): string {
  // 16 bytes: 4 bytes de marcador + 12 primeiros do fingerprint.
  return "\x00\x00\x00\x01" . substr(hex2bin($fingerprint), 0, 12);
}
