#!/usr/bin/env node
// @ts-check
/**
 * tasks-index-generate.mjs — GERADOR determinístico de BACKLOG + CHANGELOG indexados.
 *
 * Mesmo princípio do adr-index-generate.mjs (Log4brains): índice GERADO da fonte de
 * verdade, nunca mantido à mão. Fonte = as US-* dos SPEC.md (canon dos US-XXX-NNN por
 * ADR 0070; o mcp_tasks é o cache de estado vivo). Lê todo memory/requisitos/<Mod>/SPEC.md,
 * extrai cada bloco `### US-XXX-NNN · título` + a linha de metadados `> owner: … · status:
 * … · priority: … · done_at: … · commit: …`, e emite:
 *
 *   - memory/requisitos/_BACKLOG-GENERATED.md   — US ABERTAS (status ∉ done|cancelled),
 *       indexadas por módulo → status → owner. É a "lista indexada de tarefas" por
 *       módulo/identidade.
 *   - CHANGELOG gerado: DESLIGADO por ora (veredito adversarial 2026-06-16). done_at/commit
 *       só existem em ~1/44 SPECs → sairia stale/vazio. O changelog REAL são os
 *       Modules/<X>/CHANGELOG.md curados. Reabilitar quando done_at for materializado.
 *
 * Determinístico (mesmo input → mesmo output). Sem DB, sem rede — git-native, reproduzível.
 *
 * Uso:
 *   node scripts/governance/tasks-index-generate.mjs           (dry: imprime resumo)
 *   node scripts/governance/tasks-index-generate.mjs --write   (grava os 2 _*-GENERATED.md)
 *   node scripts/governance/tasks-index-generate.mjs --check   (CI: exit 1 se gerado ≠ commitado = drift)
 *
 * Refs: ADR 0070 (Jira-style task mgmt; SPEC=canon US, mcp_tasks=estado) · ADR 0256
 *       (survival, fonte única gerada) · adr-index-generate.mjs (mesmo padrão).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const SPEC_DIR = 'memory/requisitos';
const OUT_BACKLOG = 'memory/requisitos/_BACKLOG-GENERATED.md';
const OUT_CHANGELOG = 'memory/requisitos/_CHANGELOG-GENERATED.md';
const MODE = process.argv.includes('--write') ? 'write' : process.argv.includes('--check') ? 'check' : 'dry';

const DONE = new Set(['done', 'cancelled', 'canceled']);
const CLOSED_OK = 'done';

/** Lê a linha `> key: v · key: v` que segue uma US e devolve o mapa de metadados. */
function parseMeta(line) {
  /** @type {Record<string,string>} */
  const meta = {};
  const body = line.replace(/^>\s*/, '');
  for (const part of body.split('·')) {
    const m = part.match(/^\s*([a-z_]+)\s*:\s*(.+?)\s*$/i);
    if (m) meta[m[1].toLowerCase()] = m[2].trim();
  }
  return meta;
}

/** @type {Array<{module:string,id:string,title:string,status:string,owner:string,priority:string,sprint:string,done_at:string,commit:string,progress:string}>} */
const tasks = [];

for (const mod of readdirSync(join(ROOT, SPEC_DIR)).sort()) {
  const specPath = join(ROOT, SPEC_DIR, mod, 'SPEC.md');
  if (!existsSync(specPath) || !statSync(specPath).isFile()) continue;
  const lines = readFileSync(specPath, 'utf8').split('\n');

  for (let i = 0; i < lines.length; i++) {
    // Heading de US: "### US-XXX-NNN · título"  (aceita ·, : ou — como separador)
    const h = lines[i].match(/^#{2,4}\s+(US-[A-Za-z0-9]+-\d+)\b\s*[·:—-]?\s*(.*)$/);
    if (!h) continue;
    const id = h[1];
    const title = (h[2] || '').trim() || '(sem título)';

    // Procura a linha de metadados `> …` nas próximas 4 linhas (pula vazias).
    let meta = {};
    for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
      if (lines[j].trim() === '') continue;
      if (lines[j].startsWith('>')) meta = parseMeta(lines[j]);
      break; // primeira não-vazia decide
    }

    tasks.push({
      module: mod,
      id,
      title,
      status: (meta.status || 'todo').toLowerCase(),
      owner: meta.owner || '—',
      priority: (meta.priority || '').toLowerCase(),
      sprint: meta.sprint || '',
      done_at: meta.done_at || '',
      commit: meta.commit || '',
      progress: meta.progress || '',
    });
  }
}

const open = tasks.filter((t) => !DONE.has(t.status));
const done = tasks.filter((t) => t.status === CLOSED_OK);

// ─── BACKLOG (aberto) — por módulo → status → owner ─────────────────────────────
const STATUS_ORDER = ['blocked', 'doing', 'review', 'todo', 'backlog'];
function statusRank(s) {
  const i = STATUS_ORDER.indexOf(s);
  return i === -1 ? STATUS_ORDER.length : i;
}
function prioRank(p) {
  const m = p.match(/p(\d)/);
  return m ? Number(m[1]) : 9;
}

