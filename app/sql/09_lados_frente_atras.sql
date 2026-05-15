-- =============================================================
-- Migración 09 — Añadir 'Frente' y 'Atras' a ubic_lados
-- =============================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventaller;

INSERT IGNORE INTO ubic_lados VALUES ('Frente');
INSERT IGNORE INTO ubic_lados VALUES ('Atras');

SELECT 'Migración 09: Frente y Atras añadidos a ubic_lados.' AS resultado;
