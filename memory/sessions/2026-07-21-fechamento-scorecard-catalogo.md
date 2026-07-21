---
date: "2026-07-21"
hour: "15:41 BRT"
topic: "Fechamento dos sete follow-ups: juiz cego, kappa humano, C7 v1.1, SUPERFICIE, lápides, ProductUtil e catálogo"
authors: ["C"]
tags: [governanca, funcao-scorecard, calibracao, kappa, superficie, catalogo]
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
outcomes:
  - "Blind pack deixou de vazar nomes e narrativa de docblocks; rodadas antigas foram reclassificadas como parcialmente contaminadas."
  - "Scorecard ProductUtil migrou para dez critérios com C7a..C7d e ganhou fingerprint de código/rubrica."
  - "Catálogo passou a 38 módulos com dependsOn consultável; superfícies vivas ficaram completas e enforçadas."
---

# Fechamento scorecard e catálogo

## Contexto

Wagner autorizou implementar e fazer merge dos sete chips de refinamento do PR 4617. O trabalho integrou os mecanismos existentes em vez de criar fontes concorrentes.

## Alterações

1. O pack de calibração passou a emitir IDs opacos e a sanitizar narrativa de comentários/docblocks.
2. Foi criado o runner de rotulagem humana, com nove itens obrigatórios, K/9 e Cohen kappa.
3. C7 foi decomposto em quatro binários e aplicado às 37 funções do `ProductUtil`.
4. O sentinela `briefing-code-staleness` ganhou frescor de scorecard e validação das lápides legadas.
5. `module-surface --all --check` passou a exigir todos os módulos vivos, Classes B e `_Geral`; SRS ganhou superfície.
6. O catálogo passou a derivar Classes B e `dependsOn`, aceitar fronteiras futuras como `referenced-only` e responder `--query`.
7. O ownership de `mcp_skill_telemetry` foi corrigido para Brief com base no código real.
8. O workflow do catálogo foi registrado no `gates-registry.json`, fechando o Check G/M do umbrella.

## Validação

- Node: 48/48 testes.
- Memory-health: 0 falhas.
- Meta-testes: 13/13 ADR governance e 38/38 memory-health.
- Catálogo: 38 módulos, 627 nós, 950 arestas, 0 módulos/ADRs pendurados.
- Superfícies: 39/39 sem drift.
- YAML: quatro workflows e scorecard parseados.

## Decisão e pendências

A calibração humana não foi preenchida em nome de [W] e a independência de três juízes não foi simulada. Esses são os únicos passos de evidência humana ainda abertos; o mecanismo para coletá-los ficou pronto. Ver o [handoff](../handoffs/2026-07-21-1541-fechamento-scorecard-catalogo.md).
