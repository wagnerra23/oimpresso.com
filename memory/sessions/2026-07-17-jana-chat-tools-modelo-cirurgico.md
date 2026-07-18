---
date: "2026-07-17"
hour: "21:15 BRT"
topic: "Jana erp-ia-produto (4,5): tools READ-ONLY no chat (US-141/142, LIVE) + modelo cirúrgico JANA_CHAT_MODEL (US-144, gpt-4o revertido por model_not_found) + apuração dos caminhos bloqueados do modelo frontier"
authors: [C]
prs: [4421, 4443, 4459]
related_adrs:
  - 0141-agents-tool-use-pattern-claude-code
  - 0093-multi-tenant-isolation-tier-0
  - 0245-jana-advisor-modo-consultor-clarify
  - 0101-tests-business-id-1-nunca-cliente
---

# Jana — tools no chat + modelo cirúrgico (dimensão erp-ia-produto 4,5)

## TL;DR

Mandato: mover a dimensão `erp-ia-produto` (grade de réguas 2026-07-17, nota 4,5) — "5/5 tools read-only, a Jana conversa mas não age".

**Entregue e LIVE:** o chat da Jana (`ChatCopilotoAgent`) passou a declarar as 5 tools READ-ONLY do `BriefDiarioAgent` (US-141/142). Antes ele era **single-shot** — recebia `ContextoNegocio` pré-cozido e, quando o cliente pedia número, **pedia os dados de volta** ("preciso saber quais são os dados de vendas que você possui"). Agora consulta e responde com número vivo. Flag `JANA_CHAT_TOOLS_ENABLED=true` ligada em prod, R1 comprovado.

**Tentado e revertido:** ligar um modelo forte no chat (US-144, `JANA_CHAT_MODEL` cirúrgico). O mecanismo está pronto e mergeado, mas o **gpt-4o está bloqueado por acesso** — o projeto OpenAI de prod não tem acesso (`model_not_found`), apesar da autorização do [W].

## O que mudou o diagnóstico (medição, não leitura)

- A grade dizia "5/5 tools read-only". A medição achou mais fundo: **1 de 14 agents** declarava tools (`BriefDiarioAgent`, que NÃO conversa — é o brief por cron). O chat tinha **zero**.
- A grade apontou `config.php:531` como "o modelo travado" — mas aquele bloco é o do `clarify`, não o do chat. O chat usa `AI_OPENAI_TEXT_DEFAULT`.
- Suspeitei que o streaming "descartava tool-calls" (comentário `// ToolCalls ignorados` no driver). **Refutado no vendor**: `HandlesTextStreaming::handleStreamingToolCalls` executa a tool (`$this->executeTool`) e recursa — o comentário só diz que o driver não repassa os *eventos* pra UI. Apresentei leitura como achado (lápide 2026-07-15); o trabalho era menor do que eu disse.

## O incidente gpt-4o (R1 fez o trabalho dele)

Liguei `JANA_CHAT_MODEL=gpt-4o` em prod. O smoke inicial deu `GPT4O=FUNCIONA resposta=` (**vazio**) e eu segui em frente — **erro meu: o vazio era o sinal.** O R1 completo (testando `responderChat`, o caminho real, + os tipos de evento do stream) pegou:

```
Project proj_... does not have access to model gpt-4o (type: model_not_found)
```

O chat retornava "Estou sem conexão com IA no momento" e o stream emitia `Error: 1, text_len=0`. Kill-switch em minutos (apagar env + `config:cache`); chat voltou ao mini, provado com conversa real biz=1 (8 TextDeltas). O próprio `config.php` **já registrava** "gpt-4o → 403 does not have access" — eu deveria ter lido no pré-flight.

## Apuração dos caminhos do modelo frontier (todos bloqueados por credencial/decisão [W])

| Caminho | Estado | Bloqueio |
|---|---|---|
| gpt-4o no chat | mecanismo pronto | sem acesso no projeto OpenAI (as 2 chaves prod+staging são do MESMO projeto) |
| claude-sonnet no chat | seria o melhor (cache Anthropic já implementado) | prod **não tem** `ANTHROPIC_API_KEY` |
| US-137 `judge=openai` | mecanismo + 6 testes prontos | decisão LGPD [W] |
| US-137 `judge=local` | — | CT 100 é **CPU-only** (nvidia-smi vazio) + Ollama só tem embedders |
| US-135 fallback | — | precisa de 2º provider = mesma chave Anthropic |

Investiguei o `scoreFaithfulness($context,$answer,$context)` do `JudgeTraceOnlineJob` (suspeita de tautologia à la drift-sentinel): **não é** — `answer` é a resposta real (não gt), o comentário justifica `question=context`, mede alucinação legítima.

## Alavanca única

Uma **`ANTHROPIC_API_KEY` em prod** destrava três coisas: modelo frontier no chat (`claude-sonnet`, superior ao gpt-4o), fallback (US-135 ganha destino) e um 2º juiz pro eval. É mais robusto que insistir no gpt-4o. Não devo manusear a chave (credencial paga [W]).

## Custo/qualidade medido (staging, gpt-4o funcionou lá com a chave de staging antes de saber que é o mesmo projeto sem acesso — a medição vale como referência)

Mesma pergunta analítica, tools ON: mini ~$0,0003/msg vs gpt-4o ~$0,0047/msg (~15×). Ganho de qualidade **marginal** na amostra; custo absoluto trivial. Confirmar na média = US-137.

## PRs

- [#4421](https://github.com/wagnerra23/oimpresso.com/pull/4421) — US-141 capacidade (tools atrás de flag OFF)
- [#4443](https://github.com/wagnerra23/oimpresso.com/pull/4443) — US-142 flip das tools (LIVE)
- [#4459](https://github.com/wagnerra23/oimpresso.com/pull/4459) — US-144 mecanismo do modelo cirúrgico

## Estado de prod ao fechar

- `JANA_CHAT_TOOLS_ENABLED=true` — tools LIVE ✓
- `JANA_CHAT_MODEL` ausente — chat no mini (revertido) ✓
- US-136 (piso de recall) done em paralelo por outra sessão.
