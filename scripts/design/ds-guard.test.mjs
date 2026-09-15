#!/usr/bin/env node
// ds-guard.test.mjs — BITE-TEST do DS-GUARD (§8 do PROCESSO_MEMORIA_CC).
//
// POR QUE EXISTE. O ds-guard.mjs é invocado em CI (design-memory-gate.yml, 2 steps),
// pelo painel (protocolo.config.mjs) e por um reporter (replica-inconsistencias.mjs),
// e o CLAUDE.md §4b manda rodá-lo no fim de toda build visual. Até 2026-09-13 ele não
// tinha NENHUM teste nem `--selftest`: nada provava que o gate morde. Gate que ninguém
// exercita é indistinguível de gate que não olha — e a medição que abre este arquivo
// mostra que essa suspeita já tinha se materializado (ver T7).
//
// O QUE ELE EXERCITA. O CLI DE FORA, por subprocesso (§5 2026-07-30 · LC-15: assert
// sobre helper puro exportado NÃO prova contrato de pipeline — o ds-guard não exporta
// nada, toda a decisão vive no corpo do módulo). Cada regra do §8 é provada com par
// bom/ruim, para que o verde signifique "liberou o legítimo" e não "não olhou".
//
//   T1  paleta inventada (>=4 tokens de cor com o mesmo prefixo) → BLOQUEIA
//   T2  CSS legítimo (poucos tokens do mesmo prefixo)            → libera
//   T3  LIMIAR do §8: 3 tokens libera · 4 bloqueia               → controle do >=4
//   T4  tela na raiz (.html com text/babel + createRoot)         → BLOQUEIA (L-21)
//   T5  a EXCEÇÃO do host (oimpresso.com.html) e HTML sem React  → libera
//   T6  arquivo ILEGÍVEL não é skip silencioso                   → BLOQUEIA (§8)
//   T7  `--all` MEDIU algo (denominador > 0)                     → anti "verde no vácuo"
//   T8  `--report` (ADR 0388): MESMO achado, exit 0              → contrato do modo réplica
//   T9  arquivo que não é css/html é ignorado, não falha
//
// SOBRE O T7 (o que motivou metade deste arquivo). `--all` é RELATÓRIO por desenho
// (§8: árvore-inteira não bloqueia) e sai 0 sempre — então NÃO dá pra testá-lo por exit
// code sem reescrever a máquina. O que dá, e é o que importa, é provar que ele mediu
// um denominador NÃO-VAZIO: um relatório de dívida que imprime "-- limpo" tendo varrido
// zero arquivo é a §5 2026-07-29 (instrumento afirma verde quando não conseguiu medir),
// e era exatamente o estado em 2026-09-13 — a migração prototipo-ui/ → scripts/design/
// deixou o walk apontado pra pasta do próprio script (0 alvos) em vez do espelho (123).
//
// Node puro, sem deps/rede/DB. Fixtures em tmpdir do Node (nunca "/tmp" literal: §5
// 2026-08-21 — no Windows o Bash e o Node resolvem esse caminho pra lugares diferentes).
//
// Rodar: node scripts/design/ds-guard.test.mjs
//   exit 0 = o gate morde e libera certo · exit 1 = alguma prova caiu

import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const SCRIPT = join(HERE, 'ds-guard.mjs');

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  <- ' + extra}`);
  if (!cond) fails++;
};

// roda o CLI REAL como subprocesso e devolve { code, out } — nunca lança, porque
// exit 1 é resultado esperado na metade dos casos.
function run(args) {
  try {
    const out = execFileSync(process.execPath, [SCRIPT, ...args], {
      encoding: 'utf8', cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'],
    });
    return { code: 0, out };
  } catch (e) {
    // stdout E stderr: erro sai por stderr por convenção, e um detector que só lê
    // stdout fica cego justamente no caso que interessa (§5 2026-08-14).
    return { code: e.status ?? 1, out: (e.stdout || '') + (e.stderr || '') };
  }
}

const TMP = mkdtempSync(join(tmpdir(), 'ds-guard-bite-'));
const fixture = (nome, conteudo) => {
  const p = join(TMP, nome);
  mkdirSync(dirname(p), { recursive: true });
  writeFileSync(p, conteudo, 'utf8');
  return p;
};

// gera N tokens de cor com o MESMO prefixo — a forma exata que o §8 chama de
// "paleta inventada" (familia bespoke, cor fora do DS canonico).
const paleta = (prefixo, n) =>
  ':root{\n' + Array.from({ length: n }, (_, i) =>
    `  --${prefixo}-${i}: oklch(0.${50 + i} 0.1 250);`).join('\n') + '\n}\n';

const TELA_REACT = [
  '<!doctype html><html><body><div id="root"></div>',
  '<script type="text/babel">',
  '  const App = () => <div>oi</div>;',
  '  ReactDOM.createRoot(document.getElementById("root")).render(<App />);',
  '</script></body></html>',
].join('\n');

