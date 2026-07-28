#!/usr/bin/env node
// system-map.mjs — a MATRIZ gerada do painel do sistema oimpresso.
//
// POR QUE EXISTE (Wagner 2026-07-12): "é chato ter que ficar lembrando; deveria
// ser a máquina matriz que não quebra e sempre mantém atualizado". O mapa/guia do
// sistema não pode ser um doc mantido À MÃO (drifta → você volta a ter que lembrar)
// nem um presence-gate sobre um campo auto-declarado (proibicoes.md §5, L-24).
//
// A FORMA CERTA é DERIVADA: este script LÊ as fontes canônicas que JÁ são a verdade
// e emite memory/reference/PAINEL-SISTEMA.md como um ÍNDICE que APONTA pros donos —
// nunca recopia o conteúdo deles (isso seria doc paralelo, §5). Editar o .md à mão é
// inútil: a máquina regenera. Frescor é REAL (git-mtime), não campo declarado.
//
// HONESTIDADE (o que dá e o que NÃO dá pra automatizar):
//   - DERIVÁVEL com confiança (estruturado): ADRs + lifecycle/supersedes, ideias
//     mortas (§5), Tier 0 gaps, frescor dos BRIEFINGs, nº de handoffs, scorecard SDD.
//   - CURADO (prosa nos donos): o status/narrativa de cada módulo. O painel LINKA o
//     dono + mostra o frescor; NÃO inventa um "status: X" que a prosa não declara.
//
// Node puro (fs + git via execSync). Sem deps, sem DB, sem PHP. Molde: sdd-scorecard.mjs.
// Uso (na raiz do repo):
//   node scripts/governance/system-map.mjs            # gera PAINEL + PLANTA-IA + onboarding
//   node scripts/governance/system-map.mjs --stdout    # imprime, não escreve
//   node scripts/governance/system-map.mjs --check      # exit 1 se o .md commitado difere do gerado (CI)

