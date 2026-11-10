---
id: requisitos-auditoria-spec
slug: modules-auditoria-spec
title: "Modules/Auditoria — SPEC"
type: spec
module: Auditoria
version: "1.1"
last_updated: "2026-11-09"
anchor_format: "v1"
owner: wagner
status: ativo
authority: canonical
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0127-modules-auditoria-undo-activity-log, 0358-doutrina-de-teste-tenant-98-supersede-0101]
pii: false
---

# Modules/Auditoria — SPEC

> Status: **accepted** ([ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md) accepted 2026-05-10). As seções abaixo descrevem o desenho da ADR. Onde desenho e código divergem, vale o [Estado real](#estado-real-medido-em-2026-11-09).

## Estado real (medido em 2026-11-09)

Foto do `main` em `ec3b2fa`. É fato datado: para saber o estado de hoje, rode de novo os comandos da coluna "Como medir".

| Capacidade | Estado | Como medir |
|---|---|---|
| Listar e abrir a trilha, com filtro e sem ver outro business | Entregue | `AuditoriaTelasContratoTest` (UC-AUDI-01..03, UC-AUDD-01..02) |
| Reverter pela rota | Não entregue. `POST /auditoria/{id}/revert` respondia 501 e a tela não tinha botão | `AuditoriaController::revert` · `grep -n revert resources/js/Pages/Auditoria/*.tsx` |
| `RevertService` | Existia e o controller não o chamava | `grep -n RevertService Modules/Auditoria/Http/Controllers/AuditoriaController.php` (só comentário) |
| Bloqueio de irreversíveis | Efetivo em 1 de 5 chaves (`App\Transaction`). `PontoWr2\Models\Marcacao`, `NfeBrasil\Models\NfeTransaction` e `Repair\Models\OS` eram classes inexistentes. `TituloBaixa` existia, mas a condição lia `origem`, coluna ausente de `fin_titulo_baixas` | `ls` dos paths · `database/schema/mysql-schema.sql` |
| Separar usuário de IA | Parcial. `causer_kind=agent` não tinha produtor: dependia do bind `jana.agent_run_id`, que nenhum código de produção fazia | `git grep -n "jana.agent_run_id" -- '*.php' ':!tests'` (só o observer) |
| Permissão `auditoria.view` | Só escondia o item de menu. `index` e `show` não a checavam | `DataController.php` · `AuditoriaController.php` |
| Métricas de saúde desta SPEC | Nenhuma existia. O que existia era `auditoria:health`, sem agendamento | ver [Métricas](#métricas-de-saúde) |
| Notas, export e ação em lote | Sem rota. `AuditNote` e os Requests `BulkRevertActivity`, `ExportAuditEntries`, `StoreAuditNote` e `UpdateAuditNote` só eram usados em testes. `index()` recebia `Request` puro, sem `FilterAuditEntriesRequest` | `git grep -l <classe> -- '*.php'` |
| Expurgo por prazo | Nenhum. `delete_records_older_than_days=365` era só valor de config, e `activitylog:clean` não rodava em lugar nenhum | `git grep -n "activitylog:clean"` (0 ocorrências) |

**O que mudou depois da foto (2026-11-10, `main` em `810bd61`).** O `RevertService` passou a bloquear pelas classes reais: `Marcacao` e `BancoHorasMovimento` do Ponto, os documentos fiscais (`NfeEmissao`, `NfeEvento`, `NfeInutilizacao`, os dois `NfseEmissao`) e `TituloBaixa` sempre, `JobSheet` quando há NFSe ([#10674](https://github.com/wagnerra23/oimpresso.com/pull/10674)). Passou também a conferir a empresa do registro e a negar a segunda reversão ([#10675](https://github.com/wagnerra23/oimpresso.com/pull/10675)), e a negar campo de valor ou estoque ([#10676](https://github.com/wagnerra23/oimpresso.com/pull/10676)). Nessa data a rota ainda respondia 501 e a tela não tinha botão, logo nada disso era alcançável por HTTP.

**Decisão pendente de [W] (D1).** A ADR 0127 prevê reverter dentro de `/auditoria`. A decisão `E-AUDIT-REVERT` (índice do playbook `lote-trio-medida`, delegada em 2026-10-05) diz que a Auditoria é só leitura e que reverter acontece na tela de origem do registro. Esta SPEC registra o conflito e não escolhe.

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

- **`spatie/laravel-activitylog ^4.8`** — já em [composer.json:47](../../../composer.json#L47)
- Tabela `activity_log` — já existe + `business_id` (migrations 2019/2021/2023)
- Padrão de configuração canônico do projeto: [Modules/Financeiro/Models/Titulo.php:28-35](../../../Modules/Financeiro/Models/Titulo.php#L28) — `LogOptions::defaults()->logOnly([...])->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('domain.subdomain')`
- Inertia v3 + React 19 (Pages padrão MWART, [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
- Lib diff JSON: avaliar `react-diff-viewer-continued` na implementação (não decidido)

## US ativas

Backlog de user stories (US-AUDIT-*) organizado em 3 sub-sprints sequenciais.

## US Sprint 1 — Padronização Vestuario + Financeiro

### US-AUDIT-001 · Trait LogsActivity em App\Transaction `done`

> status: done

Trait `LogsActivity` em `App\Transaction` com `logOnly(['status','total_before_tax','final_total','contact_id','location_id','transaction_date','payment_status'])` + `useLogName('sales.transaction')`. Pest test: criar venda → entry em `activity_log` com `event=created` + `properties.attributes` preenchidas. Prio p0 · ~1.5h IA-pair · Dep: —

**Implementado em:** `app/Transaction.php` · `App\Transaction` · verificado@dad0b11 (2026-07-02)

### US-AUDIT-002 · Trait em TransactionSellLine + TransactionPayment `done`

> status: done

Trait em `App\TransactionSellLine` + `App\TransactionPayment` (`logOnly` campos críticos: `quantity`, `unit_price_inc_tax`, `amount`, `method`). Pest: alterar pagamento → log com diff. Prio p0 · ~1h IA-pair · Dep: US-AUDIT-001

**Implementado em:** `app/TransactionSellLine.php` · `app/TransactionPayment.php` · verificado@dad0b11 (2026-07-02)

### US-AUDIT-003 · Trait em Product + VariationLocationDetails `done`

> status: done

Trait em `App\Product` + `App\VariationLocationDetails` (estoque) — `logOnly(['sku','name','sell_price_inc_tax','enable_stock'])` no Product e `logOnly(['qty_available'])` no VLD. Pest: ajuste de estoque → log linha por VLD afetada. Prio p1 · ~1.5h IA-pair · Dep: —

**Implementado em:** `app/Product.php` · `app/VariationLocationDetails.php` · verificado@dad0b11 (2026-07-02)

### US-AUDIT-004 · Trait em App\Contact (sem PII) `done`

> status: done

Trait em `App\Contact` com `logOnly(['name','email','mobile','contact_type','customer_group_id'])` — **`tax_number_1` NÃO entra** (PII LGPD). Pest assert: dump de log não contém CPF/CNPJ. Substitui `activity()->log('add_contact')` manual em [ContactController.php](../../../app/Http/Controllers/ContactController.php). Prio p0 · ~2h IA-pair · Dep: —

**Implementado em:** `app/Contact.php` · `App\Contact` · verificado@dad0b11 (2026-07-02)

**Subtotal Sprint 1 — ~6h IA-pair**

## US Sprint 2 — Causer dual + agent_run_id

### US-AUDIT-005 · Migration causer_kind + revert em activity_log `done`

> status: done

Migration `2026_05_NN_add_causer_kind_and_revert_to_activity_log.php` — adiciona `causer_kind` ENUM, `agent_run_id` BIGINT NULL, `reverted_at` TIMESTAMP NULL, `reverted_by_user_id` BIGINT NULL, `revert_reason` VARCHAR(500) NULL + 2 índices compostos. Reversível. Smoke local: `php artisan migrate` + `migrate:rollback`. Prio p0 · ~1h IA-pair · Dep: —

**Implementado em:** `database/migrations/2026_05_10_160000_add_causer_kind_and_revert_to_activity_log.php` · verificado@dad0b11 (2026-07-02)

### US-AUDIT-006 · Resolver de causer (user/agent/system/api) `parcial`

Resolve contexto: User logado padrão; se request veio de tool MCP `Modules/Jana/Ai/Agents/*` (detect via container binding ou middleware), seta `causer_kind=agent` + `agent_run_id=<id da Jana run>`. Hook em Activity::saving event. Pest: ação Jana grava `agent`; ação Controller grava `user`. Prio p0 · ~2h IA-pair · Dep: US-AUDIT-005. _Nota: entregue como Observer `ActivityCauserKindObserver` (registrado em AppServiceProvider) em vez do `CauserResolver` Service originalmente cogitado — mesmo contrato (saving hook, 4 causer_kind), path abaixo._

**Implementado em:** _parcial_ · `app/Observers/ActivityCauserKindObserver.php` · `App\Providers\AppServiceProvider` · verificado@ec3b2fa (2026-11-09) — o caminho `agent` só existe com o bind `jana.agent_run_id`, que em 2026-11-09 só os testes faziam; depende do módulo Jana

**Subtotal Sprint 2 — ~3h IA-pair**

## US Sprint 3 — Modules/Auditoria UI + Undo

### US-AUDIT-007 · Scaffold Modules/Auditoria `done`

> status: done

Scaffold `Modules/Auditoria/` via skill `criar-modulo` (8 peças obrigatórias + 3 rotas Install + DataController hooks pra sidebar). Sem UI ainda. Smoke: `php artisan module:enable Auditoria`. Prio p0 · ~1h IA-pair · Dep: —

**Implementado em:** `Modules/Auditoria/Providers/AuditoriaServiceProvider.php` · `Modules/Auditoria/Http/Controllers/InstallController.php` · verificado@dad0b11 (2026-07-02)

### US-AUDIT-008 · RevertService com registry UNREVERTIBLE `parcial`

`Modules/Auditoria/Services/RevertService.php` com registry `UNREVERTIBLE` (5 categorias do ADR 0127 §princípio 4). Métodos `canRevert(Activity, User): RevertCheck` + `revert(Activity, User, string $reason): Activity`. Pest test cobrindo: (a) Marcacao bloqueada, (b) Transaction NFe autorizada bloqueada, (c) TituloBaixa Asaas-paid bloqueada, (d) OS com NFSe bloqueada, (e) Transaction com payment posterior bloqueada, (f) revert válido restaura state via `properties.old → fill() + save()` em DB::transaction. Prio p0 · ~4h IA-pair · Dep: US-AUDIT-005, US-AUDIT-006

**Implementado em:** _parcial_ · `Modules/Auditoria/Services/RevertService.php` · `Modules/Auditoria/Tests/Feature/AuditEntryReversibilityTest.php` · `tests/Feature/Auditoria/RevertServiceTest.php` · verificado@810bd61 (2026-11-10) — o serviço existe, mas nenhuma rota o chama (HTTP respondia 501 nessa data; decisão D1 pendente). Até 2026-11-09 os testes só conferiam a forma do registry. Desde [#10674](https://github.com/wagnerra23/oimpresso.com/pull/10674) o `RevertServiceTest` chama `canRevert` e `revert` com linha real nos casos (a) a (f)

### US-AUDIT-009 · Pages Inertia Index + Detail `parcial`

Pages Inertia: `Auditoria/Index.tsx` (filtros: data range, causer_kind, subject_type, event, só-irrevertíveis; tabela paginada DataTable) + `Auditoria/Detail.tsx` (diff old↔new side-by-side, botão Reverter com modal `revert_reason` mín 10 chars). Cada Page com `*.charter.md` ao lado ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3). Gate F1.5 + F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — Wagner aprova screenshot ANTES do PR final. Prio p0 · ~5h IA-pair · Dep: US-AUDIT-007, US-AUDIT-008

**Implementado em:** _parcial_ · `resources/js/Pages/Auditoria/Index.tsx` · `resources/js/Pages/Auditoria/Detail.tsx` · verificado@ec3b2fa (2026-11-09) — os dois charters existem em `draft`. Faltam o botão Reverter com modal (decisão D1) e os filtros de data e só-irrevertíveis: `AuditEntryService` aceita só `causer_kind`, `subject_type` e `event`

### US-AUDIT-010 · AuditoriaController + permissões + redirect 301 `parcial`

`Modules/Auditoria/Http/Controllers/AuditoriaController.php` (index + show + revert) + permissões Spatie (`auditoria.view`, `auditoria.revert.own`, `auditoria.revert.any`, `auditoria.revert.unlimited`) + redirect 301 `/reports/activity-log` → `/auditoria` mantendo querystring. Pest: multi-tenant isolation, 3 níveis permissão, revert ação >24h sem `revert.any` retorna 403. Prio p0 · ~2h IA-pair · Dep: US-AUDIT-009

**Implementado em:** _parcial_ · `Modules/Auditoria/Http/Controllers/AuditoriaController.php` · `Modules/Auditoria/Routes/web.php` · verificado@ec3b2fa (2026-11-09) — `index`, `show` e o redirect 301 entregues. `revert` responde 501 (decisão D1 pendente), logo o 403 por nível de permissão nunca é alcançado pela rota. `auditoria.view` só filtrava o menu

**Subtotal Sprint 3 — ~12h IA-pair**

## Total — ~21h IA-pair (1 sprint pequeno, 3 sub-sprints sequenciais)

## Whitelist UNREVERTIBLE (registry inicial)

Bloco abaixo: desenho da ADR 0127, em 2026-05-10. A lista viva é `RevertService::unrevertibleRegistry()`, e o estado dela está no [Estado real](#estado-real-medido-em-2026-11-09). Desde 2026-11-10 ela usa as classes reais, e os nomes abaixo ficam só como histórico do desenho.

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

## Métricas de saúde

Desenho original, para `jana:health-check` e widget do Admin Center. **Em 2026-11-09 nenhuma das quatro existia no código** (`git grep -l <nome> -- '*.php'`; `pii_leak_in_activity_log` aparecia só no docblock de um teste).

- `activity_log_growth_24h` — alerta se crescer > 50k entries/dia (algo bugado gerando log spam)
- `pct_ia_actions_reverted_30d` — % de ações com `causer_kind=agent` que foram revertidas. Depende do produtor de `agent` e do revert, que não existiam
- `revert_attempts_blocked_24h` — quantos revert tentados foram bloqueados pela whitelist. Depende do revert
- `pii_leak_in_activity_log` — query checando se `properties` JSON contém regex CPF (`\d{3}\.\d{3}\.\d{3}-\d{2}`) ou CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`); deve ser **0**

O que existia em 2026-11-09 são dois comandos manuais, sem agendamento (`git grep -n "auditoria:health"` e `"auditoria:backfill-business-id"` não acham `Kernel` nem `schedule`):

- `php artisan auditoria:health [--alert] [--json]` — 4 sinais: tabela `activity_log`, atividade em 24h, colunas de revert, taxa de revert em 7 dias.
- `php artisan auditoria:backfill-business-id` — preenche `business_id` NULL pelo registro auditado. Sem `--apply`, só dry-run.

## Validação Sprint 1

Revisão de 2026-11-09 das três listas a seguir: ✅ tem teste que prova, 🟡 prova só parte, ❌ não existe, ⬜ sem recibo versionado.

- ✅ Pest: activity de um business não aparece na query de outro (`MultiTenantIsolationTest`, 1 × 99)
- ✅ Pest: CRUD em `App\Transaction` gera entries com `properties.old`/`properties.new` corretas (`TransactionLogsActivityTest`)
- ✅ Pest: `properties` JSON não contém CPF/CNPJ (`ContactPiiLogsActivityTest`, `PiiLeakActivityLogEnforceTest`)
- ⬜ Smoke manual: criar venda e ver a entry em `/auditoria` ou por query direta. O teste automatizado usa o tenant fictício 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md))

## Validação Sprint 2

- 🟡 Pest: `causer_kind=agent` + `agent_run_id` (`CauserKindResolverTest` cenário 1) só com o bind feito pelo próprio teste. Nenhum código de produção faz o bind
- ✅ Pest: ação web sem bind grava `causer_kind=user` (`CauserKindResolverTest` cenário 3)
- ❌ `jana:health-check` com `pct_ia_actions_reverted_30d`: a métrica não existe

## Validação Sprint 3

- ✅ `RevertService::canRevert()` nas 5 categorias: `RevertServiceTest` chama com linha real de cada uma (cenários 3 a 3d, lane `auditoria-pest`, [#10674](https://github.com/wagnerra23/oimpresso.com/pull/10674))
- ✅ Revert válido restaura `properties.old` e cria entry `event=reverted`: `RevertServiceTest` cenários 5c e 5f, no serviço ([#10675](https://github.com/wagnerra23/oimpresso.com/pull/10675)). Pela rota não, que respondia 501 em 2026-11-10
- ❌ 403 sem `auditoria.revert.any` e 403 com só `auditoria.revert.own` em ação > 24h: a rota responde 501 e não há teste dos níveis de permissão
- ✅ Redirect 301 `/reports/activity-log?...` mantém a querystring (UC-AUDI-03). `start_date` e `end_date` ficam na URL, mas `normalizeFilters` os descarta
- ⬜ Gate F1.5 + F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)): sem recibo de aprovação de screenshot. Os dois charters seguem em `draft`

## Riscos e mitigações

Os riscos de revert só valem se a rota for ligada (decisão D1).

| Risco | Mitigação |
|---|---|
| `properties` é TEXT (não JSON nativo) — queries lentas em volume | Filtros UI usam só `subject_type` + `event` + `business_id` (todos indexados). Queries `JSON_EXTRACT` só em diff individual (1 row) |
| `LogsActivity` em Model com campo sensível vaza PII em `properties` | `PiiLeakActivityLogEnforceTest` + `logOnly([...])` enxuto + `$hidden` Model |
| Revert pode quebrar cascading FK | Desenho: `RevertService::revert()` em `DB::transaction` |
| Volume de `activity_log` cresce demais | Sem mitigação automática em 2026-11-09: nada é expurgado por prazo, e num ERP o dado tem dever de guarda (decisão [W] de 2026-07-27, `proibicoes.md` §5) |
| Operador clica Reverter por engano | Desenho: modal com `revert_reason ≥ 10 chars`. O `RevertActivityRequest` valida o mínimo; a tela não tem o botão |
| Revert de ação Jana sem aprovação extra é "fácil demais" | Desenho: métrica `pct_ia_actions_reverted_30d`, que não existe |

## Dependências

- ✅ ADR 0127 accepted (bloqueio)
- ✅ Padrão `LogsActivity` Financeiro/Titulo (referência viva no repo)
- ⏳ ADR 0123 (Modules/Arquivos backbone) — independente; não bloqueia mas fica claro que `arquivos_audit_log` e `activity_log` cobrem escopos distintos
- ⏳ Decisão Pilar 3 LGPD (audit de leitura PII) — Eliana ainda estudando, fora do escopo desta SPEC

## Charter F1.5 (apenas Pages do Sprint 3)

Os charters vivem em `resources/js/Pages/Auditoria/`. Abaixo, a intenção original; reverter na tela depende da decisão D1.

- `Index.charter.md` — Mission: dar visibilidade de TODA alteração em registros de negócio com filtros rápidos. Goals: (1) achar ação suspeita em < 30s, (2) distinguir IA vs humano em 1 clique. Non-goals: análise estatística (BI), auditoria de leitura PII
- `Detail.charter.md` — Mission: explicar UMA alteração com diff inequívoco e permitir reverter quando seguro. Goals: (1) operador entende o que mudou em < 10s, (2) reverter exige confirmação consciente. Non-goals: editar valores diretamente (não é form de edição), redo
