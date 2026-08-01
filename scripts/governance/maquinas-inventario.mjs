#!/usr/bin/env node
// maquinas-inventario.mjs — DERIVA um índice único e legível de TODAS as "máquinas"
// (workflows, gates, hooks, skills, agents, scripts, baselines JSON) a partir das
// PRÓPRIAS fontes — reusa os índices já gerados (hooks/skills/workflows) e preenche o
// gap não-catalogado (scripts/** + agents). NÃO escreve descrição à mão: cada linha vem
// do cabeçalho/frontmatter/_meta do próprio arquivo. Regerar: node scripts/governance/maquinas-inventario.mjs --write
import { readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const write = process.argv.includes('--write');

// --- helpers de extração (medido, nunca inventado) ---
const firstDescComment = (txt) => {
  // padrão canônico: `// nome.mjs — descrição`  OU  bloco `/** nome — descrição`
  const lines = txt.split('\n');
  for (const l of lines.slice(0, 12)) {
    let m = l.match(/^\s*(?:\/\/|\*)\s*[\w.-]+\.(?:mjs|js|cjs)\s*[—-]\s*(.+)$/);
    if (m) return m[1].trim();
    m = l.match(/^\s*(?:\/\/|\*)\s*[\w.-]+\s*[—-]\s*(.+)$/);
    if (m && !/^!/.test(l) && !/@ts-check|eslint|node/.test(l)) return m[1].trim();
  }
  // fallback: 1ª linha de comentário significativa (sem o prefixo `nome —`)
  for (const l of lines.slice(0, 15)) {
    const m = l.match(/^\s*(?:\/\/|\*)\s+(.{12,})$/);
    if (!m) continue;
    const s = m[1].trim();
    if (/^[!/*]|@ts-check|eslint-disable|^https?:|^usage:/i.test(s)) continue;
    return s.replace(/\*\/\s*$/, '').trim();
  }
  return '(sem descrição no cabeçalho)';
};
const frontmatterField = (txt, field) => {
  // block scalar PRIMEIRO: `field: |` seguido de linhas indentadas (senão o same-line captura o `|`)
  const block = txt.match(new RegExp(`^${field}:[ \\t]*[|>][-+]?[ \\t]*\\r?\\n((?:[ \\t]+.*\\r?\\n?)+)`, 'm'));
  if (block) {
    const firstLine = block[1].split('\n').map((s) => s.trim()).find((s) => s.length > 0);
    return firstLine || null;
  }
  // same-line: `field: valor` (agora seguro — o bloco já foi tratado)
  const same = txt.match(new RegExp(`^${field}:[ \\t]+(.+)$`, 'm'));
  if (same) return same[1].trim().replace(/^["']|["']$/g, '');
  return null;
};
const firstSentence = (s, max = 160) => {
  if (!s) return '';
  const cut = s.split(/(?<=[.!?])\s/)[0];
  return (cut.length > max ? cut.slice(0, max) + '…' : cut);
};
const trunc = (s, n = 150) => (s && s.length > n ? s.slice(0, n) + '…' : s || '');
const lsDir = (d) => (existsSync(join(ROOT, d)) ? readdirSync(join(ROOT, d)) : []);

const out = [];
const P = (s = '') => out.push(s);

P('# Máquinas do oimpresso — inventário consolidado (DERIVADO)');
P('');
P('> ⚙️ **Auto-gerado** por `scripts/governance/maquinas-inventario.mjs` — cada descrição vem do');
P('> cabeçalho/frontmatter/`_meta` do PRÓPRIO arquivo (medido, não escrito à mão · ADR 0256).');
P('> Regerar: `node scripts/governance/maquinas-inventario.mjs --write`.');
P('>');
P('> **Donos canônicos** (esta página só CONSOLIDA — a fonte viva de cada eixo é):');
P('> - Hooks → `.claude/hooks/_HOOKS-INDEX.md` · Skills → `.claude/skills/_SKILLS-INDEX.md`');
P('> - Gates/Workflows → `scripts/governance/gates-registry.json` · Required → `governance/required-checks-baseline.json`');
P('');

// ===== 1. WORKFLOWS / GATES (reusa gates-registry.json) =====
const reg = JSON.parse(readFileSync(join(ROOT, 'scripts/governance/gates-registry.json'), 'utf8'));
const wf = reg.workflows || {};
const wfKeys = Object.keys(wf).sort();
let required = new Set();
try {
  const base = JSON.parse(readFileSync(join(ROOT, 'governance/required-checks-baseline.json'), 'utf8'));
  (base.classic_protection?.contexts || []).forEach((c) => required.add(c));
} catch {}
P(`## 1. Workflows / Gates de CI — ${wfKeys.length} (${required.size} contexts required)`);
P('');
P('| Workflow | Descrição |');
P('|---|---|');
for (const k of wfKeys) {
  const nome = wf[k]?.nome || wf[k]?.description || '';
  P(`| \`${k}\` | ${trunc(nome, 170)} |`);
}
P('');

// ===== 2. HOOKS (aponta pro índice gerado + conta) =====
const hooks = lsDir('.claude/hooks').filter((f) => f.endsWith('.mjs') && !f.includes('.test.'));
P(`## 2. Hooks (PreToolUse/PostToolUse/SessionStart) — ${hooks.length} arquivos`);
P('');
P('> Fonte viva com evento×matcher×sinal-de-bloqueio: **`.claude/hooks/_HOOKS-INDEX.md`** (auto-gerado).');
P('');
P('| Hook | Descrição (cabeçalho) |');
P('|---|---|');
for (const f of hooks.sort()) {
  const txt = readFileSync(join(ROOT, '.claude/hooks', f), 'utf8');
  P(`| \`${f}\` | ${trunc(firstDescComment(txt), 160)} |`);
}
P('');

// ===== 3. SKILLS (aponta pro índice gerado) =====
const skillDirs = lsDir('.claude/skills').filter((d) => existsSync(join(ROOT, '.claude/skills', d, 'SKILL.md')));
P(`## 3. Skills — ${skillDirs.length}`);
P('');
P('> Fonte viva com Tier/auto_trigger: **`.claude/skills/_SKILLS-INDEX.md`** (auto-gerado do frontmatter).');
P('');
P('| Skill | Tier | Descrição (início) |');
P('|---|---|---|');
for (const d of skillDirs.sort()) {
  const txt = readFileSync(join(ROOT, '.claude/skills', d, 'SKILL.md'), 'utf8');
  const desc = frontmatterField(txt, 'description') || '';
  const tier = frontmatterField(txt, 'tier') || frontmatterField(txt, 'x-tier') || '—';
  P(`| \`${d}\` | ${tier} | ${trunc(firstSentence(desc, 999), 150)} |`);
}
P('');

// ===== 4. AGENTS (gap — deriva do frontmatter) =====
const agents = lsDir('.claude/agents').filter((f) => f.endsWith('.md') && !f.startsWith('_'));
P(`## 4. Agents (subagentes Task) — ${agents.length}`);
P('');
P('| Agent | Descrição (início) |');
P('|---|---|');
for (const f of agents.sort()) {
  const txt = readFileSync(join(ROOT, '.claude/agents', f), 'utf8');
  const desc = frontmatterField(txt, 'description') || '';
  P(`| \`${f.replace('.md', '')}\` | ${trunc(firstSentence(desc, 999), 170)} |`);
}
P('');

// ===== 5. SCRIPTS (o GAP real — nenhum índice cobre scripts/**) =====
const dumpScripts = (dir, titulo) => {
  const files = lsDir(dir).filter((f) => /\.(mjs|js|cjs)$/.test(f) && !f.includes('.test.'));
  P(`### ${titulo} — ${files.length}`);
  P('');
  P('| Script | Descrição (cabeçalho) |');
  P('|---|---|');
  for (const f of files.sort()) {
    const txt = readFileSync(join(ROOT, dir, f), 'utf8');
    P(`| \`${f}\` | ${trunc(firstDescComment(txt), 160)} |`);
  }
  P('');
};
P('## 5. Scripts (`scripts/**`) — o gap sem índice-dono');
P('');
dumpScripts('scripts/governance', '5.1 `scripts/governance/`');
dumpScripts('scripts/tests', '5.2 `scripts/tests/`');
dumpScripts('scripts', '5.3 `scripts/` (raiz)');

// ===== 6. BASELINES / JSON de estado (deriva _meta quando existe) =====
const jsonDirs = ['governance', 'config', 'scripts'];
P('## 6. Baselines & JSON de estado');
P('');
P('| Arquivo | `_meta` / propósito |');
P('|---|---|');
const seen = new Set();
for (const d of jsonDirs) {
  for (const f of lsDir(d).filter((x) => x.endsWith('.json')).sort()) {
    const p = join(d, f);
    if (seen.has(p)) continue;
    seen.add(p);
    let meta = '';
    try {
      const j = JSON.parse(readFileSync(join(ROOT, p), 'utf8'));
      meta = j._meta?.gate || j._meta?.baseline || j._meta?.nota || j._meta?.description || '';
    } catch {}
    P(`| \`${p}\` | ${trunc(meta, 150) || '(baseline/estado)'} |`);
  }
}
P('');
P(`> Total baselines JSON em governance/+config/+scripts: ${seen.size} · (mais ~5 dot-baselines na raiz + fixtures em tests/).`);
P('');

const md = out.join('\n');
if (write) {
  const dest = join(ROOT, 'governance/MAQUINAS-INVENTARIO.md');
  writeFileSync(dest, md);
  console.log(`✅ escrito: governance/MAQUINAS-INVENTARIO.md (${md.split('\n').length} linhas)`);
} else {
  process.stdout.write(md);
}
