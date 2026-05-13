<?php
// =============================================================
// INVENTARIO TALLER — Backup y restauración de base de datos
// =============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Verificar autenticación (solo admin)
$token = null;
$headers = getallheaders();
foreach ($headers as $k => $v) {
    if (strtolower($k) === 'authorization') {
        $token = str_replace('Bearer ', '', $v);
        break;
}
}
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM usuarios_app WHERE token = ? AND token_expira > NOW()");
$stmt->execute([$token]);
$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido o expirado']);
    exit;
}
if ($usuario['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo los administradores pueden realizar copias de seguridad']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// ── EXPORTAR / BACKUP ────────────────────────────────────────
if ($method === 'GET' && $accion === 'exportar') {
    try {
        $pdo = getPDO();
        $backup = [];

        // Metadatos
        $backup['__meta'] = [
            'version'    => '1.0',
            'fecha'      => date('Y-m-d H:i:s'),
            'base_datos' => DB_NAME,
            'generado_por' => $usuario['nombre'] ?? $usuario['usuario'],
        ];

        // Configuración de la base de datos: tablas a exportar
        $tablas = [
            'categorias',
            'subcategorias',
            'ubicaciones',
            'equipos',
            'especificaciones',
            'movimientos',
            'empleados',
            'usuarios_app',
        ];

        foreach ($tablas as $tabla) {
            try {
                $rows = $pdo->query("SELECT * FROM `$tabla`")->fetchAll();
                $backup[$tabla] = $rows;
            } catch (Exception $e) {
                // La tabla puede no existir en todas las versiones
                $backup[$tabla] = [];
            }
        }

        // Configuración de la BD (variables de entorno NO se incluyen por seguridad)
        $backup['__config'] = [
            'db_host' => DB_HOST,
            'db_port' => DB_PORT,
            'db_name' => DB_NAME,
        ];

        $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = 'backup_inventaller_' . date('Ymd_His') . '.json';

        // Sobrescribir headers ya enviados por config.php
        header('Content-Type: application/json; charset=utf-8', true);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('Access-Control-Allow-Origin: *');
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
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['__meta'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Fichero de backup inválido o corrupto']);
        exit;
    }

    $modo = $body['__modo'] ?? 'merge'; // 'merge' o 'replace'

    // Tablas importables (excluimos usuarios por seguridad)
    $tablas_importables = [
        'categorias'      => ['id', 'nombre'],
        'subcategorias'   => ['id', 'nombre', 'categoria_id'],
        'ubicaciones'     => ['id', 'calle', 'lado', 'hueco', 'altura', 'label'],
        'equipos'         => null, // importar tal cual
        'especificaciones'=> null,
        'movimientos'     => null,
        'empleados'       => null,
    ];

    $pdo = getPDO();
    $resultados = [];
    $errores    = [];

    try {
        $pdo->beginTransaction();

        foreach ($tablas_importables as $tabla => $campos) {
            if (!isset($body[$tabla]) || !is_array($body[$tabla])) {
                $resultados[$tabla] = ['omitida' => true];
                continue;
            }

            $filas  = $body[$tabla];
            $ins    = 0;
            $act    = 0;
            $omit   = 0;

            foreach ($filas as $fila) {
                if (empty($fila)) continue;

                // Obtener columnas reales de la tabla
                try {
                    $cols = array_keys($fila);
                    // Verificar que la fila tiene columnas válidas
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                    $colNames     = implode(', ', array_map(fn($c) => "`$c`", $cols));

                    if ($modo === 'replace') {
                        // REPLACE INTO: sobreescribe si existe el mismo PK
                        $sql = "REPLACE INTO `$tabla` ($colNames) VALUES ($placeholders)";
                    } else {
                        // INSERT IGNORE: si ya existe el PK, lo omite
                        $sql = "INSERT IGNORE INTO `$tabla` ($colNames) VALUES ($placeholders)";
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($fila));

                    $affected = $stmt->rowCount();
                    if ($modo === 'replace' && $affected === 2) {
                        $act++;
                    } elseif ($affected > 0) {
                        $ins++;
                    } else {
                        $omit++;
                    }
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

// ── Acción no reconocida ──────────────────────────────────────
http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida. Use ?accion=exportar o POST ?accion=importar']);
