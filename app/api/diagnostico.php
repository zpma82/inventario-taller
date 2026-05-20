<?php
// =============================================================
// DIAGNÓSTICO — borrar tras confirmar que funciona
// GET  → info del servidor
// POST → muestra exactamente lo que recibe (simula auth.php)
// =============================================================
require_once 'config.php';

$rawBody = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Intentar parsear igual que auth.php
$d = json_decode($rawBody, true);
if (!is_array($d) || empty($d)) { parse_str($rawBody, $d); }
if (!is_array($d)) { $d = []; }
if (!empty($_POST)) { $d = array_merge($_POST, $d); }

// Verificar token igual que auth.php
function obtenerTokenDiag(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $header  = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_AUTH_TOKEN']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if ($header && preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
    return $_GET['token'] ?? null;
}

$token = obtenerTokenDiag();
$pdo   = getPDO();
$sesion = null;
$tokenError = '';
if ($token) {
    $stmt = $pdo->prepare("
        SELECT s.usuario_id, u.usuario, u.nombre, u.rol
        FROM   sesiones s
        JOIN   usuarios u ON u.id = s.usuario_id
        WHERE  s.token = ? AND s.expira_en > NOW() AND u.activo = 1
    ");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch() ?: null;
    if (!$sesion) $tokenError = 'Token no encontrado o expirado en BD';
} else {
    $tokenError = 'No se recibió ningún token';
}

responder([
    'método'          => $_SERVER['REQUEST_METHOD'],
    'content_type'    => $_SERVER['CONTENT_TYPE'] ?? $headers['Content-Type'] ?? 'ausente',
    'token_recibido'  => $token ? substr($token,0,12).'...' : 'NINGUNO',
    'token_válido'    => $sesion ? true : false,
    'token_error'     => $tokenError ?: null,
    'rol_usuario'     => $sesion['rol'] ?? null,
    'es_admin'        => ($sesion['rol'] ?? '') === 'admin',
    'raw_body'        => $rawBody ?: '(vacío)',
    'accion_leída'    => $d['accion'] ?? '(no detectada — d vacío)',
    'd_completo'      => $d,
    'json_decode_ok'  => is_array(json_decode($rawBody, true)),
    'php_version'     => PHP_VERSION,
    'auth_modif'      => date('Y-m-d H:i:s', filemtime(__DIR__.'/auth.php')),
]);
