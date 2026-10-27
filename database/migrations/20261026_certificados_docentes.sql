-- Ejecutar una vez antes de publicar la opción Docentes.
-- No modifica certificados emitidos ni genera inscripciones para docentes.
CREATE TABLE certificado_docente_configuracion (
 edicion_id INT UNSIGNED PRIMARY KEY,
 encabezado VARCHAR(120) NOT NULL,
 titulo VARCHAR(500) NOT NULL,
 fondo VARCHAR(80) NULL,
 aprobado TINYINT(1) NOT NULL DEFAULT 0,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 actualizado_por BIGINT UNSIGNED NULL,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_cert_doc_config_edicion FOREIGN KEY(edicion_id) REFERENCES ediciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificados_docentes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 edicion_id INT UNSIGNED NOT NULL,
 docente_id INT UNSIGNED NOT NULL,
 nombre VARCHAR(250) NOT NULL,
 titulo VARCHAR(500) NOT NULL,
 encabezado VARCHAR(120) NOT NULL,
 rol VARCHAR(30) NOT NULL,
 archivo VARCHAR(80) NOT NULL,
 plantilla_sha256 CHAR(64) NOT NULL,
 pdf_sha256 CHAR(64) NOT NULL,
 administrador_id BIGINT UNSIGNED NOT NULL,
 emitido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cert_docente_edicion (edicion_id,docente_id),
 CONSTRAINT fk_cert_doc_edicion FOREIGN KEY(edicion_id) REFERENCES ediciones(id),
 CONSTRAINT fk_cert_doc_docente FOREIGN KEY(docente_id) REFERENCES docentes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se reutilizan los intentos y eventos de correo existentes.
-- Una comunicación de docente referencia su certificado, no una inscripción ficticia.
ALTER TABLE comunicaciones_transaccionales
 MODIFY inscripcion_id BIGINT UNSIGNED NULL,
 ADD certificado_docente_id BIGINT UNSIGNED NULL,
 ADD UNIQUE KEY uq_correo_cert_docente (certificado_docente_id),
 ADD CONSTRAINT fk_correo_cert_docente FOREIGN KEY(certificado_docente_id) REFERENCES certificados_docentes(id);
