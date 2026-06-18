#!/usr/bin/env node
// contrato-de-tela.mjs — Gate "Contrato de Tela" (a perna de fidelidade visual do trio-de-tela).
//
// Doc: memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md
// Origem: sessão 2026-06-18 (Wagner: "crie o mecanismo... crie um adversário e valide tudo antes
// de aplicar"). O v0 ("Fidelity Lock", screenshot pareado em CI) foi DERRUBADO por 2 adversários
// (inviável: login/PII/CDN + OKLCH↔Tailwind tautológico + backdoor de prosa). Isto é o v1 validado:
// checagens DETERMINÍSTICAS, sem render, sem auth, sem o réu escrevendo a justificativa. O juízo
// visual subjetivo (cor/ícone/densidade) fica com o humano (screenshot · ADR 0114) — NÃO automatizado.
//
// 3 modos (cada um fecha um buraco real catalogado na sessão):
//   --preflight [base]        Catraca 1 (higiene de base). Falha se <base> (default origin/main) NÃO
//                             é ancestral de HEAD (branch atrás → rebase), ou se o worktree é órfão
//                             (0 arquivos trackeados). Avisa se o diff remove > LIMIAR% dos arquivos
//                             (assinatura do `git worktree --no-checkout` = deleção em massa).
//   --contract <arquivo.json> Catraca 2 (contrato de tela ESTÁTICO). Pra cada seção do contrato:
//                             âncora `data-contract="<id>"` presente no alvo + copy literal presente
//                             + ordem das âncoras = ordem do contrato. Sem render. A âncora é a ponte
//                             Cowork-CSS↔Tailwind sem mapa-de-classes tautológico.
//   --omission [base]         Catraca 3 INVERTIDA (pega o que o handoff OMITIU). Diff <base>...HEAD
//                             nos arquivos-alvo; todo símbolo/rota/teste REMOVIDO que não for citado na
//                             justificativa (commits da branch + --notes <arquivo>) = FALHA.
//                             Inverte a fonte: diff→handoff, nunca handoff→diff.
//
// Node puro (fs + git via execSync), sem deps, sem npm ci. Exit 0 = limpo, 1 = falha (>=1).
// Self-test: node scripts/contrato-de-tela.test.mjs

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { resolve, join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url)); // scripts/
// ROOT default = raiz do repo; --root <dir> sobrescreve (hermético pra self-test / CI).
const ROOT = (() => { const i = process.argv.indexOf('--root'); return i >= 0 ? resolve(process.argv[i + 1]) : resolve(HERE, '..'); })();
const MASS_DELETE_PCT = 30;                            // diff que remove > 30% = warning (--no-checkout)

const log = (...a) => console.log(...a);
const err = (...a) => console.log('X ' + a.join(' '));
const ok = (...a) => console.log('OK ' + a.join(' '));
const warn = (...a) => console.log('! ' + a.join(' '));

function git(args, opts = {}) {
  try { return execSync(`git ${args}`, { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], ...opts }).trim(); }
  catch { return null; }
}

// Coleta .tsx/.ts sob um alvo (dir recursivo OU arquivo), ignorando node_modules e .d.ts.
function collectTargets(alvo) {
  const abs = resolve(ROOT, alvo);
  const out = [];
  const walk = (p) => {
    let st; try { st = statSync(p); } catch { return; }
    if (st.isDirectory()) {
      for (const name of readdirSync(p)) {
        if (name === 'node_modules' || name.startsWith('.')) continue;
        walk(join(p, name));
      }
    } else if (/\.tsx?$/.test(p) && !/\.d\.ts$/.test(p)) {
      out.push(p);
    }
  };
  walk(abs);
  return out.sort();
}

