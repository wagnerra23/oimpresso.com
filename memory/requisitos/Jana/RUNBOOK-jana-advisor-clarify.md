---
id: requisitos-jana-runbook-jana-advisor-clarify
title: "Jana Advisor (Modo Consultor) — Metade A: Clarify reativo"
module: Jana
owner: W
status: ativo
last_validated: "2026-06-02"
related_adrs:
  - 0245-jana-advisor-modo-consultor-clarify
preconditions:
  - "JANA_CLARIFY_ENABLED=true (default OFF; ligado em homolog via .env.staging)"
  - "JANA_CLARIFY_MODEL = modelo frontier (default gpt-4o)"
steps:
  - "Ligar a flag em homolog (.env.staging JANA_CLARIFY_ENABLED=true)"
  - "Disparar mensagens cinza e claras no chat da Jana"
  - "Medir clarify_event no log copiloto-ai (gray-hit, taxa de clarify, false-clarify)"
  - "Validar fail-open e anti-loop"
---

# RUNBOOK — Jana Advisor (Modo Consultor) · Metade A: Clarify reativo

> **Status:** proposta §10.4 · **Tier 0** (produto + custo) · peer-review (L-17), não ordem.
> **Default OFF** — `[W]` liga em homolog após validar. Não cunha nº de ADR (soberania `[W]`, ADR 0238).
> Origem: sessão `[CC]` 2026-06-02 — *"as melhores respostas vêm quando eu pergunto que pergunta eu deveria fazer."*

## O que é

Cascata **Decidir → Clarificar → Responder** no chat da Jana. Antes de responder, a Jana
decide se a mensagem é **ambígua de intenção** (várias leituras → **pergunta** a de maior ganho
de informação) ou só **falta dado** (resposta única → **busca**, não pergunta). Decoupla os dois
erros nº1 dos LLMs (INTENT-SIM, NAACL 2025 + Active Task Disambiguation, ICLR 2025 Spotlight).

**Não troca de modelo — é andaime** (scaffold). Sobe o raciocínio pela arquitetura, não pelo provider.

## Como funciona (cascata por latência)

| Estágio | Onde | Custo | Resolve |
|---|---|---|---|
| **1a heurística local** | `ClarifyCascadeService::pareceCinza()` | zero LLM (~µs) | ~80% direto → responde |
| **1b disambiguador** | `ClarificadorAgent` (frontier, structured output) | 1 chamada LLM | só o ~20% "cinza" |

- **1a** é conservadora: default = responder. Só vira "cinza" com sinal forte de ambiguidade
  (curta + dêixis sem antecedente / imperativo solto / pergunta vaga curta).
- **1b** classifica `claro | falta_dado | ambiguo` + (se ambíguo) a **pergunta de maior ganho**.
- **Honestidade:** ambíguo sem pergunta de alto valor, ou confiança < mínimo → **responde** (não inventa).
- **Anti-loop:** se o turno anterior já foi um clarify, não pergunta de novo (marcador TTL em cache).
- **Fail-open:** qualquer erro → responde normal. A cascata **nunca** quebra o chat.

## Onde plugou (estendeu, não recriou — §10.4 Passo 0)

- `LaravelAiSdkDriver::responderChat()` e `responderChatStream()` → guard `talvezClarificar()`
  ANTES do recall/LLM (espelha a interceptação do `BriefDiarioChatTrigger`). `ContextoNegocio`
  é **reusado** (snapshot único) pela cascata e pelo chat.
- Novos: `ClarificadorAgent` (5º agente, ao lado de ChatCopiloto/BriefDiario/Sugestoes/Briefing),
  `ClarifyCascadeService`, `ClarifyResult`. Recall (`MemoriaContrato`) e brief **intactos**.

## Como ligar (homolog) — ADR 0245

O flag/modelo/provider são **env-driven** (controle por ambiente: homolog liga, prod espera).
Em homolog já vem ligado via `docker/oimpresso-staging/.env.staging.example`:

```env
JANA_CLARIFY_ENABLED=true
JANA_CLARIFY_MODEL=gpt-4o          # frontier seletivo (vs gpt-4o-mini do chat); só dispara no cinza
# JANA_CLARIFY_PROVIDER=anthropic  # opcional; vazio = config('ai.default') (openai, provider do chat)
```

As constantes de tuning (`min_confianca`, `gray_max_words/chars`, `historico_turnos`,
`anti_loop_ttl_segundos`) ficam diretas no bloco `clarify` de `Modules/Jana/Config/config.php`
(merged como `copiloto.clarify.*`) — ajuste lá se precisar.

Com `JANA_CLARIFY_ENABLED=false` (default em prod) o pipeline de chat é **byte-idêntico** ao
legado (mesma postura de `contextual_retrieval` / `peso_real`).

## Como medir (senão é fé, não engenharia)

Log channel `copiloto-ai` → evento `clarify_event`:

```bash
tail -f storage/logs/copiloto-ai.log | grep clarify_event
```

Métricas a acompanhar:
- **gray-hit rate** = eventos `custo_llm=true` / total (quanto a heurística 1a deixou passar).
- **taxa de clarify** = `acao=clarificar` / total.
- **false-clarify** = clarify que o user ignorou (cruzar com a próxima ação — hook de produto,
  pendência abaixo).

## Pendências / fronteira (honestidade de escopo)

- [ ] **pergunta→ação** (sinal de valor real) precisa de instrumentação no frontend
      (registrar se a próxima msg do user responde a pergunta). Backend já loga a decisão.
- [ ] Golden/RAGAS de clarify (casos cinza canônicos) — ratchet na família `jana:health-check`.
- [ ] **Metade B** (próxima-melhor-pergunta proativa, estende o brief diário por persona) —
      próxima na fila, spec à parte.

## Testes

- `Modules/Jana/Tests/Feature/Ai/Clarify/ClarifyCascadeServiceTest.php` (9 guards: heurística,
  flag-off no-op, curto-circuito, clarifica, honestidade ×2, anti false-clarify, anti-loop, fail-open).
- `Modules/Jana/Tests/Feature/Ai/Clarify/ClarificadorAgentTest.php` (5 guards: routing, instructions,
  grounding, messages).
