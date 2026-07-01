#!/usr/bin/env node
// ledger-check.mjs — enforcement do PROTOCOLO-REFUTADOR-BACKFILL (frente GT-G5,
// plano-mãe SDD 2026-06-12 §1 "regra de ouro do backfill").
//
// O QUE FAZ: detecta PR-de-lote de backfill IA (>10 arquivos em memory/requisitos/**
// no diff) e exige entry válida em governance/sdd-verification-ledger.json — prova
// de que um agente refutador em sessão fresca tentou provar que o lote está ERRADO
// e o error_rate ficou <2%. Sem entry válida = lote não-verificado = aviso (advisory)
// ou exit 1 (--enforce). Protocolo: memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md
//
// Uso:
//   node scripts/governance/ledger-check.mjs --pr 1234 [--base origin/main] [--head HEAD]
//        [--ledger <path>] [--files-from <txt>] [--threshold 10] [--enforce] [--json]
//
// Nasce ADVISORY (sem --enforce sempre exit 0). Fase 2: plugar no workflow do
// scorecard SDD com --pr ${{ github.event.pull_request.number }}.
// Node puro (fs + git via execSync). Sem deps, sem DB, sem PHP.

import { readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';

const args = process.argv.slice(2);
const flag = (name) => args.includes(name);
const opt = (name, def) => {
  const i = args.indexOf(name);
  return i >= 0 && args[i + 1] !== undefined ? args[i + 1] : def;
};

const BASE = opt('--base', 'origin/main');
const HEAD = opt('--head', 'HEAD');
const PR = opt('--pr', null);
const LEDGER = opt('--ledger', 'governance/sdd-verification-ledger.json');
const FILES_FROM = opt('--files-from', null);
const THRESHOLD = parseInt(opt('--threshold', '10'), 10);
const ENFORCE = flag('--enforce');
const JSON_OUT = flag('--json');

// Tier superior obrigatório (avaliação adversarial SDD 2026-07-01): refutação por
// modelo IDÊNTICO ao gerador tem correlação de erros — os dois alucinam igual.
// Igualdade só é aceita no tier MÁXIMO (não existe superior disponível).
const MODEL_RANK = { haiku: 1, sonnet: 2, opus: 3, fable: 4, mythos: 4 };
const MAX_RANK = Math.max(...Object.values(MODEL_RANK));
const rank = (s) => {
  const m = String(s || '').toLowerCase().match(/haiku|sonnet|opus|fable|mythos/);
  return m ? MODEL_RANK[m[0]] : 0;
};

function changedFiles() {
  if (FILES_FROM) {
    return readFileSync(FILES_FROM, 'utf8').split('\n').map((l) => l.trim()).filter(Boolean);
  }
  const out = execSync(`git diff --name-only --diff-filter=ACMR ${BASE}...${HEAD}`, {
    stdio: ['ignore', 'pipe', 'pipe'],
  }).toString();
  return out.split('\n').map((l) => l.trim()).filter(Boolean);
}

function validateEntry(e) {
  const v = [];
  if (e.veredito !== 'aprovado') v.push(`veredito="${e.veredito}" (exigido: aprovado)`);
  if (e.sessao_fresca !== true) v.push('sessao_fresca != true (refutador contaminado pelo contexto do gerador)');
  if (typeof e.error_rate_pct !== 'number' || e.error_rate_pct >= 2) {
    v.push(`error_rate_pct=${e.error_rate_pct} (aceite: < 2)`);
  }
  if (e.pii_scan !== true) v.push('pii_scan != true (repo publico — scan CPF/CNPJ/nomes obrigatorio)');
  if (e.pii_hits !== 0) v.push(`pii_hits=${e.pii_hits} (obrigatorio 0)`);
  if (rank(e.refutador) === 0 || rank(e.gerador) === 0) {
    v.push(`gerador="${e.gerador}" / refutador="${e.refutador}" sem modelo reconhecivel (haiku|sonnet|opus|fable|mythos)`);
  } else if (rank(e.refutador) < rank(e.gerador)) {
    v.push(`refutador (${e.refutador}) < gerador (${e.gerador}) — exigido tier SUPERIOR`);
  } else if (rank(e.refutador) === rank(e.gerador) && rank(e.gerador) < MAX_RANK) {
    v.push(`refutador (${e.refutador}) do MESMO tier do gerador (${e.gerador}) — refutação por modelo idêntico correlaciona erros (avaliação 2026-07-01); exigido tier superior (igualdade só no tier máximo)`);
  }
  if (e.tipo === 'anchors') {
    if (e.amostra_pct !== 100) v.push(`tipo=anchors exige amostra_pct=100 (veio ${e.amostra_pct})`);
  } else if (e.tipo === 'prosa') {
    if (typeof e.amostra_pct !== 'number' || e.amostra_pct < 30) {
      v.push(`tipo=prosa exige amostra_pct>=30 (veio ${e.amostra_pct})`);
    }
  } else {
    v.push(`tipo="${e.tipo}" invalido (anchors|prosa)`);
  }
  if (!e.evidencia) v.push('evidencia ausente (path do artefato de refutacao)');
  return v;
}

const result = { lote: false, files_requisitos: 0, threshold: THRESHOLD, pr: PR, violations: [] };

try {
  const reqFiles = changedFiles().filter((f) => f.replace(/\\/g, '/').startsWith('memory/requisitos/'));
  result.files_requisitos = reqFiles.length;
  result.lote = reqFiles.length > THRESHOLD;

  if (result.lote) {
    if (!existsSync(LEDGER)) {
      result.violations.push(`ledger nao encontrado: ${LEDGER}`);
    } else {
      const ledger = JSON.parse(readFileSync(LEDGER, 'utf8'));
      const entries = Array.isArray(ledger.entries) ? ledger.entries : [];
      if (!PR) {
        result.violations.push('PR-de-lote detectado mas --pr nao informado — impossivel casar entry no ledger');
      } else {
        const mine = entries.filter((e) => String(e.pr) === String(PR));
        if (mine.length === 0) {
          result.violations.push(`nenhuma entry no ledger para PR #${PR} — lote IA sem refutacao adversarial (protocolo GT-G5)`);
        } else {
          // Append-only: a entry mais RECENTE (ultima do array) decide.
          const entryViolations = validateEntry(mine[mine.length - 1]);
          result.violations.push(...entryViolations.map((m) => `entry PR #${PR}: ${m}`));
        }
      }
    }
  }
} catch (err) {
  result.violations.push(`erro de execucao: ${err.message}`);
}

const ok = result.violations.length === 0;
result.ok = ok;
result.mode = ENFORCE ? 'enforce' : 'advisory';

if (JSON_OUT) {
  console.log(JSON.stringify(result, null, 2));
} else if (!result.lote) {
  console.log(`✓ ledger-check: nao e PR-de-lote (${result.files_requisitos} arquivo(s) em memory/requisitos/ <= ${THRESHOLD}) — nada a exigir.`);
} else if (ok) {
  console.log(`✓ ledger-check: PR-de-lote #${PR} (${result.files_requisitos} arquivos) com entry valida no ledger — refutacao adversarial registrada.`);
} else {
  const tag = ENFORCE ? 'FAIL' : 'ADVISORY';
  console.log(`${tag} ledger-check: PR-de-lote (${result.files_requisitos} arquivos em memory/requisitos/ > ${THRESHOLD}) com pendencia(s):`);
  for (const v of result.violations) console.log(`  ✗ ${v}`);
  console.log(`  → protocolo: memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md`);
}

process.exit(ok || !ENFORCE ? 0 : 1);
