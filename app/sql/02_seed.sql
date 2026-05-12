-- Forzar charset UTF-8 multibyte
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================================
-- INVENTARIO IT — Datos de ejemplo
-- Ejecutar con: mysql -u root -p inventaller < sql/02_seed.sql
-- =============================================================

USE inventaller;

-- Categorías de equipos informáticos
INSERT IGNORE INTO categorias (nombre) VALUES
  ('PC Sobremesa'), ('Portátil'), ('Monitor'),
  ('Impresora'), ('Red'), ('Otro');

-- Valores fijos para los campos de ubicación
-- Calles: números del 1 al 10
INSERT IGNORE INTO ubic_calles  VALUES ('1'),('2'),('3'),('4'),('5'),('6'),('7'),('8'),('9'),('10');
-- Lados: Dcha e Izda
INSERT IGNORE INTO ubic_lados   VALUES ('Dcha'),('Izda');
-- Huecos: números del 1 al 15
INSERT IGNORE INTO ubic_huecos  VALUES ('1'),('2'),('3'),('4'),('5'),('6'),('7'),('8'),('9'),('10'),('11'),('12'),('13'),('14'),('15');
-- Alturas: letras A-E
INSERT IGNORE INTO ubic_alturas VALUES ('A'),('B'),('C'),('D'),('E');

-- Ubicaciones de ejemplo
INSERT IGNORE INTO ubicaciones (calle, lado, hueco, altura) VALUES
  ('1','Dcha','1','A'), ('1','Izda','2','B'), ('2','Dcha','3','C'),
  ('3','Izda','5','D'), ('4','Dcha','2','A'), ('5','Izda','7','B');

-- Proveedores
INSERT IGNORE INTO proveedores (nombre, contacto, telefono, email) VALUES
  ('Dell Ibérica',   'Ventas B2B',  '91 111 11 11', 'ventas@dell.es'),
  ('Lenovo Spain',   'Canal B2B',   '93 222 22 22', 'b2b@lenovo.es'),
  ('HP España',      'Comercial',   '94 333 33 33', 'hp@hp.es'),
  ('Apple Business', 'Enterprise',  '91 444 44 44', 'business@apple.com');

-- Clientes
INSERT IGNORE INTO clientes (nombre, contacto, telefono, email) VALUES
  ('Empresa Alicante', 'Dep. IT',     '96 111 11 11', 'it@alicante.es'),
  ('Empresa Barcelona','Administración','93 222 22 22','admin@barcelona.es'),
  ('Empresa Cáceres',  'Gerencia',    '927 33 33 33', 'info@caceres.es'),
  ('Empresa Donostia', 'Responsable', '943 44 44 44', 'info@donostia.es');

-- Empleados / operarios
INSERT IGNORE INTO empleados (nombre, puesto, salario, fecha_contratacion) VALUES
  ('Carlos García',   'Técnico IT',     1800.00, '2020-03-01'),
  ('Laura Martínez',  'Responsable IT', 2800.00, '2018-06-15'),
  ('Pedro Sánchez',   'Operario',       1500.00, '2022-09-10'),
  ('Ana López',       'Técnico IT',     1900.00, '2021-04-20');

-- Subcategorías de ejemplo vinculadas a categorías
INSERT IGNORE INTO subcategorias (nombre, categoria_id) VALUES
  ('Torre',        1), ('Mini-PC',       1), ('All-in-One',    1),
  ('Ultrabook',    2), ('Gaming',        2), ('Workstation',   2),
  ('Full HD',      3), ('4K',            3), ('Curvo',         3),
  ('Láser B/N',    4), ('Láser Color',   4), ('Inyección',     4),
  ('Switch',       5), ('Router',        5), ('Access Point',  5),
  ('Periférico',   6), ('Almacenamiento',6), ('Accesorio',     6);

-- Equipos de ejemplo
INSERT IGNORE INTO equipos (nombre, categoria_id, subcategoria_id, numero_serie, estado, ubicacion_id, cantidad, fecha_alta, notas, proveedor_id) VALUES
  ('Dell OptiPlex 7090',     1, (SELECT id FROM subcategorias WHERE nombre='Torre'      AND categoria_id=1 LIMIT 1), 'SN-2024-001', 'Activo',        1, 5, '2024-01-10', 'Con SSD 512GB',      1),
  ('Lenovo ThinkPad E14',    2, (SELECT id FROM subcategorias WHERE nombre='Ultrabook'  AND categoria_id=2 LIMIT 1), 'SN-2024-002', 'Activo',        2, 3, '2024-02-15', '',                   2),
  ('Dell UltraSharp U2722',  3, (SELECT id FROM subcategorias WHERE nombre='Full HD'    AND categoria_id=3 LIMIT 1), 'SN-2023-010', 'En reparación', 3, 2, '2023-11-01', 'Pantalla dañada',    1),
  ('HP LaserJet Pro 4002dn', 4, (SELECT id FROM subcategorias WHERE nombre='Láser B/N'  AND categoria_id=4 LIMIT 1), 'SN-2022-015', 'Activo',        4, 1, '2022-06-20', '',                   3),
  ('Cisco SG350-28',         5, (SELECT id FROM subcategorias WHERE nombre='Switch'     AND categoria_id=5 LIMIT 1), 'SN-2021-003', 'Baja',          5, 1, '2021-03-05', 'Avería irreparable', NULL),
  ('MacBook Pro 14"',        2, (SELECT id FROM subcategorias WHERE nombre='Workstation' AND categoria_id=2 LIMIT 1), 'SN-2024-010', 'Activo',        6, 2, '2024-03-01', 'M3 Pro 18GB',        4);


-- Poblar equipo_ubicaciones desde los datos actuales de equipos
INSERT IGNORE INTO equipo_ubicaciones (equipo_id, ubicacion_id, cantidad, estado)
SELECT id, ubicacion_id, cantidad, estado
FROM   equipos
WHERE  ubicacion_id IS NOT NULL;

SELECT 'Datos de ejemplo cargados correctamente.' AS resultado;
