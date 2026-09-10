<?php
// Spotify statistics collector for Cloud86 scheduled tasks.
// Reads secrets from the root .env and writes stats/data.json + stats/listening.json.

declare(strict_types=1);

$statsDir = __DIR__;
$rootDir = dirname($statsDir);
$envPath = $rootDir . DIRECTORY_SEPARATOR . '.env';

if (is_file($envPath) && is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) continue;
        $key = $m[1];
        $value = trim($m[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

$clientId = getenv('SPOTIFY_CLIENT_ID');
$refreshToken = getenv('SPOTIFY_REFRESH_TOKEN');

if (!$clientId || !$refreshToken) {
    throw new RuntimeException('Missing SPOTIFY_CLIENT_ID or SPOTIFY_REFRESH_TOKEN in environment or root .env.');
}

function httpRequest(string $url, array $options = []): array {
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Could not initialize cURL.');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $options['headers'] ?? [],
        CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET',
        CURLOPT_POSTFIELDS => $options['body'] ?? null,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $body];
}

[$status, $tokenBody] = httpRequest('https://accounts.spotify.com/api/token', [
    'method' => 'POST',
    'headers' => [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientId),
    ],
    'body' => http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id' => $clientId,
    ]),
]);

// Spotify accepts client_id in the body for this flow, so retry without Basic auth if needed.
if ($status < 200 || $status >= 300) {
    [$status, $tokenBody] = httpRequest('https://accounts.spotify.com/api/token', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
        ]),
    ]);
}

if ($status < 200 || $status >= 300) {
    throw new RuntimeException("Spotify token refresh failed: {$status} {$tokenBody}");
}

$token = json_decode($tokenBody, true, 512, JSON_THROW_ON_ERROR);
if (empty($token['access_token'])) throw new RuntimeException('Spotify response did not contain an access token.');

function spotify(string $path, string $accessToken): array {
    [$status, $body] = httpRequest('https://api.spotify.com/v1' . $path, [
        'headers' => ['Authorization: Bearer ' . $accessToken],
    ]);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("Spotify API {$status}: {$body}");
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

$dataPath = $statsDir . DIRECTORY_SEPARATOR . 'data.json';
$historyPath = $statsDir . DIRECTORY_SEPARATOR . 'listening.json';

$data = json_decode(file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR);
$history = is_file($historyPath)
    ? json_decode(file_get_contents($historyPath), true, 512, JSON_THROW_ON_ERROR)
    : ['processed' => [], 'days' => []];

$history['processed'] ??= [];
$history['days'] ??= [];

$recent = spotify('/me/player/recently-played?limit=50', $token['access_token']);
$processed = array_fill_keys($history['processed'], true);
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

foreach ($recent['items'] ?? [] as $item) {
    $track = $item['track'] ?? null;
    $playedAt = $item['played_at'] ?? null;
    if (!$track || empty($track['id']) || !$playedAt) continue;

    $key = $playedAt . '|' . $track['id'];
    if (isset($processed[$key])) continue;

    $played = new DateTimeImmutable($playedAt);
    $date = $played->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    if (!isset($history['days'][$date])) $history['days'][$date] = ['minutes' => 0, 'tracks' => []];

    $duration = ((float)($track['duration_ms'] ?? 0)) / 60000;
    $history['days'][$date]['minutes'] += $duration;

    $artist = $track['artists'][0] ?? [];
    $trackId = $track['id'];
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

$history['processed'] = array_slice(array_keys($processed), -10000);

function startOfWeek(DateTimeImmutable $date): DateTimeImmutable {
    $day = (int)$date->format('N');
    return $date->modify('-' . ($day - 1) . ' days')->setTime(0, 0, 0);
}

$today = $now->format('Y-m-d');
$monthKey = $now->format('Y-m');
$weekStart = startOfWeek($now)->format('Y-m-d');
$dayMinutes = 0.0;
$weekMinutes = 0.0;
$monthMinutes = 0.0;
$artists = [];
$tracks = [];

foreach ($history['days'] as $date => $day) {
    $minutes = (float)($day['minutes'] ?? 0);
    if ($date === $today) $dayMinutes += $minutes;
    if ($date >= $weekStart && $date <= $today) $weekMinutes += $minutes;
    if (str_starts_with($date, $monthKey)) {
        $monthMinutes += $minutes;
        foreach ($day['tracks'] ?? [] as $id => $track) {
            if (!isset($tracks[$id])) $tracks[$id] = $track + ['plays' => 0];
            $tracks[$id]['plays'] += (int)($track['plays'] ?? 0);

            $artistName = $track['artist'] ?? 'Unknown artist';
            if (!isset($artists[$artistName])) {
                $artists[$artistName] = [
                    'name' => $artistName,
                    'url' => $track['artistUrl'] ?? '#',
                    'plays' => 0,
                ];
            }
            $artists[$artistName]['plays'] += (int)($track['plays'] ?? 0);
        }
    }
}

usort($artists, fn($a, $b) => $b['plays'] <=> $a['plays']);
usort($tracks, fn($a, $b) => $b['plays'] <=> $a['plays']);

$data = [
    'updated' => $now->format(DateTimeInterface::ATOM),
    'month' => $now->format('F Y'),
    'listeningMinutes' => [
        'day' => (int)round($dayMinutes),
        'week' => (int)round($weekMinutes),
        'month' => (int)round($monthMinutes),
    ],
    'topArtists' => array_map(fn($a) => ['name' => $a['name'], 'url' => $a['url']], array_slice($artists, 0, 5)),
    'topTracks' => array_map(fn($t) => ['name' => ($t['name'] ?? 'Unknown track') . ' — ' . ($t['artist'] ?? 'Unknown artist'), 'url' => $t['url'] ?? '#'], array_slice($tracks, 0, 5)),
];

file_put_contents($historyPath, json_encode($history, JSON_UNESCAPED_SLASHES));
file_put_contents($dataPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
