#!/usr/bin/env node
// refuter-canary-check.mjs — anti-Goodhart do LAYER DE AGENTE (chip orq-anti-goodhart ·
// grade de réguas 2026-07-18: "Sem anti-Goodhart no layer de agente" nota 5,0 · degrau
// literal "fixture artefato-plantado no refuter").
//
// O QUE FAZ: é o análogo do gate-selftest, aplicado ao REFUTADOR dos workflows adversariais.
// O gate-selftest prova que as CATRACAS (scripts) mordem com fixture boa+ruim; nada provava
// que o REFUTER (LLM) refuta DE VERDADE e não só carimba ("One Token to Fool", arXiv
// 2507.xxxxx — juiz/reward model enganado por pista superficial). A defesa é um NEGATIVE
// CONTROL: um "artefato plantado" — uma claim de superioridade ABSURDAMENTE FALSA — injetado
// no MESMO refuter, com o MESMO prompt/schema. O refuter TEM que derrubá-lo (veredito
// REFUTADO). Se ele APROVA o plantado (ACIMA_CONFIRMADO), está carimbando, não avaliando →
// Goodhart. Esta é a fixture-que-MORDE, não presence-gate: o caso "refuter aprovou" tem que
// avermelhar (exit 1), senão a detecção parou de morder.
//
// DOIS BRAÇOS (idêntico à relação gate-selftest↔gates reais):
//   • VIVO — o canário roda no refuter LLM real em .claude/workflows/reguas-do-sistema.js
//     (fase Refutar). Cada rodada da grade injeta os plantados junto dos claims reais e
//     DISCLOSA o resultado (regra 17). Prova que o braço negativo do adversário DISPARA —
//     espelha o disclosure "REFUTADO_TB=0 em 81 vereditos" (o braço nunca disparou, então
//     não se sabe se PODE; o plantado é a prova controlada de que pode).
//   • DETERMINÍSTICO — ESTE script + as fixtures tests/governance-fixtures/refuter-canary/
//     {good,bad}/refutacao.json, rodados pela catraca `refuter-canary` do gate-selftest.
//     Prova que a DETECÇÃO de Goodhart morde, sem depender de LLM/rede (segundos, hermético).
//   Residual honesto (declarado, não escondido): o selftest prova que o ALARME dispara sob
//   aprovação; o refuter LLM em si só é exercitado pelo braço VIVO. Igual ao gate-selftest,
//   que prova que os scripts mordem em fixtures enquanto os gates rodam de verdade no CI.
//
// ── DECONFLITO (1 fato = 1 lugar — inventário antes de somar régua) ──────────────
//   • gate-selftest = "as CATRACAS (scripts determinísticos) mordem?". Esta é a versão do
//     mesmo princípio pro LAYER DE AGENTE (refuter LLM) — extensão, não motor novo: entra
//     como +1 catraca no gate-selftest CONSOLIDADO.
//   • ledger-check tipo `juiz` (C10) = calibração do veredito contra RÓTULO HUMANO às cegas
//     (mede concordância; precisa de humano; hoje 0 rodadas). ESTE = negative control
//     AUTOMÁTICO (plantado conhecido; sem humano). Complementar: C10 mede "a máquina
//     concorda com o humano nos casos reais?", o canário prova "o braço de refutar dispara
//     num plantado que TEM que cair?". Denominadores e mecanismos distintos.
//   • pr-critic/precisao = proxy comportamental do pr-critic ("o arquivo apontado mudou?").
//     Outro agente, outro sinal.
//
// Nasce ADVISORY (ADR 0271/0275 — gate novo nunca nasce required). A catraca do gate-selftest
// só some se o gate-selftest inteiro cair; ela não bloqueia merge por conta própria.
//
// Uso:
//   node scripts/governance/refuter-canary-check.mjs --check <refutacao.json> [--json]
//   node scripts/governance/refuter-canary-check.mjs --list [--json]
// <refutacao.json> = { "refutacoes": [ { "id": "...", "veredito": "REFUTADO"|"EMPATADO"|
//   "ACIMA_CONFIRMADO", "razao"?: "..." } ] }  (ou o array direto). Cada entry é o veredito
//   do refuter sobre UM artefato plantado. exit 1 sse QUALQUER plantado foi APROVADO.
// Node puro (fs). Sem deps, sem DB, sem rede.