import { readdirSync, readFileSync, existsSync, writeFileSync, statSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { pathToFileURL } from 'node:url';

const ROOT = process.cwd();
// só roda a geração/CI quando invocado DIRETO (node system-map.mjs). Importado
// (ex: onboarding-paths-check.mjs reusa deadLinks) NÃO dispara escrita nem process.exit.
const IS_DIRECT_RUN = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
const OUT = join(ROOT, 'memory', 'reference', 'PAINEL-SISTEMA.md');
const OUT_AI = join(ROOT, 'memory', 'reference', 'PLANTA-IA.md');
const MODE_STDOUT = process.argv.includes('--stdout');
const MODE_CHECK = process.argv.includes('--check');

// ── helpers ──────────────────────────────────────────────────────────────────
const read = (p) => { try { return readFileSync(p, 'utf8'); } catch { return ''; } };
const ls = (p) => { try { return readdirSync(p); } catch { return []; } };
function walkRel(relDir) {
  const rows = [];
  const visit = (rel) => {
    let entries = [];
    try { entries = readdirSync(join(ROOT, rel), { withFileTypes: true }); } catch { return; }
    for (const entry of entries) {
      const child = `${rel}/${entry.name}`.replaceAll('\\', '/');
      if (entry.isDirectory()) visit(child);
      else if (entry.isFile()) rows.push(child);
    }
  };
  visit(relDir.replaceAll('\\', '/').replace(/\/$/, ''));
  return rows;
}
function frontmatter(txt) {
  const m = txt.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return {};
  const fm = {};
  for (const line of m[1].split(/\r?\n/)) {
    const kv = line.match(/^([a-z_]+):\s*(.*)$/i);
    if (kv) fm[kv[1]] = kv[2].replace(/^["']|["']$/g, '').trim();
  }
  return fm;
}
// último commit que tocou um path (data ISO curta) — frescor REAL, não declarado
function gitLastDate(relPath) {
  try {
    const out = execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim();
    return out || null;
  } catch { return null; }
}
function daysSince(isoDate) {
  if (!isoDate) return null;
  const then = new Date(isoDate + 'T00:00:00Z').getTime();
  const now = new Date(NOW + 'T00:00:00Z').getTime();
  return Math.round((now - then) / 86400000);
}
// data de geração: passada por env pra ser determinística no CI; senão hoje
const NOW = process.env.SYSTEM_MAP_DATE || new Date().toISOString().slice(0, 10);

// ── fonte 1: ADRs (lifecycle + supersede) ────────────────────────────────────
function measureAdrs() {
  const dir = join(ROOT, 'memory', 'decisions');
  const files = ls(dir).filter((f) => /^\d{4}-.*\.md$/.test(f));
  const byStatus = {};
  const superseded = [];
  for (const f of files) {
    const fm = frontmatter(read(join(dir, f)));
    const st = (fm.status || 'sem-status').toLowerCase();
    byStatus[st] = (byStatus[st] || 0) + 1;
    if (fm.supersedes && fm.supersedes.replace(/[\[\]\s]/g, '')) {
      superseded.push({ novo: f.replace(/\.md$/, ''), supera: fm.supersedes.replace(/[\[\]]/g, '').trim() });
    }
  }
  return { total: files.length, byStatus, superseded };
}

// ── fonte 2: proibicoes.md §5 (ideias mortas) + Tier 0 gaps ───────────────────
function measureProibicoes() {
  const txt = read(join(ROOT, 'memory', 'proibicoes.md'));
  const section = (title) => {
    const i = txt.indexOf(title);
    if (i < 0) return '';
    const rest = txt.slice(i + title.length);
    const j = rest.search(/\n## [^\n]/); // próximo H2
    return j < 0 ? rest : rest.slice(0, j);
  };
  const headings = (block) => (block.match(/^### (.+)$/gm) || []).map((h) => h.replace(/^### /, '').trim());
  const descartadas = headings(section('## Ideias avaliadas e DESCARTADAS'));
  const tier0gaps = headings(section('## Tier 0 gaps catalogados'));
  return { descartadas, tier0gaps };
}

// ── fonte 3: módulos + frescor do BRIEFING (curado; linka o dono) ─────────────
function measureModules() {
  const modDir = join(ROOT, 'Modules');
  const mods = ls(modDir).filter((d) => { try { return statSync(join(modDir, d)).isDirectory(); } catch { return false; } });
  const rows = [];
  for (const m of mods) {
    const brief = `memory/requisitos/${m}/BRIEFING.md`; // forward-slash sempre (link markdown + git)
    const hasBrief = existsSync(join(ROOT, 'memory', 'requisitos', m, 'BRIEFING.md'));
    const date = hasBrief ? gitLastDate(brief) : null;
    rows.push({ modulo: m, brief: hasBrief ? brief : null, atualizado: date, dias: daysSince(date) });
  }
  return rows.sort((a, b) => a.modulo.localeCompare(b.modulo));
}

// ── fonte 4: scorecard SDD (já gerado por sdd-scorecard.mjs) ───────────────────
function measureScorecard() {
  const p = join(ROOT, 'governance', 'sdd-scorecard.json');
  if (!existsSync(p)) return null;
  try {
    const j = JSON.parse(read(p));
    const metrics = j.metrics || j;
    const measured = Object.values(metrics).filter((v) => v && v.status === 'measured').length;
    const total = Object.keys(metrics).length;
    return { measured, total, floor: metrics.full_suite_pass_rate?.value ?? metrics.full_suite?.value ?? null };
  } catch { return null; }
}

// ── fonte 5: contagens (handoffs, sessions recentes) ──────────────────────────
function measureCounts() {
  const handoffs = ls(join(ROOT, 'memory', 'handoffs')).filter((f) => f.endsWith('.md')).length;
  const sessions = ls(join(ROOT, 'memory', 'sessions'))
    .filter((f) => f.endsWith('.md') && /^\d{4}-\d{2}-\d{2}/.test(f)); // só logs datados, sem _TEMPLATE/_INDEX/README
  const recent = sessions.sort().slice(-6).reverse().map((f) => f.replace(/\.md$/, ''));
  return { handoffs, sessions: sessions.length, recent };
}

// ── fonte 6: auditorias & gates (censo + o que bloqueia) ──────────────────────
// Deriva de DUAS fontes JÁ versionadas (offline, determinístico — nada de gh api):
//   - scripts/governance/gates-registry.json    → censo (o que EXISTE, por classe;
//     cobrado por memory-health Check G/M — workflow fora do censo = 🔴).
//   - governance/required-checks-baseline.json  → o que BLOQUEIA merge (required
//     CONGELADO, vigiado por protection-drift.mjs contra demoção invisível · GT-G4).
// O baseline commitado É a fonte-única do "required"; divergência do vivo é sinalizada
// pelo protection-drift, NÃO reconciliada aqui (promoção = PR + ADR 0275 §5).
function measureGates() {
  let registry = {};
  try { registry = JSON.parse(read(join(ROOT, 'scripts', 'governance', 'gates-registry.json'))).workflows || {}; } catch { /* ausente */ }
  const byClass = {};
  for (const [file, w] of Object.entries(registry)) {
    const cls = (w && w.classe) || 'sem-classe';
    (byClass[cls] = byClass[cls] || []).push(file.replace(/\.ya?ml$/, ''));
  }
  for (const c of Object.keys(byClass)) byClass[c].sort();
  let required = [];
  let enforcement = null;
  let capturado = null;
  try {
    const bl = JSON.parse(read(join(ROOT, 'governance', 'required-checks-baseline.json')));
    // contagem canônica do protection-drift.mjs: classic + ruleset (ignorar o ruleset
    // subconta — "Governance Gate" vem de ruleset, não do required_status_checks clássico).
    const classic = (bl.classic_protection && bl.classic_protection.contexts) || [];
    const ruleset = (bl.rulesets && bl.rulesets.contexts) || [];
    required = [...classic, ...ruleset];
    enforcement = bl.enforcement_level || null;
    capturado = (bl._meta && bl._meta.capturado_em) || null;
  } catch { /* ausente */ }
  return { total: Object.keys(registry).length, byClass, required, enforcement, capturado };
}

// ── fonte 7: IA derivada do código (agentes, tools registradas e compose) ─────
// Não consulta runtime e não lê docs narrativos. A página gerada separa explicitamente
// "declarado no repo" de "saudável agora" para não transformar compose em falso uptime.
function composeServices(rel) {
  const rows = [];
  let inside = false;
  for (const line of read(join(ROOT, rel)).split(/\r?\n/)) {
    if (/^services:\s*$/.test(line)) { inside = true; continue; }
    if (inside && /^[^\s#]/.test(line)) break;
    const m = inside ? line.match(/^  ([A-Za-z0-9_-]+):\s*(?:#.*)?$/) : null;
    if (m) rows.push(m[1]);
  }
  return rows;
}

function measureAi() {
  const modulePhp = walkRel('Modules').filter((p) => p.endsWith('.php'));
  const productionPhp = [
    ...modulePhp,
    ...walkRel('app').filter((p) => p.endsWith('.php')),
    ...walkRel('routes').filter((p) => p.endsWith('.php')),
    ...walkRel('config').filter((p) => p.endsWith('.php')),
  ].filter((p) => !p.includes('/Tests/'));
  const productionText = new Map(productionPhp.map((p) => [p, read(join(ROOT, p))]));

  const agentFiles = modulePhp.filter((p) => /\/Ai\/Agents\/[^/]+Agent\.php$/.test(p));
  const agents = agentFiles.map((file) => {
    const source = read(join(ROOT, file));
    const cls = source.match(/\bclass\s+([A-Za-z0-9_]+Agent)\s+implements\s+Agent\b/);
    if (!cls) throw new Error(`[system-map] agente sem contrato "implements Agent": ${file}`);
    const module = file.split('/')[1];
    const refRe = new RegExp(`\\b${cls[1]}\\b`);
    const references = [...productionText].filter(([candidate, txt]) => candidate !== file && refRe.test(txt)).map(([candidate]) => candidate);
    return { module, name: cls[1], file, references };
  }).sort((a, b) => a.module.localeCompare(b.module) || a.name.localeCompare(b.name));

  const agentNames = new Set();
  for (const agent of agents) {
    if (agentNames.has(agent.name)) throw new Error(`[system-map] nome de agente duplicado: ${agent.name}`);
    agentNames.add(agent.name);
  }

  const serverPath = 'Modules/Jana/Mcp/OimpressoMcpServer.php';
  const server = read(join(ROOT, serverPath));
  const toolsBlock = server.match(/protected array \$tools = \[([\s\S]*?)\n\s*\];/);
  if (!toolsBlock) throw new Error(`[system-map] bloco $tools não encontrado: ${serverPath}`);
  const tools = [];
  for (const line of toolsBlock[1].split(/\r?\n/)) {
    const m = line.match(/^\s*(?:(?:\\Modules\\([A-Za-z0-9]+)\\Mcp\\Tools\\)|Tools\\)([A-Za-z0-9_]+Tool)::class,/);
    if (!m) continue;
    const module = m[1] || 'Jana';
    const name = m[2];
    const file = `Modules/${module}/Mcp/Tools/${name}.php`;
    if (!existsSync(join(ROOT, file))) throw new Error(`[system-map] tool MCP registrada sem arquivo: ${file}`);
    tools.push({ module, name, file });
  }
  const toolNames = new Set();
  for (const tool of tools) {
    const identity = `${tool.module}\\${tool.name}`;
    if (toolNames.has(identity)) throw new Error(`[system-map] tool MCP duplicada no registro: ${identity}`);
    toolNames.add(identity);
  }

  const dataTools = walkRel('Modules/Jana/Ai/Tools/BriefDiario')
    .filter((p) => p.endsWith('Tool.php'))
    .map((file) => ({ file, name: file.split('/').pop().replace(/\.php$/, '') }))
    .sort((a, b) => a.name.localeCompare(b.name));

  const compose = walkRel('docker')
    .filter((p) => p.endsWith('/docker-compose.yml'))
    .map((file) => ({ file, services: composeServices(file) }))
    .sort((a, b) => a.file.localeCompare(b.file));

  const engineeringAgents = ls(join(ROOT, '.claude', 'agents')).filter((f) => f.endsWith('.md')).sort();
  return { agents, tools, dataTools, compose, engineeringAgents, serverPath };
}

// ── render ────────────────────────────────────────────────────────────────────
function render(data) {
  const { adr, proib, mods, sc, cnt, gates, ai } = data;

  const L = [];
  L.push('---');
  L.push('name: PAINEL-SISTEMA — índice gerado do estado do sistema oimpresso');
  L.push('description: MATRIZ gerada por scripts/governance/system-map.mjs. NÃO editar à mão (regenera). Índice que aponta pros donos canônicos + fatos deriváveis + frescor real.');
  L.push('type: reference');
  L.push('authority: generated');
  L.push('lifecycle: ativo');
  L.push('---');
  L.push('');
  L.push('# 🗺️ PAINEL-SISTEMA — estado do oimpresso');
  L.push('');
  L.push(`> ⚙️ **Gerado por máquina** (\`system-map.mjs\`) em **${NOW}**. NÃO edite à mão — a próxima geração sobrescreve.`);
  L.push('> Regenerar: `node scripts/governance/system-map.mjs`. Este é um **índice que aponta pros donos canônicos**, não uma cópia deles.');
  L.push('> Views humanas (mapa 🗺️ / guia 🧭 em claude.ai) derivam DESTES dados.');
  L.push('');

  // Módulos
  L.push('## Módulos & verticais');
  L.push('');
  L.push('> Status/narrativa vivem no BRIEFING de cada módulo (curado). Aqui: existência + **último toque real** (git). Data absoluta (determinística — sem churn diário); a leitura de "está velho?" é do olho: um BRIEFING de meses atrás é candidato a re-destilar.');
  L.push('');
  L.push('| Módulo | BRIEFING | Último toque |');
  L.push('|---|---|---|');
  for (const m of mods) {
    const link = m.brief ? `[BRIEFING](../${m.brief.replace('memory/', '')})` : '_sem BRIEFING_';
    L.push(`| ${m.modulo} | ${link} | ${m.atualizado || '—'} |`);
  }
  L.push('');

  // IA — resumo derivado; o detalhe vive na página gerada pela MESMA matriz.
  const agentModules = new Set(ai.agents.map((a) => a.module)).size;
  const orphanAgents = ai.agents.filter((a) => a.references.length === 0).length;
  L.push('## IA & automação');
  L.push('');
  L.push(`- **${ai.agents.length} agentes PHP** em **${agentModules} módulos** · **${orphanAgents} sem referência de produção**.`);
  L.push(`- **${ai.tools.length} tools registradas** no único servidor MCP · **${ai.dataTools.length} tools SQL** do Brief Diário.`);
  L.push(`- **${ai.engineeringAgents.length} agentes de engenharia** em \`.claude/agents/\` — catálogo diferente dos agentes PHP.`);
  L.push(`- Planta completa, fontes e probes: [\`PLANTA-IA.md\`](PLANTA-IA.md) — gerada por esta mesma máquina.`);
  L.push('');

  // SDD scorecard
  L.push('## Programa SDD (governança)');
  L.push('');
  if (sc) {
    L.push(`- Scorecard: **${sc.measured}/${sc.total}** métricas medidas${sc.floor != null ? ` · floor full-suite = **${sc.floor}**` : ''}.`);
    L.push('- Fonte viva: `governance/sdd-scorecard.json` (gerado por `sdd-scorecard.mjs`). Avaliação adversarial: `/sdd-avaliar`.');
  } else {
    L.push('- `governance/sdd-scorecard.json` ausente — rodar `node scripts/governance/sdd-scorecard.mjs`.');
  }
  L.push('- Roadmap dono: [`memory/requisitos/_Governanca/roadmap/_ROADMAP.md`](../requisitos/_Governanca/roadmap/_ROADMAP.md).');
  L.push('');

  // Auditorias & Gates
  L.push('## Auditorias & Gates');
  L.push('');
  L.push('> Fontes versionadas (offline, sem `gh api`): censo [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o que **existe**) + [`required-checks-baseline.json`](../../governance/required-checks-baseline.json) (o que **bloqueia**, congelado). Anti-demoção invisível: `protection-drift.mjs` (GT-G4). As catracas mordem: `gate-selftest` (GT-G6). Censo cobrado por `memory-health` Check G/M.');
  L.push('');
  L.push(`### Bloqueiam merge — ${gates.required.length} required${gates.enforcement ? ` (enforcement: ${gates.enforcement})` : ''}`);
  if (gates.capturado) L.push(`> Congelados no baseline (captura ${gates.capturado}). Divergência do vivo é sinalizada pelo \`protection-drift\`, não reconciliada aqui.`);
  L.push('');
  for (const c of gates.required) L.push(`- ${c}`);
  L.push('');
  L.push(`### Censo — ${gates.total} workflows por classe`);
  L.push('');
  L.push('> Lista completa + propósito de cada um: [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o dono). Aqui: contagem + exemplos.');
  L.push('');
  L.push('| Classe | Qtd | Exemplos |');
  L.push('|---|---|---|');
  const classLabel = { gate: 'gate (bloqueia/valida PR)', meta: 'meta (testa os gates)', automacao: 'automacao (cron/dispatch)', deploy: 'deploy (entrega)' };
  const order = ['gate', 'meta', 'automacao', 'deploy'];
  const classes = [...order.filter((c) => gates.byClass[c]), ...Object.keys(gates.byClass).filter((c) => !order.includes(c)).sort()];
  for (const cls of classes) {
    const files = gates.byClass[cls] || [];
    const ex = files.slice(0, 4).join(', ') + (files.length > 4 ? ', …' : '');
    L.push(`| ${classLabel[cls] || cls} | ${files.length} | ${ex} |`);
  }
  L.push('');

  // ADRs
  L.push('## Decisões (ADRs)');
  L.push('');
  L.push(`- **${adr.total}** ADRs no total. Índice gerado: [\`_INDEX-GENERATED.md\`](../decisions/_INDEX-GENERATED.md) · lifecycle: [\`_INDEX-LIFECYCLE.md\`](../decisions/_INDEX-LIFECYCLE.md).`);
  const st = Object.entries(adr.byStatus).sort((a, b) => b[1] - a[1]).map(([k, v]) => `${k}: ${v}`).join(' · ');
  if (st) L.push(`- Por status: ${st}.`);
  L.push(`- **${adr.superseded.length}** reversões de rota (ADR com \`supersedes:\`).`);
  L.push('');

  // Ideias mortas
  L.push('## Ideias avaliadas e ABANDONADAS (§5 — não re-propor)');
  L.push('');
  L.push(`> Dono canônico: [\`memory/proibicoes.md §5\`](../proibicoes.md). ${proib.descartadas.length} entradas.`);
  L.push('');
  for (const d of proib.descartadas) L.push(`- ~~${d}~~`);
  L.push('');

  // Tier 0 gaps
  if (proib.tier0gaps.length) {
    L.push('## Tier 0 gaps (esperam decisão/desbloqueio)');
    L.push('');
    for (const g of proib.tier0gaps) L.push(`- ⛔ ${g}`);
    L.push('');
  }

  // Rastro
  L.push('## Rastro');
  L.push('');
  L.push(`- **${cnt.handoffs}** handoffs · **${cnt.sessions}** session logs. Índice: [\`memory/08-handoff.md\`](../08-handoff.md).`);
  L.push('- Sessions recentes:');
  for (const s of cnt.recent) L.push(`  - \`${s}\``);
  L.push('');
  L.push('---');
  L.push(`_Gerado por \`scripts/governance/system-map.mjs\` · ${NOW} · deriva das fontes canônicas, não as substitui._`);
  L.push('');
  return L.join('\n');
}

// ── main ──────────────────────────────────────────────────────────────────────
// ── ONBOARDING-AGENTE-GERADO.md — artefato da rota de agentes. A porta global é
// README.md; este arquivo só oferece um prompt estável + ponteiros vivos. ──
// PLANTA-IA.md — página documental gerada pela mesma máquina matriz.
function renderAiPlant(data) {
  const { ai, gates } = data;
  const groupBy = (rows, key) => rows.reduce((acc, row) => {
    const value = row[key];
    (acc[value] = acc[value] || []).push(row);
    return acc;
  }, {});
  const agentsByModule = groupBy(ai.agents, 'module');
  const toolsByModule = groupBy(ai.tools, 'module');
  const orphanAgents = ai.agents.filter((a) => a.references.length === 0);
  const composeServicesTotal = ai.compose.reduce((sum, row) => sum + row.services.length, 0);

  const L = [];
  L.push('---');
  L.push('name: PLANTA-IA — topologia e inventário gerados');
  L.push('description: Página documental da IA do oimpresso, derivada do código por system-map.mjs. Separa inventário versionado de estado vivo.');
  L.push('type: reference');
  L.push('authority: generated');
  L.push('lifecycle: ativo');
  L.push('---');
  L.push('');
  L.push('# Onde a IA vive no oimpresso');
  L.push('');
  L.push(`> ⚙️ **Gerado por \`scripts/governance/system-map.mjs\` em ${NOW}.** NÃO edite à mão.`);
  L.push('> Esta página deriva o que o repositório consegue provar. Saúde de máquina é verificada por probe — compose existente não significa container vivo.');
  L.push('> Resumo do sistema inteiro: [`PAINEL-SISTEMA.md`](PAINEL-SISTEMA.md). Decisões donas: [ADR 0035](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0048](../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) e [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md).');
  L.push('');

  L.push('## Planta lógica');
  L.push('');
  L.push('> “Hostinger” e “CT 100” são **zonas operacionais**, não promessa de contagem física. O MySQL gerenciado da Hostinger pode residir em host distinto do web shared.');
  L.push('');
  L.push('```mermaid');
  L.push('flowchart LR');
  L.push('  subgraph HOST["Hostinger · plano gerenciado"]');
  L.push('    WEB["ERP web · PHP-FPM · filas curtas"]');
  L.push('    PRODDB[("MySQL de produção")]');
  L.push('    WEB --> PRODDB');
  L.push('  end');
  L.push('  subgraph CT["CT 100 · containers"]');
  L.push('    MCP["Servidor MCP"]');
  L.push('    STAGE["Staging"]');
  L.push('    STAGEDB[("Banco isolado de staging")]');
  L.push('    SEARCH["Meilisearch + Ollama"]');
  L.push('    OBS["Langfuse stack"]');
  L.push('    STAGE --> STAGEDB');
  L.push('  end');
  L.push('  MODEL["OpenAI · provedor externo"]');
  L.push('  CLIENT["Clientes MCP · Claude/Codex"]');
  L.push('  WEB --> MODEL');
  L.push('  WEB --> SEARCH');
  L.push('  WEB --> OBS');
  L.push('  MCP --> PRODDB');
  L.push('  MCP --> SEARCH');
  L.push('  MCP --> CLIENT');
  L.push('  STAGE --> SEARCH');
  L.push('  STAGE --> OBS');
  L.push('```');
  L.push('');
  L.push('O desenho mostra **quem chama quem**. Ele não multiplica um serviço compartilhado por consumidor: produção e MCP apontam para o mesmo banco de produção; Meilisearch, Ollama e Langfuse aparecem uma vez.');
  L.push('');

  L.push('## Inventário derivado do código');
  L.push('');
  L.push('| Medida | Valor derivado | Fonte dona |');
  L.push('|---|---:|---|');
  L.push(`| Agentes PHP de produto | **${ai.agents.length}** | \`Modules/*/Ai/Agents/*Agent.php\` + contrato \`implements Agent\` |`);
  L.push(`| Módulos com agentes PHP | **${Object.keys(agentsByModule).length}** | árvore \`Modules/\` |`);
  L.push(`| Agentes sem referência de produção | **${orphanAgents.length}** | referências PHP fora de \`Tests/\` |`);
  L.push(`| Tools registradas no MCP | **${ai.tools.length}** | [\`OimpressoMcpServer.php\`](../../${ai.serverPath}) |`);
  L.push(`| Tools SQL do Brief Diário | **${ai.dataTools.length}** | \`Modules/Jana/Ai/Tools/BriefDiario/\` |`);
  L.push(`| Agentes de engenharia | **${ai.engineeringAgents.length}** | \`.claude/agents/*.md\` — outra camada, não runtime PHP |`);
  L.push(`| Serviços em compose versionado | **${composeServicesTotal}** | \`docker/**/docker-compose.yml\` — declaração, não uptime |`);
  L.push(`| Checks no baseline versionado de merge | **${gates.required.length}** | \`governance/required-checks-baseline.json\` — o probe vivo é \`protection-drift.mjs\` |`);
  L.push('');

  L.push('## Agentes PHP por módulo');
  L.push('');
  L.push('| Módulo | Qtd. | Classes |');
  L.push('|---|---:|---|');
  for (const [module, rows] of Object.entries(agentsByModule).sort(([a], [b]) => a.localeCompare(b))) {
    const names = rows.map((row) => `[${row.name}](../../${row.file})`).join(' · ');
    L.push(`| ${module} | ${rows.length} | ${names} |`);
  }
  L.push('');
  if (orphanAgents.length) {
    L.push(`> ⚠️ Sem referência de produção: ${orphanAgents.map((a) => `\`${a.module}/${a.name}\``).join(' · ')}.`);
  } else {
    L.push('> ✅ Nenhuma classe de agente ficou sem referência PHP de produção.');
  }
  L.push('');

  L.push('## Tools do servidor MCP');
  L.push('');
  L.push('| Módulo dono | Qtd. | Registro |');
  L.push('|---|---:|---|');
  for (const [module, rows] of Object.entries(toolsByModule).sort(([a], [b]) => a.localeCompare(b))) {
    L.push(`| ${module} | ${rows.length} | ${rows.map((row) => row.name).join(' · ')} |`);
  }
  L.push('');
  L.push(`As **${ai.tools.length}** entradas acima são classes efetivamente registradas no array \`$tools\`. Uma classe \`*Tool.php\` solta não entra na contagem.`);
  L.push('');

  L.push('## Stacks Docker versionados');
  L.push('');
  L.push('> Esta tabela responde “o que o repo declara?”. Para responder “o que está vivo agora?”, use os probes da seção seguinte.');
  L.push('');
  L.push('| Compose | Serviços declarados | Qtd. |');
  L.push('|---|---|---:|');
  for (const row of ai.compose) {
    L.push(`| [\`${row.file}\`](../../${row.file}) | ${row.services.length ? row.services.join(' · ') : '_nenhum detectado_'} | ${row.services.length} |`);
  }
  L.push('');

  L.push('## Estado vivo: medir, não copiar');
  L.push('');
  L.push('| Superfície | Probe/recibo | O que prova |');
  L.push('|---|---|---|');
  L.push('| Web live | [`https://oimpresso.com/login`](https://oimpresso.com/login) | aplicação responde agora |');
  L.push('| MCP | [`https://mcp.oimpresso.com/api/mcp/health`](https://mcp.oimpresso.com/api/mcp/health) | servidor MCP responde agora |');
  L.push('| Staging | [`https://staging.oimpresso.com/login`](https://staging.oimpresso.com/login) | runtime de homologação responde agora |');
  L.push('| Langfuse | [`https://langfuse.oimpresso.com/api/public/health`](https://langfuse.oimpresso.com/api/public/health) | observabilidade responde agora |');
  L.push('| CT 100 | `tailscale ssh root@ct100-mcp "docker ps"` | containers realmente em execução |');
  L.push('| Hostinger | `php artisan schedule:list` + processo `queue:work` | cron e filas realmente carregados |');
  L.push('');
  L.push('A página **não grava “verde”** no Markdown: esse estado venceria no minuto seguinte. Ela preserva o probe reproduzível.');
  L.push('');

  L.push('## Como esta página continua viva');
  L.push('');
  L.push('1. `system-map.mjs` varre agentes, registro MCP, tools de dados e arquivos compose.');
  L.push('2. `node scripts/governance/system-map.mjs --check` compara o Markdown commitado com a geração atual.');
  L.push('3. [`.github/workflows/system-map.yml`](../../.github/workflows/system-map.yml) roda no PR quando uma fonte muda e também diariamente.');
  L.push('4. O job diário regenera e abre auto-PR; ninguém precisa editar contagem à mão.');
  L.push('5. Fatos de runtime ficam como probes. Se for necessário histórico de uptime, o dono deve ser monitoramento/telemetria — nunca esta página.');
  L.push('');
  L.push('### O que ainda é humano');
  L.push('');
  L.push('- explicar **por que** as camadas existem;');
  L.push('- decidir se um serviço em standby deve ser ativado ou removido;');
  L.push('- registrar mudança arquitetural em ADR;');
  L.push('- interpretar falha de probe e impacto no negócio.');
  L.push('');
  L.push('---');
  L.push(`_Gerado por \`scripts/governance/system-map.mjs\` · ${NOW} · referência derivada, não substitui os donos canônicos._`);
  L.push('');
  return L.join('\n');
}

const OUT_ONBOARDING = join(ROOT, 'memory', 'reference', 'ONBOARDING-AGENTE-GERADO.md');
function renderOnboardingAgent(data) {
  const { adr, mods } = data;
  const L = [];
  L.push('---');
  L.push('name: Onboarding de agente — prompt gerado');
  L.push('description: Artefato auxiliar da rota de agentes declarada no README.md. GERADO por system-map.mjs — prompt estável + ponteiros pras fontes vivas.');
  L.push('type: generated-prompt');
  L.push('authority: generated');
  L.push('lifecycle: ativo');
  L.push('---');
  L.push('');
  L.push('# Prompt gerado de onboarding para agente');
  L.push('');
  L.push('<!-- documentation-entrypoint: tool:agent-onboarding -->');
  L.push('');
  L.push('> ⚙️ **Gerado por `system-map.mjs`.** NÃO editar à mão. Este arquivo não é outra porta global: a entrada única continua no [`README.md`](../../README.md), rota “Trabalhar com um agente de IA”.');
  L.push('');
  L.push('## Pra uma IA nova entender tudo — cole numa sessão nova');
  L.push('');
  L.push('```');
  L.push('Você vai trabalhar no oimpresso, meu ERP. Antes de qualquer coisa:');
  L.push('1. Rode a tool `brief-fetch` (estado consolidado do projeto). SE você NÃO tiver');
  L.push('   o servidor MCP conectado, PULE e leia em vez disso (fallback): o roadmap');
  L.push('   `memory/requisitos/_Governanca/roadmap/_ROADMAP.md` + o session log DATADO mais');
  L.push('   recente (arquivo `YYYY-MM-DD-*.md` em `memory/sessions/` — IGNORE README/_INDEX/');
  L.push('   _TEMPLATE, que um `ls -t` cru joga por cima). Nunca invente o retorno da tool.');
  L.push('2. As regras já carregaram via CLAUDE.md — respeite-as (multi-tenant, PT-BR,');
  L.push('   teste só no CT 100, aprovação humana antes de merge).');
  L.push('3. Leia `memory/reference/PAINEL-SISTEMA.md` — o índice GERADO do sistema');
  L.push('   inteiro (módulos + frescor, ADRs, ideias descartadas, o que está em aberto).');
  L.push('4. Pra o histórico do que já foi tentado e por que caiu, leia');
  L.push('   `memory/proibicoes.md` (seção "Ideias avaliadas e DESCARTADAS").');
  L.push('');
  L.push('Agora, ANTES de começar, me diga em 5 bullets o que você entendeu: o que é,');
  L.push('como roda, quem é o cliente, o que está em voo, e uma regra que nunca pode');
  L.push('quebrar. Se algum bullet estiver vago, releia a fonte.');
  L.push('```');
  L.push('');
  L.push('> O último passo força a IA a **provar** que entendeu, em vez de fingir.');
  L.push('');
  L.push('## Pra auditar / revisar o sistema (2 comandos bastam)');
  L.push('');
  L.push('- **`/sdd-avaliar`** — auditoria geral do processo (7 especialistas adversariais checam o estado REAL, nota 0-100 + riscos). Responde "o sistema está honesto?".');
  L.push('- **`/avaliar-modulo <X>`** — nota de um módulo em 9 dimensões + gaps. Responde "este módulo está bom?".');
  L.push('- _Mais fundo:_ `/audit-and-fix <tema>` · `capterra-senior` (vs mercado) · `design-arte` (UX) · `php artisan jana:health-check` (saúde diária).');
  L.push('');
  L.push('## Estado vivo (não apodrece — é derivado)');
  L.push('');
  L.push(`- **${mods.length} módulos** · **${adr.total} ADRs** — detalhe + frescor no [PAINEL-SISTEMA.md](PAINEL-SISTEMA.md) (gerado junto deste).`);
  L.push('- Estado consolidado agora: rode `brief-fetch`.');
  L.push('- Regras Tier 0 + o que já falhou: [proibicoes.md](../proibicoes.md).');
  L.push('- Como o sistema é construído: `CLAUDE.md` (carrega automático) + `memory/why-oimpresso.md` / `what-oimpresso.md` / `how-trabalhar.md`.');
  L.push('- Onde o CÓDIGO mora (pra mexer, não só entender): `Modules/<Vertical>` (features por vertical) · `app/Domain/Fsm` (máquina de estados de vendas/OS) · `resources/js/Pages/<Mod>/` (telas Inertia/React). Antes de criar/alterar, ABRA `Modules/Jana` · `Modules/Repair` e imite o padrão (ADR 0011). Pra criar módulo do zero, o passo-a-passo está em `memory/requisitos/Infra/RUNBOOK-criar-modulo.md`.');
  L.push('');
  return L.join('\n');
}

// ── REGRA MECÂNICA (Wagner 2026-07-12 "os caminhos indicados são errados, sem fonte,
// sem teste — isso deveria ser uma regra clara"): o gerador NUNCA emite path que não
// resolve. Varre os markdown-links de cada saída e existsSync cada um relativo ao dir
// do arquivo. Qualquer path morto = FALHA (exit 1), NÃO escreve doc com link quebrado. ──
// dirs de repo reconhecidos — um `code` inline que começa com um deles E tem `/` é
// tratado como PATH (relativo à RAIZ do repo) e verificado. Fecha o furo que deixou
// `Modules/Project` (inexistente) passar — antes só links markdown eram checados.
const REPO_DIRS = /^(Modules|app|resources|scripts|governance|database|tests|config|routes|bootstrap|\.github|\.claude|memory)\/\S/;
export function deadLinks(md, outPath) {
  const base = dirname(outPath);
  const dead = [];
  // 1) links markdown ](path) — relativos ao dir do doc
  const reLink = /\]\(([^)]+)\)/g; let m;
  while ((m = reLink.exec(md)) !== null) {
    const link = m[1].split('#')[0].trim();
    if (!link || /^(https?:|mailto:)/.test(link)) continue; // externos não se verificam no disco
    if (!existsSync(join(base, link))) dead.push(link);
  }
  // 2) paths de repo em `code` inline — relativos à RAIZ do repo.
  // Remove blocos cercados ``` … ``` ANTES de casar inline: os backticks internos
  // do bloco bagunçam o pareamento do regex e engoliriam paths inline reais depois
  // dele (Modules/Jana, app/Domain/Fsm ficavam SEM verificação — furo silencioso).
  const noFences = md.replace(/```[\s\S]*?```/g, '');
  const reCode = /`([^`]+)`/g;
  while ((m = reCode.exec(noFences)) !== null) {
    const t = m[1].trim();
    if (!REPO_DIRS.test(t)) continue;       // só o que parece path de repo
    if (/\s/.test(t)) continue;             // tem espaço = "path + rótulo" (ex `proibicoes.md §5`), não path puro
    if (/[<>*]/.test(t)) continue;          // templates/globs (Modules/<Vertical>, Pages/<Mod>/) não são paths reais
    const p = t.replace(/\/$/, '');         // tira barra final de dir
    if (!existsSync(join(ROOT, p))) dead.push(`(inline) ${t}`);
  }
  return dead;
}
export function assertLinksLive(pairs) {
  const problems = pairs.flatMap(([md, out]) => deadLinks(md, out).map((l) => `${out}: ${l}`));
  if (problems.length) {
    console.error('[system-map] PATH MORTO — o gerador se recusa a emitir link quebrado (regra):');
    problems.forEach((p) => console.error('  ✗ ' + p));
    process.exit(1);
  }
}

if (IS_DIRECT_RUN) {
  const data = {
    adr: measureAdrs(), proib: measureProibicoes(), mods: measureModules(),
    sc: measureScorecard(), cnt: measureCounts(), gates: measureGates(), ai: measureAi(),
  };
  const outPainel = render(data);
  const outAi = renderAiPlant(data);
  const outOnboarding = renderOnboardingAgent(data);
  // REGRA: prova que TODO path emitido resolve, antes de qualquer coisa (fail-closed)
  assertLinksLive([[outPainel, OUT], [outAi, OUT_AI], [outOnboarding, OUT_ONBOARDING]]);
  // ignora a linha de data (volátil) do PAINEL na comparação de conteúdo
  const strip = (s) => s
    .replace(/em \*\*\d{4}-\d{2}-\d{2}\*\*/g, 'em **DATE**')
    .replace(/em \d{4}-\d{2}-\d{2}/g, 'em DATE')
    .replace(/· \d{4}-\d{2}-\d{2} ·/g, '· DATE ·');
  if (MODE_STDOUT) {
    process.stdout.write(outPainel + '\n\n' + outAi + '\n\n' + outOnboarding);
  } else if (MODE_CHECK) {
    let stale = false;
    if (strip(read(OUT)) !== strip(outPainel)) { console.error('[system-map] PAINEL-SISTEMA.md desatualizado'); stale = true; }
    if (strip(read(OUT_AI)) !== strip(outAi)) { console.error('[system-map] PLANTA-IA.md desatualizado'); stale = true; }
    if (strip(read(OUT_ONBOARDING)) !== strip(outOnboarding)) { console.error('[system-map] ONBOARDING-AGENTE-GERADO.md desatualizado'); stale = true; }
    if (stale) { console.error('  → rode: node scripts/governance/system-map.mjs'); process.exit(1); }
    console.log('[system-map] PAINEL + PLANTA-IA + ONBOARDING-AGENTE-GERADO em dia.');
  } else {
    writeFileSync(OUT, outPainel);
    writeFileSync(OUT_AI, outAi);
    writeFileSync(OUT_ONBOARDING, outOnboarding);
    console.log(`[system-map] escrito: ${OUT} + ${OUT_AI} + ${OUT_ONBOARDING}`);
  }
}
