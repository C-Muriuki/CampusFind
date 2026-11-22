<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verifyCsrf()) {
    http_response_code(403);
    exit('Invalid request.');
}

logoutUser();

redirect('index.php');