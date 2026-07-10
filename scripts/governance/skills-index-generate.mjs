#!/usr/bin/env node
// @ts-check
/**
 * skills-index-generate.mjs — GERADOR determinístico do índice de skills (US-GOV-052 P31).
 *
 * Mesmo modelo do adr-index-generate.mjs (Log4brains, estado-da-arte 2026): o TIER de
 * cada skill vivia em 4 fontes que driftavam (frontmatter dos SKILL.md · lista do
 * CLAUDE.md · banner SessionStart tier-a · skills-audit) — o PR #4015 corrigiu o
 * CLAUDE.md NA MÃO e ia driftar de novo. Este gerador faz do FRONTMATTER a fonte
 * única: lê `.claude/skills/<skill>/SKILL.md` (campos explícitos `tier:` + `auto_trigger:`
 * + `enabled:` + `resumo:` — NUNCA prosa, ADR 0225) e emite:
 *   (1) o bloco de skills do CLAUDE.md entre marcadores AUTO:SKILLS (só a lista —
 *       o resto do CLAUDE.md segue manual);
 *   (2) `.claude/skills/_SKILLS-INDEX.md` — tabela completa de todas as skills.
 *
 * Regras de derivação (campos explícitos, ADR 0225):
 *   tier A + enabled≠false            → núcleo always-on (segurança/LGPD/disciplina)
 *   tier A + enabled: false           → dormente
 *   tier B + auto_trigger ≠ on_demand → destaque auto-trigger (dispara por path/intenção/momento)
 *   demais (B sem auto_trigger, C)    → só na tabela do _SKILLS-INDEX.md
 *
 * Uso:
 *   node scripts/governance/skills-index-generate.mjs           (dry-run: imprime resumo)
 *   node scripts/governance/skills-index-generate.mjs --write   (grava CLAUDE.md + _SKILLS-INDEX.md)
 *   node scripts/governance/skills-index-generate.mjs --check   (CI advisory: exit 1 se gerado ≠ commitado = drift, ou frontmatter inválido)
 *
 * Refs: ADR 0225 (5 núcleo + auto-trigger) · ADR 0095 (convenção tiers) · ADR 0256
 *       (fonte única gerada) · ADR 0314 (advisory) · revisão memória-processo P31.
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const SKILLS_DIR = '.claude/skills';
const INDEX_OUT = '.claude/skills/_SKILLS-INDEX.md';
const CLAUDE_MD = 'CLAUDE.md';
const MARK_BEGIN = '<!-- AUTO:SKILLS-BEGIN — gerado por scripts/governance/skills-index-generate.mjs (fonte única: frontmatter .claude/skills/*/SKILL.md). NÃO editar à mão; rode --write. -->';
const MARK_END = '<!-- AUTO:SKILLS-END -->';
const MODE = process.argv.includes('--write') ? 'write' : process.argv.includes('--check') ? 'check' : 'dry';

const TIERS = new Set(['A', 'B', 'C']);
const AUTO_TRIGGERS = new Set(['session_start', 'path', 'intent', 'on_demand']);

function field(fm, key) {
  const m = fm.match(new RegExp(`^${key}:\\s*(.+)$`, 'mi'));
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : '';
}
// description pode ser bloco `description: |` — pega a 1ª linha útil pro índice.
function descriptionFirstLine(fm) {
  const inline = fm.match(/^description:\s*(\S.*)$/mi);
  if (inline && inline[1].trim() !== '|' && inline[1].trim() !== '>') return inline[1].trim();
  const block = fm.match(/^description:\s*[|>]-?\s*\n((?:[ \t]+.+\n?)+)/mi);
  if (block) return block[1].split('\n').map((l) => l.trim()).filter(Boolean).join(' ');
  return '';
}

