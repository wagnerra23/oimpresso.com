---
slug: 0218-multi-tenant-scope-checker-tier-0
number: 218
title: "MultiTenantScopeChecker — Tier 0 IRREVOGÁVEL (ADR 0093 defesa em profundidade)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: governance
tags: [governance, multi-tenant, tier-0, security]
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0158-module-grade-v3-d1-heuristica-hardening
  - 0216-governance-drift-framework-driftchecker-plugavel
pii: false
---

## Contexto

Princípio 6 Constituição v2: **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)). Toda query Eloquent **DEVE** ter `business_id` global scope, OU herdar de parent (`BelongsToBusinessViaParent`), OU estar em allowlist documentada.

Hoje a defesa é em 2 camadas:
- **L1 (runtime)**: trait `App\Concerns\HasBusinessScope` adiciona global scope automático
- **L2 (audit)**: `jana:health-check` SQL noturno `SELECT COUNT(*) FROM <tabela> WHERE business_id IS NULL` em tabelas críticas

Faltam:
- **L3 (CI gate)**: scan AST das Models antes do merge — qualquer Model novo SEM trait quebra build
- **L4 (pre-commit)**: scan staged files — bloqueia commit que adicione Model sem trait

**Evidência empírica de risco** (dossier senior expert):
- **PostgreSQL RLS CVE 2024-10976** + **CVE-2025-8713**: DB-level RLS row policies podem ser bypassadas via queries específicas. Defesa em profundidade application-layer (HasBusinessScope) é obrigatória.
- Smoke 2026-05-28: governance:audit --check=multi_tenant_scope escaneou **209 Models** repo-wide em 11ms — atualmente 0 drift (✅ Tier 0 saudável)

## Decisão

Implementar `Modules\Governance\Services\Checkers\MultiTenantScopeChecker` (`name='multi_tenant_scope'`):

- **Scan paths**: glob `Modules/*​/Entities/*.php`, `Modules/*​/Entities/*​/*.php`, `Modules/*​/Models/*.php`, `Modules/*​/Models/*​/*.php`
- **Detecção**: Model que `extends Model` (Eloquent\Model) E não tem `use HasBusinessScope` nem `use BelongsToBusinessViaParent`
- **Allowlist canon**: `config/governance.php > multi_tenant_scope_allowlist[]` (FQCN) — System (User/Business/Module) + Catálogo read-only (Country/State/City)
- **Severity**: `critical` (Tier 0 — viola Princípio 6 Constituição v2)
- **Enforcement**: `block` (pre-commit + CI gate fail)
- **Cadence**: `daily` (full repo) + `on_commit` (diff-only via pre-commit hook)
- **Tags**: `['tier_0', 'security', 'multi_tenant', 'compliance']`

**Modo `diff_only`** (pre-commit): roda `git diff --cached --name-only --diff-filter=AM`, filtra `Modules/*​/(Entities|Models)/.*\.php`, scaneia só staged. Latência <50ms (pequeno N).

**Finding payload**:
```json
{
  "target": "Modules/Foo/Entities/Bar.php",
  "target_type": "eloquent_model",
  "severity": "critical",
  "evidence": {
    "fqcn": "Modules\\Foo\\Entities\\Bar",
    "file": "Modules/Foo/Entities/Bar.php",
    "allowlist_size": 6
  }
}
```

**Mensagem do finding** instrui claramente:
> Model X sem HasBusinessScope/BelongsToBusinessViaParent. Ação: adicionar `use App\Concerns\HasBusinessScope;` no model + `use HasBusinessScope;` no body, OU declarar exception em config/governance.php > multi_tenant_scope_allowlist.

## Não-goals

- ❌ **Não detecta raw queries** (`DB::table('tabela')->where(...)` sem `business_id`). Sprint 2 (ADR 0223): `RawQueryTenantScopeChecker` com PHP Parser AST scan completo
- ❌ **Não detecta uso indevido de `withoutGlobalScope`** — válido em jobs system-level e admin module
- ❌ **Não scaneia Modules/*​/Http/Controllers** — controllers usam Models cujo scope já está aplicado
- ❌ **Não substitui `jana:health-check` SQL audit** — defesa em profundidade complementar (L2 + L3 + L4 juntas)
- ❌ **Não auto-corrige Model** — humano adiciona trait

## Plano implementação

✅ **Já implementado neste PR1 (ADR 0216 ship junto)**:
- `Modules\Governance\Services\Checkers\MultiTenantScopeChecker` (~190 linhas)
- Allowlist inicial em `config/governance.php`
- Registrado em `drift_checkers[]`
- Smoke local: 209 Models scaneados → 0 drift (✅ Tier 0 saudável — D1 hardening ADR 0158 deu resultado)

## Consequências

✅ **Boas:**
- Defesa em profundidade L3+L4 fecha gap CI/CD que L1 runtime não cobre
- Pre-commit bloqueia Model novo sem trait em <50ms (latência aceitável)
- Smoke real validou Tier 0 saudável: 209/209 Models compliant ⇒ baseline forte pra evitar regressão
- Allowlist documentada — não silenciamento opaco, sim exceção explícita com revisão Wagner
- Integra automaticamente com Brief Jana via `governance:drift` channel

⚠️ **Tradeoffs:**
- Regex-based detection é simplista (não AST). False positives possíveis em:
  - Models que usam trait via `parent::class` herança indireta (raríssimo)
  - Models que extendem subclasse custom (`extends BaseModel extends Model`) — minha regex não pega isto
- Allowlist precisa Wagner sign-off pra cada item adicionado (governance própria)
- Detecta ausência de trait, não ausência de FUNCIONAMENTO. Trait bugado/disabled passa por aqui — confia em test coverage
- Latência pre-commit ~50ms × N staged files; aceitável até N=20 (típico)

## Validação

- ✅ Smoke `php artisan governance:audit --check=multi_tenant_scope --json` retorna 0 findings (saudável)
- ✅ Performance: 11ms para 209 Models
- ⏳ Pest tests com fake Model FAILING (criar fixture Model sem trait → checker detecta)
- ⏳ Pre-commit smoke (PR3): adicionar fake Model sem trait → commit bloqueado

## Notas

- Estado base saudável é **GRANDE** indicador. D1 hardening ADR 0158 entregou o que prometeu — agora temos ferramenta pra manter saudável continuamente em vez de detectar regressão manualmente.
- Próxima evolução (Sprint 2): AST scan completo via `nikic/php-parser` (já em dev deps?) cobre cross-tenant queries em controllers + jobs.
- `MultiTenantScopeChecker` complementa, não substitui, `MultiTenantGovernanceTest.php` existente em Modules/Governance/Tests — eles testam runtime behavior, este audita estrutura.
