-- Ejecutar antes de desplegar el código. No modifica envíos históricos.
CREATE TABLE IF NOT EXISTS correo_control (
 id TINYINT UNSIGNED PRIMARY KEY,
 desde DATETIME NOT NULL,
 inscripcion_desde BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO correo_control (id,desde,inscripcion_desde)
SELECT 1,NOW(),COALESCE(MAX(id),0)+1 FROM inscripciones;
CREATE TABLE IF NOT EXISTS correo_intentos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 comunicacion_id BIGINT UNSIGNED NOT NULL,
 origen VARCHAR(20) NOT NULL,
 administrador_id BIGINT UNSIGNED NULL,
 resultado VARCHAR(20) NOT NULL DEFAULT 'incierto',
 iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finalizado_en DATETIME NULL,
 INDEX (comunicacion_id),
 CONSTRAINT fk_correo_intento FOREIGN KEY (comunicacion_id) REFERENCES comunicaciones_transaccionales(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS correo_ejecuciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 fin DATETIME NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'ejecutando',
 destinatarios INT UNSIGNED NOT NULL DEFAULT 0,
 enviados INT UNSIGNED NOT NULL DEFAULT 0,
 errores INT UNSIGNED NOT NULL DEFAULT 0,
 INDEX (inicio)
) ENGINE=InnoDB;
