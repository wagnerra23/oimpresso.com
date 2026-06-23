---
slug: 2026-06-04-sessao-design-gates-recurringbilling
title: "Sessão 2026-06-04 — 5 PRs merged (dark bug, analyzer, RecurringBilling DS v6, gates DS, task gate-visual); premissa do gate visual corrigida"
type: session
status: fechada
date: "2026-06-04"
authors: [claude-cowork, claude-code]
---

# Sessão 2026-06-04 — fechamento

## Pedido [W] (arco da sessão)
Começou em "memórias conflitantes / como gerenciar design de forma séria" → consultar o git real → executar agressivo-paralelo. Terminou com 5 PRs em `main` ([CL] executou; [CC] = grounding + recomendação + auditoria).

## Mergeado em `main` (5 PRs · ✓ por [CL])
| PR | Entrega |
|---|---|
| #2209 | `.fin-stat-hero` dark (bug real — hero card fundia no bg escuro) |
| #2210 | analyzer de CSS morto + relatório (922 regras ≈ 4039 linhas mortas em `sells-cowork.css`) |
| #2212 | **RecurringBilling re-skin DS v6** (Tailwind-cru → warm DS · 3 colunas · drawer molde Financeiro · sem page-header · conforme gabarito ds-v6). Merged c/ UI-Judge 90/100 como rede; [W] confirma em staging |
| #2216 | **gates DS** (`foundation-guard` + `conformance-gate`) — **cor crua trava de verdade agora** |
| #2217 | US-GOV-013 (task do conserto do gate visual) |

## Decisões [W]
- ✅ **#2216 gates — mergeado.** Lado lint/estrutural do **Pilar 6 ligado** (máquina cobra cor crua).
- ⏸️ **`foundations.css` (font IBM Plex global) — DEFERIDO.** Blast radius máximo + gate de pixel é stub → não entra blind. Só com gate visual real OU olho em staging.
- ✅ **Pipeline visual — task US-GOV-013 aberta** (p2, ~8h, não-bloqueador).

## 🔑 Correção de premissa (a mais importante da sessão)
O **gate de pixel `visual-regression.yml` (ADR 0108) é STUB** (`continue-on-error`, travado por migration-order UltimatePOS legacy). Então "os gates pegam regressão" vale pro **semântico/lint (UI-Judge, ESLint, Module-Grades, conformance — reais)**, **NÃO pro pixel**. Pra mudança **visual**, a rede real = **UI-Judge (LLM) + olho de [W]/staging**. → mudança global não entra blind; o olho de [W] em re-skin **não é redundante hoje**. (Detalhe em `2026-06-04-reframe-gerenciar-design-serio.md`.)

## Achados de auditoria (grounded, [CC])
- **Régua ds-v6 LIMPA** (gabarito/showcase/receita/mapa = só token, 1-4 exceções). O padrão está sólido.
- **Implementações do host com cor crua** (teto bruto ~1595), **mas [CL] refinou: muito é código morto** (0 consumidores) → cor crua VIVA bem menor. `vendas.css` desmentiu o "100% tokenizado".
- **Guardrail travado:** portar tela = conformar ao gabarito ds-v6, **nunca** copiar `*-page.css` do host.

## Aberto (não solto — gated/parked)
- **US-GOV-013** — conserto do gate visual (keystone: desbloqueia `foundations.css` + automatiza aprovação das 44 telas).
- **#2212** em staging — [W] confirma o visual quando puder (já mergeado).
- **Remoção incremental do CSS morto** — relatório do #2210 guia, 1 família/PR (começar OS detail-drawer), nunca nuke (gate de pixel não cobre className dinâmico).
- **Raias de higiene** (ADR DS v6 nomeado, lápides docs stale, backfill lifecycle) — pontes prontas em `COWORK_NOTES`.

## Meta-lição da sessão
A pergunta evoluiu de "reconciliar memória" → "**o aparato está desenhado 85/execução 40; ligar > escrever**". O valor veio de **ler o git real** (não inferir — L-26/L-27 reincidiu 1× e foi corrigido) e de **auditar > confiar na palavra** (vendas "100%" era falso; CSS morto inflava o número). Disciplina: parar de autorar governança, ligar a que existe, medir.
