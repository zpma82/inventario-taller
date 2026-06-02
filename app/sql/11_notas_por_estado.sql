-- Migración 11: notas y especificaciones independientes por estado
USE inventaller;

CREATE TABLE IF NOT EXISTS equipo_estado_info (
    equipo_id        INT NOT NULL,
    estado           ENUM('Activo','En reparación','Baja') NOT NULL,
    notas            TEXT,
    especificaciones TEXT,
    PRIMARY KEY (equipo_id, estado),
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
);

-- Migrar datos existentes a todos los estados activos del equipo
INSERT IGNORE INTO equipo_estado_info (equipo_id, estado, notas, especificaciones)
SELECT DISTINCT eu.equipo_id, eu.estado, e.notas, e.especificaciones
FROM   equipo_ubicaciones eu
JOIN   equipos e ON e.id = eu.equipo_id
WHERE  e.notas IS NOT NULL OR e.especificaciones IS NOT NULL;
