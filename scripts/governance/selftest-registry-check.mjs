#!/usr/bin/env node
// selftest-registry-check.mjs — P15 entrega 3: teste .mjs órfão de workflow (advisory).
//
// POR QUE EXISTE: o anti-padrão "Modules/X/Tests/ sem registrar em phpunit.xml → falsa
// cobertura" (proibicoes.md §Código) REENCARNOU em .mjs — meta-teste do distiller órfão de
// CI, wrapper sem selftest (adversário 2026-07-13 wf_33e38126; chip A3 fechou os casos da
// época, este guard impede o PRÓXIMO). Um `*.test.mjs` que existe no repo mas nenhum
// workflow invoca passa VERDE na máquina do dev e NUNCA roda no CI — cobertura narrada,
// não testada.
//
// O QUE FAZ: varre `scripts/**/*.test.mjs` + `.claude/hooks/*.test.mjs` e confere se o
// path (posix) aparece em algum `.github/workflows/*.yml` — órfão avermelha o advisory.
// Substring simples e determinístico: registrado = o path literal aparece no YAML
// (é como todos os steps invocam: `run: node scripts/...`). Sem parser de YAML de
// propósito — menos superfície, zero deps.
//
// É CONSISTÊNCIA INTERNA (o teste existe mas o CI não o conhece), prima do doneness v2 —
// NÃO gate-de-presença (não exige "teste no diff"; exige que teste EXISTENTE não seja
// letra morta). ADVISORY de nascença (lei ADR 0314: required = só Tier-0): exit 0 no modo
// default; --check (exit 1) é o primitivo de promoção futura por calendário (ADR 0275).
//
// USO (na raiz do repo):
//   node scripts/governance/selftest-registry-check.mjs             # relatório advisory (exit 0)
//   node scripts/governance/selftest-registry-check.mjs --json      # JSON determinístico
//   node scripts/governance/selftest-registry-check.mjs --check     # exit 1 se houver órfão
//   node scripts/governance/selftest-registry-check.mjs --selftest  # fixtures herméticas (CI)
//
// Node puro (fs). Sem deps, sem DB, sem PHP. Idioma: clone de doneness-lint.mjs (ADR 0302).

