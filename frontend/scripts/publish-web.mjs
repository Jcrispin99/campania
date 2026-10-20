import { access, copyFile, cp, mkdir, readdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const frontend = fileURLToPath(new URL('../', import.meta.url));
const dist = path.join(frontend, 'dist');
const publicDir = path.resolve(frontend, '../public');
const entries = await readdir(dist);
const allowed = new Set(['index.html', '_expo', 'assets', 'favicon.ico', 'metadata.json']);

await access(path.join(dist, 'index.html'));
for (const entry of entries) {
  if (!allowed.has(entry)) throw new Error(`Salida web inesperada: ${entry}. Revisa antes de publicar.`);
}

await mkdir(publicDir, { recursive: true });
// Keep existing hashed assets available for browsers open during an update.
for (const entry of entries.filter(entry => entry !== 'index.html' && entry !== 'metadata.json')) {
  await cp(path.join(dist, entry), path.join(publicDir, entry), { recursive: true });
}
await copyFile(path.join(dist, 'index.html'), path.join(publicDir, 'app.html'));
console.log('Web publicada en public/. Laravel sirve la interfaz y /api/v1 desde el mismo dominio.');
