-- Conserva certificados y comunicaciones_transaccionales existentes.
CREATE TABLE IF NOT EXISTS certificado_configuracion (
 edicion_id INT UNSIGNED PRIMARY KEY,
 encabezado VARCHAR(120) NOT NULL,
 titulo VARCHAR(500) NOT NULL,
 fondo VARCHAR(80) NULL,
 aprobado TINYINT(1) NOT NULL DEFAULT 0,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 actualizado_por BIGINT UNSIGNED NULL,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_cert_config_edicion FOREIGN KEY(edicion_id) REFERENCES ediciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS certificado_emision (
 certificado_id BIGINT UNSIGNED PRIMARY KEY,
 nombre VARCHAR(250) NOT NULL,
 titulo VARCHAR(500) NOT NULL,
 encabezado VARCHAR(120) NOT NULL,
 plantilla_sha256 CHAR(64) NOT NULL,
 pdf_sha256 CHAR(64) NOT NULL,
 administrador_id BIGINT UNSIGNED NOT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_cert_emision FOREIGN KEY(certificado_id) REFERENCES certificados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
