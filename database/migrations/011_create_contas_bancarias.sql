-- Migration 011: contas_bancarias
-- Contas correntes do FNS vinculadas ao município.

CREATE TABLE IF NOT EXISTS contas_bancarias (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id INT UNSIGNED NOT NULL,
    banco        VARCHAR(10)  NOT NULL,
    agencia      VARCHAR(10),
    conta        VARCHAR(25)  NOT NULL,
    tipo         VARCHAR(60),                -- 'FNS', 'Tesouro Municipal', etc.
    descricao    VARCHAR(150),
    ativa        TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_contas_municipio (municipio_id),

    CONSTRAINT fk_contas_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
