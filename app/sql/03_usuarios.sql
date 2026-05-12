-- Forzar charset UTF-8 multibyte
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================================
-- INVENTARIO IT — Roles y usuarios MySQL (sintaxis MySQL 8.4)
-- Ejecutar con: mysql -u root -p < sql/03_usuarios.sql
--
-- Arquitectura:
--   1. Se crean 4 ROLES con sus privilegios sobre inventaller
--   2. Se crean los usuarios MySQL (operarios) y se les asigna un rol
--   3. activate_all_roles_on_login = ON para que los roles se activen al conectar
--
-- Roles:
--   rol_admin    → ALL PRIVILEGES + GRANT OPTION
--   rol_tecnico  → CREATE, ALTER, DROP, SELECT, INSERT, UPDATE, DELETE
--   rol_usuario  → SELECT, INSERT, UPDATE, DELETE
--   rol_invitado → SELECT
-- =============================================================
USE inventaller;

-- ── Activar roles automáticamente al hacer login ────────────
-- (necesario en MySQL 8 para que los roles tengan efecto)
SET PERSIST activate_all_roles_on_login = ON;

-- ────────────────────────────────────────────────────────────
-- PASO 1 — Crear los 4 roles
-- ────────────────────────────────────────────────────────────
CREATE ROLE IF NOT EXISTS 'rol_admin';
CREATE ROLE IF NOT EXISTS 'rol_tecnico';
CREATE ROLE IF NOT EXISTS 'rol_usuario';
CREATE ROLE IF NOT EXISTS 'rol_invitado';

-- ── Privilegios de cada rol ──────────────────────────────────

-- Administrador: acceso total + puede delegar permisos
GRANT ALL PRIVILEGES ON inventaller.* TO 'rol_admin' WITH GRANT OPTION;

-- Técnico: puede modificar estructura + operar datos
GRANT CREATE, ALTER, DROP,
      SELECT, INSERT, UPDATE, DELETE
  ON inventaller.*
  TO 'rol_tecnico';

-- Usuario operario: solo operaciones CRUD sobre datos
GRANT SELECT, INSERT, UPDATE, DELETE
  ON inventaller.*
  TO 'rol_usuario';

-- Invitado: solo lectura
GRANT SELECT
  ON inventaller.*
  TO 'rol_invitado';

-- ────────────────────────────────────────────────────────────
-- PASO 2 — Usuario MySQL de la aplicación (conexión PHP)
-- Solo necesita CRUD para que la API funcione
-- ────────────────────────────────────────────────────────────
CREATE USER IF NOT EXISTS 'almacen_local'@'localhost'
  IDENTIFIED BY 'CambiaEstaPassword_Local1!';

GRANT 'rol_usuario' TO 'almacen_local'@'localhost';
SET DEFAULT ROLE 'rol_usuario' TO 'almacen_local'@'localhost';

-- Acceso remoto (opcional)
CREATE USER IF NOT EXISTS 'almacen_local'@'%'
  IDENTIFIED BY 'CambiaEstaPassword_Remoto1!';

GRANT 'rol_usuario' TO 'almacen_local'@'%';
SET DEFAULT ROLE 'rol_usuario' TO 'almacen_local'@'%';

-- ────────────────────────────────────────────────────────────
-- PASO 3 — Operarios (usuarios MySQL con acceso directo a la BD)
-- Contraseña: 1234
-- ────────────────────────────────────────────────────────────

-- Carlos García → rol_tecnico
CREATE USER IF NOT EXISTS 'carlos'@'localhost' IDENTIFIED BY '1234';
GRANT 'rol_tecnico' TO 'carlos'@'localhost';
SET DEFAULT ROLE 'rol_tecnico' TO 'carlos'@'localhost';

-- Laura Martínez → rol_tecnico
CREATE USER IF NOT EXISTS 'laura'@'localhost' IDENTIFIED BY '1234';
GRANT 'rol_tecnico' TO 'laura'@'localhost';
SET DEFAULT ROLE 'rol_tecnico' TO 'laura'@'localhost';

-- Pedro Sánchez → rol_usuario
CREATE USER IF NOT EXISTS 'pedro'@'localhost' IDENTIFIED BY '1234';
GRANT 'rol_usuario' TO 'pedro'@'localhost';
SET DEFAULT ROLE 'rol_usuario' TO 'pedro'@'localhost';

-- Ana López → rol_usuario
CREATE USER IF NOT EXISTS 'ana'@'localhost' IDENTIFIED BY '1234';
GRANT 'rol_usuario' TO 'ana'@'localhost';
SET DEFAULT ROLE 'rol_usuario' TO 'ana'@'localhost';

-- Aplicar cambios
FLUSH PRIVILEGES;

-- ── Verificación ─────────────────────────────────────────────
SELECT
  u.user                                    AS usuario,
  u.host                                    AS acceso,
  GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles_asignados
FROM mysql.user u
LEFT JOIN information_schema.APPLICABLE_ROLES r
  ON r.GRANTEE = CONCAT("'", u.user, "'@'", u.host, "'")
WHERE u.user IN ('almacen_local','carlos','laura','pedro','ana',
                 'rol_admin','rol_tecnico','rol_usuario','rol_invitado')
GROUP BY u.user, u.host
ORDER BY u.user, u.host;
