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
//   node scripts/governance/system-map.mjs            # gera memory/reference/PAINEL-SISTEMA.md
//   node scripts/governance/system-map.mjs --stdout    # imprime, não escreve
//   node scripts/governance/system-map.mjs --check      # exit 1 se o .md commitado difere do gerado (CI)

import { readdirSync, readFileSync, existsSync, writeFileSync, statSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const OUT = join(ROOT, 'memory', 'reference', 'PAINEL-SISTEMA.md');
const MODE_STDOUT = process.argv.includes('--stdout');
const MODE_CHECK = process.argv.includes('--check');

// ── helpers ──────────────────────────────────────────────────────────────────
const read = (p) => { try { return readFileSync(p, 'utf8'); } catch { return ''; } };
const ls = (p) => { try { return readdirSync(p); } catch { return []; } };
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

// ── render ────────────────────────────────────────────────────────────────────
function render() {
  const adr = measureAdrs();
  const proib = measureProibicoes();
  const mods = measureModules();
  const sc = measureScorecard();
  const cnt = measureCounts();
  const gates = measureGates();

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
const out = render();
if (MODE_STDOUT) {
  process.stdout.write(out);
} else if (MODE_CHECK) {
  const cur = read(OUT);
  // ignora a linha de data (volátil) na comparação de conteúdo
  const strip = (s) => s.replace(/em \*\*\d{4}-\d{2}-\d{2}\*\*/g, 'em **DATE**').replace(/· \d{4}-\d{2}-\d{2} ·/g, '· DATE ·');
  if (strip(cur) !== strip(out)) {
    console.error('[system-map] PAINEL-SISTEMA.md desatualizado — rode: node scripts/governance/system-map.mjs');
    process.exit(1);
  }
  console.log('[system-map] PAINEL-SISTEMA.md em dia.');
} else {
  writeFileSync(OUT, out);
  console.log(`[system-map] escrito: ${OUT}`);
}
