-- Migración 10 — Añadir empleado_id (operario responsable) a equipos
USE inventaller;
ALTER TABLE equipos
  ADD COLUMN IF NOT EXISTS empleado_id INT NULL,
  ADD CONSTRAINT fk_equipos_empleado
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL;
