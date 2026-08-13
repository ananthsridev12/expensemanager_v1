-- Migration 022: Alert deduplication log for budget and credit card alerts

CREATE TABLE IF NOT EXISTS alert_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50)  NOT NULL,
    ref_key    VARCHAR(200) NOT NULL,
    sent_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_alert (alert_type, ref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