function renderBacklog() {
  const byModule = new Map();
  for (const t of open) {
    if (!byModule.has(t.module)) byModule.set(t.module, []);
    byModule.get(t.module).push(t);
  }
  const modules = [...byModule.keys()].sort((a, b) => byModule.get(b).length - byModule.get(a).length || a.localeCompare(b));

  let out = '<!-- GERADO por scripts/governance/tasks-index-generate.mjs — NÃO editar à mão (regenera). -->\n';
  out += '# Backlog indexado (gerado)\n\n';
  out += `> Fonte: as US-* dos \`memory/requisitos/<Mod>/SPEC.md\` (canon, ADR 0070). US abertas (status ∉ done/cancelled).\n`;
  out += `> **${open.length} tarefas abertas** em **${modules.length} módulos**. Regenera com \`node scripts/governance/tasks-index-generate.mjs --write\`.\n\n`;

  // Índice (TOC) por módulo
  out += '## Índice por módulo\n\n';
  out += '| Módulo | Abertas | doing | review | blocked | todo/backlog |\n|---|---:|---:|---:|---:|---:|\n';
  for (const mod of modules) {
    const ts = byModule.get(mod);
    const c = (s) => ts.filter((t) => t.status === s).length;
    out += `| [\`${mod}\`](#${mod.toLowerCase()}) | ${ts.length} | ${c('doing')} | ${c('review')} | ${c('blocked')} | ${c('todo') + c('backlog')} |\n`;
  }
  out += '\n';

  // Detalhe por módulo → status → tarefas (ordenadas por prioridade)
  for (const mod of modules) {
    const ts = byModule.get(mod).sort((a, b) => statusRank(a.status) - statusRank(b.status) || prioRank(a.priority) - prioRank(b.priority) || a.id.localeCompare(b.id));
    out += `\n## ${mod}\n\n`;
    let curStatus = null;
    for (const t of ts) {
      if (t.status !== curStatus) {
        curStatus = t.status;
        out += `\n### ${curStatus}\n\n`;
      }
      const tags = [t.priority && `\`${t.priority}\``, t.owner !== '—' && `@${t.owner}`, t.sprint && `sprint ${t.sprint}`, t.progress && `${t.progress}`].filter(Boolean).join(' · ');
      out += `- **${t.id}** — ${t.title}${tags ? ` _(${tags})_` : ''}\n`;
    }
  }
  return out;
}

// ─── CHANGELOG (done) — por data (desc) → módulo ────────────────────────────────
function renderChangelog() {
  const dated = done.filter((t) => t.done_at).sort((a, b) => b.done_at.localeCompare(a.done_at) || a.module.localeCompare(b.module));
  const undated = done.filter((t) => !t.done_at);

  let out = '<!-- GERADO por scripts/governance/tasks-index-generate.mjs — NÃO editar à mão (regenera). -->\n';
  out += '# Changelog indexado (gerado)\n\n';
  out += `> Fonte: US-* com \`status: done\` nos SPEC.md. **${done.length} entregues** (${dated.length} com data). Mais recentes primeiro.\n\n`;

  let curDate = null;
  for (const t of dated) {
    if (t.done_at !== curDate) {
      curDate = t.done_at;
      out += `\n## ${curDate}\n\n`;
    }
    const ref = [t.commit && `\`${t.commit}\``, t.sprint && `sprint ${t.sprint}`, t.owner !== '—' && `@${t.owner}`].filter(Boolean).join(' · ');
    out += `- **[${t.module}]** ${t.id} — ${t.title}${ref ? ` _(${ref})_` : ''}\n`;
  }
  if (undated.length) {
    out += `\n## (done sem done_at)\n\n`;
    for (const t of undated.sort((a, b) => a.module.localeCompare(b.module) || a.id.localeCompare(b.id))) {
      out += `- **[${t.module}]** ${t.id} — ${t.title}\n`;
    }
  }
  return out;
}

const backlogMd = renderBacklog();
const changelogMd = renderChangelog();

console.log(`tasks-index: ${tasks.length} US lidas · ${open.length} abertas · ${done.length} done`);

if (MODE === 'dry') {
  console.log(`\n[dry-run] BACKLOG (${OUT_BACKLOG}) + CHANGELOG (${OUT_CHANGELOG}) NÃO gravados.`);
  console.log(backlogMd.split('\n').slice(0, 22).join('\n'));
} else if (MODE === 'write') {
  writeFileSync(join(ROOT, OUT_BACKLOG), backlogMd);
  console.log(`\n✅ gravado: ${OUT_BACKLOG}`);
  // CHANGELOG gerado DESLIGADO de propósito (veredito adversarial 2026-06-16): done_at/commit
  // só existem em ~1/44 SPECs (sai stale/vazio). O changelog REAL são os Modules/<X>/CHANGELOG.md
  // curados (prosa semver). Reabilitar só quando done_at for materializado nos SPECs.
} else if (MODE === 'check') {
  let drift = false;
  for (const [path, gen] of [[OUT_BACKLOG, backlogMd]]) {
    const cur = existsSync(join(ROOT, path)) ? readFileSync(join(ROOT, path), 'utf8') : '';
    if (cur !== gen) {
      console.error(`❌ drift: ${path} difere do gerado — rode --write e commit.`);
      drift = true;
    }
  }
  process.exit(drift ? 1 : 0);
}
