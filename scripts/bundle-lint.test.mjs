#!/usr/bin/env node
// Self-test bundle-lint — prova que MORDE resíduo e LIBERA bundle limpo (git repo temporário).
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'bundle-lint.mjs');
let fails = 0;
const check = (n, c, e = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  ' + e}`); if (!c) fails++; };
const node = (root) => spawnSync('node', [SCRIPT, '--root', root], { encoding: 'utf8' });
const out = (r) => (r.stdout || '') + (r.stderr || '');
const git = (root, args) => spawnSync('git', ['-C', root, ...args], { encoding: 'utf8' });

function repo(files) {
  const root = mkdtempSync(join(tmpdir(), 'bundlelint-'));
  git(root, ['init', '-q']); git(root, ['config', 'user.email', 't@t.t']); git(root, ['config', 'user.name', 't']);
  for (const f of files) { mkdirSync(join(root, dirname(f)), { recursive: true }); writeFileSync(join(root, f), 'x'); }
  git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'b']);
  return root;
}
const drop = (r) => rmSync(r, { recursive: true, force: true });

// 1. NEGATIVO — bundle com resíduo (Adversário + GAPS_v) → exit 1.
{
  const r = repo([
    'prototipo-ui/cowork-x/project/inbox-page.jsx',
    'prototipo-ui/cowork-x/project/Adversario do Protocolo.html',
    'prototipo-ui/cowork-x/project/GAPS_v2_FIN.md',
  ]);
  if (git(r, ['rev-parse', 'HEAD']).status !== 0) { console.log('[SKIP] git indisponível'); drop(r); }
  else { const res = node(r); check('bundle com resíduo → exit 1', res.status === 1 && /resíduo/.test(out(res)), out(res)); drop(r); }
}

// 2. POSITIVO — bundle limpo (app-vivo + screenshot + README) → exit 0.
{
  const r = repo([
    'prototipo-ui/cowork-x/project/inbox-page.jsx',
    'prototipo-ui/cowork-x/project/screenshots/a.png',
    'prototipo-ui/cowork-x/project/README.md',
  ]);
  if (git(r, ['rev-parse', 'HEAD']).status !== 0) { console.log('[SKIP] git indisponível'); drop(r); }
  else { const res = node(r); check('bundle limpo → exit 0', res.status === 0, out(res)); drop(r); }
}

console.log(fails ? `\n❌ ${fails} regressão(ões).` : `\n✅ bundle-lint morde resíduo e libera bundle limpo.`);
process.exit(fails ? 1 : 0);
