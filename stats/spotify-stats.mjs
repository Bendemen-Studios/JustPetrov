import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const statsDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(statsDir, '..');

// Load the root .env when running directly on Cloud86.
const envPath = path.join(rootDir, '.env');
if (fs.existsSync(envPath)) {
  for (const rawLine of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) continue;
    const match = line.match(/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);
    if (!match) continue;
    const key = match[1];
    let value = match[2].trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    if (!process.env[key]) process.env[key] = value;
  }
}

const clientId = process.env.SPOTIFY_CLIENT_ID;
const refreshToken = process.env.SPOTIFY_REFRESH_TOKEN;
if (!clientId || !refreshToken) {
  throw new Error('Missing SPOTIFY_CLIENT_ID or SPOTIFY_REFRESH_TOKEN in environment or root .env.');
}

const tokenResponse = await fetch('https://accounts.spotify.com/api/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    grant_type: 'refresh_token',
    refresh_token: refreshToken,
    client_id: clientId
  })
});
if (!tokenResponse.ok) throw new Error(`Spotify token refresh failed: ${tokenResponse.status} ${await tokenResponse.text()}`);
const token = await tokenResponse.json();

async function spotify(apiPath) {
  const res = await fetch(`https://api.spotify.com/v1${apiPath}`, {
    headers: { Authorization: `Bearer ${token.access_token}` }
  });
  if (!res.ok) throw new Error(`Spotify API ${res.status}: ${await res.text()}`);
  return res.json();
}

const dataPath = path.join(statsDir, 'data.json');
const historyPath = path.join(statsDir, 'listening.json');
let data = JSON.parse(fs.readFileSync(dataPath, 'utf8'));
let history = fs.existsSync(historyPath)
  ? JSON.parse(fs.readFileSync(historyPath, 'utf8'))
  : { processed: [], days: {} };

history.processed ??= [];
history.days ??= {};

const recent = await spotify('/me/player/recently-played?limit=50');
const processed = new Set(history.processed);
const now = new Date();

for (const item of recent.items ?? []) {
  const track = item.track;
  if (!track?.id || !item.played_at) continue;
  const key = `${item.played_at}|${track.id}`;
  if (processed.has(key)) continue;

  const played = new Date(item.played_at);
  const date = played.toISOString().slice(0, 10);
  const day = history.days[date] ??= { minutes: 0, tracks: {} };
  const duration = Number(track.duration_ms || 0) / 60000;
  day.minutes += duration;

  const artist = track.artists?.[0];
  const trackKey = track.id;
  const entry = day.tracks[trackKey] ??= {
    name: track.name,
    artist: artist?.name || 'Unknown artist',
    url: track.external_urls?.spotify || `https://open.spotify.com/track/${track.id}`,
    artistUrl: artist?.external_urls?.spotify || '#',
    plays: 0
  };
  entry.plays += 1;
  processed.add(key);
}

history.processed = [...processed].slice(-10000);

function startOfWeek(date) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

const today = now.toISOString().slice(0, 10);
const monthKey = today.slice(0, 7);
const weekStart = startOfWeek(now).toISOString().slice(0, 10);
let dayMinutes = 0;
let weekMinutes = 0;
let monthMinutes = 0;
const artists = new Map();
const tracks = new Map();

for (const [date, day] of Object.entries(history.days)) {
  if (date === today) dayMinutes += day.minutes;
  if (date >= weekStart && date <= today) weekMinutes += day.minutes;
  if (date.startsWith(monthKey)) {
    monthMinutes += day.minutes;
    for (const [id, track] of Object.entries(day.tracks || {})) {
      const t = tracks.get(id) ?? { ...track, plays: 0 };
      t.plays += track.plays || 0;
      tracks.set(id, t);
      const artistKey = track.artist;
      const a = artists.get(artistKey) ?? { name: artistKey, url: track.artistUrl || '#', plays: 0 };
      a.plays += track.plays || 0;
      artists.set(artistKey, a);
    }
  }
}

const topArtists = [...artists.values()]
  .sort((a, b) => b.plays - a.plays)
  .slice(0, 5)
  .map(({ name, url }) => ({ name, url }));
const topTracks = [...tracks.values()]
  .sort((a, b) => b.plays - a.plays)
  .slice(0, 5)
  .map(({ name, artist, url }) => ({ name: `${name} — ${artist}`, url }));

data = {
  updated: now.toISOString(),
  month: now.toLocaleString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' }),
  listeningMinutes: {
    day: Math.round(dayMinutes),
    week: Math.round(weekMinutes),
    month: Math.round(monthMinutes)
  },
  topArtists,
  topTracks
};

fs.writeFileSync(historyPath, JSON.stringify(history));
fs.writeFileSync(dataPath, JSON.stringify(data, null, 2) + '\n');
console.log(JSON.stringify(data, null, 2));
