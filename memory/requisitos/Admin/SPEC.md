---
module: Admin
version: "1.0.0"
last_updated: "2026-06-08"
owner: wagner
na_justified:
  D5: "Admin Center é Wagner-only no CT 100 via Tailscale (gate `is_wagner` + role `superadmin#1` + CIDR `100.99.0.0/16` whitelist — ADR 0122 Princípio 1+2). Cliente externo biz=4 ROTA LIVRE NÃO tem acesso por design — internet pública zera vetor de ataque. D5 cliente real não aplica."
  D4.b: "Admin Center é painel read-mostly que AGREGA visão de outros módulos (brief, health-check, cycles, ADRs) — sem state machine FSM própria. Não orquestra fluxo de negócio Eloquent; ações mutacionais limitadas a `apply` Curador, regenerate token, run-now health-check (ADR 0122 Princípio 4 read-mostly). D4.b FSM N/A."
related_adrs: [0122, 0093, 0094, 0153, 0154]
---

# Admin Center — Centro de Operações @ CT 100

> **N/A justificado** D5 + D4.b — Wagner-only no CT 100 (Tailscale-only, sem cliente externo) e painel read-mostly que agrega outros módulos (sem FSM própria). Detalhes em [ADR 0122](../../decisions/0122-admin-center-ct100.md).

> Módulo Laravel: `Modules/Admin/` (a criar)
> ADR mãe: [0122](../../decisions/0122-admin-center-ct100.md)
> Subdomínio: `admin.oimpresso.com` (Tailscale-only)

## O que é

Painel único Wagner-only que agrega visão de toda a infra/governance/time da empresa. NÃO substitui Officeimpresso superadmin nem `/copiloto/admin/team` — agrega read-mostly.

## Princípios duros

1. **Wagner-only** — gate `is_wagner($user)` + role `superadmin#1` + bloqueio duro pra equipe
2. **CT 100 only** — `admin.oimpresso.com` Traefik com DNS A → Tailscale `100.99.207.66`; internet pública zera vetor de ataque
3. **Agrega, não substitui** — Officeimpresso superadmin (cliente-side) e `/copiloto/admin/team` (MCP tokens) continuam existindo
4. **Read-mostly** — ações mutacionais limitadas a `apply` Curador, regenerate token, run-now health-check
5. **Multi-tenant Tier 0 preservado** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — `withoutGlobalScopes` apenas com `// SUPERADMIN: <razão>` mandatório

## Stack

- Laravel 13.6 + PHP 8.4 (FrankenPHP no CT 100)
- Inertia v3 + React 19 + Tailwind 4 (mesmo padrão MWART)
- Sanctum + Spatie role + Tailscale CIDR `100.99.0.0/16` whitelist
- Horizon (queue), Centrifugo (real-time)
- MySQL via autossh tunnel CT 100 → Hostinger

## User stories

> Organizadas por sprint abaixo (Sprint 1 MVP · Sprint 2 Curador · Sprint 3 condicional) + Backlog de ferramentas internas.

## Sprint 1 — MVP CASCA + 4 widgets read-only (~3-5 dias IA-pair)

### US-ADM-001..010

| ID | Título | Prioridade | Tipo |
|---|---|---|---|
| US-ADM-001 | Scaffold `Modules/Admin/` (módulo nWidart) | p1 | infra |
| US-ADM-002 | Traefik `admin.oimpresso.com` no CT 100 + DNS A Tailscale 100.99.207.66 | p1 | infra |
| US-ADM-003 | Auth gate `is_wagner` + middleware `tailscale-only` + Spatie role `superadmin#1` | p0 | seg |
| US-ADM-004 | Page `Pages/Index.tsx` shell (header + sidebar W1-W4 + footer) | p1 | UI |
| US-ADM-005 | Widget W1 — Brief diário (preview render markdown via `brief-fetch`) | p1 | feat |
| US-ADM-006 | Widget W2 — Health checks 5 SQL (jana:health-check) com 🟢🟡🔴 | p1 | feat |
| US-ADM-007 | Widget W3 — Cycles + tasks (kanban read-only via `cycles-active`+`my-work`) | p2 | feat |
| US-ADM-008 | Widget W4 — ADRs Tier 0 violados (alerta vermelho top-bar) | p1 | feat |
| US-ADM-009 | Pest tests (auth gate, RBAC, Tailscale CIDR filter) | p1 | qa |
| US-ADM-010 | Smoke walkthrough (Wagner abre via Tailscale, valida 4 widgets) | p2 | qa |

## Sprint 2 — Curador integrado + extensões (~3-5 dias)

