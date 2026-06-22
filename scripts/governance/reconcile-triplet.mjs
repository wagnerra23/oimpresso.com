#!/usr/bin/env node
// @ts-check
/**
 * reconcile-triplet.mjs — gate de PARIDADE POR SETOR (3-way charter↔protótipo↔produção).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ⚠️  v1 HEURÍSTICO — DETECÇÃO POR ASSINATURA, NÃO PARSING SEMÂNTICO COMPLETO.
 * ─────────────────────────────────────────────────────────────────────────────
 * O loop design→code do oimpresso comparava 2-way (protótipo Cowork × código
 * Inertia) e o CHARTER (o spec, fonte de verdade) NÃO era coluna. Resultado: quando
 * o protótipo contradiz o charter, o conflito SUMIA e o diff virava lixo (ex: Produto
 * — charter MANDA card-grid, produção É card-grid, mas o protótipo de hoje é lista
 * densa → o 2-way reportaria "mudou tudo", falso).
 *
 * Este gate torna o CHARTER a 1ª coluna. Para cada tela decompõe charter, protótipo
 * (se existir) e produção nos 6 SLOTS canônicos do PT-01 (memory/requisitos/
 * _DesignSystem/padroes-tela/PT-01-Lista.md) via assinaturas DETECTÁVEIS, e classifica
 * cada slot em 3 estados:
 *   - CONFORME            — charter ≈ produção
 *   - DIVERGENCIA_DECLARADA — diferem MAS há `divergence_from_blueprint` no frontmatter
 *   - DIVERGENCIA_MUDA    — diferem SEM declaração → FALHA (--strict exit 1)
 * O veredito da tela = pior slot, nomeado.
 *
 * HONESTIDADE SOBRE OS LIMITES (v1):
 *   - Slot 5 distingue table↔grid por presença de `<table`/`<thead` vs `grid-cols`/
 *     `card`/`<article`. Não entende semântica fina (ex: grid de KPIs ≠ grid de cards).
 *   - Charter "manda" é inferido das seções Goals / UX Anti-patterns / Non-Goals por
 *     palavras-chave (grid, card, tabela, table, PageHeader, BulkBar, drawer, sheet…).
 *   - O protótipo, quando AUSENTE no filesystem, é marcado AUSENTE (não vira falha por
 *     si — só sinaliza ponteiro órfão). Comparação CONFORME/DIVERGENTE usa charter×produção.
 *   - Não há render real / screenshot diff. Isto é uma CATRACA ESTRUTURAL barata, não
 *     o gate visual F1.5/F3 (Wagner aprova screenshot). É complementar, não substituto.
 *
 * Por design: ADVISORY DE NASCENÇA (exit 0 com aviso). `--strict` faz exit 1 em
 * DIVERGENCIA_MUDA. Determinístico, sem deps, sem LLM.
 *
 * Uso:
 *   node scripts/governance/reconcile-triplet.mjs --module=Produto --tela=Index [--json] [--write] [--strict]
 *   node scripts/governance/reconcile-triplet.mjs --all [--json] [--strict]
 *   node scripts/governance/reconcile-triplet.mjs --audit-pointers   (delega p/ charter-blueprint-pointers.mjs)
 */
