---
page: /admin/feature-flags
component: resources/js/Pages/Admin/FeatureFlags/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-31"
parent_module: Admin
related_us: [US-INFRA-008]
related_adrs: [122, 94, 93, 91, 70]
tier: A
charter_version: 1
---

# Page Charter — /admin/feature-flags (DRAFT)

> **Status:** draft criado em 2026-05-31 no DS uplift da tela (cores cruas amber/red → Alert/EmptyState/Badge tokens + Inertia::defer). Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Admin/Http/Controllers/FeatureFlagsController.php` (Wagner-only — middleware `tailscale-only → auth → is-wagner`). Lê GrowthBook via `GrowthBookAdminService` (REST) + audits de `feature_flag_audits`. US-INFRA-008. ADR mãe 0122.

---

## Mission

Painel read-mostly Wagner-only pra inspecionar o estado das feature flags GrowthBook self-hosted (por environment + rules biz-{N}) e o audit log recente das mudanças. Dá visão única do toggling sem precisar abrir a UI do GrowthBook nem rodar `flag:list` na CLI. Mutações vivem na tela de detalhe (`Show.tsx`), não aqui.

---

## Goals — Features (faz)

- Lista todas as features configuradas: key (link p/ detalhe), valueType, defaultValue, badges por environment (ON/OFF + nº de rules)
- Audit log recente (20 últimas) de `feature_flag_audits`: quando, quem (`actor_label`), flag (link), ação (Badge), env, resumo do diff
- Botão "Limpar cache local" (POST `admin.feature-flags.cache.clear`)
- Aviso DS (Alert default) quando `GrowthBookAdminService` não está configurado, com link pro token
- Erro de fetch (Alert destructive) sem derrubar a página (`safeCall` no controller)
- `features`, `fetch_error` e `recent_audits` via `Inertia::defer` → `<Deferred fallback={skeleton}>` (props caras: HTTP externo + DB fora do first paint)
- EmptyState (shared) graceful pra "sem features" e "sem audits"
- AppShellV2 + PageHeader shared (canon UI v2)
- Cores SÓ via tokens DS (Alert / Badge / EmptyState / border-border / text-muted-foreground) — zero hex/oklch/bg-cru

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO edita flags aqui — toggling / rules biz-{N} / mata-switch vivem em `Show.tsx`
- ❌ NÃO cria feature flags (só GrowthBook UI ou `php artisan flag:set`)
- ❌ NÃO é acessível pelo time (Maiara/Felipe/Luiz/Eliana) — `is-wagner` duro
- ❌ NÃO é acessível pela internet pública — Tailscale CIDR whitelist
- ❌ NÃO mostra payload_before/after completo (só `diff_summary`; detalhe fica no Show)
- ❌ NÃO escreve em audit log em GET (só leitura; mutações auditam no POST)
- ❌ NÃO pagina audits além das 20 mais recentes (histórico completo é por-flag no Show)
- ❌ NÃO usa cores cruas (`bg-amber-100`, `bg-red-100`, `#hex`, `oklch`, `style` de cor)

---

## UX targets

- First paint < shell + PageHeader imediato; tabelas hidratam via Deferred (skeleton) sem bloquear
- Erro de GrowthBook NÃO quebra a página — Alert destructive inline no card de features
- Empty states com CTA claro (abrir GrowthBook) em vez de texto solto centralizado
- Badges de environment legíveis: ON = `default`, OFF = `secondary` (tokens)

---

## Automation hooks (faz)

- `safeCall` captura exceção do GrowthBook e devolve `{ data, error }` (fail-soft)
- Deferred dispara fetch das props pesadas em request separado pós-shell
- `OtelHelper::spanBiz('admin.feature_flags.index')` agrega latência REST + DB

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO faz refetch automático/polling do GrowthBook a cada intervalo
- ❌ NÃO limpa cache automaticamente em GET (só via botão explícito)
- ❌ NÃO dispara mutação de flag a partir desta tela
- ❌ NÃO carrega audits de TODAS as flags (limit 20; resto no Show por-flag)

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke walkthrough via Tailscale (depende ambiente CT 100)
- [ ] Confirmar fallback skeleton visualmente aceitável (Wagner aprova screenshot, não tabela)
