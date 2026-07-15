#!/usr/bin/env node
// @ts-check
/**
 * component-registry-check.mjs — sentinela de DRIFT do registro de componentes (Onda O2).
 *
 * Valida prototipo-ui/component-registry.json contra o código React REAL: o handoff
 * Cowork → Inertia consome o registry pra traduzir bloco-de-protótipo → componente; se
 * o registry aponta pra componente/import que não existe mais, a tradução regride. Esta
 * sentinela pega esse drift ANTES do handoff.
 *
 * Pra cada entrada com status 'mapped':
 *   1. o arquivo (`file`, repo-relative) existe?
 *   2. o `import_path` resolve pro mesmo arquivo? (alias @/ → resources/js/)
 *   3. cada símbolo em `exports` é REALMENTE exportado pelo arquivo? (parse leve do .tsx/.ts —
 *      cobre `export { A, B }`, `export { A } from './x'`, `export function A`, `export const A`,
 *      `export default`, `export type { T }`)
 * Entradas com status 'gap' (bloco de protótipo sem React equivalente) são puladas — não
 * podem ter file/import/exports (M-AP-6: não fabricar componente).
 *
 * ADVISORY (gate novo nasce advisory — ADR 0271/0275): --check imprime o relatório e
 * SAI 0 SEMPRE no modo advisory (default). Use --strict pra exit 1 quando houver drift
 * (usado pelo self-test e por promoção futura a required). Determinístico, sem deps, sem rede.
 *
 * ── MODO --roles (detector de PAPEL-duplicado, ADR proposta tab-nav-canonico) ──
 * Responde a outra face do MESMO drift: o registry canoniza 1 componente por PAPEL
 * (ex "barra de abas de topo" → PageHeaderTabs), mas o código pode ter N componentes
 * hand-rolando esse papel (foi a CAUSA dos 8 topnavs divergentes — dark quebrado,
 * radius errado). Agrupa os .tsx por papel (heurística: markup `role="tablist"` de
 * nav-bar + nome `*Nav`/`*Tabs`/`*TopNav`) e particiona cada cluster em:
 *   - CANON     : o componente canônico do papel (1)
 *   - CONSUMER  : importa o canônico (wrapper legítimo — FinanceiroSubNav/JanaSubNav…)
 *   - INDEPENDENTE: cumpre o papel SEM importar o canônico = drift a migrar
 * Sinaliza clusters com INDEPENDENTE > 0. Advisory por default (exit 0); --strict
 * pra exit 1. Fronteira honesta: heurística sintática ≠ prova de papel — por isso
 * report-only, um humano decide a migração (não é gate cego).
 *
 * Uso:
 *   node scripts/governance/component-registry-check.mjs [--check] [--strict] [--registry <path>] [--root <path>]
 *   node scripts/governance/component-registry-check.mjs --roles [--strict] [--root <path>]
 */
import { readFileSync, existsSync, statSync, readdirSync } from 'node:fs';
import { join, resolve, relative, basename } from 'node:path';

// existsSync casa diretório também; aqui só queremos ARQUIVO (senão '@/Components/layout'
// casaria a pasta antes de chegar no /index.ts barril).
function isFile(p) {
  try { return statSync(p).isFile(); } catch { return false; }
}

const args = process.argv.slice(2);
function argVal(flag, def) {
  const i = args.indexOf(flag);
  return i >= 0 && args[i + 1] ? args[i + 1] : def;
}
const STRICT = args.includes('--strict');
const ROOT = resolve(argVal('--root', process.cwd()));
const REGISTRY = resolve(argVal('--registry', join(ROOT, 'prototipo-ui/component-registry.json')));

// alias @/ → resources/js/ (vite.config / tsconfig do projeto)
function resolveImport(importPath) {
  if (!importPath) return null;
  let rel = importPath;
  if (rel.startsWith('@/')) rel = join('resources/js', rel.slice(2));
  const base = join(ROOT, rel);
  // tenta .ts, .tsx, /index.ts, /index.tsx (barril)
  for (const cand of [base, `${base}.tsx`, `${base}.ts`, join(base, 'index.ts'), join(base, 'index.tsx')]) {
    if (isFile(cand)) return cand;
  }
  return null;
}

