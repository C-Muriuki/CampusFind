<?php
declare(strict_types=1);

/**
 * Stage 7.2: database-backed login rate limiting with hashed identifiers.
 *
 * Uses a separate login_security table (not the existing login_attempts
 * table from Stage 5) to avoid schema conflicts. Stores SHA-256 hashes of
 * the lowercased email and client IP instead of raw values for privacy.
 *
 * Five failures within fifteen minutes trigger a fifteen-minute lock.
 */

function cfSecurityHash(string $value): string
{
    return hash('sha256', $value);
}

function cfLoginIsBlocked(PDO $pdo, string $email, string $ip): bool
{
    $emailHash = cfSecurityHash(strtolower(trim($email)));
    $ipHash    = cfSecurityHash($ip);

    $stmt = $pdo->prepare(
        'SELECT locked_until
         FROM login_security
         WHERE email_hash = :email_hash
           AND ip_hash = :ip_hash
         LIMIT 1'
    );

    $stmt->execute([
        ':email_hash' => $emailHash,
        ':ip_hash'    => $ipHash,
    ]);

    $lockedUntil = $stmt->fetchColumn();

    if (!$lockedUntil) {
        return false;
    }

    return strtotime((string) $lockedUntil) > time();
}

function cfRecordFailedLogin(PDO $pdo, string $email, string $ip): void
{
    $emailHash = cfSecurityHash(strtolower(trim($email)));
    $ipHash    = cfSecurityHash($ip);

    $stmt = $pdo->prepare(
        "INSERT INTO login_security (
            email_hash,
            ip_hash,
            attempts,
            first_attempt_at,
            locked_until
        )
        VALUES (
            :email_hash,
            :ip_hash,
            1,
            NOW(),
            NULL
        )
        ON DUPLICATE KEY UPDATE
            locked_until = CASE
                WHEN first_attempt_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    THEN NULL
                WHEN attempts >= 4
                    THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                ELSE locked_until
            END,
            attempts = CASE
                WHEN first_attempt_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    THEN 1
                ELSE attempts + 1
            END,
            first_attempt_at = CASE
                WHEN first_attempt_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    THEN NOW()
                ELSE first_attempt_at
            END"
    );

    $stmt->execute([
        ':email_hash' => $emailHash,
        ':ip_hash'    => $ipHash,
    ]);
}

function cfClearLoginAttempts(PDO $pdo, string $email, string $ip): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM login_security
         WHERE email_hash = :email_hash
           AND ip_hash = :ip_hash'
    );

    $stmt->execute([
        ':email_hash' => cfSecurityHash(strtolower(trim($email))),
        ':ip_hash'    => cfSecurityHash($ip),
    ]);
}