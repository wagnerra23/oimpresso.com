#!/usr/bin/env node
// @ts-check
/**
 * detect-ui-drift.mjs — M1: detector de MUDANÇA DE UI NÃO-DECLARADA (eixo de AUTORIZAÇÃO).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A PERGUNTA QUE ELE RESPONDE (a dor do Wagner, textual): "cada customização não é
 * pega como alteração da máquina — parece que não sabe que alterou."
 * ─────────────────────────────────────────────────────────────────────────────
 * Toda outra máquina do loop responde "o QUE mudou" (design-spec-gate: estrutura;
 * reconcile-triplet: slots PT-01; design-diff: computed-style). NENHUMA responde
 * "foi AUTORIZADA?". Este é o eixo ortogonal que faltava: quando uma `.tsx` de tela
 * muda num PR, exige um SINAL DE AUTORIZAÇÃO FRESCO no MESMO PR. Sem sinal → 🚩.
 *
 * O protótipo Cowork aprovado é a fonte da verdade da tela. Duas formas legítimas de
 * um `.tsx` mudar — cada uma limpa o flag:
 *   1. DESVIO DECLARADO   — `divergence_from_blueprint` no charter irmão vira uma razão
 *                            REAL (não "none"/"n/a"), adicionada/alterada NESTE PR.
 *   2. PROTÓTIPO APLICADO  — o design foi seguido/atualizado, sinalizado por REUSO do
 *                            vocabulário existente (zero invenção — Wagner 2026-07-12):
 *                            (a) `related_prototype` do charter mudou pra um protótipo
 *                                REAL neste PR, OU
 *                            (b) entrada NOVA em `prototipo-ui/SYNC_LOG.md` citando a
 *                                tela (o registro que o loop Cowork↔Code já usa).
 * Qualquer outra mudança da `.tsx` (bugfix de layout, refactor, "customização") sem
 * um desses sinais → drift não-declarado → 🚩 FLAG.
 *
 * HONESTIDADE (o teto — L-24 "presença ≠ correção", §5 proibicoes / charter-sync-gate
 * rejeitado 2026-07-01):
 *   - Mede VALOR SEMÂNTICO do campo, NUNCA "a linha do charter apareceu no diff":
 *       · `divergence_from_blueprint` só limpa se virar razão real (≠ none/n/a/vazio).
 *       · `related_prototype` só limpa se MUDAR pra um protótipo REAL (≠ n/a→n/a).
 *   - FRESHNESS por construção: só olha o DIFF deste PR (base...HEAD). Uma linha velha
 *     de desvio (não tocada no PR) NÃO limpa — senão declarar 1 desvio cegaria a tela
 *     pra sempre (o buraco da anistia-por-tela do reconcile).
 *   - NÃO julga estética (cor/spacing/densidade). Isso é o `design-diff.mjs` (ADR 0299,
 *     medido) + o olho do Wagner no screenshot = juiz final. Esta máquina só sabe se a
 *     mudança foi AUTORIZADA, não se ficou bonita. São eixos ortogonais, de propósito.
 *   - Escopo do contrato v1 = telas com charter irmão (`<Tela>.charter.md`). Um `.tsx`
 *     tocado sem charter irmão (ex: `_components/`) vira NOTA advisory (fora do contrato),
 *     não flag — vetor de drift real, honestamente marcado como gap conhecido de v1.
 *
 * ADVISORY de nascença (ADR 0314 — required = só Tier-0; ADR 0336 — advisory tem que
 * ficar VISÍVEL): emite `::warning::` + job summary, exit 0. `--strict` faz exit 1 nos
 * flags (caminho de promoção honesto — a asserção "mudou sem declaração" é verdadeira
 * ou falsa, nunca teatro). Registrado em gates-registry.json com anchor + promote_by.
 *
 * REUSO (Tier 0 — não duplica): `fmScalar` espelha reconcile-triplet.mjs:68; a forma
 * diff-aware "tsx tocado → irmão tocado?" espelha design-return-gate.yml; SYNC_LOG é o
 * mesmo registro do loop. Zero vocabulário novo.
 *
 * Uso:
 *   node scripts/governance/detect-ui-drift.mjs [--base=<ref>] [--json] [--strict]
 *   node scripts/governance/detect-ui-drift.mjs --selftest   (delega p/ detect-ui-drift.test.mjs)
 *   npm run ui-drift:check
 *
 * Refs: ADR 0255 (design-spec — o QUE mudou, ortogonal) · ADR 0299 (design-diff — M2 visual) ·
 *   ADR 0314 (poda: advisory só com promote_by) · ADR 0336 (advisory visível) ·
 *   memory/requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md (a norma que esta máquina defende).
 */
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const IN_CI = !!process.env.GITHUB_ACTIONS;

