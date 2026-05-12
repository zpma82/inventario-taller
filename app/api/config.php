<?php
// =============================================================
// INVENTARIO TALLER — Configuración de conexión
// En Docker: lee variables de entorno inyectadas por Compose.
// En local:  usa los valores por defecto como fallback.
// =============================================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'inventaller');
define('DB_USER', getenv('DB_USER') ?: 'almacen_local');
define('DB_PASS', getenv('DB_PASS') ?: 'CambiaEstaPassword_Local1!');
define('DB_CHAR', 'utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function getPDO(): PDO {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHAR);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Forzar charset utf8mb4 y desactivar STRICT_TRANS_TABLES
            // STRICT convierte Warning 1265 en error fatal que aborta la transacción
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'",
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
}

function responder(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function leerBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
