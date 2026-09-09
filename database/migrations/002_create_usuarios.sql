-- Migration 002: usuarios
-- 18 perfis conforme PERMISSOES do sistema Python original.

CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    municipio_id  INT UNSIGNED,
    nome          VARCHAR(120) NOT NULL,
    email         VARCHAR(180) NOT NULL,
    senha_hash    VARCHAR(255) NOT NULL,
    perfil        ENUM(
                    'superadmin','admin','gestor','coordenador',
                    'enfermeiro','medico','tecnico_aps','acs',
                    'odontologia','farmaceutico','vigilancia',
                    'financeiro','contabilidade','planejamento',
                    'auditoria','prefeito','conselho','consulta'
                  ) NOT NULL DEFAULT 'consulta',
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acesso DATETIME,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_municipio (municipio_id),
    KEY idx_usuarios_perfil    (perfil),

    CONSTRAINT fk_usuarios_municipio
        FOREIGN KEY (municipio_id) REFERENCES municipios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuários do sistema — bcrypt cost=12';