try {
  // ---- T1/T2 — paleta inventada BLOQUEIA · CSS legitimo libera ----------------
  {
    const ruim = fixture('ruim-page.css', paleta('bespoke', 6));
    const r = run([ruim]);
    check('T1 paleta inventada (6 tokens --bespoke-*) BLOQUEIA',
      r.code === 1 && /paleta/.test(r.out) && /BLOQUEIA/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 200))}`);

    // Controle positivo do lado bom: sem ele, um gate que reprova TUDO passaria no T1.
    const boa = fixture('boa-page.css', '.x{color:var(--fg);background:var(--bg)}\n');
    const rb = run([boa]);
    check('T2 CSS que consome token do DS (nao declara familia) libera',
      rb.code === 0 && /limpo/.test(rb.out), `code=${rb.code} out=${JSON.stringify(rb.out.slice(0, 200))}`);
  }

  // ---- T3 — o LIMIAR do §8 e' >=4: 3 libera, 4 bloqueia ----------------------
  // Prova que o numero da regra e' o da lei, nao "qualquer token reprova". Sem este
  // par, T1 passaria com um gate cujo limiar fosse 1 — e ai ele reprovaria build legitima.
  {
    const tres = fixture('tres-page.css', paleta('quase', 3));
    const quatro = fixture('quatro-page.css', paleta('estoura', 4));
    const r3 = run([tres]);
    const r4 = run([quatro]);
    check('T3a limiar: 3 tokens da mesma familia LIBERA', r3.code === 0, `code=${r3.code}`);
    check('T3b limiar: 4 tokens da mesma familia BLOQUEIA',
      r4.code === 1 && /--estoura-\*\(4\)/.test(r4.out), `code=${r4.code} out=${JSON.stringify(r4.out.slice(0, 200))}`);
  }

  // ---- T4/T5 — tela na raiz (L-21) BLOQUEIA · host e HTML sem React liberam ---
  {
    const tela = fixture('tela-solta.html', TELA_REACT);
    const r = run([tela]);
    check('T4 .html com text/babel + createRoot BLOQUEIA (tela na raiz · L-21)',
      r.code === 1 && /tela na raiz/.test(r.out), `code=${r.code} out=${JSON.stringify(r.out.slice(0, 200))}`);

    // a excecao do §8: o host e' o UNICO .html com React permitido. Mesmo CONTEUDO,
    // so muda o basename — se o gate reprovasse, quebraria o host legitimo.
    const host = fixture('oimpresso.com.html', TELA_REACT);
    const rh = run([host]);
    check('T5a oimpresso.com.html (mesmo conteudo) LIBERA — excecao do host',
      rh.code === 0 && /limpo/.test(rh.out), `code=${rh.code} out=${JSON.stringify(rh.out.slice(0, 200))}`);

    const estatico = fixture('estatico.html', '<!doctype html><html><body><p>sem react</p></body></html>');
    const re = run([estatico]);
    check('T5b .html sem React LIBERA', re.code === 0, `code=${re.code}`);
  }

  // ---- T6 — ILEGIVEL e' FALHA, nunca skip silencioso (§8) ---------------------
  {
    const fantasma = join(TMP, 'nao-existe-page.css');
    const r = run([fantasma]);
    check('T6 arquivo ilegivel BLOQUEIA (nao e skip silencioso)',
      r.code === 1 && /ILEGIVEL=FALHA/.test(r.out), `code=${r.code} out=${JSON.stringify(r.out.slice(0, 200))}`);
  }

  // ---- T7 — `--all` MEDIU algo (denominador > 0) ------------------------------
  // Nao asserta o VEREDITO (--all e' relatorio e sai 0 por desenho, §8) — asserta que
  // ele varreu alvos. Ver o cabecalho: "-- limpo" sobre zero arquivo e' verde no vacuo.
  {
    const r = run(['--all']);
    const linhas = (r.out.match(/^(OK|X|!) /gm) || []).length;
    check('T7 `--all` varreu um denominador NAO-VAZIO (relatorio mede o espelho)',
      r.code === 0 && linhas > 0,
      `code=${r.code} arquivos_varridos=${linhas} — relatorio de divida sobre 0 arquivo e' verde no vacuo (§5 2026-07-29)`);
  }

  // ---- T8 — `--report` (ADR 0388): MESMO achado, exit 0 -----------------------
  // O modo replica transforma o veto em DADO (o consumidor e' replica-inconsistencias.mjs).
  // Se o achado sumisse junto com o exit, o reporter passaria a nao ver paleta nenhuma.
  {
    const ruim = fixture('report-page.css', paleta('replica', 5));
    const r = run(['--report', ruim]);
    check('T8 `--report` mantem o ACHADO e sai 0 (ADR 0388 · veto vira dado)',
      r.code === 0 && /paleta/.test(r.out) && /--replica-\*\(5\)/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 200))}`);
  }

  // ---- T9 — extensao fora do escopo e' ignorada, nao falha --------------------
  {
    const js = fixture('qualquer.js', 'export const x = 1;\n');
    const r = run([js]);
    check('T9 arquivo que nao e css/html e ignorado (nao falha)',
      r.code === 0 && /ignorado/.test(r.out), `code=${r.code} out=${JSON.stringify(r.out.slice(0, 200))}`);
  }
} finally {
  try { rmSync(TMP, { recursive: true, force: true }); } catch { /* tmp: melhor esforco */ }
}

console.log(fails
  ? `\n${fails} prova(s) caiu(ram) — o ds-guard nao esta mordendo/liberando como o §8 manda.`
  : '\nds-guard morde e libera certo (9 provas · par bom/ruim por regra).');
process.exit(fails ? 1 : 0);
