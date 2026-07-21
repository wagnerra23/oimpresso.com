#!/usr/bin/env node
// @ts-check
/**
 * ledger-hash-chain.mjs — transparency-log (Rekor/Sigstore-style) sobre o
 * `governance/sdd-verification-ledger.json`. Torna adulteração RETROATIVA de qualquer
 * entry já selada DETECTÁVEL, e pina a PROVENIÊNCIA imutável (crítico + prompt + hash da
 * rubrica) de cada lote de verificação — o que o veredito no ledger hoje NÃO carrega.
 *
 * ── POR QUE UM SIDECAR (e não editar o ledger) ───────────────────────────────
 * O ledger é **append-only Tier 0** (proibicoes.md · PROTOCOLO-REFUTADOR-BACKFILL §2.7):
 * corrigir = nova entry, NUNCA editar a antiga. Um hash-chain "clássico" (cada registro
 * guarda `prev_hash`) exigiria REESCREVER as 40+ entries existentes pra inserir o campo —
 * violação direta do append-only. Rekor resolve exatamente isso: as ENTRIES do log ficam
 * intocadas; um arquivo SEPARADO de **checkpoints** (signed tree heads) commita, num ponto
 * no tempo, a raiz de toda a árvore. Adulterar uma entry já selada muda a raiz recomputada
 * e o checkpoint pinado NÃO bate → tamper evidente. Este script é esse mecanismo:
 *   • `governance/ledger-checkpoints.json`  ← sidecar NOVO, também append-only (só cresce).
 *   • cada checkpoint sela um RANGE de entries [cobre_de, cobre_ate) com uma raiz CUMULATIVA
 *     (fold sobre entries[0..cobre_ate) — Rekor tree head), encadeia ao checkpoint anterior
 *     (`prev_checkpoint_hash` = hash do registro anterior, o "encadeamento" pedido), e pina
 *     a proveniência (crítico/prompt/rubrica_sha) daquele lote.
 *   • O `_meta` do ledger e do sidecar ficam FORA da raiz — metadados evoluem sem quebrar
 *     a corrente (a raiz só folda `entries`, nunca `_meta`).
 *
 * ── O QUE É / O QUE NÃO É ────────────────────────────────────────────────────
 *   • NÃO é presence-gate (não checa "campo presente"): recomputa hash do CONTEÚDO real e
 *     compara com o pino criptográfico — o oposto de "a seção existe".
 *   • NÃO é catraca sobre campo auto-declarado: nada aqui é "eu declaro que verifiquei"; a
 *     raiz é DERIVADA do conteúdo (lápides §5 07-01 `last_validated` / 07-09 `verificado_em`).
 *   • NÃO duplica régua consolidada: o `baseline-tamper-guard` guarda BASELINES; o
 *     `ledger-check` casa entry↔PR. Nenhum verifica integridade criptográfica do ledger —
 *     este é o dono NOVO desse fato.
 *   • Garantia HONESTA (a mesma do Rekor): só o que está SELADO por um checkpoint é
 *     tamper-evidente. Entries novas ficam "não-pinadas" até o próximo checkpoint selá-las
 *     (reportado, nunca escondido). O genesis sela toda a história atual de uma vez.
 *
 * ── DETERMINISMO (proibido Date.now/Math.random) ─────────────────────────────
 * Toda hash vem de serialização CANÔNICA (chaves ordenadas) → re-run sem mudança = mesma
 * saída. `--data` é OBRIGATÓRIO no build (a data do checkpoint NUNCA é gerada — vem do
 * operador). Sem timestamp/random em lugar nenhum.
 *
 * USO (raiz do repo):
 *   node scripts/governance/ledger-hash-chain.mjs --verify [--json] [--check]
 *   node scripts/governance/ledger-hash-chain.mjs --build --data YYYY-MM-DD [--genesis]
 *        [--critico "fable-5 (sessao fresca, tier superior)"] [--rubrica <path>]
 *        [--prompt <path|descrição>] [--dry-run]
 *   node scripts/governance/ledger-hash-chain.mjs --selftest
 *
 * `--verify` nasce ADVISORY (exit 0 sempre; só imprime). `--check` = primitivo de promoção
 * (exit 1 se detectar adulteração da parte SELADA — lei ADR 0314/0275: gate novo nasce
 * advisory; virar required exige emenda + flip [W]). Node puro (fs + crypto). Sem deps.
 */
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';

