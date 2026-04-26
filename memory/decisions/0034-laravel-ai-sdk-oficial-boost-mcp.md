# ADR 0034 — Laravel AI ecosystem 2026: SDK oficial + Boost + MCP + Vizra ADK + alternativas

**Status:** ✅ Aceita (revisa parcialmente ADR 0032)
**Data decisão:** 2026-04-26
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`, pesquisa profunda solicitada — "pesquise profundamente Vizra, Laravel IA")
**Relacionado:**
- [ADR 0026 — Posicionamento "ERP gráfico com IA"](0026-posicionamento-erp-grafico-com-ia.md)
- [ADR 0031 — `MemoriaContrato` + Mem0RestDriver default](0031-memoriacontrato-mem0-default.md)
- [ADR 0032 — Vizra ADK + Prism PHP](0032-vizra-adk-prism-php-orquestracao.md) — **revisado parcialmente** por este ADR
- [ADR 0033 — Vector store backend](0033-vector-store-meilisearch-pgvector-mem0.md)

---

## Contexto

ADR 0032 (criado mais cedo na sessão 16, 2026-04-26) decidiu por **Prism PHP** como camada A (wrapper LLM) e **Vizra ADK** como camada B (framework de agente). Pesquisa profunda subsequente revelou ecosystem AI Laravel **muito maior do que assumimos** — em particular o **Laravel AI SDK oficial lançado em fevereiro 2026**:

### Players Laravel AI mapeados (cronológico)

| Player | Quando surgiu | Camada | Status 2026-04 |
|---|---|---|---|
| **`openai-php/laravel`** | 2023 | A (single-provider) | Abandonado/sem update há meses — causa do nosso GAP 1 |
| **Prism PHP** (`prism-php/prism`) | 2024 | A (multi-provider wrapper) | **Mantido**, 5 providers (OpenAI/Anthropic/Ollama/Mistral/Bedrock), tools, structured output, multi-modal |
| **LarAgent** | 2024 | B (agente lightweight) | Mantido, prompts+history+tools+workflows |
| **Neuron AI** (`neuron-core/neuron-ai`) | 2025 | B (agente reasoning) | Mantido, agent-based reasoning |
| **Vizra ADK** (`vizra/vizra-adk`) | 2026 Q1 (Aaron Lumsden) | B (agente production-ready) | **Mantido ativamente**. Multi-agent workflows, sub-agents, eval LLM-as-Judge, auto-tracing, 20+ assertions, Vizra Cloud managed |
| **🆕 Laravel AI SDK** (`laravel/ai`) | **fev/2026** (Laravel team, **first-party**) | A + parte de B | **Lançado oficialmente**. Texto + embeddings + audio + images + tools + agents + structured + vector stores integration. Switch automático entre providers. |
| **🆕 Laravel Boost** | 2026 (Laravel team) | Tooling de dev | MCP tools + 17.000 pieces of Laravel knowledge + AI guidelines pra agentes (Cursor/Claude/etc) escreverem código idiomático Laravel |
| **🆕 Laravel MCP** | 2026 (Laravel team) | Bridge agente ↔ app | Server MCP, tools, resources — expor a app pra agentes IA externos |

Refs: [laravel/ai GitHub](https://github.com/laravel/ai), [Laravel AI SDK docs](https://laravel.com/docs/13.x/ai-sdk), [Introducing Laravel AI SDK (Laravel blog)](https://laravel.com/blog/introducing-the-laravel-ai-sdk), [Laravel News announcement](https://laravel-news.com/laravel-announces-official-ai-sdk-for-building-ai-powered-apps), [Laravel Boost](https://laravel.com/ai/boost), [Laravel MCP](https://laravel.com/ai/mcp)

### Vizra ADK — pesquisa profunda

[Vizra ADK Architecture docs](https://docs.vizra.ai/concepts/architecture) e [Tracing docs](https://vizra.ai/docs/adk/concepts/tracing) confirmaram:

- **Multi-agent workflows com sequential/parallel/conditional/loops** — features que LarAgent e Neuron AI não têm completas
- **Sub-agent delegation** — agente especialista chamado por agente coordenador
- **Eval framework com LLM-as-a-Judge** — testa agentes em CI/CD com modelo julgando qualidade da resposta
- **Auto-tracing** — sem instrumentação manual, ADK cria spans automaticamente
- **20+ assertions builtin** — content validation, sentiment, length checks
- **Vizra Cloud (managed)** — interactive trace visualization, performance analytics, regression detection, CI/CD integration. Pricing não público (precisa cadastro).
- **OpenAI-compatible API exposta** — agente Vizra pode ser consumido por qualquer cliente OpenAI

[Aaron Lumsden DEV.to post](https://dev.to/aaronlumsden/why-i-built-an-ai-agent-framework-for-laravel-and-why-php-deserves-ai-too-3il3) explicita posicionamento: "PHP deserves AI too" — production-ready, security-minded, lifecycle events, persistent memory. Comunidade Laravel-News destaca ([Laravel News article](https://laravel-news.com/vizra-adk)).

---

## Decisão

**Adotamos a stack estendida pra Copiloto:**

```
┌─────────────────────────────────────────┐
│  ChatController@index/send (Inertia)   │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Vizra Agent (CopilotoAgent)            │  ← grupo B (orquestração)
│  - sessions, messages, traces           │
│  - tools registry                        │
│  - eval LLM-as-Judge                    │
│  - 20+ assertions builtin               │
│  - sub-agent delegation                 │
└──────┬──────────────────────────┬───────┘
       │                          │
       ▼                          ▼
