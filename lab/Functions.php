<?php
declare(strict_types=1);

/**
 * Shared helpers for the lab digitization module.
 * Include this after auth/config.php (which provides $pdo and starts the session).
 */

/**
 * Make sure the user is logged in before showing any lab page.
 * NOTE: adjust the session key below if your auth module stores the
 * logged-in user under a different $_SESSION key than 'user_id'.
 */
function lab_require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit;
    }
}

/**
 * Generate the next sample number for a given year, e.g. "1404-000123".
 * Uses the Jalali(ish) year prefix just as a readable batch marker;
 * change $prefix logic if you want a different numbering scheme.
 */
function lab_generate_sample_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS c FROM samples WHERE sample_number LIKE :pattern"
    );
    $stmt->execute(['pattern' => $year . '-%']);
    $count = (int) $stmt->fetch()['c'];
    $next = $count + 1;
    return sprintf('%s-%06d', $year, $next);
}

/**
 * Fetch recent samples for the listing table on indicator.php
 */
function lab_get_recent_samples(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT id, sample_number, sample_type, entry_date, source_sender, status
         FROM samples
         ORDER BY id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}