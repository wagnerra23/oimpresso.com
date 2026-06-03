#!/usr/bin/env node
// protocol-freshness.mjs — frescor do PROTOCOLO (UC → charter PRECISA TER → GUARD).
//
// Pergunta canônica ([W]: "põe o botão e que nunca mais saia aquela merda de lá"):
// cada Caso de Uso ("A tela precisa:") das telas canon está AMARRADO a um GUARD Pest
// `uc-<id>`? O doc de casos para de defasar porque o que existe está travado no teste,
// e o que falta ACENDE aqui (não fica esquecido em prosa).
//
// Espelha em NODE o padrão de `review-freshness.mjs` (#2078): ratchet via baseline
// (ADR 0209) — a dívida herdada (UC sem cobertura HOJE) vive em
// `protocol-freshness-baseline.json`; o gate só FALHA por uma REGRESSÃO nova:
//   (a) GUARD some — UC guard=true sem teste `uc-<id>` linkado (regressão dura);
//   (b) tela canon sem charter (fora do baseline);
//   (c) charter cita UC que não existe mais no registro (UC morto).
// Gaps conhecidos (UC guard=false) são ADVISORY — acendem no digest, entram no baseline.
//
// A LEI (PROTOCOL/charter) continua de [W]: o check só ACENDE e o [CL] propõe (§10.4).
//
// Uso:
//   node prototipo-ui/audit/protocol-freshness.mjs                 # checa (exit 1 só em regressão)
//   node prototipo-ui/audit/protocol-freshness.mjs --json          # saída JSON (+ storage/reports)
//   node prototipo-ui/audit/protocol-freshness.mjs --write-baseline

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const REGISTRY = join(HERE, 'uc-registry.json');
const BASELINE = join(HERE, 'protocol-freshness-baseline.json');
const TESTS_DIR = join(REPO, 'tests');
const argv = process.argv.slice(2);
const FLAG = (f) => argv.includes(f);

function read(p) { return readFileSync(p, 'utf8'); }

// junta todo o texto dos testes Pest uma vez (procura tags `uc-<id>`).
function allTestsText() {
  const acc = [];
  const walk = (dir) => {
    for (const name of readdirSync(dir)) {
      const p = join(dir, name);
      const st = statSync(p);
      if (st.isDirectory()) walk(p);
      else if (name.endsWith('.php')) acc.push(read(p));
    }
  };
  if (existsSync(TESTS_DIR)) walk(TESTS_DIR);
  return acc.join('\n');
}

function ucTag(id) {
  return 'uc-' + id.replace(/^UC-/, '').toLowerCase();
}

// extrai tokens UC-xxx citados num charter (pra detectar UC morto — item c).
function ucsCitadosNoCharter(src) {
  const m = src.match(/UC-[A-Z0-9]+/g) || [];
  return [...new Set(m)];
}

function audit() {
  const registry = JSON.parse(read(REGISTRY));
  const testsText = allTestsText();
  // O GUARD pode ser linkado de 2 formas: (1) tag literal `'uc-<id>'` num teste
  // manual, ou (2) um teste GERADO a partir do registro (itera uc-registry.json e
  // cria 1 teste/UC guard=true). Se o gerador está wired, todo guard=true está
  // coberto — apagar o gerador = regressão massiva (todos viram guard_quebrado).
  const geradorWired = testsText.includes('uc-registry.json');

  const semCobertura = [];   // (advisory) UC guard=false — gap conhecido
  const guardQuebrado = [];  // (regressão) UC guard=true sem teste uc-<id>
  const charterAusente = []; // (regressão) tela canon sem charter
  const ucMorto = [];        // (regressão) charter cita UC fora do registro
  const cobertos = [];

  const idsRegistro = new Set();
  for (const s of registry.screens || []) {
    for (const uc of s.ucs || []) idsRegistro.add(uc.uc);
  }

  for (const s of registry.screens || []) {
    const charterAbs = join(REPO, s.charter);
    if (!existsSync(charterAbs)) {
      charterAusente.push(`${s.id}:${s.charter}`);
    } else {
      // item (c) — UC citado no charter mas ausente do registro = UC morto.
      for (const cit of ucsCitadosNoCharter(read(charterAbs))) {
        if (!idsRegistro.has(cit)) ucMorto.push(`${s.id}:${cit}`);
      }
    }

    for (const uc of s.ucs || []) {
      const tag = ucTag(uc.uc);
      if (uc.guard) {
        const linkadoLiteral = testsText.includes(`'${tag}'`) || testsText.includes(`"${tag}"`);
        if (geradorWired || linkadoLiteral) {
          cobertos.push(`${s.id}:${uc.uc}`);
        } else {
          guardQuebrado.push(`${s.id}:${uc.uc} (tag ${tag} ausente nos testes)`);
        }
      } else {
        semCobertura.push(`${s.id}:${uc.uc}`);
      }
    }
  }

  return { cobertos, semCobertura, guardQuebrado, charterAusente, ucMorto };
}

