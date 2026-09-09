-- Migration 003: audit_log
-- Trilha de auditoria imutável (apenas INSERT, nunca UPDATE/DELETE).

CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED,
    acao        VARCHAR(30)  NOT NULL,           -- CREATE, UPDATE, DELETE, LOGIN, etc.
    tabela      VARCHAR(60),
    registro_id INT UNSIGNED,
    detalhe     TEXT,
    ip          VARCHAR(45),                      -- suporta IPv6
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_usuario  (usuario_id),
    KEY idx_audit_tabela   (tabela, registro_id),
    KEY idx_audit_criado   (criado_em)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log imutável de ações — nunca apagar registros';
