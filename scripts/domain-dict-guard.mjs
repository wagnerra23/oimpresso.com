#!/usr/bin/env node
// scripts/domain-dict-guard.mjs — Gate G-4 (dicionário de domínio) da Governança executável (ADR 0264).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// A alucinação da "locação de caçamba" (ADR 0265) atravessou TODOS os gates porque
// nenhum cobria COERÊNCIA DE DOMÍNIO: um enum podia carregar `locacao` sem nada acusar.
// Este guard faz da fonte-única de domínio (memory/dominio/<modulo>.md) uma LEI: o
// vocabulário declarado lá tem que bater com os enum() reais das migrations do módulo.
//
//   Fonte única por módulo: memory/dominio/<modulo>.md, bloco ```json com { module, enums }.
//   enums = { "tabela.coluna": [valores canônicos], ... }.
//   Onda Q3 (domínios core): + migrations_paths (dirs custom) · tables_scope (tabelas
//   reivindicadas) · code_paths (Salto #3 estreito) · vocab (vocabulário de coluna
//   VARCHAR sem constraint física — só col-index do Salto #3, sem comparação enum⇔schema).
//
// O guard deriva o ESTADO ATUAL de cada enum percorrendo as migrations do módulo
// (last-write-wins por tabela.coluna, só a região up()) e compara com o dicionário.
//
// ── SALTO #3: domínio ALÉM do enum (cobertura de código) ────────────────────────────
// Erradicar o enum NÃO basta: a alucinação sobrevive como CÓDIGO MORTO que ramifica num
// valor que não existe mais (ex.: `if ($so->order_type === 'locacao')` depois do enum já
// ter virado {manutencao, mecanica}). O enum-check não pega isso. Então, NOS MÓDULOS COM
// DICIONÁRIO, o guard também varre o código de aplicação e exige que todo valor-literal
// usado em POSIÇÃO DE DOMÍNIO contra uma coluna governada seja canônico:
//   · query builder:        ->where('col', 'v') · ->where('col','=','v') · ->whereIn('col', ['v',...])
//   · comparação de campo:  $x->col === 'v' / !== / == / != / <>  (e a forma invertida)
//   · regra de validação:   'col' => '...|in:v1,v2'  (Laravel `in:`)
// Escopo CUIDADOSO (só módulo com dict; só coluna declarada; só literal — variável/CONST
// não conta). EXCLUI Database/ (migration+seeder+factory: data-fix legitimamente cita o
// valor velho) e Tests/ (fixture pode afirmar que o valor é REJEITADO). Ligação valor⇔coluna
// é pelo NOME da coluna (sufixo após o último ponto) — colunas genéricas (`tipo`/`status`)
// podem super-casar; o dono do dicionário refina declarando a coluna certa.
//
// ── SALTO #4: termos PROIBIDOS user-facing (ADR 0265 — trava de regressão) ──────────
// Erradicar enum + código não basta: a alucinação volta como STRING DE UI ("Iniciar
// locação", card "Locações ativas", label de seeder/migration nova). O dicionário pode
// declarar `forbidden_ui_terms` (termos casados case-insensitive E accent-insensitive:
// "locação"≡"locacao", "Caçamba"≡"cacamba") + `forbidden_ui_paths` (raízes onde o termo
// é PROIBIDO: Pages do módulo, Seeders/Migrations com label). Comentários (`//`,`/* */`,
// `#`) são CEGADOS antes do scan — explicar a erradicação em comentário é legítimo;
// mostrar pro usuário não. Chave POR OCORRÊNCIA (índice estável): baseline fotografa os
// residuais Tier 0 (keys FSM `cacamba_locacao` etc.); QUALQUER ocorrência NOVA = CI
// vermelho (ratchet count-based por arquivo+termo).
//
// VIOLAÇÕES (cada uma uma chave estável pro ratchet):
//   dominio:undeclared-value:<mod>:<tab.col>:<v>     valor no schema (enum) mas não no dicionário
//   dominio:stale-dict-value:<mod>:<tab.col>:<v>     valor no dicionário mas não no schema (enum)
//   dominio:undeclared-column:<mod>:<tab.col>        coluna enum no schema do módulo, fora do dicionário
//   dominio:missing-column-in-schema:<mod>:<tab.col> coluna no dicionário sem enum correspondente
//   dominio:undeclared-code-value:<mod>:<col>:<v>    valor-literal usado no CÓDIGO contra coluna governada, fora do dicionário (Salto #3)
//   dominio:forbidden-ui-term:<mod>:<file>:<termo>:<i>  i-ésima ocorrência de termo proibido user-facing (Salto #4)
//   dominio:module-no-dict:<mod>                      módulo TEM enum em migration mas NÃO tem dicionário
//
// =====================================================================================
// RATCHET / BASELINE — gêmeo de no-mock-in-prod.mjs / casos-coverage-guard.mjs
// =====================================================================================
//   node scripts/domain-dict-guard.mjs                  # valida vs baseline (exit 1 se piorou)
//   node scripts/domain-dict-guard.mjs --write-baseline  # (re)grava baseline
//   node scripts/domain-dict-guard.mjs --report          # relatório de dívida (humano)
//   node scripts/domain-dict-guard.mjs --json            # saída JSON pra CI
//
// O baseline (scripts/domain-dict-baseline.json) fotografa as divergências atuais (débito).
// Gate falha só em divergência NOVA (ratchet). Ex.: `order_type=locacao` entra no baseline
// agora; o PR de erradicação (ADR 0265) remove o enum e a divergência some sozinha.
//
// Refs: ADR 0264 (G-4 + Salto #3 cobertura de código) · ADR 0265 (erradica locação) · ADR 0261 (enforcement faseado) · ADR 0256 (catraca).

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';

