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
- Docs de `memory/*` sincronizados via webhook GitHub (contagem viva na UI `/copiloto/admin/memoria` — na casa dos milhares em 2026-07; não fixar número aqui, ele stale)
- Tabela `mcp_memory_documents` com índice FULLTEXT + Meilisearch hybrid embedder
- Token gerenciado em `/copiloto/admin/team`

## FSM Pipeline Canônico ([ADR 0143](decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — LIVE prod biz=1 desde 2026-05-12)

Toda mudança de estado em **Sells** (vendas) e **Repair** (OS) passa pelo `ExecuteStageActionService` ([app/Domain/Fsm/](../app/Domain/Fsm/)):

- **5 tabelas FSM** canônicas: `sale_processes`, `sale_process_stages`, `sale_stage_actions`, `sale_stage_action_roles`, `sale_stage_history` (audit append-only)
- **Trait `GuardsFsmTransitions`** em Transaction + JobSheet bloqueia UPDATE direto em `current_stage_id` — gateway obrigatório
- **Singleton `FsmAuthorizationFlag`** autoriza save (per-request, consume-once)
- **Pipeline Sells**: 11 stages (`quote_draft` → ... → `completed` + `cancelled`/`on_hold`) × 21 actions × 10 roles per-business
- **Pipeline Repair**: 13 stages (`recebido_para_diagnostico` → ... → `entregue_completo` + terminais) × ~15 actions × 6 roles per-business
- **Side-effects** isolados: `ReservarEstoque`, `ConsumirEstoque`, `LiberarReserva`, `CancelarVendaCascade` (orquestra cancel NFe SEFAZ + Asaas/Inter refund/cancel + Whatsapp/email)
- **UI drawer SaleSheet** ([resources/js/Pages/Sells/_components/FsmActionPanel.tsx](../resources/js/Pages/Sells/_components/FsmActionPanel.tsx)): botões dinâmicos por stage + RBAC + timeline auditável
- **Comandos artisan**: `fsm:bulk-start-pipeline {biz} [--dry-run]` (migrar vendas legadas), `fsm:scan-drift transactions` (cron daily 03:00 BRT, alerta mass-update bypass)
- **Coexistência opt-in** com state machine legacy — `current_stage_id` nullable permite migração gradual

Aplicação em vendas legadas: 162 vendas biz=1 prontas pra migrar via `php artisan fsm:bulk-start-pipeline 1`.

## Padrão arquitetural

**Modular monolith, DDD leve, especializado por vertical** ([ADR 0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md)). Append-only onde a lei exige (Portaria MTP 671/2021), `business_id` global scope obrigatório (Tier 0 — [ADR 0093](decisions/0093-multi-tenant-isolation-tier-0.md)).

```
oimpresso (núcleo comum)
├── Modules/Jana          (Jana IA + memória persistente)
├── Modules/Financeiro    (visão unificada AR/AP)
├── Modules/NfeBrasil     (NFe/NFC-e/NFSe)
├── Modules/RecurringBilling (assinaturas + boletos)
├── Modules/PaymentGateway   (🟡 parcial — código real (drivers Inter/C6/Asaas/PixAuto/CNAB + webhooks + 47 testes) porém flags OFF em prod; boleto Inter disponível — ADR 0170, extração de RecurringBilling)
├── Modules/SRS           (Software Requirements System, ex-MemCofre — uso interno raro)
├── Modules/Repair        (Kanban OS — shared infrastructure entre verticais)
└── Modules/<Vertical>    ← ESPECIALIZAÇÕES PROFUNDAS
    ├── Vestuario              ✅ em prod (ROTA LIVRE) — CNAE 4781-4/00
    ├── ComunicacaoVisual      🟡 em construção — CNAE 1813-0/01
    ├── OficinaAuto            🟡 piloto LIVE (Martinho biz=164) — CNAE 4520-0/01
    └── ...                    🔒 backlog ADR feature-wish
```

Cada módulo vertical = produto separado vendável como add-on ao núcleo.

## Módulos referência canônica (imitar antes de criar)

`Modules/Jana/`, `Modules/Repair/`, `Modules/ProjectMgmt/` — antes de criar/ajustar qualquer arquivo, abrir o equivalente e imitar ([ADR 0011](decisions/0011-alinhamento-padrao-jana.md)).

## Criar módulo novo

Ler `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` — checklist das 8 peças obrigatórias + 3 rotas Install + padrão `Route::has()` pra link público condicional + pegadinhas. Validado em Modules/ADS (2026-05-03) e Modules/ConsultaOs (2026-05-04).

## ADRs centrais

- [0035](decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) Stack-alvo IA · [0040](decisions/0040-policy-publicacao-claude-supervisiona.md) Policy publicação · [0048](decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) Vizra rejeitada
- [0053](decisions/0053-mcp-server-governanca-como-produto.md) MCP server · [0058](decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) Centrifugo > Reverb
- [0062](decisions/0062-separacao-runtime-hostinger-ct100.md) Hostinger ≠ CT 100 · [0070](decisions/0070-jira-style-task-management-current-md-removed.md) Jira-style tasks
- [0091](decisions/0091-daily-brief.md) Daily Brief · [0093](decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [0094](decisions/0094-constituicao-v2-7-camadas-8-principios.md) **Constituição v2 (mãe)** · [0095](decisions/0095-skills-tiers-convencao-interna.md) Skills Tiers
- [0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md) **Modular especializado por vertical** (núcleo + Modules/<Vertical>)
- [0129](decisions/0129-state-machine-canonica-fsm-rbac.md) FSM tabular custom (fundação) · [0143](decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) **FSM Pipeline LIVE prod biz=1** (marco 2026-05-12)
