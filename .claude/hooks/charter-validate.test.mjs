#!/usr/bin/env node
// Teste do PORTE charter-validate.mjs (ex-.ps1). Deriva do CONTRATO (Charter > Spec: editar
// Page com charter vivo sem charter-fetch antes → avisa), NÃO do .ps1. Rodar: node ...test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { toFwd, matchPage, charterPathFor, readCharterStatus, buildOutput } from './charter-validate.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'charter-validate.mjs');
const BS = String.fromCharCode(92);
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── matchPage (puro, backslash-safe) ─────────────────────────────────────────────
check('matchPage: Page top-level', (() => { const p = matchPage('resources/js/Pages/Sells/Index.tsx'); return p && p.modulo === 'Sells' && p.tela === 'Index'; })());
check('matchPage: Page em subdir', (() => { const p = matchPage('resources/js/Pages/Financeiro/Cockpit/View.tsx'); return p && p.tela === 'View'; })());
check('matchPage: backslash Windows', (() => { const p = matchPage('resources' + BS + 'js' + BS + 'Pages' + BS + 'Sells' + BS + 'Index.tsx'); return p && p.tela === 'Index'; })());
check('matchPage: _components exempt', matchPage('resources/js/Pages/_components/Foo.tsx') === null);
check('matchPage: tela _Private exempt', matchPage('resources/js/Pages/Sells/_Private.tsx') === null);
check('matchPage: App/Layout exempt', matchPage('resources/js/Pages/Sells/App.tsx') === null);
check('matchPage: fora de Pages → null', matchPage('resources/js/Components/Foo.tsx') === null);

// ── charterPathFor + readCharterStatus + buildOutput ─────────────────────────────
check('charterPathFor troca .tsx por .charter.md', charterPathFor('a/b/Index.tsx') === 'a/b/Index.charter.md');
const tmp = mkdtempSync(join(tmpdir(), 'cv-'));
const cp = join(tmp, 'Index.charter.md');
writeFileSync(cp, '---\nstatus: live\ntitle: x\n---\n# Charter');
check('readCharterStatus lê status: live', readCharterStatus(cp) === 'live');
check('readCharterStatus inexistente → unknown', readCharterStatus(join(tmp, 'no.md')) === 'unknown');
check('buildOutput warning → allow', buildOutput({ tool: 'Edit', pathFwd: 'x', charterRelative: 'y', charterStatus: 'live', strict: false }).hookSpecificOutput.permissionDecision === 'allow');
check('buildOutput strict → deny', buildOutput({ tool: 'Edit', pathFwd: 'x', charterRelative: 'y', charterStatus: 'live', strict: true }).hookSpecificOutput.permissionDecision === 'deny');

// ── E2E ──────────────────────────────────────────────────────────────────────────
const pagesDir = join(tmp, 'resources', 'js', 'Pages', 'Sells');
mkdirSync(pagesDir, { recursive: true });
const tsx = join(pagesDir, 'Index.tsx');
writeFileSync(tsx, 'export default function(){}');
writeFileSync(join(pagesDir, 'Index.charter.md'), '---\nstatus: live\n---\n');
function run(input, env = {}) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8', env: { ...process.env, CHARTER_VALIDATE_STRICT: '', ...env } }); }
const w = (fp) => ({ tool_name: 'Edit', tool_input: { file_path: fp } });

const warned = run(w(tsx));
check('E2E: Edit Page com charter → exit 0 + JSON allow', warned.status === 0 && (() => { try { return JSON.parse(warned.stdout).hookSpecificOutput.permissionDecision === 'allow'; } catch { return false; } })());
check('E2E: strict → JSON deny', (() => { const r = run(w(tsx), { CHARTER_VALIDATE_STRICT: '1' }); try { return JSON.parse(r.stdout).hookSpecificOutput.permissionDecision === 'deny'; } catch { return false; } })());
const tsxNoCharter = join(pagesDir, 'Other.tsx');
writeFileSync(tsxNoCharter, 'x');
check('E2E: Page SEM charter → exit 0 silencioso', (() => { const r = run(w(tsxNoCharter)); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: não-Page → exit 0 silencioso', (() => { const r = run(w('resources/js/Components/Foo.tsx')); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

// ── ÂNCORA STALE (2026-08-27) — o vínculo tela → charter → protótipo ────────────────
// BITE-TEST hermético: fixture própria de charter + ledger, sem depender do repo real (o
// `staleList` vivo muda quando alguém roda `--compare`, e teste que depende disso apodrece).
// Prova as DUAS pernas: morde o medido-e-REPROVADO, e LIBERA o "nunca verificado" — que é a
// diferença entre um gate de 3/44 e uma parede de 25/44.
const { ancoraStale } = await import(pathToFileURL(HOOK).href);
const raizFx = mkdtempSync(join(tmpdir(), 'ancora-fx-'));
mkdirSync(join(raizFx, 'scripts', 'governance'), { recursive: true });
writeFileSync(join(raizFx, 'scripts', 'governance', '.cowork-freshness-ledger.json'), JSON.stringify([
  { kind: 'compare', date: '2026-01-01T00:00:00Z', verified: ['velho.jsx'], staleList: [] },
  { kind: 'compare', date: '2026-02-02T00:00:00Z', verified: [], staleList: ['divergente.jsx'] },
  // live-only DEPOIS da compare: não pode virar "a última rodada" (medido 2026-08-27 — uma
  // entrada dessas fez `STALE` degradar pra `SEM VEREDITO NOVO` no ancora.mjs).
  { kind: 'live-only', date: '2026-03-03T00:00:00Z', liveOnly: 2, liveOnlyList: ['x.jsx'] },
]));
const chFx = (nome, corpo) => { const p = join(raizFx, nome); writeFileSync(p, corpo); return p; };
const cStale = chFx('a.charter.md', '---\nstatus: live\nrelated_prototype: prototipo-ui/cowork/divergente.jsx\n---\n');
const cOk = chFx('b.charter.md', '---\nstatus: live\nrelated_prototype: prototipo-ui/cowork/nunca-medido.jsx\n---\n');
const cNa = chFx('c.charter.md', '---\nstatus: live\nrelated_prototype: n/a (herda PT-01 Lista)\n---\n');
const cSem = chFx('d.charter.md', '---\nstatus: live\n---\n');

check('ancoraStale: MORDE ancora no staleList da ultima compare', !!ancoraStale(cStale, raizFx));
check('ancoraStale: reporta o arquivo e a data medidos', (() => { const r = ancoraStale(cStale, raizFx); return r && r.proto === 'divergente.jsx' && r.medidoEm === '2026-02-02T00:00:00Z'; })());
check('ancoraStale: LIBERA "nunca verificado" (senao vira parede de 25 telas)', ancoraStale(cOk, raizFx) === null);
check('ancoraStale: LIBERA related_prototype n/a (herda PT)', ancoraStale(cNa, raizFx) === null);
check('ancoraStale: LIBERA charter sem related_prototype', ancoraStale(cSem, raizFx) === null);
check('ancoraStale: entrada live-only NAO vira a ultima rodada', !!ancoraStale(cStale, raizFx));
check('ancoraStale: fail-open com ledger ausente', ancoraStale(cStale, mkdtempSync(join(tmpdir(), 'sem-ledger-'))) === null);
check('ancoraStale: fail-open com charter inexistente', ancoraStale(join(raizFx, 'nao-existe.charter.md'), raizFx) === null);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs avisa charter-first em Page com charter vivo, deny em strict, advisory default; fail-open provado. Âncora STALE: morde o medido-e-reprovado, libera o nunca-verificado.');
process.exit(fails ? 1 : 0);
