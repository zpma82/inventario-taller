<?php
// =============================================================
// INVENTARIO IT — API de movimientos
// GET  /api/movimientos.php?equipo_id=X  → historial de un equipo
// GET  /api/movimientos.php              → últimos 200 movimientos
// POST /api/movimientos.php              → registrar movimiento
//      tipo='separacion' + lineas[]      → distribuir en varias ubicaciones
// =============================================================
require_once 'config.php';

$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $equipo_id = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;

    $sql = "
        SELECT m.*,
               e.nombre  AS empleado,
               c.nombre  AS cliente,
               p.nombre  AS proveedor,
               CONCAT(uo.calle,'-',uo.lado,'-',uo.hueco,'-',uo.altura) AS ubicacion_origen,
               CONCAT(u.calle,'-',u.lado,'-',u.hueco,'-',u.altura)     AS ubicacion_nueva
        FROM   movimientos m
        LEFT JOIN empleados   e  ON e.id  = m.empleado_id
        LEFT JOIN clientes    c  ON c.id  = m.cliente_id
        LEFT JOIN proveedores p  ON p.id  = m.proveedor_id
        LEFT JOIN ubicaciones uo ON uo.id = m.ubicacion_origen_id
        LEFT JOIN ubicaciones u  ON u.id  = m.ubicacion_id
    ";
    if ($equipo_id) {
        $stmt = $pdo->prepare($sql . " WHERE m.equipo_id = ? ORDER BY m.fecha DESC");
        $stmt->execute([$equipo_id]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY m.fecha DESC LIMIT 200");
    }
    responder($stmt->fetchAll());

} elseif ($method === 'POST') {
    $d         = leerBody();
    $equipo_id = (int)($d['equipo_id'] ?? 0);
    $tipo      = $d['tipo'] ?? '';

    if (!$equipo_id) {
        responder(['error' => 'equipo_id requerido'], 400);
    }

    // ── Tipo especial: separación en múltiples ubicaciones ──────
    if ($tipo === 'separacion') {
        $lineas      = $d['lineas'] ?? [];
        $filaEstado  = $d['fila_estado'] ?? null;   // estado de la línea origen que se divide

        if (empty($lineas)) {
            responder(['error' => 'Debes indicar al menos una línea de ubicación'], 400);
        }

        // Stock de la línea origen (equipo + estado concreto)
        if ($filaEstado) {
            $q = $pdo->prepare("
                SELECT COALESCE(SUM(eu.cantidad), 0)
                FROM equipo_ubicaciones eu
                WHERE eu.equipo_id = ? AND eu.estado = ?
            ");
            $q->execute([$equipo_id, $filaEstado]);
        } else {
            // Fallback: stock total del equipo
            $q = $pdo->prepare("SELECT cantidad FROM equipos WHERE id = ?");
            $q->execute([$equipo_id]);
        }
        $stock = (int)$q->fetchColumn();

        $suma = array_sum(array_column($lineas, 'cantidad'));
        if ($suma !== $stock) {
            responder(['error' => "La suma de unidades ({$suma}) no coincide con el stock de esta línea ({$stock})"], 409);
        }

        $pdo->beginTransaction();
        try {
            // Capturar ubicación origen ANTES de borrar
            $qOrigSep = $filaEstado
                ? $pdo->prepare("SELECT ubicacion_id FROM equipo_ubicaciones WHERE equipo_id=? AND estado=? ORDER BY id LIMIT 1")
                : $pdo->prepare("SELECT ubicacion_id FROM equipos WHERE id=?");
            $filaEstado
                ? $qOrigSep->execute([$equipo_id, $filaEstado])
                : $qOrigSep->execute([$equipo_id]);
            $ubicOrigenSep = $qOrigSep->fetchColumn() ?: null;

            // Eliminar SOLO las líneas del estado origen de este equipo
            if ($filaEstado) {
                $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id = ? AND estado = ?")
                    ->execute([$equipo_id, $filaEstado]);
            } else {
                $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id = ?")
                    ->execute([$equipo_id]);
            }

            // Insertar cada nueva línea. Si ya existe (misma ubic+estado) sumar cantidad
            // VALUES() deprecado en MySQL 8.0.20+; usamos alias de fila
            $ins = $pdo->prepare("
                INSERT INTO equipo_ubicaciones (equipo_id, ubicacion_id, cantidad, estado)
                VALUES (?,?,?,?) AS nueva
                ON DUPLICATE KEY UPDATE cantidad = equipo_ubicaciones.cantidad + nueva.cantidad
            ");
            // Mapa explícito: tolerante a NFD/NFC y mayúsculas
            $mapaEstados = [
                'activo'        => 'Activo',
                'en reparación' => 'En reparación',
                'en reparacion' => 'En reparación',
                'baja'          => 'Baja',
            ];
            foreach ($lineas as $l) {
                $uid  = (int)($l['id'] ?? 0);
                $cant = (int)($l['cantidad'] ?? 0);
                $estRaw  = trim((string)($l['estado'] ?? 'Activo'));
                $estNorm = $mapaEstados[mb_strtolower($estRaw, 'UTF-8')] ?? null;
                if ($estNorm === null) {
                    throw new Exception("Estado inválido: '{$estRaw}'. Permitidos: Activo, En reparación, Baja");
                }
                if ($uid > 0 && $cant > 0) $ins->execute([$equipo_id, $uid, $cant, $estNorm]);
            }

            // Recalcular cantidad total del equipo y ubicacion_id legacy
            $pdo->prepare("
                UPDATE equipos
                SET cantidad     = (SELECT COALESCE(SUM(cantidad),0) FROM equipo_ubicaciones WHERE equipo_id = ?),
                    ubicacion_id = (SELECT ubicacion_id FROM equipo_ubicaciones WHERE equipo_id = ? ORDER BY id LIMIT 1)
                WHERE id = ?
            ")->execute([$equipo_id, $equipo_id, $equipo_id]);

            // Historial
            $primeraUbic = (int)($lineas[0]['id'] ?? 0);
            $pdo->prepare("
                INSERT INTO movimientos (equipo_id, empleado_id, tipo, cantidad, ubicacion_origen_id, ubicacion_id)
                VALUES (?, ?, 'ubicacion', 0, ?, ?)
            ")->execute([$equipo_id, $d['empleado_id'] ?? null, $ubicOrigenSep, $primeraUbic ?: null]);

            $pdo->commit();
            responder(['mensaje' => 'Separación de ubicaciones guardada'], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            responder(['error' => 'Error en la transacción: ' . $e->getMessage()], 500);
        }
        return;
    }

    // ── Tipos estándar: suma, resta, ubicacion ───────────────────
    if (!in_array($tipo, ['suma', 'resta', 'ubicacion'])) {
        responder(['error' => 'Tipo de movimiento inválido'], 400);
    }

    $filaEstado = $d['fila_estado'] ?? null;   // estado de la fila concreta

    if ($tipo !== 'ubicacion') {
        $cantidad = (int)($d['cantidad'] ?? 0);
        if ($cantidad < 1) responder(['error' => 'Cantidad inválida'], 400);

        if ($tipo === 'resta') {
            // Verificar stock de la fila concreta
            if ($filaEstado) {
                $q = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM equipo_ubicaciones WHERE equipo_id=? AND estado=?");
                $q->execute([$equipo_id, $filaEstado]);
                $stockFila = (int)$q->fetchColumn();
                // Si no hay líneas en equipo_ubicaciones, caer al stock global
                if ($stockFila === 0) {
                    $q2 = $pdo->prepare("SELECT cantidad FROM equipos WHERE id=?");
                    $q2->execute([$equipo_id]);
                    $stockFila = (int)$q2->fetchColumn();
                }
            } else {
                $q = $pdo->prepare("SELECT cantidad FROM equipos WHERE id=?");
                $q->execute([$equipo_id]);
                $stockFila = (int)$q->fetchColumn();
            }
            if ($stockFila < $cantidad) {
                responder(['error' => "Stock insuficiente en esta línea. Disponible: {$stockFila} uds."], 409);
            }
        }
    } else {
        $cantidad = 0;
    }

    $pdo->beginTransaction();
    try {
        // Capturar ubicación origen ANTES de modificar
        if ($filaEstado) {
            $qOrig = $pdo->prepare("
                SELECT ubicacion_id FROM equipo_ubicaciones
                WHERE equipo_id=? AND estado=? ORDER BY id LIMIT 1
            ");
            $qOrig->execute([$equipo_id, $filaEstado]);
        } else {
            $qOrig = $pdo->prepare("SELECT ubicacion_id FROM equipos WHERE id=?");
            $qOrig->execute([$equipo_id]);
        }
        $ubicacionOrigenId = $qOrig->fetchColumn() ?: null;

        if ($tipo === 'suma') {
            // Sumar al equipo_ubicaciones de la fila concreta si existe, si no al total
            $cq = $pdo->prepare("SELECT COUNT(*) FROM equipo_ubicaciones WHERE equipo_id=?");
            $cq->execute([$equipo_id]);
            $nLineas = (int)$cq->fetchColumn();

            if ($nLineas >= 1 && $filaEstado) {
                // Sumar en la línea o líneas con ese estado
                $pdo->prepare("
                    UPDATE equipo_ubicaciones SET cantidad = cantidad + ?
                    WHERE equipo_id = ? AND estado = ?
                ")->execute([$cantidad, $equipo_id, $filaEstado]);
            } elseif ($nLineas === 1) {
                $pdo->prepare("UPDATE equipo_ubicaciones SET cantidad = cantidad + ? WHERE equipo_id = ?")
                    ->execute([$cantidad, $equipo_id]);
            }
            // Recalcular total en equipos
            $pdo->prepare("
                UPDATE equipos
                SET cantidad = (SELECT COALESCE(SUM(eu.cantidad),0) FROM equipo_ubicaciones eu WHERE eu.equipo_id = ?)
                WHERE id = ?
            ")->execute([$equipo_id, $equipo_id]);
            // Si no hay líneas en ubic, sumar directamente al total
            if ($nLineas === 0) {
                $pdo->prepare("UPDATE equipos SET cantidad = cantidad + ? WHERE id = ?")
                    ->execute([$cantidad, $equipo_id]);
            }

        } elseif ($tipo === 'resta') {
            $cq = $pdo->prepare("SELECT COUNT(*) FROM equipo_ubicaciones WHERE equipo_id=?");
            $cq->execute([$equipo_id]);
            $nLineas = (int)$cq->fetchColumn();

            if ($nLineas >= 1 && $filaEstado) {
                $pdo->prepare("
                    UPDATE equipo_ubicaciones SET cantidad = cantidad - ?
                    WHERE equipo_id = ? AND estado = ?
                ")->execute([$cantidad, $equipo_id, $filaEstado]);
                // Limpiar líneas en 0
                $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id=? AND cantidad<=0")
                    ->execute([$equipo_id]);
            } elseif ($nLineas === 1) {
                $pdo->prepare("UPDATE equipo_ubicaciones SET cantidad = cantidad - ? WHERE equipo_id = ?")
                    ->execute([$cantidad, $equipo_id]);
                $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id=? AND cantidad<=0")
                    ->execute([$equipo_id]);
            }
            // Recalcular total
            $pdo->prepare("UPDATE equipos SET cantidad = cantidad - ? WHERE id = ?")
                ->execute([$cantidad, $equipo_id]);

        } elseif ($tipo === 'ubicacion') {
            $uid = $d['ubicacion_id'] ?? null;

            if ($filaEstado) {
                // Cambiar ubicación solo de las líneas con este estado
                // Obtener cantidad total de las líneas de este estado
                $q = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM equipo_ubicaciones WHERE equipo_id=? AND estado=?");
                $q->execute([$equipo_id, $filaEstado]);
                $cantFila = (int)$q->fetchColumn();
                if ($cantFila === 0) {
                    // Sin líneas propias: usar stock total
                    $q2 = $pdo->prepare("SELECT cantidad FROM equipos WHERE id=?");
                    $q2->execute([$equipo_id]);
                    $cantFila = (int)$q2->fetchColumn();
                }
                // Eliminar líneas de este estado
                $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id=? AND estado=?")
                    ->execute([$equipo_id, $filaEstado]);
                // Insertar la nueva línea
                if ($uid) {
                    $pdo->prepare("
                        INSERT INTO equipo_ubicaciones (equipo_id, ubicacion_id, cantidad, estado)
                        VALUES (?,?,?,?) AS nueva
                        ON DUPLICATE KEY UPDATE cantidad = equipo_ubicaciones.cantidad + nueva.cantidad
                    ")->execute([$equipo_id, (int)$uid, $cantFila, $filaEstado]);
                }
            } else {
                // Sin estado específico: mover TODO el equipo
                $q = $pdo->prepare("SELECT cantidad FROM equipos WHERE id=?");
                $q->execute([$equipo_id]);
                $stock = (int)$q->fetchColumn();
                $pdo->prepare("DELETE FROM equipo_ubicaciones WHERE equipo_id=?")
                    ->execute([$equipo_id]);
                if ($uid) {
                    $estActual = $pdo->prepare("SELECT estado FROM equipos WHERE id=?");
                    $estActual->execute([$equipo_id]);
                    $est = $estActual->fetchColumn() ?: 'Activo';
                    $pdo->prepare("
                        INSERT INTO equipo_ubicaciones (equipo_id, ubicacion_id, cantidad, estado)
                        VALUES (?,?,?,?) AS nueva
                        ON DUPLICATE KEY UPDATE cantidad = nueva.cantidad
                    ")->execute([$equipo_id, (int)$uid, $stock, $est]);
                }
            }
            // Actualizar ubicacion_id legacy
            $pdo->prepare("
                UPDATE equipos
                SET ubicacion_id = (SELECT ubicacion_id FROM equipo_ubicaciones WHERE equipo_id=? ORDER BY id LIMIT 1)
                WHERE id=?
            ")->execute([$equipo_id, $equipo_id]);
        }

        $pdo->prepare("
            INSERT INTO movimientos (equipo_id, empleado_id, tipo, cantidad, cliente_id, proveedor_id, ubicacion_origen_id, ubicacion_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $equipo_id,
            $d['empleado_id']  ?? null,
            $tipo,
            $cantidad,
            $d['cliente_id']   ?? null,
            $d['proveedor_id'] ?? null,
            $ubicacionOrigenId,
            $d['ubicacion_id'] ?? null,
        ]);

        $pdo->commit();
        responder(['mensaje' => 'Movimiento registrado'], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(['error' => 'Error en la transacción: ' . $e->getMessage()], 500);
    }
} else {
    responder(['error' => 'Método no permitido'], 405);
}
