<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$dataPath = __DIR__ . '/data.json';
$historyPath = __DIR__ . '/listening.json';
$lockPath = __DIR__ . '/.spotify-update.lock';
$configPath = __DIR__ . '/.spotify-config.php';
$maxAge = 5 * 60;
$timeZoneName = 'Europe/Amsterdam';

function jsonResponse(string $path): void {
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['error' => 'Stats data not found'], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $data = file_get_contents($path);
    if ($data === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to read stats data'], JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo $data;
}

function appendQuotaLog(string $url, int $status, ?string $retryAfter, string $body): void {
    $path = __DIR__ . '/quota-log.json';
    $logs = [];
    if (is_file($path)) {
        $parsed = json_decode((string) file_get_contents($path), true);
        if (is_array($parsed)) $logs = $parsed;
    }

    $logs[] = [
        'timestamp' => gmdate(DATE_ATOM),
        'endpoint' => parse_url($url, PHP_URL_PATH) ?: $url,
        'status' => $status,
        'retryAfter' => $retryAfter !== null && ctype_digit($retryAfter) ? (int) $retryAfter : null,
        'message' => trim((string) ($body !== '' ? (json_decode($body, true)['error']['message'] ?? $body) : 'Spotify quota exceeded')),
    ];

    // Keep the log useful without allowing it to grow forever.
    $logs = array_slice($logs, -100);
    writeQuotaLog($path, $logs);
}

function writeQuotaLog(string $path, array $logs): void {
    $tmp = $path . '.tmp';
    $json = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
        @rename($tmp, $path);
    } else {
        @unlink($tmp);
    }
}

function requestJson(string $url, array $options = []): array|int {
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize cURL.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => ($options['follow_redirects'] ?? true),
        CURLOPT_HTTPHEADER => $options['headers'] ?? [],
        CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET',
        CURLOPT_POST => ($options['method'] ?? 'GET') === 'POST',
        CURLOPT_POSTFIELDS => $options['body'] ?? null,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    $responseHeaders = $headerSize > 0 ? substr((string) $response, 0, $headerSize) : '';
    $body = $headerSize > 0 ? substr((string) $response, $headerSize) : $response;
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Spotify request failed: ' . ($error ?: 'unknown cURL error'));
    }
    if ($status === 204) return 204;
    if ($status === 429) {
        $retryAfter = null;
        if (preg_match('/^Retry-After:\s*(.+)$/im', $responseHeaders, $match)) {
            $retryAfter = trim($match[1]);
        }
        appendQuotaLog($url, $status, $retryAfter, (string) $body);
        throw new RuntimeException("Spotify quota exceeded (HTTP 429).");
    }
    if ($status < 200 || $status >= 300) {
        $endpoint = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        $message = trim((string) $body);
        throw new RuntimeException("Spotify API returned HTTP {$status} for {$method} {$endpoint}: " . substr($message, 0, 500));
    }
    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Spotify returned invalid JSON.');
    }
    return $json;
}

function writeAtomic(string $path, string $contents): void {
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $contents, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to write ' . basename($path));
    }
}

function localDate(DateTimeImmutable $date): string {
    return $date->format('Y-m-d');
}

function startOfLocalWeek(DateTimeImmutable $date): string {
    return $date->modify('monday this week')->format('Y-m-d');
}

