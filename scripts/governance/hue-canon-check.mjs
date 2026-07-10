#!/usr/bin/env node
// @ts-check
/**
 * hue-canon-check.mjs — verificador da fonte única do hue primário (US-GOV-052 P32).
 *
 * O hue primário universal (roxo 295, ADR 0190) vivia em 3 mapas divergentes —
 * a skill pageheader-canon chegou a ter check C4 APROVANDO o 145 morto (hue-per-grupo
 * pré-0190; corrigido no PR #4003, mas sem fonte que impeça reincidir). A fonte agora
 * é governance/hue-canon.json; este check falha se algum doc/skill/check DECLARAR
 * hue primário esperado diferente do canônico.
 *
 * Detecção pela CONSTRUÇÃO (ressalva do adversário — nunca por "145" cru, que é
 * legítimo como hue de grupo do sidebar):
 *   expected_hue: 145   ·   expectedHue = 145        (declaração de esperado)
 *   hue_correct: bg.includes('145')  ·  hueCorrect   (predicado de aprovação)
 *   primary_hue: 145                                  (declaração de canon paralelo)
 *
 * Fora de escopo (não flagra): sidebar_group_hue / SIDEBAR_GROUP_HUE (fonte é
 * resources/js/Components/cockpit/shared.ts) e qualquer número solto em prosa.
 *
 * Uso:
 *   node scripts/governance/hue-canon-check.mjs          (exit 1 se alguma declaração ≠ canon)
 *
 * Refs: ADR 0190 (primary roxo 295 universal) · ADR 0314 (advisory) · PR #4003 ·
 *       revisão memória-processo P32.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOT = process.cwd();
const CANON_FILE = 'governance/hue-canon.json';
const canon = JSON.parse(readFileSync(join(ROOT, CANON_FILE), 'utf8'));
const CANON_HUE = Number(canon.primary_hue);
if (!Number.isInteger(CANON_HUE) || CANON_HUE < 0 || CANON_HUE > 360) {
  console.error(`✗ ${CANON_FILE} inválido: primary_hue "${canon.primary_hue}" não é hue 0-360.`);
  process.exit(1);
}

const EXT = new Set(['.md', '.mjs', '.js', '.ts', '.tsx', '.jsx', '.ps1', '.sh', '.yml', '.yaml', '.json', '.php']);
const SKIP_DIRS = new Set(['node_modules', 'vendor', '.git', 'storage', 'bootstrap', 'public', 'worktrees', 'lang', 'database']);
// auto-exclusão: a fonte, este check e seu teste (contêm as construções por definição)
const SKIP_FILES = new Set([
  CANON_FILE,
  'scripts/governance/hue-canon-check.mjs',
  'scripts/governance/hue-canon-check.test.mjs',
]);

// construções declarativas de "hue primário esperado" — captura o número declarado
const CONSTRUCTIONS = [
  { re: /(?:expected_hue|expectedHue)\s*[:=]\s*['"]?(\d{1,3})\b/g, tipo: 'expected_hue' },
  { re: /(?:hue_correct|hueCorrect)[^\n]*?includes\(\s*['"](\d{1,3})['"]\s*\)/g, tipo: 'hue_correct' },
  { re: /(?:primary_hue|primaryHue)\s*[:=]\s*['"]?(\d{1,3})\b/g, tipo: 'primary_hue' },
];

const violations = [];
let confirmations = 0;
let scanned = 0;

function walk(dir) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const abs = join(dir, e.name);
    const rel = relative(ROOT, abs).replace(/\\/g, '/');
    if (e.isDirectory()) {
      if (SKIP_DIRS.has(e.name) || e.name.startsWith('.') && !['.claude', '.github'].includes(e.name)) continue;
      walk(abs);
      continue;
    }
    const ext = e.name.slice(e.name.lastIndexOf('.'));
    if (!EXT.has(ext) || SKIP_FILES.has(rel)) continue;
    let txt;
    try { txt = readFileSync(abs, 'utf8'); } catch { continue; }
    // pré-filtro barato antes das regex
    if (!/hue/i.test(txt)) continue;
    scanned++;
    const lines = txt.split('\n');
    for (const { re, tipo } of CONSTRUCTIONS) {
      re.lastIndex = 0;
      for (const m of txt.matchAll(re)) {
        // linha da ocorrência (pra mensagem acionável)
        const lineNo = txt.slice(0, m.index).split('\n').length;
        const lineTxt = (lines[lineNo - 1] || '').trim();
        // fora de escopo: construção do sidebar (hue por grupo) na MESMA linha
        if (/sidebar_group_hue|SIDEBAR_GROUP_HUE/.test(lineTxt)) continue;
        const declared = Number(m[1]);
        if (declared === CANON_HUE) { confirmations++; continue; }
        violations.push(`${rel}:${lineNo} — ${tipo} declara ${declared} ≠ canon ${CANON_HUE} (${canon.fonte})\n      ${lineTxt.slice(0, 160)}`);
      }
    }
  }
}
walk(ROOT);

if (violations.length) {
  console.error(`✗ ${violations.length} declaração(ões) de hue primário divergem de ${CANON_FILE} (primary_hue: ${CANON_HUE}, ${canon.fonte}):`);
  violations.forEach((v) => console.error(`   - ${v}`));
  console.error(`   → primary universal é ${CANON_HUE} (${canon.fonte}). Corrija a declaração OU, se o canon mudou, atualize ${CANON_FILE} via ADR nova.`);
  process.exit(1);
}
console.log(`✓ hue primário íntegro — ${confirmations} declaração(ões) batem com canon ${CANON_HUE} (${canon.fonte}); ${scanned} arquivos com "hue" varridos, 0 divergência.`);
