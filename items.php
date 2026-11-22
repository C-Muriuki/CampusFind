<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

header('Location: ' . rtrim(BASE_URL, '/') . '/pages/items.php', true, 302);
exit;