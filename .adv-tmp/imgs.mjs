import { readFileSync, writeFileSync } from 'node:fs';

const file = process.argv[2];
const outPrefix = process.argv[3];
const html = readFileSync(file, 'utf8');

const re = /<h3>([^<]+)<\/h3><img alt="([^"]+)" src="data:image\/png;base64,([A-Za-z0-9+/=]+)"\/>/g;
let m, n = 0;
while ((m = re.exec(html)) !== null) {
  const label = m[2].toLowerCase().replace(/[^a-z0-9]+/g, '-');
  const buf = Buffer.from(m[3], 'base64');
  const out = `${outPrefix}-${label}.png`;
  writeFileSync(out, buf);
  console.log(`${out}  ${buf.length} bytes  (h3="${m[1]}")`);
  n++;
}
console.log(`total imagens extraidas: ${n}`);
