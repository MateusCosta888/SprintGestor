-- SprintG — schema do sistema de contas
-- Corre no phpMyAdmin do InfinityFree (seleciona a base de dados criada no VistaPanel).
-- utf8mb4 é obrigatório: os dados contêm emojis (ícones de projeto/sprint/tarefa).

CREATE TABLE IF NOT EXISTS accounts (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code_hash        VARCHAR(255) NOT NULL,            -- bcrypt do código de acesso (60 chars, com folga)
  code_fingerprint CHAR(64)     NOT NULL,            -- HMAC-SHA256(hex) do código normalizado; lookup indexado
  display_name     VARCHAR(40)  NULL,                -- nome de utilizador opcional, editável
  agent_hash       CHAR(64)     NULL UNIQUE,         -- sha256 do token de Acesso de IA (NULL = sem link)
  agent_created_at DATETIME     NULL,                -- quando o link atual foi gerado
  token_version    INT UNSIGNED NOT NULL DEFAULT 1,  -- bump em regenerate = mata todas as sessões
  data             MEDIUMTEXT   NULL,                -- blob JSON de appData (NULL = nunca guardado)
  rev              INT UNSIGNED NOT NULL DEFAULT 0,  -- concorrência otimista
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fingerprint (code_fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  id            CHAR(64)     NOT NULL,               -- sha256(raw token) em hex; PK => lookup O(1)
  account_id    INT UNSIGNED NOT NULL,
  token_version INT UNSIGNED NOT NULL,               -- tem de ser igual a accounts.token_version
  expires_at    INT UNSIGNED NOT NULL,               -- unix epoch
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_account (account_id),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  ip           VARBINARY(16) NOT NULL,               -- inet_pton (IPv4/IPv6)
  window_start INT UNSIGNED  NOT NULL,               -- unix epoch do início da janela fixa
  attempts     INT UNSIGNED  NOT NULL DEFAULT 1,
  PRIMARY KEY (ip, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ai_ops — diário das chamadas do agente (medidor, histórico e merge por diário).
CREATE TABLE IF NOT EXISTS ai_ops (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id INT UNSIGNED NOT NULL,
  ts        DATETIME     NOT NULL,
  op        VARCHAR(16)  NOT NULL,                   -- manual | board | create | status
  target    VARCHAR(160) NULL,                       -- "sprint:Fundação" etc.
  ip        VARBINARY(16) NULL,
  item_rev  INT UNSIGNED NULL,                       -- rev do servidor após um create (0/NULL p/ não-create)
  item_id   VARCHAR(32)  NULL,                       -- id do item criado (para o merge)
  PRIMARY KEY (id),
  KEY idx_account_ts (account_id, ts),
  KEY idx_account_itemrev (account_id, item_rev)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- >>> Contas JÁ EXISTENTES (executa uma vez no phpMyAdmin; em BD nova não precisas):
-- ALTER TABLE accounts ADD COLUMN agent_hash CHAR(64) NULL UNIQUE AFTER display_name;
-- ALTER TABLE accounts ADD COLUMN agent_created_at DATETIME NULL AFTER agent_hash;
