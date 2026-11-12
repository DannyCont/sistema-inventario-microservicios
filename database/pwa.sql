CREATE TABLE IF NOT EXISTS solicitudes_movimiento (
    usuario_id INT UNSIGNED NOT NULL,
    solicitud_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    huella CHAR(64) CHARACTER SET ascii NOT NULL,
    existencia_actual INT UNSIGNED NULL,
    PRIMARY KEY (usuario_id, solicitud_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suscripciones_push (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    endpoint_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    suscripcion JSON NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entregas_push (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    suscripcion_id BIGINT UNSIGNED NOT NULL,
    movimiento_id BIGINT UNSIGNED NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    disponible_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (disponible_en, id),
    FOREIGN KEY (suscripcion_id) REFERENCES suscripciones_push(id) ON DELETE CASCADE
) ENGINE=InnoDB;