const LEDGER_DEFAULT = 'governance/sdd-verification-ledger.json';
const CHECKPOINTS_DEFAULT = 'governance/ledger-checkpoints.json';
const RUBRICA_DEFAULT = 'memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md';
// Semente fixa da árvore (Rekor: um "origin" estável). Literal — nunca derivada de tempo.
const GENESIS_SEED = 'sha256:oimpresso-ledger-transparency-v1';

// ── primitivos determinísticos ────────────────────────────────────────────────
/** serialização canônica: chaves ordenadas recursivamente. Independe da ordem em que
 *  as chaves aparecem no JSON (as entries do ledger têm ordens diferentes — `pr` primeiro
 *  numas, `tipo` primeiro noutras). Sem isso a hash dependeria da ordem = frágil. */
export function canonical(v) {
  if (v === null) return 'null';
  if (Array.isArray(v)) return '[' + v.map(canonical).join(',') + ']';
  if (typeof v === 'object') {
    return '{' + Object.keys(v).sort().map((k) => JSON.stringify(k) + ':' + canonical(v[k])).join(',') + '}';
  }
  return JSON.stringify(v); // string | number | boolean
}
export const sha256 = (s) => createHash('sha256').update(s, 'utf8').digest('hex');
export const entryHash = (e) => sha256(canonical(e));

/** raiz CUMULATIVA sobre entries[0..upTo) — fold encadeado (Rekor tree head degenerado).
 *  root_i = sha256(root_{i-1} + '\n' + entryHash_i), root_{-1} = GENESIS_SEED. */
export function foldRoot(entries, upTo) {
  let root = GENESIS_SEED;
  for (let i = 0; i < upTo; i++) root = sha256(root + '\n' + entryHash(entries[i]));
  return root;
}

/** conteúdo do checkpoint = tudo MENOS o próprio `checkpoint_hash` (a auto-hash é sobre o resto). */
export function checkpointContent(cp) {
  const rest = { ...cp };
  delete rest.checkpoint_hash;
  return rest;
}
export const checkpointHash = (cp) => sha256(canonical(checkpointContent(cp)));

// ── build de um checkpoint (sela as entries novas desde o último) ─────────────
/**
 * @returns {object|null} o novo checkpoint, ou null se não há entry nova pra selar.
 */
export function buildCheckpoint({ entries, checkpoints, data, critico, rubricaRef, rubricaSha, promptRef, promptSha, historico = false }) {
  const seq = checkpoints.length;
  const cobre_de = seq === 0 ? 0 : checkpoints[seq - 1].cobre_ate;
  const cobre_ate = entries.length;
  if (cobre_ate <= cobre_de) return null; // nada novo a selar
  const lotes = entries.slice(cobre_de, cobre_ate).map((e) => (e && e.lote_id) || '(sem lote_id)');
  const prev = seq === 0 ? '' : checkpoints[seq - 1].checkpoint_hash;
  const content = {
    seq,
    data,
    cobre_de,
    cobre_ate,
    n_entries: cobre_ate - cobre_de,
    lotes,
    entries_root: foldRoot(entries, cobre_ate),
    prev_checkpoint_hash: prev,
    provenancia: {
      historico: !!historico,
      critico: critico ?? null,
      rubrica_ref: rubricaRef ?? null,
      rubrica_sha256: rubricaSha ?? null,
      prompt_ref: promptRef ?? null,
      prompt_sha256: promptSha ?? null,
    },
  };
  return { ...content, checkpoint_hash: sha256(canonical(content)) };
}

// ── verificação ────────────────────────────────────────────────────────────────
/**
 * Recomputa cada checkpoint contra o ledger REAL + valida a corrente. Puro (sem I/O).
 * @returns {{ok:boolean, problems:Array, sealed:number, unpinned:number, total_entries:number, checkpoints:number, rubrica_drift:Array}}
 */
