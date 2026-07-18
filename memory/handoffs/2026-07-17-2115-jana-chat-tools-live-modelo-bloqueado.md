---
date: "2026-07-17"
time: "2115 BRT"
slug: jana-chat-tools-live-modelo-bloqueado
tldr: "Dimensão erp-ia-produto (grade 4,5): tools READ-ONLY no chat da Jana LIVE em prod (US-141/142) — a Jana deixou de pedir os dados ao cliente e passou a consultá-los. Modelo forte (US-144, knob cirúrgico JANA_CHAT_MODEL): mecanismo done, mas o flip do gpt-4o foi REVERTIDO no mesmo dia (projeto OpenAI sem acesso, model_not_found). Todos os caminhos do modelo frontier bloqueados por credencial/decisão [W]. Alavanca = ANTHROPIC_API_KEY em prod (destrava claude-sonnet + fallback + eval)."
decided_by: [W]
prs: [4421, 4443, 4459]
next_steps:
  - "MODELO FRONTIER no chat (US-144/135) — bloqueado por acesso, ação [W]. Opção A: liberar gpt-4o de VERDADE no projeto OpenAI proj_zMcVcGyURfEVtsgTRxP4RiTs (hoje model_not_found apesar do [W] achar que liberou). Opção B (RECOMENDADA, maior alavanca): pôr ANTHROPIC_API_KEY no .env do Hostinger — destrava claude-sonnet no chat (cache Anthropic já implementado) + fallback US-135 + 2º juiz. [C] não manuseia a chave (credencial paga). Feito isso: [C] liga via JANA_CHAT_MODEL + provider, smoke real do responderChat, mede no gold-set."
  - "US-135 FALLBACK provider→provider — só faz sentido depois que houver 2º provider (= chave Anthropic acima). Sem destino, não implementar."
  - "US-137 EVAL ONLINE — mecanismo pronto+testado. Ligar: judge=openai (decisão LGPD [W], manda conteúdo redigido pro OpenAI) OU judge=local (INVIÁVEL agora: CT 100 é CPU-only + Ollama só tem embedders; precisaria GPU + modelo de chat)."
  - "Quando o modelo frontier ligar: medir antes/depois no gold-set (jana:ragas-real-eval) pra provar ganho na MÉDIA — o comparativo desta sessão é 1 amostra (ganho marginal, custo ~15×)."
related_adrs:
  - 0141-agents-tool-use-pattern-claude-code
  - 0093-multi-tenant-isolation-tier-0
  - 0245-jana-advisor-modo-consultor-clarify
  - 0101-tests-business-id-1-nunca-cliente
---

# Jana chat: tools LIVE, modelo frontier bloqueado por acesso

## O que entrou em prod (LIVE)

O chat da Jana (`ChatCopilotoAgent`) declara as 5 tools READ-ONLY do `BriefDiarioAgent` (vendas, inadimplência, tickets, NF-e, oportunidades). Antes o chat era **single-shot** e, quando o cliente pedia número, **pedia os dados de volta**. Agora consulta e responde com número vivo. `JANA_CHAT_TOOLS_ENABLED=true` em prod; R1 comprovado (`TOOL_CALLS=1 [VendasPeriodoTool]`, resposta com dado real, biz=1). Kill-switch: `=false` + `config:cache`.

Tier 0 mecânico (ADR 0141/0093): `business_id` das tools vem de `conversa->business_id`, nunca do LLM; conversa sem business → zero tools (fail-safe).

## O incidente (registrado como lição na US-144)

Liguei `JANA_CHAT_MODEL=gpt-4o` em prod → chat quebrou. O R1 (smoke do `responderChat` + eventos do stream) pegou `model_not_found`: **o projeto OpenAI de prod não tem acesso ao gpt-4o**. Revertido em minutos. **Erro meu**: o smoke inicial deu `resposta=vazio` e eu segui — o vazio era o sinal; e o `config.php` já dizia "gpt-4o → 403", eu devia ter lido no pré-flight.

## Estado de prod ao fechar

| Flag | Valor | Efeito |
|---|---|---|
| `JANA_CHAT_TOOLS_ENABLED` | `true` | tools LIVE ✓ |
| `JANA_CHAT_MODEL` | ausente | chat no gpt-4o-mini (revertido) ✓ |

Serviço OK — `/login` + `/` → 200; conversa real biz=1 responde (8 TextDeltas no mini).

## Caminhos do modelo frontier (todos bloqueados — ver next_steps)

gpt-4o (sem acesso projeto) · claude-sonnet (sem chave) · US-137 judge=openai (LGPD) · US-137 judge=local (CT 100 CPU-only) · US-135 fallback (sem 2º provider). **Alavanca única: chave Anthropic.**

## Estado MCP no momento do fechamento

MCP **parcialmente disponível**: `brief-fetch` do SessionStart falhou ("age not found"), mas `my-work`/`tasks-create` funcionaram — a **US-COPI-145** (desbloqueio do modelo frontier: chave Anthropic OU acesso gpt-4o) foi criada via `tasks-create`. US pendentes no SPEC (US-135/137/144/145); tarefas futuras nos `next_steps` acima.

## Notas de disciplina

- Branch `claude/us-copi-144-flip-revertido` (o registro da reversão) é **redundante** — o conteúdo já está idêntico em `origin/main`. Pode abandonar.
- Session log: [2026-07-17-jana-chat-tools-modelo-cirurgico.md](../sessions/2026-07-17-jana-chat-tools-modelo-cirurgico.md).