/* ───────────────────────────── git (thin shell) ───────────────────────────── */

function git(args) {
  try {
    return execFileSync('git', args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  } catch (e) {
    return e && e.stdout ? String(e.stdout) : '';
  }
}
const changedFiles = (base) =>
  git(['diff', '--name-only', `${base}...HEAD`]).split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
const fileDiff = (base, path) => git(['diff', '--unified=0', `${base}...HEAD`, '--', path]);

/* ─────────────────── helpers semânticos PUROS (testáveis) ─────────────────── */

const stripQ = (s) => (s || '').trim().replace(/^["']|["']$/g, '').trim();

/** razão vazia/placeholder — NÃO conta como desvio declarado. */
export const isNoneReason = (v) => {
  const s = stripQ(v).toLowerCase();
  return !s || s === 'none' || s === 'nenhuma' || s === 'nenhum' || /^n[\/\\]?a\b/.test(s);
};

/** aponta pra um protótipo REAL (não "n/a (herda PT-0X)"). */
export const isRealPrototype = (v) =>
  !isNoneReason(v) && /(prototipo-ui|cowork|design-handoff|\.jsx|\.html)/i.test(stripQ(v));

/** valores de uma chave nas linhas ADICIONADAS (+) de um diff --unified=0. */
export const addedValues = (diff, key) => {
  const re = new RegExp('^\\+\\s*' + key + ':\\s*(.+?)\\s*$', 'gim');
  const out = [];
  let m;
  while ((m = re.exec(diff || ''))) out.push(m[1]);
  return out;
};
/** valores de uma chave nas linhas REMOVIDAS (-) de um diff --unified=0. */
export const removedValues = (diff, key) => {
  const re = new RegExp('^-\\s*' + key + ':\\s*(.+?)\\s*$', 'gim');
  const out = [];
  let m;
  while ((m = re.exec(diff || ''))) out.push(m[1]);
  return out;
};

/**
 * CLASSIFICADOR PURO — o coração testável (sem git, sem fs).
 * @param {{charterDiff?: string, syncLogAdded?: string[], telaTokens?: string[]}} input
 * @returns {{estado:'CLEARED'|'FLAG', motivo:string}}
 */
export function classifyTela({ charterDiff = '', syncLogAdded = [], telaTokens = [] }) {
  // 1) DESVIO DECLARADO fresco com razão real
  if (addedValues(charterDiff, 'divergence_from_blueprint').some((v) => !isNoneReason(v))) {
    return { estado: 'CLEARED', motivo: 'desvio declarado — divergence_from_blueprint com razão real, fresco no PR' };
  }
  // 2a) PROTÓTIPO APLICADO — related_prototype MUDOU pra um protótipo real
  const protoAdded = addedValues(charterDiff, 'related_prototype');
  const protoRemoved = removedValues(charterDiff, 'related_prototype').map(stripQ);
  if (protoAdded.some((v) => isRealPrototype(v) && !protoRemoved.includes(stripQ(v)))) {
    return { estado: 'CLEARED', motivo: 'protótipo aplicado — related_prototype mudou pra protótipo real, fresco no PR' };
  }
  // 2b) PROTÓTIPO APLICADO — SYNC_LOG novo citando a tela (registro do loop Cowork↔Code)
  const tokens = telaTokens.filter(Boolean).map((t) => t.toLowerCase());
  if (syncLogAdded.some((line) => { const l = line.toLowerCase(); return tokens.some((t) => l.includes(t)); })) {
    return { estado: 'CLEARED', motivo: 'protótipo aplicado — SYNC_LOG registrou a aplicação de design da tela, fresco no PR' };
  }
  return {
    estado: 'FLAG',
    motivo: 'a .tsx mudou sem sinal de autorização (nem desvio declarado, nem protótipo aplicado, nem SYNC_LOG)',
  };
}

/* ───────────────────────────── runner (git-aware) ─────────────────────────── */

/** charter irmão de uma tela (mesmo dir + basename). null se não existe. */
function siblingCharter(tsxRel) {
  const charterRel = tsxRel.replace(/\.tsx$/, '.charter.md');
  return existsSync(join(ROOT, charterRel)) ? charterRel : null;
}
/** tokens que identificam a tela numa linha de SYNC_LOG (evita "Index" solto). */
function telaTokensFor(tsxRel) {
  const rel = tsxRel.replace(/^resources\/js\/Pages\//, '').replace(/\.tsx$/, ''); // Sells/Index
  return [rel, 'Pages/' + rel, rel.replace(/\//g, '\\')];
}

function run(argv) {
  const base = (argv.find((a) => a.startsWith('--base=')) || '--base=origin/main').split('=')[1];
  const strict = argv.includes('--strict');
  const asJson = argv.includes('--json');

  const files = changedFiles(base);
  const pagesTsx = files.filter((f) => /^resources\/js\/Pages\/.+\.tsx$/.test(f));

  const syncLogDiff = fileDiff(base, 'prototipo-ui/SYNC_LOG.md');
  const syncLogAdded = syncLogDiff.split(/\r?\n/).filter((l) => l.startsWith('+') && !l.startsWith('+++'));

  const flags = [];
  const cleared = [];
  const semCharter = [];

  for (const tsx of pagesTsx) {
    const charterRel = siblingCharter(tsx);
    if (!charterRel) { semCharter.push(tsx); continue; }
    const res = classifyTela({
      charterDiff: fileDiff(base, charterRel),
      syncLogAdded,
      telaTokens: telaTokensFor(tsx),
    });
    (res.estado === 'FLAG' ? flags : cleared).push({ tsx, charter: charterRel, ...res });
  }

  const result = { base, total_tsx: pagesTsx.length, flags, cleared, sem_charter: semCharter };

  if (asJson) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    render(result);
  }

  // ADVISORY: exit 0 sempre (só --strict falha nos flags). A asserção é factual → promovível.
  if (strict && flags.length) process.exitCode = 1;
  return result;
}

function render(r) {
  console.log(`M1 · detector de mudança de UI não-declarada — base ${r.base}`);
  if (!r.total_tsx) { console.log('  Nenhuma .tsx de tela tocada neste diff. OK.'); return; }
  console.log(`  telas tocadas: ${r.total_tsx} · limpas: ${r.cleared.length} · flags: ${r.flags.length} · sem charter (fora do contrato v1): ${r.sem_charter.length}`);
  console.log('');
  for (const c of r.cleared) console.log(`  ✓ ${c.tsx}\n      ${c.motivo}`);
  for (const f of r.flags) {
    console.log(`  🚩 ${f.tsx}\n      ${f.motivo}\n      → declare o desvio (divergence_from_blueprint no ${f.charter}) OU registre a aplicação (related_prototype / SYNC_LOG).`);
    if (IN_CI) console.log(`::warning file=${f.tsx}::mudança de UI não-declarada (M1) — .tsx mudou sem desvio declarado nem protótipo aplicado. Ver memory/requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md`);
  }
  if (r.sem_charter.length) {
    console.log('');
    console.log('  ⓘ fora do contrato v1 (sem charter irmão — ex: _components):');
    for (const s of r.sem_charter) console.log(`      - ${s}`);
  }
  // job summary (visível no PR — ADR 0336)
  if (IN_CI && process.env.GITHUB_STEP_SUMMARY && r.flags.length) {
    const L = ['## M1 — mudança de UI não-declarada', '',
      'Estas telas mudaram a `.tsx` **sem** sinal de autorização fresco neste PR:', ''];
    for (const f of r.flags) L.push(`- \`${f.tsx}\``);
    L.push('', 'Conserto: declare o desvio (`divergence_from_blueprint` no charter irmão) **ou** registre a aplicação do design (`related_prototype` mudado / entrada no `SYNC_LOG.md`).',
      '', 'Norma: `memory/requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md`. Advisory (não bloqueia).');
    try { execFileSync('bash', ['-c', `cat >> "$GITHUB_STEP_SUMMARY"`], { input: L.join('\n') + '\n' }); } catch { /* best-effort */ }
  }
}

/* ─────────────────────────────── fmScalar (reuso) ─────────────────────────── */
// Espelha reconcile-triplet.mjs:68 — exportado pra quem quiser ler frontmatter escalar.
export function fmScalar(fm, key) {
  const m = (fm || '').match(new RegExp('^\\s*' + key + ':\\s*["\\\']?(.+?)["\\\']?\\s*$', 'm'));
  return m ? m[1].trim() : null;
}

/* ─────────────────────────────────── main ─────────────────────────────────── */
// Só executa quando invocado direto (node detect-ui-drift.mjs) — NUNCA no import
// (o .test.mjs importa classifyTela; sem o guard, importar dispararia o runner).

const { fileURLToPath } = await import('node:url');
const { realpathSync } = await import('node:fs');
const norm = (p) => { try { return realpathSync(p).replace(/\\/g, '/').toLowerCase(); } catch { return (p || '').replace(/\\/g, '/').toLowerCase(); } };
const isEntry = !!process.argv[1] && norm(fileURLToPath(import.meta.url)) === norm(process.argv[1]);

if (isEntry) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) {
    const here = fileURLToPath(new URL('.', import.meta.url));
    try {
      execFileSync('node', [join(here, 'detect-ui-drift.test.mjs')], { stdio: 'inherit' });
    } catch { process.exitCode = 1; }
  } else {
    run(argv);
  }
}
