-- Forzar charset UTF-8 multibyte
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================================
-- Migración 08 — Añadir columna especificaciones a equipos
-- Ejecutar solo si ya tienes la BD creada con el schema anterior
-- mysql -u root -p inventaller < sql/08_especificaciones.sql
-- =============================================================

-- =============================================================
-- Migración 08 — especificaciones en equipos + proveedor_id en movimientos
-- Compatible con MySQL 5.7 / 8.x
-- =============================================================

USE inventaller;

-- Añadir columna especificaciones a equipos (si no existe)
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'inventaller'
      AND TABLE_NAME   = 'equipos'
      AND COLUMN_NAME  = 'especificaciones'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE equipos ADD COLUMN especificaciones TEXT NULL AFTER notas',
    'SELECT ''especificaciones ya existe en equipos'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Añadir columna proveedor_id a movimientos (si no existe)
SET @col2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'inventaller'
      AND TABLE_NAME   = 'movimientos'
      AND COLUMN_NAME  = 'proveedor_id'
);
SET @sql2 = IF(@col2 = 0,
    'ALTER TABLE movimientos ADD COLUMN proveedor_id INT NULL AFTER cliente_id',
    'SELECT ''proveedor_id ya existe en movimientos'' AS info'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Añadir columna ubicacion_origen_id a movimientos (si no existe)
SET @col3 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'inventaller'
      AND TABLE_NAME   = 'movimientos'
      AND COLUMN_NAME  = 'ubicacion_origen_id'
);
SET @sql4 = IF(@col3 = 0,
    'ALTER TABLE movimientos ADD COLUMN ubicacion_origen_id INT NULL AFTER proveedor_id',
    'SELECT ''ubicacion_origen_id ya existe en movimientos'' AS info'
);
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;
SET @fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = 'inventaller'
      AND TABLE_NAME      = 'movimientos'
      AND CONSTRAINT_NAME = 'fk_mov_proveedor'
);
SET @sql3 = IF(@fk = 0,
    'ALTER TABLE movimientos ADD CONSTRAINT fk_mov_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL',
    'SELECT ''fk_mov_proveedor ya existe'' AS info'
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;
