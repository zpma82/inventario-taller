-- =============================================================
-- INVENTARIO TALLER — Schema completo
-- Ejecutar con: mysql -u root -p < sql/01_schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS inventaller
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE inventaller;

-- Categorías de equipo
CREATE TABLE IF NOT EXISTS categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE
);

-- Subcategorías vinculadas a una categoría
CREATE TABLE IF NOT EXISTS subcategorias (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(80) NOT NULL,
    categoria_id INT NOT NULL,
    UNIQUE KEY uk_subcat (nombre, categoria_id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);

-- Proveedores
CREATE TABLE IF NOT EXISTS proveedores (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL,
    contacto VARCHAR(100),
    telefono VARCHAR(20),
    email    VARCHAR(100)
);

-- Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL,
    contacto VARCHAR(100),
    telefono VARCHAR(20),
    email    VARCHAR(100)
);

-- Empleados / operarios
CREATE TABLE IF NOT EXISTS empleados (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(100) NOT NULL,
    puesto             VARCHAR(100),
    salario            DECIMAL(10,2),
    fecha_contratacion DATE
);

-- Tablas auxiliares ubicación
CREATE TABLE IF NOT EXISTS ubic_calles  (valor VARCHAR(10) PRIMARY KEY);
CREATE TABLE IF NOT EXISTS ubic_lados   (valor VARCHAR(10) PRIMARY KEY);
CREATE TABLE IF NOT EXISTS ubic_huecos  (valor VARCHAR(10) PRIMARY KEY);
CREATE TABLE IF NOT EXISTS ubic_alturas (valor VARCHAR(10) PRIMARY KEY);

-- Ubicaciones
CREATE TABLE IF NOT EXISTS ubicaciones (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    calle  VARCHAR(10) NOT NULL,
    lado   VARCHAR(10) NOT NULL,
    hueco  VARCHAR(10) NOT NULL,
    altura VARCHAR(10) NOT NULL,
    UNIQUE KEY uk_ubicacion (calle, lado, hueco, altura)
);

-- Equipos
CREATE TABLE IF NOT EXISTS equipos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    categoria_id    INT,
    subcategoria_id INT,
    numero_serie    VARCHAR(80),
    estado          ENUM('Activo','En reparación','Baja') NOT NULL DEFAULT 'Activo',
    ubicacion_id    INT,
    cantidad        INT NOT NULL DEFAULT 1,
    fecha_alta      DATE,
    notas           TEXT,
    especificaciones TEXT,
    proveedor_id    INT,
    FOREIGN KEY (categoria_id)    REFERENCES categorias(id)    ON DELETE SET NULL,
    FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id) ON DELETE SET NULL,
    FOREIGN KEY (ubicacion_id)    REFERENCES ubicaciones(id)   ON DELETE SET NULL,
    FOREIGN KEY (proveedor_id)    REFERENCES proveedores(id)   ON DELETE SET NULL
);

-- Movimientos
CREATE TABLE IF NOT EXISTS movimientos (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id          INT NOT NULL,
    empleado_id        INT,
    tipo               ENUM('suma','resta','ubicacion') NOT NULL,
    cantidad           INT NOT NULL DEFAULT 0,
    cliente_id         INT,
    proveedor_id       INT,
    ubicacion_origen_id INT,
    ubicacion_id       INT,
    fecha              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipo_id)           REFERENCES equipos(id)     ON DELETE CASCADE,
    FOREIGN KEY (empleado_id)         REFERENCES empleados(id)   ON DELETE SET NULL,
    FOREIGN KEY (cliente_id)          REFERENCES clientes(id)    ON DELETE SET NULL,
    FOREIGN KEY (proveedor_id)        REFERENCES proveedores(id) ON DELETE SET NULL,
    FOREIGN KEY (ubicacion_origen_id) REFERENCES ubicaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (ubicacion_id)        REFERENCES ubicaciones(id) ON DELETE SET NULL
);

-- Equipo_ubicaciones (multi-ubicación)
CREATE TABLE IF NOT EXISTS equipo_ubicaciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id    INT NOT NULL,
    ubicacion_id INT NOT NULL,
    cantidad     INT NOT NULL DEFAULT 1,
    estado       ENUM('Activo','En reparación','Baja') NOT NULL DEFAULT 'Activo',
    UNIQUE KEY uk_equipo_ubic_estado (equipo_id, ubicacion_id, estado),
    FOREIGN KEY (equipo_id)    REFERENCES equipos(id)     ON DELETE CASCADE,
    FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id) ON DELETE CASCADE
);
