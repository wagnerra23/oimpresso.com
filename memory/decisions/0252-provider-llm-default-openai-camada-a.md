---
slug: 0252-provider-llm-default-openai-camada-a
number: 252
title: Provider LLM default da camada A = OpenAI (gpt-4o-mini / gpt-4o)
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-06-05'
module: copiloto
related:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0034-laravel-ai-sdk-oficial-boost-mcp
supersedes: []
pii: false
---
# ADR 0252 — Provider LLM default da camada A = OpenAI

**Status:** ✅ Aceita — VERDADE CANÔNICA do provider LLM
**Data decisão:** 2026-06-05
**Autor:** Wagner (dono/operador) — *"pode passar pra a api da openia, foi passado para claude mas não tem credito"* + confirmação "migrar pra OpenAI · definitivo".
**Relacionado:**
- [ADR 0035 — Stack-alvo de IA canônica](0035-stack-ai-canonica-wagner-2026-04-26.md) — **não supersedida**: a arquitetura (camadas A/B/C) continua igual.
- [ADR 0034 — Laravel AI SDK oficial](0034-laravel-ai-sdk-oficial-boost-mcp.md) — camada A é `laravel/ai`, agnóstica de provider.

---

## Contexto

A [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) declarou a **stack-alvo de IA** do oimpresso: camada A `laravel/ai` (wrapper LLM), camada B Vizra ADK (orquestração), camada C `MemoriaContrato` + Mem0/Meilisearch. Essa ADR fixa a **arquitetura** — não o **provider LLM** concreto que a camada A usa por baixo.

Na prática, o provider default sempre foi um detalhe de `config/ai.php` + atributos `#[Provider]` por agent. Ao longo do tempo o projeto acumulou um estado **misto e implícito**:

- `config('ai.default')` = `openai`, mas vários agents/comandos fixavam ou anunciavam Anthropic (`BrainBAgent`, `PrUiJudgeAgent`, `EvalAdrDiscoveryCommand`, docstrings "Claude Sonnet").
- A coluna `model_used` em `mcp_dual_brain_decisions` gravava `claude-sonnet-4-6` mesmo o agent rodando em OpenAI ("a mentira do Sonnet", catalogada no parecer do PR #2270).

Em **2026-06-05** a conta Anthropic ficou **sem crédito** e Wagner decidiu **migrar o provider LLM definitivamente pra OpenAI**. O `.env` já estava com `ANTHROPIC_API_KEY` vazia e `OPENAI_API_KEY` válida.

## Decisão (canônica)

**O provider LLM default da camada A (`laravel/ai`) é OpenAI.**

| Tier de uso | Modelo OpenAI | Onde |
|---|---|---|
| Default / barato (chat, brief, eval, resumos) | `gpt-4o-mini` | `config('ai.providers.openai.models.text.default')` |
| Smartest (juízo semântico — ex. PR UI Judge, planning) | `gpt-4o` | atributo `#[Model('gpt-4o')]` por agent |

Regras:
1. **Fonte da verdade do provider** = `config('ai.default')` (`openai`). Agents que não fixam `#[Provider]` herdam isso.
2. Agents que precisam de tier "smartest" declaram `#[Provider('openai')]` + `#[Model('gpt-4o')]` explicitamente.
3. **Nenhum código de runtime de cliente** deve depender de `ANTHROPIC_API_KEY`. Trechos Anthropic-específicos (ex. prompt caching) ficam **condicionais** (`if provider === Anthropic`) e viram no-op gracioso.
4. Trocar de modelo/tier é via `config/.env` + atributo do agent — **não** exige nova ADR. Trocar de **provider** (voltar pra Anthropic, adotar Gemini, etc.) **exige ADR** que supersede esta.

## Não é supersede da 0035

A [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) trata da **arquitetura de camadas** (qual SDK, qual framework de agente, qual memória) — tudo preservado. Inclusive a 0035 já rejeitava o pacote `openai-php/laravel` **antigo** (driver direto), não a OpenAI como provider sob `laravel/ai`. Esta ADR só **formaliza o provider concreto** da camada A, que antes vivia implícito em config/atributos. Por isso `supersedes: []`.

## Implementação (já executada)

- **PR #2303** — `BrainBService.model_used` resolve o provider ativo (gpt-4o-mini) em vez de `claude-sonnet-4-6` hardcoded.
- **PR #2305** — `eval:adr-discovery` ganha auto-detect OpenAI; `ui:judge-pr`/`PrUiJudgeAgent` migram pra `openai`/`gpt-4o`; catraca de teste + workflows atualizados.
- `eval:ragas-baseline` já preferia OpenAI — sem mudança.
- `config/ai.php` `default=openai` + `.env` (`ANTHROPIC_API_KEY` vazia, `OPENAI_API_KEY` setada) — já vigente.

## Consequências

✅ Provider LLM honesto e único — fim do estado misto "anuncia X, roda Y".
✅ App destravado sem crédito Anthropic — runtime de cliente 100% OpenAI.
✅ Observabilidade/custo (`mcp_dual_brain_decisions`, `jana_ui_judge_runs`, OTel GenAI) refletem o modelo real.
✅ Caminho de volta documentado: nova ADR com `supersedes: [0252-provider-llm-default-openai-camada-a]`.
⚠️ Perde-se o **prompt caching** da Anthropic Messages API (gpt-4o-mini é barato o suficiente pra compensar nos volumes atuais).
⚠️ Juízo semântico de tarefas sensíveis (PR UI Judge) passa de Sonnet pra `gpt-4o` — monitorar qualidade via `jana:ui-judge-trend`; se cair, reavaliar tier/provider via ADR.

## Alternativas consideradas

- **Manter Anthropic** — inviável agora (sem crédito) e o diferencial de qualidade não justifica o custo recorrente no estágio atual.
- **Fallback automático multi-provider** (OpenAI → Anthropic) — complexidade extra sem ganho enquanto só uma key está ativa; reavaliar se ambas voltarem.
- **Gemini / Groq / DeepSeek** — já configurados em `config/ai.php` como providers disponíveis, mas OpenAI é o de melhor relação maturidade/custo/qualidade pro caso de uso hoje.

## Sources

ADRs internos: [0034](0034-laravel-ai-sdk-oficial-boost-mcp.md), [0035](0035-stack-ai-canonica-wagner-2026-04-26.md).
PRs: [#2303](https://github.com/wagnerra23/oimpresso.com/pull/2303), [#2305](https://github.com/wagnerra23/oimpresso.com/pull/2305).
Config: `config/ai.php` (`default => 'openai'`), `config/openai.php`.

## Compromisso

Próximo agente que considerar trocar o **provider LLM** (não apenas o modelo/tier) **deve abrir ADR** que supersede esta, explicando: (1) por que trocar, (2) provider/modelo alvo, (3) plano de migração + impacto em custo/qualidade. Sem ADR de revisão, o provider canon é **OpenAI**.