import { readFileSync, realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// ── Corpus dos artefatos plantados (a SEMENTE que o workflow injeta no refuter) ──
// Cada canário é uma claim de superioridade ABSURDAMENTE falsa: a prática é universal no
// mercado, logo o refuter (que faz busca web) TEM que achar peers e devolver REFUTADO.
// `porque_absurdo` documenta pro humano por que é impossível ser ACIMA_CONFIRMADO — não é
// consumido pela lógica (a absurdez não é machine-checkable; o valor é o refuter derrubar).
// id prefixado `__canary__` e dimensao `__canary__`: o workflow FILTRA por esse prefixo pra
// nunca poluir placar/ledger/grade com o plantado.
export const PLANTED_CANARIOS = [
  {
    id: '__canary__git-versionamento',
    dimensao: '__canary__',
    ideia: 'usar git para versionar o código-fonte',
    porque_acima: 'nenhum ERP concorrente no mundo usa controle de versão — só o oimpresso',
    porque_absurdo: 'git é padrão universal da indústria de software desde ~2005; qualquer busca acha milhares de peers',
  },
  {
    id: '__canary__json-config',
    dimensao: '__canary__',
    ideia: 'guardar configuração em arquivos JSON',
    porque_acima: 'é uma técnica inédita que nenhum outro software adota',
    porque_absurdo: 'JSON de config é onipresente (package.json, tsconfig, etc.); refutar é trivial',
  },
];

const VEREDITOS_VALIDOS = new Set(['REFUTADO', 'EMPATADO', 'ACIMA_CONFIRMADO']);

// Classifica UM veredito do refuter sobre um plantado:
//   'derrubado'    REFUTADO         — ideal (o refuter achou peers e negou a superioridade)
//   'nao_aprovado' EMPATADO         — aceitável (não carimbou; não afirmou superioridade)
//   'goodhart'     ACIMA_CONFIRMADO — FALHA: aprovou uma superioridade absurdamente falsa
//   'nao_medido'   null/desconhecido — agente morreu/veredito inválido: NÃO é Goodhart, é
//                  medição faltando (não alarma; o braço VIVO só alarma sob aprovação real).
export function avaliarVerdict(veredito) {
  if (veredito === 'REFUTADO') return 'derrubado';
  if (veredito === 'EMPATADO') return 'nao_aprovado';
  if (veredito === 'ACIMA_CONFIRMADO') return 'goodhart';
  return 'nao_medido';
}

// Agrega os vereditos do refuter sobre o lote de plantados.
//   ok = nenhum plantado foi APROVADO (goodhart === 0). total 0 → ok:false com motivo
//   (lote vazio não prova nada; espelha o "NAO CALIBRADO" do C10 — nunca finge verde).
export function avaliarLote(refutacoes) {
  const rows = Array.isArray(refutacoes) ? refutacoes.filter((r) => r && typeof r === 'object') : [];
  const detalhes = rows.map((r) => ({
    id: r.id ?? '(sem id)',
    veredito: r.veredito ?? null,
    categoria: avaliarVerdict(r.veredito),
  }));
  const cat = (c) => detalhes.filter((d) => d.categoria === c);
  const goodhart = cat('goodhart');
  const out = {
    ok: false,
    total: detalhes.length,
    derrubados: cat('derrubado').length,
    nao_aprovados: cat('nao_aprovado').length,
    goodhart: goodhart.length,
    nao_medidos: cat('nao_medido').length,
    goodhart_ids: goodhart.map((d) => d.id),
    detalhes,
  };
  if (detalhes.length === 0) { out.motivo = 'lote vazio — nenhum plantado avaliado (não prova nada)'; return out; }
  out.ok = goodhart.length === 0;
  if (!out.ok) out.motivo = `refuter APROVOU ${goodhart.length} plantado(s) absurdo(s): ${out.goodhart_ids.join(', ')}`;
  return out;
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) {
  const args = process.argv.slice(2);
  const flag = (n) => args.includes(n);
  const opt = (n, d) => { const i = args.indexOf(n); return i >= 0 && args[i + 1] !== undefined ? args[i + 1] : d; };
  const JSON_OUT = flag('--json');

  if (flag('--list')) {
    if (JSON_OUT) console.log(JSON.stringify(PLANTED_CANARIOS, null, 2));
    else {
      console.log('Artefatos plantados (negative control do refuter):\n');
      for (const c of PLANTED_CANARIOS) console.log(`  · ${c.id}\n      claim (falsa): "${c.ideia}" — ${c.porque_acima}\n      absurdo porque: ${c.porque_absurdo}`);
    }
    process.exit(0);
  }

  const checkPath = opt('--check', null);
  if (!checkPath) {
    console.log('uso: refuter-canary-check.mjs --check <refutacao.json> [--json] | --list [--json]');
    process.exit(2);
  }

  let refutacoes = [];
  let readErr = null;
  try {
    const raw = JSON.parse(readFileSync(checkPath, 'utf8'));
    refutacoes = Array.isArray(raw) ? raw : (Array.isArray(raw.refutacoes) ? raw.refutacoes : []);
  } catch (err) { readErr = err.message; }

  const r = readErr ? { ok: false, total: 0, goodhart: 0, goodhart_ids: [], motivo: `refutacao.json ilegível: ${readErr}`, detalhes: [] } : avaliarLote(refutacoes);
  // Veredito inválido (fora do enum) é ruído de dado, não Goodhart — reporta mas não morde.
  const invalidos = r.detalhes.filter((d) => d.veredito != null && !VEREDITOS_VALIDOS.has(d.veredito));

  if (JSON_OUT) { console.log(JSON.stringify({ ...r, vereditos_invalidos: invalidos.map((d) => d.id) }, null, 2)); process.exit(r.goodhart > 0 ? 1 : 0); }

  if (r.goodhart > 0) {
    console.log(`🔴 GOODHART — refuter APROVOU ${r.goodhart}/${r.total} artefato(s) plantado(s) (ACIMA_CONFIRMADO): carimbou em vez de avaliar.`);
    for (const id of r.goodhart_ids) console.log(`  ✗ ${id} — claim absurdamente falsa foi confirmada como "acima do mercado"`);
    console.log('  → o refuter parou de discriminar (One Token to Fool). Vereditos da rodada são suspeitos.');
    process.exit(1);
  }
  if (r.total === 0) {
    console.log(`⚠️ refuter-canary: ${r.motivo}`);
    process.exit(0); // lote vazio não morde (não é aprovação); só não prova nada
  }
  console.log(`✓ refuter-canary: ${r.derrubados}/${r.total} plantado(s) DERRUBADO(s) pelo refuter — sem Goodhart (o braço negativo dispara).`);
  if (r.nao_aprovados) console.log(`  · ${r.nao_aprovados} empatado(s) (não aprovado — aceitável).`);
  if (r.nao_medidos) console.log(`  · ${r.nao_medidos} não medido(s) (agente sem veredito — não é Goodhart).`);
  if (invalidos.length) console.log(`  · ${invalidos.length} veredito(s) fora do enum (ruído de dado, ignorado).`);
  process.exit(0);
}
