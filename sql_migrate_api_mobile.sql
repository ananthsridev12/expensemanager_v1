USE expensemanager;

CREATE TABLE IF NOT EXISTS api_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  pin_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  api_user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  device_name VARCHAR(160) NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_api_sessions_token_hash (token_hash),
  KEY idx_api_sessions_user (api_user_id),
  KEY idx_api_sessions_expires (expires_at),
  CONSTRAINT fk_api_sessions_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO api_users (name, pin_hash, is_active)
SELECT 'Owner', '$2y$10$yCc5WlJIgOhRm3XpO1Y10uyMw1rmvpLNrkbe9G5K6CWkpFyqdBBNW', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM api_users LIMIT 1);
