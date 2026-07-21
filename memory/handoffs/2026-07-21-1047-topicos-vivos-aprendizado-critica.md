---
date: "2026-07-21"
time: "10:47 BRT"
slug: topicos-vivos-aprendizado-critica
tldr: "Branch codex/aprendizado-topicos-vivos pronta para PR: tópico schema'd, ADR 0345, piloto Produto e SUPERFICIE corrigida para não contar componentes como telas."
decided_by: [W]
cycle: null
prs: [4617]
us: []
next_steps:
  - "Acompanhar CI do PR e corrigir somente falha introduzida por esta branch."
  - "Depois do piloto, provar o resolvedor reclamação até código/teste e reportar ambiguidades."
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
---

# Handoff 2026-07-21 10:47 BRT — tópicos vivos e aprendizado por crítica

## TL;DR

A arquitetura aprovada por Wagner foi implementada sem backfill em massa. O PR separa BRIEFING/índice de tópicos, formaliza aprendizado revisável e corrige o catálogo de telas; não altera lógica de valor/estoque.

## Estado atual dos artefatos

- ADR 0345 aceita: críticos IA propõem, IA central sintetiza, humano aprova canon.
- `topico.schema.json` entrou no memory-schema gate em grace e no validador Jana.
- Tópico piloto `Produto/calculo-total-fatura` registrou positivo, negativo e incerteza com evidência.
- O scorecard ProductUtil recém-mergeado no #4616 foi centralmente revisado e invalidado como bite-test: C2 contradizia o golden e C8 inflava 1 teste direto para 5.
- Financeiro passou de 60 `.tsx` classificados como tela para 21 Pages executáveis, alinhadas aos 21 charters.
- Self-tests 12/12; mapa 235/235 charters; oito superfícies sem drift; AJV do piloto verde.

## Decisões tomadas

| Pergunta | Decisão Wagner | Justificativa | Referência |
|---|---|---|---|
| Seguir em PR? | Sim | Pedido textual em 2026-07-21 | ADR 0345 |
| Crítica distribuída evolui o sistema? | Sim, sem escrita canônica autônoma | Evidência + síntese + aprovação preservam novidade e controle | ADR 0345 |

## Bloqueios / pendências

- Nenhum bloqueio de implementação conhecido.
- Promoção do schema a required ficou deliberadamente pendente de piloto e nova aprovação humana.

## Próximos passos

1. Abrir o PR e acompanhar os checks.
2. Não fazer merge automático; R10 permanece com Wagner.
3. Próximo experimento recomendado: resolvedor de reclamação ponta a ponta com ambiguidade explícita.

## Estado MCP no momento do fechamento

As tools MCP operacionais (`cycles-active`, `my-work`, `sessions-recent`, `decisions-search`, `whats-active`) não estavam disponíveis nesta tarefa Codex. O estado foi verificado por Git local, `gh` e arquivos canônicos; nenhuma afirmação de estado MCP foi fabricada.

## Referências

- PR: [#4617](https://github.com/wagnerra23/oimpresso.com/pull/4617)
- Session log: [2026-07-21-topicos-vivos-aprendizado-critica-revisada.md](../sessions/2026-07-21-topicos-vivos-aprendizado-critica-revisada.md)
- ADR 0345: [Tópicos vivos e aprendizado por crítica revisada](../decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
