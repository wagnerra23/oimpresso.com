---
number: 122
title: Admin Center — Centro de Operações @ CT 100 (Tailscale-only, Wagner-only, agrega)
status: proposto
date: "2026-05-09"
deciders: [Wagner]
supersedes: []
references:
  - 0042-proxmox-docker-host-canonico.md
  - 0053-mcp-server-governanca-como-produto.md
  - 0058-reverb-substituido-por-centrifugo-frankenphp.md
  - 0061-conhecimento-canonico-git-mcp-zero-automem.md
  - 0062-separacao-runtime-hostinger-ct100.md
  - 0093-multi-tenant-isolation-tier-0.md
  - 0094-constituicao-v2-7-camadas-8-principios.md
  - 0124-curador-conhecimento-pipeline.md
lifecycle: active
---

## Contexto

Wagner pediu **"administrador para gerenciar toda a infra da empresa"** — painel único que agrega visão de Curador (ADR 0124), health checks, time, Vaultwarden, MCP server, infra, brief diário, sessões Claude Code, e ADRs Tier 0 violados.

Hoje essa visão está fragmentada em:
- `php artisan jana:health-check` (CLI, output em log)
- Tools MCP (`brief-fetch`, `cycles-active`, `my-work`, `decisions-search`)
- `Modules/Officeimpresso/` superadmin (cliente-side, multi-tenant)
- `/copiloto/admin/team` (MCP tokens, docs)
- Vaultwarden web (vault.oimpresso.com)
- Proxmox UI (192.168.0.50:8006)
- Tailscale console externo
- Hostinger hPanel externo

Não tem **dashboard único** com tudo agregado. Wagner gasta tempo trocando de UI/CLI. Em escala empresa (R$ 5mi/ano [ADR 0022](0022-meta-5mi-ano-financeira.md)), isso vira gargalo de visibility.

## Decisão

Criar `Modules/Admin/` como **Centro de Operações** com 4 propriedades fundamentais:

### 1. Wagner-only (resposta ao prompt 1 de 2026-05-09)

- Auth: gate hardcoded `is_wagner($user)` (verifica `user_id=1` em biz=1) + Spatie role `superadmin#1`
- Equipe (Maiara/Felipe/Luiz/Eliana[E]) **NÃO** acessa — bloqueio duro no middleware
- Razão: dados agregam PII de cliente, secrets, custos Brain B, ADRs Tier 0 violados — exposição mesmo a dev sênior aumenta blast radius desnecessariamente

### 2. CT 100 only — `admin.oimpresso.com` Tailscale-only (resposta ao prompt 2)

- Subdomínio Traefik `admin.oimpresso.com` no CT 100 ([ADR 0042](0042-proxmox-docker-host-canonico.md) padrão)
- DNS A record aponta pra IP **Tailscale** `100.99.207.66`, NÃO IP público
- Internet pública não alcança → não existe vetor de ataque externo
- Wagner acessa de casa via Tailscale instalado no laptop dele
- Hostinger NÃO hospeda Admin Center ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — runtime separado obrigatório)

### 3. Agrega, não substitui (resposta ao prompt 3)

- `Modules/Officeimpresso/` superadmin **continua existindo** (cliente-side multi-tenant, app principal Hostinger)
- `/copiloto/admin/team` (MCP tokens) **continua existindo** no Hostinger
- Admin Center é **read-mostly aggregator** — chama APIs/MCPs/SQL existentes e exibe
- Ações mutacionais limitadas: `/admin/curador/apply`, `/admin/tokens/regenerate`, `/admin/health/run-now` — nada de CRUD cliente
- Não vira "outro Copiloto" — Jana mantém papel conversacional

### 4. Multi-tenant Tier 0 preservado ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- Admin Center **observa** dados multi-tenant (cliente-side) sem operar neles
- Queries usam `withoutGlobalScopes` apenas com comentário `// SUPERADMIN: <razão>` (mandatório)
- Não há "modo dev" que ignore `business_id` — toda leitura cross-business é log-auditada em `mcp_admin_audit_log`

## Stack runtime