export function verify(entries, checkpointsDoc, { rubricaCurrentSha = null } = {}) {
  const checkpoints = (checkpointsDoc && Array.isArray(checkpointsDoc.checkpoints)) ? checkpointsDoc.checkpoints : [];
  const problems = [];
  const rubrica_drift = [];
  let prevHash = '';
  let expectedDe = 0;
  for (const cp of checkpoints) {
    const seq = cp.seq;
    // 1. corrente de checkpoints (o "encadeamento": hash do registro anterior)
    if (cp.prev_checkpoint_hash !== prevHash) {
      problems.push({ seq, tipo: 'corrente', msg: `prev_checkpoint_hash não casa com o hash do checkpoint anterior — corrente quebrada (checkpoint inserido/removido/reordenado)` });
    }
    // 2. auto-hash do checkpoint (conteúdo do próprio checkpoint intacto?)
    const recomputed = checkpointHash(cp);
    if (recomputed !== cp.checkpoint_hash) {
      problems.push({ seq, tipo: 'checkpoint_hash', msg: `checkpoint_hash recomputado (${recomputed.slice(0, 12)}…) != gravado (${String(cp.checkpoint_hash).slice(0, 12)}…) — conteúdo do checkpoint adulterado` });
    }
    // 3. contiguidade da cobertura (sem lacuna nem sobreposição)
    if (cp.cobre_de !== expectedDe) {
      problems.push({ seq, tipo: 'contiguidade', msg: `cobre_de=${cp.cobre_de} esperava ${expectedDe} (lacuna ou sobreposição na cobertura)` });
    }
    // 4. raiz cumulativa vs ledger vivo (o coração do tamper-evidence)
    if (typeof cp.cobre_ate !== 'number' || cp.cobre_ate > entries.length) {
      problems.push({ seq, tipo: 'entries_faltando', msg: `checkpoint sela ${cp.cobre_ate} entries mas o ledger só tem ${entries.length} — entry(s) removida(s) (append-only violado)` });
    } else {
      const root = foldRoot(entries, cp.cobre_ate);
      if (root !== cp.entries_root) {
        problems.push({ seq, tipo: 'entries_root', msg: `entries_root recomputado != pinado — alguma entry em [0..${cp.cobre_ate}) foi adulterada ou reordenada desde a selagem` });
      }
    }
    // rubrica drift (ADVISORY, nunca falha): a rubrica pinada mudou desde a selagem?
    // Isto é ESPERADO quando a rubrica evolui legitimamente — o valor é justamente ver
    // QUAL versão governou cada lote. É informação, não defeito.
    if (rubricaCurrentSha && cp.provenancia && cp.provenancia.rubrica_sha256 && cp.provenancia.rubrica_sha256 !== rubricaCurrentSha) {
      rubrica_drift.push({ seq, pinada: cp.provenancia.rubrica_sha256.slice(0, 12), atual: rubricaCurrentSha.slice(0, 12) });
    }
    prevHash = cp.checkpoint_hash;
    expectedDe = cp.cobre_ate;
  }
  const sealed = checkpoints.length ? checkpoints[checkpoints.length - 1].cobre_ate : 0;
  const unpinned = Math.max(0, entries.length - sealed);
  return { ok: problems.length === 0, problems, sealed, unpinned, total_entries: entries.length, checkpoints: checkpoints.length, rubrica_drift };
}

// ═══════════════════════════════════════════════════════════════════════════════
// CLI
// ═══════════════════════════════════════════════════════════════════════════════
const isMain = (() => {
  try { return process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href; }
  catch { return false; }
})();

function parseArgs(argv) {
  const a = argv.slice(2);
  const has = (n) => a.includes(n);
  const val = (n, d = null) => { const i = a.indexOf(n); return i >= 0 && a[i + 1] !== undefined ? a[i + 1] : d; };
  return { has, val };
}

function loadJson(path) {
  if (!existsSync(path)) return null;
  return JSON.parse(readFileSync(path, 'utf8'));
}
function ledgerEntries(ledger) {
  return (ledger && Array.isArray(ledger.entries)) ? ledger.entries : [];
}