const errors = [];
const skills = [];
for (const dir of readdirSync(join(ROOT, SKILLS_DIR), { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name, 'en'))) {
  if (!dir.isDirectory()) continue;
  const file = join(ROOT, SKILLS_DIR, dir.name, 'SKILL.md');
  if (!existsSync(file)) continue;
  const txt = readFileSync(file, 'utf8');
  if (!txt.startsWith('---')) { errors.push(`${dir.name}: SKILL.md sem frontmatter`); continue; }
  const end = txt.indexOf('\n---', 3);
  const fm = end === -1 ? txt : txt.slice(0, end);
  const s = {
    slug: dir.name,
    tier: field(fm, 'tier').toUpperCase(),
    auto_trigger: field(fm, 'auto_trigger'),
    enabled: field(fm, 'enabled').toLowerCase() !== 'false', // ausente = habilitada
    resumo: field(fm, 'resumo'),
    desc: descriptionFirstLine(fm),
  };
  if (!TIERS.has(s.tier)) errors.push(`${s.slug}: tier "${s.tier || '(vazio)'}" inválido — precisa ser A/B/C no frontmatter`);
  if (s.auto_trigger && !AUTO_TRIGGERS.has(s.auto_trigger)) errors.push(`${s.slug}: auto_trigger "${s.auto_trigger}" inválido — enum ${[...AUTO_TRIGGERS].join('/')}`);
  if (s.tier === 'A' && s.auto_trigger) errors.push(`${s.slug}: tier A com auto_trigger é contradição (always-on não dispara por gatilho) — critério ADR 0225`);
  skills.push(s);
}

// ── grupos do bloco CLAUDE.md (derivação por campo explícito, nunca prosa) ──
const nucleo = skills.filter((s) => s.tier === 'A' && s.enabled);
const dormentes = skills.filter((s) => s.tier === 'A' && !s.enabled);
const autoTrigger = skills.filter((s) => s.tier === 'B' && s.auto_trigger && s.auto_trigger !== 'on_demand');
for (const s of [...nucleo, ...dormentes, ...autoTrigger]) {
  if (!s.resumo) errors.push(`${s.slug}: entra no bloco do CLAUDE.md (${s.tier === 'A' ? (s.enabled ? 'núcleo' : 'dormente') : 'auto-trigger'}) mas não tem \`resumo:\` no frontmatter`);
}

// 4ª fonte (P31): banner SessionStart precisa ao menos MENCIONAR cada skill do núcleo —
// não gera o .ps1 (texto livre), mas acusa o drift mais grave (núcleo ausente do banner).
const BANNER = '.claude/hooks/tier-a-banner.ps1';
if (existsSync(join(ROOT, BANNER))) {
  const banner = readFileSync(join(ROOT, BANNER), 'utf8');
  for (const s of nucleo) {
    if (!banner.includes(s.slug)) errors.push(`${s.slug}: skill núcleo Tier A ausente do banner ${BANNER} — atualize o banner (4ª fonte do P31)`);
  }
}

// ── render: bloco CLAUDE.md (SÓ a lista de skills — ressalva b do adversário) ──
const line = (s) => `- **${s.slug}** — ${s.resumo}`;
const bloco = `${MARK_BEGIN}
**Tier A** (núcleo always-on — segurança/LGPD/disciplina, carregam em toda sessão):
${nucleo.map(line).join('\n')}

**Auto-trigger** (Tier B — disparam por path/intenção/momento, ADR 0225):
${autoTrigger.map((s) => `- **${s.slug}** _(${s.auto_trigger})_ — ${s.resumo}`).join('\n')}
${dormentes.length ? `\n**Dormente** (tier A com \`enabled: false\`):\n${dormentes.map(line).join('\n')}\n` : ''}${MARK_END}`;

// ── render: _SKILLS-INDEX.md (tabela completa) ──
const tally = skills.reduce((o, s) => ((o[s.tier] = (o[s.tier] || 0) + 1), o), {});
const trunc = (t) => (t.length > 120 ? t.slice(0, 117) + '…' : t);
const cell = (t) => trunc(t).replace(/\|/g, '/');
const indexMd = `# Skills Index — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por \`scripts/governance/skills-index-generate.mjs\` a partir do frontmatter de \`.claude/skills/*/SKILL.md\` (fonte única — US-GOV-052 P31).
> Regenerar: \`node scripts/governance/skills-index-generate.mjs --write\`. Convenção de tiers: ADR 0095 · recalibração 5 núcleo + auto-trigger: ADR 0225.

## Resumo
- **${skills.length}** skills · Tier A **${tally.A || 0}** (${nucleo.length} núcleo + ${dormentes.length} dormente) · Tier B **${tally.B || 0}** · Tier C **${tally.C || 0}**
- Auto-trigger explícito: ${skills.filter((s) => s.auto_trigger).length} (${['session_start', 'path', 'intent', 'on_demand'].map((k) => `${k} ${skills.filter((s) => s.auto_trigger === k).length}`).join(' · ')})
- Destacadas no bloco do CLAUDE.md: ${nucleo.length + dormentes.length + autoTrigger.length} (entre marcadores AUTO:SKILLS)

## Todas as skills (${skills.length})
| Skill | Tier | auto_trigger | enabled | Descrição (início) |
|---|---|---|---|---|
${skills.map((s) => `| ${s.slug} | ${s.tier} | ${s.auto_trigger || '—'} | ${s.enabled ? 'sim' : '**false (dormente)**'} | ${cell(s.desc || s.resumo || '')} |`).join('\n')}
`;

