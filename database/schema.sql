-- CampusFind
-- Stage 3.1: Database schema
-- MySQL 8+ / MariaDB compatible

CREATE DATABASE IF NOT EXISTS campusfind
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE campusfind;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_security;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS contact_submissions;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    student_number VARCHAR(60) NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    account_status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    email_verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (account_status)
) ENGINE=InnoDB;

-- 2. Item categories
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Lost and found reports
CREATE TABLE reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    item_type ENUM('lost', 'found') NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(180) NOT NULL,
    incident_date DATE NOT NULL,
    image_path VARCHAR(255) NULL,
    contact_phone VARCHAR(30) NULL,
    contact_email VARCHAR(190) NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'resolved',
        'archived'
    ) NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_reports_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reports_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_reports_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_reports_type_status (item_type, status),
    INDEX idx_reports_category (category_id),
    INDEX idx_reports_location (location),
    INDEX idx_reports_date (incident_date),
    INDEX idx_reports_created (created_at),
    FULLTEXT INDEX ft_reports_search (item_name, description, location)
) ENGINE=InnoDB;

-- 3b. Claim submissions against a found report
CREATE TABLE claims (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id     BIGINT UNSIGNED NOT NULL,
    claimant_id   BIGINT UNSIGNED NOT NULL,
    proof         TEXT            NOT NULL,
    status        ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by   BIGINT UNSIGNED NULL,
    reviewed_at   DATETIME        NULL,
    admin_notes   TEXT            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_claims_report (report_id),
    INDEX idx_claims_claimant (claimant_id),
    INDEX idx_claims_status (status),
    INDEX idx_claims_reviewer (reviewed_by)
) ENGINE=InnoDB;

-- 4. Contact form submissions
CREATE TABLE contact_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'resolved')
        NOT NULL DEFAULT 'new',
    admin_response TEXT NULL,
    responded_by BIGINT UNSIGNED NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_contact_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_contact_admin
        FOREIGN KEY (responded_by) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_contact_status (status),
    INDEX idx_contact_created (created_at)
) ENGINE=InnoDB;

-- 5. Notifications
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL DEFAULT 'general',
    related_report_id BIGINT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_notifications_report
        FOREIGN KEY (related_report_id) REFERENCES reports(id)
        ON DELETE SET NULL,

    INDEX idx_notifications_user_read (user_id, is_read),
    INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB;

-- 6. Activity logs
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_activity_user (user_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

-- 7. Password reset tokens
CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_password_reset_expiry (expires_at)
) ENGINE=InnoDB;

-- 7b. Login rate limiting (raw attempt log, used by Stage 5 attemptLogin()).
CREATE TABLE login_attempts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address    VARCHAR(45)     NOT NULL,
    email         VARCHAR(190)    NOT NULL,
    successful    TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_login_ip_time (ip_address, attempted_at),
    INDEX idx_login_attempted (attempted_at)
) ENGINE=InnoDB;

-- 7c. Login rate limiting with hashed identifiers (Stage 7.2, security_helpers.php).
CREATE TABLE login_security (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_hash        CHAR(64)        NOT NULL,
    ip_hash           CHAR(64)        NOT NULL,
    attempts          INT UNSIGNED    NOT NULL DEFAULT 0,
    first_attempt_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until      DATETIME        NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY unique_login_identity (email_hash, ip_hash),
    INDEX idx_login_locked_until (locked_until),
    INDEX idx_login_first_attempt (first_attempt_at)
) ENGINE=InnoDB;

-- 8. Remember-me tokens
CREATE TABLE remember_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_remember_expiry (expires_at),
    INDEX idx_remember_user (user_id)
) ENGINE=InnoDB;