---
slug: 0176-mwart-excecao-cliente-show-wave-paralela
number: 176
title: "MWART exceção — Cliente/Show Wave paralela paridade tabs (visual regression override)"
type: adr
status: aceito
authority: reference
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: Cliente
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0108-regressao-visual-pest-browser-tier-2
  - 0149-mwart-screen-pattern-reuse-cowork
---

# ADR 0176 — MWART exceção: Cliente/Show Wave paralela paridade tabs

## Contexto

PR #1298 (`feat/cliente-show-paridade-tabs-paralelo`) reescreve estrutura visual de `resources/js/Pages/Cliente/Show.tsx` substituindo "Histórico de transações" minimal (lista 20 itens) por 4 tabs canon (Extrato / Vendas / Pagamentos / Documentos) + dropdown Ações header + badge Inativo.

A mudança **trigerou visual regression** no gate Pest Browser (ADR 0108) — baseline screenshot do Show.tsx pré-Wave não casa mais com o render pós-Wave (intencionalmente).

Wave executada por 5 sub-agents (coordenador-paralelo) entregando 6 sub-components em `_show/`, consolidados via wiring controller + Show.tsx em 5 commits sequenciais. Pest unit 40/40 verde. Multi-tenant Tier 0 (ADR 0093) preservado.

## Decisão

Aprovar `/mwart-override` aplicado no PR #1298 ([comment 4507720825](https://github.com/wagnerra23/oimpresso.com/pull/1298#issuecomment-4507720825)) como **exceção INTENCIONAL** ao gate visual regression. Justificativa:

1. **Aprovação explícita Wagner** — sessão 2026-05-21 ("ative para todos") autorizou a evolução visual conforme `memory/requisitos/Cliente/show-visual-comparison.md` (status: approved).
2. **Paridade funcional crítica** — Show legacy tinha 10 tabs com features que React Show não possuía; ativar SHOW sem fechar paridade era inviável (rollback `MWART_CLIENTE_SHOW=false` foi forçado por isso).
3. **Baseline screenshot obsoleta intencionalmente** — quando `tests/Browser/` rodar pós-merge em runner com chromedriver, baseline será regenerada via `--update-snapshots`. Não fazer agora porque (a) prazo curto, (b) ambiente local Windows + Herd não tem setup browser-test estável.
4. **F1.5 gate satisfeito** — `show-visual-comparison.md` documenta matriz 15 dimensões com nota 38.7→76.2/100 e Wagner aprovou explicitamente.

## Consequências

- Visual regression gate fica em status "override ativo" até alguém rodar `./vendor/bin/pest tests/Browser/ --update-snapshots` num runner válido + commit dos novos `tests/Browser/Screenshots/`.
- Risco residual: se houver regressão visual NÃO-intencional escondida sob o override, ela não será detectada até o próximo update de snapshot. Mitigação: smoke prod logado pós-deploy (checklist em `memory/requisitos/Cliente/RUNBOOK-show.md`).
- Sem impacto em outros gates (multi-tenant, module grade Crm +16, type-check, build vite).

## Alternativas consideradas

- **Update snapshots local** — descartado: setup browser-test não confiável em Windows + Herd, custaria 1-2h pra estabilizar ambiente; bloqueia prazo apertado.
- **Não mergear até snapshots atualizados** — descartado: mantém Show paridade desligada em prod, perdendo o ganho da Wave.
- **Revert mudança visual** — descartado: derrota o propósito da Wave (paridade funcional 40%→85%).

## Refs

- PR #1298 — `feat/cliente-show-paridade-tabs-paralelo`
- `memory/requisitos/Cliente/SPEC.md` (US-CRM-063..067)
- `memory/requisitos/Cliente/show-visual-comparison.md` (matriz paridade)
- `memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md` (coordenação paralela)
- ADR 0107 — visual-comparison gate F1.5
- ADR 0108 — regressão visual Pest Browser Tier 2
