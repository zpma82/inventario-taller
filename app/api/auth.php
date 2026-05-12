<?php
// =============================================================
// INVENTARIO IT — API de autenticación y gestión de usuarios
//
// POST { accion:'login',    usuario, password }          → token
// GET  Authorization: Bearer <token>                     → sesión activa
// POST { accion:'logout' }                               → cierra sesión
// POST { accion:'cambiar_password', password_actual, password_nueva }
// POST { accion:'listar_usuarios' }                      → admin/tecnico
// POST { accion:'listar_empleados_sin_usuario' }         → admin
// POST { accion:'crear_usuario', ... }                   → solo admin
// POST { accion:'editar_usuario', ... }                  → solo admin
// POST { accion:'eliminar_usuario', id }                 → solo admin
// =============================================================
require_once 'config.php';

$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: verificar token ─────────────────────────────────────
if ($method === 'GET') {
    $token  = obtenerToken();
    if (!$token) { responder(['valido' => false, 'error' => 'Sin token'], 401); }
    $sesion = verificarToken($pdo, $token);
    if (!$sesion) { responder(['valido' => false, 'error' => 'Token inválido o expirado'], 401); }
    responder([
        'valido'      => true,
        'usuario'     => $sesion['usuario'],
        'nombre'      => $sesion['nombre'],
        'rol'         => $sesion['rol'],
        'empleado_id' => $sesion['empleado_id'] ?? null,
    ]);
}

if ($method !== 'POST') { responder(['error' => 'Método no permitido'], 405); }

$d      = leerBody();
$accion = $d['accion'] ?? 'login';

// ── LOGIN ────────────────────────────────────────────────────
if ($accion === 'login') {
    $user = trim($d['usuario']  ?? '');
    $pass = $d['password'] ?? '';
    if (!$user || !$pass) { responder(['error' => 'Usuario y contraseña requeridos'], 400); }

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->execute([$user]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pass, $u['password_hash'])) {
        responder(['error' => 'Usuario o contraseña incorrectos'], 401);
    }

    // Limpiar sesiones expiradas del usuario
    $pdo->prepare("DELETE FROM sesiones WHERE usuario_id = ? AND expira_en < NOW()")
        ->execute([$u['id']]);

    $token  = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+8 hours'));
    $pdo->prepare("INSERT INTO sesiones (usuario_id, token, expira_en) VALUES (?,?,?)")
        ->execute([$u['id'], $token, $expira]);
    $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
        ->execute([$u['id']]);

    responder([
        'token'       => $token,
        'usuario'     => $u['usuario'],
        'nombre'      => $u['nombre'],
        'rol'         => $u['rol'],
        'empleado_id' => $u['empleado_id'] ?? null,
        'expira'      => $expira,
    ]);
}

// ── LOGOUT ───────────────────────────────────────────────────
if ($accion === 'logout') {
    $token = obtenerToken();
    if ($token) {
        $pdo->prepare("DELETE FROM sesiones WHERE token = ?")->execute([$token]);
    }
    responder(['mensaje' => 'Sesión cerrada']);
}

// ── Las siguientes acciones requieren autenticación ──────────
$token  = obtenerToken();
$sesion = $token ? verificarToken($pdo, $token) : null;
if (!$sesion) { responder(['error' => 'No autenticado'], 401); }

$esAdmin   = $sesion['rol'] === 'admin';
$esTecnico = in_array($sesion['rol'], ['admin', 'tecnico']);

// ── CAMBIAR CONTRASEÑA PROPIA ────────────────────────────────
if ($accion === 'cambiar_password') {
    $actual = $d['password_actual'] ?? '';
    $nueva  = $d['password_nueva']  ?? '';
    if (!$actual || !$nueva)     { responder(['error' => 'Faltan campos'], 400); }
    if (strlen($nueva) < 4)      { responder(['error' => 'La contraseña debe tener al menos 4 caracteres'], 400); }

    $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id = ?");
    $stmt->execute([$sesion['usuario_id']]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($actual, $u['password_hash'])) {
        responder(['error' => 'La contraseña actual no es correcta'], 401);
    }
    $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($nueva, PASSWORD_BCRYPT), $sesion['usuario_id']]);
    responder(['mensaje' => 'Contraseña actualizada correctamente']);
}

