# Stack e estrutura

## Stack canônica REAL

- **Laravel 13.6 + PHP 8.4** (Herd local; Hostinger prod)
- **MySQL** Laragon dev / Hostinger prod (DB `oimpresso`)
- **Inertia v3 + React 19 + Tailwind 4**
- **Pest v4** (testes)
- **`spatie/laravel-html` ^3.13** com shim `App\View\Helpers\Form` (preserva ~6.4k chamadas Blade)
- **nWidart/laravel-modules ^10**

## Runtime separado (Tier 0 IRREVOGÁVEL — ADR 0062)

- **Hostinger** (shared hosting): app web + Pest local + git pull
- **CT 100 Proxmox**: FrankenPHP + Centrifugo + Meilisearch + MCP server + Ollama embedder + Vaultwarden
- ⛔ **NUNCA** instalar `laravel/octane`/`laravel/mcp` no Hostinger (CLAUDE.md §4)

## IA canônica ([ADR 0035](decisions/0035-stack-ai-canonica-wagner-2026-04-26.md))

- **Camada A** (LLM wrapper): `laravel/ai` ^0.6.3 oficial fev/2026
- **Camada B** (agents): `LaravelAiSdkDriver` + 4 Agents próprios em `Modules/Jana/Ai/Agents/` — **Vizra ADK REJEITADA** ([ADR 0048](decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md))
- **Camada C** (memória): `MemoriaContrato` + `MeilisearchDriver` default + `NullDriver` dev

## MCP server canônico ([ADR 0053](decisions/0053-mcp-server-governanca-como-produto.md))

- `mcp.oimpresso.com` (CT 100/FrankenPHP)
- 352+ docs sincronizados de `memory/*` via webhook GitHub
- Tabela `mcp_memory_documents` com índice FULLTEXT + Meilisearch hybrid embedder
- Token gerenciado em `/copiloto/admin/team`

## Padrão arquitetural

**Modular monolith, DDD leve**, append-only onde a lei exige (Portaria MTP 671/2021), `business_id` global scope obrigatório (Tier 0 — [ADR 0093](decisions/0093-multi-tenant-isolation-tier-0.md)).

## Módulos referência canônica (imitar antes de criar)

`Modules/Jana/`, `Modules/Repair/`, `Modules/Project/` — antes de criar/ajustar qualquer arquivo, abrir o equivalente e imitar ([ADR 0011](decisions/0011-alinhamento-padrao-jana.md)).

## Criar módulo novo

Ler `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` — checklist das 8 peças obrigatórias + 3 rotas Install + padrão `Route::has()` pra link público condicional + pegadinhas. Validado em Modules/ADS (2026-05-03) e Modules/ConsultaOs (2026-05-04).

## ADRs centrais

- [0035](decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) Stack-alvo IA · [0040](decisions/0040-policy-publicacao-claude-supervisiona.md) Policy publicação · [0048](decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) Vizra rejeitada
- [0053](decisions/0053-mcp-server-governanca-como-produto.md) MCP server · [0058](decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) Centrifugo > Reverb
- [0062](decisions/0062-separacao-runtime-hostinger-ct100.md) Hostinger ≠ CT 100 · [0070](decisions/0070-jira-style-task-management-current-md-removed.md) Jira-style tasks
- [0091](decisions/0091-daily-brief.md) Daily Brief · [0093](decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [0094](decisions/0094-constituicao-v2-7-camadas-8-principios.md) **Constituição v2 (mãe)** · [0095](decisions/0095-skills-tiers-convencao-interna.md) Skills Tiers
