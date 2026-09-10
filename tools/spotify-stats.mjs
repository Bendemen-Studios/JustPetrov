import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');
const envPath = path.join(rootDir, '.env');
const dataPath = path.join(rootDir, 'stats', 'data.json');
const historyPath = path.join(rootDir, 'stats', 'listening.json');
const timeZone = process.env.STATS_TIMEZONE || 'Europe/Amsterdam';

// Load the root .env when running on Cloud86. Existing environment variables
// always take priority.
if (fs.existsSync(envPath)) {
  const envText = fs.readFileSync(envPath, 'utf8');
  for (const rawLine of envText.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) continue;
    const match = line.match(/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);
    if (!match) continue;
    const key = match[1];
    let value = match[2].trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) value = value.slice(1, -1);
    if (!process.env[key]) process.env[key] = value;
  }
}

const clientId = process.env.SPOTIFY_CLIENT_ID;
const refreshToken = process.env.SPOTIFY_REFRESH_TOKEN;
if (!clientId || !refreshToken) throw new Error('Missing SPOTIFY_CLIENT_ID or SPOTIFY_REFRESH_TOKEN.');

async function request(url, options = {}, attempts = 4) {
  for (let attempt = 0; attempt < attempts; attempt++) {
    const res = await fetch(url, options);
    if (res.ok) return res;
    if (res.status === 429 || res.status >= 500) {
      const retryAfter = Number(res.headers.get('retry-after'));
      const wait = Number.isFinite(retryAfter) ? Math.min(retryAfter * 1000, 15000) : Math.min(1000 * 2 ** attempt, 8000);
      await new Promise(resolve => setTimeout(resolve, wait));
      continue;
    }
    throw new Error(`${res.status} ${await res.text()}`);
  }
  throw new Error(`Request failed after ${attempts} attempts`);
}

const tokenResponse = await request('https://accounts.spotify.com/api/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ grant_type: 'refresh_token', refresh_token: refreshToken, client_id: clientId })
});
const token = await tokenResponse.json();
if (!token.access_token) throw new Error('Spotify token refresh returned no access token.');

async function spotify(apiPath) {
  const res = await request(`https://api.spotify.com/v1${apiPath}`, { headers: { Authorization: `Bearer ${token.access_token}` } });
  return res.json();
}

function localDate(date) {
  return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
}

function localMonth(date) {
  return localDate(date).slice(0, 7);
}

function startOfLocalWeek(date) {
  const parts = new Intl.DateTimeFormat('en-US', { timeZone, weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(date);
  const values = Object.fromEntries(parts.map(p => [p.type, p.value]));
  const base = new Date(`${values.year}-${values.month}-${values.day}T12:00:00`);
  const weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(values.weekday);
  base.setDate(base.getDate() - (weekday === 0 ? 6 : weekday - 1));
  return `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}-${String(base.getDate()).padStart(2, '0')}`;
}

let data = {};
if (fs.existsSync(dataPath)) {
  try { data = JSON.parse(fs.readFileSync(dataPath, 'utf8')); } catch { data = {}; }
}
let history = { processed: [], days: {} };
if (fs.existsSync(historyPath)) {
  try { history = JSON.parse(fs.readFileSync(historyPath, 'utf8')); } catch { history = { processed: [], days: {} }; }
}
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
  if (Number.isNaN(played.getTime())) continue;
  const date = localDate(played);
  const day = history.days[date] ??= { minutes: 0, tracks: {} };
  day.minutes += Number(track.duration_ms || 0) / 60000;

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

// Keep a large rolling key set so old plays are not accidentally counted twice.
history.processed = [...processed].slice(-100000);

const today = localDate(now);
const monthKey = localMonth(now);
const weekStart = startOfLocalWeek(now);
let dayMinutes = 0;
let weekMinutes = 0;
let monthMinutes = 0;
const artists = new Map();
const tracks = new Map();

for (const [date, day] of Object.entries(history.days)) {
  if (date === today) dayMinutes += Number(day.minutes || 0);
  if (date >= weekStart && date <= today) weekMinutes += Number(day.minutes || 0);
  if (date.startsWith(monthKey)) {
    monthMinutes += Number(day.minutes || 0);
    for (const [id, track] of Object.entries(day.tracks || {})) {
      const t = tracks.get(id) ?? { ...track, plays: 0 };
      t.plays += Number(track.plays || 0);
      tracks.set(id, t);
      const artistKey = track.artist;
      const a = artists.get(artistKey) ?? { name: artistKey, url: track.artistUrl || '#', plays: 0 };
      a.plays += Number(track.plays || 0);
      artists.set(artistKey, a);
    }
  }
}

const topArtists = [...artists.values()].sort((a, b) => b.plays - a.plays).slice(0, 5).map(({ name, url }) => ({ name, url }));
const topTracks = [...tracks.values()].sort((a, b) => b.plays - a.plays).slice(0, 5).map(({ name, artist, url }) => ({ name: `${name} — ${artist}`, url }));

data = {
  updated: now.toISOString(),
  month: new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric', timeZone }).format(now),
  listeningMinutes: { day: Math.round(dayMinutes), week: Math.round(weekMinutes), month: Math.round(monthMinutes) },
  topArtists,
  topTracks
};

// Write through temporary files so the live endpoint never sees half-written JSON.
const writeAtomic = (file, value) => {
  const temp = `${file}.tmp`;
  fs.writeFileSync(temp, value);
  fs.renameSync(temp, file);
};
writeAtomic(historyPath, JSON.stringify(history));
writeAtomic(dataPath, JSON.stringify(data, null, 2) + '\n');
console.log(JSON.stringify(data, null, 2));
