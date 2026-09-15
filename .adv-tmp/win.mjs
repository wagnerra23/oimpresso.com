import fs from 'node:fs';

const logPath = new URL('./job.log', import.meta.url);
const raw = fs.readFileSync(logPath, 'utf8').replace(/^﻿/, '');
const lines = raw.split(/\r?\n/);

const A = Date.parse(process.argv[2]);
const B = Date.parse(process.argv[3]);
const mode = process.argv[4] || 'count';

let parsed = 0, unparsed = 0;
const win = [];
lines.forEach((l, i) => {
  const m = l.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z) /);
  if (!m) { unparsed++; return; }
  parsed++;
  const t = Date.parse(m[1]);
  if (t >= A && t <= B) win.push([i, l]);
});

console.error(`[meta] linhas totais=${lines.length} comTs=${parsed} semTs=${unparsed} naJanela=${win.length}`);
if (win.length) console.error(`[meta] idx ${win[0][0]} .. ${win[win.length - 1][0]}`);

if (mode === 'print') {
  for (const [i, l] of win) console.log(`${String(i).padStart(5)}| ${l}`);
}