| ID | Título | Prioridade | Depends |
|---|---|---|---|
| US-ADM-011 | Migration `mcp_curador_{batches,files,audit_log,consent}` (com `business_id` Tier 0) | p1 | US-ADM-001 |
| US-ADM-012 | API `POST /admin/curador/api/upload-batch` recebe JSONL do script Node local | p1 | US-ADM-011 |
| US-ADM-013 | Page `Pages/Curador/Batches/{Index,Review}.tsx` (substitui `[x]` markdown) | p1 | US-ADM-012 |
| US-ADM-014 | Job `ApplyBatchJob` Horizon (move arquivos, git add via Symfony Process) | p1 | US-ADM-013 |
| US-ADM-015 | Widget W5 — Curador (count batches pending, sensitive, métricas saúde) | p2 | US-ADM-013 |
| US-ADM-016 | Widget W6 — MCP server health (`mcp_memory_documents` count, last sync, ping CT 100) | p2 | US-ADM-001 |
| US-ADM-017 | Widget W7 — Vaultwarden (count itens, certs vencendo via API ADMIN_TOKEN) | p2 | US-ADM-001 |
| US-ADM-018 | Widget W8 — Sessões Claude Code (cross-dev via cc-watcher, últimas 10) | p3 | US-ADM-001 |
| US-ADM-019 | Widget W9 — Infra status (5 healthchecks paralelos com timeout) | p2 | US-ADM-001 |
| US-ADM-020 | Widget W10 — Custos Brain B 24h (`jana_health_check_results`) | p3 | US-ADM-001 |

## Sprint 3 (≥jul/2026, condicional)

- Grafana dashboard CT 100 embedded
- Alerting rules (Tier 0 violado → mensagem WhatsApp via Evolution)
- Daemon background Curador (Tailscale-aware) — extensão de `scripts/curador/`

## Backlog — Ferramentas internas

### US-ADM-021 · Tela Admin/MapaTelas — mapa vivo de telas (spec-driven cockpit)

> owner: wagner · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —

**Contexto.** Wagner pediu superfície única pra "ver o que cada tela tem e o que deveria ter" e dirigir evolução/atrito (sessão 2026-06-08). Hoje isso vive num `.md` gerado por `scripts/gen-mapa-telas.py` (PR #2412) que ele não abre direto. Esta US transforma o mapa numa **tela real no ERP, auto-regenerável**, e fecha o loop de captura de atrito.

**Goal.** Página Wagner-only `/admin/mapa-telas` que lista as ~232 telas reais agrupadas por módulo, mostrando o "deveria ter" (Mission do charter) + status do contrato, com captura de atrito inline que alimenta o backlog.

**Acceptance criteria:**
- [ ] Rota `/admin/mapa-telas` (middleware `is-wagner`), Inertia `Admin/MapaTelas.tsx`
- [ ] Backend: artisan command porta de `gen-mapa-telas.py` — escaneia `resources/js/Pages/**/*.tsx` + `*.charter.md` ao lado, classifica tela×componente, extrai Mission, gera JSON/props (cacheado)
- [ ] Por tela: nome, link "abrir" pra rota real, badge charter (✅ live / 📝 draft / ❌ sem), trecho da Mission
- [ ] Filtros: por módulo + toggle "só telas cegas (sem charter)"
- [ ] KPIs no topo: total telas, % com charter, módulos cegos
- [ ] **Fase 2** — botão "apontar atrito/evolução" por tela → cria item via skill `feedback-capture` → triagem vira charter/US (fecha o loop do diagrama da sessão 2026-06-08)
- [ ] `Admin/MapaTelas.charter.md` próprio (dogfood spec-driven)
- [ ] Pest: scope Wagner-only + business_id; browser MCP smoke salvo

**Refs.** PR #2412 (mapa + gerador) · ADR 0105 (cliente/Wagner como sinal) · skills `charter-first`, `feedback-capture`, `audit-to-backlog`.

> ⚠️ Criada manualmente como **US-ADM-021** (não 002): a tool MCP `tasks-create` gerou `US-ADM-002` por drift da cópia server-side (que só via US-ADM-001), colidindo com US-ADM-002..020 já existentes no main. Próximo ID livre = 021.

## Não-goals

- ❌ NÃO substitui Officeimpresso superadmin (mantido cliente-side)
- ❌ NÃO substitui `/copiloto/admin/team` (mantido em Hostinger)
- ❌ NÃO permite edição de PII cliente (LGPD — só auditoria)
- ❌ NÃO acessível pelo time (bloqueio duro `is_wagner`)
- ❌ NÃO acessível pela internet pública (Tailscale CIDR whitelist)
- ❌ NÃO vira interface conversacional (Jana mantém esse papel)

## Validação Sprint 1

- ✅ Wagner abre `admin.oimpresso.com` via Tailscale → 4 widgets carregam em <2s
- ✅ Maiara/Felipe tentam acessar → 403 + log em `mcp_admin_audit_log`
- ✅ curl externo (sem Tailscale) → time-out
- ✅ Health check W2 mostra estado real (testar quebrando isolamento multi-tenant temp em homolog → widget vira 🔴)
- ✅ Brief widget W1 cache 5min funcionando

## Histórico

- **2026-06-08** — Adicionada US-ADM-021 (tela Admin/MapaTelas, mapa vivo de telas). Frontmatter conformado ao schema atual (`version`/`last_updated`/`owner`) + seção `## User stories` — SPEC estava pré-schema e nunca fora revalidado.
- **2026-05** — SPEC inicial Admin Center (ADR 0122): Sprints 1-3, US-ADM-001..020.

## Referências

- [ADR 0122 — Admin Center CT 100](../../decisions/0122-admin-center-ct100.md) (mãe)
- [ADR 0093 — Multi-tenant isolation Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- PR #2412 — mapa panorâmico de telas + gerador (`scripts/gen-mapa-telas.py`)