// ── Catraca 1: preflight de base ──────────────────────────────────────────────
function preflight(base = 'origin/main') {
  let fail = 0;
  const tracked = git('ls-files');
  const nTracked = tracked ? tracked.split('\n').filter(Boolean).length : 0;
  if (nTracked === 0) { err(`worktree órfão (0 arquivos trackeados) — base inválida pra trabalhar`); return 1; }

  const isAncestor = git(`merge-base --is-ancestor ${base} HEAD`) !== null
    && execAncestor(base);
  if (!isAncestor) {
    err(`branch atrás de ${base} (não-ancestral) — rebase antes de codar (\`git rebase ${base}\`)`);
    fail++;
  } else {
    ok(`base limpa — ${base} é ancestral de HEAD`);
  }

  // diff de massa removida vs base (assinatura --no-checkout)
  const stat = git(`diff --numstat ${base}...HEAD`);
  if (stat) {
    let removedFiles = 0;
    for (const line of stat.split('\n')) {
      const m = line.match(/^(\d+|-)\t(\d+|-)\t/);
      if (m && m[1] === '0' && m[2] !== '0') removedFiles++; // 0 add, >0 del em arquivo deletado some no numstat; aproximação
    }
    const delOnly = git(`diff --diff-filter=D --name-only ${base}...HEAD`);
    const nDel = delOnly ? delOnly.split('\n').filter(Boolean).length : 0;
    const pct = nTracked ? Math.round((nDel / nTracked) * 100) : 0;
    if (pct > MASS_DELETE_PCT) {
      warn(`diff remove ${nDel}/${nTracked} arquivos (${pct}%) — assinatura de \`worktree --no-checkout\`/deleção em massa. CONFIRMAR.`);
    }
  }
  return fail;
}
// merge-base --is-ancestor usa EXIT CODE (0=ancestral), não stdout — checa via try/catch dedicado.
function execAncestor(base) {
  try { execSync(`git merge-base --is-ancestor ${base} HEAD`, { cwd: ROOT, stdio: 'ignore' }); return true; }
  catch { return false; }
}

// ── Catraca 2: contrato de tela (estático) ────────────────────────────────────
function loadContract(file) {
  const abs = resolve(ROOT, file);
  if (!existsSync(abs)) { err(`contrato não encontrado: ${file}`); process.exit(1); }
  let c;
  try { c = JSON.parse(readFileSync(abs, 'utf8')); }
  catch (e) { err(`contrato JSON inválido (${file}): ${e.message}`); process.exit(1); }
  if (!Array.isArray(c.alvo) || !Array.isArray(c.secoes)) {
    err(`contrato sem \`alvo\` (array) ou \`secoes\` (array): ${file}`); process.exit(1);
  }
  return c;
}

