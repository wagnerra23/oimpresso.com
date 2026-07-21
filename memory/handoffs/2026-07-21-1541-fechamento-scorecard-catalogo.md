---
date: "2026-07-21"
time: "15:41 BRT"
slug: fechamento-scorecard-catalogo
tldr: "Sete follow-ups do PR 4617 fechados: blindagem real do juiz, calibração humana pronta, C7 binário, superfícies completas, lápides, ProductUtil fresco e catálogo consultável."
decided_by: [W]
cycle: null
prs: [4644, 4645]
us: []
next_steps:
  - "Coletar os nove rótulos cegos de [W] e calcular Cohen kappa contra o gold selado."
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
---

# Handoff 2026-07-21 15:41 BRT — fechamento scorecard e catálogo

## TL;DR

Os sete itens pedidos foram implementados e verificados. A principal correção metodológica foi admitir que as rodadas antigas deixavam pistas nos nomes e docblocks: o pack integrado ao PR #4645 usa labels `L01...` em ordem de hash, remove narrativa e já foi reexecutado por quatro famílias de modelo. A nota humana não foi fabricada; o mecanismo está pronto para receber nove rótulos de [W].

## Entregue

- Fixture cega v1.2: IDs opacos, docblocks sanitizados e testes anti-vazamento.
- Calibração humana: template cego, abertura tardia do gabarito, K/9 e Cohen kappa.
- Rubrica C7 v1.1: C7a/C7b/C7c/C7d aplicados às 37 funções do `ProductUtil`.
- Frescor do scorecard: `source_blob_sha` + versão da rubrica no sentinela já existente.
- Onze `Modules/*/BRIEFING.md` concorrentes validados como lápides canônicas.
- `SUPERFICIE.md` obrigatória para módulos vivos, Classes B e `_Geral`; SRS foi incorporado.
- Catálogo derivado e consultável: 38 módulos, 627 nós, 950 arestas, `dependsOn` e nós `referenced-only`.
- Ownership de `mcp_skill_telemetry` corrigido no Brief a partir da migration e dos writers reais.
- Workflow novo `catalog-graph.yml` registrado no catálogo canônico de gates como advisory com revisão em 2026-08-04.

## Provas locais

- 52/52 testes Node relevantes após integrar a `main` e resolver a sobreposição com o PR #4645.
- `briefing-code-staleness.test.mjs`: todos os bite/release passaram.
- `module-surface --all --check`: 39 superfícies sem drift.
- `catalog-graph --check`: sem drift e sem aresta estrutural pendurada.
- `memory-health`: 0 falhas; 12 avisos legados.
- Meta-testes: ADR governance 13/13 e memory-health 38/38.

## Fronteira honesta

- A calibração humana real continua pendente dos nove rótulos de [W].
- Quatro famílias de modelo já julgaram o pack sem os vazamentos residuais; a evidência e a fronteira de erro vieram do PR #4645.
- Quatro tabelas mantêm co-ownership explicitamente declarado e aparecem como revisão, não como erro estrutural.
- O índice de planos já estava stale e seu step é advisory no umbrella; não foi regenerado fora do escopo.

## Estado MCP no momento do fechamento

As tools MCP do produto não estavam disponíveis nesta tarefa Codex. Estado, código e validações foram consultados diretamente no Git local; PR e CI serão operados pelo `gh`. Nenhum estado MCP foi presumido.