const CHECKPOINTS_META = {
  doc: 'Transparency-log (Rekor-style) do governance/sdd-verification-ledger.json.',
  gerado_por: 'scripts/governance/ledger-hash-chain.mjs --build',
  regra: 'append-only: cada checkpoint sela um range [cobre_de, cobre_ate) de entries com uma raiz CUMULATIVA (fold sobre entries[0..cobre_ate)) + encadeia ao checkpoint anterior (prev_checkpoint_hash). Corrigir = novo checkpoint, NUNCA editar/remover checkpoint antigo (adulteração é detectada por --verify).',
  garantia: 'só o range SELADO por um checkpoint é tamper-evidente; entries após o último checkpoint ficam não-pinadas até o próximo build (Rekor: entry existe antes da próxima signed tree head). Genesis sela toda a história de uma vez.',
  determinismo: 'raiz e hashes vêm de serialização canônica (chaves ordenadas); sem Date.now/Math.random. `data` do checkpoint é passada pelo operador (--data), nunca gerada.',
  proveniencia: 'cada checkpoint pina crítico + referência/hash da rubrica + referência/hash do prompt do lote — a proveniência imutável que o ledger.entries (só veredito) não carrega. historico:true = genesis selando entries que ANTECEDEM o pino de proveniência (proveniência por-lote começa nos checkpoints seguintes).',
  verificar: 'node scripts/governance/ledger-hash-chain.mjs --verify [--json] [--check]',
};

function doBuild({ has, val }) {
  const ledgerPath = val('--ledger', LEDGER_DEFAULT);
  const cpPath = val('--checkpoints', CHECKPOINTS_DEFAULT);
  const data = val('--data', null);
  if (!data || !/^\d{4}-\d{2}-\d{2}$/.test(data)) {
    console.error('✗ --build exige --data YYYY-MM-DD (a data do checkpoint NUNCA é gerada — vem do operador; regra "sem Date.now").');
    process.exit(2);
  }
  const ledger = loadJson(ledgerPath);
  if (!ledger) { console.error(`✗ ledger não encontrado: ${ledgerPath}`); process.exit(2); }
  const entries = ledgerEntries(ledger);
  const doc = loadJson(cpPath) || { _meta: CHECKPOINTS_META, checkpoints: [] };
  if (!Array.isArray(doc.checkpoints)) doc.checkpoints = [];
  doc._meta = CHECKPOINTS_META; // _meta fica fora da corrente — regenerar é seguro

  const genesis = has('--genesis') || doc.checkpoints.length === 0;
  const rubricaRef = val('--rubrica', RUBRICA_DEFAULT);
  const rubricaSha = existsSync(rubricaRef) ? sha256(readFileSync(rubricaRef, 'utf8')) : null;
  // --prompt aceita um PATH (hasheia o conteúdo) OU uma descrição (só referência).
  const promptArg = val('--prompt', genesis ? `${RUBRICA_DEFAULT} §2.4 (prompt adversarial canônico)` : null);
  let promptRef = promptArg, promptSha = null;
  if (promptArg && existsSync(promptArg)) promptSha = sha256(readFileSync(promptArg, 'utf8'));
  const critico = val('--critico', genesis ? 'múltiplos — ver governance/sdd-verification-ledger.json entries[].refutador' : null);

  const cp = buildCheckpoint({
    entries, checkpoints: doc.checkpoints, data, critico,
    rubricaRef, rubricaSha, promptRef, promptSha, historico: genesis,
  });
  if (!cp) {
    console.log(`✓ nada novo a selar: ledger tem ${entries.length} entries e o último checkpoint já cobre até ${doc.checkpoints.length ? doc.checkpoints.at(-1).cobre_ate : 0}.`);
    process.exit(0);
  }
  doc.checkpoints.push(cp);
  if (has('--dry-run')) {
    console.log('DRY-RUN — checkpoint que seria adicionado:\n' + JSON.stringify(cp, null, 2));
    process.exit(0);
  }
  writeFileSync(cpPath, JSON.stringify(doc, null, 2) + '\n');
  console.log(`✓ checkpoint seq=${cp.seq} adicionado a ${cpPath}: sela entries [${cp.cobre_de}..${cp.cobre_ate}) (${cp.n_entries} lote(s))`);
  console.log(`  entries_root=${cp.entries_root.slice(0, 16)}…  checkpoint_hash=${cp.checkpoint_hash.slice(0, 16)}…  prev=${cp.prev_checkpoint_hash ? cp.prev_checkpoint_hash.slice(0, 16) + '…' : '(genesis)'}`);
  console.log(`  proveniência: crítico="${cp.provenancia.critico}" · rubrica=${cp.provenancia.rubrica_ref} (${cp.provenancia.rubrica_sha256 ? cp.provenancia.rubrica_sha256.slice(0, 12) + '…' : 'ausente'})`);
  process.exit(0);
}

