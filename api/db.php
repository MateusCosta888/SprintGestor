<?php
// db.php — singleton PDO. Real server-side prepares + utf8mb4 (emojis nos dados).
function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,   // prepared statements reais no servidor
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
      ]);
    } catch (PDOException $e) {
      // Não revelamos detalhes da BD ao cliente.
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['error' => 'db_unavailable']);
      exit;
    }
  }
  return $pdo;
}
