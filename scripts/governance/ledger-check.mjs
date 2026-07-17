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
// ── DECONFLITO do tipo `juiz` (1 fato = 1 lugar — inventário antes de somar régua) ──
//   • pr-critic/precisao.mjs = PROXY comportamental do pr-critic ("o arquivo apontado
//     mudou depois do comentário?"). O próprio script declara que é proxy (G1: mudou
//     depois ≠ mudou por causa) e NÃO tem rótulo humano explícito.
//   • ESTE tipo `juiz`      = rótulo humano DIRETO e às cegas sobre o VEREDITO
//     (refutador do ledger, workflow adversarial). É o que fecha o gap que o proxy
//     admite ter — complementar, não duplicado. Escopo diferente, denominador diferente.
//   • backfill_error_rate (sdd-scorecard) = erro do LOTE segundo a máquina; não mede
//     se a máquina acerta. Calibrar o juiz é a pergunta de cima dessa.
//
// TIPO `juiz` (chip C10 — calibração do juiz, grade de réguas 2026-07-17): entries de
// tipo `juiz` NÃO são refutação de lote — registram uma rodada de CALIBRAÇÃO, onde [W]
// rotulou N vereditos ÀS CEGAS e comparamos com o que a máquina decidiu. Elas são
// EXCLUÍDAS do casamento do PR-de-lote (§2.6 do protocolo continua exigindo anchors|prosa):
// registrar calibração NÃO libera merge de lote. `--juiz-report` publica a taxa de acerto
// com denominador; zero rótulos humanos = NÃO CALIBRADO (nunca finge um número).
// A calibração é MEDIÇÃO, não portão — o report sai 0 sempre.
//
// Uso:
//   node scripts/governance/ledger-check.mjs --pr 1234 [--base origin/main] [--head HEAD]
//        [--ledger <path>] [--files-from <txt>] [--threshold 10] [--enforce] [--json]
//   node scripts/governance/ledger-check.mjs --juiz-report [--ledger <path>] [--json]
//
// Nasce ADVISORY (sem --enforce sempre exit 0). Fase 2: plugar no workflow do
// scorecard SDD com --pr ${{ github.event.pull_request.number }}.
// Node puro (fs + git via execSync). Sem deps, sem DB, sem PHP.

