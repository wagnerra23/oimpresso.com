#!/usr/bin/env node
// Self-test do alarme anti-atrofia (ADR 0334) — a catraca morde e libera certo, sem tocar git.
// Roda: node scripts/governance/negocio-vs-governanca-ratio.test.mjs
import { classificar, agregar } from './negocio-vs-governanca-ratio.mjs'

let falhas = 0
const eq = (nome, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp)
  if (!ok) { falhas++; console.error(`✗ ${nome}\n   esperado ${JSON.stringify(exp)}\n   obtido   ${JSON.stringify(got)}`) }
  else console.log(`✓ ${nome}`)
}

// ── Classificação ────────────────────────────────────────────────────────────────────────────
eq('governança: gate', classificar('feat(governance): gate-selftest required'), 'GOVERNANCA')
eq('governança: adr', classificar('docs(adr): ratifica 0330 flip proposto→aceito [CC]'), 'GOVERNANCA')
eq('governança: régua', classificar('feat(reguas): fase integração no reguas-do-sistema'), 'GOVERNANCA')
eq('negócio: venda', classificar('feat(sells): grade avançada + desconto por item'), 'NEGOCIO')
eq('negócio: financeiro', classificar('fix(financeiro): baixa de boleto ROTA LIVRE'), 'NEGOCIO')
eq('negócio: nfe', classificar('feat(nfe): emissão de NFC-e no PDV'), 'NEGOCIO')
eq('jana PRODUTO conta negócio', classificar('feat(jana): SaleInsightAgent responde faturamento'), 'NEGOCIO')
eq('jana PLUMBING conta governança', classificar('feat(jana): ragas recall-eval gold-set'), 'GOVERNANCA')
eq('infra', classificar('chore(ci): bump vite build runner'), 'INFRA')
eq('outro', classificar('chore: mexe em coisa nenhuma'), 'OUTRO')

// ── Agregação + alarme: cenário "crossover de junho" (governança domina) ──────────────────────
const junho = [
  'feat(governance): gate A', 'feat(governance): hook B', 'docs(adr): C', 'feat(reguas): D',
  'chore(governance): scorecard E', 'feat(governance): lint F', 'docs(adr): G', 'feat(governance): charter H',
  'feat(sells): venda nova', // 1 só de negócio
]
const rJun = agregar(junho)
eq('junho: 8 governança / 1 negócio', [rJun.baldes.GOVERNANCA, rJun.baldes.NEGOCIO], [8, 1])
eq('junho: share relevante > 0.65 (ALARME)', rJun.share_governanca_relevante > 0.65, true)

// ── Cenário equilibrado: NÃO dispara ──────────────────────────────────────────────────────────
const equilibrado = [
  'feat(sells): A', 'feat(financeiro): B', 'feat(nfe): C', 'feat(jana): responde meta D',
  'feat(governance): gate E', 'docs(adr): F',
]
const rEq = agregar(equilibrado)
eq('equilíbrio: 4 negócio / 2 governança', [rEq.baldes.NEGOCIO, rEq.baldes.GOVERNANCA], [4, 2])
eq('equilíbrio: share relevante <= 0.65 (LIBERA)', rEq.share_governanca_relevante <= 0.65, true)

// ── Vazio: não quebra ─────────────────────────────────────────────────────────────────────────
const rVazio = agregar([])
eq('vazio: total 0 sem NaN', rVazio.total, 0)

if (falhas) { console.error(`\n${falhas} falha(s).`); process.exit(1) }
console.log('\nOK — o alarme morde no crossover e libera no equilíbrio.')