| Componente | Onde | Por quê |
|---|---|---|
| Web framework | Laravel 13.6 + PHP 8.4 (FrankenPHP) | mesmo stack canon ([what-oimpresso.md](../what-oimpresso.md)) |
| Frontend | Inertia v3 + React 19 + Tailwind 4 | mesmo padrão MWART ([ADR 0104](0104-processo-mwart-canonico-unico-caminho.md)) |
| Auth | Sanctum + role `superadmin#1` + IP whitelist Tailscale CIDR `100.99.0.0/16` | defense in depth |
| Queue | Horizon (CT 100) | já configurado, [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) |
| Real-time | Centrifugo + FrankenPHP | mesmo da app principal |
| DB | MySQL Hostinger via autossh tunnel CT 100 | mesmo do MCP server, [ADR 0042](0042-proxmox-docker-host-canonico.md) |
| Webhook ingest | Bearer token + endpoint `/admin/api/*` | scripts Node Curador locais → Admin |

## Widgets MVP (Sprint 1) — read-only, fontes existentes

Princípio: **só widgets cuja fonte de verdade já existe**. Não cria novo SQL/serviço pra MVP — só expõe.

| # | Widget | Fonte | Tipo |
|---|---|---|---|
| W1 | **Brief diário** | tool MCP `brief-fetch` (cache 5min) | preview render markdown |
| W2 | **Health checks 5 SQL** | `php artisan jana:health-check` results em `health_check_results` | tabela com 🟢🟡🔴 |
| W3 | **Cycles + tasks** | tools MCP `cycles-active` + `my-work` agregado dev-by-dev | kanban read-only |
| W4 | **ADRs Tier 0 violados** | `jana:health-check` outputs `multi_tenant_isolation`, `pii_leak`, etc | alerta vermelho top-bar |

Sprint 1 = MVP (~3-5 dias com IA-pair fator 10x [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

## Widgets Sprint 2 — fontes novas / extensão

| # | Widget | Fonte | Esforço |
|---|---|---|---|
| W5 | **Curador** | `mcp_curador_batches` (criada na Fase 2 do [ADR 0124](0124-curador-conhecimento-pipeline.md)) | médio (precisa migration + API receive JSONL) |
| W6 | **MCP server health** | `mcp_memory_documents` count, last `git_sha` sync, ping CT 100 | baixo |
| W7 | **Vaultwarden** | API Vaultwarden (`vault.oimpresso.com:8200/api/`) com ADMIN_TOKEN | médio (parser de itens com tag `cert-vencendo-30d`) |
| W8 | **Sessões Claude Code** | `cc_watcher_sessions` (já populada via `oimpresso-cc-watcher-setup` skill) | baixo |
| W9 | **Infra status** | ping Hostinger SSH, CT 100 Tailscale latency, Centrifugo `/health`, Meilisearch `/version`, MySQL `SELECT 1` | médio (5 healthchecks paralelos com timeout) |
| W10 | **Custos Brain B 24h** | `jana_health_check_results` métrica `custo_brain_b_24h` | baixo |

## Não-goals

- ❌ NÃO substitui Officeimpresso superadmin existente (cliente-side mantido)
- ❌ NÃO substitui `/copiloto/admin/team` MCP tokens (mantido em Hostinger)
- ❌ NÃO permite edição de PII cliente (LGPD — só auditoria/leitura cross-business com log)
- ❌ NÃO acessível pelo time (Maiara/Felipe/Luiz/Eliana[E]) — bloqueio duro
- ❌ NÃO acessível pela internet pública — só Tailscale
- ❌ NÃO vira interface conversacional (Jana mantém esse papel)
- ❌ NÃO duplica dados — só lê de fontes canônicas

## Plano de adoção

**Sprint 1 — MVP CASCA + 4 widgets (Sprint dedicada, ~3-5 dias IA-pair):**
- US-ADM-001: scaffold `Modules/Admin/` (módulo nWidart, [ADR 0011](0011-alinhamento-padrao-jana.md) + skill `criar-modulo`)
- US-ADM-002: Traefik `admin.oimpresso.com` no CT 100 + DNS A record Tailscale 100.99.207.66
- US-ADM-003: Auth gate `is_wagner` + middleware `tailscale-only` + Spatie role
- US-ADM-004: Page `Pages/Index.tsx` shell (header, sidebar W1-W4, footer)
- US-ADM-005: Widget W1 (Brief diário)
- US-ADM-006: Widget W2 (Health checks 5 SQL)
- US-ADM-007: Widget W3 (Cycles + tasks)
- US-ADM-008: Widget W4 (ADRs Tier 0 alerta)
- US-ADM-009: Pest tests (auth gate, RBAC, Tailscale IP filter)
- US-ADM-010: smoke walkthrough (Wagner abre `admin.oimpresso.com` via Tailscale, valida 4 widgets)

**Sprint 2 — Modules/Arquivos integrado + extensões (~3-5 dias):**

> **Amendment 2026-05-09:** Sprint 2 do Admin reaproveita o backbone [Modules/Arquivos](../requisitos/Arquivos/SPEC.md) ([ADR 0123](0123-modules-arquivos-backbone.md)). Pages que iam ser `/admin/curador/*` viram `/admin/arquivos/*`. Tabelas `mcp_curador_*` substituídas por `arquivos`+`arquivos_audit_log`+`arquivos_dedupe`.

- US-ADM-011: Pages `Modules/Admin/Pages/Arquivos/{Index,Review,Detail}.tsx` (referencia US-ARQ-013..015)
- US-ADM-012: Widget W5 — Arquivos no painel principal (count por bucket, sensitive aguardando vault, métricas saúde)
- US-ADM-013..017: Widgets W6-W10 (MCP server, Vaultwarden, Sessões, Infra, Custos B)

**Sprint 3 (≥jul/2026, condicional):** observability avançada — Grafana dashboard CT 100 embedded, alerting rules, on-call rotation (mesmo sendo só Wagner — pode automatizar pra mensagem WhatsApp via Evolution se Tier 0 for violado).

## Alternativas consideradas

### A. Hostinger em `/admin`
**Rejeitada.** Hostinger é shared hosting — mesmo com auth, exposição pública aumenta superficie de ataque ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)). Tailscale-only no CT 100 zera vetor externo.

