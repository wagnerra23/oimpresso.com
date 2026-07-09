#!/usr/bin/env node
// ref-integrity.mjs — sentinela ADVISORY de integridade referencial rota↔código
// (P10 da revisão de processo 2026-07-09). Node puro (fs), sem deps/DB/PHP.
//
// POR QUE EXISTE: os anti-padrões técnicos F3 do batch Financeiro rejeitado
// (prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) eram "o agente tem que
// LEMBRAR de Glob/Read". 3 viraram máquina (PHPStan: oimpresso.missingTenantScope
// T-AP-2, oimpresso.nopMutation T-AP-13, oimpresso.silentFallback AP-18). Estes 4
// o PHPStan NÃO vê — são integridade REFERENCIAL cruzando arquivos:
//
//   1. MIDDLEWARE FANTASMA  (T-AP-3): rota referencia alias de middleware que não
//      existe no registro (Kernel $routeMiddleware∪$middlewareGroups ∪ os
//      aliasMiddleware() dos ServiceProviders dos módulos).
//   2. COLISÃO DE ROTA       (T-AP-14): 2 rotas com mesmo NAME ou mesmo PATH+método.
//   3. LINK DE SIDEBAR MORTO (T-AP-15): route('nome') no DataController de um módulo
//      apontando pra um name que NÃO está definido (só quando o namespace é conhecido
//      — senão o universo de names é parcial e daria falso-positivo).
//   4. PAGE INERTIA AUSENTE  (M-AP-1): Inertia::render('Mod/Tela') sem o arquivo
//      resources/js/Pages/Mod/Tela.tsx (o "TSX que não compila no repo real").
//
// ADVISORY por lei (ADR 0314 — required = só Tier-0: dinheiro/PII/multi-tenant/fiscal).
// Integridade referencial é qualidade, não Tier-0. exit 0 sempre no modo default; o
// valor é o RELATÓRIO. --check (exit 1) fica reservado pra arming futuro por calendário.
//
// RESSALVAS ANTI-FALSO-POSITIVO (verificadas contra o repo real 2026-07-09):
//   - middleware válido = Kernel ∪ aliasMiddleware() dos Providers (senão log.delphi/
//     mcp.auth/is-wagner/whatsapp.*.signature/ads.api/tailscale-only = 10 FPs).
//   - Route::resource/apiResource expandidos nos names implícitos, honrando only()/except().
//   - resolução de Page = a MESMA do anchor-lint.mjs (resources/js/Pages/<name>.tsx,
//     só .tsx — confirmado em resources/js/app.tsx: import.meta.glob('./Pages/**/*.tsx')).
//   - refs dinâmicas puladas: route($var), Inertia::render($x), uri/name/prefix por variável.
//   - names: providers de módulo NÃO adicionam name-prefix (RouteServiceProvider padrão
//     nWidart só faz Route::middleware('web')->group) → name full = grupos in-file + local.
//   - paths: só api.php ganha prefixo 'api/' (mapApiRoutes usa ->prefix('api')).
//
// Uso (na raiz do repo):
//   node scripts/governance/ref-integrity.mjs            # relatório humano (exit 0)
//   node scripts/governance/ref-integrity.mjs --json     # JSON determinístico
//   node scripts/governance/ref-integrity.mjs --selftest # fixtures bite/release (exit 1 se falhar)
//   node scripts/governance/ref-integrity.mjs --check    # exit 1 se houver achado (arming futuro)
//
// Idioma/estilo: clone de anchor-lint.mjs / knowledge-drift.mjs (governança oimpresso).

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const SELFTEST = process.argv.includes('--selftest');
const CHECK = process.argv.includes('--check');

// ─────────────────────────────────────────────────────────────────────────────
// 1) SANITIZE — remove comentários e transforma strings em SENTINELAS livres-de-chave.
//    POR QUE: uris de rota têm chaves ('assinaturas/{assinatura}') que quebrariam a
//    contagem de { }. Removendo o conteúdo das strings pra sentinelas <id>
//    (sem ( ) [ ] { }), o balanceamento de delimitadores no "clean" fica confiável e
//    ainda recupero o valor literal via tabela lateral. Preserva newlines (line#).
const S_OPEN = '';
const S_CLOSE = '';
const SENT_RE = /(\d+)/g;

function sanitize(src) {
  const lits = [];
  let out = '';
  let i = 0;
  const n = src.length;
  while (i < n) {
    const c = src[i];
    const c2 = src[i + 1];
    // comentário de linha // e #
    if (c === '/' && c2 === '/') { while (i < n && src[i] !== '\n') i++; continue; }
    if (c === '#') { while (i < n && src[i] !== '\n') i++; continue; }
    // comentário de bloco /* */ (preserva newlines internos)
    if (c === '/' && c2 === '*') {
      i += 2;
      while (i < n && !(src[i] === '*' && src[i + 1] === '/')) { if (src[i] === '\n') out += '\n'; i++; }
      i += 2;
      continue;
    }
    // strings '...' e "..."
    if (c === "'" || c === '"') {
      const quote = c;
      i++;
      let val = '';
      let dynamic = false;
      let nl = '';
      while (i < n) {
        const d = src[i];
        if (d === '\\') { val += (src[i + 1] ?? ''); i += 2; continue; }
        if (d === quote) { i++; break; }
        if (d === '\n') nl += '\n';
        if (quote === '"' && (d === '$' || (d === '{' && src[i + 1] === '$'))) dynamic = true;
        val += d;
        i++;
      }
      const id = lits.length;
      lits.push({ value: val, dynamic });
      out += S_OPEN + id + S_CLOSE + nl; // preserva newlines de string multi-linha
      continue;
    }
    out += c;
    i++;
  }
  return { clean: out, lits };
}

function makeLineAt(clean) {
  const nl = [0];
  for (let i = 0; i < clean.length; i++) if (clean[i] === '\n') nl.push(i);
  return (idx) => {
    let lo = 0;
    let hi = nl.length;
    while (lo < hi) { const m = (lo + hi) >> 1; if (nl[m] <= idx) lo = m + 1; else hi = m; }
    return lo; // 1-indexed
  };
}

