---
slug: modules-auditoria-spec
title: "Modules/Auditoria — SPEC"
type: spec
module: Auditoria
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: ativo
authority: canonical
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0127-modules-auditoria-undo-activity-log, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0156-module-grade-v3-errata-otel-helper-na-justified]
na_justified:
  D5: "Governança transversal cross-tenant — módulo de audit log opera sobre todos businesses (activity_log reusado). Exceção formal ao Tier 0 multi-tenant ([ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md) §SUPERADMIN exception + Constituição v2 Art. 6 [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md))."
pii: false
updated_at: 2026-05-16
---

# Modules/Auditoria — SPEC

> Status: **accepted** ([ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md) accepted 2026-05-10)
> Última atualização: 2026-05-10

## O que é

Módulo de governança que reusa `spatie/laravel-activitylog` (já instalado) + tabela `activity_log` existente (já com `business_id` Tier 0) e adiciona: padronização de `LogsActivity` em Models críticos, distinção User vs IA via `causer_kind`, UI Inertia rica com diff old↔new lado-a-lado, e botão Reverter com whitelist de irreversibilidade. Substitui `/reports/activity-log` legacy.

## Princípios duros (do ADR 0127)

1. Não duplicar tabela — reusar `activity_log`, migrations só ADITIVAS
2. Multi-tenant Tier 0 mantido ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
3. Causer dual (`causer_kind` ENUM `user`/`agent`/`system`/`api` + `agent_run_id`)
4. Whitelist UNREVERTIBLE explícita (5 categorias)
5. 3 níveis permissão (own ≤24h / any ≤30d / unlimited)
6. Revert de ação IA não exige aprovação extra, mas é métrica
7. Cada revert vira nova linha `event=reverted` linkada via `batch_uuid` (audit do audit)

## Stack

