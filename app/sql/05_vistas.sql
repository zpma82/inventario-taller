-- =============================================================
-- INVENTARIO IT v3 — Vistas útiles para consulta directa en MySQL
-- Ejecutar UNA SOLA VEZ:
--   mysql -u root -p inventaller < sql/05_vistas.sql
-- =============================================================
USE inventaller;

-- -------------------------------------------------------------
-- Vista: v_equipos_detalle
-- Equivalente a la lógica PHP de getTodos(): una fila por cada
-- combinación (equipo × estado). Al hacer SELECT * FROM v_equipos_detalle
-- se ve lo mismo que muestra el HTML.
-- -------------------------------------------------------------
CREATE OR REPLACE VIEW v_equipos_detalle AS
SELECT
    e.id                                                        AS equipo_id,
    e.nombre,
    c.nombre                                                    AS categoria,
    e.numero_serie,
    eu.estado                                                   AS estado,
    CONCAT(u.calle,'-',u.lado,'-',u.hueco,'-',u.altura)        AS ubicacion,
    eu.cantidad                                                 AS cantidad_en_ubicacion,
    e.cantidad                                                  AS cantidad_total,
    e.fecha_alta,
    p.nombre                                                    AS proveedor,
    e.notas
FROM       equipos           e
LEFT JOIN  categorias        c  ON c.id = e.categoria_id
LEFT JOIN  proveedores       p  ON p.id = e.proveedor_id
LEFT JOIN  equipo_ubicaciones eu ON eu.equipo_id = e.id
LEFT JOIN  ubicaciones       u  ON u.id = eu.ubicacion_id
ORDER BY   e.id, eu.estado;

-- -------------------------------------------------------------
-- Vista: v_equipos_resumen
-- Una fila por equipo con: estado mayoritario, ubicaciones
-- concatenadas y cantidad total. Ideal para informes rápidos.
-- -------------------------------------------------------------
CREATE OR REPLACE VIEW v_equipos_resumen AS
SELECT
    e.id                                                            AS equipo_id,
    e.nombre,
    c.nombre                                                        AS categoria,
    e.numero_serie,
    -- Estado mayoritario (el que tiene más unidades)
    SUBSTRING_INDEX(
        GROUP_CONCAT(eu.estado ORDER BY eu.cantidad DESC SEPARATOR ','),
        ',', 1
    )                                                               AS estado_mayoritario,
    -- Todas las ubicaciones con sus cantidades
    GROUP_CONCAT(
        CONCAT(u.calle,'-',u.lado,'-',u.hueco,'-',u.altura,
               ' ×', eu.cantidad, ' (', eu.estado, ')')
        ORDER BY eu.cantidad DESC
        SEPARATOR ' | '
    )                                                               AS ubicaciones,
    e.cantidad                                                      AS cantidad_total,
    e.fecha_alta,
    p.nombre                                                        AS proveedor
FROM       equipos            e
LEFT JOIN  categorias         c  ON c.id = e.categoria_id
LEFT JOIN  proveedores        p  ON p.id = e.proveedor_id
LEFT JOIN  equipo_ubicaciones eu ON eu.equipo_id = e.id
LEFT JOIN  ubicaciones        u  ON u.id = eu.ubicacion_id
GROUP BY   e.id, e.nombre, c.nombre, e.numero_serie, e.cantidad, e.fecha_alta, p.nombre
ORDER BY   e.id;

SELECT 'Vistas creadas: v_equipos_detalle, v_equipos_resumen' AS resultado;