const ROOT = process.cwd();
const DOMINIO_DIR = resolve(ROOT, 'memory/dominio');
const MODULES_DIR = resolve(ROOT, 'Modules');
const BASELINE_PATH = resolve(ROOT, 'scripts/domain-dict-baseline.json');

const MODE_WRITE = process.argv.includes('--write-baseline');
const MODE_REPORT = process.argv.includes('--report');
const MODE_JSON = process.argv.includes('--json');

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

function walk(dir, filter, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'node_modules' || e.name === 'vendor' || e.name === '.git') continue;
      walk(full, filter, acc);
    } else if (e.isFile() && filter(full, e.name)) {
      acc.push(full);
    }
  }
  return acc;
}

// ---------------------------------------------------------------------------
// Dicionários de domínio (fonte única)
// ---------------------------------------------------------------------------
function loadDicts() {
  // map: moduleName -> { enums: {tab.col: [vals]}, file }
  const dicts = {};
  if (!existsSync(DOMINIO_DIR)) return dicts;
  for (const e of readdirSync(DOMINIO_DIR, { withFileTypes: true })) {
    if (!e.isFile() || !e.name.endsWith('.md')) continue;
    const content = readFileSync(join(DOMINIO_DIR, e.name), 'utf8');
    const m = content.match(/```json\s*([\s\S]*?)```/);
    if (!m) continue;
    let parsed;
    try { parsed = JSON.parse(m[1]); } catch { continue; }
    if (!parsed?.module || !parsed?.enums) continue;
    dicts[parsed.module] = {
      enums: parsed.enums,
      forbidden_ui_terms: parsed.forbidden_ui_terms || [],
      forbidden_ui_paths: parsed.forbidden_ui_paths || [],
      // Onda Q3 — domínios CORE (vendas/estoque vivem em database/migrations + app/,
      // não em Modules/<X>): paths explícitos substituem os defaults por-módulo.
      // tables_scope restringe o undeclared-column check às tabelas que o domínio
      // REIVINDICA (sem ele, um dict core seria cobrado por users.marital_status etc).
      migrations_paths: Array.isArray(parsed.migrations_paths) ? parsed.migrations_paths : null,
      code_paths: Array.isArray(parsed.code_paths) ? parsed.code_paths : null,
      tables_scope: Array.isArray(parsed.tables_scope) ? parsed.tables_scope : null,
      // `vocab` (Onda Q3): vocabulário de coluna SEM constraint física (varchar) — o BD
      // não constrange, o dicionário é a ÚNICA lei. Entra no col-index do Salto #3
      // (código fora do vocab = violação) mas NÃO na comparação enum⇔schema (não há
      // enum pra comparar). Caso real: transactions.type virou varchar(191) na física
      // (módulos adicionam tipos como production_purchase sem ALTER).
      vocab: parsed.vocab && typeof parsed.vocab === 'object' ? parsed.vocab : {},
      file: `memory/dominio/${e.name}`,
    };
  }
  return dicts;
}

