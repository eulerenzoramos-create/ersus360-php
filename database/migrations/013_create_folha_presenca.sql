-- Migration 013: folha_presenca
-- Presença mensal de funcionários (equivalente ao /tmp/ersus_folha_presenca.json).
-- Persistência em banco elimina a perda de dados no redeploy Railway.

CREATE TABLE IF NOT EXISTS folha_presenca (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED    NOT NULL,
    mes_referencia  DATE            NOT NULL,          -- primeiro dia do mês
    matricula       VARCHAR(20)     NOT NULL,
    nome_funcionario VARCHAR(120)   NOT NULL,
    cargo           VARCHAR(80),
    lotacao         VARCHAR(80),
    dias_uteis      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    dias_presentes  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    dias_ausentes   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    observacao      VARCHAR(300),
    status          ENUM('ativo','afastado','ferias','licenca','rescisao') NOT NULL DEFAULT 'ativo',
    registrado_por  INT UNSIGNED,
    criado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_folha_presenca (municipio_id, mes_referencia, matricula),
    KEY idx_folha_presenca_mes     (mes_referencia),
    KEY idx_folha_presenca_status  (status),

    CONSTRAINT fk_folha_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Presença mensal — substitui /tmp no Railway';
