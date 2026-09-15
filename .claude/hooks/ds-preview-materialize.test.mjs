#!/usr/bin/env node
// Teste do hook ds-preview-materialize.mjs.
//
// CONTRATO ATUAL (desde o #7224, 2026-09-11): o hook NÃO materializa mais nada. O shell ativo
// referencia `../../design-system/` direto, e recriar `_ds/` reintroduziria a duplicata física
// que aquele PR removeu — o próprio docblock do hook diz isso. Ele virou detector de
// compatibilidade: avisa quando um shell ANTIGO ainda referencia `_ds/`, e cala quando não há
// o que avisar.
//
// POR QUE O BITE RODA POR FIXTURE, e não contra o checkout real (a lição que produziu este
// arquivo): até 2026-09-14 o bloco ponta-a-ponta era guardado por `existsSync` de 3 paths que o
// #7224 apagou (`cowork/oimpresso.com.html`, `scripts/design-sync/mirror-snapshot`,
// `cowork/_ds`). A guarda falhava, o bloco inteiro era pulado em silêncio e o script ainda
// fechava `✅ todos os casos passaram` — ausência de medição travestida de saúde (LC-13).
// Medido no mesmo dia: reapontar os paths para os vivos NÃO salvava o bloco — ele dava
// `[FAIL] 0 arquivos, stdout sem "reposto"` e depois crashava com ENOENT, porque testava um
// comportamento que o hook perdeu. Fixture remove as duas fragilidades de uma vez: é
// determinística e não depende de o shell vivo ter refs `_ds/` — hoje ele tem ZERO, então o
// caso "avisa" jamais seria exercido aqui.
//
// Rodar: node .claude/hooks/ds-preview-materialize.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { precisaMaterializar, refsDoShell, SHELL_REL } from './ds-preview-materialize.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'ds-preview-materialize.mjs');
let fails = 0;
let skips = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };
// Pré-condição ausente NÃO é caso verde: entra no veredito e derruba o exit (LC-13).
const skip = (name, motivo) => { console.log('[SKIP] ' + name + ' — ' + motivo); skips++; };

// ── PURO: refs derivadas do shell (a fonte de verdade é o html, não uma lista à mão) ──
const html = '<link rel="stylesheet" href="_ds/id-x/colors_and_type.css"/>\n<link href="_ds/id-x/cockpit_domains.css"/>\n<script src="_ds/id-x/_ds_bundle.js?v=1"></script>\n<link href="styles.css"/>';
const refs = refsDoShell(html);
check('refsDoShell: 3 refs _ds/, sem query string, sem styles.css', refs.length === 3 && refs.every((r) => r.startsWith('_ds/id-x/')) && !refs.some((r) => r.includes('?')));
check('refsDoShell: html sem _ds → []', refsDoShell('<html></html>').length === 0);

// ── PURO: decisão ──
check('precisa: tudo existe → false', precisaMaterializar(refs, () => true).precisa === false);
const d = precisaMaterializar(refs, (p) => !p.endsWith('_ds_bundle.js'));
check('precisa: falta 1 → true e lista o que falta', d.precisa === true && d.faltam.length === 1 && d.faltam[0].endsWith('_ds_bundle.js'));

// ── BITE ponta a ponta: exercita o CLI de fora (subprocesso), em worktree de mentira ──
const HTML_LEGADO = '<link rel="stylesheet" href="_ds/id-x/colors_and_type.css"/>\n<script src="_ds/id-x/_ds_bundle.js?v=1"></script>';
const HTML_ATUAL = '<link rel="stylesheet" href="../../design-system/colors_and_type.css"/>\n<script src="../../design-system/_ds_bundle.js"></script>';

