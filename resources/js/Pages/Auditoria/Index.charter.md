---
page: /auditoria
component: resources/js/Pages/Auditoria/Index.tsx
status: draft
---

# Charter — Pages/Auditoria/Index.tsx

> Charter F1.5 obrigatório (ADR 0107 + ADR 0114). Atualizado Wave 25 (2026-05-16) — adiciona Export flow + Bulk action panel + edge cases catalogados.
> Tela `/auditoria` — UI rica de governança transversal sobre `activity_log`.

## Goal único

Wagner/admin/auditor olha o `/auditoria` e em **≤2 cliques** consegue:
1. Filtrar por causer_kind (user/ia/system) + event (created/updated/deleted/reverted/restored) + subject_type
2. Identificar a entry que precisa investigar
3. Abrir detalhe (`/auditoria/{id}`) com diff old vs current + possibilidade de revert

Sem revert ninguém volta na decisão = governança quebrada. UI defende whitelist
UNREVERTIBLE com badge vermelho.

## Audience

- **Wagner** (superadmin) — uso primário, vê tudo cross-tenant via Admin Center.
- **Admin de negócio** (per business_id) — vê activity_log do próprio business
  via global scope Tier 0.
- **Auditor LGPD/compliance** — leitura, sem revert.

## Data sources (Controller)

- `AuditoriaController@index` → `AuditEntryService::list($businessId, $filters)`
- `AuditoriaController@show($id)` → `AuditEntryService::find($businessId, $id)`
- `AuditoriaController@revert($id)` → `RevertService::revert($activity, $by, $reason)`

**Inertia::defer DEFAULT** ([RUNBOOK-inertia-defer-pattern.md](../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)):
- `activities` = `LengthAwarePaginator` → deferred (queries paginadas custam 100-400ms)
- `activity` (Show) = single + properties JSON → deferred
- `filters` = state UI ≤1ms → eager

## Layout (12-column AppShellV2)

```
[PageHeader: Auditoria — N entries (filtros ativos)]
[Filtros bar: causer_kind | event | subject_type | período]
[<Deferred data="activities" fallback={<Skeleton/>}>]
  [Table:]
    [#] [causer (user/ia/system badge)] [event badge] [subject_type] [description] [created_at] [→ Detail]
[Paginação Inertia preserve filters]
[EmptyState se 0 results]
```

## Whitelist UNREVERTIBLE (badge vermelho)

Quando entry pertence a uma das 5 categorias (`RevertService::unrevertibleRegistry`):
- Marcacao (Portaria MTP 671/2021)
- NfeTransaction (cstat ∈ {100, 101, 135})
- TituloBaixa (origem='asaas-paid')
- OS (nfse_emitida=true)
- Transaction (payment posterior)

Botão "Reverter" fica **disabled** + tooltip explicando o motivo (ADR 0127 §3).

## Tier 0 IRREVOGÁVEIS

- ⛔ Toda query SCOPED via `AuditEntryService::baseQuery($businessId)` (ADR 0093)
- ⛔ `revert_reason` mínimo 10 chars + passar por `PiiRedactor` ANTES de persistir
- ⛔ Bulk revert ≤50 ids (BulkRevertActivityRequest)
- ⛔ Charter exige Pest `MultiTenantIsolationTest` biz=1 vs biz=99
- ⛔ Inertia::defer NUNCA pra `filters` (state UI, ≤1ms)
- ⛔ Frontend **NUNCA** infere "is unrevertible" no cliente — depende de
  `activity.unrevertible_reason` vindo do backend

## Decisões F1.5

- **PageHeader** com count visível (`N entries`) — facilita Wagner perceber
  filtro ativo.
- **Badge por causer_kind** com 3 cores (user=blue, ia=purple, system=gray) —
  ADR 0127 distingue origem.
- **Skeleton table** durante Inertia::defer (não spinner full-page).
- **Diff old vs current** em Detail.tsx via `react-diff-viewer` (lib leve ~6KB).

