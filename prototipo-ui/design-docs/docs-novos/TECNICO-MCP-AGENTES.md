---
id: reference-tecnico-mcp-agentes
name: Técnico — MCP e agentes
description: O que um agente pode fazer neste sistema — tools de estado vivo, trust level por manifesto, e as duas capacidades negadas no token que nenhuma conveniência reabre.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: tecnico
nav_order: 60
lente: [construir]
---

# Técnico — MCP e agentes

> O servidor MCP expõe o conhecimento canônico do `memory/` como tools
> ([ADR 0053](../decisions/0053-mcp-server-governanca-como-produto.md)). Cada ator tem manifesto
> com trust level ([ADR 0081](../decisions/0081-identity-mesh-mcp-actors.md)): **sem manifesto,
> sem ação** — default-deny.

## Estado vivo vem de tool, nunca de markdown

| Pergunta | Tool |
|---|---|
| "onde o projeto está agora?" | `brief-fetch` — primeira coisa da sessão |
| "o que é meu?" | `my-work` / `my-inbox` |
| "já decidimos isso?" | `decisions-search` |
| "qual o ciclo ativo?" | `cycles-active` |

Ciclo, tasks e brief mudam por hora. Lê-los de um `.md` é ler o saldo bancário num extrato
impresso semana passada — por isso nenhum documento deste acervo os copia.

## O que é negado no token

- `git.merge` — o agente **propõe**; o merge é o ato de ratificação do [W]
  ([ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) ·
  [ADR 0282](../decisions/0282-protocolo-v2-colapso-ratificacao.md)).
- `constituicao.edit` — lei não se edita por agente.
- Ação sem manifesto: negada, sem exceção de conveniência.
- Toda ação vai pra audit log imutável — inclusive a negada.

## Isolamento é mecânico, não instruído

O `business_id` de uma tool vem do construtor, nunca do modelo
([ADR 0141](../decisions/0141-agents-tool-use-pattern-claude-code.md)). Um prompt não é fronteira
de segurança; código é. Ver [Dados e multi-tenant](TECNICO-DADOS-MULTITENANT.md).

## Como o conhecimento chega ao agente

1. Nasce em `memory/`, no git — versionado, revisável, sem memória privada do agente
   ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).
2. Webhook empurra pro corpus, com PII redigida no caminho.
3. Dois índices convivem: léxico pra termo raro, vetorial pra pergunta em linguagem natural.
4. O recall é reordenado por relevância e decaimento — senão a verdade de seis meses atrás volta
   com a mesma confiança da de ontem.