// Parse leve: coleta TODOS os símbolos exportados de um arquivo (não roda TS).
function collectExports(src) {
  const found = new Set();
  // export { A, B as C, type D } [from '...']  — pega o NOME PÚBLICO (após "as" se houver)
  for (const m of src.matchAll(/export\s+(?:type\s+)?\{([^}]*)\}/g)) {
    for (let part of m[1].split(',')) {
      part = part.trim();
      if (!part) continue;
      part = part.replace(/^type\s+/, '');
      const asMatch = part.match(/\bas\s+([A-Za-z0-9_$]+)/);
      const name = asMatch ? asMatch[1] : part.split(/\s+/)[0];
      if (name) found.add(name.trim());
    }
  }
  // export function|const|class|let|var|type|interface|enum NAME
  for (const m of src.matchAll(/export\s+(?:async\s+)?(?:function|const|class|let|var|type|interface|enum)\s+([A-Za-z0-9_$]+)/g)) {
    found.add(m[1]);
  }
  // export default ...
  if (/export\s+default\b/.test(src)) found.add('default');
  return found;
}

// ══════════════════════════════════════════════════════════════════════════
// MODO --roles: detector de PAPEL-duplicado (ADR proposta tab-nav-canonico)
// ══════════════════════════════════════════════════════════════════════════

/**
 * Assinaturas de papel. Cada papel canoniza 1 componente; o detector procura
 * OUTROS componentes cumprindo o mesmo papel sem consumir o canônico.
 *
 * `canonImport` aceita string OU lista de strings (o papel pode ser cumprido por
 * mais de um canônico — ex status-badge = o primitivo <Badge> OU o wrapper de
 * domínio <StatusBadge>; importar QUALQUER um = consumidor legítimo).
 *
 * ONDAS ABERTAS: `barra-de-abas-de-topo` + `sub-navegacao-contextual` (ADR proposta
 * tab-nav-canonico) · `combobox` (campo de busca com dropdown) · `status-badge`
 * (pílula de status, Onda 2026-07-15). Cada nova onda entra como entrada aqui.
 */