┌──────────────────┐      ┌──────────────────────┐
│ Laravel AI SDK   │      │  MemoriaContrato     │  ← grupo C (ADR 0031+0033)
│ (`laravel/ai`)   │      │  - Mem0RestDriver    │
│  - text/audio/   │      │  - MeilisearchDriver │
│    image/embed   │      │  - NullMemoriaDriver │
│  - 5+ providers  │      └──────────────────────┘
│  - tools/struct  │
└──────────────────┘
```

### Camada A — wrapper LLM: **Laravel AI SDK oficial (`laravel/ai`)** em vez de Prism PHP

**Revisa ADR 0032 sprint 1.** Decisão atualizada:

| ADR 0032 dizia | Atualizado em ADR 0034 |
|---|---|
| Sprint 1: `composer require prism-php/prism` | Sprint 1: `composer require laravel/ai` |
| `PrismDriver` em cima do Prism PHP | `LaravelAiDriver` em cima do `laravel/ai` |
| 5 providers (OpenAI/Anthropic/Ollama/Mistral/Bedrock) | 5+ providers (OpenAI/Anthropic/Gemini/+) com switch automático provider-down |
| Texto + tools + multi-modal + structured | Texto + tools + multi-modal + structured + **audio + images + embeddings + vector store integration** |

**Por que mudar:** Laravel AI SDK é first-party (manutenção garantida pelo Laravel team), cobre tudo que Prism cobria + audio/images/embeddings, e tem fallback automático entre providers. Prism vira **fallback explícito** documentado (ADR 0032 mantém-se válido como contingência se SDK oficial perder feature crítica em algum upgrade).

### Camada B — framework de agente: **Vizra ADK confirmado**

ADR 0032 mantém-se. Vizra ADK roda em cima de qualquer wrapper LLM (Prism OU Laravel AI SDK) — checar primeira sprint se Vizra ADK 2026-Q2 já suporta `laravel/ai` nativamente; se não, configurar driver custom no Vizra Agent que invoca `laravel/ai`.

### Camada C — memória: **Mem0 default + Meilisearch fallback** (ADR 0031 + 0033)

Sem mudança.

### Tooling de desenvolvimento (não-Copiloto, mas relevante)

**Laravel Boost** (`composer require laravel/boost --dev`):
- Adiciona MCP tools pra agentes (Cursor, Claude Code, ChatGPT, etc) **inspecionarem nossa app durante dev**
- 17.000 pieces of Laravel knowledge embutidos
- AI guidelines pra agente escrever Laravel idiomático (não inventar route::resource errado, etc)
- **Ação:** instalar imediatamente em ambiente de dev. Wagner usa Cursor + Claude Code paralelos — Boost fecha gap entre agentes em padrões Laravel.

**Laravel MCP** (`composer require laravel/mcp`):
- Servidor MCP pra **expor a oimpresso pra agentes externos** (Claude Desktop, Cursor)
- Permite Wagner conversar via Claude Desktop e perguntar "cria meta de R$80k pra ROTA LIVRE" → Claude chama MCP server do oimpresso → roda no app real
- **Ação:** avaliar em sprint futuro do Copiloto Tier 5+ (US-COPI-MEM-014 procedural memory pode usar MCP)

### Não usar

- ❌ **`openai-php/laravel`** — abandonado, causa do GAP 1 atual. **Remover qualquer remanescente** quando sprint 1 (Laravel AI SDK swap) for executada.
- ❌ **LarAgent** — overlap com Vizra ADK; sem feature que justifique escolha.
- ❌ **Neuron AI** — overlap com Vizra ADK; comunidade menor.

---

## Justificativa

- **Laravel AI SDK é first-party.** Prism PHP é OSS de qualidade mas single-maintainer; Laravel AI SDK tem time da Laravel garantindo manutenção pro próximo decade. Reduz risco de abandono.
- **Cobre mais features** do que Prism (audio/images/embeddings/vector stores nativos).
- **Switch automático provider-down** é safety net pra produção que Prism não tem nativo.
- **Vizra ADK continua sendo a melhor camada B PHP** — eval framework + auto-tracing + multi-agent + 20+ assertions + Vizra Cloud são features que Laravel AI SDK não tenta cobrir (escopo diferente).
- **Laravel Boost fecha gap dev** — Cursor + Claude Code passam a escrever Laravel idiomático sem Wagner re-explicar convenções a cada sessão.
- **Laravel MCP abre porta pra agentes externos** consumirem a oimpresso — eventual diferencial comercial ("controle seu ERP pelo Claude Desktop").

## Consequências

✅ Stack alinhada com first-party Laravel — manutenção garantida.
✅ Sprint 1 (LLM swap) tem caminho claro: `composer require laravel/ai` substitui `openai-php/laravel`.
✅ Laravel Boost reduz overhead de Cursor + Claude Code — escreve Laravel idiomático by default.
✅ Laravel MCP abre futuro: agentes externos consumirem oimpresso é estratégico.
✅ Vizra ADK preservado como camada B (não competimos com SDK oficial — ele é wrapper, Vizra é orquestrador).
⚠️ ADR 0032 sprint 1 fica obsoleto (Prism → `laravel/ai`). Markdown do 0032 recebe nota "revisado por ADR 0034".
⚠️ Verificar em sprint 1 se Vizra ADK 2026-Q2 suporta `laravel/ai` nativamente como provider; se não, custom driver intermediário.
⚠️ Laravel AI SDK é novo (fev/2026, ~2 meses no momento desta decisão) — bug surface ainda emergindo. Mitigação: testar em sprint 1 com smoke tests + manter Prism como fallback documentado.
⚠️ Laravel Boost adiciona 17.000 pieces de knowledge no agent context — verificar se isso aumenta custo de tokens em sessões Cursor/Claude. Provavelmente não pra Claude Code (lazy load), mas medir.

## Alternativas consideradas

- **Manter Prism PHP (ADR 0032 original):** rejeitado — Laravel AI SDK é superior em manutenção, features e safety. Prism vira fallback documentado, não default.
- **Usar Vercel AI SDK (Node) em sub-processo:** rejeitado — exige container Node, repete problema do Python.
- **Construir wrapper LLM caseiro em PHP:** rejeitado — nem com `laravel/ai` disponível first-party isso faz sentido.
- **LarAgent ou Neuron AI no lugar de Vizra ADK:** rejeitado — overlap funcional sem ganho. Vizra tem eval LLM-as-Judge + auto-tracing + multi-agent que os outros não têm completos.
- **Não usar Vizra ADK e construir agente direto sobre `laravel/ai`:** considerado. Laravel AI SDK tem "agents com tools, memory, structured outputs, full testing capabilities built-in" segundo a doc oficial. **Conclusão:** revisar em sprint 2 — se Laravel AI SDK agents cobrirem 80%+ do que precisamos do Vizra ADK, pular Vizra. Por ora, manter Vizra ADK pelo eval framework + multi-agent workflows + Vizra Cloud (que SDK oficial não tem).

## Refs externas

### Laravel AI ecosystem oficial
- [laravel/ai GitHub](https://github.com/laravel/ai)
- [laravel/ai Packagist](https://packagist.org/packages/laravel/ai)
- [Laravel AI SDK landing page](https://laravel.com/ai)
- [Laravel AI SDK docs](https://laravel.com/docs/13.x/ai-sdk)
- [Laravel AI Assisted Development](https://laravel.com/docs/13.x/ai)
- [Introducing the Laravel AI SDK (Laravel blog)](https://laravel.com/blog/introducing-the-laravel-ai-sdk)
- [Laravel News: Official AI SDK announcement](https://laravel-news.com/laravel-announces-official-ai-sdk-for-building-ai-powered-apps)
- [Laravel Boost](https://laravel.com/ai/boost)
- [Laravel MCP](https://laravel.com/ai/mcp)
- [RCV Tech: Everything to Know About Laravel AI SDK](https://www.rcvtechnologies.com/blog/everything-to-know-about-the-laravel-ai-sdk-package-launch-in-february-2026/)
- [Sadique Ali: Laravel's Official AI SDK Just Landed (Medium)](https://sadiqueali.medium.com/laravels-official-ai-sdk-just-landed-3801e509b899)

### Vizra ADK
- [Vizra ADK GitHub](https://github.com/vizra-ai/vizra-adk)
- [Vizra ADK Architecture docs](https://docs.vizra.ai/concepts/architecture)
- [Vizra ADK Tracing docs](https://vizra.ai/docs/adk/concepts/tracing)
- [Vizra Cloud landing](https://vizra.ai/cloud)
- [Laravel News: Vizra ADK feature](https://laravel-news.com/vizra-adk)
- [Aaron Lumsden DEV.to: Why I Built Vizra ADK](https://dev.to/aaronlumsden/why-i-built-an-ai-agent-framework-for-laravel-and-why-php-deserves-ai-too-3il3)
- [Vizra ADK Packagist](https://packagist.org/packages/vizra/vizra-adk)

### Outros referenciados
- [Prism PHP](https://prismphp.com/) — fallback documentado
- [LarAgent vs Prism comparison (YouTube)](https://www.youtube.com/watch?v=cuPi5J4U1Lw)
- [Neuron AI introduction (Medium)](https://medium.com/@valerio_27709/introducing-neuron-ai-create-full-featured-ai-agents-in-php-40464e1ed009)
- [Laravel ecosystem 2026 overview](https://www.addwebsolution.com/blog/the-laravel-ecosystem-in-2026-tools-packages-workflows)
- [Laravel Trends 2026](https://laracopilot.com/blog/laravel-trends-2026/)

## Roadmap concreto (atualiza ADR 0032)

| Sprint | Entregável | Mudança vs ADR 0032 |
|---|---|---|
| 1 | `composer require laravel/ai` (era `prism-php/prism`); `LaravelAiDriver` substitui `OpenAiDirectDriver`; `OPENAI_API_KEY`/`ANTHROPIC_API_KEY` em prod; `COPILOTO_AI_DRY_RUN=false` | **Mudou**: SDK oficial em vez de Prism |
| 1.5 (paralelo) | `composer require laravel/boost --dev` no main worktree; configurar AI guidelines pra Cursor + Claude Code | Adicional |
| 2 | Vizra ADK install; `CopilotoAgent` consome `laravel/ai`; `ChatController@send` migrado | Sem mudança vs 0032 (só driver muda) |
| 3 | 5-10 tools registradas; multi-tenant scope; testes Pest | Sem mudança |
| 4-5 | `MemoriaContrato` + `Mem0RestDriver` (ADR 0031) | Sem mudança |
| 6 (opt) | Avaliar Vizra Cloud OU construir dashboard caseiro | Sem mudança |
| 7 | Stress test + LGPD opt-out | Sem mudança |
| 8-10 (condicional) | `MeilisearchDriver` da MemoriaContrato (ADR 0033) se Mem0 ficar caro | Adicional |
| Futuro | `composer require laravel/mcp` + expor agente Copiloto via MCP server pra Claude Desktop | Adicional, US-COPI-MEM-014 |

## Atualização ao ADR 0032

ADR 0032 deve receber nota no header:
> *"⚠️ Sprint 1 deste ADR foi revisado em ADR 0034: trocar `prism-php/prism` por `laravel/ai` (Laravel AI SDK oficial, fev/2026). Prism PHP fica como fallback documentado se SDK oficial perder feature crítica. Camadas B (Vizra ADK) e C (Mem0/Meilisearch) permanecem inalteradas."*
