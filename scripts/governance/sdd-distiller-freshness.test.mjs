#!/usr/bin/env node
// Meta-teste do read-side de distiller_freshness (ADR 0291 D-D · peça 3 do keystone).
// Importa measureDistillerFreshness de sdd-scorecard.mjs (guard isMain garante que o
// import NÃO dispara o scorecard inteiro) e prova o padrão anti-stale + determinístico:
//   A) zero portas carimbadas        → not_yet_measured (honesto; distiller não rodou)
//   B) ≥1 carimbada + 1 atrasada      → measured, value = nº atrás do doc mais novo (>7d)
//   C) carimbada e fresca             → não conta como stale
//   D) porta SEM carimbo              → entra no detail (cobertura pendente), não stale
//   E) reqDir ausente                 → not_yet_measured
//   F) checkout shallow + fonte git   → not_yet_measured (não fabrica stale de calendário);
//      fonte injetada ignora o guard  (incidente 2026-07-08→12: publish shallow publicou 6)
// `newestDocDate` é injetado (mapa) → sem git/FS real, determinístico.
// Uso: node scripts/governance/sdd-distiller-freshness.test.mjs
import { measureDistillerFreshness, isDocGerado } from './sdd-scorecard.mjs';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, basename } from 'node:path';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

/** Monta um memory/requisitos/ de teste. spec: { Mod: {distilledAt?, briefing?} } */
function makeReq(spec) {
  const dir = mkdtempSync(join(tmpdir(), 'distfresh-'));
  for (const [mod, cfg] of Object.entries(spec)) {
    const md = join(dir, mod);
    mkdirSync(md, { recursive: true });
    if (cfg.briefing !== false) {
      const fm = cfg.distilledAt ? `distilled_at: "${cfg.distilledAt}"\n` : '';
      writeFileSync(join(md, 'BRIEFING.md'), `---\nslug: ${mod}\n${fm}---\n# ${mod}\n`);
    }
  }
  return dir;
}
/** newestDocDate injetado a partir de um mapa por nome de módulo. */
const newestFrom = (map) => (modDir) => map[basename(modDir)] ?? null;
/** newestCodeDate injetado (2ª fonte do evento). `nada` = fonte sem opinião em toda porta. */
const codeFrom = (map) => (modDir) => map[basename(modDir)] ?? null;
const nada = () => null;

