---
page: /admin
component: resources/js/Pages/Admin/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-10"
parent_module: Admin
related_us: [US-ADM-004, US-ADM-005, US-ADM-006, US-ADM-007, US-ADM-008, US-ADM-015, US-ADM-016, US-ADM-017, US-ADM-018]
related_adrs: [122, 93, 94, 91, 70, 42, 62]
tier: A
charter_version: 1
---

# Page Charter — /admin (DRAFT)

> **Status:** draft criado em Sprint 1 dia 3-4 a partir do [ADR 0122](../../../../memory/decisions/0122-admin-center-ct100.md). Wagner aprova **Non-Goals + Automation Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Admin/Http/Controllers/IndexController.php` invoca 4 adapters Service (Brief, Health, Cycles, AdrAlert). Não substitui Officeimpresso superadmin nem `/copiloto/admin/team`.

---

## Mission

Centro de Operações Wagner-only que agrega visão única de toda a infra/governance da empresa em **read-mostly aggregator**: brief diário, health checks 5 SQL, cycles ativos com tasks-by-dev, ADRs Tier 0 violadas. Reduz tempo de troca de contexto entre CLI/MCP/painéis dispersos.

---

## Goals — Features (faz)

- 4 widgets read-mostly em grid 2-col responsivo (mobile 1-col)
  - **W1 Brief diário** — preview markdown do `mcp_briefs` mais recente (cache 5min)
  - **W2 Health Checks** — 5 SQL com 🟢🟡🔴 e overall_status (snapshot file)
  - **W3 Cycles + Tasks** — top 3 cycles ativos + tabela tasks-by-dev (% done)
  - **W4 ADRs Tier 0 violadas** — alerta vermelho top-bar quando >0 + lista detalhada com link pro ADR no GitHub
- Top-bar vermelha quando Tier 0 violada (chamativo, primeiro elemento da página)
- Banner amarelo `ADMIN_BYPASS_LOCAL ativo` quando middleware bypass dev
- Footer com `generated_at` + ADR canon (transparência)
- AppShellV2 layout (canon)
- PageHeader shared component
- Card / CardContent / CardTitle do `ui/`
- Icon helper canônico
- Auth gate: tailscale-only → auth → is-wagner (3 condições AND + fallback_username env)

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO substitui `Modules/Officeimpresso` superadmin (cliente-side mantido em Hostinger)
- ❌ NÃO substitui `/copiloto/admin/team` MCP tokens (mantido)
- ❌ NÃO permite edição de PII cliente (LGPD — só auditoria/leitura)
- ❌ NÃO acessível pelo time (Maiara/Felipe/Luiz/Eliana[E]) — bloqueio duro `is-wagner`
- ❌ NÃO acessível pela internet pública — Tailscale CIDR whitelist
- ❌ NÃO vira interface conversacional (Jana mantém esse papel)
- ❌ NÃO gera dados — só agrega de fontes canônicas (mcp_briefs, snapshot, mcp_cycles, mcp_tasks)
- ❌ NÃO faz mutations destrutivas (apply Curador/regenerate token virá em Sprint 2 com double-confirmation)
- ❌ NÃO mostra dados multi-tenant cross-business sem `withoutGlobalScopes` justificado

---

## UX targets (estado-da-arte, calibragem Cockpit V2)

- Carregamento <2s via cache (Brief 5min) + snapshot file (Health/AdrAlert)
- 4 widgets visíveis simultaneamente em laptop 1280px (Wagner padrão)
- Mobile 1-col responsive — Wagner pode acessar via Tailscale celular
- Empty state graceful pra cada widget (snapshot ausente / tabela ausente / sem cycles)
- Top-bar vermelha sticky quando Tier 0 violada (não rola pra fora viewport)
- Click no badge ADR na lista alertas abre GitHub blob view (target=_blank)

---

## Automation hooks (faz)

- Cache Brief 5min via `Cache::remember('admin.widget.brief', 300, ...)`
- Snapshot file Health refreshado por scheduled command (Sprint 2 US-ADM-021): `php artisan admin:refresh-snapshot`
- Cycles aggregator lê SQL direto (single-business=1 superadmin)
- AdrAlertReader reusa snapshot Health filtrando por TIER_0_MAP

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO atualiza Brief on-demand a cada GET /admin (cache obrigatório, evita rate-limit)
- ❌ NÃO roda `jana:health-check` síncrono no controller (timeout HTTP — Sprint 2 vira job Horizon)
- ❌ NÃO escreve em `mcp_admin_audit_log` em GET (só em ações mutacionais Sprint 2+)
- ❌ NÃO carrega TODAS as tasks (limit cycle atual; histórico Sprint 2)
- ❌ NÃO mostra senhas/secrets ainda que admin Wagner-only — defense in depth
- ❌ NÃO chama brief-fetch tool MCP via HTTP (round-trip caro; consulta SQL direto)

---

## Métricas de sucesso (validação Sprint 1 dia 5)

- ✅ Wagner abre `https://admin.oimpresso.com` via Tailscale → 4 widgets <2s
- ✅ Wagner em dev local (`APP_ENV=local` + `ADMIN_BYPASS_LOCAL=true`) acessa via `http://oimpresso.test/admin` sem precisar Tailscale
- ✅ Maiara/Felipe tentam acessar (mesmo dev local) sem bypass → 403
- ✅ Snapshot ausente → empty states graceful em W2 + W4 (não quebra página)
- ✅ Tier 0 violada (testar quebrando isolation temp) → top-bar vermelha + lista alertas

---

## Pendências Sprint 1 dia 5

- [ ] Pest matriz 6 cenários (US-ADM-009): Wagner Tailscale role / Wagner sem role / Maiara Tailscale / Wagner externo / sem auth Tailscale / sem auth externo
- [ ] Visual comparison vs mockup Cowork (ainda não existe — Sprint 1 não tem prototipo HTML aprovado pré-codigo)
- [ ] Smoke walkthrough Wagner manual (depende DNS+container CT 100 — US-PRE)

---

## Sprint 2 evoluções planejadas

- Widget W5 Curador (count batches pending, métricas saúde 99.9%) — pós-merge Modules/Arquivos UI
- Widget W6 MCP server health (count docs, last sync, ping CT 100)
- Widget W7 Vaultwarden (cert vencendo, rotation status)
- Widget W8 Sessões Claude (cross-dev cc-watcher)
- Widget W9 Infra status (Hostinger SSH last contact, CT 100 latency, Centrifugo, Meilisearch)
- Widget W10 Custos Brain B 24h (gauge + threshold alarm)
- Mutations: apply Curador batch + regenerate MCP token + run-now health-check
