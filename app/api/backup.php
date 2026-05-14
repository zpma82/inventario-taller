<?php
// =============================================================
// INVENTARIO TALLER — Backup y restauración de base de datos
// NO incluir auth.php (ejecuta código en el include y responde
// con la sesión antes de que este script pueda actuar).
// =============================================================
require_once __DIR__ . '/config.php';
// config.php envía Content-Type: application/json — lo
// sobreescribimos justo antes de enviar el fichero.

// ── Helpers de autenticación (inline, sin include auth.php) ──
function obtenerTokenBackup(): ?string {
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'authorization') {
            return str_replace('Bearer ', '', trim($v));
        }
    }
    return $_GET['token'] ?? null;
}

function verificarAdminBackup(PDO $pdo): array {
    $token = obtenerTokenBackup();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    // Intentar primero tabla usuarios_app, luego usuarios (compatibilidad)
    foreach (['usuarios_app', 'usuarios'] as $tabla) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `$tabla` WHERE token = ? AND token_expira > NOW()");
            $stmt->execute([$token]);
            $u = $stmt->fetch();
            if ($u) {
                if (($u['rol'] ?? '') !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Solo los administradores pueden realizar copias de seguridad']);
                    exit;
                }
                return $u;
            }
        } catch (Exception $e) { /* tabla no existe, probar la siguiente */ }
    }
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido o expirado']);
    exit;
}

$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// ── EXPORTAR / BACKUP ────────────────────────────────────────
if ($method === 'GET' && $accion === 'exportar') {
    $usuario = verificarAdminBackup($pdo);

    try {
        $backup = [];

        $backup['__meta'] = [
            'version'     => '1.0',
            'fecha'       => date('Y-m-d H:i:s'),
            'base_datos'  => DB_NAME,
            'generado_por'=> $usuario['nombre'] ?? $usuario['usuario'] ?? 'admin',
        ];

        $tablas = [
            'categorias', 'subcategorias', 'ubicaciones',
            'equipos', 'especificaciones', 'movimientos',
            'empleados', 'usuarios_app',
        ];

        foreach ($tablas as $tabla) {
            try {
                $backup[$tabla] = $pdo->query("SELECT * FROM `$tabla`")->fetchAll();
            } catch (Exception $e) {
                $backup[$tabla] = [];   // tabla no existe en esta versión
            }
        }

        $backup['__config'] = [
            'db_host' => DB_HOST,
            'db_port' => DB_PORT,
            'db_name' => DB_NAME,
        ];

        $json     = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = 'backup_inventaller_' . date('Ymd_His') . '.json';

        // Sobreescribir TODOS los headers previos y forzar descarga
        header_remove();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

        echo $json;
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al generar backup: ' . $e->getMessage()]);
        exit;
    }
}

// ── IMPORTAR / RESTAURAR ─────────────────────────────────────
if ($method === 'POST' && $accion === 'importar') {
    $usuario = verificarAdminBackup($pdo);

    $body = json_decode(file_get_contents('php://input'), true);

    // Tablas reconocidas — basta con que el JSON tenga al menos una
    $tablas_sistema  = ['equipos','categorias','subcategorias','ubicaciones','movimientos','especificaciones','empleados','usuarios_app'];
    $tablas_en_json  = array_intersect($tablas_sistema, array_keys($body ?? []));

    if (!$body || (empty($body['__meta']) && count($tablas_en_json) === 0)) {
        http_response_code(400);
        echo json_encode(['error' => 'Fichero de backup inválido: no contiene tablas reconocidas de Inventario Taller']);
        exit;
    }

    // Inyectar __meta si no existe (backups de formato antiguo)
    if (empty($body['__meta'])) {
        $body['__meta'] = [
            'version'     => 'legacy',
            'fecha'       => '(desconocida)',
            'base_datos'  => DB_NAME,
            'generado_por'=> '(formato antiguo)',
        ];
    }

    $modo = $body['__modo'] ?? 'merge';

    $tablas_importables = [
        'categorias', 'subcategorias', 'ubicaciones',
        'equipos', 'especificaciones', 'movimientos', 'empleados',
        // usuarios_app se omite por seguridad
    ];

    $resultados = [];
    $errores    = [];

    try {
        $pdo->beginTransaction();

        foreach ($tablas_importables as $tabla) {
            if (!isset($body[$tabla]) || !is_array($body[$tabla])) {
                $resultados[$tabla] = ['omitida' => true];
                continue;
            }

            $filas = $body[$tabla];
            $ins = $act = $omit = 0;

            foreach ($filas as $fila) {
                if (empty($fila)) continue;
                try {
                    $cols         = array_keys($fila);
                    $colNames     = implode(', ', array_map(fn($c) => "`$c`", $cols));
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));

                    $sql  = ($modo === 'replace')
                        ? "REPLACE INTO `$tabla` ($colNames) VALUES ($placeholders)"
                        : "INSERT IGNORE INTO `$tabla` ($colNames) VALUES ($placeholders)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($fila));

                    $affected = $stmt->rowCount();
                    if ($modo === 'replace' && $affected === 2) { $act++; }
                    elseif ($affected > 0)                      { $ins++; }
                    else                                         { $omit++; }
                } catch (Exception $e) {
                    $errores[] = "[$tabla] " . $e->getMessage();
                    $omit++;
                }
            }

            $resultados[$tabla] = ['insertados' => $ins, 'actualizados' => $act, 'omitidos' => $omit];
        }

        $pdo->commit();

        responder([
            'ok'          => true,
            'modo'        => $modo,
            'resultados'  => $resultados,
            'errores'     => $errores,
            'meta_backup' => $body['__meta'],
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al importar: ' . $e->getMessage(), 'errores' => $errores]);
        exit;
    }
}

// ── Acción no reconocida ─────────────────────────────────────
http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida. Use ?accion=exportar o POST ?accion=importar']);