// ── A: zero carimbadas → notYet ─────────────────────────────────────────────
let dir = makeReq({ SemCarimbo: {}, Outra: {} });
try {
  const r = measureDistillerFreshness(dir, { newestDocDate: newestFrom({}), newestCodeDate: nada });
  ok(r.status === 'not_yet_measured', 'zero portas carimbadas → not_yet_measured');
  ok(r.value === null, 'sem carimbo → value null (não mente 0)');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── B/C/D: mistura fresca + atrasada + sem-carimbo + sem-porta ──────────────
dir = makeReq({
  Fresca: { distilledAt: '2026-06-15' },
  Atrasada: { distilledAt: '2026-05-01' },
  SemCarimbo: {},
  SemPorta: { briefing: false },
});
try {
  const r = measureDistillerFreshness(dir, {
    newestDocDate: newestFrom({ Fresca: '2026-06-16', Atrasada: '2026-06-18' }),
    newestCodeDate: nada,
  });
  ok(r.status === 'measured', '≥1 carimbada → measured');
  ok(r.value === 1, 'value === 1 (só a Atrasada está >7d atrás do doc mais novo)');
  ok(r.direction === 'down' && r.target === 0, 'distiller_freshness DESCE pra 0');
  ok(r.detail.portas === 3, 'detail.portas = 3 (SemPorta sem BRIEFING é ignorada)');
  ok(r.detail.carimbadas === 2, 'detail.carimbadas = 2');
  ok(r.detail.sem_carimbo === 1, 'detail.sem_carimbo = 1 (cobertura pendente, não stale)');
  ok(r.detail.stale === 1, 'detail.stale = 1');
  ok(r.detail.oldest_distilled_at === '2026-05-01', 'detail.oldest_distilled_at = mais antigo');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── C isolado: fresca dentro de 7d não conta ────────────────────────────────
dir = makeReq({ Fresca: { distilledAt: '2026-06-15' } });
try {
  const r = measureDistillerFreshness(dir, { newestDocDate: newestFrom({ Fresca: '2026-06-20' }), newestCodeDate: nada });
  ok(r.status === 'measured' && r.value === 0, 'porta carimbada e fresca (5d) → value 0');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── F: checkout shallow com fonte git default → notYet (não fabrica stale) ──
dir = makeReq({ CarimbadaVelha: { distilledAt: '2026-05-01' } });
try {
  const r = measureDistillerFreshness(dir, { shallow: () => true });
  ok(r.status === 'not_yet_measured', 'shallow + fonte git default → not_yet_measured (não mede calendário)');
  ok(/shallow|fetch-depth/.test(r.source ?? ''), 'source explica o shallow e aponta fetch-depth: 0');
  const r2 = measureDistillerFreshness(dir, {
    shallow: () => true,
    newestDocDate: newestFrom({ CarimbadaVelha: '2026-05-02' }),
    newestCodeDate: nada,
  });
  ok(r2.status === 'measured' && r2.value === 0, 'fonte injetada ignora o guard de shallow (determinístico)');
  // O guard tem que valer pras DUAS fontes: deixar a do CÓDIGO no default git num checkout
  // shallow mediria calendário, não evento — mesmo com a do doc injetada.
  const r3 = measureDistillerFreshness(dir, {
    shallow: () => true,
    newestDocDate: newestFrom({ CarimbadaVelha: '2026-05-02' }),
  });
  ok(r3.status === 'not_yet_measured',
    'shallow + fonte do CÓDIGO no default git → not_yet_measured (guard cobre as 2 fontes)');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── E: reqDir ausente → notYet ──────────────────────────────────────────────
ok(
  measureDistillerFreshness(join(tmpdir(), `req-inexistente-${process.pid}`)).status === 'not_yet_measured',
  'reqDir ausente → not_yet_measured',
);

// ── G: doc GERADO não conta como "conhecimento novo" (2026-08-05) ───────────
// O que o `newestDocDate` injetado dos casos acima NÃO alcança: a regra de exclusão que
// roda DENTRO do walk. Era por NOME (`SUPERFICIE.md`) e deixava passar o irmão gerado
// (`Jana/ARCHITECTURE.md`, do system-map.mjs) → falso-stale que avermelhou um gate
// REQUIRED (PR #5298). O critério agora é o `authority: generated` do frontmatter.
// Controles NEGATIVOS junto: doc normal e `authority` citado na prosa CONTAM.
const ler = (m) => () => m;
ok(isDocGerado('x.md', ler('---\nauthority: generated\n---\n# t\n')),
  'frontmatter authority: generated → é doc gerado (exclui do "doc mais novo")');
ok(isDocGerado('x.md', ler('---\nid: a\nauthority:   generated  \nlifecycle: ativo\n---\n')),
  'espaço extra em volta do valor não engana o match');
ok(!isDocGerado('x.md', ler('---\nauthority: canonical\n---\n# t\n')),
  'authority: canonical → CONTA como conhecimento (controle negativo)');
ok(!isDocGerado('x.md', ler('---\nid: a\n---\n\nO campo authority: generated é explicado aqui.\n')),
  'authority citado no CORPO não classifica — só o bloco de abertura (controle negativo)');
ok(!isDocGerado('x.md', ler('# sem frontmatter\n')),
  'doc sem frontmatter → CONTA como conhecimento (controle negativo)');
ok(!isDocGerado('x.md', () => { throw new Error('EACCES'); }),
  'ilegível → false: na dúvida o doc CONTA (erra pro lado de acusar stale, não de esconder)');

// ── H: UNIÃO das 2 fontes do evento — doc OU código (2026-09-15) ──────────────
// Os dois eixos são cegos em lugares DIFERENTES e os flips vão pra lados OPOSTOS, então a
// regra SOMA em vez de trocar. Casos reais que deram origem a isto (medidos 2026-09-15):
//   Jana   — doc fresco (5d; o único doc novo era `authority: generated`, excluído)
//            + código atrás (9d)   → só a fonte do CÓDIGO vê;
//   Fiscal — doc atrás (8d, reescrita de VEREDITO num gap) + código fresco (5d)
//                                  → só a fonte do DOC vê.
dir = makeReq({
  SoCodigoVe: { distilledAt: '2026-09-06' },   // padrão Jana
  SoDocVe: { distilledAt: '2026-09-06' },      // padrão Fiscal
  NenhumVe: { distilledAt: '2026-09-06' },     // controle NEGATIVO: os dois frescos
});
try {
  const r = measureDistillerFreshness(dir, {
    newestDocDate: newestFrom({ SoCodigoVe: '2026-09-11', SoDocVe: '2026-09-14', NenhumVe: '2026-09-11' }),
    newestCodeDate: codeFrom({ SoCodigoVe: '2026-09-15', SoDocVe: '2026-09-11', NenhumVe: '2026-09-11' }),
  });
  ok(r.value === 2, 'união pega as DUAS portas (uma por cada eixo) — value 2, não 1');
  ok(r.detail.carimbadas === 3, 'a 3ª porta segue no denominador (não sumiu por ser fresca)');
} finally { rmSync(dir, { recursive: true, force: true }); }

// Cada eixo ISOLADO, pra provar que nenhum dos dois carrega o outro nas costas.
dir = makeReq({ So: { distilledAt: '2026-09-06' } });
try {
  const soDoc = measureDistillerFreshness(dir, {
    newestDocDate: newestFrom({ So: '2026-09-14' }), newestCodeDate: nada,
  });
  ok(soDoc.value === 1, 'eixo DOC sozinho acusa (padrão Fiscal: 8d)');
  const soCod = measureDistillerFreshness(dir, {
    newestDocDate: nada, newestCodeDate: codeFrom({ So: '2026-09-15' }),
  });
  ok(soCod.value === 1, 'eixo CÓDIGO sozinho acusa (padrão Jana: 9d)');
  const nenhum = measureDistillerFreshness(dir, {
    newestDocDate: newestFrom({ So: '2026-09-11' }), newestCodeDate: codeFrom({ So: '2026-09-11' }),
  });
  ok(nenhum.value === 0, 'controle NEGATIVO: os dois dentro de 7d → não acusa');
  // `null` é "esta fonte não tem opinião", JAMAIS "está fresco" — se null silenciasse o
  // outro eixo, módulo sem `Modules/<X>` (Cliente, Sells) ficaria imune à regra.
  const docNull = measureDistillerFreshness(dir, {
    newestDocDate: nada, newestCodeDate: codeFrom({ So: '2026-09-15' }),
  });
  ok(docNull.value === 1, 'doc sem opinião (null) NÃO anula o eixo do código');
  const codNull = measureDistillerFreshness(dir, {
    newestDocDate: newestFrom({ So: '2026-09-14' }), newestCodeDate: nada,
  });
  ok(codNull.value === 1, 'código sem opinião (null) NÃO anula o eixo do doc');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── I: CONTROLE POSITIVO — detail.datadas_por_codigo ───────────────────────
// O modo de falha REAL desta mudança (2026-09-15): um par de barras invertidas colapsou no
// transporte da escrita, o resolvedor de path devolveu null em 14/14 portas, a união
// degradou pra fonte-única em silêncio e o agregado saiu `stale=0` — que parecia SAÚDE.
// `datadas_por_codigo: 0` com `carimbadas > 0` é ausência de MEDIÇÃO, não frescura — e o
// campo torna isso visível no JSON commitado, onde o diff denuncia.
dir = makeReq({ A: { distilledAt: '2026-09-06' }, B: { distilledAt: '2026-09-06' } });
try {
  const dois = measureDistillerFreshness(dir, {
    newestDocDate: nada, newestCodeDate: codeFrom({ A: '2026-09-07', B: '2026-09-07' }),
  });
  ok(dois.detail.datadas_por_codigo === 2, 'datou as 2 portas → datadas_por_codigo = 2');
  const muda = measureDistillerFreshness(dir, { newestDocDate: nada, newestCodeDate: nada });
  ok(muda.detail.datadas_por_codigo === 0 && muda.detail.carimbadas === 2,
    'fonte do código MUDA → datadas_por_codigo 0 com carimbadas 2 (a mudez fica visível)');
  const parcial = measureDistillerFreshness(dir, {
    newestDocDate: nada, newestCodeDate: codeFrom({ A: '2026-09-07' }),
  });
  ok(parcial.detail.datadas_por_codigo === 1, 'mudez PARCIAL também aparece (1 de 2)');
} finally { rmSync(dir, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  distiller_freshness read-side (ADR 0291 D-D): OK\n' : `\n  distiller_freshness: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
