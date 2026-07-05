---
slug: onda1-regrade-telas-stale
title: "Onda 1 — re-grade das telas stale (Check B)"
date: "2026-07-05"
status: concluido
authors: [C]
related_adrs: ["0230", "0232", "0264", "0320"]
topic: "execução da Onda 1 do PLANO-APROFUNDAMENTO-AVALIACOES — refrescar os scorecards de tela velhos (Check B) sem maquiar quedas"
plano: "memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md"
---

# Onda 1 — re-grade das telas stale

## O que era

`Check B` do `memory-health` acusava **221 scorecards de tela stale** (a tela mudou
depois do `graded_at` do baseline de 2026-05-30). A lente já existia (skill
`screen-grade`, `screen-grade-report.mjs`, `screen-grades-ratchet.mjs`) — só faltava
refrescar a nota pra refletir o código atual. DoD do plano: **Check B < 50** + ratchet
verde + lista de notas-que-caíram pro Wagner.

## O que foi feito

- **Fan-out** de 16 agentes re-graduadores (1 por lote de módulo), método SCREEN-GRADE
  16-dim, ancorados na nota antiga (test-retest σ≤3) e movendo **só com evidência de
  `git log`** desde o `graded_at`. Regra dura no prompt: nota que cai vira achado, não
  se maquia; nota que sobe exige evidência.
- **217 telas re-graduadas**; **4 eram órfãs** (tsx deletado) — removidas, não regradas.
- Bumps: `graded_at → 2026-07-05`, `source → regrade-onda1-2026-07-05`, 16 dims + resumo
  + gaps atualizados. **Não tocado:** `baseline_anterior`, `peso_real`, `persona`,
  `archetype`, `casos_coverage`, `d1_calculo`.

## Resultado (medição)

| Métrica | Antes | Depois |
|---|---:|---:|
| **Check B (stale)** | 221 | **0** |
| Scorecards de tela | 223 | 219 |
| Telas `<70` (Developing/Beginner) | 42 | **5** |
| Telas que mudaram de nível | — | 44 |

- **125 subiram · 92 mantiveram · 0 caíram.** Ratchet verde (0 regressões vs `origin/main`).
  Coverage gate verde.

## Notas que caíram → **NENHUMA**

Zero quedas em 217 telas. Não é maquiagem — é **under-counting do baseline**: entre
2026-05-30 (baseline) e hoje entraram **duas campanhas monotônicas de melhoria** que o
scorecard nunca capturou:

1. **2026-05-31** — "37 telas `<70 → ≥70`" (US-TR-309..314). Reescreveu as telas mais
   fracas no dia seguinte ao baseline. É a origem dos maiores saltos.
2. **2026-06-13 (#2666)** — adoção em massa do DS (329 tokenizações, 132 arquivos, 32
   módulos). Sobe `preflight_conformance` + `internal_consistency` transversalmente.

### Saltos grandes verificados (amostragem adversarial, não confie cego)

Todos os saltos `≥+14` caem nas telas mais fracas do baseline — sinal clássico de
inflação por ancoragem. **Verifiquei os maiores contra git + código real**; são legítimos
(tela reescrita na campanha 05-31, baseline 05-30 estava defasado):

| Tela | Antes→Depois | Evidência |
|---|---:|---|
| NfeBrasil/Transactions/NfceStatus | 38 → 74 | reescrita completa 05-31: PageHeader + DS, 5 estados, ações reais (Verificar/DANFE/Reemitir), polling `hasGivenUp`. Dims variadas 62–88, gaps citam TODOs reais no arquivo. |
| Produto/StockHistory | 47 → 80 | redesign 05-31: timeline via Inertia `defer` + skeleton, PageHeader, partial reload. Nota honesta: "saldo corrente pende Wave 3". |
| Jana/Painel | 55 → 74 | esqueleto `.jc-*` virou hub launcher DS-clean; density mantida em 55 (tela esparsa) — não inflou. |

Spot-check cross-batch (agentes diferentes) consistente: resumos citam construções de
código reais e TODOs do próprio arquivo, dimensões variadas (não uniformemente altas).

## Achados pro Wagner (humano-gated — NÃO maquiar)

1. **4 scorecards órfãos removidos** (tela deletada do código, não stale):
   - `atendimento-inbox-index` (Inbox removida #2522 → CaixaUnificada)
   - `jana-brief-index` (stub `/ia/brief` removido #2777)
   - `oficinaauto-producaooficina-index` + `oficinaauto-serviceorders-index`
     (OS unificada em 1 workspace #2544)
2. **5 telas ainda `<70`** (candidatas a evolução — Onda 2+, não escopo desta onda):
   Financeiro/AssinaturaAtualizar (64), Financeiro/Unificado/Novo (65), ads/Admin/Graph (68),
   Financeiro/Advisor/Login (68), Jana/Regras/Index (68).

## Limite honesto

A nota é LLM-as-judge de uma passada. Verifiquei os maiores movimentos por amostragem
(não os 217). A catraca (`screen-grades-ratchet`) protege contra queda futura; a foto
lado-a-lado (`screen-grade-report.mjs`) continua expondo comportamento/D1 separado da UX
(plugar, não fundir — ADR 0320). Re-grade ≠ prova de comportamento: `casos_coverage` e
`d1_calculo` seguem sendo o dente real, intocados aqui.

## Entrega / PRs

Bin-packed por prioridade (Sells/POS → Financeiro → Fiscal/NfeBrasil → cauda), cada PR
≤300 linhas de churn YAML, ratchet verde. **PRs seguram até o #3820 (o plano) mergear**
— regra da sessão executora do doc.
