-- Migration 010: portarias
-- Portarias do DOU (Diário Oficial da União) scrapeadas.

CREATE TABLE IF NOT EXISTS portarias (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    municipio_id     INT UNSIGNED  NOT NULL,
    numero           VARCHAR(30),
    data_publicacao  DATE,
    secao            TINYINT UNSIGNED,           -- 1, 2 ou 3
    pagina           SMALLINT UNSIGNED,
    orgao            VARCHAR(150),
    titulo           VARCHAR(500)  NOT NULL,
    resumo           TEXT,
    url_dou          VARCHAR(512),
    texto_completo   LONGTEXT,
    palavras_chave   JSON,                        -- array de strings
    relevancia       TINYINT UNSIGNED DEFAULT 0,  -- 0-10 score IA
    lida             TINYINT(1)    NOT NULL DEFAULT 0,
    notificada       TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_portarias_municipio       (municipio_id),
    KEY idx_portarias_data_publicacao (data_publicacao),
    KEY idx_portarias_lida            (lida),

    CONSTRAINT fk_portarias_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Portarias DOU — diario.in.gov.br';
