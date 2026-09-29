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
$maxAge = 15 * 60;
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

function requestJson(string $url, array $options = []): array|int {
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize cURL.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $options['headers'] ?? [],
        CURLOPT_POST => ($options['method'] ?? 'GET') === 'POST',
        CURLOPT_POSTFIELDS => $options['body'] ?? null,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Spotify request failed: ' . ($error ?: 'unknown cURL error'));
    }
    if ($status === 204) return 204;
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("Spotify API returned HTTP {$status}: " . substr((string) $body, 0, 500));
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

    $recent = requestJson('https://api.spotify.com/v1/me/player/recently-played?limit=50', [
        'headers' => $authHeaders,
    ]);
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

    $processed = array_fill_keys($history['processed'], true);
    $tz = new DateTimeZone($timeZoneName);
    $now = new DateTimeImmutable('now', $tz);

    foreach (($recent['items'] ?? []) as $item) {
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

    $processedKeys = array_keys($processed);
    if (count($processedKeys) > 100000) $processedKeys = array_slice($processedKeys, -100000);
    $history['processed'] = $processedKeys;

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

    $data = [
        'updated' => $now->format(DATE_ATOM),
        'month' => $now->format('F Y'),
        'listeningMinutes' => [
            'day' => (int) floor($dayMinutes),
            'week' => (int) floor($weekMinutes),
            'month' => (int) floor($monthMinutes),
        ],
        'live' => $live,
        'topArtists' => $topArtists,
        'topTracks' => $topTracks,
    ];

    writeAtomic($historyPath, json_encode($history, JSON_UNESCAPED_SLASHES));
    writeAtomic($dataPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

try {
    $needsUpdate = !is_file($dataPath) || (time() - (int) filemtime($dataPath)) >= $maxAge;
    if ($needsUpdate) {
        $lock = fopen($lockPath, 'c');
        if ($lock !== false && flock($lock, LOCK_EX | LOCK_NB)) {
            try {
                clearstatcache(true, $dataPath);
                $needsUpdate = !is_file($dataPath) || (time() - (int) filemtime($dataPath)) >= $maxAge;
                if ($needsUpdate) {
                    refreshSpotifyStats($dataPath, $historyPath, $configPath, $timeZoneName);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
} catch (Throwable $error) {
    error_log('[Spotify Stats] ' . $error->getMessage());
    // Keep serving the last successful stats when Spotify or the updater is unavailable.
}

jsonResponse($dataPath);
