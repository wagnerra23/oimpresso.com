#!/usr/bin/env node
// maquinas-inventario.mjs — DERIVA um índice único e legível de TODAS as "máquinas"
// (workflows, gates, hooks, skills, agents, scripts, baselines JSON) a partir das
// PRÓPRIAS fontes — reusa os índices já gerados (hooks/skills/workflows) e preenche o
// gap não-catalogado (scripts/** + agents). NÃO escreve descrição à mão: cada linha vem
// do cabeçalho/frontmatter/_meta do próprio arquivo. Regerar: node scripts/governance/maquinas-inventario.mjs --write
import { readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';

const ROOT = process.cwd();
const MODE = process.argv.includes('--write') ? 'write'
  : process.argv.includes('--check') ? 'check' : 'dry';

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

// Frontmatter: exigido pelo schema de `memory/reference/*.md` (name/description/type/
// authority) e é o que faz o doc ATRAVESSAR o filtro do DocumentacaoController —
// `type` ∈ {adr,reference,spec,runbook,feature} + `admin_only=false` + `scope_required`
// nulo. Sem ele o arquivo é indexado e some da página, calado. `authority: generated`
// é o mesmo par do PAINEL-SISTEMA.md (gerado, não curado).
P('---');
P('name: MAQUINAS-INVENTARIO — inventário derivado das máquinas do oimpresso');
P('description: Censo GERADO por scripts/governance/maquinas-inventario.mjs (workflows, hooks, skills, agents, scripts, baselines). NÃO editar à mão (regenera). Cada descrição vem do cabeçalho/frontmatter/_meta da própria máquina.');
P('type: reference');
P('authority: generated');
P('lifecycle: ativo');
P('---');
P('');
P('# Máquinas do oimpresso — inventário consolidado (DERIVADO)');
P('');
P('> ⚙️ **Auto-gerado** por `scripts/governance/maquinas-inventario.mjs` — cada descrição vem do');
P('> cabeçalho/frontmatter/`_meta` do PRÓPRIO arquivo (medido, não escrito à mão · ADR 0256).');
P('> Regerar: `node scripts/governance/maquinas-inventario.mjs --write` · drift de COBERTURA');
P('> acusado por `--check` (advisory em `governance-script-tests.yml`): morde quando uma máquina');
P('> é adicionada/removida sem regenerar. Bite-test: `maquinas-inventario.test.mjs`.');
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
    // POSIX sempre: `join` usa o separador do SO, então no Windows isto virava
    // `governance\x.json` — e o `--check` no CI (Linux) lia como "fora do índice",
    // acusando 41 baselines que estavam lá. O índice é comparado string-exata entre
    // máquinas de SOs diferentes ([W] no Windows, o time e o CI em POSIX).
    const p = join(d, f).split('\\').join('/');
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
// Mora em `memory/reference/` (era `governance/`) porque é ONDE O ACERVO ENXERGA: o
// IndexarMemoryGitParaDb varre `memory/reference` por RECURSÃO (não por glob — os globs
// não cobrem essa pasta) e o DocumentacaoController publica quem tem `type: reference`.
// De `governance/` o arquivo não aparecia em /documentacao: aquele path não é varrido
// nem por glob nem por recursão. Mesmo lugar e mesmo motivo do PAINEL-SISTEMA.md, que
// também é gerado. Tombstone no path antigo — backlinks em handoff/session são
// append-only e não podem ser relinkados.
const OUT = process.env.MAQUINAS_OUT || 'memory/reference/MAQUINAS-INVENTARIO.md'; // env: fixture no bite-test

// Identidade da máquina = token da 1ª coluna das tabelas (`| \`nome\` | …`). Comparar SÓ
// esse conjunto torna o --check insensível a mudança de descrição/contagem — ele morde
// apenas quando uma máquina é ADICIONADA ou REMOVIDA sem regenerar (o drift que importa;
// byte-exact viraria vermelho em quase todo PR de governança → ruído ignorado).
const extractNames = (text) => {
  const s = new Set();
  for (const m of text.matchAll(/^\| `([^`]+)` /gm)) s.add(m[1]);
  return s;
};

const OUT_PATH = resolve(ROOT, OUT); // resolve trata OUT absoluto (env fixture) sem manglar
if (MODE === 'write') {
  writeFileSync(OUT_PATH, md);
  console.log(`✅ escrito: ${OUT} (${md.split('\n').length} linhas)`);
} else if (MODE === 'check') {
  if (!existsSync(OUT_PATH)) {
    console.error(`✗ ${OUT} não existe — rode: node scripts/governance/maquinas-inventario.mjs --write`);
    process.exit(1);
  }
  const fresh = extractNames(md);
  const have = extractNames(readFileSync(OUT_PATH, 'utf8'));
  const missing = [...fresh].filter((n) => !have.has(n)).sort(); // no disco, FORA do índice
  const ghost = [...have].filter((n) => !fresh.has(n)).sort();    // no índice, SUMIU do disco
  if (missing.length || ghost.length) {
    console.error(`✗ ${OUT} DESATUALIZADO (cobertura) — rode: node scripts/governance/maquinas-inventario.mjs --write`);
    if (missing.length) console.error(`  ${missing.length} no disco fora do índice: ${missing.slice(0, 15).join(', ')}${missing.length > 15 ? '…' : ''}`);
    if (ghost.length) console.error(`  ${ghost.length} no índice que sumiram do disco: ${ghost.slice(0, 15).join(', ')}${ghost.length > 15 ? '…' : ''}`);
    process.exit(1);
  }
  console.log(`✅ ${OUT} cobre todas as ${fresh.size} máquinas (0 faltando · 0 ghost).`);
} else {
  process.stdout.write(md);
}
