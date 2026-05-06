---
slug: 0032-vizra-adk-prism-php-orquestracao
number: 32
title: !!binary gJQgVml6cmEgQURLICsgUHJpc20gUEhQIGNvbW8gY2FtYWRhIGRlIG9ycXVlc3RyYcOnw6NvIGUgd3JhcHBlciBMTE0gZG8gQ29waWxvdG8=
type: adr
status: superseded
authority: canonical
lifecycle: substituido
decided_by:
  - W
decided_at: '2026-04-26'
module: copiloto
quarter: 2026-Q2
tags: {  }
supersedes:
  - '2026-04-30'
  - '0048'
  - 0048-vizra-rejeitada-laravel-ai-consolidado
related:
  - '0026'
  - 0026-posicionamento-erp-grafico-com-ia
  - '0027'
  - 0027-gestao-memoria-roles-claros
  - '0031'
  - 0031-memoriacontrato-mem0-default
  - '0033'
  - 0033-vector-store-meilisearch-pgvector-mem0
pii: false
---
# ADR 0032 — Vizra ADK + Prism PHP como camada de orquestração e wrapper LLM do Copiloto

> ⛔ **SUPERSEDED em 2026-04-30 por [ADR 0048](0048-vizra-rejeitada-laravel-ai-consolidado.md).**
> Vizra ADK ficou em PHP `^11|^12`, nunca atualizou pra Laravel 13 (stack canônica
> oimpresso). `laravel/ai` ^0.6.3 + LaravelAiSdkDriver + 4 Agents próprios cobrem
> os casos de uso (Sprint 1 já em prod desde `3d64e5bb`). Tasks **COP-015 cancelada**.
> Nada de Vizra deve ser puxado a partir desta data — ler ADR 0048 antes de propor
> qualquer dependência relacionada.

**Status:** ⛔ Superseded by ADR 0048 (2026-04-30) — historicamente Aceita 26-abr.
**Data decisão:** 2026-04-26
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Relacionado:**
- [ADR 0026 — Posicionamento "ERP gráfico com IA"](0026-posicionamento-erp-grafico-com-ia.md)
- [ADR 0027 — Gestão de memória do projeto](0027-gestao-memoria-roles-claros.md)
- [ADR 0031 — `MemoriaContrato` + Mem0 default](0031-memoriacontrato-mem0-default.md)
- [ADR 0033 — Vector store backend](0033-vector-store-meilisearch-pgvector-mem0.md)
- [ADR 0034 — Laravel AI SDK oficial + Boost + MCP](0034-laravel-ai-sdk-oficial-boost-mcp.md) — **revisa sprint 1 deste ADR**
- Revoga parcialmente: nota "Vizra ADK + Prisma" do CLAUDE.md (typo "Prisma" → corrigido pra "Prism PHP")

> **⚠️ Atualização 2026-04-26 (ADR 0034):** Sprint 1 deste ADR foi revisado: trocar `prism-php/prism` por `laravel/ai` (Laravel AI SDK oficial, lançado fev/2026). Prism PHP fica como **fallback documentado** se SDK oficial perder feature crítica. Camadas B (Vizra ADK) e C (Mem0/Meilisearch) **permanecem inalteradas**. Detalhes em ADR 0034.

---

## Contexto

Em 2026-04-26 foi feito comparativo Capterra da stack completa pra agentes PHP/Laravel cobrindo 3 grupos: (A) wrapper de LLM, (B) framework de agente, (C) memória especializada. Achados centrais:

- **Estado do Copiloto hoje:**
  - **Camada A (wrapper LLM):** [OpenAiDirectDriver.php](../../Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php) usa `OpenAI\Laravel\Facades\OpenAI` mas o pacote **não está em `composer.lock`** (foi removido na limpeza de 2026-04-23). Copiloto **só roda em `dry_run`** (devolve fixtures).
  - **Camada B (framework de agente):** lógica caseira em `ChatController@send` chamando `responderChat()` direto. Sem registry de tools, sem agent loop think→act→observe, sem traces visuais, sem assertions/eval.
  - **Camada C (memória):** Tier 1 (ver ADR 0031).

