-- Conserva el resultado del correo; solo retira el aviso de atención.
CREATE TABLE correo_descartes (
 comunicacion_id BIGINT UNSIGNED PRIMARY KEY,
 version CHAR(64) NOT NULL,
 administrador_id BIGINT UNSIGNED NOT NULL,
 descartado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_correo_descarte FOREIGN KEY(comunicacion_id) REFERENCES comunicaciones_transaccionales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
