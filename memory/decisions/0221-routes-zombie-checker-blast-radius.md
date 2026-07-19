---
slug: 0221-routes-zombie-checker-blast-radius
number: 221
title: "RoutesZombieChecker — routes sem hits = tech debt + blast radius"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: governance
tags: [governance, routes, tech-debt, observability]
related:
  - 0216-governance-drift-framework-driftchecker-plugavel
pii: false
---

## Contexto

`Route::getRoutes()` retorna **1889 routes** em oimpresso (smoke 2026-05-28). Rotas declaradas nunca usadas:

- **Tech debt**: código órfão difícil de remover (medo de quebrar)
- **Blast radius**: superfície de ataque maior (cada route é endpoint válido — auth/RBAC bypassáveis se rota esquecida)
- **Compliance**: Spotify Backstage Scorecards lição "Production SRE Level 3" — feature flags + endpoints <30d unused

Hoje não existe nenhum mecanismo de detecção. Routes acumulam silenciosamente.

## Decisão

Implementar `Modules\Governance\Services\Checkers\RoutesZombieChecker` (`name='routes_zombie'`):

- **Snapshot**: `Route::getRoutes()` filtrado pra métodos públicos (GET|POST|PUT|PATCH|DELETE — exclui HEAD/OPTIONS/internals)
- **Cross-check**: tabela `system_access_log` (se existir) últimos N=30 dias
- **Window**: padrão 30d hit-window + 14d grace period
- **Allowlist** `config/governance.php > routes_zombie_allowlist[]`:
  - Health checks: `/healthz`, `/up`
  - Webhooks externos low-traffic: `/api/webhooks/`, `/api/asaas`, `/api/sicoob`, `/api/mailgun`
  - Centrifugo proxy: `/_centrifugo`
  - Adicionar mais conforme MVP rodar
- **Severity**: `low`
- **Enforcement**: `advisory` (advisory only — Brief Jana mensal)
- **Cadence**: `weekly` (stats caros, não diário)
- **Tags**: `['tier_2', 'tech_debt', 'observability']`

**Modo MVP — fail-safe se sem tabela**: smoke 2026-05-28 mostrou `system_access_log` NÃO existe ainda → checker retorna `clean` com `metadata.skipped = "access log table not available — Sprint 2 ADR 0162"`. Não emite findings sem evidência.

## Não-goals

- ❌ **Não remove routes automaticamente** — finding sugere, humano remove
- ❌ **Não detecta routes duplicadas** (mesmo URI registrado 2× com handlers diferentes) — `mwart-gate.yml` já cobre
- ❌ **Não detecta middleware bypass** (route declarada mas middleware required ausente) — futura ADR 0225
- ❌ **Não cobre Inertia routes** (resources/js/pages/* sem backend) — `mwart-comparative` cobre
- ❌ **Não funciona sem persistência de access log** — Sprint 2 obrigatório

## Plano implementação

✅ **Já implementado neste PR1 (ADR 0216 ship junto)**:
- `Modules\Governance\Services\Checkers\RoutesZombieChecker` (~180 linhas)
- Allowlist inicial
- Fail-safe quando access log table ausente
- Registrado em `drift_checkers[]`
- Smoke: 1889 routes snapshot OK, skip por table missing (resultado esperado)

⏳ **Sprint 2 (ADR 0226 futura)**:
- Criar `system_access_log` table ou integrar com `mcp_observability_spans` (ADR 0162) pra dados runtime
- Adicionar middleware `LogAccessMiddleware` global pra populates a tabela
- Smoke biz=4 prod 30d → primeira lista real de routes zombies

## Consequências

✅ **Boas:**
- 1889 routes / só uma fração é exercitada — checker vai produzir lista enxuta priorizada pra cleanup
- Backstage-style scoring por módulo no futuro (% routes ativas / total declaradas)
- Reduz blast radius por route órfã removida (cada uma = mini-vulnerabilidade potencial)
- Allowlist evita false-positive em webhooks low-traffic (Asaas/Sicoob etc.)

⚠️ **Tradeoffs:**
- Inútil sem `system_access_log` — dependência Sprint 2 explícita
- 30d window pode ser curto pra routes mensais (faturamento Asaas, reports) — allowlist mitiga
- Performance cron weekly: query agregada 30d × 100K rows access log = ~1-2s. Aceitável.
- Findings podem ser barulhentos antes do cleanup inicial — Brief Jana mensal já é frequência adequada

## Validação

- ✅ Smoke `php artisan governance:audit --check=routes_zombie --json`: 1889 routes snapshot, skip correto sem table
- ⏳ Sprint 2: smoke com `system_access_log` populated, validar findings reais

## Notas

- Checker é "pre-wired" pra Sprint 2 — assim que `system_access_log` virar canon, funciona sem mudança aqui
- `mcp_observability_spans` (ADR 0162) é candidato natural pra fonte de access stats — Sprint 2 decide qual usar (sample rate vs cobertura)
- Routes Zombie é caso clássico de "loop fechado por métrica" (Princípio 4 Constituição v2) — detecção + remediation + verify
