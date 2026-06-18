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
//                             Catraca 2b (SEMÂNTICA · ADR 0286 §5): se o contrato declara
//                             `acordos_estado`, prova que cada `state` do vocabulário acordado aparece
//                             como literal nos DOIS lados — o backend que o EMITE e o frontend que o
//                             TRATA. Backend-emite-mas-frontend-ignora = o bug paired≠connected
//                             (2026-06-18) que a catraca de PRESENÇA não pegou. Ainda estático, sem render.
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

  // Catraca 2b — acordo de vocabulário de `state` backend↔frontend (ADR 0286 §5).
  fail += checkStateAgreements(c, file);
  return fail;
}

// ── Catraca 2b: acordo de estado backend↔frontend (catraca SEMÂNTICA · ADR 0286 §5) ──
// A catraca de PRESENÇA (2a) garante a âncora + a copy, mas não o ACORDO DE VALORES: o `connect`
// devolve `state:'paired'`, o `status` devolve `state:'connected'`, e o ReconnectModal só tratava
// 'connected' → a resposta de sucesso caía no ramo de erro vermelho (incidente 2026-06-18). O
// contrato estava estruturalmente presente e o comportamento quebrado — passou no gate.
//
// Cada `acordo` em `acordos_estado` declara um VOCABULÁRIO de `state` compartilhado (ex: o conjunto
// que significa "sessão ativa = SUCESSO, não erro"). O gate prova que TODA palavra do acordo aparece
// como literal entre aspas nos DOIS lados — o backend que a EMITE e o frontend que a TRATA:
//   - `valores`  — os state-strings acordados (ex: ["paired","connected"]).
//   - `backend`  — fonte(s) que emitem o state (arquivo/dir PHP; ex: ChannelsController.php).
//   - `frontend` — fonte(s) que tratam o state (default = `alvo` do contrato; ex: reconnectState.ts).
//   - `escopo`   — (opcional, default "global") a quem o acordo se aplica: global | vertical:<x>
//                  | cliente:biz=<n> | persona:<p> | tela:<rota>. Default global = não vaza Tier 0.
//   - `verdict`  — (opcional, default "aprovado") aprovado | recusado.
// Veredito binário, derivado do CÓDIGO (comentário NÃO conta — senão é "backdoor de prosa", RUNBOOK §4)
// e só em posição de VALOR (não a chave `'x' =>`). Sem render/auth/DB — mesmo idioma da catraca 2a.
//   FALHA A: estado declarado que o backend NÃO emite (drift de contrato / valor morto).
//   FALHA B: backend EMITE o estado mas o frontend NÃO o trata (divergência paired≠connected).
//   FALHA C: `escopo` em formato inválido (typo que mis-escoparia o veredito).
const SOURCE_EXTS = /\.(php|tsx?|jsx?|mjs|cjs)$/;
const ESCOPO_RE = /^(global|vertical:[\w-]+|cliente:biz=\d+|persona:[\w-]+|tela:[\w./-]+)$/;
// Remove comentários antes de casar o literal — um `state` citado em JSDoc/`//`/`#` NÃO prova que o
// código o trata (era o furo do v0: "backdoor de prosa", RUNBOOK §4). Heurística suficiente p/ PHP+TS;
// um `//` dentro de string vira falso-NEGATIVO (gate reclama, humano confere — direção segura).
function stripComments(src) {
  return src
    .replace(/\/\*[\s\S]*?\*\//g, ' ')   // bloco /* … */ (inclui JSDoc /** … */)
    .replace(/\/\/[^\n]*/g, ' ')          // linha // …
    .replace(/(^|\s)#[^\n]*/g, '$1 ');    // linha PHP # … (não casa '#fff' colado em aspas)
}
function readSourceBlob(paths) {
  const files = [];
  const walk = (p) => {
    let st; try { st = statSync(p); } catch { return; }
    if (st.isDirectory()) {
      for (const name of readdirSync(p)) {
        if (name === 'node_modules' || name.startsWith('.')) continue;
        walk(join(p, name));
      }
    } else if (SOURCE_EXTS.test(p) && !/\.d\.ts$/.test(p)) {
      files.push(p);
    }
  };
  for (const p of (Array.isArray(paths) ? paths : [paths])) walk(resolve(ROOT, p));
  const raw = files.map(f => readFileSync(f, 'utf8')).join('\n');
  return { files: files.sort(), blob: raw, code: stripComments(raw) };
}
// state-string como literal entre aspas EM POSIÇÃO DE VALOR: 'v' | "v" | `v`, mas não a chave `'v' =>`
// (senão `'paired' => true` faria o gate achar que o backend ainda emite 'paired' após renomear o state).
// Recebe o `code` (já sem comentários) — não o blob cru.
function hasStateLiteral(code, v) {
  const e = String(v).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp('([\'"`])' + e + '\\1(?!\\s*=>)').test(code);
}
function checkStateAgreements(c, file) {
  const acordos = c.acordos_estado;
  if (acordos === undefined) return 0;                          // bloco opcional — sem acordo, nada a checar
  if (!Array.isArray(acordos)) { err(`\`acordos_estado\` deve ser array (${file})`); return 1; }
  let fail = 0;
  for (const ac of acordos) {
    if (!ac.id) { err(`acordo de estado sem id no contrato`); fail++; continue; }
    if (!Array.isArray(ac.valores) || !ac.valores.length) { err(`acordo "${ac.id}" sem \`valores\` (array não-vazio)`); fail++; continue; }
    if (!ac.backend) { err(`acordo "${ac.id}" sem \`backend\` (fonte que emite o state)`); fail++; continue; }
    // escopo (default "global" = seguro, não vaza Tier 0) + verdict (default "aprovado") — base do eixo D5.
    const escopo = ac.escopo ?? 'global';
    if (!ESCOPO_RE.test(escopo)) { err(`acordo "${ac.id}": escopo inválido ${JSON.stringify(escopo)} — use global | vertical:<x> | cliente:biz=<n> | persona:<p> | tela:<rota>`); fail++; continue; }
    const verdict = ac.verdict ?? 'aprovado';
    if (!['aprovado', 'recusado'].includes(verdict)) { err(`acordo "${ac.id}": verdict inválido ${JSON.stringify(verdict)} — use aprovado | recusado`); fail++; continue; }
    if (verdict === 'recusado') { ok(`acordo "${ac.id}" [escopo:${escopo}] — recusado (registrado, não enforçado)`); continue; }
    const fePaths = ac.frontend ?? c.alvo;
    const be = readSourceBlob(ac.backend);
    const fe = readSourceBlob(fePaths);
    if (!be.files.length) { err(`acordo "${ac.id}": backend não encontrado (${[].concat(ac.backend).join(', ')})`); fail++; continue; }
    if (!fe.files.length) { err(`acordo "${ac.id}": frontend não encontrado (${[].concat(fePaths).join(', ')})`); fail++; continue; }
    let local = 0;
    for (const v of ac.valores) {
      const inBe = hasStateLiteral(be.code, v);
      const inFe = hasStateLiteral(fe.code, v);
      if (!inBe) { err(`acordo "${ac.id}": estado ${JSON.stringify(v)} declarado mas o backend não emite (drift de contrato)`); local++; continue; }
      if (!inFe) { err(`acordo "${ac.id}": backend emite ${JSON.stringify(v)} mas o frontend NÃO trata — divergência de vocabulário (catraca semântica · ADR 0286)`); local++; }
    }
    if (!local) ok(`acordo "${ac.id}" [escopo:${escopo}] — vocabulário {${ac.valores.join(', ')}} coerente backend↔frontend`);
    fail += local;
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
// ── --map: mapa protótipo→prod DERIVADO (régua 5: não-mantido-à-mão · mata o SYNC_LOG) ──
// Gera on-demand de `*.contract.json` (.fonte) + âncoras `data-contract` + git log.
// `--map`        = só imprime a tabela (informativo, exit 0).
// `--map --check` = FALHA se `fonte` aponta arquivo inexistente OU seção sem âncora e
//                   sem `<!-- design-deviation -->` no alvo.
function listContracts() {
  const out = git('ls-files "*.contract.json"');
  return out ? out.split('\n').filter(p => p && !/EXEMPLO/i.test(p)) : [];
}
function anchorsOf(alvo) {
  const ids = new Set();
  for (const f of (alvo || []).flatMap(collectTargets)) {
    const re = /data-contract\s*=\s*["'`]([^"'`]+)["'`]/g; let m;
    const t = readFileSync(f, 'utf8');
    while ((m = re.exec(t))) ids.add(m[1]);
  }
  return ids;
}
function hasDeviation(alvo) {
  return (alvo || []).flatMap(collectTargets).some(f => readFileSync(f, 'utf8').includes('design-deviation'));
}
function buildMap(doCheck) {
  const contracts = listContracts();
  let fail = 0;
  log('# Mapa protótipo → produção  ·  GERADO por `contrato-de-tela.mjs --map` — NÃO editar à mão\n');
  log('| Tela | Fonte (protótipo) | Seções | Último commit no alvo | Estado |');
  log('|---|---|---:|---|---|');
  if (!contracts.length) log('| _(nenhum contrato ativo)_ | — | — | — | — |');
  for (const cf of contracts) {
    let c;
    try { c = JSON.parse(readFileSync(resolve(ROOT, cf), 'utf8')); }
    catch { err(`contrato ilegível: ${cf}`); fail++; continue; }
    const tela = c.tela ?? cf;
    const fonteOk = !!c.fonte && existsSync(resolve(ROOT, c.fonte));
    const ids = anchorsOf(c.alvo);
    const secs = c.secoes || [];
    const missing = secs.filter(s => !ids.has(s.id));
    const dev = hasDeviation(c.alvo);
    const last = git(`log -1 --format=%h -- ${(c.alvo || []).map(x => `"${x}"`).join(' ')}`) || '—';
    const estado = !fonteOk ? '🔴 fonte quebrada'
      : missing.length === 0 ? '🟢 portado'
        : dev ? '⚠️ âncora-faltando (desvio declarado)'
          : '🔴 âncora-faltando';
    log(`| ${tela} | \`${c.fonte ?? '—'}\` ${fonteOk ? '✓' : '✗'} | ${secs.length - missing.length}/${secs.length} | ${last} | ${estado} |`);
    if (doCheck) {
      if (!fonteOk) { err(`${tela}: fonte aponta arquivo inexistente — ${c.fonte}`); fail++; }
      if (missing.length && !dev) { err(`${tela}: seção(ões) sem âncora e sem design-deviation — ${missing.map(s => s.id).join(', ')}`); fail++; }
    }
  }
  return fail;
}

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
  } else if (a.includes('--map')) {
    fail += buildMap(a.includes('--check'));
    if (!a.includes('--check')) process.exit(0); // --map informativo: só a tabela, sem resumo
  } else {
    log('uso: node scripts/contrato-de-tela.mjs [--preflight [base] | --contract <f.json> | --omission [base] (--alvo a,b | --contract-alvo f.json) [--notes f] | --map [--check]]');
    process.exit(2);
  }
  if (fail) { log(`\n❌ ${fail} falha(s).`); process.exit(1); }
  log(`\n✅ limpo.`); process.exit(0);
}
main();
