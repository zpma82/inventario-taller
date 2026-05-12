<?php
// =============================================================
// INVENTARIO IT — API de ubicaciones
// GET  /api/ubicaciones.php              → lista todas las ubicaciones
// GET  /api/ubicaciones.php?tipo=opciones → valores fijos de cada campo
// POST /api/ubicaciones.php              → crear nueva ubicación
// =============================================================
require_once 'config.php';

$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Devuelve los valores fijos de los 4 campos para rellenar los desplegables
    if (isset($_GET['tipo']) && $_GET['tipo'] === 'opciones') {
        responder([
            'calles'  => $pdo->query("SELECT valor FROM ubic_calles  ORDER BY valor+0, valor")->fetchAll(PDO::FETCH_COLUMN),
            'lados'   => $pdo->query("SELECT valor FROM ubic_lados   ORDER BY valor")->fetchAll(PDO::FETCH_COLUMN),
            'huecos'  => $pdo->query("SELECT valor FROM ubic_huecos  ORDER BY valor+0, valor")->fetchAll(PDO::FETCH_COLUMN),
            'alturas' => $pdo->query("SELECT valor FROM ubic_alturas ORDER BY valor")->fetchAll(PDO::FETCH_COLUMN),
        ]);
    }

    // Devuelve todas las ubicaciones con la etiqueta concatenada
    $stmt = $pdo->query("
        SELECT id, calle, lado, hueco, altura,
               CONCAT(calle,'-',lado,'-',hueco,'-',altura) AS etiqueta
        FROM   ubicaciones
        ORDER  BY calle+0, lado, hueco+0, altura
    ");
    responder($stmt->fetchAll());

} elseif ($method === 'POST') {
    $d = leerBody();
    if (empty($d['calle']) || empty($d['lado']) || empty($d['hueco']) || empty($d['altura'])) {
        responder(['error' => 'Los 4 campos son obligatorios'], 400);
    }

    // INSERT IGNORE para no fallar si ya existe la combinación
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO ubicaciones (calle, lado, hueco, altura)
        VALUES (:calle, :lado, :hueco, :altura)
    ");
    $stmt->execute([
        ':calle'  => $d['calle'],
        ':lado'   => $d['lado'],
        ':hueco'  => $d['hueco'],
        ':altura' => $d['altura'],
    ]);

    $newId = $pdo->lastInsertId();
    // Si ya existía (lastInsertId = 0), buscar su id real
    if (!$newId) {
        $q = $pdo->prepare("SELECT id FROM ubicaciones WHERE calle=? AND lado=? AND hueco=? AND altura=?");
        $q->execute([$d['calle'], $d['lado'], $d['hueco'], $d['altura']]);
        $newId = $q->fetchColumn();
    }
    responder(['id' => $newId, 'mensaje' => 'Ubicación guardada'], 201);
} else {
    responder(['error' => 'Método no permitido'], 405);
}
