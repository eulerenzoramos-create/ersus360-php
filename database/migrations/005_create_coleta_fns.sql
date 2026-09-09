-- Migration 005: coleta_fns
-- Histórico de execuções do job de coleta FNS.

CREATE TABLE IF NOT EXISTS coleta_fns (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED NOT NULL,
    competencia     DATE         NOT NULL,
    registros_novos INT UNSIGNED NOT NULL DEFAULT 0,
    registros_total INT UNSIGNED NOT NULL DEFAULT 0,
    duracao_segundos SMALLINT UNSIGNED,
    status          ENUM('ok','erro','parcial') NOT NULL DEFAULT 'ok',
    mensagem        TEXT,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_coleta_fns_municipio   (municipio_id),
    KEY idx_coleta_fns_competencia (competencia),

    CONSTRAINT fk_coleta_fns_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de execuções do job de coleta FNS';