import { readdirSync, readFileSync, existsSync, mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const posix = (p) => p.replace(/\\/g, '/');

/** varre dir recursivamente por *.test.mjs — retorna paths RELATIVOS posix ao root. */
export function collectTestFiles(root) {
  const out = [];
  const walk = (dir) => {
    let entries;
    try { entries = readdirSync(join(root, dir), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      if (e.name === 'node_modules' || e.name.startsWith('.git')) continue;
      const rel = dir ? `${dir}/${e.name}` : e.name;
      if (e.isDirectory()) walk(rel);
      else if (e.name.endsWith('.test.mjs')) out.push(posix(rel));
    }
  };
  walk('scripts');
  // hooks: não-recursivo de propósito (testes de hook vivem flat em .claude/hooks/)
  try {
    for (const e of readdirSync(join(root, '.claude', 'hooks'))) {
      if (e.endsWith('.test.mjs')) out.push(`.claude/hooks/${e}`);
    }
  } catch { /* sem hooks dir — ok */ }
  return out.sort();
}

/** concatena o conteúdo de todos os workflows (.yml/.yaml). */
export function collectWorkflowText(root) {
  const dir = join(root, '.github', 'workflows');
  if (!existsSync(dir)) return '';
  return readdirSync(dir)
    .filter((f) => /\.ya?ml$/.test(f)).sort()
    .map((f) => readFileSync(join(dir, f), 'utf8'))
    .join('\n');
}

/** órfão = test file cujo path literal não aparece em nenhum workflow. */
export function findOrphans(testFiles, workflowText) {
  return testFiles.filter((f) => !workflowText.includes(f));
}

function report(root, { json = false } = {}) {
  const tests = collectTestFiles(root);
  const wfText = collectWorkflowText(root);
  const orphans = findOrphans(tests, wfText);
  const out = {
    _meta: {
      guard: 'selftest-registry — teste .mjs órfão de workflow (P15 entrega 3). Reencarnação .mjs do "Tests/ sem phpunit.xml = falsa cobertura" (proibicoes §Código).',
      generator: 'scripts/governance/selftest-registry-check.mjs',
      regra: 'órfão = *.test.mjs em scripts/** ou .claude/hooks/ cujo path NÃO aparece em .github/workflows/*.yml. Registrado = path literal no YAML.',
      fase: 'ADVISORY (lei ADR 0314) — exit 0 no default; --check (exit 1) é o primitivo de promoção (calendário ADR 0275).',
      determinismo: 'sem timestamps/sha — re-run sem mudança = diff vazio',
    },
    summary: { tests_total: tests.length, registrados: tests.length - orphans.length, orfaos: orphans.length },
    orfaos: orphans,
  };
  if (json) { process.stdout.write(JSON.stringify(out, null, 2) + '\n'); return out; }
  console.log(`\n  SELFTEST-REGISTRY — teste .mjs órfão de workflow (P15) · ${tests.length} testes · ${orphans.length} órfão(s)\n`);
  if (orphans.length) {
    for (const o of orphans) console.log(`  🔴 ÓRFÃO: ${o} — existe no repo, nenhum workflow invoca (cobertura narrada, não testada)`);
    console.log(`\n  Fix: adicionar step em .github/workflows/governance-script-tests.yml (\`run: node <path>\`)`);
    console.log(`  ou remover o teste morto. Origem: proibicoes §"Tests/ sem phpunit.xml" + P15 entrega 3.`);
  } else {
    console.log('  🟢 zero órfãos — todo *.test.mjs está invocado em algum workflow.');
  }
  console.log('');
  return out;
}

// ── selftest hermético (fixtures em tmp — CI roda com --selftest) ────────────────
function selftest() {
  let fails = 0;
  const check = (n, c, extra = '') => { console.log((c ? '[OK]   ' : '[FAIL] ') + n + (c ? '' : '  → ' + extra)); if (!c) fails++; };
  const tmp = mkdtempSync(join(tmpdir(), 'selftest-registry-'));
  mkdirSync(join(tmp, 'scripts', 'governance'), { recursive: true });
  mkdirSync(join(tmp, '.claude', 'hooks'), { recursive: true });
  mkdirSync(join(tmp, '.github', 'workflows'), { recursive: true });
  writeFileSync(join(tmp, 'scripts', 'governance', 'registrado.test.mjs'), '// ok\n');
  writeFileSync(join(tmp, 'scripts', 'governance', 'orfao.test.mjs'), '// órfão\n');
  writeFileSync(join(tmp, '.claude', 'hooks', 'hook-orfao.test.mjs'), '// órfão hook\n');
  writeFileSync(join(tmp, 'scripts', 'governance', 'nao-teste.mjs'), '// não é .test.mjs\n');
  writeFileSync(join(tmp, '.github', 'workflows', 'ci.yml'), 'jobs:\n  t:\n    steps:\n      - run: node scripts/governance/registrado.test.mjs\n');

  const tests = collectTestFiles(tmp);
  check('coleta os 3 .test.mjs (scripts recursivo + hooks flat)', tests.length === 3, JSON.stringify(tests));
  check('não coleta .mjs comum', !tests.includes('scripts/governance/nao-teste.mjs'));
  const orphans = findOrphans(tests, collectWorkflowText(tmp));
  check('registrado NÃO é órfão', !orphans.includes('scripts/governance/registrado.test.mjs'));
  check('órfão de scripts detectado', orphans.includes('scripts/governance/orfao.test.mjs'), JSON.stringify(orphans));
  check('órfão de hooks detectado', orphans.includes('.claude/hooks/hook-orfao.test.mjs'));

  // bite/release E2E via exit code (--check morde, default advisory não)
  const me = process.argv[1];
  const run = (...args) => spawnSync(process.execPath, [me, ...args], { cwd: tmp, encoding: 'utf8' });
  check('default (advisory): exit 0 mesmo com órfão', run().status === 0);
  check('--check: exit 1 com órfão (bite)', run('--check').status === 1);
  writeFileSync(join(tmp, '.github', 'workflows', 'ci.yml'),
    'jobs:\n  t:\n    steps:\n      - run: node scripts/governance/registrado.test.mjs\n      - run: node scripts/governance/orfao.test.mjs\n      - run: node .claude/hooks/hook-orfao.test.mjs\n');
  check('--check: exit 0 quando todos registrados (release)', run('--check').status === 0);
  const j = JSON.parse(run('--json').stdout);
  check('--json determinístico com summary', j.summary.tests_total === 3 && j.summary.orfaos === 0, JSON.stringify(j.summary));

  rmSync(tmp, { recursive: true, force: true });
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — órfão morde em --check, registrado solta; advisory default exit 0 (P15 entrega 3 · ADR 0314).');
  process.exit(fails ? 1 : 0);
}

// ── entry-point ──────────────────────────────────────────────────────────────────
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) selftest();
  else {
    const out = report(process.cwd(), { json: process.argv.includes('--json') });
    process.exit(process.argv.includes('--check') && out.summary.orfaos > 0 ? 1 : 0);
  }
}
