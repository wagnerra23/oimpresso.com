#!/usr/bin/env node
// Teste do hook ds-preview-materialize.mjs. Contrato: `_ds/` vazio/incompleto num worktree
// fresco é defeito ([W] 2026-09-02) — o hook tem que repor a partir do mirror-snapshot
// versionado, e ficar em silêncio quando já está completo.
//
// Rodar: node .claude/hooks/ds-preview-materialize.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readdirSync, renameSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { precisaMaterializar, refsDoShell } from './ds-preview-materialize.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'ds-preview-materialize.mjs');
const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── PURO: refs derivadas do shell (a fonte de verdade é o html, não uma lista à mão) ──
const html = '<link rel="stylesheet" href="_ds/id-x/colors_and_type.css"/>\n<link href="_ds/id-x/cockpit_domains.css"/>\n<script src="_ds/id-x/_ds_bundle.js?v=1"></script>\n<link href="styles.css"/>';
const refs = refsDoShell(html);
check('refsDoShell: 3 refs _ds/, sem query string, sem styles.css', refs.length === 3 && refs.every((r) => r.startsWith('_ds/id-x/')) && !refs.some((r) => r.includes('?')));
check('refsDoShell: html sem _ds → []', refsDoShell('<html></html>').length === 0);

// ── PURO: decisão ──
check('precisa: tudo existe → false', precisaMaterializar(refs, () => true).precisa === false);
const d = precisaMaterializar(refs, (p) => !p.endsWith('_ds_bundle.js'));
check('precisa: falta 1 → true e lista o que falta', d.precisa === true && d.faltam.length === 1 && d.faltam[0].endsWith('_ds_bundle.js'));

// ── BITE (ponta a ponta, no checkout real): `_ds/` é cache gitignored — pode ser afastado e
//    reposto sem tocar nada versionado. O produtor (`--preview-ds`) lê só o mirror-snapshot
//    versionado, sem rede. Restaura o cache original no `finally`, aconteça o que acontecer. ──
const shellReal = join(REPO, 'prototipo-ui', 'cowork', 'oimpresso.com.html');
const snapReal = join(REPO, 'scripts', 'design-sync', 'mirror-snapshot');
const produtor = join(REPO, 'scripts', 'governance', 'cowork-mirror-freshness.mjs');
const dsDir = join(REPO, 'prototipo-ui', 'cowork', '_ds');
if (existsSync(shellReal) && existsSync(snapReal) && existsSync(produtor)) {
  const aside = dsDir + '.test-aside-' + process.pid;
  const conta = () => { try { return readdirSync(dsDir, { recursive: true }).filter((p) => /\.(css|js|woff2)$/.test(String(p))).length; } catch { return 0; } };
  const tinha = existsSync(dsDir);
  try {
    if (tinha) renameSync(dsDir, aside);
    const r1 = spawnSync(process.execPath, [HOOK], { cwd: REPO, encoding: 'utf8' });
    const n1 = conta();
    check(`BITE: _ds ausente → hook materializa (exit ${r1.status}, ${n1} arquivos css/js/woff2, stdout menciona reposto)`, r1.status === 0 && n1 >= 3 && /reposto/.test(r1.stdout));

    const r2 = spawnSync(process.execPath, [HOOK], { cwd: REPO, encoding: 'utf8' });
    check(`silêncio quando completo (exit ${r2.status}, stdout vazio)`, r2.status === 0 && r2.stdout.trim() === '');

    rmSync(dsDir, { recursive: true, force: true });
    const r3 = spawnSync(process.execPath, [HOOK], { cwd: REPO, encoding: 'utf8', env: { ...process.env, OIMPRESSO_DS_PREVIEW_OFF: '1' } });
    check('escape OIMPRESSO_DS_PREVIEW_OFF=1 → não materializa, exit 0, silêncio', r3.status === 0 && r3.stdout.trim() === '' && conta() === 0);
  } finally {
    rmSync(dsDir, { recursive: true, force: true });
    if (tinha && existsSync(aside)) renameSync(aside, dsDir);
  }
} else {
  console.log('[SKIP] BITE ponta a ponta: shell/mirror-snapshot/produtor não encontrados neste checkout');
}

// ── worktree sem espelho: silêncio ──
const vazio = mkdtempSync(join(tmpdir(), 'ds-preview-vazio-'));
try {
  const r = spawnSync(process.execPath, [HOOK], { cwd: vazio, encoding: 'utf8' });
  check('sem shell → exit 0 e silêncio', r.status === 0 && r.stdout.trim() === '');
} finally { rmSync(vazio, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n✅ ds-preview-materialize: todos os casos passaram' : `\n❌ ${fails} caso(s) falharam`);
process.exit(fails === 0 ? 0 : 1);
