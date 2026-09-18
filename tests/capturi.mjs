import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const base = process.env.APP_URL || 'http://flagprahova.test';
const shotsDir = path.resolve('storage/shots');
// Profil dedicat: dacă folosești profilul implicit al Chrome, headless nu
// scrie nimic (silent) când mai există o fereastră Chrome deschisă.
const profil = path.join(tmpdir(), 'flagprahova-capturi-profil');

mkdirSync(shotsDir, { recursive: true });

// Paginile autentificate (Meniu, Intrare, Fișiere, Utilizatori) nu pot fi
// capturate aici: Chrome headless nu poate primi o sesiune de admin logat
// fără un profil/CDP dedicat. Se capturează manual, sau printr-un runner
// cu puppeteer (Plan 2).
const pagini = [
  ['admin-login', '/admin/login', 1366],
  ['admin-login-mobil', '/admin/login', 390],
];

for (const [nume, cale, latime] of pagini) {
  const fisier = path.join(shotsDir, `${nume}.png`);
  execFileSync(chrome, [
    '--headless=new',
    '--disable-gpu',
    `--user-data-dir=${profil}`,
    `--window-size=${latime},900`,
    `--screenshot=${fisier}`,
    base + cale,
  ], { stdio: 'ignore' });
  console.log('ok', nume);
}
