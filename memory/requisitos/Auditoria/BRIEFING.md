# Modules/Auditoria — BRIEFING

> 1-pager executivo. Estado consolidado do módulo. Atualizado por PR (skill `brief-update`).
> Última atualização: 2026-05-16

## O que é

Módulo de governança que materializa o **Art. 9 da Constituição v2** (auditabilidade total) reusando `spatie/laravel-activitylog` ^4.8 + tabela `activity_log` existente (multi-tenant Tier 0 desde 2019). Padroniza `LogsActivity` em Models críticos (`App\Transaction`, `App\Product`, `App\Contact`, `Modules\Financeiro\Models\Titulo`, etc), distingue **User vs IA Jana vs System vs API** via `causer_kind`, e expõe UI Inertia rica com diff old↔new + botão **Reverter** governado por whitelist de irreversibilidade.

## Por que existe

- **Compliance LGPD/CLT/CONFAZ**: trilha imutável de quem alterou o quê e quando — exigência implícita pra ROTA LIVRE biz=4 (vestuário) e futuros clientes ComunicacaoVisual/OficinaAuto.
- **Confiabilidade Jana**: separar `causer_kind=agent` permite métrica `pct_ia_actions_reverted_30d` — se Jana errar muito, a métrica detecta antes do cliente reclamar.
- **Substitui `/reports/activity-log` legacy**: redirect 301 mantendo querystring (US-AUDIT-010).

## Diferenciais (vs Tiny/Bling/Omie/Conta Azul)

| Capacidade | Concorrentes | oimpresso/Auditoria |
|---|---|---|
| Audit log granular por Model | Parcial (só CRUD bruto) | `LogsActivity` com `logOnly([...])` enxuto + `logOnlyDirty` |
| Distinção User vs IA | ❌ não existe | ✅ `causer_kind` ENUM + `agent_run_id` FK pra Jana run |
| Undo/Revert governado | ❌ apenas soft delete | ✅ `RevertService` + whitelist UNREVERTIBLE (5 categorias) |
| Imutabilidade legal (Portaria 671/2021) | ❌ delete livre | ✅ `Marcacao` UNREVERTIBLE permanente |
| Diff old↔new side-by-side | ❌ texto cru | ✅ Page Inertia `Detail.tsx` (Sprint 3) |

## Componentes canônicos atuais

| Peça | Path | Responsabilidade |
|---|---|---|
| Trait `LogsActivity` em Models | `App\Contact`, `App\Product`, `App\Transaction`, `App\TransactionSellLine`, `App\TransactionPayment`, `Modules\Financeiro\Models\Titulo` etc | Captura `properties.old`/`new` por Model crítico |
| `CauserKindResolver` | `Modules/Auditoria/...` (Sprint 2) | Resolve User/Agent Jana/System/API via Activity::saving event |
| `RevertService` | [`Modules/Auditoria/Services/RevertService.php`](../../../Modules/Auditoria/Services/RevertService.php) | `canRevert()` + `revert()` em DB::transaction; registry UNREVERTIBLE |
| `RevertCheck` | [`Modules/Auditoria/Services/RevertCheck.php`](../../../Modules/Auditoria/Services/RevertCheck.php) | DTO `{allowed, reason}` |
| `AuditEntryService` (NOVO Wave M) | [`Modules/Auditoria/Services/AuditEntryService.php`](../../../Modules/Auditoria/Services/AuditEntryService.php) | Listing/filter (extrai lógica de `AuditoriaController::index/show`) |
| `AuditoriaController` | [`Modules/Auditoria/Http/Controllers/AuditoriaController.php`](../../../Modules/Auditoria/Http/Controllers/AuditoriaController.php) | `index` + `show` + `revert` (delega Service) |
| Pages Inertia | `Modules/Auditoria/Pages/Index.tsx` + `Detail.tsx` (Sprint 3, F1.5+F3 gate) | UI rica filtros + diff |
| Permissões Spatie | `auditoria.view`, `auditoria.revert.own` (≤24h), `auditoria.revert.any` (≤30d), `auditoria.revert.unlimited` | 3 níveis governados |

## Whitelist UNREVERTIBLE (compliance crítica — ZERO touch sem ADR)

1. `Modules\PontoWr2\Models\Marcacao` — Portaria MTP 671/2021 append-only
2. `Modules\NfeBrasil\Models\Transaction` com `cstat IN (100,101,135)` — NFe SEFAZ autorizada/cancelada/inutilizada
3. `Modules\Financeiro\Models\TituloBaixa` com `origem='asaas-paid'` — boleto pago externamente
4. `Modules\Repair\Models\OS` com `nfse_emitida=true` — NFSe prefeitura
5. `App\Transaction` com payment posterior — quebra consistência

Adicionar = PR + comentário. Remover = ADR amendada. Detalhe em [SPEC.md §Whitelist UNREVERTIBLE](SPEC.md).

## Estado atual (2026-05-16)

- ✅ Sprint 1 (US-AUDIT-001..004): traits `LogsActivity` em Models do piloto Vestuario (ROTA LIVRE biz=4)
- ✅ Sprint 2 (US-AUDIT-005..006): migration `causer_kind`/`agent_run_id`/`reverted_*` + `CauserKindResolver`
- ✅ Sprint 3 scaffold (US-AUDIT-007): módulo nWidart com 8 peças + 3 rotas Install
- ✅ `RevertService` + `RevertCheck` (US-AUDIT-008) com whitelist viva
- 🟡 Pages Inertia `Index.tsx`/`Detail.tsx` + charter F1.5+F3 (US-AUDIT-009) — aguarda Wagner aprovar screenshot
- 🟡 Permissões Spatie 3 níveis + redirect 301 legacy (US-AUDIT-010) — em revisão
- 🟢 Wave M: extração `AuditEntryService` pareada com `Modules/Governance/AuditDrillDownService` (Wave H)

## Métricas de saúde (jana:health-check)

- `activity_log_growth_24h` — alerta > 50k entries/dia (log spam)
- `pct_ia_actions_reverted_30d` — % `causer_kind=agent` revertidas (Jana errando?)
- `revert_attempts_blocked_24h` — operador confuso → treinar UX
- `pii_leak_in_activity_log` — regex CPF/CNPJ em `properties` JSON deve ser **0**

## Riscos vivos

- `properties` TEXT (não JSON nativo) — filtros UI só em colunas indexadas (`subject_type`/`event`/`business_id`)
- Volume cresce — `delete_records_older_than_days=365` ativo
- PII leak via `logOnly` mal configurado — Pest `pii_leak_in_activity_log` obrigatório no CI

## Referências canônicas

- [SPEC.md](SPEC.md) — escopo completo (Sprint 1 + 2 + 3, validações)
- [ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md) — decisão arquitetural mãe
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — `business_id` Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 Art. 9
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Pest biz=1 nunca cliente
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — Gate F1.5+F3 Pages
- Padrão referência viva: [`Modules/Financeiro/Models/Titulo.php:28-35`](../../../Modules/Financeiro/Models/Titulo.php#L28)