// ── LISTAR USUARIOS ──────────────────────────────────────────
// Accesible para admin y tecnico (lectura)
if ($accion === 'listar_usuarios') {
    if (!$esTecnico) { responder(['error' => 'Sin permisos'], 403); }
    $stmt = $pdo->query("
        SELECT u.id, u.nombre, u.usuario, u.rol, u.activo,
               u.creado_en, u.ultimo_acceso,
               e.nombre AS empleado_nombre
        FROM usuarios u
        LEFT JOIN empleados e ON e.id = u.empleado_id
        ORDER BY FIELD(u.rol,'admin','tecnico','usuario','invitado'), u.nombre
    ");
    responder($stmt->fetchAll());
}

// ── LISTAR TODOS LOS EMPLEADOS con info de si ya tienen usuario ─
if ($accion === 'listar_empleados_sin_usuario') {
    if (!$esAdmin) { responder(['error' => 'Sin permisos'], 403); }
    $stmt = $pdo->query("
        SELECT e.id, e.nombre, e.puesto,
               u.usuario AS usuario_actual,
               u.rol     AS rol_actual
        FROM empleados e
        LEFT JOIN usuarios u ON u.empleado_id = e.id
        ORDER BY e.nombre
    ");
    responder($stmt->fetchAll());
}

// ── Las siguientes acciones son solo para ADMIN ──────────────
if (!$esAdmin) { responder(['error' => 'Acción reservada para administradores'], 403); }

// ── CREAR USUARIO (solo de operarios/empleados) ──────────────
if ($accion === 'crear_usuario') {
    $nombre    = trim($d['nombre']    ?? '');
    $usuario2  = trim($d['usuario2']  ?? '');
    $pass      = $d['password']       ?? '';
    $rol       = $d['rol']            ?? 'usuario';
    $empId     = $d['empleado_id']    ?? null;

    if (!$nombre || !$usuario2 || !$pass) {
        responder(['error' => 'Nombre, usuario y contraseña son obligatorios'], 400);
    }
    if (strlen($pass) < 4) {
        responder(['error' => 'La contraseña debe tener al menos 4 caracteres'], 400);
    }
    if (!in_array($rol, ['admin','tecnico','usuario','invitado'])) {
        responder(['error' => 'Rol inválido'], 400);
    }

    // Si se vincula a un empleado, verificar que ese empleado existe
    // y que no tiene ya un usuario asignado
    if ($empId) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empleado_id = ?");
        $chk->execute([(int)$empId]);
        if ((int)$chk->fetchColumn() > 0) {
            responder(['error' => 'Ese operario ya tiene un usuario asignado'], 409);
        }
    }

    try {
        $pdo->prepare("
            INSERT INTO usuarios (nombre, usuario, password_hash, rol, empleado_id)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $nombre,
            $usuario2,
            password_hash($pass, PASSWORD_BCRYPT),
            $rol,
            $empId ? (int)$empId : null,
        ]);
        responder(['id' => (int)$pdo->lastInsertId(), 'mensaje' => 'Usuario creado correctamente'], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            responder(['error' => 'El nombre de usuario ya existe'], 409);
        }
        responder(['error' => 'Error al crear usuario: ' . $e->getMessage()], 500);
    }
}

// ── EDITAR USUARIO (cambiar rol, activar/desactivar, reset pass) ──
if ($accion === 'editar_usuario') {
    $uid    = (int)($d['id']     ?? 0);
    $nombre = trim($d['nombre']  ?? '');
    $rol    = $d['rol']          ?? null;
    $activo = isset($d['activo']) ? (int)$d['activo'] : null;

    if (!$uid || !$nombre) { responder(['error' => 'ID y nombre requeridos'], 400); }
    if ($uid === (int)$sesion['usuario_id']) {
        responder(['error' => 'No puedes editar tu propio usuario desde aquí'], 400);
    }

    // Construir UPDATE dinámico según campos enviados
    $sets  = ['nombre = ?'];
    $vals  = [$nombre];

    if ($rol && in_array($rol, ['admin','tecnico','usuario','invitado'])) {
        $sets[] = 'rol = ?'; $vals[] = $rol;
    }
    if ($activo !== null) {
        $sets[] = 'activo = ?'; $vals[] = $activo;
    }
    // Reset de contraseña opcional
    if (!empty($d['password_nueva']) && strlen($d['password_nueva']) >= 4) {
        $sets[] = 'password_hash = ?';
        $vals[] = password_hash($d['password_nueva'], PASSWORD_BCRYPT);
    }

    $vals[] = $uid;
    $pdo->prepare("UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = ?")
        ->execute($vals);

    responder(['mensaje' => 'Usuario actualizado correctamente']);
}

// ── ELIMINAR USUARIO ─────────────────────────────────────────
if ($accion === 'eliminar_usuario') {
    $uid = (int)($d['id'] ?? 0);
    if (!$uid) { responder(['error' => 'ID requerido'], 400); }
    if ($uid === (int)$sesion['usuario_id']) {
        responder(['error' => 'No puedes eliminar tu propio usuario'], 400);
    }
    $pdo->prepare("DELETE FROM sesiones WHERE usuario_id = ?")->execute([$uid]);
    $pdo->prepare("DELETE FROM usuarios  WHERE id = ?")->execute([$uid]);
    responder(['mensaje' => 'Usuario eliminado correctamente']);
}

responder(['error' => 'Acción no reconocida'], 400);

// ── Helpers ──────────────────────────────────────────────────
function obtenerToken(): ?string {
    // Apache a veces no pasa Authorization a PHP directamente.
    // Se prueban todas las variantes conocidas.
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['HTTP_X_AUTH_TOKEN']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';

    // Fallback: leer de getallheaders() si está disponible
    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($header && preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }

    // Último recurso: parámetro GET o body JSON
    if (isset($_GET['token'])) return trim($_GET['token']);

    return null;
}

function verificarToken(PDO $pdo, string $token): ?array {
    $stmt = $pdo->prepare("
        SELECT s.usuario_id, u.usuario, u.nombre, u.rol, u.empleado_id
        FROM   sesiones s
        JOIN   usuarios u ON u.id = s.usuario_id
        WHERE  s.token     = ?
          AND  s.expira_en > NOW()
          AND  u.activo    = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}
