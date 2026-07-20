#!/usr/bin/env node
// Teste bite/release do hooks-manifest-generate (grade de réguas 2026-07-19 — padrão
// gate-selftest: caso bom TEM que passar, caso ruim TEM que morder; crash != morder).
//
// Hermetico: monta um repo temporario com .claude/settings.json + .claude/hooks/* +
// governance/required-checks-baseline.json e roda o gerador como subprocesso (cwd=tmp).
//
// Rodar: node scripts/governance/hooks-manifest-generate.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'hooks-manifest-generate.mjs');
const OUT = ['.claude', 'hooks', '_HOOKS-INDEX.md'];

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}
const run = (cwd, ...argv) => spawnSync('node', [SCRIPT, ...argv], { cwd, encoding: 'utf8' });

function makeRepo() {
  const tmp = mkdtempSync(join(tmpdir(), 'hooks-manifest-test-'));
  mkdirSync(join(tmp, '.claude', 'hooks'), { recursive: true });
  mkdirSync(join(tmp, 'governance'), { recursive: true });
  const hook = (f, body) => writeFileSync(join(tmp, '.claude', 'hooks', f), body, 'utf8');
  // wired: um bloqueante node (exit 2), um bloqueante deny (ps1), um advisory (exit 0)
  hook('block-x.mjs', 'process.exit(2)\n');
  hook('charter-y.ps1', "decision = 'deny'\n");
  hook('nudge-z.ps1', 'Write-Host "aviso"\nexit 0\n');
  // órfão: arquivo sem wiring
  hook('orfao-solto.mjs', 'process.exit(0)\n');
  // gêmeo cross-platform (o .ps1 é wired, o .sh não): órfão com nota específica
  hook('charter-y.sh', 'echo advisory\n');
  writeFileSync(join(tmp, '.claude', 'settings.json'), JSON.stringify({
    hooks: {
      PreToolUse: [
        { matcher: 'Write|Edit|MultiEdit', hooks: [
          { type: 'command', command: 'node .claude/hooks/block-x.mjs' },
          { type: 'command', command: 'powershell -File .claude/hooks/charter-y.ps1' },
        ] },
        { matcher: 'Bash', hooks: [
          { type: 'command', command: 'powershell -File .claude/hooks/nudge-z.ps1' },
        ] },
      ],
    },
  }, null, 2), 'utf8');
  writeFileSync(join(tmp, 'governance', 'required-checks-baseline.json'), JSON.stringify({
    classic_protection: { contexts: ['No hardcode business_id (Tier 0)', 'visual-regression'] },
    rulesets: { contexts: ['Governance Gate'] },
  }, null, 2), 'utf8');
  return tmp;
}

// ── release: repo íntegro --write + --check passam ──
const tmp = makeRepo();
const w = run(tmp, '--write');
check('--write roda sem erro (exit 0)', w.status === 0);
const md = readFileSync(join(tmp, ...OUT), 'utf8');
check('manifesto lista o hook bloqueante node com sinal exit-2', /block-x\.mjs.*exit-2/.test(md));
check('manifesto lista o hook deny (ps1) com sinal deny', /charter-y\.ps1.*deny/.test(md));
check('manifesto lista o advisory sem sinal (—)', /nudge-z\.ps1.*\| — \|/.test(md));
check('manifesto deriva ponto-de-corte geração pra Write/Edit', /geração \(pré-Write\/Edit\)/.test(md));
check('manifesto copia os gates CI do baseline', md.includes('visual-regression') && md.includes('Governance Gate'));
check('órfão solto reportado na seção Órfãos', /orfao-solto\.mjs/.test(md));
check('gêmeo cross-platform .sh reportado como órfão com nota', /charter-y\.sh.*gêmeo cross-platform/.test(md));
check('--check passa em repo recem-gerado (release)', run(tmp, '--check').status === 0);

// ── bite 1: manifesto editado à mão (drift) ──
writeFileSync(join(tmp, ...OUT), md.replace('block-x.mjs', 'block-x-EDITADO-NA-MAO.mjs'), 'utf8');
const b1 = run(tmp, '--check');
check('--check MORDE manifesto editado à mão (exit 1 + DESATUALIZADO)', b1.status === 1 && /DESATUALIZADO/.test(b1.stderr));

// ── bite 2: fantasma (wiring aponta pra arquivo inexistente) ──
run(tmp, '--write'); // regenera limpo
const cfg = JSON.parse(readFileSync(join(tmp, '.claude', 'settings.json'), 'utf8'));
cfg.hooks.PreToolUse[1].hooks.push({ type: 'command', command: 'node .claude/hooks/nao-existe-fantasma.mjs' });
writeFileSync(join(tmp, '.claude', 'settings.json'), JSON.stringify(cfg, null, 2), 'utf8');
const b2 = run(tmp, '--check');
check('--check MORDE fantasma (wiring sem arquivo → exit 1)', b2.status === 1 && /fantasma/i.test(b2.stderr) && /nao-existe-fantasma\.mjs/.test(b2.stderr));

// ── release final: remove o fantasma e regenera → volta a passar ──
cfg.hooks.PreToolUse[1].hooks.pop();
writeFileSync(join(tmp, '.claude', 'settings.json'), JSON.stringify(cfg, null, 2), 'utf8');
run(tmp, '--write');
const rf = run(tmp, '--check');
check('--check volta a passar depois de consertar (release · órfão não falha)', rf.status === 0);

// ── bite 3: settings.json JSON inválido → sai 1, não passa fingindo ──
writeFileSync(join(tmp, '.claude', 'settings.json'), '{ isso não é json', 'utf8');
const b3 = run(tmp, '--check');
check('--check MORDE settings.json inválido (exit 1)', b3.status === 1 && /ileg|inválido/i.test(b3.stderr));

rmSync(tmp, { recursive: true, force: true });

console.log('');
if (fails === 0) {
  console.log('[PASS] hooks-manifest-generate morde e libera (12/12).');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — o gerador/catraca do manifesto de hooks regrediu.`);
  process.exit(1);
}