const ROLE_SIGNATURES = [
  {
    role: 'barra-de-abas-de-topo',
    canon: 'resources/js/Components/shared/PageHeaderTabs.tsx',
    canonImport: '@/Components/shared/PageHeaderTabs',
    // Um componente cumpre o papel se: (markup) declara uma nav-bar com role=tablist
    // ou classe de topnav conhecida, OU (nome) o basename é de nav/tabs de topo.
    matches(file, src) {
      const name = basename(file);
      // O SubNav compartilhado NÃO é barra de abas de topo — é sub-navegação
      // contextual in-page (papel próprio, abaixo). Casava por nome/JSDoc
      // (`subnav`) = falso-positivo de proximidade. Exclui explicitamente
      // (decisão DS [W] 2026-07-15: SubNav = papel distinto, não drift do tab-nav).
      if (file === 'resources/js/Components/shared/SubNav.tsx') return false;
      // markup = role=tablist ACOMPANHADO de uma classe de barra-de-topo conhecida
      // (não basta tablist: in-panel/mobile tabs também usam tablist e são OUTRO papel).
      const markup =
        /role=["']tablist["']/.test(src) &&
        /(moduletopnav|cli-moduletopnav|fx-subtabs|\bsubtabs\b|\btopnav\b|\bsubnav\b)/i.test(src);
      const byName = /(ModuleTopNav|TopNav|SubNav|Tabs|Tablist)\.tsx$/.test(name);
      // exclui falsos-amigos de nome que NÃO são barra de navegação de topo
      const notNav = /(MobileTabs|Chips|Preview|Message)\.tsx$/.test(name);
      return (markup || byName) && !notNav;
    },
  },
  {
    // Papel DISTINTO da barra de abas de topo (decisão DS [W] 2026-07-15).
    // Sub-navegação CONTEXTUAL dentro de uma mesma página: troca de seção via
    // estado controlado (`value`/`onChange`), SEM mudar a URL — não tem `href`
    // nem lê `shell.menu`. Variantes underline/segmented, baixo contraste.
    // Consumidor vivo: Jana/Admin/Governanca (sub-tabs de modo de gráfico).
    role: 'sub-navegacao-contextual',
    canon: 'resources/js/Components/shared/SubNav.tsx',
    canonImport: '@/Components/shared/SubNav',
    // Discriminador do papel: controlado (value/onChange), sem shell.menu/href.
    // Detecção de hand-rolls independentes fica DEFERIDA à onda respectiva
    // (mesmo padrão de status-badge/combobox) — hoje só registra o canon
    // pra o papel existir no tooling e o detector parar de confundi-lo com
    // tab-nav. Ampliar o matches() é decisão de abrir a onda (não unilateral).
    matches(file /*, src */) {
      return file === 'resources/js/Components/shared/SubNav.tsx';
    },
  },
  {
    // O "campo de busca com dropdown" (combobox / autocomplete) canoniza na
    // composição Popover + Command (cmdk) — o Command é o MOTOR: input de busca +
    // lista filtrada + navegação de teclado + a11y (role=combobox/listbox/option,
    // aria-activedescendant) de fábrica. Cada hand-roll reimplementa esse motor à
    // mão (input + <ul role="listbox"> + onKeyDown ArrowUp/Down) de um jeito
    // ligeiramente diferente — a11y divergente/bugada é a CAUSA. Referência viva do
    // consumo certo: Pages/OficinaAuto/ServiceOrders/Create.tsx (Popover+Command).
    role: 'combobox',
    // Âncora = o MOTOR canônico (Command). "Importa o canon" = importa
    // @/Components/ui/command = construiu sobre o motor (qualquer skin) = consumidor.
    // Popover sozinho é só posicionamento — não conta como consumir o combobox.
    canon: 'resources/js/Components/ui/command.tsx',
    canonImport: '@/Components/ui/command',
    matches(file, src) {
      const name = basename(file);
      // markup = signals de um combobox hand-rolado na TELA (aria-autocomplete é o
      // sinal-definidor de input-autocomplete à mão; role=combobox idem). O padrão
      // canônico põe role=combobox no <Button> trigger e NUNCA usa aria-autocomplete.
      const markup = /aria-autocomplete\s*=/.test(src) || /role=["']combobox["']/.test(src);
      // nome = o papel está no nome do componente
      const byName = /(Combobox|Autocomplete|Typeahead|Lookup)\.tsx$/.test(name);
      // o próprio motor canônico self-matcha pelo basename (cmdk seta os ARIA em
      // runtime, não escreve role=combobox literal) → evita "canon AUSENTE" falso.
      const isCanon = /^command\.tsx$/i.test(name);
      // falsos-amigos: ⌘K palette e card-grid picker NÃO são o combobox-de-campo.
      const notCombobox = /(CommandPalette|CommandDialog|TemplatePicker|Preview|Message)\.tsx$/.test(name);
      return (markup || byName || isCanon) && !notCombobox;
    },
  },
  {
    role: 'status-badge',
    // Papel cumprido por DOIS canônicos (importar qualquer um = consumidor):
    //   - primitivo <Badge variant="success|warning|danger|info|neutral"> (tokenizado -soft/-fg)
    //   - wrapper de domínio <StatusBadge kind value> (mapeia string-de-domínio → tone+label
    //     sobre Badge). O `canon` abaixo é o wrapper (é o arquivo que casa por NOME e ancora
    //     a fidelidade); o primitivo entra via canonImport.
    canon: 'resources/js/Components/shared/StatusBadge.tsx',
    canonImport: ['@/Components/shared/StatusBadge', '@/Components/ui/badge'],
    matches(file /* , src */) {
      const name = basename(file);
      // Sinal = NOME. O markup de pill (rounded+px+cor-de-status) é genérico demais —
      // casa centenas de usos INLINE; esse eixo inline fica com a regra
      // ds/no-handrolled-status-pill + ratchet (0209). O detector rastreia COMPONENTES
      // reusáveis que reimplementam o papel (fronteira honesta: nome ≠ prova de papel →
      // report-only, humano decide a migração).
      const byName = /(StatusBadge|StatusPill|StatusChip|StageBadge|StagePill|Pills)\.tsx$/.test(name);
      // Exclui: (a) canon fiscal FiscalStatusBadge/NfceStatusBadge — exceção DOCUMENTADA
      // (R-DS-002 / ADR 0235, paleta oklch própria, fonte única do status fiscal; NÃO é
      // drift); (b) chips de CATEGORIA/FILTRO/avatar (papel diferente).
      const notStatus = /(FiscalStatusBadge|NfceStatusBadge|TagChip|TipoPill|ActiveChip|Avatar)\.tsx$/.test(name);
      return byName && !notStatus;
    },
  },
];

// Walker recursivo simples (sem deps). Ignora node_modules/dist/vendor.
function walkTsx(dir, acc = []) {
  let ents;
  try { ents = readdirSync(dir, { withFileTypes: true }); } catch { return acc; }
  for (const ent of ents) {
    if (ent.name === 'node_modules' || ent.name === 'dist' || ent.name === '.git') continue;
    const full = join(dir, ent.name);
    if (ent.isDirectory()) walkTsx(full, acc);
    else if (ent.isFile() && ent.name.endsWith('.tsx')) acc.push(full);
  }
  return acc;
}

// rel repo-relativo → specifier de import com alias @/ (sem extensão). Espelha o
// alias @/ → resources/js/ do projeto. Usado pra detectar consumo TRANSITIVO.
function relToAlias(rel) {
  return rel.replace(/^resources\/js\//, '@/').replace(/\.(t|j)sx?$/, '');
}

function scanRoles(root) {
  const base = join(root, 'resources/js');
  const files = existsSync(base) ? walkTsx(base) : [];
  const clusters = [];
  for (const sig of ROLE_SIGNATURES) {
    const canon = [];
    const matched = []; // {rel, src} que cumprem o papel e NÃO são o canon
    for (const abs of files) {
      const rel = relative(root, abs).replaceAll('\\', '/');
      let src;
      try { src = readFileSync(abs, 'utf8'); } catch { continue; }
      if (!sig.matches(rel, src)) continue;
      if (rel === sig.canon) { canon.push(rel); continue; }
      matched.push({ rel, src });
    }
    // canonImport pode ser string OU lista (papel com >1 canônico — ex status-badge
    // = <Badge> OU <StatusBadge>). Importar QUALQUER um = consumidor direto.
    const imports = Array.isArray(sig.canonImport) ? sig.canonImport : [sig.canonImport];
    const importsAny = (src) => imports.some((i) => src.includes(i));
    // Consumidor DIRETO = importa o canon (é um wrapper, ex *SubNav).
    const direct = matched.filter((m) => importsAny(m.src));
    const rest = matched.filter((m) => !importsAny(m.src));
    // Consumo TRANSITIVO: uma tela que renderiza um wrapper (que por sua vez
    // importa o canon) JÁ consome o papel — não é hand-roll independente. Sem
    // isso, telas como Financeiro/Unificado (importam FinanceiroSubNav + têm um
    // tablist de DRAWER, papel diferente) viravam falso-positivo de drift.
    const consumerSpecs = direct.map((m) => relToAlias(m.rel));
    const consumers = direct.map((m) => m.rel);
    const independent = [];
    for (const m of rest) {
      const transitive = consumerSpecs.some((spec) => m.src.includes(spec));
      (transitive ? consumers : independent).push(m.rel);
    }
    clusters.push({ role: sig.role, canon: sig.canon, canonPresent: canon.length > 0, consumers, independent });
  }
  return clusters;
}

function reportRoles(clusters, strict) {
  let drift = 0;
  console.log(`component-registry-check --roles — ${clusters.length} papel(éis) rastreado(s)`);
  for (const c of clusters) {
    const total = (c.canonPresent ? 1 : 0) + c.consumers.length + c.independent.length;
    console.log(`\n▸ papel "${c.role}" — ${total} componente(s) no cluster`);
    console.log(`  canon: ${c.canonPresent ? c.canon : `⚠️ AUSENTE (${c.canon})`}`);
    if (c.consumers.length) console.log(`  consumidores (consomem o canon — direto ou via wrapper *SubNav): ${c.consumers.length}`);
    for (const f of c.consumers) console.log(`    ✓ ${f}`);
    if (c.independent.length) {
      drift += c.independent.length;
      console.log(`  INDEPENDENTES (cumprem o papel SEM importar o canon → drift a migrar): ${c.independent.length}`);
      for (const f of c.independent) console.log(`    ⚠️ ${f}`);
    } else {
      console.log(`  independentes: 0 ✅ (nenhum hand-roll paralelo ao canon)`);
    }
  }
  if (drift === 0) {
    console.log(`\n[OK] nenhum papel com hand-roll independente do canon.`);
  } else {
    console.log(`\n[DRIFT] ${drift} componente(s) independente(s) cumprindo papel canonizado — migrar pro canon (ver REGISTRY_DS_COMPONENTES.md).`);
    if (!strict) console.log(`(advisory — exit 0; rode com --strict pra falhar o build)`);
  }
  return drift;
}

function main() {
  // MODO --roles: detector de papel-duplicado (separado do drift de registry JSON).
  if (args.includes('--roles')) {
    const drift = reportRoles(scanRoles(ROOT), STRICT);
    process.exit(STRICT && drift ? 1 : 0);
  }

  if (!existsSync(REGISTRY)) {
    console.error(`[ERRO] registry não encontrado: ${REGISTRY}`);
    process.exit(STRICT ? 1 : 0);
  }
  /** @type {{ entries: any[] }} */
  let reg;
  try {
    reg = JSON.parse(readFileSync(REGISTRY, 'utf8'));
  } catch (e) {
    console.error(`[ERRO] registry inválido (JSON): ${e.message}`);
    process.exit(STRICT ? 1 : 0);
  }
  const entries = Array.isArray(reg.entries) ? reg.entries : [];
  const drift = [];
  let mapped = 0, gaps = 0;

  for (const e of entries) {
    const tag = e.bloco_prototipo || e.componente_react || '(sem-nome)';
    if (e.status === 'gap') {
      gaps++;
      // gap NÃO pode ter file/import/exports — isso seria fabricação (M-AP-6)
      if (e.file || e.import_path || (Array.isArray(e.exports) && e.exports.length)) {
        drift.push({ tag, motivo: `status 'gap' não pode declarar file/import_path/exports (fabricação)` });
      }
      continue;
    }
    if (e.status !== 'mapped') {
      drift.push({ tag, motivo: `status desconhecido: '${e.status}' (esperado mapped|gap)` });
      continue;
    }
    mapped++;

    // 1. file existe?
    if (!e.file) { drift.push({ tag, motivo: `entrada 'mapped' sem campo 'file'` }); continue; }
    const filePath = join(ROOT, e.file);
    if (!existsSync(filePath)) {
      drift.push({ tag, motivo: `file não existe: ${e.file}` });
      continue;
    }

    // 2. import_path resolve pro mesmo arquivo?
    const resolved = resolveImport(e.import_path);
    if (!resolved) {
      drift.push({ tag, motivo: `import_path não resolve: '${e.import_path}'` });
    } else if (relative(resolved, filePath) !== '') {
      drift.push({ tag, motivo: `import_path '${e.import_path}' resolve pra ${relative(ROOT, resolved).replaceAll('\\', '/')} != file ${e.file}` });
    }

    // 3. exports existem? (icon-registry e barris podem ter exports:[] → só checa file)
    const wantExports = Array.isArray(e.exports) ? e.exports : [];
    if (wantExports.length) {
      const src = readFileSync(filePath, 'utf8');
      const have = collectExports(src);
      const missing = wantExports.filter((x) => !have.has(x));
      if (missing.length) {
        drift.push({ tag, motivo: `exports ausentes em ${e.file}: ${missing.join(', ')}` });
      }
    }
  }

  // relatório
  console.log(`component-registry-check — ${entries.length} entradas (${mapped} mapped · ${gaps} gap)`);
  if (drift.length === 0) {
    console.log(`[OK] registro íntegro: todo 'mapped' bate com arquivo/export real; todo 'gap' sem fabricação.`);
  } else {
    console.log(`[DRIFT] ${drift.length} problema(s):`);
    for (const d of drift) console.log(`  - ${d.tag}: ${d.motivo}`);
    if (!STRICT) {
      console.log(`\n(advisory — exit 0; rode com --strict pra falhar o build)`);
    }
  }

  process.exit(STRICT && drift.length ? 1 : 0);
}

main();
