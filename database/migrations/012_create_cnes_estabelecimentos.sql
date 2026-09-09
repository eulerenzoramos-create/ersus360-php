-- Migration 012: cnes_estabelecimentos
-- Estabelecimentos de saúde (CNES/DATASUS).

CREATE TABLE IF NOT EXISTS cnes_estabelecimentos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED NOT NULL,
    cnes            VARCHAR(10)  NOT NULL,
    nome            VARCHAR(200) NOT NULL,
    nome_fantasia   VARCHAR(200),
    tipo_unidade    VARCHAR(80),
    esfera          ENUM('federal','estadual','municipal','privado') DEFAULT 'municipal',
    cnpj            VARCHAR(18),
    logradouro      VARCHAR(200),
    numero          VARCHAR(10),
    bairro          VARCHAR(80),
    cep             CHAR(9),
    telefone        VARCHAR(20),
    email           VARCHAR(120),
    latitude        DECIMAL(10,7),
    longitude       DECIMAL(10,7),
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    sincronizado_em DATETIME,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cnes_cnes (cnes),
    KEY idx_cnes_municipio (municipio_id),

    CONSTRAINT fk_cnes_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Estabelecimentos CNES — cnes.datasus.gov.br';
