---
date: "2026-07-02"
time: "13:10"
slug: p10w3-charters-related-us-backfill
tldr: "P10 wave 3: backfill related_us nos charters de tela (cobertura 30/158→55/158). 4 PRs #3633-3636 (25 charters só-frontmatter) + 15 joins deferidos pra fila A6 §3-bis (charters live sem sinal). Refutador Fable 5 G5: 66 joins, 1 refutado (Purchase/Edit→US-COM-004, removido), error_rate 1,5% aprovado. ~62 órfãs ficam sem campo (dependem de US-nova no SPEC)."
prs: [3633, 3634, 3635, 3636]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Handoff — P10 wave 3: backfill `related_us` nos charters (join US→tela)

## O que foi feito

Backfill do campo canônico `related_us:` no frontmatter dos charters de tela que
ainda não tinham (30/158 → 55/158). Continuação da campanha P10/SA-A5
([ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md);
lotes precedentes: Jana #3544, OficinaAuto #3542, Financeiro #3540, wave 2 #3571-3580).

Join derivado por evidência real (join reverso da âncora `Implementado em:`, ou
citação de rota/Page/Controller, ou `Inertia::render` do Controller da US). Charter
sem US identificável ficou **sem campo** (Tier 0: nunca inventar slug).

## PRs abertos (aguardam merge Wagner — R10)

| PR | Lote | Charters |
|---|---|---|
| [#3633](https://github.com/wagnerra23/oimpresso.com/pull/3633) | Fiscal | 7 |
| [#3634](https://github.com/wagnerra23/oimpresso.com/pull/3634) | Cliente+KB | 7 |
| [#3635](https://github.com/wagnerra23/oimpresso.com/pull/3635) | Gestão (ProjectMgmt+Purchase+RB+Auditoria) | 5 |
| [#3636](https://github.com/wagnerra23/oimpresso.com/pull/3636) | Admin+MCP + fila A6 §3-bis + ledger + session log | 6 charters + docs |

25 charters editados (só frontmatter). 15 joins deferidos (charters `status:live`
sem sinal, barrados pelo gate `charter-live-signal`) foram estacionados na fila A6
§3-bis, prontos pra aterrissar quando a tela ganhar smoke/prod-flag.

## Verificação (protocolo G5)

Refutador **Fable 5** (tier superior ao gerador Opus 4.8), sessão fresca, 100% dos
anchors: **66 joins · 1 refutado · error_rate 1,5% < 2% → aprovado.**
- Refutado e **removido antes do merge**: `Purchase/Edit → US-COM-004` (US de infra
  de deprecação, não descreve a tela). Charter revertido.
- Entry no ledger `SA-A5-P10w3-charters` (#3636), `ledger-check --pr 3636` → `ok:true`.
- `charter-us-lint --check` verde em todos os lotes.

## Estado / próximos passos

1. **Merge dos 4 PRs** (Wagner R10) — `gh pr checks` antes; PRs são docs-only
   (frontmatter + fila A6 + ledger), sem UI/smoke.
2. **Órfãs (~62 charters)** não têm US no SPEC — fechar exige **escrever US nova no
   SPEC dono** (Produto/Repair/Sells-detalhe/team-mcp/Stock/etc), não é backfill.
   Fica pra quando os SPECs desses módulos ganharem US ancoradas.
3. **Decisão Wagner pendente** (proposta no §final do session log, NÃO executei):
   catraca agregada de cobertura charter-us no scorecard? Recomendação = **não** agora
   (a cauda depende de US-nova, não de join; alinhado ADR 0314 required=só-Tier-0;
   o no-new-lie por-charter do `--check` já é o floor honesto).
4. **Fila A6 §3-bis** tem agora 28 joins deferidos verificados (13 wave1 + 15 wave3) —
   aterrissam por PR trivial quando cada tela live ganhar sinal de prod.

## Estado MCP no momento do fechamento

Não consultei MCP tools nesta sessão (trabalho foi 100% git/filesystem sobre charters
canônicos + refutação por subagente). Brief do SessionStart: cycle sem nome ativo,
2 HITL Wagner pendentes (FIN-004, runbook on-prem), 0 incidentes 24h, ADRs recentes
0316/0317/0318. Nenhuma task MCP tocada — este trabalho é campanha de conteúdo P10
(documentação canônica), não US de backlog.