- **PHP/Laravel ganhou em 2025-2026 dois frameworks AI fortes:**
  - **Prism PHP** ([prismphp.com](https://prismphp.com/), [github](https://github.com/prism-php/prism)): wrapper LLM low-level, Anthropic+OpenAI+Ollama+Mistral+Bedrock built-in, fluent interface, tools, multi-modal, structured output, testing fakes. OSS MIT.
  - **Vizra ADK** ([vizra.ai](https://vizra.ai/), [github](https://github.com/vizra-ai/vizra-adk), [packagist `vizra/vizra-adk`](https://packagist.org/packages/vizra/vizra-adk)): framework de agente em cima do Prism, com Eloquent sessions/messages/traces, OpenAI-compatible API exposta, web dashboard, streaming, 20+ assertions builtin. OSS MIT. Vizra Cloud (managed) opcional.

- **Vizra ADK e Mem0 são complementares, não concorrentes** — Vizra orquestra, Mem0 lembra. ADR 0031 cobre memória; este ADR cobre orquestração e wrapper LLM.

- **Stack Python (LangGraph/Letta/etc) inviável** em Hostinger compartilhado — exige container Python ao lado do Laravel. Vizra ADK + Prism = `composer require ... && php artisan vendor:publish`, zero infra extra.

Comparativo completo: [memory/comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](../comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md) — score 11/11 no checklist do template.

## Decisão

**Adotamos Vizra ADK como camada B (framework de agente) e Prism PHP como camada A (wrapper LLM) do Copiloto.**

### Stack final do Copiloto

```
┌─────────────────────────────────────────┐
│  ChatController@index/send (Inertia)   │  ← rota /copiloto/* (não muda)
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Vizra Agent (CopilotoAgent)            │  ← grupo B
│  - sessions, messages, traces           │
│  - tools registry (snapshot, metas...)  │
│  - agent loop think→act→observe         │
│  - assertions builtin                   │
└──────┬──────────────────────────┬───────┘
       │                          │
       ▼                          ▼
┌──────────────────┐      ┌──────────────────────┐
│  Prism (LLM)     │      │  MemoriaContrato     │  ← grupo C (ADR 0031)
│  - OpenAI/Claude │      │  - Mem0RestDriver    │
│  - Gemini/Ollama │      │  - NullMemoriaDriver │
│  - tools, stream │      │  (drivers pluggable) │
└──────────────────┘      └──────────────────────┘
```

### Adoção em fases (sprints 1-3 do caminho A)

**Sprint 1 — Prism PHP swap (resolve GAP 1):**
- `composer require prism-php/prism`
- Reescrever `Modules\Jana\Services\Ai\OpenAiDirectDriver` como `Modules\Jana\Services\Ai\PrismDriver`
- Switch `OpenAI\Laravel\Facades\OpenAI::chat()->create()` por `Prism::text()->using(...)->withMessages(...)->generate()`
- Manter `AiAdapter` interface intacta — Prism fica atrás dela
- Testar com OpenAI primeiro; documentar swap pra Anthropic/Ollama
- Setar `OPENAI_API_KEY` (ou `ANTHROPIC_API_KEY`) no `.env` da produção
- Remover `'COPILOTO_AI_DRY_RUN=true'` do `.env` quando Prism estiver verde
- Pest: 6 testes — provider switching, error handling, structured output

**Sprint 2 — Vizra ADK migration (resolve GAP 2 parcial):**
- `composer require vizra/vizra-adk`
- `php artisan vendor:publish --tag=vizra-config && php artisan migrate` (cria `vizra_sessions`, `vizra_messages`, `vizra_traces`)
- Criar `Modules\Jana\Agents\CopilotoAgent` estendendo `Vizra\Agent`
- Migrar `ChatController@send` pra usar `CopilotoAgent::session($conversaId)->send($mensagem)` em vez de chamar `OpenAiDirectDriver::responderChat()` direto
- Mapear `copiloto_conversas` → `vizra_sessions` via Observer (ou migrar dados se for limpo)
- Manter `copiloto_metas`, `copiloto_meta_periodos`, etc — só `Conversa`/`Mensagem` são substituídos por Vizra equivalentes

**Sprint 3 — Tools + Multi-tenant scope:**
- Registrar tools no `CopilotoAgent`: `BuscarSnapshotBusiness`, `CriarMeta`, `ConsultarApuracao`, `ListarMetas`, `RegistrarFonte`
- Tools usam `session('user.business_id')` pra isolar por tenant
- Adicionar Vizra Tenant Scope global em `vizra_sessions` filtrando por `business_id`
- Pest: 12 testes (1 por tool + 4 multi-tenant edge cases)

**Sprints 4-5: Mem0 integration** — coberto pelo ADR 0031.

**Sprint 6 (opt): Vizra Cloud / dashboard.** Avaliar se `localhost:vizra-dashboard` é suficiente vs upgrade pra Vizra Cloud managed pra Wagner ver traces dos clientes em produção.

### Configuração

```php
// config/copiloto.php
'orquestracao' => [
    'driver' => env('COPILOTO_AGENT_DRIVER', 'vizra'),  // 'vizra' | 'native' (legado)
],
'llm' => [
    'driver' => env('COPILOTO_LLM_DRIVER', 'prism'),  // 'prism' | 'openai_direct' (legado, deprecated)
    'prism' => [
        'provider' => env('COPILOTO_PRISM_PROVIDER', 'openai'),  // openai | anthropic | gemini | ollama
        'model'    => env('COPILOTO_PRISM_MODEL', 'gpt-4o-mini'),
    ],
],
```

`COPILOTO_AI_DRY_RUN=true` continua funcionando como override (devolve fixtures), agora também pra Prism+Vizra.

## Justificativa

- **Composer install em 1 comando, sem container Python.** Hostinger compartilhada não roda Python self-hosted; LangGraph/Letta auto-host inviável.
- **Prism PHP resolve `OpenAi\Laravel\Facades\OpenAI` quebrado** com 2 sprints e dá multi-provider grátis (Anthropic/Gemini/Ollama testáveis sem reescrita).
- **Vizra ADK dá traces+dashboard+eval gratuitamente** — features que precisaríamos construir do zero (estimado 6-8 sprints) ou abrir mão. Aaron Lumsden é mantenedor ativo na comunidade Laravel.
- **Eloquent models nativos do Vizra** (`vizra_sessions`, `vizra_messages`, `vizra_traces`) integram com `business_id` via Tenant Scope sem wrapper custom.
- **Camadas separadas (A/B/C)** — Prism faz LLM, Vizra orquestra, Mem0 lembra. Cada uma trocável por interface (consistente com ADR 0027 — papéis claros).

## Consequências

✅ Copiloto sai do `dry_run` em ~2 sprints (sprint 1 = Prism swap).
✅ ChatController vira mais magro — Vizra absorve agent loop e session.
✅ Dashboard de traces pra debug visual.
✅ 20+ assertions Vizra builtin pra eval automatizada.
✅ Multi-provider LLM grátis — possível trocar OpenAI pra Anthropic/Gemini sem reescrever.
✅ Stack PHP-only — deploy continua simples.
⚠️ 2 dependências OSS novas (`prism-php/prism`, `vizra/vizra-adk`) — sujeito a abandono. Mitigação: ambas via interface (`AiAdapter` + `OrquestradorContrato` se virar necessário).
⚠️ Migração `copiloto_conversas`/`copiloto_mensagens` → `vizra_sessions`/`vizra_messages` exige plano de migração de dados (sprint 2). Atenção a backups antes.
⚠️ Vizra ADK é projeto novo (2026) — bug surface não conhecido. Sprint 1 (Prism só) destrava produto sem expor a Vizra; faseamento dá tempo de avaliar.
⚠️ ADR 0031 (Mem0) tem dependência implícita: roda em sprints 4-5 **depois** de Vizra estar estável (sprint 3).

## Alternativas consideradas

- **Continuar com `OpenAi\Laravel\Facades\OpenAI` esperando upstream voltar:** rejeitado — projeto não atualizado há meses. Já causou GAP 1.
- **Migrar pra `anthropic-sdk-php` direto sem Prism:** rejeitado — single-provider, perde abstração, mesma armadilha do `openai-php/laravel`.
- **Vercel AI SDK (Node):** rejeitado — exige container Node ao lado, repete problema do Python.
- **Construir agente loop + tools registry caseiro em PHP:** rejeitado — 6-8 sprints só pra B, sem dashboard, sem eval. Vizra entrega tudo isso.
- **LangGraph self-hosted via Python container:** rejeitado — Hostinger compartilhada não suporta. Deal-breaker.
- **Letta self-hosted:** mesmo problema.
- **Apenas Prism (sem Vizra):** caminho B do comparativo — minimum viable, considerado plano fallback se Vizra ADK abandonar manutenção em 12m.

## Refs externas

- [Vizra ADK GitHub](https://github.com/vizra-ai/vizra-adk)
- [Vizra ADK Architecture docs](https://docs.vizra.ai/concepts/architecture)
- [Laravel News: Vizra ADK feature](https://laravel-news.com/vizra-adk)
- [Aaron Lumsden DEV.to: Why I Built Vizra ADK](https://dev.to/aaronlumsden/why-i-built-an-ai-agent-framework-for-laravel-and-why-php-deserves-ai-too-3il3)
- [Prism PHP homepage](https://prismphp.com/)
- [Prism PHP GitHub](https://github.com/prism-php/prism)
- [Laravel News: Prism is an AI Package for Laravel](https://laravel-news.com/prism-ai-laravel)

## Roadmap concreto

**Sprints 1-3 (caminho A do comparativo):**

| Sprint | Entregável | Risco |
|---|---|---|
| 1 | Prism PHP swap; `PrismDriver` substitui `OpenAiDirectDriver`; `OPENAI_API_KEY` em prod; `COPILOTO_AI_DRY_RUN=false` | Baixo |
| 2 | Vizra ADK install; `CopilotoAgent` consome Prism; `ChatController@send` migrado; dados `copiloto_conversas` migrados pra `vizra_sessions` | Médio (migração de dados) |
| 3 | 5-10 tools registradas; multi-tenant scope; 12 testes Pest novos; remoção do `OpenAiDirectDriver` legado | Baixo |
| 4-5 | (ADR 0031) Mem0 REST integration | Médio (dependência externa) |
| 6 | (opt) Vizra Cloud + dashboard | Baixo |

US relacionadas (do Anexo A do comparativo Camada C): **US-COPI-MEM-001 a 008** (Tiers 2-4).
