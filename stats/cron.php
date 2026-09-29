<?php
declare(strict_types=1);

// CLI cron entry point for Spotify stats.
// Run every 5 minutes from Plesk/Cloud86.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$dataPath = __DIR__ . '/data.json';
$historyPath = __DIR__ . '/listening.json';
$lockPath = __DIR__ . '/.spotify-update.lock';
$configPath = __DIR__ . '/.spotify-config.php';
$timeZoneName = 'Europe/Amsterdam';

require_once __DIR__ . '/live.php';
