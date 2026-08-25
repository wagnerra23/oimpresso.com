---
id: requisitos-documentacao-runbook-programa
title: "RUNBOOK — /documentacao/programa (Trilha D: plano em git × tasks MCP)"
module: Documentacao
tela: Documentacao/Programa
owner: W
status: rascunho
last_validated: "2026-08-06"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0070-jira-style-task-management-current-md-removed
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — `/documentacao/programa` (Trilha D)

> ⚠️ **NUNCA EXECUTADO** — a tela ainda não existe. `last_validated` é data de criação.
>
> O compartilhado vive em **[RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)**; as duas fontes
> desta tela estão detalhadas no §8 de lá.

## Rota

`GET /documentacao/programa` → `Inertia::render('Documentacao/Programa')`. **Acima do `{slug}`** no
grupo: `programa` casa a regex do slug e seria engolido.

## As duas fontes — e por que a tela existe

Ela é a única do conjunto que não migra nada. Existe porque cruza duas coisas que markdown sozinho
não cruza:

1. **`TrilhaDParser`** — a § Trilha D de `_Governanca/programa-ondas/PLANO-MESTRE.md`.
   Seções **reais**: `D.1` camadas · `D.2` onde o estado vive · `D.3` ondas D0–D10 · `D.4` ciclo de
   11 estações · `D.5` caminho por tipo · `D.6` batimento · `D.7` DoD.
   **Não existe `D.8`** — um mapa que circulou apontando `dod` para D.8 e `estacoes` para D.2 está
   errado em quatro linhas, e um parser escrito contra ele leria "onde o estado vive" achando que
   é o ciclo. O parser não inventa campo: o que não está no plano volta vazio, e mudança de forma
   falha alto.
2. **`EstadoDasOndas`** — as tasks MCP com `parent_plan=programa-ondas`, que injetam
   `todo|doing|done`. Estado **nunca** é chumbado em markdown nem no `.tsx` (ADR 0070). Sem MCP, o
   serviço devolve **indisponível** e a tela renderiza sem estado.

## Vocabulário

Canônico no dado: `todo` · `doing` · `done`. Rótulo humano ("na fila", "em execução") é tradução
na borda, dentro do `.tsx`, declarada lá.

## Sintomas

| Sintoma | Causa provável |
|---|---|
| Onda com `doing` sem MCP responder | status chumbado — viola ADR 0070 e o anti-hook do charter |
| Estrutura parcial silenciosa | o parser passou a completar lacuna com default em vez de falhar alto |
| Tela dá 404 | rota declarada depois do `{slug}` |
| Botão de marcar onda/DoD | a tela virou de escrita — Non-Goal read-only |

## Refs

- [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) §8 · [PLANO-MESTRE § Trilha D](../_Governanca/programa-ondas/PLANO-MESTRE.md) · [GOV-PROGRAMA-DOCUMENTACAO.md](../../reference/GOV-PROGRAMA-DOCUMENTACAO.md)