// balanceia o delimitador aberto em openIdx (só conta o par do próprio tipo; ok porque
// no "clean" não há delimitador dentro de string/comentário)
function matchDelim(s, openIdx) {
  const open = s[openIdx];
  const close = open === '(' ? ')' : open === '[' ? ']' : open === '{' ? '}' : null;
  if (!close) return -1;
  let depth = 0;
  for (let i = openIdx; i < s.length; i++) {
    if (s[i] === open) depth++;
    else if (s[i] === close) { depth--; if (depth === 0) return i; }
  }
  return -1;
}

// ; que termina o statement começado em startIdx (paren-depth 0)
function statementEnd(s, startIdx) {
  let depth = 0;
  for (let i = startIdx; i < s.length; i++) {
    const c = s[i];
    if (c === '(') depth++;
    else if (c === ')') depth--;
    else if (c === ';' && depth === 0) return i;
  }
  return s.length;
}

function sentVals(text, lits) {
  const out = [];
  for (const m of text.matchAll(SENT_RE)) out.push(lits[+m[1]]);
  return out;
}
// 1ª sentinela imediatamente após o fim de `re` (ex: ->prefix( <SENT> ); null se o
// argumento não for literal — variável/const → dinâmico, pula)
function sentAfter(text, re, lits) {
  const m = re.exec(text);
  if (!m) return null;
  const rest = text.slice(m.index + m[0].length);
  const s = /^\s*(\d+)/.exec(rest);
  return s ? lits[+s[1]] : null;
}
// conteúdo (string) dentro dos parênteses da chamada casada por `re` (ex: ->middleware( ... ))
function callArgText(text, re) {
  const m = re.exec(text);
  if (!m) return null;
  const open = text.indexOf('(', m.index + m[0].length - 1);
  if (open === -1) return null;
  const close = matchDelim(text, open);
  return close === -1 ? null : text.slice(open + 1, close);
}
// split top-level respeitando profundidade de ( ) [ ] { }
function splitTopLevel(s, sep) {
  const out = [];
  let depth = 0;
  let cur = '';
  for (const c of s) {
    if ('([{'.includes(c)) depth++;
    else if (')]}'.includes(c)) depth--;
    if (c === sep && depth === 0) { out.push(cur); cur = ''; continue; }
    cur += c;
  }
  out.push(cur);
  return out;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2) MIDDLEWARE VÁLIDO — Kernel (routeMiddleware ∪ middlewareGroups) ∪ aliasMiddleware() dos providers.
