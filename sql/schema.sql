-- =========================================================================
--  Diário de Bordo CECAPE — esquema do banco de dados (MySQL 8 / MariaDB 10.4+)
--
--  Execute uma única vez:
--      mysql -u root -p < sql/schema.sql
--
--  Depois crie o primeiro administrador:
--      php ferramentas/criar-admin.php
-- =========================================================================

-- Este arquivo está em UTF-8. Sem a linha abaixo, clientes que assumem latin1
-- por padrão (o mysql da linha de comando, entre outros) gravam os acentos
-- com codificação dupla — "Diário" vira "DiÃ¡rio".
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS diario_bordo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE diario_bordo;

-- ------------------------------------------------------------- usuários --
CREATE TABLE IF NOT EXISTS usuarios (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome              VARCHAR(120)  NOT NULL,
  usuario           VARCHAR(60)   NOT NULL,
  email             VARCHAR(160)  NOT NULL,
  senha_hash        VARCHAR(255)  NOT NULL,
  perfil            ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  ativo             TINYINT(1)    NOT NULL DEFAULT 1,
  deve_trocar_senha TINYINT(1)    NOT NULL DEFAULT 0,
  ultimo_acesso     DATETIME      NULL DEFAULT NULL,
  criado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_usuario (usuario),
  UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- atividades --
CREATE TABLE IF NOT EXISTS atividades (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(160)  NOT NULL,
  descricao       TEXT          NULL,
  responsavel     VARCHAR(80)   NULL,
  categoria       VARCHAR(60)   NULL,
  local           VARCHAR(80)   NULL,
  modelo          VARCHAR(30)   NULL,
  inicio_previsto DATETIME      NOT NULL,
  fim_previsto    DATETIME      NOT NULL,
  inicio_real     DATETIME      NULL DEFAULT NULL,
  fim_real        DATETIME      NULL DEFAULT NULL,
  cancelada       TINYINT(1)    NOT NULL DEFAULT 0,
  criado_por      INT UNSIGNED  NULL DEFAULT NULL,
  atualizado_por  INT UNSIGNED  NULL DEFAULT NULL,
  criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_atividades_inicio (inicio_previsto),
  KEY idx_atividades_responsavel (responsavel),
  KEY idx_atividades_categoria (categoria),
  CONSTRAINT fk_atividades_criado_por FOREIGN KEY (criado_por)
    REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_atividades_atualizado_por FOREIGN KEY (atualizado_por)
    REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- fases --
CREATE TABLE IF NOT EXISTS fases (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  atividade_id    BIGINT UNSIGNED NOT NULL,
  ordem           SMALLINT UNSIGNED NOT NULL,
  nome            VARCHAR(120)  NOT NULL,
  peso            DECIMAL(6,2)  NOT NULL DEFAULT 0,
  duracao_min     INT UNSIGNED  NOT NULL DEFAULT 1,
  inicio_previsto DATETIME      NOT NULL,
  fim_previsto    DATETIME      NOT NULL,
  inicio_real     DATETIME      NULL DEFAULT NULL,
  fim_real        DATETIME      NULL DEFAULT NULL,
  observacao      VARCHAR(500)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fases_atividade_ordem (atividade_id, ordem),
  CONSTRAINT fk_fases_atividade FOREIGN KEY (atividade_id)
    REFERENCES atividades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- configurações --
CREATE TABLE IF NOT EXISTS configuracoes (
  chave          VARCHAR(60)  NOT NULL,
  valor          TEXT         NOT NULL,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  atualizado_por INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (chave),
  CONSTRAINT fk_config_usuario FOREIGN KEY (atualizado_por)
    REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------- tentativas de login --
-- Base do bloqueio progressivo contra força bruta.
CREATE TABLE IF NOT EXISTS tentativas_login (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identificador VARCHAR(160) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  sucesso       TINYINT(1)   NOT NULL DEFAULT 0,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tentativas_identificador (identificador, criado_em),
  KEY idx_tentativas_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ auditoria --
-- Trilha de quem fez o quê: exigência básica para um diário de bordo.
CREATE TABLE IF NOT EXISTS auditoria (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED NULL DEFAULT NULL,
  usuario_nome VARCHAR(120) NULL,
  acao         VARCHAR(40)  NOT NULL,
  entidade     VARCHAR(40)  NULL,
  entidade_id  VARCHAR(40)  NULL,
  detalhes     TEXT         NULL,
  ip           VARCHAR(45)  NULL,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_criado (criado_em),
  KEY idx_auditoria_usuario (usuario_id, criado_em),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ configuração inicial --
INSERT INTO configuracoes (chave, valor) VALUES
  ('organizacao', 'CECAPE'),
  ('subtitulo',   'Diário de Bordo — Registro de Atividades'),
  ('jornada',     '{"modo":"comercial","dias":[1,2,3,4,5],"inicio":"08:00","fim":"18:00","pausaAtiva":true,"pausaInicio":"12:00","pausaFim":"13:00"}')
ON DUPLICATE KEY UPDATE chave = chave;

-- =========================================================================
--  Usuário da aplicação com privilégio mínimo.
--  Troque a senha antes de executar e use a MESMA em api/config.php.
-- =========================================================================
-- CREATE USER 'diario_app'@'localhost' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON diario_bordo.* TO 'diario_app'@'localhost';
-- FLUSH PRIVILEGES;
--
-- Observe que o usuário da aplicação NÃO recebe CREATE, DROP, ALTER nem GRANT:
-- mesmo que uma falha permitisse injeção, não haveria como alterar o esquema.