import { readFileSync, readdirSync, mkdirSync, writeFileSync, realpathSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const ROOT = process.cwd();
const PAGES = join(ROOT, 'resources/js/Pages');
const COWORK_MAP = join(ROOT, 'prototipo-ui/cowork-map.json');

// ── existsExact: case-SENSITIVE (espelha CI Linux / Hostinger). Sem isto, Windows/macOS
//    mente (case-insensitive) e o veredito diverge entre dev e CI. ─────────────────────
function existsExact(p) {
  if (!p) return false;
  try { return realpathSync.native(p).replaceAll('\\', '/') === p.replaceAll('\\', '/'); }
  catch { return false; }
}
function dirExists(p) {
  try { return statSync(p).isDirectory(); } catch { return false; }
}

function splitFrontmatter(content) {
  const m = content.match(/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s);
  return m ? [m[1], m[2]] : ['', content];
}

/** lê chave escalar simples do frontmatter (sem YAML lib — string entre aspas ou nua). */
function fmScalar(fm, key) {
  const m = fm.match(new RegExp('^\\s*' + key + ':\\s*["\\\']?(.+?)["\\\']?\\s*$', 'm'));
  return m ? m[1].trim() : null;
}

/* ───────────────────────────── descoberta de telas ───────────────────────────── */

const hasUnderscoreSeg = (rel) => rel.split('/').some((s) => s && s[0] === '_');

/** lista todos os *.charter.md sob Pages/ (relpath), ignorando segmentos _privados. */
function allCharters() {
  const out = [];
  if (!dirExists(PAGES)) return out;
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile() && p.endsWith('.charter.md')) {
        const rel = p.slice(PAGES.length + 1).replaceAll('\\', '/');
        if (!hasUnderscoreSeg(rel)) out.push(rel);
      }
    }
  })(PAGES);
  return out.sort();
}

/** resolve charterRel (ex "Produto/Index.charter.md") → caminhos absolutos. */
function pathsForCharter(charterRel) {
  const charterAbs = join(PAGES, charterRel);
  const tsxRel = charterRel.replace(/\.charter\.md$/, '.tsx');
  const tsxAbs = join(PAGES, tsxRel);
  return { charterAbs, tsxAbs, tsxRel: 'resources/js/Pages/' + tsxRel, charterRel: 'resources/js/Pages/' + charterRel };
}

/* ─────────────────────── resolução do path de protótipo ─────────────────────── */

/**
 * Resolve onde o protótipo da tela DEVERIA estar e se EXISTE.
 * Fontes (em ordem): frontmatter mwart_pattern_reuse.blueprint_cowork → cowork-map (por
 * page_id / chave) → Refs do corpo do charter. DETECTA ausência (vácuo).
 *
 * @returns {{declared: string[], existing: string[], missing: string[], source: string}}
 */
function resolvePrototype(charterAbs, fm, body, coworkMap) {
  const declared = [];

  // 1. frontmatter blueprint_cowork (path direto)
  const bp = fmScalar(fm, 'blueprint_cowork');
  if (bp) declared.push({ path: bp.replace(/\/$/, ''), src: 'frontmatter:blueprint_cowork' });

  // 2. cowork-map: casa pela pasta destino (.to) cuja screen mapeia este page/module.
  //    Heurística honesta: pega o page do frontmatter (`page:`) ou o nome do dir.
  if (coworkMap && coworkMap.screens) {
    for (const [key, screen] of Object.entries(coworkMap.screens)) {
      const routes = (screen && screen.routes) || [];
      for (const r of routes) {
        const to = (r && r.to) || '';
        if (to && to.includes('/prototipos/')) {
          const dir = to.replace(/\/$/, '');
          // só consideramos como candidato se a chave bate com algum token do charter rel
          const relDir = charterAbs.replace(/\\/g, '/');
          if (relDir.toLowerCase().includes('/' + key.toLowerCase() + '/') ||
              relDir.toLowerCase().includes('/' + key.replace(/-/g, '').toLowerCase() + '/')) {
            if (!declared.some((d) => d.path === dir)) declared.push({ path: dir, src: 'cowork-map:' + key });
          }
        }
      }
    }
  }

  // 3. Refs do corpo: linhas markdown apontando p/ prototipo-ui/** ou ui_kits/**
  for (const mm of body.matchAll(/`(prototipo-ui\/[^`]+|ui_kits\/[^`]+)`/g)) {
    const raw = mm[1].trim();
    if (/\.(jsx|tsx|html|css)$/.test(raw) || raw.endsWith('/')) {
      const norm = raw.replace(/\/$/, '');
      if (!declared.some((d) => d.path === norm)) declared.push({ path: norm, src: 'charter:Refs' });
    }
  }

  const existing = [];
  const missing = [];
  for (const d of declared) {
    const abs = join(ROOT, d.path);
    const ok = d.path.match(/\.(jsx|tsx|html|css)$/) ? existsExact(abs) : dirExists(abs);
    (ok ? existing : missing).push(d);
  }
  return {
    declared: declared.map((d) => d.path),
    existing: existing.map((d) => `${d.path} (${d.src})`),
    missing: missing.map((d) => `${d.path} (${d.src})`),
    source: declared.length ? declared.map((d) => d.src).join(', ') : 'nenhum',
  };
}

