-- Migration 023: OAuth 2.0 dynamic client registration + MCP bearer tokens
-- Run once in phpMyAdmin before enabling the MCP endpoint.

CREATE TABLE IF NOT EXISTS oauth_clients (
    id            VARCHAR(80)   NOT NULL PRIMARY KEY,
    name          VARCHAR(200)  NOT NULL DEFAULT 'MCP Client',
    redirect_uris TEXT          NOT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS oauth_codes (
    code                  VARCHAR(128)  NOT NULL PRIMARY KEY,
    client_id             VARCHAR(80)   NOT NULL,
    redirect_uri          VARCHAR(2000) NOT NULL,
    code_challenge        VARCHAR(128)  DEFAULT NULL,
    code_challenge_method VARCHAR(10)   DEFAULT 'S256',
    expires_at            DATETIME      NOT NULL,
    used                  TINYINT(1)    NOT NULL DEFAULT 0,
    created_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client_id (client_id),
    INDEX idx_expires   (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    token_hash   VARCHAR(64)   NOT NULL,
    client_id    VARCHAR(80)   NOT NULL,
    label        VARCHAR(200)  DEFAULT NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP     NULL DEFAULT NULL,
    UNIQUE KEY unique_token (token_hash),
    INDEX idx_client    (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