function sha() {
  try { return execSync('git rev-parse --short HEAD', { cwd: REPO }).toString().trim(); } catch { return 'unknown'; }
}

function loadBaseline() {
  if (!existsSync(BASELINE)) return { sem_cobertura: [] };
  try { return JSON.parse(read(BASELINE)); } catch { return { sem_cobertura: [] }; }
}

function emitJson(payload) {
  const dir = join(REPO, 'storage', 'reports');
  try { if (!existsSync(dir)) mkdirSync(dir, { recursive: true }); writeFileSync(join(dir, 'protocol-freshness.json'), JSON.stringify(payload, null, 2) + '\n'); } catch {}
}

function main() {
  const res = audit();

  if (FLAG('--write-baseline')) {
    const baseline = {
      _doc: 'Ratchet de frescor do protocolo (espelha review-freshness-baseline.json · ADR 0209). '
        + 'Dívida HERDADA = UC das telas canon SEM cobertura (guard=false) hoje. O gate só FALHA por '
        + 'regressão NOVA (GUARD que sumiu, tela canon sem charter, UC morto citado). A lista só ENCOLHE: '
        + 'ao cobrir um UC (marker + GUARD uc-<id>), mude guard=true no registro e rode --write-baseline.',
      generated_against_sha: sha(),
      sem_cobertura: res.semCobertura,
    };
    writeFileSync(BASELINE, JSON.stringify(baseline, null, 2) + '\n');
    console.log(`[protocol] baseline gravado: ${res.semCobertura.length} sem cobertura (herdado) · ${res.cobertos.length} cobertos · sha ${baseline.generated_against_sha}`);
    return;
  }

  const baseline = loadBaseline();
  const baseSem = new Set(baseline.sem_cobertura || []);
  const novoSem = res.semCobertura.filter((x) => !baseSem.has(x)); // gap novo fora do baseline

  // acende (advisory) = tudo que precisa de atenção; regressão = subset que FALHA o gate.
  const acende = [
    ...res.semCobertura.map((x) => ({ tipo: 'sem_cobertura', ref: x })),
    ...res.guardQuebrado.map((x) => ({ tipo: 'guard_quebrado', ref: x })),
    ...res.charterAusente.map((x) => ({ tipo: 'charter_ausente', ref: x })),
    ...res.ucMorto.map((x) => ({ tipo: 'uc_morto', ref: x })),
  ];
  const regressao = [
    ...res.guardQuebrado.map((x) => ({ tipo: 'guard_quebrado', ref: x })),
    ...res.charterAusente.map((x) => ({ tipo: 'charter_ausente', ref: x })),
    ...res.ucMorto.map((x) => ({ tipo: 'uc_morto', ref: x })),
    ...novoSem.map((x) => ({ tipo: 'sem_cobertura_novo', ref: x })),
  ];

  const payload = { generated_against_sha: sha(), cobertos: res.cobertos, acende, regressao, baseline_sem_cobertura: [...baseSem] };
  emitJson(payload);

  if (FLAG('--json')) {
    console.log(JSON.stringify(payload, null, 2));
  } else {
    console.log(`[protocol] canon: ${res.cobertos.length} UC cobertos · ${res.semCobertura.length} sem cobertura (${baseSem.size} no baseline) · ${res.guardQuebrado.length} guard quebrado · ${res.charterAusente.length} charter ausente · ${res.ucMorto.length} UC morto`);
    if (res.cobertos.length) console.log(`  cobertos: ${res.cobertos.join(', ')}`);
    if (res.semCobertura.length) console.log(`  sem cobertura (ADVISORY): ${res.semCobertura.join(', ')}`);
    if (regressao.length) {
      console.error(`\n[protocol] ✗ FALHA: ${regressao.length} regressão(ões) fora do baseline:`);
      for (const r of regressao) console.error(`  - [${r.tipo}] ${r.ref}`);
    }
  }

  if (regressao.length) process.exit(1);
  console.log('[protocol] OK — nenhuma regressão de protocolo (gaps ⊆ baseline).');
}

main();