/** carrega o conteúdo concatenado de TODOS os arquivos de um dir de protótipo existente. */
function loadPrototypeContent(prototypeDirs) {
  let content = '';
  for (const d of prototypeDirs) {
    const path = d.replace(/ \(.*\)$/, ''); // tira o sufixo (src)
    const abs = join(ROOT, path);
    if (existsExact(abs) && abs.match(/\.(jsx|tsx|html|css)$/)) {
      try { content += '\n' + readFileSync(abs, 'utf8'); } catch { /* ignore */ }
    } else if (dirExists(abs)) {
      for (const e of readdirSync(abs)) {
        if (/\.(jsx|tsx|html|css)$/.test(e)) {
          try { content += '\n' + readFileSync(join(abs, e), 'utf8'); } catch { /* ignore */ }
        }
      }
    }
  }
  return content;
}

/* ───────────────────── decomposição nos 6 slots PT-01 ─────────────────────── */
//
// Cada slot tem uma ASSINATURA detectável em (a) charter — o que o spec MANDA, lido
// das seções Goals/Non-Goals/UX Anti-patterns; (b) protótipo/produção — o que o JSX/HTML
// RENDERIZA. Heurística honesta, documentada slot a slot.

const SLOTS = [
  { id: 1, nome: 'PageHeader' },
  { id: 2, nome: 'ModuleTopNav' },
  { id: 3, nome: 'Toolbar' },
  { id: 4, nome: 'BulkBar' },
  { id: 5, nome: 'Table/Grid' },
  { id: 6, nome: 'Drawer' },
];

/**
 * Detecta a "forma" de cada slot num blob de código (JSX/HTML/TSX).
 * Valores possíveis por slot:
 *   1 PageHeader : 'header-sticky' | 'pageheader-shared' | 'ausente'
 *   2 ModuleTopNav: 'subnav' | 'tabs' | 'ausente'
 *   3 Toolbar    : 'busca+filtros' | 'busca' | 'ausente'
 *   4 BulkBar    : 'bulkbar' | 'ausente'
 *   5 Table/Grid : 'table' | 'grid' | 'ausente'
 *   6 Drawer     : 'sheet' | 'drawer' | 'ausente'
 */
