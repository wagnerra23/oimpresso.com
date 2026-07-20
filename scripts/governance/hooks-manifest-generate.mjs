#!/usr/bin/env node
// @ts-check
/**
 * hooks-manifest-generate.mjs — GERADOR determinístico do manifesto de hooks (grade de réguas
 * 2026-07-19: fraquezas `spec-hooks-desarmados` 8,0 + `spec-enforcement-geracao` 6,5).
 *
 * O metaguard (settings-*-registration ×9 + gate-selftest + protection-drift) já PROVA que
 * hooks específicos estão acoplados — mas não existia fonte única LEGÍVEL de "quais hooks
 * existem, qual matcher, qual mecanismo, e em qual ponto-de-corte mordem". Extensão do padrão
 * fonte-única do skills-index-generate.mjs (#4032): o manifesto é DERIVADO das fontes, nunca
 * escrito à mão (senão vira o presence-gate de campo auto-declarado — §5 2026-07-09).
 *
 * Fontes (só leitura):
 *   (1) .claude/settings.json                    — wiring real (evento × matcher × comando)
 *   (2) .claude/hooks/*                          — arquivos de hook no disco
 *   (3) governance/required-checks-baseline.json — gates de CI (corte no merge)
 *
 * Colunas de estado são COMPUTADAS na geração — nunca prosa em tempo presente (lápide §5
 * 2026-07-16: artefato não declara o próprio enforcement; aqui a coluna é derivada e
 * regenerável, e o --check acusa quando o manifesto laga a realidade):
 *   - sinal de bloqueio: heurística estática sobre o CONTEÚDO do arquivo (`deny` quoted ·
 *     exit-2/return 2) — o critério da ADR 0224 ("mecanismo real, não o nome"). É sinal de
 *     CAPACIDADE detectada no código (deny condicional/strict conta), NÃO afirmação de runtime.
 *   - ponto-de-corte: derivado de evento+matcher (sessão/prompt/geração/comando/leitura/
 *     pós-ação) e, pros gates CI, da presença no baseline (merge).
 *   - órfão (arquivo sem wiring) e fantasma (wiring sem arquivo): computados por diff.
 *
 * Uso:
 *   node scripts/governance/hooks-manifest-generate.mjs           (dry-run: resumo)
 *   node scripts/governance/hooks-manifest-generate.mjs --write   (grava .claude/hooks/_HOOKS-INDEX.md)
 *   node scripts/governance/hooks-manifest-generate.mjs --check   (exit 1 se manifesto ≠ realidade, ou fantasma; órfão = warn)
 *
 * Report-only por desenho (ADR 0314 — não entra em branch protection).
 * Refs: ADR 0224 (block vs advisory por mecanismo) · ADR 0256 (derivado+enforçado sobrevive) ·
 *       ADR 0275/0314 (dono de "required no merge" = governance/required-checks-baseline.json).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const SETTINGS = '.claude/settings.json';
const HOOKS_DIR = '.claude/hooks';
const BASELINE = 'governance/required-checks-baseline.json';
const OUT = '.claude/hooks/_HOOKS-INDEX.md';
const MODE = process.argv.includes('--write') ? 'write' : process.argv.includes('--check') ? 'check' : 'dry';
const HOOK_EXTS = ['.mjs', '.js', '.cjs', '.ps1', '.sh'];

function lerJson(rel) {
  try {
    return JSON.parse(readFileSync(join(ROOT, rel), 'utf8'));
  } catch (e) {
    console.error(`✗ ${rel} ilegível/JSON inválido: ${e.message}`);
    process.exit(1);
  }
}
const settings = lerJson(SETTINGS);
const baseline = lerJson(BASELINE);
const eventos = settings.hooks && typeof settings.hooks === 'object' ? settings.hooks : {};

function matcherCobre(matcher, tool) {
  try { return new RegExp(`^(?:${matcher})$`).test(tool); }
  catch { return String(matcher).split('|').includes(tool); }
}

// ponto-de-corte derivado SÓ de evento+matcher (taxonomia do pedido: geração/comando/merge)
function pontoDeCorte(evento, matcher) {
  if (evento === 'SessionStart') return 'sessão (início — injeção de contexto)';
  if (evento === 'UserPromptSubmit') return 'prompt (pré-turno)';
  if (evento === 'Stop') return 'fim de turno';
  if (evento === 'PostToolUse') return 'pós-ação (observa, não corta)';
  if (evento === 'PreToolUse') {
    if (matcherCobre(matcher, 'Write') || matcherCobre(matcher, 'Edit')) return 'geração (pré-Write/Edit)';
    if (matcherCobre(matcher, 'Bash') || matcherCobre(matcher, 'PowerShell')) return 'comando (pré-shell — git commit/push trafegam aqui)';
    if (matcherCobre(matcher, 'Read') || matcherCobre(matcher, 'Glob')) return 'leitura (pré-Read/Glob/Grep)';
    return 'ferramenta (pré-uso do matcher)';
  }
  return `evento ${evento}`;
}

// Heurística estática (critério ADR 0224 — mecanismo real, não o nome). Sinal de CAPACIDADE
// no código; deny condicional (ex.: modo strict do bom-encoding/charter-validate) conta.
function sinaisBloqueio(nomeArquivo, txt) {
  const s = [];
  if (/['"]deny['"]/.test(txt)) s.push('deny');
  const js = /\.(mjs|js|cjs)$/.test(nomeArquivo);
  if (js ? /process\.exit\(2\)|exitCode\s*=\s*2|return 2\b/.test(txt) : /^\s*exit\s+2\b/m.test(txt)) s.push('exit-2');
  return s;
}

const runtimeDe = (cmd) => (/^node\b/.test(cmd) ? 'node' : /powershell/i.test(cmd) ? 'powershell' : 'outro');
const arquivoDe = (cmd) => {
  const m = cmd.match(/\.claude[\\/]hooks[\\/]([A-Za-z0-9._-]+)/);
  return m ? m[1] : null;
};

// ── fonte 1 × fonte 2: wirings, fantasmas ──
const rows = [];
const fantasmas = [];
const wiredFiles = new Set();
for (const [evento, grupos] of Object.entries(eventos)) {
  for (const g of Array.isArray(grupos) ? grupos : []) {
    const matcher = String(g.matcher ?? '*');
    for (const h of Array.isArray(g.hooks) ? g.hooks : []) {
      const cmd = String(h.command || '');
      const file = arquivoDe(cmd);
      let sinais = [];
      if (file) {
        wiredFiles.add(file);
        const p = join(ROOT, HOOKS_DIR, file);
        if (existsSync(p)) sinais = sinaisBloqueio(file, readFileSync(p, 'utf8'));
        else fantasmas.push({ evento, matcher, file });
      } else {
        sinais = sinaisBloqueio('inline.ps1', cmd); // comando inline: escaneia o próprio texto
      }
      rows.push({ evento, matcher, file: file || '(inline no settings.json)', runtime: runtimeDe(cmd), corte: pontoDeCorte(evento, matcher), sinais });
    }
  }
}

// ── fonte 2: órfãos (arquivo de hook sem wiring; *.test.* são testes, fora da conta) ──
const arquivos = readdirSync(join(ROOT, HOOKS_DIR)).filter((f) => HOOK_EXTS.some((e) => f.endsWith(e))).sort();
const testes = arquivos.filter((f) => /\.test\./.test(f));
const orfaos = [];
for (const f of arquivos) {
  if (/\.test\./.test(f) || wiredFiles.has(f)) continue;
  const stem = f.replace(/\.[^.]+$/, '');
  const gemeo = [...wiredFiles].find((w) => w.replace(/\.[^.]+$/, '') === stem);
  orfaos.push({ f, nota: gemeo ? `gêmeo cross-platform de ${gemeo} (wired)` : 'sem wiring em settings.json' });
}

// ── fonte 3: gates CI do baseline (corte no merge) ──
const classic = (baseline.classic_protection && baseline.classic_protection.contexts) || [];
const rulesets = (baseline.rulesets && baseline.rulesets.contexts) || [];

// ── render (determinístico — sem timestamps, senão o --check flaparia) ──
const cell = (t, max = 100) => {
  const s = String(t).replace(/\|/g, '/');
  return s.length > max ? s.slice(0, max - 1) + '…' : s;
};
const sinaisCell = (s) => (s.length ? s.join(' + ') : '—');
const manifest = `# Hooks Manifest — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por \`scripts/governance/hooks-manifest-generate.mjs\` — fontes: \`.claude/settings.json\` (wiring) + \`.claude/hooks/*\` (arquivos) + \`governance/required-checks-baseline.json\` (gates CI).
> Regenerar: \`node scripts/governance/hooks-manifest-generate.mjs --write\` · drift acusado por \`--check\`.
>
> **Como ler as colunas computadas** (nada aqui é declarado à mão):
> - **Sinal de bloqueio** = heurística estática sobre o conteúdo do arquivo na geração (\`deny\` quoted · \`exit-2\`/\`return 2\`) — critério da [ADR 0224](../../memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md) ("mecanismo real, não o nome"). É CAPACIDADE detectada no código (deny condicional/strict conta); ausência de sinal ≠ classificação advisory, e presença ≠ afirmação de runtime.
> - **Ponto-de-corte** = derivado de evento+matcher (hooks) ou da presença no baseline (gates CI → merge).
> - O dono de "o que é required no merge" é \`governance/required-checks-baseline.json\` (vigiado por \`protection-drift.mjs\`) — a seção de gates abaixo é CÓPIA GERADA dele, re-derivada a cada \`--write\` e conferida pelo \`--check\`.

## Resumo
- **${rows.length}** wirings em \`settings.json\` (${Object.keys(eventos).length} eventos) · **${wiredFiles.size}** arquivos de hook distintos wired
- **${arquivos.length - testes.length}** arquivos de hook no disco (+${testes.length} \`*.test.*\` — testes, fora da conta de órfãos)
- Órfãos (arquivo sem wiring): **${orfaos.length}** · Fantasmas (wiring sem arquivo): **${fantasmas.length}**
- Gates CI no baseline: **${classic.length}** classic + **${rulesets.length}** ruleset → ponto-de-corte merge

## Hooks wired (evento × matcher × arquivo)
| Evento | Matcher | Hook | Runtime | Ponto-de-corte | Sinal de bloqueio (heurística) |
|---|---|---|---|---|---|
${rows.map((r) => `| ${r.evento} | \`${cell(r.matcher, 60)}\` | ${cell(r.file)} | ${r.runtime} | ${r.corte} | ${sinaisCell(r.sinais)} |`).join('\n')}

## Fantasmas (wiring sem arquivo no disco)
${fantasmas.length ? fantasmas.map((x) => `- ⛔ \`${x.file}\` — referenciado em ${x.evento} (matcher \`${cell(x.matcher, 60)}\`) mas o arquivo não existe`).join('\n') : 'Nenhum.'}

## Órfãos (arquivo de hook sem wiring em settings.json)
${orfaos.length ? orfaos.map((x) => `- ⚠️ \`${x.f}\` — ${x.nota}`).join('\n') : 'Nenhum.'}

## Gates CI (\`required-checks-baseline.json\` → ponto-de-corte merge)
Contexts \`classic_protection\` (${classic.length}):
${classic.map((c) => `- ${c}`).join('\n')}

Contexts \`rulesets\` (${rulesets.length}):
${rulesets.map((c) => `- ${c}`).join('\n')}
`;

const resumoCurto = `${rows.length} wirings · ${wiredFiles.size} hooks wired · ${orfaos.length} órfão(s) · ${fantasmas.length} fantasma(s) · ${classic.length + rulesets.length} gates CI`;
const norm = (t) => t.replace(/\r\n/g, '\n').trim();

if (MODE === 'check') {
  let fail = false;
  if (fantasmas.length) {
    console.error(`✗ ${fantasmas.length} fantasma(s) — wiring em settings.json apontando pra arquivo inexistente (hook morto em silêncio — família "correção ≠ invocação"):`);
    fantasmas.forEach((x) => console.error(`   - ${x.evento} → ${x.file}`));
    fail = true;
  }
  const cur = existsSync(join(ROOT, OUT)) ? readFileSync(join(ROOT, OUT), 'utf8') : '';
  if (norm(cur) !== norm(manifest)) {
    console.error(`✗ ${OUT} está DESATUALIZADO vs settings/hooks/baseline — rode --write. (manifesto gerado ≠ commitado = drift)`);
    fail = true;
  }
  if (orfaos.length) orfaos.forEach((x) => console.error(`⚠️ órfão: ${x.f} — ${x.nota} (report-only, não falha)`));
  if (fail) process.exit(1);
  console.log(`✓ ${OUT} em dia (${resumoCurto}).`);
} else if (MODE === 'write') {
  writeFileSync(join(ROOT, OUT), manifest);
  console.log(`✓ gerado ${OUT} (${resumoCurto}).`);
} else {
  console.log(manifest);
  console.log(`\n[dry-run] ${resumoCurto}. Rode --write pra gravar.`);
}
