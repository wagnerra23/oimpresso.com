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
import { auditar, validarContrato } from './auditar-intencao-fluxo.mjs';

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
// HONESTO — o que prova e o que NÃO prova: derivado do CÓDIGO (comentário NÃO conta), em posição de
// VALOR (não a chave `'x' =>`), o gate prova que o vocabulário é MENCIONADO nos dois lados. Pega
// "backend renomeou/sumiu o state" (FALHA A) e "frontend totalmente ignorante do state" (FALHA B, a
// forma do bug 2026-06-18). NÃO prova que o frontend TRATA o state certo — um `'paired' | 'connected'`
// num tipo TS menciona sem tratar; o handling é coberto pelo vitest `reconnect-session-active.test.ts`
// (#2984). Catraca = costura PHP↔TS + ratchet de regressão, não detector de handling. Sem render/auth/DB.
//   FALHA A: estado declarado que o backend NÃO emite (drift de contrato / renomeado / valor morto).
//   FALHA B: backend EMITE o estado mas o frontend NÃO o MENCIONA em código (ignorância total).
//   FALHA C: `escopo` em formato inválido (typo que mis-escoparia o veredito · Tier 0).
const SOURCE_EXTS = /\.(php|tsx?|jsx?|mjs|cjs)$/;
// `tela:` sem `.` → mata path-traversal (`tela:../../x`). vertical/persona = slug; cliente = biz=N.
const ESCOPO_RE = /^(global|vertical:[\w-]+|cliente:biz=\d+|persona:[\w-]+|tela:[\w/-]+)$/;
// Remove comentários antes de casar o literal (um `state` citado em JSDoc/`//`/`#` NÃO prova handling).
// String-aware: caminha char-a-char respeitando aspas, então `'http://x//y'` NÃO é confundido com
// comentário de linha (o falso-positivo achado pelo adversário). `#` só conta como comentário PHP em
// início de token (preserva `this.#campo` e `'#fff'`).
function stripComments(src) {
  let out = '', i = 0, q = null;
  const n = src.length;
  while (i < n) {
    const c = src[i], d = src[i + 1];
    if (q) {                                    // dentro de string: copia (trata escape), detecta fecho
      out += c;
      if (c === '\\') { out += d ?? ''; i += 2; continue; }
      if (c === q) q = null;
      i++; continue;
    }
    if (c === '"' || c === "'" || c === '`') { q = c; out += c; i++; continue; }
    if (c === '/' && d === '*') { const e = src.indexOf('*/', i + 2); out += ' '; i = e < 0 ? n : e + 2; continue; }
    if (c === '/' && d === '/') { const e = src.indexOf('\n', i); out += ' '; i = e < 0 ? n : e; continue; }
    if (c === '#') {                            // comentário PHP só em início de token (não `this.#x`/`'#fff'`)
      const p = out.length ? out[out.length - 1] : '\n';
      if (p === ' ' || p === '\n' || p === '\t' || p === '\r') { const e = src.indexOf('\n', i); out += ' '; i = e < 0 ? n : e; continue; }
    }
    out += c; i++;
  }
  return out;
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
      if (!inFe) { err(`acordo "${ac.id}": backend emite ${JSON.stringify(v)} mas o frontend NÃO menciona ${JSON.stringify(v)} em código — divergência de vocabulário (catraca semântica · ADR 0286)`); local++; }
    }
    if (!local) ok(`acordo "${ac.id}" [escopo:${escopo}] — vocabulário {${ac.valores.join(', ')}} coerente backend↔frontend`);
    fail += local;
  }
  return fail;
}

