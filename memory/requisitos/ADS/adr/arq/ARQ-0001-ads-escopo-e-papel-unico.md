---
id: requisitos-ads-adr-arq-arq-0001-ads-escopo-e-papel-unico
slug: ARQ-0001-ads-escopo-e-papel-unico
title: "ADS — Escopo, definição e papel único"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: []
---

# ARQ-0001 — ADS: Escopo, definição e papel único

## Contexto

O projeto acumulou múltiplos sistemas de automação com papéis sobrepostos:
- `SinteseSemanalAgent` faz síntese narrativa
- `EvolutionAgent` monitora codebase
- `cc-watcher` ingere sessões Claude Code
- `TaskRegistry` gerencia tarefas
- MCP server centraliza memória

Sem definição formal do meta-sistema que os orquestra, os sistemas conflitam silenciosamente:
dois agentes modificam o mesmo arquivo, a síntese semanal recalibra thresholds que o Learning Loop
já ajustou, ou uma tarefa é criada duas vezes por fontes diferentes.

## Decisão

O **Adaptive Decision System (ADS)** é o meta-sistema que orquestra todos os agentes e engines.

**Definição formal:**
> ADS é a camada que recebe eventos, decide qual agente age, com qual autoridade, e retroalimenta
> o sistema com o resultado. O ADS não executa nada diretamente.

**Papel único do ADS:**
```
evento → [Risk Engine + Confidence Engine + Policy Engine] → Decision Router → agente certo → outcome → Decision Memory → Learning Loop
```

### O que o ADS É

- Meta-orquestrador de decisões
- Fonte única de roteamento de eventos para agentes
- Responsável por garantir que nenhuma decisão aconteça sem registro auditável
- Camada de governança que resolve conflitos entre agentes

### O que o ADS NÃO É

| Não é | É responsabilidade de |
|---|---|
| Executor de código | Claude Code |
| Monitor de eventos | Brain A (Ollama daemon) |
| Repositório de memória | MCP Server |
| Gestor de tarefas | TaskRegistry |
| Respondedor de chat | Copiloto Chat |
| Sintetizador narrativo | SinteseSemanalAgent |

### Fronteira com EvolutionAgent

O EvolutionAgent é um **cliente do ADS**, não parte dele. O EvolutionAgent detecta oportunidades
de melhoria no codebase e submete eventos ao ADS. O ADS decide se executa, escalona ou bloqueia.

### Fronteira com Copiloto Chat

Copiloto Chat responde perguntas do usuário de negócio (Larissa, Wagner operacional).
ADS opera no plano da engenharia de software. Os dois sistemas não se comunicam diretamente
— compartilham apenas o MCP como bus de memória.

## Consequências

**Positivas:**
- Um único ponto de entrada para decisões automatizadas elimina race conditions entre agentes
- Auditoria centralizada: toda decisão passa pelo ADS e é gravada em `mcp_dual_brain_decisions`
- Governança clara: saber "quem decidiu isso e por quê" é possível para qualquer ação

**Negativas:**
- O ADS é um ponto único de falha. Mitigação: Policy Engine e Brain A operam mesmo se ADS estiver
  degradado, mas em modo conservador (tudo escalona para Wagner)

## Módulo Laravel

`Modules/ADS/` — módulo independente, sem dependência de Copiloto ou EvolutionAgent.
Depende apenas de: MCP Server (via HTTP), TaskRegistry (via HTTP), `laravel/ai`.
