-- Migration 015: configuracoes
-- Configurações por município (chave-valor JSON).

CREATE TABLE IF NOT EXISTS configuracoes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id INT UNSIGNED,                -- NULL = configuração global
    chave        VARCHAR(80)  NOT NULL,
    valor        JSON,
    descricao    VARCHAR(255),
    criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_config (municipio_id, chave),
    KEY idx_config_municipio (municipio_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Configurações do sistema por município';
