<?php
// =============================================================
// DIAGNÓSTICO TEMPORAL — borrar tras confirmar que funciona
// Acceso: http://<IP>:8085/api/diagnostico.php
// =============================================================
require_once 'config.php';

$rawBody = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

$info = [
    'php_version'    => PHP_VERSION,
    'method'         => $_SERVER['REQUEST_METHOD'],
    'content_type'   => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'no detectado',
    'authorization'  => isset($headers['Authorization']) ? 'PRESENTE' : (isset($headers['authorization']) ? 'presente (lowercase)' : 'AUSENTE'),
    'raw_body'       => $rawBody ?: '(vacío)',
    'json_decode_ok' => is_array(json_decode($rawBody, true)),
    'POST'           => $_POST,
    'auth_php_version' => file_exists(__DIR__.'/auth.php')
        ? trim(shell_exec('grep -c "crear_operario" '.escapeshellarg(__DIR__.'/auth.php'))??'?').' ocurrencias de crear_operario en auth.php'
        : 'auth.php no encontrado',
    'archivo_modif'  => date('Y-m-d H:i:s', filemtime(__DIR__.'/auth.php')),
];

responder($info);