// ── substituição do bloco no CLAUDE.md ──
function spliceClaudeMd(current) {
  const b = current.indexOf(MARK_BEGIN);
  const e = current.indexOf(MARK_END);
  if (b === -1 || e === -1) return null;
  return current.slice(0, b) + bloco + current.slice(e + MARK_END.length);
}

if (errors.length) {
  console.error(`✗ ${errors.length} erro(s) de frontmatter — a fonte única precisa estar limpa:`);
  errors.forEach((w) => console.error(`   - ${w}`));
  process.exit(1);
}

const claudePath = join(ROOT, CLAUDE_MD);
const claudeCur = existsSync(claudePath) ? readFileSync(claudePath, 'utf8') : '';
const claudeNew = spliceClaudeMd(claudeCur);

if (MODE === 'check') {
  let fail = false;
  if (claudeNew === null) {
    console.error(`✗ ${CLAUDE_MD} sem marcadores AUTO:SKILLS-BEGIN/END — o bloco de skills precisa ser gerado, não manual.`);
    fail = true;
  } else if (claudeNew !== claudeCur) {
    console.error(`✗ bloco de skills do ${CLAUDE_MD} está DESATUALIZADO vs frontmatter das skills — rode --write.`);
    fail = true;
  }
  const idxCur = existsSync(join(ROOT, INDEX_OUT)) ? readFileSync(join(ROOT, INDEX_OUT), 'utf8') : '';
  if (idxCur.trim() !== indexMd.trim()) {
    console.error(`✗ ${INDEX_OUT} está DESATUALIZADO — rode --write. (índice gerado ≠ commitado = drift)`);
    fail = true;
  }
  if (fail) process.exit(1);
  console.log(`✓ bloco CLAUDE.md + ${INDEX_OUT} em dia (${skills.length} skills · ${nucleo.length} núcleo A · ${autoTrigger.length} auto-trigger · ${dormentes.length} dormente).`);
  process.exit(0);
}
if (MODE === 'write') {
  if (claudeNew === null) {
    console.error(`✗ ${CLAUDE_MD} sem marcadores AUTO:SKILLS — insira ${MARK_BEGIN.slice(0, 30)}… e ${MARK_END} onde o bloco deve viver (1ª vez é manual).`);
    process.exit(1);
  }
  writeFileSync(claudePath, claudeNew);
  writeFileSync(join(ROOT, INDEX_OUT), indexMd);
  console.log(`✓ gerados: bloco CLAUDE.md + ${INDEX_OUT} — ${skills.length} skills (${nucleo.length} núcleo A + ${autoTrigger.length} auto-trigger + ${dormentes.length} dormente destacadas).`);
} else {
  console.log(bloco);
  console.log(`\n[dry-run] ${skills.length} skills · A=${tally.A || 0} B=${tally.B || 0} C=${tally.C || 0}. Rode --write pra gravar.`);
}
