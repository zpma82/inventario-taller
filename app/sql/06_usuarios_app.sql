-- Forzar charset UTF-8 multibyte
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================================
-- INVENTARIO IT — Usuarios del frontend web
-- Ejecutar: mysql -u root -p inventaller < sql/06_usuarios_app.sql
--
-- Vincula los operarios (empleados) con cuentas de acceso
-- al frontend. Contraseña por defecto: 1234
-- Hash de '1234' con PASSWORD_BCRYPT generado con PHP.
-- =============================================================
USE inventaller;

-- Tabla de usuarios del frontend
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100)  NOT NULL,
    usuario       VARCHAR(60)   NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    rol           ENUM('admin','tecnico','usuario','invitado') NOT NULL DEFAULT 'usuario',
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    empleado_id   INT NULL,
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL
);

-- Tabla de sesiones activas (tokens de 8h)
CREATE TABLE IF NOT EXISTS sesiones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    expira_en   TIMESTAMP NOT NULL,
    creada_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ── Usuarios iniciales ───────────────────────────────────────
-- Contraseña de todos: 1234
-- Hash generado con: php -r "echo password_hash('1234', PASSWORD_BCRYPT);"
-- Para cambiarla en producción usa ese mismo comando con tu contraseña.

INSERT IGNORE INTO usuarios (nombre, usuario, password_hash, rol, empleado_id) VALUES
  -- Administrador del sistema (sin empleado vinculado)
  ('Administrador',  'admin',   '$2y$10$C8DiCEgHLRejn7165hAuJu2vruBDi15vBr8ak/IE6JmNouXkyV1n2', 'admin',    NULL),
  -- Técnicos (vinculados a empleados 1 y 2)
  ('Carlos García',  'carlos',  '$2y$10$C8DiCEgHLRejn7165hAuJu2vruBDi15vBr8ak/IE6JmNouXkyV1n2', 'tecnico',  1),
  ('Laura Martínez', 'laura',   '$2y$10$C8DiCEgHLRejn7165hAuJu2vruBDi15vBr8ak/IE6JmNouXkyV1n2', 'tecnico',  2),
  -- Usuarios operarios (vinculados a empleados 3 y 4)
  ('Pedro Sánchez',  'pedro',   '$2y$10$C8DiCEgHLRejn7165hAuJu2vruBDi15vBr8ak/IE6JmNouXkyV1n2', 'usuario',  3),
  ('Ana López',      'ana',     '$2y$10$C8DiCEgHLRejn7165hAuJu2vruBDi15vBr8ak/IE6JmNouXkyV1n2', 'usuario',  4),
  -- Invitado (solo lectura)
  ('Invitado',       'invitado','$2y$10$C8DiCEgHLRejn7165hAuJu2vruBDi15vBr8ak/IE6JmNouXkyV1n2', 'invitado', NULL);

-- Verificación
SELECT
  CONCAT('✔ ', u.nombre, ' (', u.rol, ') → login: ', u.usuario,
    IFNULL(CONCAT(' — operario vinculado: ', e.nombre), '')) AS usuarios_creados
FROM usuarios u
LEFT JOIN empleados e ON e.id = u.empleado_id
ORDER BY FIELD(u.rol,'admin','tecnico','usuario','invitado'), u.nombre;