/** Worktree de mentira com um shell no path pedido. Devolve a raiz (o chamador remove). */
function fixture(shellRel, conteudo) {
  const raiz = mkdtempSync(join(tmpdir(), 'ds-preview-'));
  const alvo = join(raiz, shellRel);
  mkdirSync(dirname(alvo), { recursive: true });
  writeFileSync(alvo, conteudo, 'utf8');
  return raiz;
}
const rodar = (cwd, extraEnv = {}) => spawnSync(process.execPath, [HOOK], { cwd, encoding: 'utf8', env: { ...process.env, ...extraEnv } });
/** O hook escreveu algum `_ds/` em qualquer nível? (contrato NEGATIVO do #7224) */
const criouDs = (raiz) => {
  try { return readdirSync(raiz, { recursive: true }).some((p) => String(p).split(sep).includes('_ds')); }
  catch { return false; }
};

// 1. shell ANTIGO (ainda com `_ds/`) → avisa, e NÃO recria a duplicata.
{
  const raiz = fixture(SHELL_REL, HTML_LEGADO);
  try {
    const r = rodar(raiz);
    check(`BITE: shell legado com _ds/ → avisa (exit ${r.status}, stdout cita o hook e _ds/)`,
      r.status === 0 && r.stdout.includes('[ds-preview-materialize]') && r.stdout.includes('_ds/'));
    check('BITE: contrato negativo do #7224 — o hook NÃO recria a duplicata física `_ds/`', criouDs(raiz) === false);
  } finally { rmSync(raiz, { recursive: true, force: true }); }
}

// 2. shell ATUAL (referencia ../../design-system/) → silêncio. Controle negativo do caso 1:
//    sem ele, um hook que avisasse sempre passaria no caso 1 e no mesmo assert.
{
  const raiz = fixture(SHELL_REL, HTML_ATUAL);
  try {
    const r = rodar(raiz);
    check(`BITE: shell atual (zero refs _ds/) → silêncio (exit ${r.status}, stdout vazio)`, r.status === 0 && r.stdout.trim() === '');
  } finally { rmSync(raiz, { recursive: true, force: true }); }
}

// 3. escape valve — anunciada no docblock do hook, então testada (LC-15: saída anunciada e não
//    honrada é promessa que apodrece calada).
{
  const raiz = fixture(SHELL_REL, HTML_LEGADO);
  try {
    const r = rodar(raiz, { OIMPRESSO_DS_PREVIEW_OFF: '1' });
    check('escape OIMPRESSO_DS_PREVIEW_OFF=1 → silêncio mesmo com shell legado', r.status === 0 && r.stdout.trim() === '');
  } finally { rmSync(raiz, { recursive: true, force: true }); }
}

// 4. pina o PATH que o hook lê. Um shell legado no lugar pré-#7224 tem que ser ignorado — se
//    alguém reverter `SHELL_REL` para `cowork/oimpresso.com.html`, este caso cai. É o assert
//    que teria pego o hardcode que quebrou este arquivo.
{
  const raiz = fixture(join('prototipo-ui', 'cowork', 'oimpresso.com.html'), HTML_LEGADO);
  try {
    const r = rodar(raiz);
    check('BITE: shell no path pré-#7224 não é lido — o hook lê SHELL_REL (cowork/Wagner/)', r.status === 0 && r.stdout.trim() === '');
  } finally { rmSync(raiz, { recursive: true, force: true }); }
}

// 5. worktree sem espelho nenhum: silêncio.
{
  const vazio = mkdtempSync(join(tmpdir(), 'ds-preview-vazio-'));
  try {
    const r = rodar(vazio);
    check('sem shell → exit 0 e silêncio', r.status === 0 && r.stdout.trim() === '');
  } finally { rmSync(vazio, { recursive: true, force: true }); }
}

if (fails === 0 && skips === 0) console.log('\n✅ ds-preview-materialize: todos os casos passaram');
else if (fails > 0) console.log(`\n❌ ${fails} caso(s) falharam${skips > 0 ? ` · ${skips} NÃO MEDIDO(s)` : ''}`);
else console.log(`\n⚠️ ${skips} caso(s) NÃO MEDIDO(s) — pré-condição sumiu. Ausência de medição não é saúde.`);
process.exit(fails === 0 && skips === 0 ? 0 : 1);
