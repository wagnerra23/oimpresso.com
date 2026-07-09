#!/usr/bin/env node
// protocolo.config.mjs — FONTE ÚNICA EXECUTÁVEL do protocolo de aplicação de protótipo (skill
// `aplicar-prototipo`). O "painel" do RUNBOOK: IDs, paths fixos e mapa fase→comando num só lugar.
//
// PROBLEMA que resolve (Wagner 2026-07-09: "o que mais precisa ficar procurando? tudo tem que
// estar documentado para o processo"): os IDs dos 2 projetos Cowork, o path do staging fixo e o
// mapa fase→comando viviam só em PROSA (INDEX §0.2, ADR 0325, RUNBOOK de 190 linhas, runbooks).
// O agente RECONSTRUÍA isso lendo 5 docs — cada leitura = um "procura" = risco de pular/errar.
// A confusão entre os 2 IDs de nome parecido já mordeu 3× (INDEX §0.2, 2026-07-06). Aqui vira
// CONSTANTE nomeada + mapa executável + selftest que trava drift: "o protocolo sabe" deixa de
// depender de eu ter lido o INDEX.
//
// NÃO reimplementa nada — re-exporta os motores canônicos (normalize/contentHash do
// cowork-mirror-freshness · resolveAncora do ancora). Só CONSOLIDA o que estava espalhado.
//
// USO:
//   node prototipo-ui/protocolo.config.mjs            # imprime o painel (IDs, paths, fases)
//   node prototipo-ui/protocolo.config.mjs --json     # idem, JSON
//   node prototipo-ui/protocolo.config.mjs --selftest # trava se ID/path/script sumir (CI)
//
// IMPORT (scripts + o próprio agente):
//   import { COWORK_PROJECT_ID, STAGING_DIR, FASES } from './protocolo.config.mjs'
//
// Refs: ADR 0325 (pull direto) · ADR 0324 (identidade normalizada) · INDEX-DESIGN-MEMORIAS §0.2 ·
//       prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md (as 7 fases −1..5).

import { existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { homedir } from 'node:os';

// binding local + re-export (re-export puro não cria binding usável no selftest deste módulo)
import { normalize, contentHash } from '../scripts/governance/cowork-mirror-freshness.mjs';
import { resolveAncora } from './ancora.mjs';
export { normalize, contentHash, resolveAncora };

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(HERE, '..');

// ── PROJETOS Cowork (ADR 0325 · INDEX §0.2) ─────────────────────────────────────
// Os DOIS têm nome parecido — NÃO confundir (mordeu 3× em 2026-07-06). E ATENÇÃO: só o DS é
// listado/writable em `DesignSync.list_projects`; o Cowork (as TELAS) alcança-se por ID EXPLÍCITO
// — nunca "descobrir" por lista. Por isso o ID tem que viver aqui, não na memória do agente.
export const COWORK_PROJECT_ID = '019dcfd3-6ef2-7ee6-8512-b1b0e5544e58';        // "Oimpresso ERP Comunicação Visual" — FONTE DAS TELAS (*-page.jsx, 1337 arq)
export const DESIGN_SYSTEM_PROJECT_ID = '019dd02f-d2d0-7ba6-a57f-24b3ddd073ac'; // "Office Impresso — Design System" — biblioteca do DS (tokens/componentes)

export const PROJETOS = {
  cowork:       { id: COWORK_PROJECT_ID,        nome: 'Oimpresso ERP Comunicação Visual', papel: 'telas',  listado: false },
  designSystem: { id: DESIGN_SYSTEM_PROJECT_ID, nome: 'Office Impresso — Design System',   papel: 'ds',     listado: true  },
};

// ── PATHS FIXOS (as âncoras do protocolo dependem destes — RUNBOOK Fase −1) ─────
// STAGING_DIR: 1 destino FIXO fora do repo, sobrescrito a cada import (path estável → âncoras não erram).
// MIRROR_DIR: SSOT do design no repo, build-only (R1 do cowork-ssot-guard rejeita .md aqui).
export const STAGING_DIR = join(homedir(), 'Downloads', '_cowork-handoff-staging');
export const MIRROR_DIR  = join(REPO_ROOT, 'prototipo-ui', 'cowork');

// ── PRÉ-FLIGHT da Fase 4 — os gates que a tela nova zera ANTES do PR ────────────
// ("funciona no staging ≠ passa no portão": incidente perfil 2026-06-24 tripou 6 gates no PR).
export const PREFLIGHT_GATES = [
  'node scripts/layout-primitives-guard.mjs',
  'node scripts/casos-coverage-guard.mjs',
  'npm run lint:baseline:check',
  'node_modules/.bin/tsc --noEmit',
  'node prototipo-ui/ds-guard.mjs <arquivos-tocados>',
  'node scripts/governance/cowork-ssot-guard.mjs',
];

// ── MAPA FASE → comando(s) reais (o "painel" executável do RUNBOOK) ─────────────
export const FASES = [
  { fase: '-1', nome: 'Importar/baixar o design', comandos: [
      'DesignSync.get_file(projectId=COWORK_PROJECT_ID, path=<âncora>) → STAGING_DIR   # pull direto, agente logado (ADR 0325)',
      'node prototipo-ui/importar-bundle.mjs "<zip>"                                    # fallback bundle cheio',
      'pwsh prototipo-ui/check-handoff.ps1 -Zip <zip>                                   # portão barato "mudou?"',
    ], selftest: 'node prototipo-ui/handoff-changed.mjs --selftest' },
  { fase: '0/0.5', nome: 'Detectar + manifesto', comandos: [
      'node prototipo-ui/detectar-telas.mjs --staging <dir> --json --strict',
    ], selftest: 'node prototipo-ui/detectar-telas.mjs --selftest' },
  { fase: '1', nome: 'Mapear / comparar', comandos: [
      'node prototipo-ui/ancora.mjs <Mod/Tela>',
      'node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>',
      'node prototipo-ui/design-diff.mjs --compare prod.json design.json --check',
    ], selftest: 'node prototipo-ui/style-fingerprint.mjs --selftest' },
  { fase: '3/4', nome: 'Registrar + aplicar região', comandos: [
      'node prototipo-ui/gerar-contrato.mjs <gap.md>',
      'node scripts/contrato-de-tela.mjs --contract <c.json> --contract-alvo <Pages/...>',
      'node prototipo-ui/recortar-regiao.mjs --contract <c.json> --bboxes <b.json> --png <shot.png> --out <dir>',
    ], selftest: 'node prototipo-ui/gerar-contrato.mjs --selftest' },
  { fase: '4-preflight', nome: 'Gates antes do PR', comandos: PREFLIGHT_GATES },
  { fase: '5', nome: 'Fechar o loop', comandos: [
      'node scripts/governance/anchor-lint.mjs --check memory/requisitos/<Mod>/SPEC.md',
    ], selftest: 'node prototipo-ui/integrity-check.mjs' },
];

// ── selftest hermético (trava drift — vai pro design-memory-gate no CI) ─────────
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
function scriptsReferenciados() {
  const cmds = [...PREFLIGHT_GATES, ...FASES.flatMap((f) => [...(f.comandos || []), f.selftest].filter(Boolean))];
  const out = new Set();
  for (const c of cmds) {
    const m = c.match(/(?:node|pwsh)\s+([^\s"']+\.(?:mjs|ps1))/);
    if (m) out.add(m[1]);
  }
  return [...out];
}
function selftest() {
  const fails = [];
  if (!UUID.test(COWORK_PROJECT_ID)) fails.push('COWORK_PROJECT_ID não é UUID');
  if (!UUID.test(DESIGN_SYSTEM_PROJECT_ID)) fails.push('DESIGN_SYSTEM_PROJECT_ID não é UUID');
  if (COWORK_PROJECT_ID === DESIGN_SYSTEM_PROJECT_ID) fails.push('os 2 IDs colidiram (anti-confusão dos projetos)');
  if (!existsSync(MIRROR_DIR)) fails.push(`MIRROR_DIR ausente no repo: ${MIRROR_DIR}`);
  for (const fn of [['normalize', normalize], ['contentHash', contentHash], ['resolveAncora', resolveAncora]]) {
    if (typeof fn[1] !== 'function') fails.push(`motor re-exportado quebrou: ${fn[0]} não é função`);
  }
  if (typeof contentHash === 'function' && contentHash('abc') !== contentHash('abc')) {
    fails.push('contentHash não-determinístico');
  }
  const scripts = scriptsReferenciados();
  for (const s of scripts) {
    if (!existsSync(join(REPO_ROOT, s))) fails.push(`script referenciado no mapa FASES não existe: ${s}`);
  }
  if (fails.length) { console.error('SELFTEST FALHOU:\n - ' + fails.join('\n - ')); process.exit(1); }
  console.log(`✓ protocolo.config selftest OK — 2 IDs válidos+distintos · MIRROR_DIR presente · ${scripts.length} scripts do mapa existem · motores (normalize/contentHash/resolveAncora) vivos.`);
  process.exit(0);
}

function painel() {
  console.log('PROTOCOLO DE APLICAÇÃO DE PROTÓTIPO — fonte única (protocolo.config.mjs)\n');
  console.log('PROJETOS Cowork (ADR 0325 · só por ID — NÃO confundir):');
  console.log(`  telas   ${COWORK_PROJECT_ID}  "${PROJETOS.cowork.nome}"  [não-listado, por ID]`);
  console.log(`  ds      ${DESIGN_SYSTEM_PROJECT_ID}  "${PROJETOS.designSystem.nome}"  [listado]`);
  console.log(`\nPATHS FIXOS:\n  STAGING_DIR  ${STAGING_DIR}\n  MIRROR_DIR   ${MIRROR_DIR}`);
  console.log('\nFASES (fase → comando real):');
  for (const f of FASES) {
    console.log(`  [${f.fase}] ${f.nome}`);
    for (const c of f.comandos) console.log(`      ${c}`);
    if (f.selftest) console.log(`      selftest: ${f.selftest}`);
  }
}

const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) selftest();
  else if (argv.includes('--json')) {
    console.log(JSON.stringify({ projetos: PROJETOS, stagingDir: STAGING_DIR, mirrorDir: MIRROR_DIR, fases: FASES, preflightGates: PREFLIGHT_GATES }, null, 2));
  } else painel();
}
