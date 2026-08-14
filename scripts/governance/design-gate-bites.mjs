#!/usr/bin/env node
// @ts-check
/**
 * design-gate-bites.mjs — o BITE-LOG dos gates de design (DR-2a da ADR 0336).
 *
 * A [ADR 0336](memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)
 * abriu a classe "gate de design vira required quando prova MORDIDA REAL", com critério
 * DR-2: **≥2 PRs reais distintos onde o gate teria bloqueado uma violação que MERGEOU**.
 * Mas a DR-2a — o artefato append-only que REGISTRA essas mordidas — nunca foi construída
 * ("PR próprio pós-aceite"). Sem ele, o gate advisory-wrapped emite `::warning::` + `exit 0`
 * e NUNCA registra uma mordida → quando o ZELADOR chega no `promote_by`, encontra 0 evidência
 * coletável e não tem como decidir promover vs manter por DADO. Este script fecha esse elo.
 *
 * O artefato: `memory/governance/design-gate-bites.jsonl` (JSONL append-only). Cada linha =
 * `{gate, pr, sha, arquivo, motivo, quando, sig}`. Uma "mordida" = um gate cujo `--check`
 * daria fail num sha que ESTÁ no main (= a violação mergeou, o advisory não segurou).
 *
 * ── COMO A MORDIDA É DETECTADA ──
 * `--scan` roda o `--check` de cada gate no HEAD atual (main pós-merge). exit≠0 = drift = a
 * violação está no main = mergeou. Dedup por `sig` (hash do gate + saída normalizada): a MESMA
 * violação persistente não infla a contagem — é registrada UMA vez, no merge onde apareceu.
 * Uma violação NOVA (sig diferente) = mordida nova. Isso dá contagem HONESTA (nunca infla) de
 * violações-distintas-que-mergearam. `--tally` conta PRs DISTINTOS por gate (critério DR-2).
 *
 * ── COMO É COLETADO (persistência) ──
 * NÃO há workflow que commita no main (enforce_admins rejeita push direto; auto-PR com
 * COWORK_BOT_PAT seria mecanismo novo com secret). Em vez disso, REUSO o dono existente: o
 * **ZELADOR** (sessão diária que já abre PRs — `scripts/governance/ZELADOR.md`) roda
 * `--scan` no passo dele e inclui as mordidas novas no PR diário. Extensão do dono, não
 * paralelo (§5 proibicoes "não duplicar régua/dono"). Ver ZELADOR.md.
 *
 * ── FRONTEIRA HONESTA ──
 *  - `ds-tokens-build-sync` fica FORA do scan: o `--check` dele exige `npm run tokens:build`
 *    antes (pesado, precisa de deps) — o recorder é leve/hermético. Mordida dele, se houver,
 *    entra manual pelo ZELADOR. Declarado, não escondido.
 *  - O scan detecta violação PRESENTE no main; se o recorder for plugado quando uma violação
 *    já existe (backfill), atribui ao merge que disparou o 1º scan (imperfeição de bootstrap
 *    declarada) — daí em diante a atribuição é correta (o merge que introduz roda o scan e vê
 *    o sig novo primeiro).
 *  - "gate crashou" (stack trace / exit≠0 SEM saída de drift) ≠ "gate mordeu": o recorder
 *    NÃO registra mordida quando o próprio gate falhou em rodar (evita falso-positivo).
 *
 * Uso:
 *   node scripts/governance/design-gate-bites.mjs --scan  [--pr <n>] [--sha <sha>] [--dry-run] [--root <path>]
 *   node scripts/governance/design-gate-bites.mjs --tally [--root <path>]
 *   node scripts/governance/design-gate-bites.mjs --selftest
 */
