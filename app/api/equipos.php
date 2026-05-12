<?php
// =============================================================
// INVENTARIO IT v3 — API de equipos
// Cada equipo puede tener múltiples líneas en equipo_ubicaciones,
// cada una con su propio estado. La lista principal devuelve
// una "fila virtual" por cada combinación (equipo × estado).
// =============================================================
require_once 'config.php';

$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match ($method) {
    'GET'    => $id ? getUno($pdo, $id) : getTodos($pdo),
    'POST'   => crear($pdo),
    'PUT'    => actualizar($pdo, $id),
    'DELETE' => eliminar($pdo, $id),
    default  => responder(['error' => 'Método no permitido'], 405),
};

// -------------------------------------------------------------
// Lista todos los equipos. Por cada equipo genera una fila
// por cada estado distinto que tenga en equipo_ubicaciones.
// Si no tiene líneas, genera una única fila sin ubicación.
// -------------------------------------------------------------
function getTodos(PDO $pdo): void {
    // Datos base
    $equipos = $pdo->query("
        SELECT e.*,
               c.nombre  AS categoria,
               sc.nombre AS subcategoria,
               p.nombre  AS proveedor_nombre
        FROM   equipos e
        LEFT JOIN categorias    c  ON c.id  = e.categoria_id
        LEFT JOIN subcategorias sc ON sc.id = e.subcategoria_id
        LEFT JOIN proveedores   p  ON p.id  = e.proveedor_id
        ORDER  BY e.id DESC
    ")->fetchAll();

    // Líneas de ubicación con estado
    $ubics = $pdo->query("
        SELECT eu.equipo_id,
               eu.ubicacion_id,
               eu.cantidad,
               eu.estado,
               CONCAT(u.calle,'-',u.lado,'-',u.hueco,'-',u.altura) AS etiqueta
        FROM   equipo_ubicaciones eu
        JOIN   ubicaciones u ON u.id = eu.ubicacion_id
        ORDER  BY eu.equipo_id, eu.estado, eu.id
    ")->fetchAll();

    // Indexar por equipo_id
    $byEquipo = [];
    foreach ($ubics as $row) {
        $byEquipo[$row['equipo_id']][] = [
            'id'       => (int)$row['ubicacion_id'],
            'etiqueta' => $row['etiqueta'],
            'cantidad' => (int)$row['cantidad'],
            'estado'   => $row['estado'],
        ];
    }

    $filas = [];
    foreach ($equipos as $e) {
        $lines = $byEquipo[$e['id']] ?? [];

        if (empty($lines)) {
            // Sin ubicaciones: una sola fila con el estado del equipo
            $row = array_merge($e, [
                'ubicaciones'     => [],
                'ubicacion_label' => null,
                'ubicacion_id'    => null,
                'fila_estado'     => $e['estado'],
                'fila_cantidad'   => (int)$e['cantidad'],
                'fila_id'         => $e['id'] . '_' . $e['estado'],
            ]);
            $filas[] = $row;
            continue;
        }

        // Agrupar líneas por estado
        $porEstado = [];
        foreach ($lines as $l) {
            $porEstado[$l['estado']][] = $l;
        }

        foreach ($porEstado as $estado => $lineasEstado) {
            $cantEstado = array_sum(array_column($lineasEstado, 'cantidad'));
            $etiquetas  = array_unique(array_column($lineasEstado, 'etiqueta'));
            $label      = count($etiquetas) === 1
                ? $etiquetas[0]
                : implode(' · ', array_map(fn($l) => $l['etiqueta'].' ×'.$l['cantidad'], $lineasEstado));

            $row = array_merge($e, [
                'ubicaciones'     => $lineasEstado,
                'ubicacion_label' => $label,
                'ubicacion_id'    => (int)$lineasEstado[0]['id'],
                'estado'          => $estado,           // override con el estado de la fila
                'fila_estado'     => $estado,
                'fila_cantidad'   => $cantEstado,
                'fila_id'         => $e['id'] . '_' . $estado,
                'cantidad'        => $cantEstado,       // mostrar cant de esta fila
            ]);
            $filas[] = $row;
        }
    }

    responder($filas);
}

// -------------------------------------------------------------
function getUno(PDO $pdo, int $id): void {
    $stmt = $pdo->prepare("
        SELECT e.*,
               c.nombre  AS categoria,
               sc.nombre AS subcategoria,
               p.nombre  AS proveedor_nombre
        FROM   equipos e
        LEFT JOIN categorias    c  ON c.id  = e.categoria_id
        LEFT JOIN subcategorias sc ON sc.id = e.subcategoria_id
        LEFT JOIN proveedores   p  ON p.id  = e.proveedor_id
        WHERE  e.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { responder(['error' => 'Equipo no encontrado'], 404); return; }

    $ubics = $pdo->prepare("
        SELECT eu.ubicacion_id AS id, eu.cantidad, eu.estado,
               CONCAT(u.calle,'-',u.lado,'-',u.hueco,'-',u.altura) AS etiqueta
        FROM   equipo_ubicaciones eu
        JOIN   ubicaciones u ON u.id = eu.ubicacion_id
        WHERE  eu.equipo_id = ?
        ORDER  BY eu.estado, eu.id
    ");
    $ubics->execute([$id]);
    $lines = $ubics->fetchAll();

    $row['ubicaciones']     = $lines;
    $row['ubicacion_label'] = count($lines) === 1
        ? $lines[0]['etiqueta']
        : (count($lines) > 1 ? implode(' · ', array_column($lines, 'etiqueta')) : null);
    $row['ubicacion_id']    = count($lines) >= 1 ? (int)$lines[0]['id'] : null;

    responder($row);
}

// -------------------------------------------------------------
function crear(PDO $pdo): void {
    $d = leerBody();
    if (empty($d['nombre'])) { responder(['error' => 'El nombre es obligatorio'], 400); }

    $ubicLines = $d['ubicaciones'] ?? null;
    $ubicIdLeg = null;
    if ($ubicLines && count($ubicLines) > 0) {
        $ubicIdLeg = (int)$ubicLines[0]['id'];
    } elseif (!empty($d['ubicacion_id'])) {
        $ubicIdLeg = (int)$d['ubicacion_id'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO equipos
                (nombre,categoria_id,subcategoria_id,numero_serie,estado,ubicacion_id,cantidad,fecha_alta,notas,proveedor_id,especificaciones)
            VALUES
                (:nombre,:categoria_id,:subcategoria_id,:numero_serie,:estado,:ubicacion_id,:cantidad,:fecha_alta,:notas,:proveedor_id,:especificaciones)
        ")->execute([
            ':nombre'           => $d['nombre'],
            ':categoria_id'     => $d['categoria_id']     ?? null,
            ':subcategoria_id'  => $d['subcategoria_id']  ?? null,
            ':numero_serie'     => $d['numero_serie']     ?? null,
            ':estado'           => $d['estado']           ?? 'Activo',
            ':ubicacion_id'     => $ubicIdLeg,
            ':cantidad'         => $d['cantidad']         ?? 1,
            ':fecha_alta'       => $d['fecha_alta']       ?? date('Y-m-d'),
            ':notas'            => $d['notas']            ?? null,
            ':proveedor_id'     => $d['proveedor_id']     ?? null,
            ':especificaciones' => $d['especificaciones'] ?? null,
        ]);
        $newId = (int)$pdo->lastInsertId();
        guardarUbicaciones($pdo, $newId, $ubicLines, $d);
        $pdo->commit();
        responder(['id' => $newId, 'mensaje' => 'Equipo creado'], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(['error' => 'Error al crear: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
function actualizar(PDO $pdo, ?int $id): void {
    if (!$id) responder(['error' => 'ID requerido'], 400);
    $d = leerBody();

    $pdo->beginTransaction();
    try {
        if (!empty($d['_solo_ubicacion'])) {
            $uid  = $d['ubicacion_id'] ?? null;
            $q    = $pdo->prepare("SELECT cantidad, estado FROM equipos WHERE id = ?");
            $q->execute([$id]);
            $eq   = $q->fetch();
            $cant = (int)$eq['cantidad'];
            $est  = $eq['estado'];

            $pdo->prepare("UPDATE equipos SET ubicacion_id = ? WHERE id = ?")
                ->execute([$uid, $id]);
            $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id = ?")
                ->execute([$id]);
            if ($uid) {
                $pdo->prepare("INSERT INTO equipo_ubicaciones (equipo_id,ubicacion_id,cantidad,estado) VALUES (?,?,?,?)")
                    ->execute([$id, (int)$uid, $cant, $est]);
            }
            $pdo->commit();
            responder(['mensaje' => 'Ubicación actualizada']);
            return;
        }

        $ubicLines = $d['ubicaciones'] ?? null;
        $ubicIdLeg = null;
        if ($ubicLines && count($ubicLines) > 0) {
            $ubicIdLeg = (int)$ubicLines[0]['id'];
        } elseif (!empty($d['ubicacion_id'])) {
            $ubicIdLeg = (int)$d['ubicacion_id'];
        }

        $pdo->prepare("
            UPDATE equipos SET
                nombre=:nombre, categoria_id=:categoria_id, subcategoria_id=:subcategoria_id,
                numero_serie=:numero_serie, estado=:estado, ubicacion_id=:ubicacion_id,
                cantidad=:cantidad, fecha_alta=:fecha_alta, notas=:notas, proveedor_id=:proveedor_id,
                especificaciones=:especificaciones
            WHERE id=:id
        ")->execute([
            ':nombre'           => $d['nombre']           ?? '',
            ':categoria_id'     => $d['categoria_id']     ?? null,
            ':subcategoria_id'  => $d['subcategoria_id']  ?? null,
            ':numero_serie'     => $d['numero_serie']     ?? null,
            ':estado'           => $d['estado']           ?? 'Activo',
            ':ubicacion_id'     => $ubicIdLeg,
            ':cantidad'         => $d['cantidad']         ?? 1,
            ':fecha_alta'       => $d['fecha_alta']       ?? null,
            ':notas'            => $d['notas']            ?? null,
            ':proveedor_id'     => $d['proveedor_id']     ?? null,
            ':especificaciones' => $d['especificaciones'] ?? null,
            ':id'               => $id,
        ]);

        guardarUbicaciones($pdo, $id, $ubicLines, $d);
        $pdo->commit();
        responder(['mensaje' => 'Equipo actualizado']);
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
function eliminar(PDO $pdo, ?int $id): void {
    if (!$id) responder(['error' => 'ID requerido'], 400);
    $pdo->prepare("DELETE FROM equipos WHERE id = ?")->execute([$id]);
    responder(['mensaje' => 'Equipo eliminado']);
}

// -------------------------------------------------------------
function guardarUbicaciones(PDO $pdo, int $equipoId, ?array $lines, array $d): void {
    $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id = ?")
        ->execute([$equipoId]);

    $estadoDefault = $d['estado'] ?? 'Activo';

    if ($lines && count($lines) > 0) {
        $ins = $pdo->prepare(
            "INSERT INTO equipo_ubicaciones (equipo_id,ubicacion_id,cantidad,estado) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)"
        );
        foreach ($lines as $l) {
            $uid    = (int)($l['id'] ?? 0);
            $cant   = (int)($l['cantidad'] ?? 1);
            $est    = in_array($l['estado'] ?? '', ['Activo','En reparación','Baja'])
                      ? $l['estado'] : $estadoDefault;
            if ($uid > 0 && $cant > 0) $ins->execute([$equipoId, $uid, $cant, $est]);
        }
    } elseif (!empty($d['ubicacion_id'])) {
        $pdo->prepare(
            "INSERT INTO equipo_ubicaciones (equipo_id,ubicacion_id,cantidad,estado) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)"
        )->execute([$equipoId, (int)$d['ubicacion_id'], (int)($d['cantidad'] ?? 1), $estadoDefault]);
    }
}
