---
date: "2026-07-03"
time: "17:03 BRT"
slug: dente-calculo-produto
tldr: "Dente de cálculo do Produto (motor preço/margem indefeso) entregue + registrado no PLANO-MESTRE; stand-down do resto da onda Produto (owned por sessão paralela #3729)."
prs: [3730, 3733]
decided_by: [W]
related_adrs: [0320-programa-ondas-regua-correcao]
next_steps: ["Onda Produto adversário/backlog: acompanhar #3729 (sessão funny-cerf) — NÃO duplicar"]
---

# Handoff — Dente de cálculo do Produto (Onda 1.4 aplicada) + stand-down

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ATIVO em COPI (programa de ondas é transversal, `parent_plan=programa-ondas`).
- **my-work (@wagner):** 30 tasks (8 review / 8 blocked / 14 todo) — nenhuma mapeia direto ao trabalho desta sessão (dente rodou via parent_plan, não US específica). Sem `tasks-update`.
- **decisions-search:** timeout MCP (não-bloqueante — ADR 0320 já conhecida via PLANO-MESTRE).

## O que aconteceu
Tarefa da sessão: **dente de cálculo do módulo Produto** (programa de ondas). Verifiquei em `origin/main` que os métodos de **preço/margem** do `ProductUtil` estavam **sem teste nenhum** (`calc_percentage`/`calc_percentage_base`/`get_percent` + `getVariationGroupPrice`/`calculateComboDetails`). Escrevi `tests/Feature/Calculo/CalculoValorProdutoTest.php` (TEST-ONLY, 6 grupos: property markup/round-trip/margem + 2 goldens DB + discriminador RED) espelhando o padrão do `CalculoValorSellsTest`. **21 passed / 83 assertions no CT100** (MySQL biz=1; os 2 goldens DB rodaram de verdade via `EstoqueFixture`, não skiparam). Não dupliquei Sells (#3695) nem Compras (#3722). `getProductDiscount` ficou fora (lookup puro, zero cálculo).

Wagner mergeou #3730 + #3733 e pediu "abre e merge" a onda Produto completa. Ao tentar abrir, **descobri colisão de sessão paralela**: Wagner roda ~10 sessões simultâneas (uma por módulo) e a **Produto 3.1** (`funny-cerf`, **PR #3729**) já entrega FICHA (nota 61) + INVENTARIO + 7 tasks. Meu agente `audit-senior-expert` duplicou a FICHA (nota 53 divergente) — **removi os strays**, main limpo. Wagner decidiu (AskUserQuestion): **eu fico de fora**, a sessão dona mergeia o #3729.

## Artefatos gerados
- `tests/Feature/Calculo/CalculoValorProdutoTest.php` (+~330 linhas) — **#3730 MERGEADO**.
- `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` (§Status vivo: linha **Onda 4 — Produto** 🟡 só o dente + summary) — **#3733 MERGEADO**.

## Persistência
- **git:** #3730 + #3733 mergeados em `main`. Este handoff via `claude/handoff-dente-produto`.
- **MCP:** webhook GitHub→MCP propaga o handoff em ~2min após push.
- **BRIEFING:** não tocado (dente é test-only; o adversário/FICHA do Produto é do #3729).

## Próximos passos pra retomar
- Onda Produto (adversário/backlog): **acompanhar #3729** — NÃO abrir PR competidor (Tier 0 não-duplicação).
- Régua Produto: já seedada (8 charters draft + 8 scorecards; Unificado/Index 56 é o alvo baixo).
- Próxima lane livre: pedir a Wagner o módulo/lane específico pra não colidir com sessões-irmãs.

## Lições catalogadas
- **Parallel-session collision é real e caro:** "abre a onda X" pode já estar em execução por sessão dedicada. **Checar `list_sessions` + `gh pr list` ANTES de spawnar agente de pesquisa** (o `audit-senior-expert` gastou ~151k tokens numa FICHA duplicada). Regra memory `sessoes-paralelas-mesma-branch` reforçada.
- **RED em TEST-ONLY é estrutural:** o discriminador reproduz o strip-do-ponto inline (não muta prod) — mesmo padrão do Sells 1.4. Mutar `num_uf` no container foi (corretamente) barrado pelo classifier.

## Pointers detalhados
- Padrão do dente: [`onda-1-sells/1.4-dente-calculo.md`](../requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md)
- Motor testado: `app/Utils/ProductUtil.php` (:911 markup · :917 base · :920 margem · :1067 grupo% · :623 combo) + `app/Utils/Util.php` (:150 calc_percentage · :162 calc_percentage_base)
- Onda Produto adversário/backlog: PR #3729 (sessão funny-cerf)
