---
date: '2026-07-02'
topic: "Capterra de capacidade do módulo Sells (Onda 1.1) — benchmark vs 13 concorrentes de PDV, nota 60/100, leitura adversarial do que a nota 90 esconde"
authors: [C]
related_adrs:
  - 0089-capterra-driven-module-evolution
  - 0101-tests-business-id-1-nunca-cliente
  - 0284-pipeline-incidente-graduado-confianca
prs: [3699]
---

# Session — Capterra de capacidade do módulo Sells (Onda 1.1)

## TL;DR

Gerada a **ficha de capacidade** do módulo Sells (`CAPTERRA-FICHA.md`, nota **60/100**) vs 13 concorrentes de PDV/venda — complementa a `CAPTERRA-DESIGN-FICHA.md` (UX, 68). Achado central (pedido da onda "o que a nota 90 esconde"): a nota 88-90 é **screen-grade de design**, cega a cálculo/fiscal/offline/pagamento; capacidade real 60. Gap #1 = **sem teste E2E de correção de cálculo** (o `store` test é tautológico/strpos); o incidente R$ inflado ×100k em 16 vendas (2026-06-05) foi pego em produção, não por teste. Gap C05=2 = sem offline (modo de falha real da Larissa, internet instável SC). Read-only, docs-only (PR #3699).

- **Data:** 2026-07-02
- **Agente/skill:** `capterra-senior` (via programa de ondas — Onda 1.1 "adversário concorrente")
- **Escopo:** pesquisa read-only + geração de ficha de capacidade. **Não commita código** — só a ficha + este log.
- **Entrada:** [1.1-adversario-capterra.md](../requisitos/_Governanca/programa-ondas/onda-1-sells/1.1-adversario-capterra.md)
- **Saída:** [memory/requisitos/Sells/CAPTERRA-FICHA.md](../requisitos/Sells/CAPTERRA-FICHA.md) (nova, capacidade) — complementa a `CAPTERRA-DESIGN-FICHA.md` (UX, nota 68) que já existia.

## Objetivo

Ancorar Sells no concorrente pelo eixo **capacidade** (features/fiscal/pagamento/offline/automação) — que a `CAPTERRA-DESIGN-FICHA.md` (UX) e o screen-grade (design) não medem. Pedido explícito da onda: **procurar o que a nota alta (90 Leader) esconde**.

## Método

1. Leitura do brief + ficha de design existente + formato canônico (`Repair/CAPTERRA-FICHA.md`, 10 seções).
2. **Grounding no código real** (via `origin/main` — checkout local estava 4655 commits atrás): SPEC US-SELL-001..009/040, BRIEFING, `Create.tsx`/`Show.tsx`, suíte de testes Sells, module-grade + screen-grade.
3. **Pesquisa de mercado** (subagente, ~24 buscas, 127k tokens): 13 concorrentes (Bling, Tiny, Omie, Conta Azul, Shopify POS, Square, Stripe Terminal, Lightspeed, Nex, Hiper, Linx, Clover, Toast) + 8 eixos de capacidade SOTA (velocidade, offline, pagamento, fiscal BR, pré-venda/autosave, devolução, perceived perf, sinais de qualidade).
4. Nota ponderada P0=4/P1=2/P2=1/P3=0.5 sobre 19 capacidades.

## Nota

**Capacidade = 60/100** (vs design 88-90, vs UX 68). Referências: Omie ~75 (topo BR), Bling ~66, Shopify POS ~48 (desqualificado por não ter NFC-e).

## Achados adversariais (o que a nota 90 esconde)

1. **A nota 90/88 é screen-grade de DESIGN** — não mede cálculo, fiscal, offline nem pagamento. Capacidade real é 60.
2. **Cálculo sem prova E2E.** Defesa hoje = `Math.round` frontend + `num_uf` unit tests (reativos ao incidente) + sensor runtime `sells_value_sanity` (detecção post-hoc, ADR 0284). **Falta** teste que submeta venda (desc%/split/frete) e verifique `final_total` persistido. `SellPosControllerStoreInvariantsTest` = 11 asserts **estruturais** (strpos no source) = tautológico (anti-padrão proibicoes §5). **Incidente R$ inflado ×100k em 16 vendas (2026-06-05) foi pego em produção, não por teste.** → Gap #1 (G-01).
3. **Sem offline** (C05=2, igual Bling) — modo de falha real da Larissa (internet instável em SC); Omie/Hiper/Nex/Square já resolveram.

## Diferenciais confirmados (lane vazia de mercado)

- **Autosave silencioso de rascunho** — ninguém anuncia (todos só "pré-venda" explícita).
- **Multi-tenant Tier 0** auditável + **FSM pipeline** + **venda↔OS/Oficina** (ADR 0251).

## Top 3 gaps P0 (pro backlog)

1. **G-01** teste E2E de cálculo (US-SELL-040, ~6-10h) — rede de segurança mais barata contra 2º incidente de valor.
2. **G-03** NFC-e no checkout + contingência automática `tpEmis=9` (~30h).
3. **G-02** offline-first fila IndexedDB (~40h) — medir frequência de queda antes (ADR 0105).

## Estado / próximos passos

- Ficha + log landados via branch fresco de `origin/main` (PR #3699) — checkout base local estava stale (−4655); os 2 arquivos são novos (sem conflito com main), PR docs-only limpo.
- Próxima onda do programa pode transformar G-01..G-06 em tasks MCP (`parent_plan=programa-ondas`) — fora do escopo read-only desta.

## Pegadinhas da sessão

- Checkout local −4655 de `origin/main` → toda leitura de canon feita via `git show origin/main:<path>` (guard `git-base-freshness-guard`).
- Pricing de concorrente redigido qualitativamente (sem `R$`+dígito) — Tier 0 não commita valores BRL em `memory/` ([proibicoes](../proibicoes.md)); global mantido em US$ (dado público, não-BRL).
