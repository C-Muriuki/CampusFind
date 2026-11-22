-- Stage 5 schema additions.
-- Run once against the CampusFind database.
-- Safe to re-run: every statement is guarded with IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS claims (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id     BIGINT UNSIGNED NOT NULL,
    claimant_id   BIGINT UNSIGNED NOT NULL,
    proof         TEXT            NOT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by   BIGINT UNSIGNED NULL,
    reviewed_at   DATETIME        NULL,
    admin_notes   TEXT            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_claims_report (report_id),
    KEY idx_claims_claimant (claimant_id),
    KEY idx_claims_status (status),
    KEY idx_claims_reviewer (reviewed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address    VARCHAR(45)     NOT NULL,
    email         VARCHAR(190)    NOT NULL,
    successful    TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_ip_time (ip_address, attempted_at),
    KEY idx_login_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;