### B. Reaproveitar `Modules/Officeimpresso/` superadmin
**Rejeitada.** Officeimpresso é cliente-side com multi-tenant scope ativo. Misturar visão Wagner-only (sem business_id scope) com cliente-side viola SoC brutal ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §5).

### C. CLI-only (ferramentas MCP + scripts artisan)
**Rejeitada.** Wagner já tem isso. Pediu painel agregado pra reduzir tempo de troca de contexto. Tradeoff custo-construção vs benefício-velocidade — vale construir UI minimal.

### D. Grafana embed standalone
**Rejeitada como solução completa.** Grafana é bom pra time-series, mas Admin Center precisa também de listas (cycles, tasks, batches Curador). Pode ser **complementar** em Sprint 3 (W11 = "Open Grafana") sem virar UI principal.

### E. Multi-usuário (time todo acessa)
**Rejeitada (resposta Wagner 2026-05-09).** Aumenta blast radius sem benefício imediato — time não pediu. Pode reavaliar em ≥2027 se Maiara/Felipe ganharem responsabilidades de governança.

## Consequências

✅ **Boas:**
- Wagner ganha visão única de toda infra empresa
- Tailscale-only zera superficie ataque externa
- Read-mostly evita risco de "Wagner clica errado e quebra prod"
- Reaproveita stack canon (Laravel + Inertia + nWidart) — sem nova tecnologia
- Curador encontra casa natural como widget — sem 3º lugar paralelo
- Princípio escalável: cada novo sistema gerenciado vira widget (não duplica painel)

⚠️ **Tradeoffs:**
- CT 100 vira ponto crítico — se cair, Wagner perde visão (mitigação: CLI tools MCP continuam funcionando como fallback)
- Tailscale instalado em todo dispositivo Wagner usa pra trabalhar (laptop, casa, eventual celular) — overhead operacional aceitável
- Custos Brain B widget pode disparar alarme em cenário "ROTA LIVRE faz 100 perguntas" → ajustar threshold conforme uso real
- Auth `is_wagner` hardcoded é frágil contra DB corruption — Pest test obrigatório validando user_id=1 + biz_id=1

## Validação

- ✅ Wagner abre `admin.oimpresso.com` via Tailscale → 4 widgets carregam em <2s
- ✅ Maiara/Felipe tentam acessar → 403 + log em `mcp_admin_audit_log`
- ✅ Internet pública (curl externo) → time-out (Traefik não responde fora Tailscale CIDR)
- ✅ Health check W2 mostra estado real (testar quebrando isolamento multi-tenant temp em homolog → widget vira 🔴)
- ✅ Brief widget W1 cache 5min funcionando (não bate brief-fetch a cada refresh)

## Notas de governança

- Admin Center **NÃO** é canon obrigatório — qualquer dev (incl. Wagner) pode resolver problema sem ele usando CLI tools MCP. Existência é **conveniência**, não dependência.
- Bug em Admin Center NÃO bloqueia operação — toda ação tem fallback CLI/SQL direto.
- ADR 0122 é sobre **decisão arquitetural** (onde, quem, como). Especificações detalhadas (UX, API contracts, tabelas) ficam em `memory/requisitos/Admin/SPEC.md` quando Sprint 1 começar.