// ---------------------------------------------------------------------------
// Estado atual dos enums por migration (last-write-wins, up() only)
// ---------------------------------------------------------------------------
const parseEnumValues = (raw) =>
  [...raw.matchAll(/['"]([a-zA-Z0-9_]+)['"]/g)].map((x) => x[1]);

// Extrai definições de enum de uma região de texto (já só up()).
// Retorna [{ index, table, col, values }] na ordem do texto.
function extractEnumDefs(upRegion) {
  const defs = [];

  // Marcadores de tabela (Schema::create/table) com posição.
  const tableMarkers = [];
  for (const m of upRegion.matchAll(/Schema::(?:create|table)\(\s*['"]([a-z0-9_]+)['"]/g)) {
    tableMarkers.push({ index: m.index, table: m[1] });
  }
  const tableAt = (idx) => {
    let t = null;
    for (const mk of tableMarkers) { if (mk.index < idx) t = mk.table; else break; }
    return t;
  };

  // (a) ->enum('col', [ ... ])  — tabela vem do Schema::create/table mais próximo antes.
  for (const m of upRegion.matchAll(/->enum\(\s*['"]([a-z0-9_]+)['"]\s*,\s*\[([\s\S]*?)\]/g)) {
    const col = m[1];
    const values = parseEnumValues(m[2]);
    const table = tableAt(m.index);
    if (table && values.length) defs.push({ index: m.index, table, col, values });
  }

  // (b) ALTER TABLE <t> ... MODIFY [COLUMN] <col> ENUM( ... )  — tabela+coluna explícitas.
  for (const m of upRegion.matchAll(/ALTER\s+TABLE\s+([a-z0-9_]+)[\s\S]*?MODIFY\s+(?:COLUMN\s+)?([a-z0-9_]+)\s+ENUM\(([\s\S]*?)\)/gi)) {
    const table = m[1];
    const col = m[2];
    const values = parseEnumValues(m[3]);
    if (values.length) defs.push({ index: m.index, table, col, values });
  }

  return defs.sort((a, b) => a.index - b.index);
}

// Para um domínio: percorre migrations sorted, last-write-wins por tab.col.
// Default: Modules/<module>/Database/Migrations. Domínio CORE (Onda Q3) declara
// `migrations_paths` (ex.: ["database/migrations"]) e opcionalmente `tables_scope`
// (só as tabelas reivindicadas entram no estado → undeclared-column não cobra
// tabela alheia num diretório compartilhado).
// Retorna map: "tab.col" -> [valores atuais].
function currentEnums(moduleName, dict = null) {
  const dirs = dict?.migrations_paths?.length
    ? dict.migrations_paths.map((p) => resolve(ROOT, p))
    : [join(MODULES_DIR, moduleName, 'Database', 'Migrations')];
  // Ordena pelo BASENAME (timestamp da migration) com comparação por codepoint —
  // cross-dir o last-write-wins tem que ser CRONOLÓGICO (não path-alfabético) e
  // determinístico entre Windows/CI (localeCompare é locale-dependente). Caso real:
  // nfse_emissoes criada no NFSe (2026_05_01) e RE-criada no NfeBrasil (2026_05_11) —
  // o estado atual é o do NfeBrasil.
  const base = (p) => p.split(/[\\/]/).pop();
  const files = dirs
    .flatMap((d) => walk(d, (full, name) => name.endsWith('.php')))
    .sort((a, b) => (base(a) < base(b) ? -1 : base(a) > base(b) ? 1 : 0));
  const scope = dict?.tables_scope?.length ? new Set(dict.tables_scope) : null;
  const state = {};
  for (const file of files) {
    const content = readFileSync(file, 'utf8');
    const upRegion = content.split(/function\s+down\s*\(/)[0];
    // Cola literais de string concatenados em PHP ("ALTER ... MODIFY col " . "ENUM(...)")
    // pra o ALTER...ENUM voltar a ser uma sequência contígua antes do regex.
    const glued = upRegion.replace(/['"]\s*\.\s*['"]/g, '');
    for (const def of extractEnumDefs(glued)) {
      if (scope && !scope.has(def.table)) continue; // tabela não-reivindicada (Onda Q3)
      state[`${def.table}.${def.col}`] = def.values; // last write wins
    }
  }
  return state;
}

// Conjunto de módulos que têm migrations com enum (pra detectar module-no-dict).
function modulesWithEnums() {
  const out = new Set();
  if (!existsSync(MODULES_DIR)) return out;
  for (const e of readdirSync(MODULES_DIR, { withFileTypes: true })) {
    if (!e.isDirectory()) continue;
    const migDir = join(MODULES_DIR, e.name, 'Database', 'Migrations');
    const files = walk(migDir, (full, name) => name.endsWith('.php'));
    for (const f of files) {
      const c = readFileSync(f, 'utf8');
      if (/->enum\(|ENUM\(/i.test(c)) { out.add(e.name); break; }
    }
  }
  return out;
}

// ---------------------------------------------------------------------------
// SALTO #3 — cobertura de código (valores de domínio hardcoded fora do enum)
// ---------------------------------------------------------------------------
// Sufixo da coluna (ignora prefixo de tabela: 'service_orders.order_type' → 'order_type').
const shortColOf = (c) => (c.includes('.') ? c.split('.').pop() : c);

// Índice coluna-curta → Set(valores canônicos) a partir do dicionário do módulo.
// União por nome de coluna: um valor é "declarado" se for canônico pra QUALQUER tabela com
// aquela coluna (conservador — minimiza falso-positivo no ratchet).
function buildColIndex(enums) {
  const idx = {};
  for (const [tabCol, vals] of Object.entries(enums)) {
    const short = shortColOf(tabCol);
    (idx[short] ??= new Set());
    for (const v of vals) idx[short].add(v);
  }
  return idx;
}

// Arquivos de CÓDIGO de aplicação do domínio (exclui data-setup e testes — ver header).
// Default: Modules/<module>. Domínio CORE declara `code_paths` ESTREITOS (colunas
// genéricas tipo `status`/`tipo` super-casam — escopo largo em app/ = ruído).
function moduleCodeFiles(moduleName, dict = null) {
  if (dict?.code_paths) {
    const wanted = (full, name) => {
      if (!name.endsWith('.php') && !name.endsWith('.tsx') && !name.endsWith('.ts')) return false;
      const rel = norm(full);
      return !/\/(Database|Tests|tests)\//.test(rel);
    };
    return dict.code_paths.flatMap((p) => {
      const full = resolve(ROOT, p);
      if (!existsSync(full)) return [];
      // path pode ser um ARQUIVO único (controller específico) ou diretório.
      if (statSync(full).isFile()) return wanted(full, full.split(/[\\/]/).pop()) ? [full] : [];
      return walk(full, wanted);
    });
  }
  const dir = join(MODULES_DIR, moduleName);
  return walk(dir, (full, name) => {
    if (!name.endsWith('.php')) return false;
    const rel = norm(full);
    return !/\/(Database|Tests|tests)\//.test(rel);
  });
}

// Referências valor⇔coluna num texto PHP. Só literais entre aspas simples; variável/CONST
// (sem aspas) é ignorada de propósito. Retorna [{ col, value, index }].
function codeValueRefs(content) {
  const refs = [];

  // (1) where / orWhere — 2-arg ('col','v') ou 3-arg ('col','OP','v'). Agnóstico a aspas
  // (' ou ", via backreference). No 3-arg só conta IGUALDADE (=,==,===,!=,<>): operadores
  // tipo `like`/`>`/`<` levam pattern/número, não valor de domínio. Guarda extra: se o 2º
  // arg é um operador-palavra (like/ilike/...) o 3º (real valor) costuma ser variável/aspas
  // duplas — ignora. E o valor tem que parecer token de domínio (^[a-z0-9_]+$).
  const EQ_OPS = new Set(['=', '==', '===', '!=', '<>']);
  const WORD_OPS = new Set(['like', 'ilike', 'rlike', 'regexp', 'in', 'between', 'not']);
  const isDomainTok = (v) => /^[a-z0-9_]+$/.test(v);
  for (const m of content.matchAll(/->(?:or)?[wW]here\(\s*['"]([a-z0-9_.]+)['"]\s*,\s*(['"])([^'"]*)\2\s*(?:,\s*(['"])([^'"]*)\4)?/g)) {
    const col = shortColOf(m[1]);
    const hasThird = m[5] !== undefined;
    const value = hasThird ? m[5] : m[3];
    if (hasThird && !EQ_OPS.has(m[3])) continue;   // 3-arg não-igualdade (like/>/<) → ignora
    if (!hasThird && WORD_OPS.has(m[3])) continue;  // 2-arg cujo "valor" é na verdade operador → ignora
    if (!isDomainTok(value)) continue;
    refs.push({ col, value, index: m.index });
  }
  // (2) whereIn / whereNotIn com lista LITERAL (self::CONST sem '[' não casa, de propósito).
  for (const m of content.matchAll(/->where(?:Not)?In\(\s*'([a-z0-9_.]+)'\s*,\s*\[([^\]]*)\]/g)) {
    const col = shortColOf(m[1]);
    for (const vm of m[2].matchAll(/'([a-z0-9_]+)'/g)) refs.push({ col, value: vm[1], index: m.index });
  }
  // (3) comparação de campo: ->col OP 'v'  e a forma invertida 'v' OP $x->col.
  for (const m of content.matchAll(/->([a-z0-9_]+)\s*(?:===|!==|==|!=|<>)\s*'([a-z0-9_]+)'/g)) {
    refs.push({ col: m[1], value: m[2], index: m.index });
  }
  for (const m of content.matchAll(/'([a-z0-9_]+)'\s*(?:===|!==|==|!=|<>)\s*\$[a-z0-9_]+->([a-z0-9_]+)/g)) {
    refs.push({ col: m[2], value: m[1], index: m.index });
  }
  // (4) regra de validação Laravel `in:` na mesma linha lógica: 'col' => '...|in:v1,v2'.
  for (const m of content.matchAll(/'([a-z0-9_.]+)'\s*=>[^\n]*?\bin:([a-z0-9_,]+)/g)) {
    const col = shortColOf(m[1]);
    for (const v of m[2].split(',').filter(Boolean)) refs.push({ col, value: v, index: m.index });
  }

  return refs;
}

const lineOf = (content, index) => content.slice(0, index).split('\n').length;

// Viola se a coluna é GOVERNADA pelo dicionário e o valor NÃO é canônico.
// Retorna { keys:Set, occ:{ key -> [{file,line}] } }.
function codeValueViolations(moduleName, colIndex, dict = null) {
  const keys = new Set();
  const occ = {};
  if (!Object.keys(colIndex).length) return { keys, occ };
  for (const file of moduleCodeFiles(moduleName, dict)) {
    const content = readFileSync(file, 'utf8');
    for (const { col, value, index } of codeValueRefs(content)) {
      const allowed = colIndex[col];
      if (!allowed || allowed.has(value)) continue; // coluna não-governada OU valor canônico
      const key = `dominio:undeclared-code-value:${moduleName}:${col}:${value}`;
      keys.add(key);
      (occ[key] ??= []).push({ file: norm(file), line: lineOf(content, index) });
    }
  }
  return { keys, occ };
}

// ---------------------------------------------------------------------------
// SALTO #4 — termos PROIBIDOS user-facing (trava de regressão ADR 0265)
// ---------------------------------------------------------------------------
// Normaliza acento+caixa: "Locação" → "locacao" (NFD + strip combining marks).
const foldText = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

// CEGA comentários preservando posição/linhas (offsets ficam válidos pro lineOf):
// `/* ... */`, `// ...` (não-URL: exige não vir após ':') e `# ...` (PHP).
function blindComments(content) {
  const blank = (m) => m.replace(/[^\n]/g, ' ');
  return content
    .replace(/\/\*[\s\S]*?\*\//g, blank)
    .replace(/(^|[^:'"])\/\/[^\n]*/g, (m, pre) => pre + blank(m.slice(pre.length)))
    .replace(/(^|\s)#(?!\[)[^\n]*/g, (m, pre) => pre + blank(m.slice(pre.length)));
}

// Varre os paths declarados e emite UMA chave por ocorrência (índice estável):
// ratchet count-based — ocorrência nova num arquivo já-baselined também estoura.
// Retorna { keys: list, occ: { key -> [{file,line}] } }.
function forbiddenUiTermViolations(moduleName, dictEntry) {
  const keys = [];
  const occ = {};
  const terms = [...new Set((dictEntry.forbidden_ui_terms || []).map(foldText))].sort();
  const paths = dictEntry.forbidden_ui_paths || [];
  if (!terms.length || !paths.length) return { keys, occ };

  const exts = ['.php', '.ts', '.tsx', '.js', '.jsx'];
  for (const root of paths) {
    const dir = resolve(ROOT, root);
    const files = walk(dir, (full, name) => exts.some((e) => name.endsWith(e))).sort((a, b) =>
      a.localeCompare(b),
    );
    for (const file of files) {
      const folded = foldText(blindComments(readFileSync(file, 'utf8')));
      for (const term of terms) {
        let idx = 0;
        let n = 0;
        const lines = [];
        while ((idx = folded.indexOf(term, idx)) !== -1) {
          n++;
          lines.push(lineOf(folded, idx));
          idx += term.length;
        }
        for (let i = 1; i <= n; i++) {
          const key = `dominio:forbidden-ui-term:${moduleName}:${norm(file)}:${term}:${i}`;
          keys.push(key);
          occ[key] = [{ file: norm(file), line: lines[i - 1] }];
        }
      }
    }
  }
  return { keys, occ };
}

// ---------------------------------------------------------------------------
// Cálculo de violações
// ---------------------------------------------------------------------------
function computeViolations() {
  const dicts = loadDicts();
  const withEnums = modulesWithEnums();
  const violations = [];
  const occurrences = {}; // key -> [{file,line}] (code-value + forbidden-ui-term, pro --report)
  const stats = { modules_with_dict: Object.keys(dicts).length, modules_with_enums: withEnums.size, divergences: 0, code_divergences: 0, forbidden_ui_terms: 0, modules_no_dict: 0 };

  // Módulos com enum mas sem dicionário → débito (ratchet pra F3 cobrir todos).
  for (const mod of withEnums) {
    if (!dicts[mod]) { violations.push(`dominio:module-no-dict:${mod}`); stats.modules_no_dict++; }
  }

  // Módulos COM dicionário → comparação valor-a-valor.
  for (const [mod, dict] of Object.entries(dicts)) {
    const actual = currentEnums(mod, dict);
    // vocab conta como DECLARADA pro undeclared-column (migrations legadas podem registrar
    // enum antigo de coluna que hoje é varchar — ex transactions.type) mas fica FORA da
    // comparação valor-a-valor (não há constraint física pra comparar).
    const declaredCols = new Set([...Object.keys(dict.enums), ...Object.keys(dict.vocab)]);
    const actualCols = new Set(Object.keys(actual));

    // Coluna enum no schema do módulo, fora do dicionário.
    for (const col of actualCols) {
      if (!declaredCols.has(col)) { violations.push(`dominio:undeclared-column:${mod}:${col}`); stats.divergences++; }
    }

    for (const [col, canonicalVals] of Object.entries(dict.enums)) {
      const actualVals = actual[col];
      if (!actualVals) { violations.push(`dominio:missing-column-in-schema:${mod}:${col}`); stats.divergences++; continue; }
      const canon = new Set(canonicalVals);
      const real = new Set(actualVals);
      for (const v of real) if (!canon.has(v)) { violations.push(`dominio:undeclared-value:${mod}:${col}:${v}`); stats.divergences++; }
      for (const v of canon) if (!real.has(v)) { violations.push(`dominio:stale-dict-value:${mod}:${col}:${v}`); stats.divergences++; }
    }

    // SALTO #3 — valores de domínio hardcoded no código de aplicação.
    // col-index = enums (constraint física) ∪ vocab (vocabulário de varchar — Onda Q3).
    const { keys, occ } = codeValueViolations(mod, buildColIndex({ ...dict.enums, ...dict.vocab }), dict);
    for (const key of keys) { violations.push(key); occurrences[key] = occ[key]; stats.code_divergences++; }

    // SALTO #4 — termos PROIBIDOS user-facing (trava de regressão ADR 0265).
    const ui = forbiddenUiTermViolations(mod, dict);
    for (const key of ui.keys) { violations.push(key); occurrences[key] = ui.occ[key]; stats.forbidden_ui_terms++; }
  }

  return { violations: violations.sort((a, b) => a.localeCompare(b)), stats, occurrences };
}

function loadBaseline() {
  if (!existsSync(BASELINE_PATH)) return null;
  return JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  const { violations, stats, occurrences } = computeViolations();

  if (MODE_JSON) {
    const baseline = loadBaseline();
    const baseSet = new Set(baseline?.violations || []);
    const novos = violations.filter((v) => !baseSet.has(v));
    console.log(JSON.stringify({ stats, total: violations.length, baseline: baseSet.size, novos, ok: novos.length === 0 }, null, 2));
    process.exit(novos.length === 0 ? 0 : 1);
  }

  if (MODE_REPORT) {
    console.log('# Relatório de dívida — dominio:check (ADR 0264 G-4)\n');
    console.log(`Módulos com dicionário: ${stats.modules_with_dict} · com enum: ${stats.modules_with_enums}`);
    console.log(`Módulos com enum SEM dicionário: ${stats.modules_no_dict}`);
    console.log(`Divergências enum⇔dicionário (nos módulos com dict): ${stats.divergences}`);
    console.log(`Divergências de domínio no CÓDIGO (Salto #3): ${stats.code_divergences}`);
    console.log(`Termos proibidos user-facing (Salto #4): ${stats.forbidden_ui_terms}`);
    console.log(`\nTOTAL de violações (débito): ${violations.length}`);
    if (violations.length) {
      console.log('\nDetalhe:');
      for (const v of violations) {
        console.log('  · ' + v);
        // Code-value: lista as ocorrências file:line pra a dívida ser caçável.
        for (const o of occurrences[v] || []) console.log(`      ↳ ${o.file}:${o.line}`);
      }
    }
    console.log('\n→ F1 fotografa no baseline (não-bloqueante). Erradicação (ADR 0265) zera `order_type=locacao` no enum E no código.');
    process.exit(0);
  }

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        gate: 'dominio:check (ADR 0264 G-4 — dicionário de domínio ⇔ enum de migration + código, Salto #3)',
        stats,
        nota: 'Divergências ATUAIS fotografadas (débito): enum⇔dicionário E valores de domínio hardcoded no código (Salto #3). Gate falha só em divergência NOVA (ratchet). order_type=locacao zera quando a erradicação (ADR 0265) remover o valor do enum E das ramificações de código.',
        refs: ['ADR 0264', 'ADR 0265', 'ADR 0261', 'ADR 0256'],
      },
      violations,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`✅ Baseline gravado: ${violations.length} violações (${stats.divergences} enum · ${stats.code_divergences} código · ${stats.forbidden_ui_terms} termo UI · ${stats.modules_no_dict} módulo sem dict) → ${norm(BASELINE_PATH)}`);
    process.exit(0);
  }

  // VALIDATE
  console.log(`dominio:check · ${violations.length} violações (dicts: ${stats.modules_with_dict}, enum: ${stats.divergences}, código: ${stats.code_divergences}, termo UI: ${stats.forbidden_ui_terms})`);
  const baseline = loadBaseline();
  if (!baseline) {
    console.error(`\n❌ Baseline ausente (${norm(BASELINE_PATH)}). Rode: npm run dominio:baseline:write`);
    process.exit(1);
  }
  const baseSet = new Set(baseline.violations || []);
  const novos = violations.filter((v) => !baseSet.has(v));

  if (novos.length) {
    console.error(`\n❌ ${novos.length} divergência(s) NOVA(s) de domínio (não no baseline):\n`);
    for (const v of novos) {
      console.error('  🆕 ' + v);
      for (const o of occurrences[v] || []) console.error(`        ↳ ${o.file}:${o.line}`);
    }
    console.error(
      `\nUm enum de migration, um valor-literal no código OU um termo proibido user-facing` +
        `\ndivergiu do dicionário do módulo (memory/dominio/<mod>.md) — ADR 0264 G-4` +
        `\n(+ Salto #3 código · Salto #4 termos UI).` +
        `\nReintroduzir \`locacao\`/\`locada\`/"locação"/"caçamba" viola a ADR 0265 (ver memory/proibicoes.md).` +
        `\nSe a mudança de domínio for legítima: atualize o dicionário + npm run dominio:baseline:write`,
    );
    process.exit(1);
  }

  const delta = (baseline.violations?.length || 0) - violations.length;
  console.log(`✅ Sem divergências novas (débito ${delta > 0 ? `caiu −${delta}` : 'estável'} vs baseline).`);
  process.exit(0);
}

main();