function detectSlotsFromCode(code) {
  const c = (code || '').toLowerCase();
  const has = (...res) => res.some((re) => re.test(c));
  return {
    1: has(/<pageheader/, /pageheader\b/) ? 'pageheader-shared'
      : has(/<header[\s>]/, /sticky top-0/) ? 'header-sticky'
      : 'ausente',
    2: has(/<moduletopnav/, /subnav/, /sub-tabs/) ? 'subnav'
      : has(/role=["']tablist["']/, /aria-label=["'][^"']*categoria/, /\btabs\b/) ? 'tabs'
      : 'ausente',
    3: has(/type=["']search["']/, /placeholder=["'][^"']*buscar/) && has(/filtro/, /<filterdropdown/, /mostrar inativos/, /checkbox/)
        ? 'busca+filtros'
      : has(/type=["']search["']/, /placeholder=["'][^"']*buscar/, /<input.*search/) ? 'busca'
      : 'ausente',
    4: has(/<bulkactionbar/, /bulkbar/, /selected\.length\s*>\s*0/, /em lote/) ? 'bulkbar' : 'ausente',
    5: has(/<table[\s>]/, /<thead/, /<tbody/, /<datatable/) ? 'table'
      : has(/grid-cols/, /grid grid-cols/, /<article/, /produtocard/, /\bcards?\b/) ? 'grid'
      : 'ausente',
    6: has(/<sheet[\s>]/, /detailsheet/, /<drawer/, /slide-in/, /slide-over/) ? 'sheet'
      : 'ausente',
  };
}

/**
 * Detecta o que o CHARTER MANDA por slot, lendo Goals/Non-Goals/UX Anti-patterns.
 * Retorna o mesmo vocabulário de detectSlotsFromCode (+ 'qualquer' quando o charter
 * não opina). HONESTO: é palavra-chave, não NLU.
 */
function detectSlotsFromCharter(body) {
  const b = body.toLowerCase();
  // separa "manda X" (Goals/Mission) de "proíbe X" (UX Anti-patterns + Non-Goals).
  // antis CONCATENA todas as seções de proibição — o veto a CRUD inline vive em Non-Goals,
  // o veto a tabela vive em UX Anti-patterns; pegar só a 1ª perderia metade.
  const goals = sliceSection(b, ['## goals', '## mission']) + '\n' + sliceSection(b, ['## mission']);
  const antis = sliceSection(b, ['## ux anti-patterns']) + '\n' + sliceSection(b, ['## non-goals']);

  const wantsGrid = /grid view|grid de cards|cards visuais|card-grid|card grid/.test(goals) || /grid only|sempre grid/.test(b);
  const forbidsTable = /tabela ao invés de cards|table view|❌.*tabela|nÃo tabela|não tabela/.test(antis) || /grid only/.test(b);
  const wantsTable = /<datatable|tabela paginável|lista paginável|data table/.test(goals) && !wantsGrid;

  // Slot 6 PT-01 = drawer slide-in pra CRIAR/EDITAR a entidade (não o detail read-only).
  // Quando o charter PROÍBE CRUD inline (Non-Goals), detalhe-via-rota é variante PT-01
  // legítima ("Casos limite") → o charter NÃO manda o slot 6 (= 'qualquer'). Heurística
  // honesta: só exige 'sheet' se Goals menciona drawer/sheet E Non-Goals NÃO veta CRUD inline.
  const forbidsInlineCrud = /❌.*crud inline|crud inline.*(rotas|dedicada)|criar\/editar via rotas/.test(antis);
  const goalsMentionsDrawer = /drawer|sheet|detailsheet|slide-in/.test(goals);

  return {
    1: /pageheader|<pageheader>|header sticky|appshellv2/.test(goals) ? 'pageheader-ou-header' : 'qualquer',
    2: /tabs de categoria|sub-tabs|moduletopnav|sub-views/.test(goals) ? 'subnav-ou-tabs' : 'qualquer',
    3: /search bar|busca|filtros|toolbar/.test(goals) ? 'busca' : 'qualquer',
    4: /bulk action|ações em lote|bulkbar/.test(goals) && !/❌.*bulk|sem bulk/.test(antis) ? 'bulkbar' : 'qualquer',
    5: wantsGrid ? 'grid' : wantsTable ? 'table' : (forbidsTable ? 'grid' : 'qualquer'),
    6: goalsMentionsDrawer && !forbidsInlineCrud ? 'sheet' : 'qualquer',
  };
}

function sliceSection(body, headings) {
  for (const h of headings) {
    const i = body.indexOf(h);
    if (i === -1) continue;
    const rest = body.slice(i + h.length);
    const next = rest.search(/\n##\s/);
    return next === -1 ? rest : rest.slice(0, next);
  }
  return '';
}

/* ──────────────────── comparação por slot e veredito ──────────────────── */

// famílias equivalentes — charter "manda grid" ≈ produção "grid"; "pageheader-ou-header"
// aceita 'pageheader-shared' OU 'header-sticky'. 'qualquer' aceita tudo.
function charterSatisfied(charterWants, prodHas) {
  if (charterWants === 'qualquer') return true;
  if (charterWants === 'pageheader-ou-header') return prodHas === 'pageheader-shared' || prodHas === 'header-sticky';
  if (charterWants === 'subnav-ou-tabs') return prodHas === 'subnav' || prodHas === 'tabs';
  if (charterWants === 'busca') return prodHas === 'busca' || prodHas === 'busca+filtros';
  if (charterWants === 'sheet') return prodHas === 'sheet';
  if (charterWants === 'bulkbar') return prodHas === 'bulkbar';
  // grid / table — match exato de família
  return charterWants === prodHas;
}

// estado por slot
const ESTADO = { CONFORME: 'CONFORME', DECLARADA: 'DIVERGENCIA_DECLARADA', MUDA: 'DIVERGENCIA_MUDA' };
// pior-é-maior pra computar veredito da tela
const SEVERITY = { CONFORME: 0, DIVERGENCIA_DECLARADA: 1, DIVERGENCIA_MUDA: 2 };

/**
 * Reconcilia UMA tela. Retorna a matriz (6 células) + veredito.
 */
function reconcileScreen(charterRel, coworkMap) {
  const { charterAbs, tsxAbs, tsxRel, charterRel: charterRelFull } = pathsForCharter(charterRel);
  const charterMissing = !existsExact(charterAbs);
  const tsxMissing = !existsExact(tsxAbs);

  const charterRaw = charterMissing ? '' : readFileSync(charterAbs, 'utf8');
  const [fm, body] = splitFrontmatter(charterRaw);
  const tsxCode = tsxMissing ? '' : readFileSync(tsxAbs, 'utf8');

  const divergenceDecl = fmScalar(fm, 'divergence_from_blueprint');
  const hasDivergenceDecl = !!divergenceDecl && !/^none/i.test(divergenceDecl);

  const proto = resolvePrototype(charterAbs, fm, body, coworkMap);
  const protoContent = proto.existing.length ? loadPrototypeContent(proto.existing) : '';
  const protoPresent = proto.existing.length > 0;

  const charterWants = detectSlotsFromCharter(body);
  const prodHas = detectSlotsFromCode(tsxCode);
  const protoHas = protoPresent ? detectSlotsFromCode(protoContent) : null;

  const cells = SLOTS.map((slot) => {
    const wants = charterWants[slot.id];
    const prod = prodHas[slot.id];
    const protoVal = protoHas ? protoHas[slot.id] : 'AUSENTE';

    const conforme = charterSatisfied(wants, prod);
    let estado;
    if (conforme) {
      estado = ESTADO.CONFORME;
    } else if (hasDivergenceDecl) {
      estado = ESTADO.DECLARADA;
    } else {
      estado = ESTADO.MUDA;
    }

    let veredito;
    if (estado === ESTADO.CONFORME) veredito = `charter(${wants}) ≡ produção(${prod})`;
    else if (estado === ESTADO.DECLARADA) veredito = `charter(${wants}) ≠ produção(${prod}) — divergência DECLARADA no frontmatter`;
    else veredito = `charter(${wants}) ≠ produção(${prod}) — divergência MUDA (sem declaração) → FALHA`;

    return {
      slot: slot.id,
      nome: slot.nome,
      charter_manda: wants,
      prototipo_mostra: protoVal,
      producao_renderiza: prod,
      fonte: charterMissing ? 'charter AUSENTE' : (tsxMissing ? 'tsx AUSENTE' : 'charter+tsx'),
      estado,
      veredito,
    };
  });

  // veredito da tela = pior slot, nomeado. Se TODOS conformes, não há "pior slot".
  let pior = cells[0];
  for (const c of cells) if (SEVERITY[c.estado] > SEVERITY[pior.estado]) pior = c;
  const tudoConforme = pior.estado === ESTADO.CONFORME;
  const vereditoTela = {
    estado: pior.estado,
    pior_slot: tudoConforme ? '— (todos os 6 slots conformes)' : `Slot ${pior.slot} (${pior.nome})`,
    pior_veredito: tudoConforme
      ? 'charter ≡ produção em todos os 6 slots PT-01'
      : pior.veredito,
  };

  return {
    tela: charterRel,
    charter: charterRelFull,
    producao: tsxRel,
    charter_missing: charterMissing,
    tsx_missing: tsxMissing,
    divergence_declared: hasDivergenceDecl ? divergenceDecl : null,
    prototipo: {
      presente: protoPresent,
      declarados: proto.declared,
      existentes: proto.existing,
      orfaos: proto.missing,
      fonte: proto.source,
    },
    cells,
    veredito: vereditoTela,
  };
}

/* ───────────────────────────── renderização ───────────────────────────── */

function renderText(result) {
  const L = [];
  const badge = (e) => e === ESTADO.CONFORME ? '✓ CONFORME'
    : e === ESTADO.DECLARADA ? '~ DIVERGÊNCIA DECLARADA'
    : '✗ DIVERGÊNCIA MUDA';
  L.push(`Tela: ${result.tela}`);
  L.push(`  charter:   ${result.charter}${result.charter_missing ? '  [AUSENTE]' : ''}`);
  L.push(`  produção:  ${result.producao}${result.tsx_missing ? '  [AUSENTE]' : ''}`);
  L.push(`  protótipo: ${result.prototipo.presente ? result.prototipo.existentes.join('; ') : 'AUSENTE'}`);
  if (result.prototipo.orfaos.length) {
    L.push(`             ⚠️  ponteiro(s) órfão(s): ${result.prototipo.orfaos.join('; ')}`);
  }
  if (result.divergence_declared) L.push(`  divergence_from_blueprint: "${result.divergence_declared}"`);
  L.push('');
  L.push('  Slot                     | charter manda        | protótipo mostra | produção renderiza | estado');
  L.push('  -------------------------|----------------------|------------------|--------------------|-------');
  for (const c of result.cells) {
    const slotLabel = `${c.slot} · ${c.nome}`.padEnd(24);
    L.push(`  ${slotLabel} | ${String(c.charter_manda).padEnd(20)} | ${String(c.prototipo_mostra).padEnd(16)} | ${String(c.producao_renderiza).padEnd(18)} | ${badge(c.estado)}`);
  }
  L.push('');
  L.push(`  VEREDITO DA TELA: ${badge(result.veredito.estado)} — pior slot: ${result.veredito.pior_slot}`);
  L.push(`    ${result.veredito.pior_veredito}`);
  return L.join('\n');
}

function renderMatrixMarkdown(result, now) {
  const badge = (e) => e === ESTADO.CONFORME ? '✓ CONFORME'
    : e === ESTADO.DECLARADA ? '~ DIVERGÊNCIA DECLARADA'
    : '✗ DIVERGÊNCIA MUDA';
  const L = [];
  L.push('---');
  L.push(`tela: ${result.tela}`);
  L.push(`gerado_por: scripts/governance/reconcile-triplet.mjs (v1 heurístico)`);
  L.push(`gerado_em: "${now}"`);
  L.push(`veredito: ${result.veredito.estado}`);
  L.push('---');
  L.push('');
  L.push(`# Matriz de Paridade por Setor — ${result.tela}`);
  L.push('');
  L.push('> **Gate 3-way charter↔protótipo↔produção (PT-01, 6 slots).** O CHARTER é a 1ª coluna');
  L.push('> (a fonte de verdade — o spec). v1 HEURÍSTICO: detecção por assinatura, **não** parsing');
  L.push('> semântico completo nem render/screenshot. Catraca estrutural barata, complementar ao');
  L.push('> gate visual F1.5/F3 (Wagner aprova screenshot). Gerado por `reconcile-triplet.mjs --write`.');
  L.push('');
  L.push('## Fontes');
  L.push('');
  L.push(`- **Charter:** \`${result.charter}\`${result.charter_missing ? ' — ⚠️ AUSENTE' : ''}`);
  L.push(`- **Produção:** \`${result.producao}\`${result.tsx_missing ? ' — ⚠️ AUSENTE' : ''}`);
  if (result.prototipo.presente) {
    L.push(`- **Protótipo (existente):** ${result.prototipo.existentes.map((p) => '`' + p + '`').join(', ')}`);
  } else {
    L.push(`- **Protótipo:** ⚠️ **AUSENTE** — nenhum dos ponteiros declarados existe no filesystem.`);
  }
  if (result.prototipo.orfaos.length) {
    L.push('');
    L.push('### ⚠️ Ponteiros de protótipo órfãos (apontam pro vácuo)');
    L.push('');
    for (const o of result.prototipo.orfaos) L.push(`- \`${o}\``);
  }
  if (result.divergence_declared) {
    L.push('');
    L.push(`- **\`divergence_from_blueprint\`:** "${result.divergence_declared}"`);
  }
  L.push('');
  L.push('## Matriz (6 slots PT-01)');
  L.push('');
  L.push('| Slot | Charter manda | Protótipo mostra | Produção renderiza | Estado |');
  L.push('|---|---|---|---|---|');
  for (const c of result.cells) {
    L.push(`| **${c.slot} · ${c.nome}** | \`${c.charter_manda}\` | \`${c.prototipo_mostra}\` | \`${c.producao_renderiza}\` | ${badge(c.estado)} |`);
  }
  L.push('');
  L.push('## Veredito da tela');
  L.push('');
  L.push(`**${badge(result.veredito.estado)}** — pior slot: ${result.veredito.pior_slot}.`);
  L.push('');
  L.push(`> ${result.veredito.pior_veredito}`);
  L.push('');
  L.push('## Legenda dos estados');
  L.push('');
  L.push('- **CONFORME** — charter ≈ produção no slot.');
  L.push('- **DIVERGÊNCIA DECLARADA** — diferem, mas o frontmatter tem `divergence_from_blueprint` (desvio consciente).');
  L.push('- **DIVERGÊNCIA MUDA** — diferem **sem** declaração → FALHA (`--strict` exit 1).');
  L.push('');
  L.push('## Limites do v1 (honestidade)');
  L.push('');
  L.push('- Detecção por **assinatura** (regex sobre JSX/HTML/charter), não NLU nem AST.');
  L.push('- Slot 5 distingue `table` (`<table`/`<thead`/`<DataTable`) de `grid` (`grid-cols`/`<article`/`card`).');
  L.push('- Charter "manda" inferido de Goals/Non-Goals/UX Anti-patterns por palavra-chave.');
  L.push('- Protótipo AUSENTE não é falha por si — só sinaliza ponteiro órfão. CONFORME/MUDA usa charter×produção.');
  L.push('- **Não** substitui o gate visual (screenshot Wagner) — é catraca estrutural complementar.');
  L.push('');
  return L.join('\n');
}

/* ──────────────────────────────── CLI ──────────────────────────────── */

function parseArgs(argv) {
  const args = { json: false, write: false, strict: false, all: false, auditPointers: false, module: null, tela: null };
  for (const a of argv) {
    if (a === '--json') args.json = true;
    else if (a === '--write') args.write = true;
    else if (a === '--strict') args.strict = true;
    else if (a === '--all') args.all = true;
    else if (a === '--audit-pointers') args.auditPointers = true;
    else if (a.startsWith('--module=')) args.module = a.slice('--module='.length);
    else if (a.startsWith('--tela=')) args.tela = a.slice('--tela='.length);
  }
  return args;
}

function loadCoworkMap() {
  try { return JSON.parse(readFileSync(COWORK_MAP, 'utf8')); } catch { return null; }
}

function writeMatrix(result) {
  // memory/requisitos/<Module>/_telas/<module>-<tela>-setor-matrix.md
  const parts = result.tela.replace(/\.charter\.md$/, '').split('/'); // ex: ["Produto","Index"]
  const moduleName = parts[0];
  const telaSlug = parts.join('-').toLowerCase(); // produto-index
  const dir = join(ROOT, 'memory/requisitos', moduleName, '_telas');
  mkdirSync(dir, { recursive: true });
  const file = join(dir, `${telaSlug}-setor-matrix.md`);
  const now = new Date().toISOString().slice(0, 10);
  writeFileSync(file, renderMatrixMarkdown(result, now).replace(/\r\n/g, '\n'), { encoding: 'utf8' });
  return 'memory/requisitos/' + moduleName + '/_telas/' + `${telaSlug}-setor-matrix.md`;
}

function main() {
  const args = parseArgs(process.argv.slice(2));

  // modo auditoria de ponteiros — delega pro script dedicado
  if (args.auditPointers) {
    const here = dirname(fileURLToPath(import.meta.url));
    const sub = join(here, 'charter-blueprint-pointers.mjs');
    const r = spawnSync('node', [sub, ...(args.json ? ['--json'] : []), ...(args.strict ? ['--strict'] : [])], { stdio: 'inherit' });
    process.exit(r.status ?? 0);
  }

  const coworkMap = loadCoworkMap();

  // resolve quais telas processar
  let targets = [];
  if (args.all) {
    targets = allCharters();
  } else if (args.module && args.tela) {
    targets = [`${args.module}/${args.tela}.charter.md`];
  } else {
    console.error('Uso: --module=<Mod> --tela=<Tela>  |  --all  |  --audit-pointers');
    console.error('Flags: --json --write --strict');
    process.exit(2);
  }

  const results = targets.map((t) => reconcileScreen(t, coworkMap));

  // saída
  if (args.json) {
    console.log(JSON.stringify({ tool: 'reconcile-triplet', version: 'v1-heuristico', advisory: !args.strict, results }, null, 2));
  } else {
    console.log('reconcile-triplet (v1 heurístico — detecção por assinatura, ADVISORY de nascença)');
    console.log('Gate de paridade por setor 3-way charter↔protótipo↔produção (6 slots PT-01).\n');
    for (const r of results) {
      console.log(renderText(r));
      console.log('');
    }
  }

  // --write
  if (args.write) {
    for (const r of results) {
      const path = writeMatrix(r);
      if (!args.json) console.log(`matriz gravada: ${path}`);
    }
  }

  // contabiliza
  const muda = results.filter((r) => r.veredito.estado === ESTADO.MUDA);
  const orfaos = results.filter((r) => r.prototipo.orfaos.length > 0);

  if (!args.json) {
    console.log('─'.repeat(72));
    console.log(`Resumo: ${results.length} tela(s) · ${muda.length} com DIVERGÊNCIA MUDA · ${orfaos.length} com ponteiro de protótipo órfão.`);
  }

  if (muda.length > 0) {
    if (args.strict) {
      if (!args.json) {
        console.error(`\n✗ --strict: ${muda.length} tela(s) com divergência MUDA (charter ≠ produção sem declaração):`);
        for (const r of muda) console.error(`   - ${r.tela}: ${r.veredito.pior_slot} → ${r.veredito.pior_veredito}`);
        console.error('\n  Conserte: alinhe a produção ao charter, OU declare o desvio em `divergence_from_blueprint` (consciente).');
      }
      process.exit(1);
    } else {
      if (!args.json) console.log(`\n⚠️  ADVISORY: ${muda.length} divergência(s) MUDA detectada(s). Use --strict pra falhar o CI.`);
    }
  }
  process.exit(0);
}

main();
