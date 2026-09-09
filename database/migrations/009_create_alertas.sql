-- Migration 009: alertas
-- Notificações internas geradas pelo sistema (jobs, integrações).

CREATE TABLE IF NOT EXISTS alertas (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id INT UNSIGNED NOT NULL,
    tipo         ENUM('info','aviso','critico','portaria','fns','aps','sistema') NOT NULL DEFAULT 'info',
    titulo       VARCHAR(200) NOT NULL,
    mensagem     TEXT         NOT NULL,
    url_referencia VARCHAR(512),
    lido         TINYINT(1)   NOT NULL DEFAULT 0,
    lido_em      DATETIME,
    lido_por     INT UNSIGNED,
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_alertas_municipio (municipio_id),
    KEY idx_alertas_lido      (lido),
    KEY idx_alertas_tipo      (tipo),

    CONSTRAINT fk_alertas_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
