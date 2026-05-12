-- =============================================================
-- MIGRACIÓN: soporte de múltiples ubicaciones por equipo
-- Ejecutar UNA SOLA VEZ en bases de datos ya existentes:
--   mysql -u root -p inventaller < sql/04_migracion_multi_ubicacion.sql
-- =============================================================
USE inventaller;

-- Ampliar columna lado si aún tiene VARCHAR(5)
ALTER TABLE ubic_lados   MODIFY valor VARCHAR(10);
ALTER TABLE ubicaciones  MODIFY lado  VARCHAR(10);

-- Actualizar valores de lado a Dcha/Izda (solo si aún son A/B/C/D)
UPDATE ubic_lados SET valor='Dcha' WHERE valor='A';
UPDATE ubic_lados SET valor='Izda' WHERE valor='B';
DELETE FROM ubic_lados WHERE valor IN ('C','D');
UPDATE ubicaciones SET lado='Dcha' WHERE lado='A';
UPDATE ubicaciones SET lado='Izda' WHERE lado='B';
UPDATE ubicaciones SET lado='Dcha' WHERE lado='C';
UPDATE ubicaciones SET lado='Izda' WHERE lado='D';

-- Nueva tabla para múltiples ubicaciones por equipo
CREATE TABLE IF NOT EXISTS equipo_ubicaciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id    INT NOT NULL,
    ubicacion_id INT NOT NULL,
    cantidad     INT NOT NULL DEFAULT 1,
    UNIQUE KEY uk_equipo_ubic (equipo_id, ubicacion_id),
    FOREIGN KEY (equipo_id)    REFERENCES equipos(id)    ON DELETE CASCADE,
    FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id) ON DELETE CASCADE
);

-- Poblar desde los datos actuales
INSERT IGNORE INTO equipo_ubicaciones (equipo_id, ubicacion_id, cantidad)
SELECT id, ubicacion_id, cantidad
FROM   equipos
WHERE  ubicacion_id IS NOT NULL;

SELECT 'Migración completada.' AS resultado;

-- Añadir columna estado a equipo_ubicaciones (si no existe ya)
ALTER TABLE equipo_ubicaciones
  ADD COLUMN IF NOT EXISTS estado ENUM('Activo','En reparación','Baja') NOT NULL DEFAULT 'Activo';

-- Actualizar la UNIQUE KEY para incluir estado
ALTER TABLE equipo_ubicaciones DROP INDEX IF EXISTS uk_equipo_ubic;
ALTER TABLE equipo_ubicaciones
  ADD UNIQUE KEY IF NOT EXISTS uk_equipo_ubic_estado (equipo_id, ubicacion_id, estado);

-- Sincronizar el estado desde equipos
UPDATE equipo_ubicaciones eu
JOIN   equipos e ON e.id = eu.equipo_id
SET    eu.estado = e.estado;

SELECT 'Migración v3 completada.' AS resultado;
