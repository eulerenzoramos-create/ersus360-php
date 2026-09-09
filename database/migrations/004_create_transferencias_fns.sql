-- Migration 004: transferencias_fns
-- Repasses do Fundo Nacional de Saúde (consultafns + apifns).
-- chave_unica: hash SHA-256 determinístico para evitar duplicatas.

CREATE TABLE IF NOT EXISTS transferencias_fns (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED  NOT NULL,
    chave_unica     CHAR(64)      NOT NULL,           -- SHA-256 hex
    competencia     DATE          NOT NULL,            -- primeiro dia do mês
    numero_parcela  TINYINT UNSIGNED,
    numero_banco    VARCHAR(10),
    agencia         VARCHAR(10),
    conta_corrente  VARCHAR(20),
    acao            VARCHAR(30),
    programa        VARCHAR(120),
    bloco           VARCHAR(60),
    subprograma     VARCHAR(120),
    componente      VARCHAR(120),
    detalhe_url     VARCHAR(512),                      -- URL scraped no consultafns
    valor_bruto     DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_desconto  DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_liquido   DECIMAL(14,2) NOT NULL DEFAULT 0,
    tipo_operacao   VARCHAR(20),
    situacao        VARCHAR(60),
    data_credito    DATE,
    fonte           ENUM('scraping','api','manual') NOT NULL DEFAULT 'scraping',
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_transferencias_chave (chave_unica),
    KEY idx_transferencias_municipio   (municipio_id),
    KEY idx_transferencias_competencia (competencia),
    KEY idx_transferencias_bloco       (bloco),

    CONSTRAINT fk_transferencias_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Repasses FNS — consultafns.saude.gov.br + apifns.saude.gov.br';
