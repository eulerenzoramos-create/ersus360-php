-- Migration 014: job_execucoes
-- Log de execução de todos os cron jobs do sistema.

CREATE TABLE IF NOT EXISTS job_execucoes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id    INT UNSIGNED,
    job_nome        VARCHAR(80)  NOT NULL,         -- 'FnsSync', 'ApsSync', 'PortariasSync', etc.
    status          ENUM('em_andamento','concluido','erro','cancelado') NOT NULL DEFAULT 'em_andamento',
    iniciado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em   DATETIME,
    duracao_ms      INT UNSIGNED,
    registros_proc  INT UNSIGNED DEFAULT 0,
    mensagem        TEXT,
    stacktrace      TEXT,

    PRIMARY KEY (id),
    KEY idx_job_nome    (job_nome),
    KEY idx_job_status  (status),
    KEY idx_job_inicio  (iniciado_em)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de execução de cron jobs';
