#!/usr/bin/env node
// Teste bite/release do skills-index-generate (US-GOV-052 P31 — padrão gate-selftest:
// caso bom TEM que passar, caso ruim TEM que morder; exit 1 por crash != morder).
//
// Hermetico: monta um repo temporario com .claude/skills/ + CLAUDE.md (marcadores AUTO)
// + tier-a-banner.mjs e roda o gerador como subprocesso (cwd=tmp).
//
// Rodar: node scripts/governance/skills-index-generate.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'skills-index-generate.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}
const run = (cwd, ...argv) => spawnSync('node', [SCRIPT, ...argv], { cwd, encoding: 'utf8' });

const MARK_BEGIN = '<!-- AUTO:SKILLS-BEGIN — gerado por scripts/governance/skills-index-generate.mjs (fonte única: frontmatter .claude/skills/*/SKILL.md). NÃO editar à mão; rode --write. -->';
const MARK_END = '<!-- AUTO:SKILLS-END -->';

function makeRepo() {
  const tmp = mkdtempSync(join(tmpdir(), 'skills-idx-test-'));
  const skill = (slug, fm) => {
    mkdirSync(join(tmp, '.claude', 'skills', slug), { recursive: true });
    writeFileSync(join(tmp, '.claude', 'skills', slug, 'SKILL.md'), `---\nname: ${slug}\n${fm}\n---\n\ncorpo\n`, 'utf8');
  };
  skill('nucleo-a', 'tier: A\nresumo: skill de seguranca sempre-on');
  skill('auto-b', 'tier: B\nauto_trigger: path\nresumo: dispara por path');
  skill('quieta-b', 'tier: B'); // B sem auto_trigger -> so na tabela, resumo dispensavel
  skill('slash-c', 'tier: C');
  skill('dormente-a', 'tier: A\nenabled: false\nresumo: dormente ate S5');
  mkdirSync(join(tmp, '.claude', 'hooks'), { recursive: true });
  writeFileSync(join(tmp, '.claude', 'hooks', 'tier-a-banner.mjs'), 'export const banner = "TIER A nucleo: nucleo-a";\n', 'utf8');
  writeFileSync(join(tmp, 'CLAUDE.md'), `# CLAUDE.md fixture\n\n## Skills\n\n${MARK_BEGIN}\n${MARK_END}\n\nresto manual intocavel\n`, 'utf8');
  return tmp;
}

// ── release: repo integro --write + --check passam ──
const tmp = makeRepo();
const w = run(tmp, '--write');
check('--write roda sem erro (exit 0)', w.status === 0);
const claude = readFileSync(join(tmp, 'CLAUDE.md'), 'utf8');
const index = readFileSync(join(tmp, '.claude', 'skills', '_SKILLS-INDEX.md'), 'utf8');
check('bloco gerado tem a skill nucleo A', claude.includes('**nucleo-a**'));
check('bloco gerado tem a auto-trigger com gatilho', claude.includes('**auto-b** _(path)_'));
check('bloco gerado tem a dormente', claude.includes('**dormente-a**'));
check('bloco NAO destaca tier B sem auto_trigger nem tier C', !claude.includes('**quieta-b**') && !claude.includes('**slash-c**'));
check('resto manual do CLAUDE.md preservado fora dos marcadores', claude.includes('resto manual intocavel'));
check('_SKILLS-INDEX.md lista TODAS (5) na tabela', ['nucleo-a', 'auto-b', 'quieta-b', 'slash-c', 'dormente-a'].every((s) => index.includes(`| ${s} |`)));
check('--check passa em repo recem-gerado (release)', run(tmp, '--check').status === 0);

// ── bite 1: bloco do CLAUDE.md editado a mao (drift) ──
writeFileSync(join(tmp, 'CLAUDE.md'), claude.replace('**nucleo-a**', '**nucleo-a-EDITADO-NA-MAO**'), 'utf8');
const b1 = run(tmp, '--check');
check('--check MORDE bloco CLAUDE.md editado a mao (exit 1 + acusacao)', b1.status === 1 && /DESATUALIZADO/.test(b1.stderr));

// ── bite 2: _SKILLS-INDEX.md driftado ──
run(tmp, '--write');
writeFileSync(join(tmp, '.claude', 'skills', '_SKILLS-INDEX.md'), index + '\nlinha intrusa\n', 'utf8');
const b2 = run(tmp, '--check');
check('--check MORDE _SKILLS-INDEX.md driftado', b2.status === 1 && /_SKILLS-INDEX\.md/.test(b2.stderr));

// ── bite 3: frontmatter sem tier ──
run(tmp, '--write');
writeFileSync(join(tmp, '.claude', 'skills', 'quieta-b', 'SKILL.md'), '---\nname: quieta-b\n---\n\ncorpo\n', 'utf8');
const b3 = run(tmp, '--check');
check('--check MORDE skill sem tier (fonte suja)', b3.status === 1 && /tier "\(vazio\)" inválido/.test(b3.stderr));
writeFileSync(join(tmp, '.claude', 'skills', 'quieta-b', 'SKILL.md'), '---\nname: quieta-b\ntier: B\n---\n\ncorpo\n', 'utf8');

// ── bite 4: destaque sem resumo ──
writeFileSync(join(tmp, '.claude', 'skills', 'auto-b', 'SKILL.md'), '---\nname: auto-b\ntier: B\nauto_trigger: path\n---\n\ncorpo\n', 'utf8');
const b4 = run(tmp, '--check');
check('--check MORDE destaque sem `resumo:`', b4.status === 1 && /não tem `resumo:`/.test(b4.stderr));
writeFileSync(join(tmp, '.claude', 'skills', 'auto-b', 'SKILL.md'), '---\nname: auto-b\ntier: B\nauto_trigger: path\nresumo: dispara por path\n---\n\ncorpo\n', 'utf8');

// ── bite 5: tier A com auto_trigger (contradicao ADR 0225) ──
writeFileSync(join(tmp, '.claude', 'skills', 'nucleo-a', 'SKILL.md'), '---\nname: nucleo-a\ntier: A\nauto_trigger: path\nresumo: x\n---\n\ncorpo\n', 'utf8');
const b5 = run(tmp, '--check');
check('--check MORDE tier A com auto_trigger (contradicao)', b5.status === 1 && /contradição/.test(b5.stderr));
writeFileSync(join(tmp, '.claude', 'skills', 'nucleo-a', 'SKILL.md'), '---\nname: nucleo-a\ntier: A\nresumo: skill de seguranca sempre-on\n---\n\ncorpo\n', 'utf8');

// ── bite 6: nucleo ausente do banner (4a fonte) ──
writeFileSync(join(tmp, '.claude', 'hooks', 'tier-a-banner.mjs'), 'export const banner = "TIER A nucleo: outra-coisa";\n', 'utf8');
const b6 = run(tmp, '--check');
check('--check MORDE nucleo A ausente do banner SessionStart', b6.status === 1 && /ausente do banner/.test(b6.stderr));
writeFileSync(join(tmp, '.claude', 'hooks', 'tier-a-banner.mjs'), 'export const banner = "TIER A nucleo: nucleo-a";\n', 'utf8');

// ── release final: consertado tudo, volta a passar ──
run(tmp, '--write');
check('--check volta a passar depois de consertar (release)', run(tmp, '--check').status === 0);

rmSync(tmp, { recursive: true, force: true });

console.log('');
if (fails === 0) {
  console.log('[PASS] skills-index-generate morde e libera (14/14).');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — o gerador/catraca de skills regrediu.`);
  process.exit(1);
}