function refreshSpotifyStats(
    string $dataPath,
    string $historyPath,
    string $configPath,
    string $timeZoneName
): void {
    $config = is_file($configPath) ? require $configPath : [];
    if (!is_array($config)) $config = [];

    $clientId = getenv('SPOTIFY_CLIENT_ID') ?: ($config['client_id'] ?? '');
    $refreshToken = getenv('SPOTIFY_REFRESH_TOKEN') ?: ($config['refresh_token'] ?? '');
    $configuredTimeZone = getenv('STATS_TIMEZONE') ?: ($config['timezone'] ?? $timeZoneName);
    if ($configuredTimeZone) $timeZoneName = $configuredTimeZone;

    if (!$clientId || !$refreshToken) {
        throw new RuntimeException('Spotify credentials are not configured.');
    }

    $token = requestJson('https://accounts.spotify.com/api/token', [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        'body' => http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
        ]),
    ]);

    if (!is_array($token) || empty($token['access_token'])) {
        throw new RuntimeException('Spotify token refresh returned no access token.');
    }

    $authHeaders = [
        'Authorization: Bearer ' . $token['access_token'],
        'Accept: application/json',
    ];

    // Backfill all history Spotify makes available using before-cursor pagination.
    // Work in bounded chunks so a single web/cron run cannot time out. The cursor
    // is persisted in listening.json and the next cron run continues further back.
    $historyForBackfill = [];
    if (is_file($historyPath)) {
        $parsedHistory = json_decode((string) file_get_contents($historyPath), true);
        if (is_array($parsedHistory)) $historyForBackfill = $parsedHistory;
    }

    $recent = requestJson('https://api.spotify.com/v1/me/player/recently-played?limit=50', [
        'headers' => $authHeaders,
    ]);
    $recentItems = $recent['items'] ?? [];
    $backfillComplete = ($historyForBackfill['backfillComplete'] ?? false) === true;
    $backfillCursor = isset($historyForBackfill['backfillCursor']) ? (int) $historyForBackfill['backfillCursor'] : 0;

    if (!$backfillComplete && $recentItems) {
        if ($backfillCursor <= 0) {
            $oldestInitial = $recentItems[count($recentItems) - 1] ?? null;
            if (!empty($oldestInitial['played_at'])) {
                $backfillCursor = (new DateTimeImmutable($oldestInitial['played_at']))->getTimestamp() * 1000;
            }
        }

        $backfillPagesThisRun = 0;
        $maxBackfillPagesPerRun = 10;
        while ($backfillCursor > 0 && $backfillPagesThisRun < $maxBackfillPagesPerRun) {
            $page = requestJson('https://api.spotify.com/v1/me/player/recently-played?limit=50&before=' . $backfillCursor, [
                'headers' => $authHeaders,
            ]);
            $pageItems = $page['items'] ?? [];
            if (!$pageItems) {
                $backfillComplete = true;
                break;
            }

            $recentItems = array_merge($recentItems, $pageItems);
            $oldestPageItem = $pageItems[count($pageItems) - 1] ?? null;
            if (empty($oldestPageItem['played_at'])) {
                $backfillComplete = true;
                break;
            }

            $nextCursor = (new DateTimeImmutable($oldestPageItem['played_at']))->getTimestamp() * 1000;
            if ($nextCursor >= $backfillCursor) {
                // Prevent an infinite loop if the API returns a repeated page.
                break;
            }
            $backfillCursor = $nextCursor;
            $backfillPagesThisRun++;

            if (count($pageItems) < 50) {
                $backfillComplete = true;
                break;
            }
        }

        // Deduplicate pages by playback timestamp + track ID before processing.
        $recentItems = array_values(array_reduce($recentItems, static function (array $carry, array $item): array {
            $key = (string) ($item['played_at'] ?? '') . '|' . (string) ($item['track']['id'] ?? '');
            if ($key !== '|') $carry[$key] = $item;
            return $carry;
        }, []));
    } else {
        $backfillComplete = $backfillComplete || !$recentItems;
    }

    try {
        $currentlyPlaying = requestJson('https://api.spotify.com/v1/me/player/currently-playing', [
            'headers' => $authHeaders,
        ]);
        if ($currentlyPlaying === 204) $currentlyPlaying = null;
    } catch (Throwable) {
        $currentlyPlaying = null;
    }

    $data = [];
    if (is_file($dataPath)) {
        $parsed = json_decode((string) file_get_contents($dataPath), true);
        if (is_array($parsed)) $data = $parsed;
    }

    $history = ['processed' => [], 'days' => []];
    if (is_file($historyPath)) {
        $parsed = json_decode((string) file_get_contents($historyPath), true);
        if (is_array($parsed)) $history = $parsed;
    }
    $history['processed'] ??= [];
    $history['days'] ??= [];
    $history['backfillCursor'] = $backfillCursor;
    $history['backfillComplete'] = $backfillComplete;

    $processed = array_fill_keys($history['processed'], true);
    $tz = new DateTimeZone($timeZoneName);
    $now = new DateTimeImmutable('now', $tz);

    foreach ($recentItems as $item) {
        $track = $item['track'] ?? null;
        $playedAt = $item['played_at'] ?? null;
        if (!is_array($track) || empty($track['id']) || !$playedAt) continue;

        $key = $playedAt . '|' . $track['id'];
        if (isset($processed[$key])) continue;

        try {
            $played = (new DateTimeImmutable($playedAt))->setTimezone($tz);
        } catch (Throwable) {
            continue;
        }

        $date = localDate($played);
        if (!isset($history['days'][$date]) || !is_array($history['days'][$date])) {
            $history['days'][$date] = ['minutes' => 0, 'tracks' => []];
        }

        $history['days'][$date]['minutes'] += ((float) ($track['duration_ms'] ?? 0)) / 60000;
        $artist = $track['artists'][0] ?? [];
        $trackId = (string) $track['id'];

        if (!isset($history['days'][$date]['tracks'][$trackId])) {
            $history['days'][$date]['tracks'][$trackId] = [
                'name' => $track['name'] ?? 'Unknown track',
                'artist' => $artist['name'] ?? 'Unknown artist',
                'url' => $track['external_urls']['spotify'] ?? ('https://open.spotify.com/track/' . $trackId),
                'artistUrl' => $artist['external_urls']['spotify'] ?? '#',
                'plays' => 0,
            ];
        }
        $history['days'][$date]['tracks'][$trackId]['plays']++;
        $processed[$key] = true;
    }

    $history['processed'] = array_keys($processed);

    $today = $now->format('Y-m-d');
    $monthKey = $now->format('Y-m');
    $weekStart = startOfLocalWeek($now);
    $dayMinutes = 0;
    $weekMinutes = 0;
    $monthMinutes = 0;
    $artists = [];
    $tracks = [];

    foreach ($history['days'] as $date => $day) {
        if (!is_array($day)) continue;
        $minutes = (float) ($day['minutes'] ?? 0);
        if ($date === $today) $dayMinutes += $minutes;
        if ($date >= $weekStart && $date <= $today) $weekMinutes += $minutes;
        if (str_starts_with($date, $monthKey)) {
            $monthMinutes += $minutes;
            foreach (($day['tracks'] ?? []) as $id => $track) {
                if (!is_array($track)) continue;
                if (!isset($tracks[$id])) {
                    $tracks[$id] = $track;
                    $tracks[$id]['plays'] = 0;
                }
                $tracks[$id]['plays'] += (int) ($track['plays'] ?? 0);

                $artistName = $track['artist'] ?? 'Unknown artist';
                if (!isset($artists[$artistName])) {
                    $artists[$artistName] = [
                        'name' => $artistName,
                        'url' => $track['artistUrl'] ?? '#',
                        'plays' => 0,
                    ];
                }
                $artists[$artistName]['plays'] += (int) ($track['plays'] ?? 0);
            }
        }
    }

    usort($artists, fn($a, $b) => $b['plays'] <=> $a['plays']);
    usort($tracks, fn($a, $b) => $b['plays'] <=> $a['plays']);

    $topArtists = array_map(
        fn($item) => ['name' => $item['name'], 'url' => $item['url']],
        array_slice($artists, 0, 5)
    );
    $topTracks = array_map(
        fn($item) => [
            'name' => ($item['name'] ?? 'Unknown track') . ' — ' . ($item['artist'] ?? 'Unknown artist'),
            'url' => $item['url'] ?? '#',
        ],
        array_slice($tracks, 0, 5)
    );

    $live = null;
    if (is_array($currentlyPlaying) && !empty($currentlyPlaying['item']['id'])) {
        $track = $currentlyPlaying['item'];
        $progressMs = max(0, (int) ($currentlyPlaying['progress_ms'] ?? 0));
        $durationMs = max($progressMs, (int) ($track['duration_ms'] ?? $progressMs));
        $live = [
            'isPlaying' => ($currentlyPlaying['is_playing'] ?? false) === true,
            'trackId' => $track['id'],
            'progressMs' => $progressMs,
            'durationMs' => $durationMs,
            'fetchedAt' => $now->format(DATE_ATOM),
        ];
    }

    // Calculate all-time listening records from the collected day-by-day history.
    $yearTotals = [];
    $monthTotals = [];
    $weekTotals = [];
    $dayTotals = [];
    foreach ($history['days'] as $date => $day) {
        if (!is_array($day) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string) $date)) continue;
        $seconds = max(0, (int) round(((float) ($day['minutes'] ?? 0)) * 60));
        $dayTotals[$date] = ($dayTotals[$date] ?? 0) + $seconds;
        $year = substr($date, 0, 4);
        $month = substr($date, 0, 7);
        $yearTotals[$year] = ($yearTotals[$year] ?? 0) + $seconds;
        $monthTotals[$month] = ($monthTotals[$month] ?? 0) + $seconds;
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if ($dateObj) {
            $weekStartDate = $dateObj->modify('monday this week')->format('Y-m-d');
            $weekTotals[$weekStartDate] = ($weekTotals[$weekStartDate] ?? 0) + $seconds;
        }
    }

    $bestRecord = static function (array $totals, callable $label): array {
        if (!$totals) return ['seconds' => 0, 'period' => 'NO DATA YET'];
        arsort($totals, SORT_NUMERIC);
        $periodKey = (string) array_key_first($totals);
        return ['seconds' => (int) $totals[$periodKey], 'period' => $label($periodKey)];
    };
    $records = [
        'year' => $bestRecord($yearTotals, static fn($key) => $key),
        'month' => $bestRecord($monthTotals, static function ($key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m', $key);
            return $date ? strtoupper($date->format('F Y')) : $key;
        }),
        'week' => $bestRecord($weekTotals, static function ($key) use ($tz) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $key, $tz);
            if (!$date) return $key;
            $weekOfMonth = (int) ceil((int) $date->format('j') / 7);
            return strtoupper($date->format('F Y') . ' / WEEK ' . $weekOfMonth);
        }),
        'day' => $bestRecord($dayTotals, static function ($key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $key);
            return $date ? strtoupper($date->format('d/m/Y')) : $key;
        }),
    ];

    $data = [
        'updated' => $now->format(DATE_ATOM),
        'month' => $now->format('F Y'),
        'listeningMinutes' => [
            'day' => (int) floor($dayMinutes),
            'week' => (int) floor($weekMinutes),
            'month' => (int) floor($monthMinutes),
        ],
        'live' => $live,
        'records' => $records,
        'topArtists' => $topArtists,
        'topTracks' => $topTracks,
    ];

    writeAtomic($historyPath, json_encode($history, JSON_UNESCAPED_SLASHES));
    writeAtomic($dataPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

try {
    $forceUpdate = PHP_SAPI === 'cli';
    if ($forceUpdate) echo '[Spotify Stats] Starting update...' . PHP_EOL;
    $needsUpdate = $forceUpdate || !is_file($dataPath) || (time() - (int) filemtime($dataPath)) >= $maxAge;
    if ($needsUpdate) {
        $lock = fopen($lockPath, 'c');
        if ($lock !== false && flock($lock, LOCK_EX | LOCK_NB)) {
            try {
                clearstatcache(true, $dataPath);
                $needsUpdate = $forceUpdate || !is_file($dataPath) || (time() - (int) filemtime($dataPath)) >= $maxAge;
                if ($needsUpdate) {
                    refreshSpotifyStats($dataPath, $historyPath, $configPath, $timeZoneName);
                    if ($forceUpdate) {
                        $updated = json_decode((string) @file_get_contents($dataPath), true)['updated'] ?? null;
                        if ($updated) {
                            $stamp = (new DateTimeImmutable($updated))->setTimezone(new DateTimeZone('Europe/Amsterdam'));
                            echo '[Spotify Stats] Data updated: ' . $stamp->format('d-m-Y H:i:s') . ' (Europe/Amsterdam Time)' . PHP_EOL;
                        } else {
                            echo '[Spotify Stats] Data updated successfully.' . PHP_EOL;
                        }
                    }
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
} catch (Throwable $error) {
    error_log('[Spotify Stats] ' . $error->getMessage());
    if (PHP_SAPI === 'cli') {
        echo '[Spotify Stats] ERROR: ' . $error->getMessage() . PHP_EOL;
    }
    // Keep serving the last successful stats when Spotify or the updater is unavailable.
}

if (PHP_SAPI !== 'cli') {
    jsonResponse($dataPath);
}