import { readFileSync, existsSync, realpathSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// Importável pra teste sem rodar o gate (idiom da casa — espelha sdd-scorecard.mjs).
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

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
const JUIZ_REPORT = flag('--juiz-report');

// Tipo de entry que NÃO é refutação-de-lote (ver cabeçalho). Fica fora do casamento
// do PR pra que (a) registrar calibração nunca satisfaça o gate de lote — anti-gaming —
// e (b) uma entry `juiz` no mesmo PR de um lote não seja validada com as regras erradas
// (o casamento pega a ÚLTIMA entry do PR; sem este filtro, a calibração mascararia
// ou reprovaria o lote por acidente). Tipo desconhecido segue caindo em validateEntry,
// que o acusa — filtrar por "!== juiz" preserva esse fail-secure.
const JUIZ_TIPO = 'juiz';

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

// ── tipo `juiz` — calibração do juiz contra rótulo humano (chip C10) ──────────
// Uma rodada de calibração vale se, e só se: um HUMANO DO TIME rotulou ÀS CEGAS, e
// a aritmética fecha. As regras duras são anti-fabricação:
//   1. `rotulador` tem que ser um HUMANO CONHECIDO — por ALLOWLIST das siglas do time
//      ([W]/[M]/[F]/[L]/[E], regras-time.md), NÃO por denylist de modelo. Um denylist
//      de nomes de modelo é "incompleto por construção" (lápide §5 2026-06-30): todo
//      modelo novo escapa — gpt-4o-mini (JÁ usado como gerador neste ledger), gemini,
//      grok, o3... O conjunto de humanos do projeto é FECHADO e pequeno, então a
//      asserção POSITIVA "é um dos 5" é completa pro domínio; a de modelo, não.
//      Motivo: um juiz avaliando o juiz é o mesmo viés duas vezes — substituto
//      sintético do denominador humano, que é justamente o que falta (grade 2026-07-17).
//   1b. Denylist de modelo fica como reforço SECUNDÁRIO — pega "[W] + copilot sonnet"
//      (rótulo humano ASSISTIDO por modelo contamina a calibração tanto quanto modelo
//      puro). Residual honesto: copilot não-Anthropic no rótulo é indetectável por
//      string (o mesmo limite do §5) — a allowlist garante que um humano assinou; a
//      ausência de assistência fica no `evidencia`, não é mecanicamente provável.
//   2. `cego !== true` — quem rotula vendo o veredito da máquina não calibra, ancora.
// Entry inválida NÃO conta pro número publicado (e aparece listada) — assim o report
// nunca mistura rótulo podre no placar, sem precisar bloquear ninguém.
const HUMANO_TIME = /\[(W|M|F|L|E)\]/; // siglas do time (regras-time.md) — allowlist fechada
export function validateJuizEntry(e) {
  const v = [];
  if (e.cego !== true) {
    v.push('cego != true (rotulou vendo o veredito da maquina = ancoragem, nao calibracao)');
  }
  if (!e.rotulador) {
    v.push('rotulador ausente (quem rotulou? exige sigla de humano do time, ex: "[W]")');
  } else if (!HUMANO_TIME.test(String(e.rotulador))) {
    v.push(`rotulador="${e.rotulador}" sem sigla de humano do time ([W]/[M]/[F]/[L]/[E]) — calibracao exige rotulo HUMANO (allowlist fechada, NAO denylist de modelo: lapide §5 2026-06-30 "adivinhar por nome e incompleto por construcao")`);
  } else if (rank(e.rotulador) > 0) {
    const mdl = String(e.rotulador).toLowerCase().match(/haiku|sonnet|opus|fable|mythos/)?.[0];
    v.push(`rotulador="${e.rotulador}" mistura humano com MODELO (${mdl}) — copilot no loop de rotulagem contamina a calibracao (juiz avaliando juiz)`);
  }
  if (!e.juiz) v.push('juiz ausente (o que esta sendo calibrado? ex: "ledger-refutador (fable-5)")');
  const n = e.itens_rotulados, k = e.concordancias;
  if (!Number.isInteger(n) || n < 1) {
    v.push(`itens_rotulados=${n} (exige inteiro >= 1 — sem N nao ha denominador)`);
  }
  if (!Number.isInteger(k) || k < 0) {
    v.push(`concordancias=${k} (exige inteiro >= 0)`);
  }
  if (Number.isInteger(n) && Number.isInteger(k) && k > n) {
    v.push(`concordancias=${k} > itens_rotulados=${n} (impossivel)`);
  }
  // A taxa declarada tem que FECHAR com o denominador — trava contra publicar um
  // número redondo que os rótulos não sustentam.
  if (Number.isInteger(n) && Number.isInteger(k) && n >= 1 && k <= n) {
    const esperado = Math.round((1000 * k) / n) / 10;
    if (typeof e.concordancia_pct !== 'number' || Math.abs(e.concordancia_pct - esperado) > 0.1) {
      v.push(`concordancia_pct=${e.concordancia_pct} nao fecha com ${k}/${n} (esperado ~${esperado})`);
    }
  }
  if (!e.evidencia) v.push('evidencia ausente (path dos rotulos de [W])');
  if (!e.data) v.push('data ausente (YYYY-MM-DD da rodada)');
  return v;
}

// Agrega as rodadas de calibração válidas num número COM DENOMINADOR.
// Zero rodadas válidas → { calibrado: false } — o estado honesto (espelha o
// `not_yet_measured` do sdd-scorecard: nunca fabrica 0 nem 100).
export function juizReport(ledgerPath) {
  const out = { calibrado: false, rodadas: 0, itens_rotulados: 0, concordancias: 0, concordancia_pct: null, por_rodada: [], rejeitadas: [] };
  if (!existsSync(ledgerPath)) {
    out.motivo = `ledger nao encontrado: ${ledgerPath}`;
    return out;
  }
  let ledger;
  try { ledger = JSON.parse(readFileSync(ledgerPath, 'utf8')); }
  catch (err) { out.motivo = `ledger JSON invalido: ${err.message}`; return out; }
  // `e &&`: entry null/não-objeto (JSON válido, ledger podre) não pode derrubar o
  // report — ele promete "exit 0 sempre / MEDIÇÃO, não portão". Sem o guard, um
  // `entries:[null]` lançava TypeError e saía != 0 (achado do skeptic 2).
  const entries = (Array.isArray(ledger.entries) ? ledger.entries : []).filter((e) => e && e.tipo === JUIZ_TIPO);
  for (const e of entries) {
    const v = validateJuizEntry(e);
    if (v.length) { out.rejeitadas.push({ lote_id: e.lote_id ?? '(sem lote_id)', motivos: v }); continue; }
    out.rodadas++;
    out.itens_rotulados += e.itens_rotulados;
    out.concordancias += e.concordancias;
    out.por_rodada.push({
      lote_id: e.lote_id ?? '(sem lote_id)', data: e.data, juiz: e.juiz, rotulador: e.rotulador,
      concordancias: e.concordancias, itens_rotulados: e.itens_rotulados, concordancia_pct: e.concordancia_pct,
      evidencia: e.evidencia,
    });
  }
  if (out.rodadas === 0) {
    out.motivo = entries.length === 0
      ? 'NAO CALIBRADO — zero rodadas de calibracao no ledger. O juiz nunca foi conferido contra rotulo humano; sem denominador humano, "o juiz acerta" e prosa, nao medicao (grade 2026-07-17, chip C10).'
      : `NAO CALIBRADO — ${entries.length} entry(s) tipo juiz, todas invalidas (ver rejeitadas). Rotulo podre nao entra no placar.`;
    return out;
  }
  out.calibrado = true;
  out.concordancia_pct = Math.round((1000 * out.concordancias) / out.itens_rotulados) / 10;
  return out;
}

// ── modo report (medição, nunca portão — exit 0 sempre) ──────────────────────
// try/catch de cinto-e-suspensório: NADA aqui pode sair != 0 (invariante declarada
// no cabeçalho + _meta.tipos.juiz). juizReport já é throw-safe, mas o bloco de print
// fica blindado do mesmo jeito que o path do gate (achado do skeptic 2).
if (isMain && JUIZ_REPORT) {
  try {
    const r = juizReport(LEDGER);
    if (JSON_OUT) {
      console.log(JSON.stringify(r, null, 2));
    } else {
      console.log('Calibracao do juiz — concordancia com rotulo humano as cegas (chip C10)\n');
      if (!r.calibrado) {
        console.log(`  ${r.motivo}`);
        console.log('  Como calibrar: [W] rotula N vereditos SEM ver o que a maquina decidiu,');
        console.log('  compara, e registra 1 entry tipo="juiz" no ledger (schema em _meta.schema_entry_juiz).');
      } else {
        console.log(`  concordancia: ${r.concordancias}/${r.itens_rotulados} = ${r.concordancia_pct}% (${r.rodadas} rodada(s))\n`);
        for (const p of r.por_rodada) {
          console.log(`  · ${p.lote_id} (${p.data}) — ${p.concordancias}/${p.itens_rotulados} = ${p.concordancia_pct}%`);
          console.log(`      juiz=${p.juiz} · rotulador=${p.rotulador}`);
        }
      }
      for (const rej of r.rejeitadas) {
        console.log(`\n  ! rodada IGNORADA (fora do placar): ${rej.lote_id}`);
        for (const m of rej.motivos) console.log(`      ✗ ${m}`);
      }
    }
  } catch (err) {
    // Report é MEDIÇÃO — falha vira aviso honesto, nunca exit != 0.
    console.log(`Calibracao do juiz — nao foi possivel ler o ledger: ${err.message} (report e medicao, segue exit 0).`);
  }
  process.exit(0);
}

if (isMain) {
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
          // Entry `juiz` (calibração) NÃO é refutação de lote — não satisfaz o gate.
          const mine = entries.filter((e) => String(e.pr) === String(PR) && e.tipo !== JUIZ_TIPO);
          if (mine.length === 0) {
            const soJuiz = entries.some((e) => String(e.pr) === String(PR) && e.tipo === JUIZ_TIPO);
            result.violations.push(soJuiz
              ? `PR #${PR} tem entry tipo="juiz" (calibracao) mas NENHUMA refutacao de lote (anchors|prosa) — calibrar o juiz nao substitui refutar o lote (protocolo GT-G5 §2)`
              : `nenhuma entry no ledger para PR #${PR} — lote IA sem refutacao adversarial (protocolo GT-G5)`);
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
}