## Comparativo F3 (15 dimensões)

Pendente Wave 24 — `mwart-comparative V4` orquestrará Claude Design plugin.
Por ora referência canônica é Datadog Audit Trail UI (filtros laterais
sticky + table dense + diff modal).

## Wave 25 — Export flow + Bulk action panel (POLISH ≥92)

### Export CSV/JSON (`ExportAuditEntriesRequest`)

Auditor LGPD/Compliance pode dump da grade filtrada via botão `[Exportar]` no
PageHeader. UX:

```
[Modal Export]
  Format: ( ) CSV  ( ) JSON
  Limite: [____] (default 1000, max 10.000 — anti-abuse)
  [x] Incluir properties JSON (PII passa por PiiRedactor automaticamente)
  Motivo (obrigatório, min 10 chars):
  [________________________________]
  [Cancelar]  [Exportar]
```

**Tier 0 IRREVOGÁVEIS export:**
- ⛔ Cap hard `limit` max 10.000 (FormRequest) — anti-DoS no servidor
- ⛔ `include_properties=true` SEMPRE passa pelo `PiiRedactor` antes do stream
- ⛔ Motivo grava `activity_log` evento `auditoria.export.requested` (audit de auditoria)
- ⛔ Format whitelist `csv|json` apenas — bloqueia `xlsx`/`php` injetados
- ⛔ `business_id` JAMAIS no body (`prohibited` rule) — sempre da session

### Bulk action panel (`BulkRevertActivityRequest` — Wave 18 + UX Wave 25)

Quando user marca ≥2 checkboxes na table, aparece sticky panel:

```
[Panel Bulk — N selecionados]
  [Reverter N]  [Anotar N]  [Exportar N]  [Limpar seleção]
```

**Tier 0 bulk revert:**
- ⛔ Máximo 50 ids por chamada (FormRequest cap)
- ⛔ Reason único compartilhado (auditor justifica uma vez)
- ⛔ Whitelist UNREVERTIBLE filtra silenciosamente — count "Reverted: X/Y"
  no toast pós-action
- ⛔ Confirm modal double-confirm exigido (anti-tap acidental)

### Edge cases catalogados Wave 25

1. **Auditor seleciona 50 ids mas 30 são UNREVERTIBLE** — backend retorna
   `{ reverted: 20, skipped: 30, skipped_reasons: [...] }`. Frontend toast
   mostra split + abre modal "Ver detalhes dos 30 ignorados".
2. **Period filter sem `period_end`** — backend assume `period_end = now()`
   silenciosamente; PageHeader badge mostra range explícito.
3. **Export com filter zero results** — backend retorna 204 No Content +
   toast frontend "Nenhuma entrada no filtro atual" (NÃO download vazio).
4. **PiiRedactor falha (Jana indisponível)** — Controller fail-secure: retorna
   503 + audit log `auditoria.export.failed` com reason. Auditor reexecuta.
5. **Charter <-> Controller drift** — Pest `PiiLeakActivityLogEnforceTest`
   garante `RevertService::revert` invoca `PiiRedactor` antes de save.

## Anti-padrões catalogados

[`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md):
- ❌ Filter bar full-width que empurra table — usar Cards laterais (160-200px)
- ❌ Action button "Reverter" sem confirm modal (precisa double-confirm + reason)
- ❌ Sem `<Deferred>` wrap = página carrega lenta mesmo com defer no controller

## Referências

- ADR 0093 — Multi-tenant Tier 0
- ADR 0094 — Constituição v2
- ADR 0107 — Visual comparison gate F3
- ADR 0114 — Loop Cowork ↔ Claude Code
- ADR 0127 — Modules/Auditoria UI + undo (mãe)
- SPEC `memory/requisitos/Auditoria/SPEC.md` US-AUDIT-007..010
- BRIEFING `Modules/Auditoria/BRIEFING.md`
