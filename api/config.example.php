<?php
// config.example.php — template de api/config.php.
// Copia para api/config.php e preenche com as tuas credenciais reais.
// O api/config.php verdadeiro está no .gitignore e nunca deve ser commitado.

// ---- Base de dados (VistaPanel > MySQL Databases) ----
define('DB_HOST', 'SEU_MYSQL_HOST');
define('DB_NAME', 'SEU_BANCO');
define('DB_USER', 'SEU_UTILIZADOR');
define('DB_PASS', 'SUA_PASSWORD');

// ---- Segredo para o fingerprint do código (NUNCA partilhado nem no repo) ----
// Gera com: php -r "echo bin2hex(random_bytes(32));"
define('PEPPER', 'GERA_UM_VALOR_ALEATORIO_LONGO');

// ---- Política de autenticação ----
define('CODE_ALPHABET', '23456789ABCDEFGHJKMNPQRS');
define('CODE_LENGTH', 20);
define('BCRYPT_COST', 10);
define('SESSION_TTL', 60 * 60 * 24 * 30);
define('MAX_BLOB_BYTES', 2 * 1024 * 1024);

// ---- Rate limiting de login ----
define('LOGIN_WINDOW', 15 * 60);
define('LOGIN_MAX_PER_IP', 12);
define('LOGIN_MAX_PER_CODE', 6);

// ---- CORS / origem ----
define('ALLOWED_ORIGIN', ''); // '' = same-origin only

date_default_timezone_set('UTC');