// ── Onda 2: resolução de escopo (veredito-por-tenant · NÃO-VAZAMENTO Tier 0) ───────────────
// Vários vereditos do MESMO conceito (`id`) em escopos diferentes → resolve QUAL vale num contexto.
// O mais ESPECÍFICO vence (later-wins em empate), igual a herança da Constituição UI ("herda, nunca
// contradiz") e o `resolutionOrder` do W3C DTCG. PROPRIEDADE TIER 0 (P0): um veredito `cliente:biz=4`
// NÃO aplica a `biz=7` — escopo que não casa o contexto é IGNORADO, então NÃO vaza entre tenants.
//
// ⚠️ DORMENTE (invariante PRÉ-CABEADA, não load-bearing): hoje nenhum contrato real tem veredito
// multi-escopo (o único é `global`) e NADA consome `--resolve` pra gatear por tenant — é infra +
// trava P0 armada ANTES do consumidor. Honesto chamar de pré-cabeada, não "enforcement vivo". Vira
// load-bearing quando a 2ª tela escopada existir (mesmo critério que adiou a herança de zona · #2995).
const ESCOPO_SPEC = ['global', 'vertical:', 'persona:', 'cliente:biz=', 'tela:']; // índice = especificidade
function escopoSpec(escopo) {
  if (escopo === 'global') return 0;
  for (let i = 1; i < ESCOPO_SPEC.length; i++) if (escopo.startsWith(ESCOPO_SPEC[i])) return i;
  return -1;
}
function escopoAplica(escopo, ctx) {
  if (escopo === 'global') return true;
  if (escopo.startsWith('vertical:')) return ctx.vertical != null && ctx.vertical === escopo.slice(9);
  if (escopo.startsWith('persona:')) return ctx.persona != null && ctx.persona === escopo.slice(8);
  if (escopo.startsWith('cliente:biz=')) return ctx.cliente != null && String(ctx.cliente) === escopo.slice(12);
  if (escopo.startsWith('tela:')) return ctx.tela != null && ctx.tela === escopo.slice(5);
  return false;
}
// Resolve o veredito vencedor por `id` pra um contexto. Escopo que não casa o ctx é DESCARTADO
// (não-vazamento). Entre os que casam, maior especificidade vence; empate → o último (later-wins).
function resolveVereditos(vereditos, ctx) {
  const win = new Map();
  for (const v of vereditos) {
    const escopo = v.escopo ?? 'global';
    if (!escopoAplica(escopo, ctx)) continue;          // ← não-vazamento Tier 0
    const spec = escopoSpec(escopo);
    const cur = win.get(v.id);
    if (!cur || spec >= cur._spec) win.set(v.id, { ...v, escopo, _spec: spec });
  }
  return [...win.values()];
}
// `--ctx cliente:biz=4,tela:Foo,vertical:vestuario,persona:larissa` → objeto de contexto.
function parseCtx(s) {
  const ctx = {};
  for (const tok of (s || '').split(',').map(t => t.trim()).filter(Boolean)) {
    if (tok.startsWith('cliente:biz=')) ctx.cliente = tok.slice(12);
    else if (tok.startsWith('vertical:')) ctx.vertical = tok.slice(9);
    else if (tok.startsWith('persona:')) ctx.persona = tok.slice(8);
    else if (tok.startsWith('tela:')) ctx.tela = tok.slice(5);
  }
  return ctx;
}
// --resolve <contrato.json> --ctx <tokens>: imprime o veredito vencedor por conceito naquele contexto.
function resolveContract(file, ctxStr) {
  const c = loadContract(file);
  const acordos = Array.isArray(c.acordos_estado) ? c.acordos_estado : [];
  const ctx = parseCtx(ctxStr);
  log(`tela: ${c.tela ?? file} · contexto: ${JSON.stringify(ctx)}`);
  let fail = 0;
  for (const ac of acordos) {
    const escopo = ac.escopo ?? 'global';
    if (!ESCOPO_RE.test(escopo)) { err(`acordo "${ac.id}": escopo inválido ${JSON.stringify(escopo)}`); fail++; }
  }
  if (fail) return fail;
  if (!acordos.length) { log('(contrato sem `acordos_estado`)'); return 0; }
  const ganho = new Map(resolveVereditos(acordos, ctx).map(g => [g.id, g]));
  for (const id of [...new Set(acordos.map(a => a.id))]) {
    const g = ganho.get(id);
    if (g) ok(`conceito "${id}" → vence [escopo:${g.escopo}] verdict=${g.verdict ?? 'aprovado'}`);
    else warn(`conceito "${id}" → nenhum veredito aplica ao contexto (cai pro default do código)`);
  }
  return 0;
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
function listIntentContracts() {
  const dir = resolve(ROOT, 'prototipo-ui/contrato');
  if (!existsSync(dir)) return [];
  return readdirSync(dir)
    .filter(name => name.endsWith('.intent.json') && !/EXEMPLO/i.test(name))
    .map(name => `prototipo-ui/contrato/${name}`)
    .sort();
}
function checkIntentMap(file) {
  let c;
  try { c = JSON.parse(readFileSync(resolve(ROOT, file), 'utf8')); }
  catch { err(`contrato de intenção ilegível: ${file}`); return 1; }
  const invalid = validarContrato(c);
  if (invalid) { err(`${file}: ${invalid}`); return 1; }
  const sourceOk = !!c.fonte && existsSync(resolve(ROOT, c.fonte));
  const charterOk = !!c.charter && existsSync(resolve(ROOT, c.charter));
  const targets = c.alvo.map(path => resolve(ROOT, path));
  const targetsOk = targets.every(existsSync);
  let linked = false;
  if (charterOk) {
    const charter = readFileSync(resolve(ROOT, c.charter), 'utf8');
    linked = charter.includes(`intent_contract: ${file}`) && charter.includes(c.fonte);
  }
  const issues = targetsOk ? auditar(c, targets.map(path => readFileSync(path, 'utf8')).join('\n')) : [{ kind: 'alvo-ausente' }];
  const state = sourceOk && charterOk && targetsOk && linked && !issues.length ? '🟢 íntegro' : '🔴 elo quebrado';
  log(`| ${c.tela ?? file} | \`${c.fonte ?? '—'}\` ${sourceOk ? '✓' : '✗'} | \`${c.charter ?? '—'}\` ${charterOk && linked ? '✓' : '✗'} | ${c.fluxos.length}/${c.fluxos.length} | ${state} |`);
  if (!sourceOk) err(`${c.tela ?? file}: protótipo ausente — ${c.fonte ?? 'sem fonte'}`);
  if (!charterOk) err(`${c.tela ?? file}: charter ausente — ${c.charter ?? 'sem charter'}`);
  else if (!linked) err(`${c.tela ?? file}: charter não aponta para o contrato e protótipo corretos`);
  if (!targetsOk) err(`${c.tela ?? file}: alvo ausente`);
  for (const issue of issues) err(`${c.tela ?? file}: ${issue.kind}${issue.flow ? ` — ${issue.flow}: ${issue.literal}` : ''}`);
  return sourceOk && charterOk && targetsOk && linked && !issues.length ? 0 : 1;
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
  log('| Tela | Fonte (protótipo) | Seções | Vereditos (escopo) | Último commit no alvo | Estado |');
  log('|---|---|---:|---|---|---|');
  if (!contracts.length) log('| _(nenhum contrato ativo)_ | — | — | — | — | — |');
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
    const acordos = Array.isArray(c.acordos_estado) ? c.acordos_estado : [];
    const vereditosCol = acordos.length ? acordos.map(a => `${a.id}[${a.escopo ?? 'global'}:${a.verdict ?? 'aprovado'}]`).join('<br>') : '—';
    const estado = !fonteOk ? '🔴 fonte quebrada'
      : missing.length === 0 ? '🟢 portado'
        : dev ? '⚠️ âncora-faltando (desvio declarado)'
          : '🔴 âncora-faltando';
    log(`| ${tela} | \`${c.fonte ?? '—'}\` ${fonteOk ? '✓' : '✗'} | ${secs.length - missing.length}/${secs.length} | ${vereditosCol} | ${last} | ${estado} |`);
    if (doCheck) {
      if (!fonteOk) { err(`${tela}: fonte aponta arquivo inexistente — ${c.fonte}`); fail++; }
      if (missing.length && !dev) { err(`${tela}: seção(ões) sem âncora e sem design-deviation — ${missing.map(s => s.id).join(', ')}`); fail++; }
    }
  }
  const intents = listIntentContracts();
  log('\n## Intenção de fluxo (Charter → protótipo → produção)\n');
  log('| Tela | Fonte (protótipo) | Charter ligado | Fluxos | Estado |');
  log('|---|---|---|---:|---|');
  if (!intents.length) log('| _(nenhum contrato de intenção ativo)_ | — | — | — | — |');
  for (const file of intents) {
    const localFail = checkIntentMap(file);
    if (doCheck) fail += localFail;
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
  } else if (a.includes('--resolve')) {
    fail += resolveContract(argVal('--resolve'), argVal('--ctx'));
  } else if (a.includes('--map')) {
    fail += buildMap(a.includes('--check'));
    if (!a.includes('--check')) process.exit(0); // --map informativo: só a tabela, sem resumo
  } else {
    log('uso: node scripts/contrato-de-tela.mjs [--preflight [base] | --contract <f.json> | --omission [base] (--alvo a,b | --contract-alvo f.json) [--notes f] | --map [--check] | --resolve <f.json> --ctx <cliente:biz=N,tela:X,…>]');
    process.exit(2);
  }
  if (fail) { log(`\n❌ ${fail} falha(s).`); process.exit(1); }
  log(`\n✅ limpo.`); process.exit(0);
}
main();
