-- Anuncios opt-in separados de los correos transaccionales. No envía correos.
CREATE TABLE IF NOT EXISTS novedades_campanas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 edicion_id INT UNSIGNED NOT NULL UNIQUE,
 asunto VARCHAR(190) NOT NULL,
 introduccion TEXT NOT NULL,
 contenido LONGTEXT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'borrador',
 creado_por BIGINT UNSIGNED NOT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 iniciado_en DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS novedades_destinatarios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 campana_id BIGINT UNSIGNED NOT NULL,
 persona_id INT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL,
 token CHAR(64) NOT NULL UNIQUE,
 estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
 motivo VARCHAR(500) NULL,
 enviado_en DATETIME NULL,
 UNIQUE KEY uq_novedad_email(campana_id,email),
 INDEX idx_novedad_pendientes(campana_id,estado),
 FOREIGN KEY(campana_id) REFERENCES novedades_campanas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS novedades_intentos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 destinatario_id BIGINT UNSIGNED NOT NULL,
 fase VARCHAR(40) NOT NULL DEFAULT 'preparando',
 codigo INT NULL,
 message_id VARCHAR(190) NOT NULL,
 iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 terminado_en DATETIME NULL,
 INDEX idx_novedad_fecha(iniciado_en),
 FOREIGN KEY(destinatario_id) REFERENCES novedades_destinatarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS novedades_bajas (
 email VARCHAR(190) PRIMARY KEY,
 registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS novedades_pruebas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clave VARCHAR(100) NOT NULL UNIQUE,
 estado VARCHAR(20) NOT NULL DEFAULT 'iniciado',
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finalizado_en DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
