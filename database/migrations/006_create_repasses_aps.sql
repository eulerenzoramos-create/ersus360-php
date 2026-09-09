-- Migration 006: repasses_aps
-- Repasses APS (e-Gestor): PAB fixo, variável, NASF, SAÚDE BUCAL, etc.
-- Blocos de financiamento conforme Portaria 3.992/2017.

CREATE TABLE IF NOT EXISTS repasses_aps (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED  NOT NULL,
    competencia     DATE          NOT NULL,           -- primeiro dia do mês
    bloco           VARCHAR(80)   NOT NULL,           -- 'Atenção Básica', 'Média e Alta Complexidade', etc.
    componente      VARCHAR(120),
    subcomponente   VARCHAR(120),
    valor_federal   DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_estadual  DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_municipal DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_total     DECIMAL(14,2) GENERATED ALWAYS AS (valor_federal + valor_estadual + valor_municipal) STORED,
    situacao        VARCHAR(60),
    data_credito    DATE,
    fonte           VARCHAR(60)   DEFAULT 'egestor',
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_repasses_aps (municipio_id, competencia, bloco, componente),
    KEY idx_repasses_aps_competencia (competencia),

    CONSTRAINT fk_repasses_aps_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Repasses APS — egestorab.saude.gov.br';