function checkContract(file) {
  const c = loadContract(file);
  const files = c.alvo.flatMap(collectTargets);
  if (!files.length) { err(`nenhum .tsx/.ts no alvo do contrato (${c.alvo.join(', ')})`); return 1; }
  const blob = files.map(f => readFileSync(f, 'utf8')).join('\n');
  // sequência de âncoras data-contract no fonte (ordem de arquivo, depois posição)
  const seq = [];
  for (const f of files) {
    const t = readFileSync(f, 'utf8');
    const re = /data-contract\s*=\s*["'`]([^"'`]+)["'`]/g; let m;
    while ((m = re.exec(t))) seq.push(m[1]);
  }
  let fail = 0;
  log(`tela: ${c.tela ?? '(sem nome)'} · alvo: ${c.alvo.join(', ')} · ${files.length} arquivo(s)`);

  for (const s of c.secoes) {
    if (!s.id) { err(`seção sem id no contrato`); fail++; continue; }
    const hasAnchor = seq.includes(s.id);
    if (!hasAnchor) { err(`seção "${s.id}" sem âncora data-contract no alvo`); fail++; }
    for (const str of (s.copy ?? [])) {
      if (!blob.includes(str)) { err(`copy ausente em "${s.id}": ${JSON.stringify(str)}`); fail++; }
    }
    if (hasAnchor && !s.copy?.some(x => !blob.includes(x))) ok(`seção "${s.id}" — âncora + copy presentes`);
  }

  // ordem: a `ordem` declarada deve ser subsequência da sequência de âncoras no fonte
  if (Array.isArray(c.ordem) && c.ordem.length > 1) {
    const present = c.ordem.filter(id => seq.includes(id));
    let i = 0;
    for (const id of seq) { if (i < present.length && seq.includes(present[i]) && id === present[i]) i++; }
    if (i < present.length) { err(`ordem divergente — esperado ${JSON.stringify(present)} como subsequência das âncoras ${JSON.stringify(seq)}`); fail++; }
    else ok(`ordem das âncoras coerente com o contrato`);
  }
  return fail;
}

// ── Catraca 3: omissão (inverte a fonte) ──────────────────────────────────────
const SYMBOL_RES = [
  /export\s+(?:default\s+)?(?:async\s+)?(?:function|const|class)\s+([A-Za-z0-9_]+)/,
  /^[-]\s*(?:async\s+)?function\s+([A-Za-z0-9_]+)\s*\(/,
  /route\(\s*["'`]([\w.]+)["'`]/,
  /Route::[a-z]+\(\s*["'`]([^"'`]+)["'`]/,
  /\b(?:it|test|describe)\(\s*["'`]([^"'`]+)["'`]/,
];
function checkOmission(base = 'origin/main', alvos, notesFile) {
  const pathArgs = (alvos && alvos.length) ? '-- ' + alvos.map(a => `"${a}"`).join(' ') : '';
  const diff = git(`diff --unified=0 ${base}...HEAD ${pathArgs}`);
  if (diff === null) { err(`git diff falhou (base ${base} existe?)`); return 1; }
  const removed = new Set();
  for (const line of diff.split('\n')) {
    if (!line.startsWith('-') || line.startsWith('---')) continue;
    for (const re of SYMBOL_RES) { const m = line.match(re); if (m) removed.add(m[1]); }
  }
  let just = git(`log ${base}..HEAD --format=%B`) || '';
  if (notesFile && existsSync(resolve(ROOT, notesFile))) just += '\n' + readFileSync(resolve(ROOT, notesFile), 'utf8');
  let fail = 0;
  if (!removed.size) { ok(`nenhum símbolo/rota/teste removido nos arquivos-alvo`); return 0; }
  for (const sym of removed) {
    if (just.includes(sym)) ok(`removido "${sym}" — justificado`);
    else { err(`removido "${sym}" SEM justificativa (cite no PR/handoff ou --notes)`); fail++; }
  }
  return fail;
}

// ── CLI ───────────────────────────────────────────────────────────────────────
function argVal(flag) { const i = process.argv.indexOf(flag); return i >= 0 ? process.argv[i + 1] : null; }
function main() {
  const a = process.argv.slice(2);
  let fail = 0;
  if (a.includes('--preflight')) {
    const base = argVal('--preflight') && !argVal('--preflight').startsWith('--') ? argVal('--preflight') : 'origin/main';
    fail += preflight(base);
  } else if (a.includes('--contract')) {
    fail += checkContract(argVal('--contract'));
  } else if (a.includes('--omission')) {
    const base = argVal('--omission') && !argVal('--omission').startsWith('--') ? argVal('--omission') : 'origin/main';
    const alvoFlag = argVal('--alvo');
    const contractFlag = argVal('--contract-alvo');
    let alvos = alvoFlag ? alvoFlag.split(',') : null;
    if (!alvos && contractFlag) alvos = loadContract(contractFlag).alvo;
    fail += checkOmission(base, alvos, argVal('--notes'));
  } else {
    log('uso: node scripts/contrato-de-tela.mjs [--preflight [base] | --contract <f.json> | --omission [base] (--alvo a,b | --contract-alvo f.json) [--notes f]]');
    process.exit(2);
  }
  if (fail) { log(`\n❌ ${fail} falha(s).`); process.exit(1); }
  log(`\n✅ limpo.`); process.exit(0);
}
main();
