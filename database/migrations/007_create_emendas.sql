-- Migration 007: emendas
-- Emendas parlamentares / propostas InvestSUS.

CREATE TABLE IF NOT EXISTS emendas (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    municipio_id        INT UNSIGNED  NOT NULL,

    -- Identificação
    numero_emenda       VARCHAR(30),
    autor               VARCHAR(150),
    parlamentar         VARCHAR(150),
    partido             VARCHAR(20),
    estado_parlamentar  CHAR(2),
    tipo                ENUM('individual','bancada','comissao','relator') NOT NULL DEFAULT 'individual',

    -- Objeto
    objeto              TEXT,
    programa            VARCHAR(120),
    acao                VARCHAR(120),
    funcao              VARCHAR(80),
    subfuncao           VARCHAR(80),

    -- Valores (DECIMAL para precisão monetária)
    valor_autorizado    DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_empenhado     DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_liquidado     DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_pago          DECIMAL(14,2) NOT NULL DEFAULT 0,

    -- Execução
    fase                ENUM('proposta','aprovada','empenhada','liquidada','paga','cancelada') NOT NULL DEFAULT 'proposta',
    ano_orcamentario    SMALLINT UNSIGNED,
    data_empenho        DATE,
    data_pagamento      DATE,

    -- Controle interno
    prioridade          TINYINT(1)    NOT NULL DEFAULT 0,  -- destaque no dashboard
    observacoes         TEXT,

    criado_em           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_emendas_municipio (municipio_id),
    KEY idx_emendas_fase      (fase),
    KEY idx_emendas_ano       (ano_orcamentario),

    CONSTRAINT fk_emendas_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Emendas parlamentares — InvestSUS / SIOPS';
