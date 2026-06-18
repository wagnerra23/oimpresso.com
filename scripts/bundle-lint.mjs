#!/usr/bin/env node
// bundle-lint.mjs — esteira ≠ armazém (régua 6 da memória de proveniência).
//
// O bundle do design (prototipo-ui/cowork-*/) é ESTEIRA: só carrega app-vivo +
// screenshots + handoff-ATIVO + orientação. RESÍDUO de processo (adversário/tribunal/
// avaliação, _arquivo/benchmark/uploads, prompts já processados GAPS_v*/FORCE_*) é peso
// morto que JÁ cumpriu o papel — a CONCLUSÃO durável vai pro memory/ (armazém, padrão do
// projeto), o CRU sai do git. Este lint flagra o resíduo que voltou/ficou no bundle.
//
// "Auditoria*" bare NÃO é resíduo aqui — audit pode ser conhecimento a INGERIR (vira
// memory/, não delete). Por isso fica fora do padrão (decisão consciente).
//
// Node puro, sem deps. Exit 1 se achar resíduo (MORDE); o workflow é advisory (não-required,
// veredito do adversário). Self-test: node scripts/bundle-lint.test.mjs
//
// Doc: memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md + prototipo-ui/PROTOCOL.md

import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = (() => { const i = process.argv.indexOf('--root'); return i >= 0 ? resolve(process.argv[i + 1]) : resolve(HERE, '..'); })();
const log = (...a) => console.log(...a);

// Resíduo de processo do design (peso morto · conclusão vai pro memory/, cru sai do git).
const RESIDUO = [
  /\/_arquivo\//i, /\/benchmark\//i, /\/uploads\//i, /\.thumbnail$/i,
  /GAPS_v\d/i, /\/FORCE_/i, /(Advers[áa]rio|Tribunal|Avaliac)/i,
];

function git(args) {
  try { return execSync(`git ${args}`, { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim(); }
  catch { return ''; }
}

function lint() {
  const all = (git('ls-files') || '').split('\n').filter(Boolean);
  const bundle = all.filter(p => /^prototipo-ui\/cowork-/.test(p));
  if (!bundle.length) { log('bundle-lint: nenhum bundle versionado (prototipo-ui/cowork-*) — nada a checar.'); return 0; }
  const hits = bundle.filter(f => RESIDUO.some(re => re.test(f)));
  log(`bundle-lint · ${bundle.length} arquivo(s) no bundle · ${hits.length} resíduo(s)`);
  for (const h of hits) log('X resíduo: ' + h);
  if (hits.length) {
    log(`\n❌ ${hits.length} resíduo(s) de processo no bundle. Esteira ≠ armazém:`);
    log('   - conclusão durável → memory/ (padrão do projeto); o cru sai do git.');
    log('   - ver RUNBOOK-contrato-de-tela.md §"esteira ≠ armazém" + prototipo-ui/PROTOCOL.md.');
    return hits.length;
  }
  log('✅ bundle limpo (só app-vivo + screenshots + handoff-ativo + orientação).');
  return 0;
}

process.exit(lint() ? 1 : 0);
