-- Migration 008: indicadores
-- Indicadores de saúde (SIAPS, e-SUS, RNDS).

CREATE TABLE IF NOT EXISTS indicadores (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED  NOT NULL,
    competencia     DATE          NOT NULL,
    codigo          VARCHAR(20)   NOT NULL,   -- ex: 'COBER_ESF', 'PRENATAL_7C', 'HIPERTENSO'
    nome            VARCHAR(150)  NOT NULL,
    categoria       VARCHAR(60),              -- 'APS', 'Vigilância', 'Hospitalar'
    valor_absoluto  DECIMAL(14,4),
    valor_meta      DECIMAL(14,4),
    percentual      DECIMAL(6,2),
    situacao        ENUM('adequado','critico','alerta','sem_dado') NOT NULL DEFAULT 'sem_dado',
    fonte           VARCHAR(60),              -- 'SIAPS', 'eSUS', 'RNDS', 'CNES'
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_indicadores (municipio_id, competencia, codigo),
    KEY idx_indicadores_competencia (competencia),
    KEY idx_indicadores_situacao    (situacao),

    CONSTRAINT fk_indicadores_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