import { readFileSync, existsSync, appendFileSync, mkdirSync, mkdtempSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { join, resolve, dirname } from 'node:path';
import { tmpdir } from 'node:os';

const args = process.argv.slice(2);
function argVal(flag, def) {
  const i = args.indexOf(flag);
  return i >= 0 && args[i + 1] ? args[i + 1] : def;
}
const ROOT = resolve(argVal('--root', process.cwd()));
const DRY = args.includes('--dry-run');
const LEDGER = join(ROOT, 'memory/governance/design-gate-bites.jsonl');

/**
 * Registro dos gates de design escaneáveis (leves, sem build). Cada um: nome canônico do
 * check + o comando que o CI roda. exit≠0 (com saída de drift) = mordida.
 * FORA (declarado): ds-tokens-build-sync (exige `npm run tokens:build`).
 */
const GATES = [
  { name: 'component-registry', cmd: ['scripts/governance/component-registry-check.mjs', '--check', '--strict'] },
  { name: 'pt-conformance', cmd: ['scripts/governance/pt-conformance.mjs', '--check'] },
  { name: 'ds-mirror-drift', cmd: ['scripts/governance/ds-mirror-drift.mjs'] },
  { name: 'design-coverage', cmd: ['scripts/qa/design-coverage.mjs', '--check'] },
  { name: 'ds-token-version', cmd: ['scripts/design-sync/ds-token-version.mjs', '--check'] },
];

// ── util ────────────────────────────────────────────────────────────────────

/**
 * roda um gate; devolve {exit, out, err, saida, crashed}.
 *
 * `saida` = stdout + stderr. É ela que alimenta sig/motivo/arquivo — NUNCA `out` sozinho.
 * MEDIDO em 2026-08-14 (violação plantada em cada um dos 5 gates do array, um a um):
 *   component-registry → drift no **stdout**
 *   pt-conformance     → drift no **stderr**  (console.error)
 *   design-coverage    → drift no **stderr**  (console.error)
 *   ds-token-version   → drift no **stderr**  (console.error)
 *   ds-mirror-drift    → **exit 0** no comando que o CI roda (sem `--enforce`, todo caminho
 *                        de drift cai em `console.log` + exit 0) → não vira mordida por
 *                        construção. Fato declarado, não corrigido aqui: mudar o comando faria
 *                        o recorder medir um caminho diferente do que o CI executa.
 * Ou seja: 3 de 5 escreviam o drift onde o recorder não olhava. O efeito não era só motivo em
 * branco — o `sig` virava sha256(gate + ""), IGUAL pra qualquer violação daquele gate, e o
 * dedup do `scan` descartava a 2ª violação distinta. Como o `--tally` conta PRs distintos
 * (critério DR-2 da ADR 0336), o contador que decide promoção ficava travado em 1.
 *
 * `crashed` continua lendo **só o stderr**: é lá que stack trace e ENOENT aparecem, e misturar
 * o stdout na detecção de crash reclassificaria mordida legítima como "gate não rodou".
 */
function runGate(gate, root) {
  const r = spawnSync('node', gate.cmd, { cwd: root, encoding: 'utf8', timeout: 120000 });
  const out = (r.stdout || '') + '';
  const err = (r.stderr || '') + '';
  const exit = typeof r.status === 'number' ? r.status : 1;
  // "crashou" = node estourou stack trace OU não achou o script (ENOENT) — NÃO é mordida.
  const crashed =
    r.error != null ||
    /^\s*(node:internal|Error:|TypeError:|SyntaxError:|ReferenceError:)/m.test(err) ||
    /Cannot find module/.test(err);
  const saida = [out, err].filter((s) => s.trim()).join('\n');
  return { exit, out, err, saida, crashed };
}

/** normaliza a saída pra assinatura estável (tira contagens/números, ordena linhas). */
function normalize(out) {
  return out
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter((l) => l && !/^\(advisory/.test(l) && !/^component-registry-check —/.test(l))
    .map((l) => l.replace(/\d+/g, '#')) // some contagens não geram sig novo
    .sort()
    .join('\n');
}

/** assinatura estável da mordida (gate + saída normalizada). */
function sigOf(gateName, out) {
  return createHash('sha256').update(gateName + '\n' + normalize(out)).digest('hex').slice(0, 16);
}

/** melhor-esforço: caminhos de arquivo citados na saída de drift. */
function filesFrom(out) {
  const set = new Set();
  for (const m of out.matchAll(/[\w./@-]+\.(?:tsx?|jsx?|mjs|css|json|md)\b/g)) set.add(m[0]);
  return [...set].slice(0, 20);
}

/** melhor-esforço: 1ª linha significativa de motivo. */
function motivoFrom(out) {
  const line = out
    .split(/\r?\n/)
    .map((l) => l.trim())
    .find((l) => /\[DRIFT\]|regrediu|missing|ausente|drift|não resolve|diverg|perdeu/i.test(l));
  return (line || out.split(/\r?\n/).find((l) => l.trim()) || '').slice(0, 240);
}

function readLedgerSigs(ledgerPath) {
  if (!existsSync(ledgerPath)) return new Set();
  const sigs = new Set();
  for (const line of readFileSync(ledgerPath, 'utf8').split(/\r?\n/)) {
    if (!line.trim()) continue;
    try { const o = JSON.parse(line); if (o.sig) sigs.add(o.sig); } catch { /* ignora linha corrompida */ }
  }
  return sigs;
}

// ── modos ─────────────────────────────────────────────────────────────────

/**
 * roda cada gate; registra mordida nova (sig inédito). Devolve {bites, skipped}.
 * `dry` é explícito (default = flag global) pra que o selftest exercite ESTA função — o
 * chokepoint real — em vez de uma cópia paralela que poderia divergir dela sem ninguém ver.
 */
function scan({ root, ledgerPath, pr, sha, when, gates = GATES, dry = DRY }) {
  const known = readLedgerSigs(ledgerPath);
  const bites = [];
  const skipped = [];
  for (const gate of gates) {
    const r = runGate(gate, root);
    if (r.crashed) { skipped.push({ gate: gate.name, motivo: 'gate crashou (não é mordida)', err: r.err.slice(0, 200) }); continue; }
    if (r.exit === 0) continue; // verde = sem violação
    // `saida` (stdout+stderr), nunca `out`: 3 dos 5 gates escrevem o drift no stderr (ver runGate).
    const sig = sigOf(gate.name, r.saida);
    if (known.has(sig)) continue; // violação já registrada (persistente) — não infla
    const rec = { gate: gate.name, pr: pr || null, sha: sha || null, arquivo: filesFrom(r.saida), motivo: motivoFrom(r.saida), quando: when, sig };
    bites.push(rec);
    known.add(sig);
  }
  if (bites.length && !dry) {
    mkdirSync(dirname(ledgerPath), { recursive: true });
    appendFileSync(ledgerPath, bites.map((b) => JSON.stringify(b)).join('\n') + '\n');
  }
  return { bites, skipped };
}

/** conta PRs distintos por gate (critério DR-2 ≥2). */
function tally(ledgerPath) {
  const byGate = new Map();
  if (existsSync(ledgerPath)) {
    for (const line of readFileSync(ledgerPath, 'utf8').split(/\r?\n/)) {
      if (!line.trim()) continue;
      let o; try { o = JSON.parse(line); } catch { continue; }
      if (!o.gate) continue;
      if (!byGate.has(o.gate)) byGate.set(o.gate, { prs: new Set(), bites: 0 });
      const g = byGate.get(o.gate);
      g.bites++;
      if (o.pr != null) g.prs.add(String(o.pr));
    }
  }
  return byGate;
}

function reportTally(ledgerPath) {
  const byGate = tally(ledgerPath);
  console.log('design-gate-bites — tally (critério DR-2 · ADR 0336: ≥2 PRs distintos = candidato a required)');
  if (byGate.size === 0) { console.log('  (ledger vazio — 0 mordidas registradas; todos os gates de design seguem advisory)'); return; }
  for (const [gate, g] of byGate) {
    const n = g.prs.size;
    const flag = n >= 2 ? '✅ CANDIDATO a promoção (DR-3)' : `⏳ ${n}/2 PRs distintos — segue advisory`;
    console.log(`  ▸ ${gate}: ${g.bites} mordida(s) · ${n} PR(s) distinto(s) → ${flag}`);
  }
}

// ── selftest (hermético — a máquina que valida não pode mentir) ──────────────

function selftest() {
  const tmp = mkdtempSync(join(tmpdir(), 'dgbites-'));
  const ledger = join(tmp, 'bites.jsonl');
  // gates-fixture: 1 morde (exit 1 com drift), 1 verde (exit 0), 1 crasha (exit≠0 sem drift).
  const biter = { name: 'fake-biter', cmd: ['-e', 'process.stdout.write("[DRIFT] 1: export foo ausente em resources/js/x.tsx"); process.exit(1)'] };
  const clean = { name: 'fake-clean', cmd: ['-e', 'process.stdout.write("[OK] tudo certo"); process.exit(0)'] };
  const crasher = { name: 'fake-crasher', cmd: ['-e', 'throw new Error("boom")'] };
  let fail = 0;
  const assert = (c, msg) => { if (!c) { console.error(`  ✗ ${msg}`); fail++; } else console.log(`  ✓ ${msg}`); };
  // roda o `scan` REAL (não uma cópia de teste): é o pipeline que o --scan usa em produção.
  const run = (o) => scan({ root: process.cwd(), ledgerPath: ledger, dry: false, ...o });

  // 1) morde → 1 bite; verde → 0; crash → skip (não vira mordida)
  const r1 = run({ pr: '101', sha: 'aaa', when: '2026-01-01', gates: [biter, clean, crasher] });
  assert(r1.bites.length === 1 && r1.bites[0].gate === 'fake-biter', 'gate que morde vira 1 mordida; verde não; crash não');
  assert(r1.skipped.some((s) => s.gate === 'fake-crasher'), 'gate que crashou é pulado (não é mordida)');
  assert(r1.bites[0].arquivo.includes('resources/js/x.tsx'), 'arquivo extraído da saída de drift');

  // 2) MESMA violação num PR novo → dedup por sig (não infla)
  const r2 = run({ pr: '102', sha: 'bbb', when: '2026-01-02', gates: [biter] });
  assert(r2.bites.length === 0, 'mesma violação (sig igual) não registra de novo — não infla');

  // 3) violação DIFERENTE → mordida nova
  const biter2 = { name: 'fake-biter', cmd: ['-e', 'process.stdout.write("[DRIFT] 1: import quebrado em resources/js/y.tsx"); process.exit(1)'] };
  const r3 = run({ pr: '103', sha: 'ccc', when: '2026-01-03', gates: [biter2] });
  assert(r3.bites.length === 1, 'violação com sig diferente vira mordida nova');

  // 4) tally: fake-biter tem 2 PRs distintos (101, 103) → candidato
  const t = tally(ledger);
  const fb = t.get('fake-biter');
  assert(fb && fb.prs.size === 2, 'tally conta 2 PRs distintos pro gate que mordeu em 2 PRs');
  assert(fb && fb.bites === 2, 'tally conta 2 mordidas totais');

  // 5) GATE QUE ESCREVE O DRIFT SÓ NO **stderr** — o defeito real de 3 dos 5 gates do array
  //    (pt-conformance, design-coverage, ds-token-version usam console.error). Antes do fix,
  //    sig/motivo liam só o stdout: motivo vinha VAZIO e o sig era sha256(gate+"") — o MESMO
  //    pra qualquer violação, então a 2ª violação distinta era engolida pelo dedup e o --tally
  //    (critério DR-2) ficava travado em 1 PR.
  //    BITE-TEST (medido 2026-08-14, revertendo só `r.saida`→`r.out` numa cópia): 5 asserts
  //    dos blocos 5+6 reprovam. O 1º do bloco 5 NÃO reprova — `exit≠0` já basta pra virar
  //    mordida; o que o bug estragava era o CONTEÚDO (motivo/arquivo) e a UNICIDADE do sig.
  const errBiter = { name: 'fake-stderr-biter', cmd: ['-e', 'process.stderr.write("[DRIFT] token bg-surface ausente em resources/css/a.css"); process.exit(1)'] };
  const r5 = run({ pr: '201', sha: 'ddd', when: '2026-01-05', gates: [errBiter] });
  assert(r5.bites.length === 1, 'gate que só escreve no stderr vira mordida');
  assert(r5.bites.length === 1 && r5.bites[0].motivo.includes('bg-surface'), 'motivo NÃO fica vazio quando o drift sai no stderr');
  assert(r5.bites.length === 1 && r5.bites[0].arquivo.includes('resources/css/a.css'), 'arquivo é extraído do stderr também');

  // 6) 2ª violação DISTINTA do mesmo gate-stderr → sig diferente, não é engolida pelo dedup.
  //    (com o bug, ambas tinham sig de string vazia → esta some e o tally trava em 1 PR)
  const errBiter2 = { name: 'fake-stderr-biter', cmd: ['-e', 'process.stderr.write("[DRIFT] token fg-muted ausente em resources/css/b.css"); process.exit(1)'] };
  const r6 = run({ pr: '202', sha: 'eee', when: '2026-01-06', gates: [errBiter2] });
  assert(r6.bites.length === 1, 'violação stderr DISTINTA gera sig novo (dedup não engole)');
  // os dois sigs têm de EXISTIR: `a?.sig !== b?.sig` passaria com b ausente (undefined),
  // dando verde justamente no cenário do bug — assert que mede presença, não comportamento.
  const sig5 = r5.bites[0]?.sig, sig6 = r6.bites[0]?.sig;
  assert(Boolean(sig5) && Boolean(sig6) && sig5 !== sig6, 'sigs de violações stderr distintas são diferentes (e ambas foram registradas)');
  const eb = tally(ledger).get('fake-stderr-biter');
  assert(eb && eb.prs.size === 2, 'tally do gate-stderr conta 2 PRs distintos (critério DR-2 destravado)');

  if (fail) { console.error(`\n[SELFTEST FALHOU] ${fail} asserção(ões)`); process.exit(1); }
  console.log('\n[SELFTEST OK] o recorder morde (stdout E stderr), libera, dedupa e conta certo.');
}

// (não existe `scanForTest`: o selftest roda o `scan` REAL. Uma cópia paralela poderia passar
//  verde enquanto o pipeline de produção regredia — foi por isso que este defeito sobreviveu ao
//  selftest anterior, que exercitava a cópia.)

// ── main ────────────────────────────────────────────────────────────────────

function isoNow() {
  // Date normal (node runtime) — a restrição de Date é só pra scripts do Workflow-tool.
  return new Date().toISOString().slice(0, 10);
}

function main() {
  if (args.includes('--selftest')) return selftest();
  if (args.includes('--tally')) return reportTally(LEDGER);
  if (args.includes('--scan')) {
    const pr = argVal('--pr', process.env.BITE_PR || null);
    const sha = argVal('--sha', process.env.GITHUB_SHA || null);
    const when = argVal('--when', isoNow());
    const { bites, skipped } = scan({ root: ROOT, ledgerPath: LEDGER, pr, sha, when });
    console.log(`design-gate-bites --scan${DRY ? ' (dry-run)' : ''} — ${bites.length} mordida(s) nova(s), ${skipped.length} gate(s) pulado(s)`);
    for (const b of bites) console.log(`  🩸 ${b.gate} @ PR ${b.pr || '?'} (${b.sig}) — ${b.motivo}`);
    for (const s of skipped) console.log(`  ⚠️ ${s.gate}: ${s.motivo}`);
    if (bites.length) console.log(bites.length && DRY ? '  (dry-run: nada gravado)' : `  → ${bites.length} linha(s) apendada(s) em ${LEDGER}`);
    return;
  }
  console.error('uso: --scan [--pr N --sha X --dry-run] | --tally | --selftest');
  process.exit(2);
}

main();
