#!/usr/bin/env node
// ds-push.mjs — orquestrador determinístico do PUSH git→espelho (passos 1-3 do
//   runbook design-sync-push.md colapsados em UM comando + auto-validação VALOR:0).
//
// POR QUE EXISTE: hoje o push é semi-manual — montar o scaffold (ds-mirror-build),
//   emitir o companion (ds-domains-companion), validar (ds-token-diff --companion) e só
//   então empurrar via DesignSync. Quatro passos que "alguém tem que lembrar de rodar na
//   ordem certa" (o anti-padrão da ADR 0329). Este comando faz os três primeiros de uma vez,
//   VALIDA sozinho (VALOR:0) e imprime a chamada DesignSync exata do 4º.
//
// NÃO FAZ o upload DesignSync: o `finalize_plan`/`write_files` precisam do login claude.ai
//   (MCP interativo) — o CI/headless não tem. Seria desonesto fingir. O script MONTA + VALIDA
//   o bundle e IMPRIME a chamada de 2 linhas que o operador roda no passo interativo.
//
// REUSA (não reinventa — proibicoes §5): ds-mirror-build.mjs (scaffold+valores) ·
//   ds-domains-companion.mjs (companion de domínio) · ds-token-diff.mjs --companion --json
//   (gate VALOR:0). Invoca os três como subprocessos node — zero lógica de parsing duplicada.
//
// Uso:
//   node scripts/design-sync/ds-push.mjs [--tokens <dir>] [--scaffold <colors_and_type.css>]
//        [--out <dir>] [--write]
//   default scaffold = scripts/design-sync/mirror-snapshot/colors_and_type.css (último espelho)
//   default tokens   = resources/css/tokens
//   default out      = scripts/design-sync/.push-bundle  (gitignored)
//   --write          = também refresca o mirror-snapshot commitado (colors_and_type + cockpit_domains)
//                      — o snapshot do sentinela ds-mirror-drift (passo 5 do runbook).
//
// Sai != 0 se a validação der VALOR > 0 (o scaffold/tokens divergiram — NÃO empurre;
//   é sinal de que a montagem errou ou o scaffold está velho).

import { readFileSync, writeFileSync, mkdirSync, copyFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..', '..');

const args = process.argv.slice(2);
const flag = (name, def) => {
  const i = args.indexOf(name);
  return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : def;
};
const has = (name) => args.includes(name);

const tokensDir = resolve(flag('--tokens', join(REPO, 'resources', 'css', 'tokens')));
const scaffold = resolve(flag('--scaffold', join(HERE, 'mirror-snapshot', 'colors_and_type.css')));
const outDir = resolve(flag('--out', join(HERE, '.push-bundle')));
const doWrite = has('--write');

const MIRROR = join(HERE, 'mirror-snapshot');
const PROJECT_ID = '019dd02f-d2d0-7ba6-a57f-24b3ddd073ac';

function node(script, scriptArgs) {
  return execFileSync('node', [join(HERE, script), ...scriptArgs], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
}

// Pré-checks legíveis (falha cedo com mensagem, não stack trace).
if (!existsSync(scaffold)) {
  console.error(`ds-push: scaffold ausente — ${scaffold}\n  (o mirror-snapshot ainda não foi semeado; rode o runbook design-sync-push.md passo 5, ou passe --scaffold).`);
  process.exit(1);
}
for (const f of ['_generated-inertia-theme.css', '_generated-cockpit-light.css']) {
  if (!existsSync(join(tokensDir, f))) {
    console.error(`ds-push: tokens não buildados — falta ${f} em ${tokensDir}\n  rode: npm run tokens:build`);
    process.exit(1);
  }
}

console.log('═══ ds-push (git → espelho) ═══\n');
console.log(`tokens   : ${tokensDir}`);
console.log(`scaffold : ${scaffold}`);
console.log(`out      : ${outDir}${doWrite ? '  (+ refresca mirror-snapshot)' : '  (dry — sem --write)'}\n`);

// PASSO 1 — montar colors_and_type.css (scaffold + valores do git, via ds-mirror-build).
const builtCT = node('ds-mirror-build.mjs', [scaffold, tokensDir]);
// PASSO 2 — emitir o companion cockpit_domains.css (via ds-domains-companion).
const builtDOM = node('ds-domains-companion.mjs', [tokensDir]);

mkdirSync(outDir, { recursive: true });
const outCT = join(outDir, 'colors_and_type.css');
const outDOM = join(outDir, 'cockpit_domains.css');
writeFileSync(outCT, builtCT);
writeFileSync(outDOM, builtDOM);
console.log('✓ montado    colors_and_type.css + cockpit_domains.css');

// PASSO 3 — validar VALOR:0 (mesmo motor do sentinela P3).
const diffRaw = node('ds-token-diff.mjs', [outCT, tokensDir, '--companion', outDOM, '--json']);
let diff;
try { diff = JSON.parse(diffRaw); } catch {
  console.error('ds-push: ds-token-diff --json não parseou:\n' + diffRaw);
  process.exit(1);
}
const total = diff.totalDiverge ?? 0;
if (total > 0) {
  console.error(`\n✗ VALIDAÇÃO FALHOU — divergências de VALOR: ${total}. NÃO empurre.`);
  for (const [scope, r] of Object.entries(diff.report || {})) {
    for (const d of r.diverge || []) console.error(`   ✗ [${scope}] ${d.k}  git:${d.gv} → montado:${d.dv}`);
  }
  console.error('\nO scaffold montado não bate com o git — a montagem (passo 2 do runbook) errou, ou o scaffold está velho.');
  process.exit(1);
}
console.log(`✓ validado   divergências de VALOR: 0  (bundle == git canon)\n`);

// PASSO 4 (opcional) — refrescar o snapshot commitado do sentinela.
if (doWrite) {
  copyFileSync(outCT, join(MIRROR, 'colors_and_type.css'));
  copyFileSync(outDOM, join(MIRROR, 'cockpit_domains.css'));
  console.log('✓ refrescado scripts/design-sync/mirror-snapshot/{colors_and_type,cockpit_domains}.css');
  console.log('  → commite o snapshot + rode: node scripts/governance/ds-mirror-drift.mjs --update-baseline\n');
}

// Manifesto do passo interativo (o upload que este script NÃO faz por não ter login claude.ai).
console.log('── próximo passo (interativo, precisa de login claude.ai — DesignSync) ──');
console.log(`DesignSync finalize_plan  projectId=${PROJECT_ID}  localDir=${outDir} \\`);
console.log(`           writes=[colors_and_type.css, cockpit_domains.css]`);
console.log(`DesignSync write_files    planId=<do finalize_plan> \\`);
console.log(`           files=[{path:colors_and_type.css, localPath:colors_and_type.css},`);
console.log(`                  {path:cockpit_domains.css,  localPath:cockpit_domains.css}]`);
console.log('\nApós empurrar: atualize o commit-fonte no README do espelho (proveniência) e');
console.log('confirme o loop com  node scripts/design-sync/ds-token-diff.mjs <colors vivo> resources/css/tokens --companion <domains vivo>  → VALOR:0.');
