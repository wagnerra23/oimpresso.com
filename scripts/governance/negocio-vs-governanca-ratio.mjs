#!/usr/bin/env node
// negocio-vs-governanca-ratio.mjs — o alarme anti-atrofia da inteligência de negócio.
// (Doutrina do modelo de 3 camadas — a ADR que a documenta está em ratificação; o alarme vale por si.)
//
// Classifica os merges (first-parent) de origin/main numa janela em 3 baldes — NEGÓCIO (A+B),
// GOVERNANÇA-META (C), INFRA — pelo escopo/keywords do conventional-commit, e emite WARN quando
// GOVERNANÇA domina a janela. É o alarme que teria disparado no crossover de junho/2026 (governança
// 8× negócio) e ninguém ouviu porque não existia. Advisory por design (required é só Tier-0, ADR 0314):
// nunca bloqueia — imprime o ratio + WARN e sai 0. A régua aponta ONDE trabalhar; não é catraca.
//
// Uso:  node scripts/governance/negocio-vs-governanca-ratio.mjs [--weeks N] [--warn 0.65] [--json]
//       (default: 4 semanas — janela curta é responsiva; 8+ dilui o pico com semanas saudáveis)
// Trend real medido 2026-07-10: mai 38% → jun 64% → jul 78% de governança = o crossover que ninguém ouviu.
//
// A classificação é transparente e keyword-based (auditável, não IA) — ver CLASSIF abaixo.
// Regra de ouro: a governança-meta serve o negócio; quando ela domina o FLUXO sem
// sinal de cliente, a régua virou o produto. Este script mede o fluxo, não o estoque.

import { execSync } from 'node:child_process'

// ── Classificação (ordem importa: GOVERNANÇA e NEGÓCIO checados antes de INFRA) ──────────────
// Casamos no escopo do conventional-commit `tipo(escopo): ...` e em palavras-chave do assunto.
const GOVERNANCA = /\b(governance|governanca|gate|hook|adr|scorecard|regua|régua|reguas|sdd|lint|ratchet|charter|proibicoes|proibições|handoff|memory-health|doc-freshness|pr-critic|selftest|self-test|baseline|dominio-gate|casos-gate|mwart-gate|skill|workflow|prototipo-ui|design-memory|constituicao|constituição)\b/i
const NEGOCIO = /\b(sells?|vendas?|venda|pos|financeiro|nfe|nfce|nfse|nfebrasil|fiscal|boleto|cobranca|cobrança|recurringbilling|assinatura|repair|os|kanban|vestuario|vestuário|comunicacaovisual|comvis|oficinaauto|oficina|crm|whatsapp|estoque|produto|produtos|jana|caixa|conciliacao|conciliação|cliente|larissa|rota.livre)\b/i
const INFRA = /\b(ci|deploy|deps|dependabot|composer|npm|vite|build|docker|infra|pipeline|runner|actions)\b/i

// jana é ambíguo: só conta NEGÓCIO se o assunto sugerir capacidade-de-produto, não plumbing/eval de processo.
const JANA_PROCESSO = /\bjana\b/i
const JANA_PLUMBING = /\b(ragas|recall-eval|eval|sentinel|drift|mcp-task|scorer|distiller|redteam|red-team|prompt-injection|gold-set|golden)\b/i

export function classificar(subject) {
  const s = String(subject || '')
  // Jana: separa produto de plumbing-de-governança
  if (JANA_PROCESSO.test(s)) {
    if (JANA_PLUMBING.test(s) || GOVERNANCA.test(s)) return 'GOVERNANCA'
    return 'NEGOCIO'
  }
  if (GOVERNANCA.test(s)) return 'GOVERNANCA'
  if (NEGOCIO.test(s)) return 'NEGOCIO'
  if (INFRA.test(s)) return 'INFRA'
  return 'OUTRO'
}

// ── Coleta (só usada no modo real; o self-test não toca git) ─────────────────────────────────
export function coletarSubjects(sinceISO, base) {
  const cwd = base || process.cwd()
  const out = execSync(
    `git -C "${cwd}" log origin/main --first-parent --since="${sinceISO}" --pretty=%s`,
    { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 },
  )
  return out.split('\n').map((l) => l.trim()).filter(Boolean)
}

export function agregar(subjects) {
  const b = { NEGOCIO: 0, GOVERNANCA: 0, INFRA: 0, OUTRO: 0 }
  for (const s of subjects) b[classificar(s)]++
  const total = subjects.length || 1
  const relev = b.NEGOCIO + b.GOVERNANCA || 1 // ratio ignora infra/outro (ruído)
  return {
    total: subjects.length,
    baldes: b,
    share_governanca: b.GOVERNANCA / total,
    ratio_gov_neg: b.GOVERNANCA / (b.NEGOCIO || 1),
    share_governanca_relevante: b.GOVERNANCA / relev,
  }
}

// ── CLI ──────────────────────────────────────────────────────────────────────────────────────
function parseArgs(argv) {
  const a = { weeks: 4, warn: 0.65, json: false }
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--weeks') a.weeks = Number(argv[++i])
    else if (argv[i] === '--warn') a.warn = Number(argv[++i])
    else if (argv[i] === '--json') a.json = true
  }
  return a
}

function main() {
  const a = parseArgs(process.argv.slice(2))
  const days = a.weeks * 7
  // Sem Date.now() disponível em alguns runners? Aqui é CLI real — usamos git relative date.
  const since = `${days} days ago`
  let subjects
  try {
    subjects = execSync(
      `git log origin/main --first-parent --since="${since}" --pretty=%s`,
      { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 },
    ).split('\n').map((l) => l.trim()).filter(Boolean)
  } catch (e) {
    console.error(`[negocio-vs-governanca] não consegui ler git log: ${e.message}`)
    process.exit(0) // advisory: nunca quebra o CI
  }
  const r = agregar(subjects)
  const pct = (x) => `${(x * 100).toFixed(0)}%`
  const alarme = r.share_governanca_relevante > a.warn

  if (a.json) {
    console.log(JSON.stringify({ ...r, weeks: a.weeks, warn: a.warn, alarme }, null, 2))
  } else {
    console.log(`\n=== Ratio negócio × governança — últimas ${a.weeks} semanas (${r.total} merges) ===`)
    console.log(`  NEGÓCIO (A+B) : ${r.baldes.NEGOCIO}\t${pct(r.baldes.NEGOCIO / (r.total || 1))}`)
    console.log(`  GOVERNANÇA (C): ${r.baldes.GOVERNANCA}\t${pct(r.share_governanca)}`)
    console.log(`  INFRA         : ${r.baldes.INFRA}\t${pct(r.baldes.INFRA / (r.total || 1))}`)
    console.log(`  OUTRO         : ${r.baldes.OUTRO}`)
    console.log(`  ratio gov÷neg : ${r.ratio_gov_neg.toFixed(1)}×`)
    console.log(`  gov / (gov+neg): ${pct(r.share_governanca_relevante)}  (limiar WARN ${pct(a.warn)})`)
    if (alarme) {
      console.log(`\n  ⚠️  ALARME (anti-atrofia da inteligência de negócio): governança domina o fluxo. A régua pode estar passando por sinal.`)
      console.log(`      Cheque: há cycle ativo com goal de NEGÓCIO? Os PRs de governança citam sinal-de-cliente?`)
    } else {
      console.log(`\n  ✓ equilíbrio dentro do limiar.`)
    }
  }
  process.exit(0) // advisory sempre
}

// só roda o CLI quando invocado direto (não no import do self-test)
if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith('negocio-vs-governanca-ratio.mjs')) {
  main()
}
