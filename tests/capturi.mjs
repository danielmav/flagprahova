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

// Paginile publice + login-ul de admin. Paginile autentificate (Meniu,
// Intrare, Fișiere, Utilizatori) nu pot fi capturate aici: Chrome headless
// nu poate primi o sesiune de admin logat fără un profil/CDP dedicat.
// Chrome headless are lățime minimă ~500 px, deci capturile „-mobil" (390)
// ies la ~500 px CSS — layout-ul mobil se judecă de acolo.
const pagini = [
  ['landing', '/', 1366], ['landing-mobil', '/', 390],
  ['acasa-2021', '/2021-2027/', 1366], ['acasa-2021-mobil', '/2021-2027/', 390],
  ['acasa-2014', '/2014-2020/', 1366],
  ['pagina-cooperare', '/2014-2020/cooperare', 1366], ['pagina-cooperare-mobil', '/2014-2020/cooperare', 390],
  ['dosar-arhiva', '/2014-2020/arhiva', 1366],
  ['contact', '/2021-2027/contact', 1366],
  ['eroare-404', '/2021-2027/nu-exista', 1366],
  ['admin-login', '/admin/login', 1366],
];

for (const [nume, cale, latime] of pagini) {
  const fisier = path.join(shotsDir, `${nume}.png`);
  execFileSync(chrome, [
    '--headless=new',
    '--disable-gpu',
    `--user-data-dir=${profil}`,
    `--window-size=${latime},1400`,
    '--force-device-scale-factor=1',
    `--screenshot=${fisier}`,
    base + cale,
  ], { stdio: 'ignore' });
  console.log('ok', nume);
}
