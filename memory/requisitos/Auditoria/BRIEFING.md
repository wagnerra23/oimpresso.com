---
module: Auditoria
status: governança transversal cross-tenant (sprint 3 em curso)
piloto: N/A (governança transversal — todos businesses sem cliente externo direto)
last_review: 2026-05-16
owner: wagner
parent_adr: 0127
related_adrs: [0093, 0094, 0101, 0107, 0127, 0153, 0155, 0156]
nota_atual_v2: "~55/100 (injusto — D5 penaliza ausência de cliente externo)"
nota_esperada_v3: "~75-85/100 pós-PR4 na_justified D5 declarado"
na_justified: [D5]
---

# Modules/Auditoria — BRIEFING

> 1-pager executivo. Estado consolidado do módulo. Atualizado por PR (skill `brief-update`).
> Última atualização: 2026-05-16 (Wave 5 re-try — `na_justified` D5 declarado no SPEC pareado, rubrica v3 [ADR 0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md))

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

## Score module-grade (v3 pós-PR4)

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR4) | ~55/100 | Penalizava D5 (cliente externo) — `activity_log` é fundação transversal sem cliente direto |
| **v3 (pós-PR4)** | **~75-85/100** (esperado) | `na_justified` D5 declarado no SPEC → rubrica v3 redistribui peso (ADR 0156) |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** governança transversal cross-tenant — todos businesses consomem `activity_log` table compartilhada, mas não há cliente externo único piloto. Larissa biz=4 usa indiretamente via reverse de transactions, sem UI dedicada.

## Estado atual (2026-05-16)

- ✅ Sprint 1 (US-AUDIT-001..004): traits `LogsActivity` em Models do piloto Vestuario (ROTA LIVRE biz=4)
- ✅ Sprint 2 (US-AUDIT-005..006): migration `causer_kind`/`agent_run_id`/`reverted_*` + `CauserKindResolver`
- ✅ Sprint 3 scaffold (US-AUDIT-007): módulo nWidart com 8 peças + 3 rotas Install
- ✅ `RevertService` + `RevertCheck` (US-AUDIT-008) com whitelist viva
- 🟡 Pages Inertia `Index.tsx`/`Detail.tsx` + charter F1.5+F3 (US-AUDIT-009) — gate visual = **CI** (visual-regression + PR UI Judge), **não** aprovação síncrona de screenshot (v2 · [ADR 0241](../../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md)/[0282](../../decisions/0282-protocolo-v2-colapso-ratificacao.md), [PROTOCOL §0.1](../../../prototipo-ui/PROTOCOL.md))
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
- [ADR 0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) — Rubrica v3 `na_justified`
- [ADR 0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md) — Pesos redistribuídos v3
- Padrão referência viva: [`Modules/Financeiro/Models/Titulo.php:28-35`](../../../Modules/Financeiro/Models/Titulo.php#L28)
