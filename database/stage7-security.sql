-- Stage 7.2: login rate-limiting table with hashed identifiers.
-- Additive migration. Does not drop or recreate existing tables.
-- Uses a separate table name (login_security) to avoid conflicting with
-- the existing login_attempts table from Stage 5.

CREATE TABLE IF NOT EXISTS login_security (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,

    attempts INT UNSIGNED NOT NULL DEFAULT 0,

    first_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_login_identity (email_hash, ip_hash),
    INDEX idx_login_locked_until (locked_until),
    INDEX idx_login_first_attempt (first_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;