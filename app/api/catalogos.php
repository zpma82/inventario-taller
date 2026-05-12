<?php
// =============================================================
// INVENTARIO TALLER — API de catálogos
//
// GET  ?tabla=empleados|clientes|proveedores|categorias
// GET  ?tabla=subcategorias[&categoria_id=X]
// POST { accion:'crear_categoria', nombre }           → admin/tecnico
// POST { accion:'crear_subcategoria', nombre, categoria_id } → admin/tecnico
// POST { accion:'eliminar_categoria', id }            → admin/tecnico
// POST { accion:'eliminar_subcategoria', id }         → admin/tecnico
// =============================================================
require_once 'config.php';

$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $tabla = $_GET['tabla'] ?? '';
    $permitidas = ['empleados','clientes','proveedores','categorias','subcategorias'];
    if (!in_array($tabla, $permitidas)) {
        responder(['error' => 'Tabla no permitida'], 400);
    }
    if ($tabla === 'subcategorias') {
        $catId = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
        if ($catId) {
            $stmt = $pdo->prepare("SELECT id, nombre, categoria_id FROM subcategorias WHERE categoria_id = ? ORDER BY nombre");
            $stmt->execute([$catId]);
        } else {
            $stmt = $pdo->query("
                SELECT s.id, s.nombre, s.categoria_id, c.nombre AS categoria_nombre
                FROM subcategorias s
                JOIN categorias c ON c.id = s.categoria_id
                ORDER BY c.nombre, s.nombre
            ");
        }
        responder($stmt->fetchAll());
    }
    $stmt = $pdo->query("SELECT id, nombre FROM {$tabla} ORDER BY nombre");
    responder($stmt->fetchAll());
}

// ── POST (requiere autenticación admin/tecnico) ───────────────
if ($method === 'POST') {
    $d      = leerBody();
    $accion = $d['accion'] ?? '';

    // Verificar token y rol
    $token  = obtenerToken();
    $sesion = $token ? verificarToken($pdo, $token) : null;
    if (!$sesion) { responder(['error' => 'No autenticado'], 401); }
    if (!in_array($sesion['rol'], ['admin','tecnico'])) {
        responder(['error' => 'Solo admin y técnico pueden modificar categorías'], 403);
    }

    if ($accion === 'crear_categoria') {
        $nombre = trim($d['nombre'] ?? '');
        if (!$nombre) { responder(['error' => 'Nombre obligatorio'], 400); }
        try {
            $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)")->execute([$nombre]);
            $id = (int)$pdo->lastInsertId();
            responder(['id' => $id, 'nombre' => $nombre, 'mensaje' => 'Categoría creada'], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') responder(['error' => 'Esa categoría ya existe'], 409);
            responder(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    if ($accion === 'crear_subcategoria') {
        $nombre  = trim($d['nombre']       ?? '');
        $catId   = (int)($d['categoria_id'] ?? 0);
        if (!$nombre || !$catId) { responder(['error' => 'Nombre y categoría son obligatorios'], 400); }
        try {
            $pdo->prepare("INSERT INTO subcategorias (nombre, categoria_id) VALUES (?,?)")->execute([$nombre, $catId]);
            $id = (int)$pdo->lastInsertId();
            responder(['id' => $id, 'nombre' => $nombre, 'categoria_id' => $catId, 'mensaje' => 'Subcategoría creada'], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') responder(['error' => 'Esa subcategoría ya existe en esta categoría'], 409);
            responder(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    if ($accion === 'eliminar_categoria') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) { responder(['error' => 'ID obligatorio'], 400); }
        $pdo->prepare("DELETE FROM categorias WHERE id = ?")->execute([$id]);
        responder(['mensaje' => 'Categoría eliminada']);
    }

    if ($accion === 'eliminar_subcategoria') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) { responder(['error' => 'ID obligatorio'], 400); }
        $pdo->prepare("DELETE FROM subcategorias WHERE id = ?")->execute([$id]);
        responder(['mensaje' => 'Subcategoría eliminada']);
    }

    responder(['error' => 'Acción no reconocida'], 400);
}

responder(['error' => 'Método no permitido'], 405);

// ── Helpers (mismos que auth.php) ─────────────────────────────
function obtenerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['HTTP_X_AUTH_TOKEN']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';
    if (!$header && function_exists('getallheaders')) {
        $h = getallheaders();
        $header = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if ($header && preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
    return isset($_GET['token']) ? trim($_GET['token']) : null;
}

function verificarToken(PDO $pdo, string $token): ?array {
    $stmt = $pdo->prepare("
        SELECT s.usuario_id, u.usuario, u.nombre, u.rol
        FROM   sesiones s
        JOIN   usuarios u ON u.id = s.usuario_id
        WHERE  s.token = ? AND s.expira_en > NOW() AND u.activo = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}
