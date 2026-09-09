-- Migration 001: municipios
-- Tabela central: cada município é uma unidade isolada de dados.
-- Apuí/AM: código IBGE 1300144

CREATE TABLE IF NOT EXISTS municipios (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome            VARCHAR(120) NOT NULL,
    codigo_ibge     CHAR(7)      NOT NULL,
    estado          CHAR(2)      NOT NULL DEFAULT 'AM',
    populacao       INT UNSIGNED,
    competencia_aps VARCHAR(7),          -- 'YYYY-MM' da última competência APS importada
    latitude        DECIMAL(10, 7),
    longitude       DECIMAL(10, 7),
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_municipios_ibge (codigo_ibge)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Municípios atendidos pelo sistema';
