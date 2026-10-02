-- =========================================================
-- ECAP
-- Primera actividad real
-- =========================================================

INSERT INTO actividades (
    tipo_actividad_id,
    titulo,
    slug,
    descripcion,
    programa,
    estado
)
SELECT
    id,
    'El procedimiento probatorio y la prueba científica',
    'el-procedimiento-probatorio-y-la-prueba-cientifica',
    NULL,
    NULL,
    'publicada'
FROM tipos_actividad
WHERE slug = 'taller'
LIMIT 1;


SET @actividad_id = LAST_INSERT_ID();


INSERT INTO ediciones (
    actividad_id,
    fecha_inicio,
    fecha_fin,
    modalidad,
    cupo_maximo,
    estado
) VALUES (
    @actividad_id,
    '2026-10-08 14:00:00',
    NULL,
    'online',
    100,
    'inscripcion_abierta'
);


SET @edicion_id = LAST_INSERT_ID();


INSERT INTO clases (
    edicion_id,
    titulo,
    numero,
    fecha_inicio,
    fecha_fin,
    zoom_meeting_id,
    zoom_join_url,
    acceso_desde
) VALUES (
    @edicion_id,
    'El procedimiento probatorio y la prueba científica',
    1,
    '2026-10-08 14:00:00',
    NULL,
    NULL,
    NULL,
    '2026-10-08 13:40:00'
);