- **`spatie/laravel-activitylog ^4.8`** — já em [composer.json:47](../../composer.json#L47)
- Tabela `activity_log` — já existe + `business_id` (migrations 2019/2021/2023)
- Padrão de configuração canônico do projeto: [Modules/Financeiro/Models/Titulo.php:28-35](../../Modules/Financeiro/Models/Titulo.php#L28) — `LogOptions::defaults()->logOnly([...])->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('domain.subdomain')`
- Inertia v3 + React 19 (Pages padrão MWART, [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
- Lib diff JSON: avaliar `react-diff-viewer-continued` na implementação (não decidido)

## US ativas

Backlog de user stories (US-AUDIT-*) organizado em 3 sub-sprints sequenciais.

## US Sprint 1 — Padronização Vestuario + Financeiro

| ID | Descrição | Prio | Esforço (IA-pair) | Dep |
|---|---|---|---|---|
| US-AUDIT-001 | Trait `LogsActivity` em `App\Transaction` com `logOnly(['status','total_before_tax','final_total','contact_id','location_id','transaction_date','payment_status'])` + `useLogName('sales.transaction')`. Pest test: criar venda → entry em `activity_log` com `event=created` + `properties.attributes` preenchidas | p0 | 1.5h | — |
| US-AUDIT-002 | Trait em `App\TransactionSellLine` + `App\TransactionPayment` (`logOnly` campos críticos: `quantity`, `unit_price_inc_tax`, `amount`, `method`). Pest: alterar pagamento → log com diff | p0 | 1h | US-AUDIT-001 |
| US-AUDIT-003 | Trait em `App\Product` + `App\VariationLocationDetails` (estoque) — `logOnly(['sku','name','sell_price_inc_tax','enable_stock'])` no Product e `logOnly(['qty_available'])` no VLD. Pest: ajuste de estoque → log linha por VLD afetada | p1 | 1.5h | — |
| US-AUDIT-004 | Trait em `App\Contact` com `logOnly(['name','email','mobile','contact_type','customer_group_id'])` — **`tax_number_1` NÃO entra** (PII LGPD). Pest assert: dump de log não contém CPF/CNPJ. Substitui `activity()->log('add_contact')` manual em [ContactController.php](../../app/Http/Controllers/ContactController.php) | p0 | 2h | — |

**Subtotal Sprint 1 — ~6h IA-pair**

## US Sprint 2 — Causer dual + agent_run_id

| ID | Descrição | Prio | Esforço (IA-pair) | Dep |
|---|---|---|---|---|
| US-AUDIT-005 | Migration `2026_05_NN_add_causer_kind_and_revert_to_activity_log.php` — adiciona `causer_kind` ENUM, `agent_run_id` BIGINT NULL, `reverted_at` TIMESTAMP NULL, `reverted_by_user_id` BIGINT NULL, `revert_reason` VARCHAR(500) NULL + 2 índices compostos. Reversível. Smoke local: `php artisan migrate` + `migrate:rollback` | p0 | 1h | — |
| US-AUDIT-006 | `Modules/Auditoria/Services/CauserResolver.php` — resolve contexto: User logado padrão; se request veio de tool MCP `Modules/Jana/Ai/Agents/*` (detect via container binding ou middleware), seta `causer_kind=agent` + `agent_run_id=<id da Jana run>`. Hook em Activity::saving event. Pest: ação Jana grava `agent`; ação Controller grava `user` | p0 | 2h | US-AUDIT-005 |

**Subtotal Sprint 2 — ~3h IA-pair**

## US Sprint 3 — Modules/Auditoria UI + Undo

| ID | Descrição | Prio | Esforço (IA-pair) | Dep |
|---|---|---|---|---|
| US-AUDIT-007 | Scaffold `Modules/Auditoria/` via skill `criar-modulo` (8 peças obrigatórias + 3 rotas Install + DataController hooks pra sidebar). Sem UI ainda. Smoke: `php artisan module:enable Auditoria` | p0 | 1h | — |
| US-AUDIT-008 | `Modules/Auditoria/Services/RevertService.php` com registry `UNREVERTIBLE` (5 categorias do ADR 0127 §princípio 4). Métodos `canRevert(Activity, User): RevertCheck` + `revert(Activity, User, string $reason): Activity`. Pest test cobrindo: (a) Marcacao bloqueada, (b) Transaction NFe autorizada bloqueada, (c) TituloBaixa Asaas-paid bloqueada, (d) OS com NFSe bloqueada, (e) Transaction com payment posterior bloqueada, (f) revert válido restaura state via `properties.old → fill() + save()` em DB::transaction | p0 | 4h | US-AUDIT-005, US-AUDIT-006 |
| US-AUDIT-009 | Pages Inertia: `Modules/Auditoria/Pages/Index.tsx` (filtros: data range, causer_kind, subject_type, event, só-irrevertíveis; tabela paginada DataTable) + `Modules/Auditoria/Pages/Detail.tsx` (diff old↔new side-by-side, botão Reverter com modal `revert_reason` mín 10 chars). Cada Page com `*.charter.md` ao lado ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3). Gate F1.5 + F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — Wagner aprova screenshot ANTES do PR final | p0 | 5h | US-AUDIT-007, US-AUDIT-008 |
| US-AUDIT-010 | `Modules/Auditoria/Http/Controllers/AuditoriaController.php` (index + show + revert) + permissões Spatie (`auditoria.view`, `auditoria.revert.own`, `auditoria.revert.any`, `auditoria.revert.unlimited`) + redirect 301 `/reports/activity-log` → `/auditoria` mantendo querystring. Pest: multi-tenant isolation, 3 níveis permissão, revert ação >24h sem `revert.any` retorna 403 | p0 | 2h | US-AUDIT-009 |

**Subtotal Sprint 3 — ~12h IA-pair**

## Total — ~21h IA-pair (1 sprint pequeno, 3 sub-sprints sequenciais)

## Whitelist UNREVERTIBLE (registry inicial)

```php
const UNREVERTIBLE = [
    \Modules\PontoWr2\Models\Marcacao::class => [
        'reason' => 'Portaria MTP 671/2021 — registro de ponto é append-only por força de lei. Use Marcacao::anular() (que cria marcação de anulação, não deleta a original).',
        'condition' => null, // sempre bloqueado
    ],
    \Modules\NfeBrasil\Models\Transaction::class => [
        'reason' => 'NFe autorizada/cancelada/inutilizada na SEFAZ não pode ser revertida via undo. Use fluxo SEFAZ apropriado (cancelamento/inutilização/CC-e).',
        'condition' => fn($model) => in_array($model->cstat, [100, 101, 135]),
    ],
    \Modules\Financeiro\Models\TituloBaixa::class => [
        'reason' => 'Boleto pago externamente (Asaas) — estorno deve ser feito via fluxo Asaas, não via undo de auditoria.',
        'condition' => fn($model) => $model->origem === 'asaas-paid',
    ],
    \Modules\Repair\Models\OS::class => [
        'reason' => 'OS com NFSe emitida não pode ser revertida — cancelar NFSe na prefeitura primeiro.',
        'condition' => fn($model) => $model->nfse_emitida === true,
    ],
    \App\Transaction::class => [
        'reason' => 'Esta transação tem pagamento(s) registrado(s) posteriormente. Reverter quebraria consistência. Estorne os pagamentos primeiro.',
        'condition' => fn($model) => $model->payment_lines()->where('created_at', '>', $logEntry->created_at)->exists(),
    ],
];
```

Adicionar Model novo = PR + comentário no registry. Remover = ADR amendada.

## Cobertura de migração (Models legacy → trait moderno)

Após Sprint 1 fechado, contagem esperada de Models com `LogsActivity` trait:

| Antes (achado 2026-05-10) | Após Sprint 1 |
|---|---|
| 7 Models (Financeiro/4 + Essentials/1 + Accounting/2) | 7 + 5 = **12 Models** (+ Transaction, TransactionSellLine, TransactionPayment, Product, VariationLocationDetails, Contact) |

Sprint 1 cobre 100% dos paths críticos do piloto Vestuario (ROTA LIVRE biz=4) — venda, estoque, cliente, pagamento, financeiro.

Modules legacy ainda em log manual (Repair/Stock/Purchase) migram **opt-in** quando cada módulo for tocado em sprint futuro. Não-disruptivo.

## Métricas de saúde (jana:health-check + Admin Center widget)

- `activity_log_growth_24h` — alerta se crescer > 50k entries/dia (algo bugado gerando log spam)
- `pct_ia_actions_reverted_30d` — % de ações com `causer_kind=agent` que foram revertidas; subir = Jana errando muito
- `revert_attempts_blocked_24h` — quantos revert tentados foram bloqueados pela whitelist; subir = operador confuso, treinar UX
- `pii_leak_in_activity_log` — query checando se `properties` JSON contém regex CPF (`\d{3}\.\d{3}\.\d{3}-\d{2}`) ou CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`); deve ser **0**

## Validação Sprint 1

- ✅ Pest: `Activity::query()->count()` em context biz=1 não retorna activity de biz=4 (Tier 0)
- ✅ Pest: CRUD em `App\Transaction` gera entries com `properties.old`/`properties.new` corretas
- ✅ Pest: `properties` JSON não contém senha/token/CPF/CNPJ
- ✅ Smoke biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)): criar venda → entry visível em `/auditoria` (Sprint 3) ou query direta `SELECT * FROM activity_log ORDER BY id DESC LIMIT 1` (Sprint 1 standalone)

## Validação Sprint 2

- ✅ Pest: ação por tool MCP grava `causer_kind=agent` + `agent_run_id` preenchido
- ✅ Pest: ação por Controller grava `causer_kind=user` + `agent_run_id=NULL`
- ✅ `jana:health-check` aceita nova métrica `pct_ia_actions_reverted_30d`

## Validação Sprint 3

- ✅ Pest: `RevertService::canRevert()` retorna `{allowed:false, reason: <texto da lei/regra>}` pras 5 categorias UNREVERTIBLE
- ✅ Pest: revert válido restaura `properties.old` em `DB::transaction` + cria entry `event=reverted` linkada via `batch_uuid`
- ✅ Pest: 403 sem permissão `auditoria.revert.any` tentando reverter ação alheia
- ✅ Pest: 403 com só `auditoria.revert.own` tentando reverter ação > 24h
- ✅ Smoke: redirect 301 `/reports/activity-log?start_date=X&end_date=Y` → `/auditoria?start_date=X&end_date=Y`
- ✅ Gate F1.5 + F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)): Wagner aprova screenshot Index + Detail antes de merge

## Riscos e mitigações

| Risco | Mitigação |
|---|---|
| `properties` é TEXT (não JSON nativo) — queries lentas em volume | Filtros UI usam só `subject_type` + `event` + `business_id` (todos indexados). Queries `JSON_EXTRACT` só em diff individual (1 row) |
| `LogsActivity` em Model com campo sensível vaza PII em `properties` | Pest test `pii_leak_in_activity_log` obrigatório + `logOnly([...])` enxuto + `$hidden` Model |
| Revert pode quebrar cascading FK | `RevertService::revert()` em `DB::transaction` com try/catch — se falhar, rollback + 422 mensagem específica |
| Volume de `activity_log` cresce demais | `delete_records_older_than_days=365` continua + observabilidade `activity_log_growth_24h` |
| Operador clica Reverter por engano | Modal de confirmação obriga `revert_reason ≥ 10 chars` (não dá pra clicar inadvertidamente) |
| Revert de ação Jana sem aprovação extra é "fácil demais" | Métrica `pct_ia_actions_reverted_30d` — se passar de threshold (5%? definir), reabrir decisão (ADR amendada) |

## Dependências

- ✅ ADR 0127 accepted (bloqueio)
- ✅ Padrão `LogsActivity` Financeiro/Titulo (referência viva no repo)
- ⏳ ADR 0123 (Modules/Arquivos backbone) — independente; não bloqueia mas fica claro que `arquivos_audit_log` e `activity_log` cobrem escopos distintos
- ⏳ Decisão Pilar 3 LGPD (audit de leitura PII) — Eliana ainda estudando, fora do escopo desta SPEC

## Charter F1.5 (apenas Pages do Sprint 3)

- `Modules/Auditoria/Pages/Index.charter.md` — Mission: dar visibilidade de TODA alteração em registros de negócio com filtros rápidos. Goals: (1) achar ação suspeita em < 30s, (2) distinguir IA vs humano em 1 clique. Non-goals: análise estatística (BI), auditoria de leitura PII
- `Modules/Auditoria/Pages/Detail.charter.md` — Mission: explicar UMA alteração com diff inequívoco e permitir reverter quando seguro. Goals: (1) operador entende o que mudou em < 10s, (2) reverter exige confirmação consciente. Non-goals: editar valores diretamente (não é form de edição), redo
