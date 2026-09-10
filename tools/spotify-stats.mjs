import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');
const envPath = path.join(rootDir, '.env');
const dataPath = path.join(rootDir, 'stats', 'data.json');
const historyPath = path.join(rootDir, 'stats', 'listening.json');
const quotaLogPath = path.join(rootDir, 'stats', 'quota-log.json');
const timeZone = process.env.STATS_TIMEZONE || 'Europe/Amsterdam';

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

function logQuotaExceeded(entry) {
  try {
    let log = [];
    if (fs.existsSync(quotaLogPath)) {
      try { log = JSON.parse(fs.readFileSync(quotaLogPath, 'utf8')); } catch { log = []; }
    }
    if (!Array.isArray(log)) log = [];
    log.push({ timestamp: new Date().toISOString(), ...entry });
    log = log.slice(-100);
    const temp = `${quotaLogPath}.tmp`;
    fs.writeFileSync(temp, JSON.stringify(log, null, 2) + '\n');
    fs.renameSync(temp, quotaLogPath);
  } catch (error) {
    console.error('[Spotify] Could not write quota log:', error?.message || error);
  }
}

async function request(url, options = {}, attempts = 4) {
  for (let attempt = 0; attempt < attempts; attempt++) {
    const res = await fetch(url, options);
    if (res.ok || res.status === 204) return res;
    if (res.status === 429) {
      const retryAfterHeader = res.headers.get('retry-after');
      const retryAfter = Number(retryAfterHeader);
      const rawBody = await res.text();
      let body = {};
      try { body = JSON.parse(rawBody); } catch {}
      const reason = body?.error?.reason || body?.reason || '';
      const message = body?.error?.message || body?.message || '';
      const endpoint = new URL(url).pathname;
      const type = reason === 'QUOTA_EXCEEDED' ? 'QUOTA_EXCEEDED' : 'RATE_LIMITED';
      console.error(`[Spotify] 429 ${type} ${endpoint}${retryAfterHeader ? ` - retry after ${retryAfterHeader}s` : ''}${message ? ` - ${message}` : ''}`);
      logQuotaExceeded({ status: 429, type, reason: reason || null, endpoint, retryAfter: Number.isFinite(retryAfter) ? retryAfter : null, message: message || null, attempt: attempt + 1 });
      const wait = Number.isFinite(retryAfter) ? Math.min(retryAfter * 1000, 15000) : Math.min(1000 * 2 ** attempt, 8000);
      await new Promise(resolve => setTimeout(resolve, wait));
      continue;
    }
    if (res.status >= 500) {
      const rawBody = await res.text();
      const retryAfterHeader = res.headers.get('retry-after');
      const retryAfter = Number(retryAfterHeader);
      const wait = Number.isFinite(retryAfter) ? Math.min(retryAfter * 1000, 15000) : Math.min(1000 * 2 ** attempt, 8000);
      console.error(`[Spotify] ${res.status} server error${rawBody ? ` - ${rawBody.slice(0, 300)}` : ''}`);
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
  if (res.status === 204) return null;
  return res.json();
}

function localDate(date) { return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date); }
function localMonth(date) { return localDate(date).slice(0, 7); }
function startOfLocalWeek(date) {
  const parts = new Intl.DateTimeFormat('en-US', { timeZone, weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(date);
  const values = Object.fromEntries(parts.map(p => [p.type, p.value]));
  const base = new Date(`${values.year}-${values.month}-${values.day}T12:00:00`);
  const weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(values.weekday);
  base.setDate(base.getDate() - (weekday === 0 ? 6 : weekday - 1));
  return `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}-${String(base.getDate()).padStart(2, '0')}`;
}

let data = {};
if (fs.existsSync(dataPath)) { try { data = JSON.parse(fs.readFileSync(dataPath, 'utf8')); } catch { data = {}; } }
let history = { processed: [], days: {} };
if (fs.existsSync(historyPath)) { try { history = JSON.parse(fs.readFileSync(historyPath, 'utf8')); } catch { history = { processed: [], days: {} }; } }
history.processed ??= [];
history.days ??= {};

const [recent, currentlyPlaying] = await Promise.all([
  spotify('/me/player/recently-played?limit=50'),
  spotify('/me/player/currently-playing').catch(error => {
    console.error('[Spotify] Currently-playing request unavailable:', error?.message || error);
    return null;
  })
]);
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
  const entry = day.tracks[track.id] ??= {
    name: track.name,
    artist: artist?.name || 'Unknown artist',
    url: track.external_urls?.spotify || `https://open.spotify.com/track/${track.id}`,
    artistUrl: artist?.external_urls?.spotify || '#',
    plays: 0
  };
  entry.plays += 1;
  processed.add(key);
}

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
      const a = artists.get(track.artist) ?? { name: track.artist, url: track.artistUrl || '#', plays: 0 };
      a.plays += Number(track.plays || 0);
      artists.set(track.artist, a);
    }
  }
}

let live = null;
if (currentlyPlaying?.item?.id) {
  const track = currentlyPlaying.item;
  const progressMs = Math.max(0, Number(currentlyPlaying.progress_ms || 0));
  const durationMs = Math.max(progressMs, Number(track.duration_ms || progressMs));
  live = { isPlaying: currentlyPlaying.is_playing === true, trackId: track.id, progressMs, durationMs, fetchedAt: now.toISOString() };
  if (live.isPlaying && durationMs > 0) {
    const liveMinutes = progressMs / 60000;
    dayMinutes += liveMinutes;
    weekMinutes += liveMinutes;
    monthMinutes += liveMinutes;
  }
}

const topArtists = [...artists.values()].sort((a, b) => b.plays - a.plays).slice(0, 5).map(({ name, url }) => ({ name, url }));
const topTracks = [...tracks.values()].sort((a, b) => b.plays - a.plays).slice(0, 5).map(({ name, artist, url }) => ({ name: `${name} — ${artist}`, url }));

data = {
  updated: now.toISOString(),
  month: new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric', timeZone }).format(now),
  listeningMinutes: { day: Math.floor(dayMinutes), week: Math.floor(weekMinutes), month: Math.floor(monthMinutes) },
  live,
  topArtists,
  topTracks
};

const writeAtomic = (file, value) => {
  const temp = `${file}.tmp`;
  fs.writeFileSync(temp, value);
  fs.renameSync(temp, file);
};
writeAtomic(historyPath, JSON.stringify(history));
writeAtomic(dataPath, JSON.stringify(data, null, 2) + '\n');
console.log(JSON.stringify(data, null, 2));
