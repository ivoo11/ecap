-- =========================================================
-- ECAP
-- Esquema inicial v0.1
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- PERSONAS
-- Una persona puede ser alumno, docente, etc.
-- ---------------------------------------------------------

CREATE TABLE personas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,

    dni VARCHAR(20) NOT NULL,
    email VARCHAR(190) NOT NULL,
    telefono VARCHAR(50) NULL,

    universidad_id INT UNSIGNED NULL,

    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_personas_dni (dni),
    INDEX idx_personas_email (email),
    INDEX idx_personas_apellido (apellido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- UNIVERSIDADES
-- El usuario selecciona universidad.
-- Pública/privada es información interna.
-- ---------------------------------------------------------

CREATE TABLE universidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(190) NOT NULL,
    nombre_corto VARCHAR(80) NULL,

    tipo ENUM('publica', 'privada') NOT NULL,

    provincia VARCHAR(100) NULL,

    activa TINYINT(1) NOT NULL DEFAULT 1,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_universidades_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- FK universidad se agrega después de crear universidades

ALTER TABLE personas
    ADD CONSTRAINT fk_personas_universidad
    FOREIGN KEY (universidad_id)
    REFERENCES universidades(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;


-- ---------------------------------------------------------
-- ÁMBITOS PROFESIONALES
-- ---------------------------------------------------------

CREATE TABLE ambitos_profesionales (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uq_ambitos_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE persona_ambitos (
    persona_id INT UNSIGNED NOT NULL,
    ambito_id SMALLINT UNSIGNED NOT NULL,

    PRIMARY KEY (persona_id, ambito_id),

    CONSTRAINT fk_persona_ambitos_persona
        FOREIGN KEY (persona_id)
        REFERENCES personas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_persona_ambitos_ambito
        FOREIGN KEY (ambito_id)
        REFERENCES ambitos_profesionales(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- TIPOS DE ACTIVIDAD
-- Taller, curso, seminario, jornada...
-- ---------------------------------------------------------

CREATE TABLE tipos_actividad (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uq_tipo_actividad_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- ACTIVIDADES
-- Define el producto académico.
-- No define todavía cuándo se dicta.
-- ---------------------------------------------------------

CREATE TABLE actividades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tipo_actividad_id SMALLINT UNSIGNED NOT NULL,

    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,

    descripcion TEXT NULL,
    programa TEXT NULL,

    estado ENUM(
        'borrador',
        'publicada',
        'archivada'
    ) NOT NULL DEFAULT 'borrador',

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_actividades_slug (slug),

    CONSTRAINT fk_actividad_tipo
        FOREIGN KEY (tipo_actividad_id)
        REFERENCES tipos_actividad(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- EDICIONES
-- Una actividad puede dictarse varias veces.
-- ---------------------------------------------------------

CREATE TABLE ediciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    actividad_id INT UNSIGNED NOT NULL,

    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,

    modalidad ENUM(
        'online',
        'presencial',
        'hibrida'
    ) NOT NULL DEFAULT 'online',

    cupo_maximo INT UNSIGNED NULL DEFAULT 100,

    inscripcion_desde DATETIME NULL,
    inscripcion_hasta DATETIME NULL,

    estado ENUM(
        'borrador',
        'inscripcion_abierta',
        'cupo_completo',
        'inscripcion_cerrada',
        'en_curso',
        'finalizada',
        'cancelada'
    ) NOT NULL DEFAULT 'borrador',

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_edicion_actividad
        FOREIGN KEY (actividad_id)
        REFERENCES actividades(id)
        ON DELETE RESTRICT,

    INDEX idx_ediciones_fecha (fecha_inicio),
    INDEX idx_ediciones_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- DOCENTES
-- Perfil académico adicional de una persona.
-- ---------------------------------------------------------

CREATE TABLE docentes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    persona_id INT UNSIGNED NOT NULL,

    titulo_profesional VARCHAR(100) NULL,
    bio TEXT NULL,
    foto VARCHAR(255) NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uq_docente_persona (persona_id),

    CONSTRAINT fk_docente_persona
        FOREIGN KEY (persona_id)
        REFERENCES personas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE edicion_docentes (
    edicion_id INT UNSIGNED NOT NULL,
    docente_id INT UNSIGNED NOT NULL,

    rol VARCHAR(100) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    PRIMARY KEY (edicion_id, docente_id),

    CONSTRAINT fk_edicion_docente_edicion
        FOREIGN KEY (edicion_id)
        REFERENCES ediciones(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_edicion_docente_docente
        FOREIGN KEY (docente_id)
        REFERENCES docentes(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- INSCRIPCIONES
-- ---------------------------------------------------------

CREATE TABLE inscripciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    persona_id INT UNSIGNED NOT NULL,
    edicion_id INT UNSIGNED NOT NULL,

    estado ENUM(
        'confirmada',
        'cancelada',
        'lista_espera'
    ) NOT NULL DEFAULT 'confirmada',

    inscripto_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelado_en DATETIME NULL,

    CONSTRAINT fk_inscripcion_persona
        FOREIGN KEY (persona_id)
        REFERENCES personas(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_inscripcion_edicion
        FOREIGN KEY (edicion_id)
        REFERENCES ediciones(id)
        ON DELETE RESTRICT,

    UNIQUE KEY uq_persona_edicion (persona_id, edicion_id),

    INDEX idx_inscripciones_edicion_estado (edicion_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- ASISTENCIA
-- Separada de la inscripción.
-- ---------------------------------------------------------

CREATE TABLE asistencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    inscripcion_id BIGINT UNSIGNED NOT NULL,

    estado ENUM(
        'pendiente',
        'presente',
        'ausente',
        'justificada'
    ) NOT NULL DEFAULT 'pendiente',

    registrado_en DATETIME NULL,

    UNIQUE KEY uq_asistencia_inscripcion (inscripcion_id),

    CONSTRAINT fk_asistencia_inscripcion
        FOREIGN KEY (inscripcion_id)
        REFERENCES inscripciones(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- CLASES
-- Nos sirve desde ahora y después para cursos de varias clases.
-- ---------------------------------------------------------

CREATE TABLE clases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    edicion_id INT UNSIGNED NOT NULL,

    titulo VARCHAR(255) NULL,

    numero SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,

    zoom_meeting_id VARCHAR(100) NULL,
    zoom_join_url TEXT NULL,

    acceso_desde DATETIME NULL,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_clase_edicion
        FOREIGN KEY (edicion_id)
        REFERENCES ediciones(id)
        ON DELETE CASCADE,

    INDEX idx_clases_edicion (edicion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- MATERIALES
-- ---------------------------------------------------------

CREATE TABLE materiales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    clase_id INT UNSIGNED NOT NULL,

    titulo VARCHAR(255) NOT NULL,
    archivo VARCHAR(255) NOT NULL,

    tipo_archivo VARCHAR(50) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    visible TINYINT(1) NOT NULL DEFAULT 1,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_material_clase
        FOREIGN KEY (clase_id)
        REFERENCES clases(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- CERTIFICADOS
-- El código existe aunque no necesariamente aparezca
-- impreso en el PDF.
-- ---------------------------------------------------------

CREATE TABLE certificados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    inscripcion_id BIGINT UNSIGNED NOT NULL,

    codigo VARCHAR(80) NOT NULL,

    archivo VARCHAR(255) NULL,

    estado ENUM(
        'pendiente',
        'emitido',
        'anulado'
    ) NOT NULL DEFAULT 'pendiente',

    disponible_desde DATETIME NULL,
    emitido_en DATETIME NULL,
    descargado_en DATETIME NULL,

    UNIQUE KEY uq_certificado_codigo (codigo),
    UNIQUE KEY uq_certificado_inscripcion (inscripcion_id),

    CONSTRAINT fk_certificado_inscripcion
        FOREIGN KEY (inscripcion_id)
        REFERENCES inscripciones(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- CONSENTIMIENTOS
-- Especialmente email marketing.
-- ---------------------------------------------------------

CREATE TABLE consentimientos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    persona_id INT UNSIGNED NOT NULL,

    tipo ENUM(
        'email_marketing'
    ) NOT NULL,

    aceptado TINYINT(1) NOT NULL,

    origen VARCHAR(100) NULL,

    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_consentimiento_persona
        FOREIGN KEY (persona_id)
        REFERENCES personas(id)
        ON DELETE CASCADE,

    INDEX idx_consentimiento_persona_tipo (persona_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- CÓDIGOS DE VERIFICACIÓN
-- Alumno recurrente: DNI -> email enmascarado -> código.
-- ---------------------------------------------------------

CREATE TABLE codigos_verificacion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    persona_id INT UNSIGNED NOT NULL,

    codigo_hash VARCHAR(255) NOT NULL,

    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,

    intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_codigo_persona
        FOREIGN KEY (persona_id)
        REFERENCES personas(id)
        ON DELETE CASCADE,

    INDEX idx_codigo_persona (persona_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- USUARIOS ADMINISTRATIVOS
-- ---------------------------------------------------------

CREATE TABLE usuarios_admin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    rol ENUM(
        'superadmin',
        'admin',
        'operador'
    ) NOT NULL DEFAULT 'operador',

    activo TINYINT(1) NOT NULL DEFAULT 1,

    ultimo_acceso DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- AUDITORÍA
-- ---------------------------------------------------------

CREATE TABLE auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_admin_id INT UNSIGNED NULL,

    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(100) NULL,
    entidad_id BIGINT UNSIGNED NULL,

    detalle TEXT NULL,

    ip VARCHAR(45) NULL,

    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_auditoria_admin
        FOREIGN KEY (usuario_admin_id)
        REFERENCES usuarios_admin(id)
        ON DELETE SET NULL,

    INDEX idx_auditoria_entidad (entidad, entidad_id),
    INDEX idx_auditoria_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- DATOS BASE
-- ---------------------------------------------------------

INSERT INTO tipos_actividad (nombre, slug) VALUES
('Taller', 'taller'),
('Curso', 'curso'),
('Seminario', 'seminario'),
('Jornada', 'jornada'),
('Conferencia', 'conferencia');


INSERT INTO ambitos_profesionales (nombre, slug) VALUES
('Sector público nacional', 'sector-publico-nacional'),
('Sector público provincial', 'sector-publico-provincial'),
('Sector público municipal', 'sector-publico-municipal'),
('Poder Judicial / Ministerio Público', 'poder-judicial-ministerio-publico'),
('Sector privado', 'sector-privado'),
('Ejercicio independiente', 'ejercicio-independiente'),
('Docencia / ámbito académico', 'docencia-academia'),
('Otro', 'otro');


SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE universidades
MODIFY tipo ENUM('publica', 'privada', 'otra') NOT NULL;

ALTER TABLE personas
ADD COLUMN universidad_otra VARCHAR(190) NULL
AFTER universidad_id;

ALTER TABLE actividades
    ADD COLUMN imagen_portada VARCHAR(255) NULL AFTER programa,
    ADD COLUMN destacada TINYINT(1) NOT NULL DEFAULT 0 AFTER imagen_portada,
    ADD COLUMN orden_destacada INT UNSIGNED NULL AFTER destacada;

    ALTER TABLE actividades
    ADD INDEX idx_actividades_destacadas (
        destacada,
        orden_destacada
    );