function doVerify({ has, val }) {
  const ledgerPath = val('--ledger', LEDGER_DEFAULT);
  const cpPath = val('--checkpoints', CHECKPOINTS_DEFAULT);
  const json = has('--json');
  const check = has('--check');
  const ledger = loadJson(ledgerPath);
  const doc = loadJson(cpPath);
  if (!ledger) { console.log(`ledger-hash-chain: ledger não encontrado (${ledgerPath}) — nada a verificar.`); process.exit(0); }
  if (!doc) { console.log(`ledger-hash-chain: sem checkpoints ainda (${cpPath}). Rode --build --data <hoje> pra selar o genesis.`); process.exit(0); }
  const entries = ledgerEntries(ledger);
  const rubricaRef = (doc.checkpoints && doc.checkpoints[0] && doc.checkpoints[0].provenancia && doc.checkpoints[0].provenancia.rubrica_ref) || RUBRICA_DEFAULT;
  const rubricaCurrentSha = existsSync(rubricaRef) ? sha256(readFileSync(rubricaRef, 'utf8')) : null;
  const r = verify(entries, doc, { rubricaCurrentSha });

  if (json) {
    console.log(JSON.stringify(r, null, 2));
    process.exit(check && !r.ok ? 1 : 0);
  }
  console.log('\nledger-hash-chain — verificação de integridade (transparency-log Rekor-style)\n');
  console.log(`  entries no ledger: ${r.total_entries} · selados por checkpoint: ${r.sealed} · não-pinados (cauda): ${r.unpinned} · checkpoints: ${r.checkpoints}`);
  if (r.ok) {
    console.log(`  🟢 íntegro — a raiz de cada checkpoint bate com o ledger vivo e a corrente está intacta. Nenhuma entry selada foi adulterada.`);
  } else {
    console.log(`  🔴 ${r.problems.length} problema(s) de integridade:`);
    for (const p of r.problems) console.log(`      [seq ${p.seq} · ${p.tipo}] ${p.msg}`);
    console.log(`  → adulteração RETROATIVA detectada. Investigue o diff do ${ledgerPath} / ${cpPath} (append-only Tier 0).`);
  }
  if (r.unpinned > 0) {
    console.log(`  ℹ ${r.unpinned} entry(s) na cauda ainda NÃO seladas — normal após append. Rode --build --data <hoje> pra selá-las (só o selado é tamper-evidente).`);
  }
  if (r.rubrica_drift.length) {
    console.log(`  ℹ rubrica evoluiu desde a selagem em ${r.rubrica_drift.length} checkpoint(s) (esperado — o pino preserva QUAL versão governou cada lote):`);
    for (const d of r.rubrica_drift) console.log(`      seq ${d.seq}: pinada ${d.pinada}… → atual ${d.atual}…`);
  }
  console.log('');
  process.exit(check && !r.ok ? 1 : 0);
}

if (isMain) {
  const args = parseArgs(process.argv);
  if (args.has('--selftest')) {
    const test = fileURLToPath(new URL('./ledger-hash-chain.test.mjs', import.meta.url));
    const { spawnSync } = await import('node:child_process');
    const res = spawnSync(process.execPath, [test], { stdio: 'inherit' });
    process.exit(res.status ?? 1);
  } else if (args.has('--build')) {
    doBuild(args);
  } else if (args.has('--verify')) {
    doVerify(args);
  } else {
    console.log('uso: --verify [--json] [--check] | --build --data YYYY-MM-DD [--genesis] [--critico ..] [--rubrica <path>] [--prompt <path|desc>] [--dry-run] | --selftest');
    process.exit(0);
  }
}