function kernelMiddleware(kernelSrc) {
  const { clean, lits } = sanitize(kernelSrc);
  const set = new Set();
  for (const prop of ['routeMiddleware', 'middlewareAliases', 'middlewareGroups']) {
    const re = new RegExp(`\\$${prop}\\s*=\\s*\\[`);
    const m = re.exec(clean);
    if (!m) continue;
    const open = clean.indexOf('[', m.index);
    const close = matchDelim(clean, open);
    if (close === -1) continue;
    const body = clean.slice(open, close + 1);
    // chaves de nível-topo: SENT => ...
    for (const pair of splitTopLevel(body.slice(1, -1), ',')) {
      const arrow = pair.indexOf('=>');
      if (arrow === -1) continue;
      const key = /(\d+)/.exec(pair.slice(0, arrow));
      if (key) set.add(lits[+key[1]].value);
    }
  }
  return set;
}
function providerAliases(providerSrc) {
  const { clean, lits } = sanitize(providerSrc);
  const set = new Set();
  // aliasMiddleware( 'literal' , ... ) — \s permite a forma multi-linha (Officeimpresso)
  for (const m of clean.matchAll(/aliasMiddleware\s*\(\s*(\d+)/g)) {
    const lit = lits[+m[1]];
    if (lit && !lit.dynamic && lit.value) set.add(lit.value);
  }
  // (b) LOOP DINÂMICO: Connector/Crm/Ponto/Jana registram via foreach sobre uma propriedade
  //     `$middleware = ['Modulo' => ['alias' => 'ClassName']]` + aliasMiddleware($name,$class). Sem
  //     isto, ContactSidebarMenu/CheckContactLogin viram falso-positivo. Colho as CHAVES-FOLHA (as que
  //     mapeiam pra STRING = classname; as que mapeiam pra array são nível de módulo → recurse).
  for (const prop of ['middleware', 'routeMiddleware', 'middlewareAliases']) {
    const re = new RegExp(`\\$${prop}\\s*=\\s*\\[`);
    const m = re.exec(clean);
    if (!m) continue;
    const open = clean.indexOf('[', m.index);
    const close = matchDelim(clean, open);
    if (close !== -1) collectLeafKeys(clean.slice(open, close + 1), lits, set);
  }
  return set;
}
// chaves-folha de um array PHP (K => V-string). K => V-array → recursão (nível de módulo).
function collectLeafKeys(arrText, lits, set) {
  for (const pair of splitTopLevel(arrText.slice(1, -1), ',')) {
    const arrow = pair.indexOf('=>');
    if (arrow === -1) continue;
    const key = sentVals(pair.slice(0, arrow), lits)[0];
    const rhs = pair.slice(arrow + 2).trim();
    if (rhs.startsWith(S_OPEN) || !rhs.startsWith('[')) {
      if (key && !key.dynamic && key.value) set.add(key.value);
    } else {
      const lb = rhs.indexOf('[');
      const rb = matchDelim(rhs, lb);
      if (rb !== -1) collectLeafKeys(rhs.slice(lb, rb + 1), lits, set);
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) PARSER DE ARQUIVO DE ROTA — devolve records resolvidos (name/path/middleware full).
const RES_ACTIONS = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
const API_RES_ACTIONS = ['index', 'store', 'show', 'update', 'destroy'];

function groupAttrsFromChain(chainText, lits) {
  // o 1º elo da cadeia é `Route::prefix(`/`Route::middleware(`/`Route::name(` (não `->`);
  // os demais são `->`. Casar os dois senão prefix/middleware do grupo se perdem.
  const prefix = sentAfter(chainText, /(?:Route\s*::|->)\s*prefix\s*\(/, lits);
  const name = sentAfter(chainText, /(?:Route\s*::|->)\s*name\s*\(/, lits);
  const mwArg = callArgText(chainText, /(?:Route\s*::|->)\s*middleware\s*\(/);
  const middleware = mwArg ? sentVals(mwArg, lits) : [];
  return {
    prefixParts: prefix && !prefix.dynamic ? [prefix.value] : [],
    nameParts: name && !name.dynamic ? [name.value] : [],
    middleware,
  };
}
function groupAttrsFromArray(arrText, lits) {
  const out = { prefixParts: [], nameParts: [], middleware: [] };
  for (const pair of splitTopLevel(arrText.slice(1, -1), ',')) {
    const arrow = pair.indexOf('=>');
    if (arrow === -1) continue;
    const keyM = /(\d+)/.exec(pair.slice(0, arrow));
    if (!keyM) continue;
    const key = lits[+keyM[1]].value;
    const rhs = pair.slice(arrow + 2);
    if (key === 'prefix') { const v = sentVals(rhs, lits)[0]; if (v && !v.dynamic) out.prefixParts = [v.value]; }
    else if (key === 'as' || key === 'name') { const v = sentVals(rhs, lits)[0]; if (v && !v.dynamic) out.nameParts = [v.value]; }
    else if (key === 'middleware') { out.middleware = sentVals(rhs, lits); }
  }
  return out;
}

// opções do 3º arg de Route::resource(base, ctrl, ['as'=>.., 'only'=>[..], 'except'=>[..], 'names'=>[action=>name]])
function resourceOptions(optText, lits) {
  const out = { as: '', only: null, except: null, names: null };
  const lb = optText.indexOf('[');
  if (lb === -1) return out;
  const rb = matchDelim(optText, lb);
  if (rb === -1) return out;
  for (const pair of splitTopLevel(optText.slice(lb + 1, rb), ',')) {
    const arrow = pair.indexOf('=>');
    if (arrow === -1) continue;
    const key = sentVals(pair.slice(0, arrow), lits)[0];
    if (!key || key.dynamic) continue;
    const rhs = pair.slice(arrow + 2);
    if (key.value === 'as') { const v = sentVals(rhs, lits)[0]; if (v && !v.dynamic) out.as = v.value; }
    else if (key.value === 'only') out.only = sentVals(rhs, lits).map((s) => s.value);
    else if (key.value === 'except') out.except = sentVals(rhs, lits).map((s) => s.value);
    else if (key.value === 'names') out.names = parseNamesMap(rhs, lits);
  }
  return out;
}
// mapa custom action=>name de Route::resource(...,['names'=>[...]]) ou ->names([...]).
// POR QUE: Jana/Ponto nomeiam cada rota do resource à mão (ex 'index'=>'jana.metas.index').
// Sem isto, geraria o name default e o custom viraria falso-positivo no #3.
function parseNamesMap(arrText, lits) {
  const lb = arrText.indexOf('[');
  if (lb === -1) return null;
  const rb = matchDelim(arrText, lb);
  if (rb === -1) return null;
  const map = {};
  for (const pair of splitTopLevel(arrText.slice(lb + 1, rb), ',')) {
    const arrow = pair.indexOf('=>');
    if (arrow === -1) continue;
    const k = sentVals(pair.slice(0, arrow), lits)[0];
    const v = sentVals(pair.slice(arrow + 2), lits)[0];
    if (k && !k.dynamic && v && !v.dynamic) map[k.value] = v.value;
  }
  return Object.keys(map).length ? map : null;
}

function findContexts(clean, lits) {
  const contexts = [];
  // grupos fluentes: Route:: <chain sem ;{}> ->group(
  for (const m of clean.matchAll(/\bRoute\s*::\s*[^;{}]*?->\s*group\s*\(/g)) {
    const chain = m[0];
    const bodyOpen = clean.indexOf('{', m.index + m[0].length - 1);
    if (bodyOpen === -1) continue;
    const bodyClose = matchDelim(clean, bodyOpen);
    if (bodyClose === -1) continue;
    contexts.push({ open: bodyOpen, close: bodyClose, ...groupAttrsFromChain(chain, lits) });
  }
  // grupos array: Route::group([ ... ], function(){ ... })
  for (const m of clean.matchAll(/\bRoute\s*::\s*group\s*\(\s*\[/g)) {
    const arrOpen = clean.indexOf('[', m.index);
    const arrClose = matchDelim(clean, arrOpen);
    if (arrClose === -1) continue;
    const bodyOpen = clean.indexOf('{', arrClose);
    if (bodyOpen === -1) continue;
    const bodyClose = matchDelim(clean, bodyOpen);
    if (bodyClose === -1) continue;
    contexts.push({ open: bodyOpen, close: bodyClose, ...groupAttrsFromArray(clean.slice(arrOpen, arrClose + 1), lits) });
  }
  return contexts;
}

const VERB_RE = /\bRoute\s*::\s*(get|post|put|patch|delete|options|any|match|resource|apiResource|redirect|permanentRedirect|view|fallback)\s*\(/g;

function joinPath(parts) {
  return parts
    .map((p) => String(p).replace(/^\/+|\/+$/g, ''))
    .filter((p) => p !== '')
    .join('/');
}

// parseia UM arquivo de rota → { records:[...] , unresolved:{path:n,name:n} }
function parseRouteFile({ path, content, kind }) {
  const { clean, lits } = sanitize(content);
  const lineAt = makeLineAt(clean);
  const contexts = findContexts(clean, lits);
  const providerPrefix = kind === 'api' ? 'api' : '';
  const records = [];
  const unresolved = { pathDyn: 0, nameDyn: 0 };

  for (const m of clean.matchAll(VERB_RE)) {
    const verb = m[1];
    const start = m.index;
    const end = statementEnd(clean, start);
    const stmt = clean.slice(start, end);
    const line = lineAt(start);
    const enclosing = contexts
      .filter((c) => c.open < start && start < c.close)
      .sort((a, b) => a.open - b.open);
    const groupNames = enclosing.flatMap((c) => c.nameParts).join('');
    const groupPrefix = enclosing.flatMap((c) => c.prefixParts);
    const groupMw = enclosing.flatMap((c) => c.middleware);

    // middleware local do statement (só `->middleware(` — o stmt começa no verbo, nunca
    // contém `Route::middleware(` do grupo)
    const localMwArg = callArgText(stmt, /->\s*middleware\s*\(/);
    const localMw = localMwArg ? sentVals(localMwArg, lits) : [];
    const middleware = [...groupMw, ...localMw];

    // name local (->name('x'))
    const localName = sentAfter(stmt, /->\s*name\s*\(/, lits);

    // args (sentinelas de nível-statement, na ordem)
    const args = sentVals(stmt.slice(stmt.indexOf('(')), lits);

    const rec = { file: path, line, verb, methods: [], path: null, name: null, middleware, isResource: false };

    if (verb === 'resource' || verb === 'apiResource') {
      rec.isResource = true;
      // args do call resource(base, ctrl, [opts]) — 3º arg é o array de opções do Laravel.
      // IGNORAR o 3º arg foi FP sistêmico: Route::resource('bookings', C, ['as'=>'contact']) gera
      // 'contact.bookings.*', NÃO 'bookings.*' (colisão FALSA com o resource core). Idem asset./connector.
      const callArgsText = callArgText(stmt, new RegExp(`::\\s*${verb}\\s*\\(`));
      const callArgs = callArgsText ? splitTopLevel(callArgsText, ',') : [];
      const base = sentVals(callArgs[0] ?? '', lits)[0];
      if (base && !base.dynamic) {
        const nameBase = base.value.replace(/^\/+|\/+$/g, '').replace(/\//g, '.');
        const opt = resourceOptions(callArgs[2] ?? '', lits); // {as, only, except, names}
        // fluent ->only()/->except()/->names() (co-existe com o 3º-arg; fluent tem precedência se presente)
        const fOnly = callArgText(stmt, /->\s*only\s*\(/);
        const fExcept = callArgText(stmt, /->\s*except\s*\(/);
        const fNamesArg = callArgText(stmt, /->\s*names\s*\(/);
        const only = fOnly ? sentVals(fOnly, lits).map((s) => s.value) : opt.only;
        const except = fExcept ? sentVals(fExcept, lits).map((s) => s.value) : opt.except;
        const customNames = (fNamesArg ? parseNamesMap(fNamesArg, lits) : null) || opt.names || {};
        let actions = verb === 'apiResource' ? API_RES_ACTIONS : RES_ACTIONS;
        if (only) actions = actions.filter((a) => only.includes(a));
        if (except) actions = actions.filter((a) => !except.includes(a));
        const namePrefix = groupNames + (opt.as ? opt.as + '.' : '');
        rec.resourceNames = actions.map((a) => customNames[a] || (namePrefix + nameBase + '.' + a));
      }
      // paths de resource: PULADOS (params, alta chance de FP em colisão)
      records.push(rec);
      continue;
    }

    // métodos
    if (verb === 'any') rec.methods = ['ANY'];
    else if (verb === 'match') {
      // 1º arg é o array de métodos (sentinelas), uri é o 2º arg
      const methArg = callArgText(stmt, /->\s*match\s*\(|::\s*match\s*\(/) ?? '';
      const firstArr = splitTopLevel(methArg, ',')[0] ?? '';
      rec.methods = sentVals(firstArr, lits).map((s) => s.value.toUpperCase());
    } else if (['redirect', 'permanentRedirect', 'view'].includes(verb)) rec.methods = ['GET'];
    else if (verb === 'fallback') rec.methods = [];
    else rec.methods = [verb.toUpperCase()];

    // uri
    let uri = null;
    if (verb === 'match') uri = args[1]; // após o array de métodos
    else if (verb === 'fallback') uri = null;
    else uri = args[0];

    if (uri && !uri.dynamic) rec.path = joinPath([providerPrefix, ...groupPrefix, uri.value]);
    else if (verb !== 'fallback' && (!uri || uri.dynamic)) unresolved.pathDyn++;

    if (localName && !localName.dynamic) rec.name = groupNames + localName.value;
    else if (localName && localName.dynamic) unresolved.nameDyn++;

    records.push(rec);
  }
  return { records, unresolved };
}

// ─────────────────────────────────────────────────────────────────────────────
// 4) CHECKS (puros — recebem dados parseados + oráculos; selftest injeta fixtures)

// #1 middleware fantasma
function checkPhantomMiddleware(routeFiles, validSet) {
  const findings = [];
  for (const rf of routeFiles) {
    for (const rec of parseRouteFile(rf).records) {
      for (const tok of rec.middleware) {
        if (!tok || tok.dynamic) continue; // route($var) etc
        const raw = tok.value;
        if (raw.includes('\\')) continue; // FQCN string — não é alias
        const base = raw.split(':')[0].trim();
        if (!base || /[^A-Za-z0-9_.-]/.test(base)) continue; // não-bareword → pula
        if (!validSet.has(base)) findings.push({ file: rec.file, line: rec.line, middleware: raw });
      }
    }
  }
  return findings;
}

// #2 colisão (name e path+método) — devolve todos os records resolvidos + colisões
function collectRoutes(routeFiles) {
  const all = [];
  const unresolved = { pathDyn: 0, nameDyn: 0 };
  for (const rf of routeFiles) {
    const r = parseRouteFile(rf);
    unresolved.pathDyn += r.unresolved.pathDyn;
    unresolved.nameDyn += r.unresolved.nameDyn;
    all.push(...r.records);
  }
  return { all, unresolved };
}
function checkCollisions(records) {
  // NAME — inclui resourceNames; colisão = mesmo name full em ≥2 sites distintos
  const byName = new Map();
  for (const rec of records) {
    const names = rec.isResource ? (rec.resourceNames || []) : (rec.name ? [rec.name] : []);
    for (const nm of names) {
      if (!byName.has(nm)) byName.set(nm, []);
      byName.get(nm).push({ file: rec.file, line: rec.line });
    }
  }
  const nameCollisions = [];
  for (const [nm, sites] of byName) {
    const uniq = dedupeSites(sites);
    if (uniq.length > 1) nameCollisions.push({ name: nm, sites: uniq });
  }
  // PATH — só rotas não-resource com path resolvido; colisão = mesmo path E método sobreposto
  const byPath = new Map();
  for (const rec of records) {
    if (rec.isResource || !rec.path) continue;
    if (!byPath.has(rec.path)) byPath.set(rec.path, []);
    byPath.get(rec.path).push({ file: rec.file, line: rec.line, methods: rec.methods });
  }
  const pathCollisions = [];
  for (const [p, sites] of byPath) {
    if (sites.length < 2) continue;
    // pares que compartilham método (ou ANY) e são de sites distintos
    const clashing = [];
    for (let i = 0; i < sites.length; i++) {
      for (let j = i + 1; j < sites.length; j++) {
        if (sameSite(sites[i], sites[j])) continue;
        if (methodsOverlap(sites[i].methods, sites[j].methods)) { clashing.push(sites[i], sites[j]); }
      }
    }
    if (clashing.length) pathCollisions.push({ path: p, sites: dedupeSites(clashing) });
  }
  return { nameCollisions, pathCollisions };
}
function methodsOverlap(a, b) {
  if (a.includes('ANY') || b.includes('ANY')) return true;
  return a.some((m) => b.includes(m));
}
function sameSite(a, b) { return a.file === b.file && a.line === b.line; }
function dedupeSites(sites) {
  const seen = new Set();
  const out = [];
  for (const s of sites) { const k = `${s.file}:${s.line}`; if (!seen.has(k)) { seen.add(k); out.push(s); } }
  return out;
}

// #3 sidebar → route('nome') morto (só em namespace conhecido)
function collectDefinedNames(records) {
  const names = new Set();
  for (const rec of records) {
    const rn = rec.isResource ? (rec.resourceNames || []) : (rec.name ? [rec.name] : []);
    for (const nm of rn) names.add(nm);
  }
  return names;
}
function checkSidebarRefs(refs, definedNames) {
  const namespaces = new Set([...definedNames].map((n) => n.split('.')[0]));
  const findings = [];
  for (const ref of refs) {
    if (ref.dynamic) continue;
    const nm = ref.name;
    if (definedNames.has(nm)) continue;
    // só acusa se o namespace (1º segmento) é conhecido → universo de names completo p/ ele
    if (nm.includes('.') && namespaces.has(nm.split('.')[0])) findings.push({ file: ref.file, line: ref.line, name: nm });
  }
  return findings;
}

// #4 Inertia::render('Mod/Tela') sem .tsx — pageExists é o oráculo (reuso da regra do anchor-lint)
function checkMissingPages(renders, pageExists) {
  const findings = [];
  for (const r of renders) {
    if (r.dynamic) continue;
    if (!pageExists(r.name)) findings.push({ file: r.file, line: r.line, page: r.name });
  }
  return findings;
}

// coletores de renders / refs a partir de conteúdo PHP sanitizado
function collectRenders(phpFile) {
  const { clean, lits } = sanitize(phpFile.content);
  const lineAt = makeLineAt(clean);
  const out = [];
  for (const m of clean.matchAll(/\bInertia\s*::\s*render\s*\(\s*(\d+)/g)) {
    const lit = lits[+m[1]];
    out.push({ file: phpFile.path, line: lineAt(m.index), name: lit.value, dynamic: lit.dynamic || /[{$]/.test(lit.value) });
  }
  // Inertia::render($x  → dinâmico (registrado só pra contagem)
  for (const m of clean.matchAll(/\bInertia\s*::\s*render\s*\(\s*\$/g)) {
    out.push({ file: phpFile.path, line: lineAt(m.index), name: null, dynamic: true });
  }
  return out;
}
function collectRefs(phpFile) {
  const { clean, lits } = sanitize(phpFile.content);
  const lineAt = makeLineAt(clean);
  const out = [];
  for (const m of clean.matchAll(/\broute\s*\(\s*(\d+)/g)) {
    const lit = lits[+m[1]];
    out.push({ file: phpFile.path, line: lineAt(m.index), name: lit.value, dynamic: lit.dynamic || /[{$]/.test(lit.value) });
  }
  return out;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5) MODO REAL — descobre arquivos no disco, roda checks, relata (advisory exit 0)
function walkPhp(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) walkPhp(p, acc);
    else if (e.name.endsWith('.php')) acc.push(p);
  }
  return acc;
}
function listModules() {
  const dir = join(ROOT, 'Modules');
  if (!existsSync(dir)) return [];
  return readdirSync(dir, { withFileTypes: true }).filter((e) => e.isDirectory()).map((e) => e.name);
}
function readFileSafe(p) { try { return readFileSync(p, 'utf8'); } catch { return null; } }

function scanRepo() {
  const mods = listModules();
  // arquivos de rota: raiz + convenção nova nWidart (Routes/web|api.php) + convenção LEGADA
  // (Http/routes.php — Governance/Jana/KB/Ponto/ProjectMgmt/SRS/TeamMcp). Sem a legada, o
  // universo de names fica incompleto → kb.index (em Http/routes.php) viraria falso-positivo no #3.
  const routeFilePaths = [
    join(ROOT, 'routes', 'web.php'),
    join(ROOT, 'routes', 'api.php'),
    ...mods.flatMap((m) => [
      join(ROOT, 'Modules', m, 'Routes', 'web.php'),
      join(ROOT, 'Modules', m, 'Routes', 'api.php'),
      join(ROOT, 'Modules', m, 'Http', 'routes.php'), // legado (kind web — loadRoutesFrom sem prefixo)
    ]),
  ].filter(existsSync);
  const routeFiles = routeFilePaths.map((p) => ({
    path: rel(p), content: readFileSync(p, 'utf8'), kind: /api\.php$/.test(p) ? 'api' : 'web',
  }));

  // middleware válido: Kernel ∪ providers
  const validSet = new Set();
  const kernelSrc = readFileSafe(join(ROOT, 'app', 'Http', 'Kernel.php'));
  if (kernelSrc) for (const k of kernelMiddleware(kernelSrc)) validSet.add(k);
  const providerPaths = [
    ...walkPhp(join(ROOT, 'app', 'Providers')),
    ...mods.flatMap((m) => walkPhp(join(ROOT, 'Modules', m, 'Providers'))),
  ];
  for (const p of providerPaths) {
    const src = readFileSafe(p);
    if (src) for (const a of providerAliases(src)) validSet.add(a);
  }

  // renders (Inertia::render) — controllers app/ + Modules/*/Http
  const renderPaths = [
    ...walkPhp(join(ROOT, 'app', 'Http')),
    ...mods.flatMap((m) => walkPhp(join(ROOT, 'Modules', m, 'Http'))),
  ];
  const renders = [];
  for (const p of renderPaths) {
    const src = readFileSafe(p);
    if (src && src.includes('Inertia')) renders.push(...collectRenders({ path: rel(p), content: src }));
  }

  // refs de sidebar: route('name') nos DataController de cada módulo (T-AP-15)
  const dataCtrls = mods
    .map((m) => join(ROOT, 'Modules', m, 'Http', 'Controllers', 'DataController.php'))
    .filter(existsSync);
  const refs = [];
  for (const p of dataCtrls) refs.push(...collectRefs({ path: rel(p), content: readFileSync(p, 'utf8') }));

  const pageExists = (name) => existsSync(join(ROOT, 'resources', 'js', 'Pages', `${name}.tsx`));

  const { all, unresolved } = collectRoutes(routeFiles);
  const definedNames = collectDefinedNames(all);

  return {
    phantom: checkPhantomMiddleware(routeFiles, validSet),
    collisions: checkCollisions(all),
    sidebar: checkSidebarRefs(refs, definedNames),
    pages: checkMissingPages(renders, pageExists),
    stats: {
      route_files: routeFiles.length,
      routes_resolved: all.length,
      valid_middleware: validSet.size,
      renders_scanned: renders.length,
      sidebar_refs: refs.length,
      defined_names: definedNames.size,
      unresolved,
    },
  };
}
function rel(p) { return p.replace(ROOT, '').replace(/^[\\/]/, '').replace(/\\/g, '/'); }

// ─────────────────────────────────────────────────────────────────────────────
// 6) RELATÓRIO
function report(r) {
  if (JSON_OUT) {
    const payload = {
      _meta: {
        gate: 'ref-integrity (advisory · ADR 0314 — integridade referencial rota↔código NÃO é Tier-0)',
        contrato: 'prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md — T-AP-3 (middleware fantasma) · T-AP-14 (colisão) · T-AP-15 (sidebar) · M-AP-1 (page ausente)',
        generator: 'scripts/governance/ref-integrity.mjs',
        determinismo: 'sem timestamp/sha — re-run sem mudança = diff vazio',
      },
      summary: {
        phantom_middleware: r.phantom.length,
        name_collisions: r.collisions.nameCollisions.length,
        path_collisions: r.collisions.pathCollisions.length,
        sidebar_dead_refs: r.sidebar.length,
        missing_pages: r.pages.length,
        stats: r.stats,
      },
      findings: r,
    };
    process.stdout.write(JSON.stringify(payload, null, 2) + '\n');
    return;
  }
  const c = r.collisions;
  console.log('\n  REF-INTEGRITY — integridade referencial rota↔código (advisory · ADR 0314)');
  console.log(`  contrato: LICOES_F3_FINANCEIRO_REJEITADO (T-AP-3 · T-AP-14 · T-AP-15 · M-AP-1)`);
  console.log('  ' + '─'.repeat(78));
  console.log(`  arquivos de rota: ${r.stats.route_files} · rotas resolvidas: ${r.stats.routes_resolved} · middleware válido: ${r.stats.valid_middleware}`);
  console.log(`  renders escaneados: ${r.stats.renders_scanned} · refs de sidebar: ${r.stats.sidebar_refs} · names definidos: ${r.stats.defined_names}`);
  console.log(`  (pulados por dinâmico: ${r.stats.unresolved.pathDyn} path · ${r.stats.unresolved.nameDyn} name)`);
  console.log('  ' + '─'.repeat(78));

  section('1) MIDDLEWARE FANTASMA (T-AP-3)', r.phantom, (f) => `${f.file}:${f.line} → middleware '${f.middleware}' não registrado`);
  section('2a) COLISÃO DE NAME (T-AP-14)', c.nameCollisions, (f) => `name '${f.name}' em ${f.sites.length} sites: ${f.sites.map((s) => `${s.file}:${s.line}`).join(' · ')}`);
  section('2b) COLISÃO DE PATH+MÉTODO (T-AP-14)', c.pathCollisions, (f) => `path '${f.path}' em ${f.sites.map((s) => `${s.file}:${s.line}[${s.methods.join(',')}]`).join(' · ')}`);
  section('3) SIDEBAR route() MORTO (T-AP-15)', r.sidebar, (f) => `${f.file}:${f.line} → route('${f.name}') não definido`);
  section('4) PAGE INERTIA AUSENTE (M-AP-1)', r.pages, (f) => `${f.file}:${f.line} → Inertia::render('${f.page}') sem resources/js/Pages/${f.page}.tsx`);

  const total = r.phantom.length + c.nameCollisions.length + c.pathCollisions.length + r.sidebar.length + r.pages.length;
  console.log('  ' + '─'.repeat(78));
  console.log(`  TOTAL: ${total} achado(s). Advisory — o valor é o relatório (não bloqueia PR · exit 0).\n`);
}
function section(title, items, fmt) {
  console.log(`\n  ${items.length ? '🔴' : '🟢'} ${title}: ${items.length}`);
  for (const it of items) console.log(`       • ${fmt(it)}`);
}

// ─────────────────────────────────────────────────────────────────────────────
// 7) SELFTEST — hermético (fixtures inline · bite/release). A asserção "bad" é ancorada
//    no CONTRATO citado (LICOES_F3_FINANCEIRO_REJEITADO nomeia T-AP-3 'tenant' e T-AP-14),
//    NUNCA no output da própria implementação.
function selftest() {
  const results = [];
  const expect = (nome, ok, detalhe) => { results.push({ nome, ok, detalhe }); };

  // Kernel-fixture + provider-fixture pro conjunto válido
  const kernelFx = `<?php class Kernel { protected $middlewareGroups = ['web'=>[],'api'=>[]];
    protected $routeMiddleware = ['auth'=>Auth::class,'language'=>Lang::class]; }`;
  const providerFx = `<?php class P {
    protected $middleware = ['Crm' => ['ContactSidebarMenu' => 'ContactSidebarMenu', 'CheckContactLogin' => 'CheckContactLogin']];
    public function boot(){ $router->aliasMiddleware('mcp.auth', Foo::class); } }`;
  const validSet = new Set([...kernelMiddleware(kernelFx), ...providerAliases(providerFx)]);

  // FIXTURE BITE (middleware fantasma 'tenant' — o literal que o T-AP-3 nomeia)
  const fxPhantom = { path: 'fx/web.php', kind: 'web', content: `<?php
    Route::middleware(['web','auth','tenant'])->prefix('x')->group(function () {
      Route::get('/a', [C::class,'a'])->name('x.a');
    });` };
  const phantom = checkPhantomMiddleware([fxPhantom], validSet);
  // CONTRATO T-AP-3: 'tenant' NÃO existe (canon é ['web','auth',...]) → tem que acusar exatamente 'tenant'.
  expect("BITE middleware-fantasma 'tenant' acusado (T-AP-3)",
    phantom.length === 1 && phantom[0].middleware === 'tenant', JSON.stringify(phantom));
  // e 'mcp.auth' (alias de provider) NÃO pode ser acusado
  const fxProvOk = { path: 'fx/w2.php', kind: 'web', content: `<?php
    Route::middleware(['web','mcp.auth'])->group(function(){ Route::get('/m',[C::class,'m'])->name('m'); });` };
  expect("RELEASE alias de provider 'mcp.auth' NÃO acusado",
    checkPhantomMiddleware([fxProvOk], validSet).length === 0, '');
  // RELEASE alias de LOOP DINÂMICO ($middleware['Crm']['ContactSidebarMenu']) — o FP de 24 itens
  const fxLoop = { path: 'fx/crm.php', kind: 'web', content: `<?php
    Route::middleware(['web','ContactSidebarMenu','CheckContactLogin'])->group(function(){ Route::get('/c',[C::class,'i'])->name('c'); });` };
  expect("RELEASE alias de loop dinâmico 'ContactSidebarMenu' NÃO acusado",
    checkPhantomMiddleware([fxLoop], validSet).length === 0, JSON.stringify(checkPhantomMiddleware([fxLoop], validSet)));

  // FIXTURE BITE (colisão) — CONTRATO T-AP-14: 2 rotas com o mesmo name = colisão
  const fxNameCol = { path: 'fx/col.php', kind: 'web', content: `<?php
    Route::get('/unificado', [A::class,'i'])->name('financeiro.unificado');
    Route::get('/unificado-b', [B::class,'i'])->name('financeiro.unificado');` };
  const cNames = checkCollisions(collectRoutes([fxNameCol]).all).nameCollisions;
  expect('BITE colisão de name acusada (T-AP-14)',
    cNames.length === 1 && cNames[0].name === 'financeiro.unificado' && cNames[0].sites.length === 2, JSON.stringify(cNames));
  // colisão de path+método (mesmo path, mesmo GET, sites distintos)
  const fxPathCol = { path: 'fx/col2.php', kind: 'web', content: `<?php
    Route::get('/dup', [A::class,'i']);
    Route::get('/dup', [B::class,'i']);` };
  const cPaths = checkCollisions(collectRoutes([fxPathCol]).all).pathCollisions;
  expect('BITE colisão de path+GET acusada (T-AP-14)',
    cPaths.length === 1 && cPaths[0].path === 'dup', JSON.stringify(cPaths));
  // RELEASE: mesmo path com métodos diferentes (GET vs POST) NÃO é colisão
  const fxPathOk = { path: 'fx/ok2.php', kind: 'web', content: `<?php
    Route::get('/form', [A::class,'show']);
    Route::post('/form', [A::class,'store']);` };
  expect('RELEASE GET+POST no mesmo path NÃO é colisão',
    checkCollisions(collectRoutes([fxPathOk]).all).pathCollisions.length === 0, '');

  // FIXTURE BITE (resource expandido honrando except) + prefix/name de grupo
  // (fixture SEM rota show explícita, senão ela re-adicionaria o name e mascararia o except)
  const fxRes = { path: 'fx/res.php', kind: 'web', content: `<?php
    Route::prefix('financeiro')->name('financeiro.')->group(function () {
      Route::resource('sells', SellController::class)->except(['show']);
    });` };
  const resNames = collectDefinedNames(collectRoutes([fxRes]).all);
  expect('resource expande names honrando except (sem financeiro.sells.show)',
    resNames.has('financeiro.sells.index') && resNames.has('financeiro.sells.update') && !resNames.has('financeiro.sells.show'),
    [...resNames].join(','));
  // RELEASE resource 3º-arg ['as'=>x] — o FP de 16 colisões (bookings/settings/client)
  const fxResAs = { path: 'fx/resas.php', kind: 'web', content: `<?php
    Route::resource('bookings', 'X\\ContactBookingController', ['as' => 'contact']);
    Route::resource('bookings', 'X\\CoreBookingController');` };
  const nAs = collectDefinedNames(collectRoutes([fxResAs]).all);
  const colAs = checkCollisions(collectRoutes([fxResAs]).all).nameCollisions;
  expect("resource 3º-arg ['as'=>'contact'] gera contact.bookings.* e NÃO colide com bookings.* (T-AP-14)",
    nAs.has('contact.bookings.index') && nAs.has('bookings.index') && colAs.length === 0, JSON.stringify(colAs));
  // parser: array-group legado (Http/routes.php estilo KB) — prefix + string-controller
  const fxLegacy = { path: 'fx/legacy.php', kind: 'web', content: `<?php
    Route::group(['middleware' => ['web','auth'], 'prefix' => 'kb'], function () {
      Route::get('/', 'KbController@index')->name('kb.index');
    });` };
  const legacy = collectRoutes([fxLegacy]).all;
  expect('parser: array-group legado (prefix + string-controller) resolve name/path',
    legacy.length === 1 && legacy[0].name === 'kb.index' && legacy[0].path === 'kb', JSON.stringify(legacy));
  // RELEASE resource com 'names' custom (3º-arg Jana E fluent ->names() Ponto) — o FP de 3 sidebar
  const fxNames = { path: 'fx/names.php', kind: 'web', content: `<?php
    Route::resource('/metas', 'MetasController', ['names' => ['index' => 'jana.metas.index', 'store' => 'jana.metas.store']]);
    Route::resource('/escalas', 'EscalaController')->names(['index' => 'ponto.escalas.index']);` };
  const nCustom = collectDefinedNames(collectRoutes([fxNames]).all);
  expect("resource 'names' custom (3º-arg + fluent ->names) define os names à mão",
    nCustom.has('jana.metas.index') && nCustom.has('jana.metas.store') && nCustom.has('ponto.escalas.index'), [...nCustom].join(','));

  // FIXTURE #3 sidebar — namespace conhecido acusa; desconhecido (core) NÃO (FP-safe)
  const defined = new Set(['financeiro.unificado', 'financeiro.fluxo', 'ponto.dashboard']);
  const refs = [
    { file: 'fx/DataController.php', line: 10, name: 'financeiro.fantasma', dynamic: false }, // ns conhecido, name morto → acusa
    { file: 'fx/DataController.php', line: 11, name: 'financeiro.fluxo', dynamic: false }, // existe → ok
    { file: 'fx/DataController.php', line: 12, name: 'login', dynamic: false }, // sem '.', ns core → pula
    { file: 'fx/DataController.php', line: 13, name: 'core.qualquer', dynamic: false }, // ns desconhecido → pula (FP-safe)
    { file: 'fx/DataController.php', line: 14, name: 'x', dynamic: true }, // dinâmico → pula
  ];
  const side = checkSidebarRefs(refs, defined);
  expect('BITE sidebar route() morto em ns conhecido acusado (T-AP-15)',
    side.length === 1 && side[0].name === 'financeiro.fantasma', JSON.stringify(side));
  expect('RELEASE ref a ns desconhecido/core NÃO acusado (FP-safe)',
    !side.some((f) => f.name === 'core.qualquer' || f.name === 'login'), '');

  // FIXTURE #4 page ausente — oráculo pageExists injetado (mesma regra do anchor-lint)
  const renders = [
    { file: 'fx/Ctrl.php', line: 5, name: 'Financeiro/Unificado/Index', dynamic: false },
    { file: 'fx/Ctrl.php', line: 6, name: 'Financeiro/Fantasma', dynamic: false },
    { file: 'fx/Ctrl.php', line: 7, name: null, dynamic: true },
  ];
  const pageExists = (n) => n === 'Financeiro/Unificado/Index';
  const miss = checkMissingPages(renders, pageExists);
  expect('BITE Inertia::render sem .tsx acusado (M-AP-1)',
    miss.length === 1 && miss[0].page === 'Financeiro/Fantasma', JSON.stringify(miss));
  expect('RELEASE render dinâmico NÃO acusado',
    !miss.some((f) => f.page === null), '');

  // CASO-BOM completo (release) — nada acusado
  const fxGood = { path: 'fx/good.php', kind: 'web', content: `<?php
    Route::middleware(['web','auth','language'])->prefix('ok')->name('ok.')->group(function () {
      Route::get('/home', [C::class,'home'])->name('home');
      Route::post('/save', [C::class,'save'])->name('save');
    });` };
  const goodPhantom = checkPhantomMiddleware([fxGood], validSet);
  const goodCol = checkCollisions(collectRoutes([fxGood]).all);
  expect('RELEASE caso-bom → 0 fantasma + 0 colisão',
    goodPhantom.length === 0 && goodCol.nameCollisions.length === 0 && goodCol.pathCollisions.length === 0, '');

  // parse de string com {param} não quebra o balanceamento de chaves
  const fxParam = { path: 'fx/param.php', kind: 'web', content: `<?php
    Route::prefix('financeiro')->name('financeiro.')->group(function () {
      Route::patch('assinaturas/{assinatura}', [A::class,'u'])->name('assinaturas.atualizar')->where('assinatura','[0-9]+');
    });` };
  const pr = collectRoutes([fxParam]).all;
  expect('parser: {param} na uri não quebra grupo (name/path resolvem)',
    pr.length === 1 && pr[0].name === 'financeiro.assinaturas.atualizar' && pr[0].path === 'financeiro/assinaturas/{assinatura}', JSON.stringify(pr));

  let falhou = false;
  for (const r of results) { if (!r.ok) falhou = true; console.log(`  [${r.ok ? 'PASS' : 'FAIL'}] ${r.nome}${r.ok ? '' : ' → ' + r.detalhe}`); }
  if (falhou) { console.error('\nSELFTEST FALHOU — a catraca não morde/solta como o contrato exige.'); process.exit(1); }
  console.log(`\nSELFTEST OK — ${results.length} asserções · bite (T-AP-3/14/15/M-AP-1) morde, release solta. Ancorado no contrato, não no output.`);
  process.exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
if (SELFTEST) {
  selftest();
} else {
  const r = scanRepo();
  report(r);
  const total = r.phantom.length + r.collisions.nameCollisions.length + r.collisions.pathCollisions.length + r.sidebar.length + r.pages.length;
  process.exit(CHECK && total > 0 ? 1 : 0);
}
