-- Migración 10 — Añadir empleado_id (operario responsable) a equipos
USE inventaller;

-- Añadir columna solo si no existe
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'inventaller'
    AND TABLE_NAME   = 'equipos'
    AND COLUMN_NAME  = 'empleado_id'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE equipos ADD COLUMN empleado_id INT NULL, ADD CONSTRAINT fk_equipos_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL',
  'SELECT ''columna empleado_id ya existe'' AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
