-- Forzar charset UTF-8 multibyte
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================================
-- INVENTARIO TALLER — Migración: tabla subcategorias
-- Ejecutar UNA SOLA VEZ en bases de datos ya existentes:
--   mysql -u root -p inventaller < sql/07_migracion_subcategorias.sql
-- =============================================================
USE inventaller;

-- Crear tabla subcategorias
CREATE TABLE IF NOT EXISTS subcategorias (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(80) NOT NULL,
    categoria_id INT NOT NULL,
    UNIQUE KEY uk_subcat (nombre, categoria_id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);

-- Añadir columna subcategoria_id a equipos si no existe
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'inventaller'
      AND TABLE_NAME   = 'equipos'
      AND COLUMN_NAME  = 'subcategoria_id'
);
SET @sql_col = IF(@col_exists = 0,
    'ALTER TABLE equipos ADD COLUMN subcategoria_id INT NULL AFTER categoria_id',
    'SELECT "columna subcategoria_id ya existe" AS info'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Añadir FK si no existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'inventaller'
      AND TABLE_NAME         = 'equipos'
      AND CONSTRAINT_NAME    = 'fk_equip_subcat'
);
SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE equipos ADD CONSTRAINT fk_equip_subcat FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id) ON DELETE SET NULL',
    'SELECT "FK ya existe" AS info'
);
PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Subcategorías de ejemplo
INSERT IGNORE INTO subcategorias (nombre, categoria_id) VALUES
  ('Torre',1),('Mini-PC',1),('All-in-One',1),
  ('Ultrabook',2),('Gaming',2),('Workstation',2),
  ('Full HD',3),('4K',3),('Curvo',3),
  ('Láser B/N',4),('Láser Color',4),('Inyección',4),
  ('Switch',5),('Router',5),('Access Point',5),
  ('Periférico',6),('Almacenamiento',6),('Accesorio',6);

SELECT 'Migración subcategorias completada.' AS resultado;
SELECT COUNT(*) AS subcategorias_creadas FROM subcategorias;
