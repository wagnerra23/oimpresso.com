---
slug: deprecation-plan-accounting
title: "DEPRECATION-PLAN — Modules/Accounting (consolidar no Modules/Financeiro)"
status: planejado
owner: wagner
date: 2026-05-20
generated_by: deprecar-modulo (caminho B liberado pelo Wagner)
substituto_canonico: Modules/Financeiro
proibido_neste_doc: commit, abrir PR, criar task MCP, executar migration, alterar SCOPE/BRIEFING/module.json, editar ARQ-0005 ou ARQ-0001
ondas: 7 (Onda 0 + 6 ondas formais)
estimate_total_semanas: 11-13 (fator 10x IA-pair ADR 0106 + margem 2x; etapas humano-limitadas mantêm relógio real)
ref_inspecao: memory/requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md
adr_proposta_principal: memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
adr_proposta_errata: memory/decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md
---

# DEPRECATION-PLAN — Modules/Accounting

## 1. Decisão (1 linha)

Deprecar `Modules/Accounting` em **7 ondas** (Onda 0 + E1-E6, ~11-13 semanas) consolidando contabilidade operacional no `Modules/Financeiro`; SPED ECD/ECF outsourced via Portal Advisor (US-FIN-037, Onda 31, entregue 2026-05-20).

## 2. Sinal canon de aprovação Wagner — 2026-05-20

Resposta literal Wagner pós-inspeção forense (`INSPECAO-FORENSE-2026-05-20.md`):

> "ROTA LIVRE Simples Nacional. Pode fazer tudo isso. Não achei nada que proíba extinguir. Deveria construir tudo no Financeiro. O que a ROTA LIVRE usa são os pagamentos dos clientes, se está pago e quanto o cliente pagou. Acho que até isso vai ter que ir pro Financeiro?"

Interpretação canon: 
- Pré-cond #2 (regime tributário Larissa) resolvida = **Simples Nacional** → sem ECD/ECF.
- Caminho B (deprecar programado) autorizado.
- "Tudo no Financeiro" — Wagner valida consolidação como destino canônico.
- Pergunta final "até isso vai ter que ir pro Financeiro?" é **VERDADE DE CAMPO** verificada na seção 3 (já está no Financeiro hoje).

## 3. VERDADE DE CAMPO — onde Larissa biz=4 vê HOJE "este cliente pagou quanto, está pago?"

**Resposta verificada por código real (não promessa SPEC):** a Larissa vê pagamento em **DUAS camadas que já se conversam automaticamente via Observers Laravel**.

### 3.1 Camada operacional (origem do dado) — Sells UltimatePOS

- **Tela canon migrada Inertia:** [`resources/js/Pages/Sells/Index.tsx`](../../../resources/js/Pages/Sells/Index.tsx) (linhas 46, 58, 94, 115, 193-202, 221, 428-429, 522, 527, 642-644, 723, 732) — coluna `payment_status` (`paid|due|partial`), coluna `total_paid` (numérica) + KPIs "Faturado hoje", "Pendentes pgto.", pills `paga|pendente|atrasado`, groupBy `payment_status`. **Sells legacy Blade** (`resources/views/sell/index.blade.php` + `partials/payment_status.blade.php`) ainda existe paralelo, mas core foi migrado MWART (charter ao lado em `Sells/Index.charter.md`).
- **Schema raw:** `transactions.payment_status` + `transactions.final_total` + `transactions.total_remaining_amount` (UltimatePOS core, tabela `transactions`).

### 3.2 Camada financeira (visão consolidada) — Financeiro

- **Bridge AUTOMÁTICA:** [`Modules/Financeiro/Observers/TransactionObserver.php`](../../../Modules/Financeiro/Observers/TransactionObserver.php) + [`TransactionPaymentObserver.php`](../../../Modules/Financeiro/Observers/TransactionPaymentObserver.php) — registrados no boot do [`FinanceiroServiceProvider.php`](../../../Modules/Financeiro/Providers/FinanceiroServiceProvider.php) linhas 60-61 (`\App\Transaction::observe(...)` + `\App\TransactionPayment::observe(...)`).
- **Fluxo automático real (não promessa):**
  1. Usuário cria/edita Sell em `/sells/create` (Blade ou Inertia) ou paga via TransactionPayment → core grava `transactions.payment_status='due'|'partial'` + `transaction_payments.amount` etc.
  2. `TransactionObserver::created/updated/deleted` dispara → `TituloAutoService::sincronizarDeTransacao($tx)` → cria/atualiza `fin_titulos` (origem='venda', origem_id=tx.id, idempotente via UNIQUE business_id+origem+origem_id+parcela_numero).
  3. `TransactionPaymentObserver::created/updated/deleted` dispara → `TituloAutoService::registrarPagamento($tp)` → cria `fin_titulo_baixas` (idempotency_key=`tp_<id>`) + `fin_caixa_movimentos` (entrada/saida).
  4. `TituloAutoService::recalcularTitulo()` atualiza `valor_aberto` + `status` (aberto/parcial/quitado/cancelado).
- **Schema canon:** [`2026_04_24_140004_create_fin_titulos_table.php`](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140004_create_fin_titulos_table.php) — colunas decisivas: `cliente_id`, `cliente_descricao`, `valor_total`, `valor_aberto`, `status` (enum aberto|parcial|quitado|cancelado), `vencimento`, `competencia_mes`, `origem` (manual|venda|compra|despesa|recurring|folha), `origem_id`. + [`2026_04_24_140005_create_fin_titulo_baixas_table.php`](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140005_create_fin_titulo_baixas_table.php) — `valor_baixa`, `data_baixa`, `meio_pagamento`, `transaction_payment_id`, `estorno_de_id` (append-only TECH-0002).
- **Tela canon Inertia consolidada:** [`resources/js/Pages/Financeiro/Unificado/Index.tsx`](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx) — Cockpit V2, US-FIN-013+US-FIN-020, controlled by [`Modules/Financeiro/Http/Controllers/UnificadoController.php`](../../../Modules/Financeiro/Http/Controllers/UnificadoController.php). Mostra Titulo com `cliente_descricao` + `valor_total` + `valor_aberto` agregados. Também [`resources/js/Pages/Financeiro/ContasReceber/Index.tsx`](../../../resources/js/Pages/Financeiro/ContasReceber/Index.tsx) linhas 20-23, 159, 165 — colunas `cliente_descricao`, `cliente_id`, `valor_total`, `valor_aberto`.

### 3.3 Conclusão BLOCO 1

A pergunta literal do Wagner ("até isso vai ter que ir pro Financeiro?") **JÁ ESTÁ no Financeiro hoje em produção**. Não há gap bloqueador AR-Larissa. O fluxo Sells UltimatePOS → fin_titulos → Unificado/ContasReceber está **operacional, idempotente, append-only, multi-tenant-safe** desde Onda 2 (2026-04-25).

**Onda 0 NÃO é necessária como pré-requisito IRREVOGÁVEL.** Foi degradada de "obrigatória bloqueadora" para "validação smoke" — confirmar em DB de produção que `fin_titulos` da biz=4 tem registros recentes vindos do Observer (não vazios).

---

## 4. Pré-condições atendidas (checklist)

| # | Pré-condição original (inspeção forense seção 9) | Estado 2026-05-20 |
|---|---|---|
| 1 | DB audit produção — top businesses por journal_entries | **Sugestão Wagner roda em Onda 0** (não bloqueia E1 dado o sinal de que Accounting está zumbi) |
| 2 | **Regime tributário Larissa** | ✅ **RESOLVIDA** — Wagner confirmou Simples Nacional 2026-05-20 |
| 3 | Outros clientes em prod com obrigação ECD/ECF | **Sugestão Wagner roda em Onda 0** + Portal Advisor (US-FIN-037) já entregue pra mitigar |
| 4 | Errata ADRs canon (drift `accounting_*` vs nomes nus) | ✅ Arquivo 3 deste plano (ADR proposta 0173) cobre |
| 5 | Validar JournalEntry "transparente" — `manual_entry=0 AND created_at >= '2026-04-01'` | **Sugestão Wagner roda em Onda 0** (esperado: zero, confirma claim BRIEFING falsa) |
| 6 | `accounts_legacy_map` audit | **Sugestão Wagner roda em Onda 0** (esperado: usada por importer Banking Onda Z, não bloqueador) |
| **7** (novo) | **VERDADE DE CAMPO AR Larissa** — onde ela vê pagamento hoje | ✅ **RESOLVIDA** — seção 3 deste doc, Observers + fin_titulos + Unificado/ContasReceber operacionais |

---

## 5. Mapping tabela DB → destino

Schema real (NÃO ADR ARQ-0005 que tem drift):

| # | Tabela | Owner declarado | Linhas (estimar Onda 0) | FK in | FK out | PII | Append-only? | Destino | Justificativa |
|---|---|---|---|---|---|---|---|---|---|
| 1 | `chart_of_accounts` | Accounting | ? | `journal_entries.chart_of_account_id` | self (parent_account_id), `business`, `currencies`, `account_subtypes`, `account_detail_types`, `payment_types` | ❌ | ❌ | **ARCHIVE → view bridge 60d → DROP** | `fin_planos_conta` canon (47 entries DCASP); ZERO uso cross-módulo (inspeção §6.1) |
| 2 | `journal_entries` | Accounting | ? | none external | `chart_of_accounts`, `business_locations`, `contacts`, `payment_details` | 🟡 (notes via PiiRedactor já protegido) | ✅ (Wave J reversal pattern) | **ARCHIVE → DROP após 90d** | Sem equivalente em Financeiro (capacidade 2 inspeção AUSENTE); aceita perda — `<5%` tenants UltimatePOS já usavam (ARQ-0005 linha 33) |
| 3 | `account_subtypes` | Accounting | seed 8 rows | `chart_of_accounts.account_subtype_id` | none | ❌ | ❌ | **ARCHIVE** | Taxonomia GAAP genérica; `fin_planos_conta.natureza` substitui parcial |
| 4 | `account_detail_types` | Accounting | seed 21 rows | `chart_of_accounts.detail_type_id` | none | ❌ | ❌ | **ARCHIVE** | Mesma justificativa subtype |
| 5 | `transfers` | Accounting | ? | none | `business_locations`, `chart_of_accounts` | ❌ | ❌ | **DROP** | Conceito ausente em Financeiro (capacidade 16 PARCIAL); baixa-de-titulo cobre cenário operacional |
| 6 | `budgets` | Accounting | ? | none | `business`, `chart_of_accounts` | ❌ | ❌ | **PRESERVE → MIGRATE Fase 2** | Capacidade 15 PARCIAL DIVERGENTE — Financeiro NÃO tem orçamento; vira `US-FIN-NNN` futuro (Onda 35+); por ora archive snapshot em S3, drop em E6 |
| 7 | `branch_capital` | Accounting | ? | none | `business_locations` | ❌ | ❌ | **DROP** | Capital inicial filial sem uso ativo; ZERO cross-ref |
| 8 | `payment_details` | Accounting | ? | `journal_entries.payment_detail_id` | none | 🟡 (cheque/bank_name redactor) | ❌ | **ARCHIVE** | Detalhe pagamento (cheque/receipt/bank_name); sem equivalente em fin_titulo_baixas (que tem só meio_pagamento enum) |
| 9 | `payment_types` | Accounting (raiz UltimatePOS core também!) | seed | `chart_of_accounts.payment_type_id` | none | ❌ | ❌ | **PRESERVE in-place** | Tabela é compartilhada com UltimatePOS core — NÃO É owner exclusivo do Accounting; manter sem alteração |
| 10 | `countries` | Accounting (raiz UltimatePOS core também!) | seed 250 | múltiplas raiz | none | ❌ | ❌ | **PRESERVE in-place** | UltimatePOS core dep; NÃO touch |
| 11 | `accounts` (não confundir com chart_of_accounts) | UltimatePOS raiz, mas usado pelo Accounting | ? | `account_transactions.account_id`, `transfers`, transactions, `fin_contas_bancarias.account_id` (via `accounts_legacy_map`) | none | ❌ | ❌ | **PRESERVE in-place** | UltimatePOS core canon caixa/banco; `fin_contas_bancarias` mapeia via `accounts_legacy_map` (criado 2026-05-09) |
| 12 | `account_transactions` | UltimatePOS raiz | ? | none | `accounts`, `transactions` | ❌ | ❌ | **PRESERVE in-place** | UltimatePOS core canon movimento caixa; paralelo a `fin_caixa_movimentos` Financeiro (capacidade 23 PARCIAL) |
| 13 | `transactions.journal_entry_id` (col NULLABLE) | UltimatePOS raiz | nullable | none | `journal_entries.id` (set null em drop) | ❌ | ❌ | **DROP col** em E6 (depois de drop `journal_entries`) | Coluna inativa 4 anos — só preenchida via UI manual Accounting; safe drop, mas pode ficar NULL forever (cost zero) |
| 14 | `accounts_legacy_map` | Financeiro (2026-05-09) | ? | nenhuma referida | `accounts`, `fin_contas_bancarias` | ❌ | ❌ | **PRESERVE in-place** | Infra de migração Financeiro; **NÃO é Accounting**; mantém |

**Resumo destinos:**
- **PRESERVE in-place** (sem ação): 4 tabelas (`payment_types`, `countries`, `accounts`, `account_transactions`, `accounts_legacy_map`) — todas UltimatePOS core ou Financeiro
- **ARCHIVE** (mysqldump em `governance/archive/accounting-YYYY-MM-DD.sql.gz` + storage criptografado per LGPD Art. 16 retention 5y): 4 tabelas (`chart_of_accounts`, `journal_entries`, `account_subtypes`, `account_detail_types`, `payment_details`)
- **DROP**: 3 tabelas (`transfers`, `branch_capital`, col `journal_entry_id`)
- **PRESERVE → MIGRATE futuro**: 1 tabela (`budgets` — US-FIN-NNN pra Onda 35+)

---

## 6. Mapping feature → módulo receptor canônico

(24 capacidades da inspeção forense seção 4)

| # | Capacidade | Status no Financeiro | Ação |
|---|---|---|---|
| 1 | Plano de contas hierárquico | ✅ COBERTA | Nenhuma ação — `PlanoContaController` + `fin_planos_conta` 47 entries DCASP |
| 2 | Lançamentos contábeis double-entry | ❌ AUSENTE | **Aceitar perda** — `<5%` tenants UltimatePOS já usavam; SPED outsourced via Portal Advisor |
| 3 | Razão analítico (Ledger contábil) | 🟡 PARCIAL | **Aceitar perda contábil**; Financeiro tem extrato bancário (`ExtratoController`) operacional |
| 4 | Balancete (Trial Balance) | ❌ AUSENTE | **Aceitar perda** — outsource Portal Advisor (contador externo gera no software dele) |
| 5 | DRE Gerencial (4 meses) | 🟡 PARCIAL | Já tem `RelatoriosController::montarDre` US-FIN-014; DRE formal RF outsourced |
| 6 | Balanço Patrimonial | ❌ AUSENTE | **Aceitar perda** — outsource |
| 7 | SPED Contábil ECD/ECF | ❌ AUSENTE | Outsource via Portal Advisor (US-FIN-037 entregue 2026-05-20; Fase 2 entregará export TXT pra Domínio/Sage — US-FIN-NNN futuro) |
| 8 | LALUR | ❌ AUSENTE | **Aceitar perda** — só Lucro Real, raro PME |
| 9 | Conciliação bancária contábil | 🟡 PARCIAL DIFERENTE | Financeiro tem `ConciliacaoController` OFX (superior operacional); contábil outsource |
| 10 | Fechamento mensal/anual | ❌ AUSENTE | **Criar US-FIN-NNN futuro** (low priority — Onda 35+) ou aceitar perda |
| 11 | Centros de custo contábeis | ❌ AUSENTE | **Criar US-FIN-NNN futuro** se cliente pedir (sinal qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)); por ora aceitar |
| 12 | Rateio de despesas | ❌ AUSENTE | **Aceitar perda** — sem sinal de cliente |
| 13 | Reclassificação contas | 🟡 PARCIAL | Aceitar perda — outsource |
| 14 | Encerramento exercício | ❌ AUSENTE | **Aceitar perda** — outsource |
| 15 | Orçamento (Budget) | 🟡 PARCIAL DIVERGENTE | **PRESERVE archive (tabela `budgets`) + criar US-FIN-NNN futuro** (Onda 35+) — feature deve voltar no Financeiro |
| 16 | Transferência entre contas | 🟡 PARCIAL | Aceitar perda do `Transfer` Accounting; baixa cobre cenário operacional |
| 17 | AR/AP Ageing summary+detail | ✅ COBERTA 1:1 | Nenhuma ação — `FinAgeing.tsx` + Unificado |
| 18 | Audit log contábil | ✅ COBERTA | Nenhuma ação — `FinanceiroAuditLogger` + Spatie ActivityLog |
| 19 | Multi-currency | ❌ AUSENTE no Financeiro | **Aceitar perda** — BR-only, irrelevante público alvo |
| 20 | Reports PDF/Excel/CSV | ✅ PARCIAL | Tem CSV (`exportCsv` BOM UTF-8); **criar US-FIN-NNN PDF futuro** se cliente pedir |
| 21 | Integração contador externo | ✅ COBERTA (modelo superior) | Portal Advisor US-FIN-037 entregue 2026-05-20 |
| 22 | AR/AP via UltimatePOS sells/purchases | 🤔 DESNECESSÁRIA | **Descartar** — Unificado já agrega via Observer |
| 23 | Conciliação contábil bancária | 🟡 PARCIAL DIFERENTE | Aceitar perda; OFX operacional cobre uso real |
| 24 | Plano de contas seed por país | 🟡 PARCIAL DIVERGENTE | Nenhuma ação — Financeiro DCASP BR é mais aderente |

**Resumo ações:**
- ✅ **Cobertas (nenhuma ação)**: 5 itens (1, 17, 18, 20-CSV, 21)
- ❌ **Aceitar perda + Portal Advisor mitiga**: 9 itens (2, 4, 6, 7, 8, 14, 19, 12, 13)
- 🟡 **Aceitar perda parcial**: 4 itens (3, 5, 9, 16, 23)
- 🟢 **Criar US-FIN-NNN futuro (Onda 35+)**: 3 itens (10 Fechamento, 11 Centro de custo, 15 Orçamento, 20-PDF)
- 🤔 **Descartar**: 1 item (22 AR/AP via sells redundante)

---

## 7. Roadmap 7 ondas

Estimate: fator 10x IA-pair ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) + margem 2x; etapas humano-limitadas (canary 24h, monitor 30d/60d/90d) mantêm relógio real.

| Onda | Tipo PR | LOC | Pré-req | Gate Wagner | ETA |
|---|---|---|---|---|---|
| **Onda 0** | Audit produção (SQL + smoke real, ZERO PR código) | 0 | Wagner roda 3 SQLs sugeridos + valida fin_titulos biz=4 | Aprovação dos dados retornados | 1d humano |
| **E1** | docs (2 ADRs + plano publicado) | ~250 | Onda 0 aprovada | Promove ADR 0172 + 0173 de `proposals/` pra `accepted` | 1d |
| **E2** | docs/comments PHP (`@deprecated`) | ~80 | E1 mergeado | Review code (Sintaxe, sem mudança comportamento) | 1d |
| **E3** | feat (UI freeze: sidebar + redirects) | ~200 | E2 mergeado + smoke biz=4 | curl -sv 82 rotas + canary biz=4 24h + ROTA LIVRE Larissa avisada 7d antes | 9d (7d aviso + 2d code) |
| **E4** | feat (archive snapshot + view bridge) | ~280 | E3 estável 14d + mysqldump validado | Pest cross-tenant biz=1 vs biz=99 ANTES e DEPOIS + storage criptografado S3 verificado | 16d (14d wait + 2d code) |
| **E5** | chore (drop código PHP + permissions + provider) | ~300 | E4 estável 60d sem incidente | Zero log error apontando `/accounting/*` 60d + Pest "schema preservado" | 62d (60d wait + 2d code) |
| **E6** | chore (drop tabelas DB) | ~150 | E5 estável 90d + Wagner aprovação final | mysqldump 2ª validação + ROTA LIVRE biz=4 smoke prod | 92d (90d wait + 2d code) |
| **Total** | — | **~1260** | — | — | **~184d corridos ≈ 26 semanas (com ondas waits)** |

**Ressalva crítica:** 26 semanas é tempo **CORRIDO** dominado pelos 60d+90d de wait (E5+E6) — período de monitor pra confirmar zero regressão. Tempo de **TRABALHO** ativo: ~18d úteis (~4 semanas) distribuídos.

### Onda 0 — Audit produção (PRE-REQUISITO IRREVOGÁVEL)

**Inputs Wagner:**
```sql
-- Sugestão 1 — quem usa Accounting hoje?
SELECT b.id, b.name, b.business_type,
  (SELECT COUNT(*) FROM chart_of_accounts WHERE business_id = b.id) as coa_count,
  (SELECT COUNT(*) FROM journal_entries je 
    JOIN business_locations bl ON bl.id = je.location_id 
    WHERE bl.business_id = b.id AND je.created_at >= '2026-04-01') as je_recent
FROM business b ORDER BY je_recent DESC LIMIT 20;

-- Sugestão 2 — confirma claim BRIEFING falsa
SELECT COUNT(*) FROM journal_entries 
  WHERE manual_entry = 0 AND created_at >= '2026-04-01';

-- Sugestão 3 — VERDADE DE CAMPO Larissa biz=4
SELECT 
  (SELECT COUNT(*) FROM fin_titulos WHERE business_id = 4 AND origem = 'venda') as titulos_venda,
  (SELECT COUNT(*) FROM fin_titulo_baixas WHERE business_id = 4) as baixas,
  (SELECT MAX(created_at) FROM fin_titulos WHERE business_id = 4) as ultima_criacao;
```

**Critério aprovação Onda 0:**
- 1ª query: ≤2 businesses com `je_recent > 100` → safe pra deprecar (esperado: zero)
- 2ª query: retorna 0 → confirma claim BRIEFING falsa (esperado: zero)
- 3ª query: `titulos_venda > 0 AND ultima_criacao >= NOW() - INTERVAL 7 DAY` → confirma VERDADE DE CAMPO Larissa (Observer ativo gerando)

Se algum critério falhar → STOP, voltar pra inspeção forense, revisitar caminho A (não deprecar).

### Onda E1 — ADRs governance (PR docs)

**Output:** dois ADRs em `memory/decisions/proposals/` promovidos pra `accepted` (Wagner aprova):
1. [`0172-deprecar-modulo-accounting-fundir-financeiro.md`](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md) — ADR principal com `supersedes: [memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md, memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md]`
2. [`0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md`](../../decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md) — errata drift `accounting_*` → nomes nus (rule forward-looking)

**Gate Wagner:** lê draft, valida supersedes, aprova ou pede ajuste. NÃO edita ARQ-0005 nem ARQ-0001 (append-only).

### Onda E2 — Marcações @deprecated PHP (PR docs/comments)

**Output:** PHPDoc `@deprecated since v2026.5.20, use Modules\Financeiro\Models\Titulo instead` em:
- 12 Controllers (`AccountingController`, `AccountingSettingsController`, ..., `ReportController`)
- 10 Services (`AccountingService`, ..., `TrialBalanceService`)
- 8 Entities core contábil (`ChartOfAccount`, `JournalEntry`, `Budget`, `Transfer`, `BranchCapital`, `Account*Subtype/DetailType`, `PaymentDetail`)

**Não muda comportamento.** Phpstan baseline atualizado pra silenciar warnings nas chamadas internas.

### Onda E3 — UI freeze (PR feat)

**Output:**
- `DataController::modifyAdminMenu()` retorna `null` (sidebar entry Accounting desaparece)
- 82 routes `/accounting/*` viram `Route::redirect('/accounting/{any}', '/financeiro/unificado', 301)` (catch-all) + 12 routes `/report/accounting/*` similar
- `subscription package accounting_module` setado `false` em superadmin (Wagner faz manual)
- Pest test `tests/Feature/AccountingDeprecation/RedirectsWorkTest.php` — 94 URLs antigas retornam 301

**Gate Wagner:**
- `gh workflow run smoke-prod-evidence` pra cada uma das 82 URLs (`/accounting/dashboard`, `/accounting/chart_of_account`, ..., `/report/accounting/balance_sheet`)
- Canary biz=4 24h após merge — monitor `storage/logs/laravel.log` + Sentry
- **ROTA LIVRE Larissa avisada 7d ANTES via Wagner manual** ("vou mudar uma coisa interna, te avisarei se quebrar")

**Override emergência:** label PR `deprecation-rollback-approved` permite revert single PR.

### Onda E4 — Archive snapshot + view bridge (PR feat)

**Output:**
- Script `php artisan accounting:archive-tables` (read-only mysqldump):
  - `mysqldump --single-transaction --column-statistics=0 oimpresso chart_of_accounts journal_entries account_subtypes account_detail_types payment_details > governance/archive/accounting-2026-MM-DD.sql`
  - Compressão `gzip` + criptografia AES-256 com chave Vaultwarden (item `oimpresso-archive-key`)
  - Upload S3 bucket `oimpresso-governance-archives` com server-side encryption
- Views legacy MySQL (opcional, low-risk):
  - `CREATE VIEW chart_of_accounts_legacy AS SELECT * FROM chart_of_accounts WHERE business_id IN (SELECT id FROM business)` — pra bookmarks externos eventualmente
- Pest cross-tenant **TIER 0 IRREVOGÁVEL** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):
  - `tests/Feature/AccountingDeprecation/CrossTenantArchiveSafetyTest.php` — biz=1 não vê dados biz=99 nem antes nem depois do archive

**Gate Wagner:**
- Smoke: rodar `accounting:archive-tables` em staging, validar arquivo S3 zipado + chave decifra
- Cross-tenant Pest verde (biz=1 vs biz=99) ANTES e DEPOIS do script
- LGPD audit: PII em `journal_entries.notes/reference` já passa por `Privacy\AccountingAuditLogger` ([Wave 11](../../sessions/2026-05-16-wave-11-accounting-privacy.md)) — dump preserva sanitização? Validar amostragem

### Onda E5 — Drop código PHP (PR chore — APÓS E4 60d estável)

**Output:**
- `git rm -r Modules/Accounting/` (12 Controllers + 10 Services + 70 Entities + 91 Blade views + 21 migrations + 1 Command + config + lang + helpers)
- `bootstrap/providers.php` — remove entry `Modules\Accounting\Providers\AccountingServiceProvider::class`
- `modules_statuses.json` — Accounting `false` (Modules::disable padrão)
- Seeder cleanup: `permissions` table — 11 strings `accounting.*` removidas (Pest test conta rows antes/depois)
- `governance/module-grades-baseline.json` + `module.json` Accounting — remoção entry bucket `functional_horizontal` (ADR 0160)

**Gate Wagner:**
- 60d sem erro em log apontando `/accounting/*` ou namespace `Modules\Accounting\`
- Sentry/log scrape: `grep "Accounting\|/accounting/" storage/logs/laravel-2026-MM-DD.log | wc -l` retorna ≤5 (false positives admissíveis)
- Pest test `tests/Feature/AccountingDeprecation/SchemaPreservedTest.php` — tabelas `chart_of_accounts`, `journal_entries`, etc AINDA EXISTEM (drop é E6 só)

### Onda E6 — Drop tabelas DB (PR chore — APÓS E5 90d estável)

**Output:**
- 1 migration `drop_accounting_tables.php`:
  ```php
  Schema::dropIfExists('journal_entries'); // primeiro filhas
  Schema::dropIfExists('payment_details');
  Schema::dropIfExists('chart_of_accounts'); // depois pais
  Schema::dropIfExists('account_subtypes');
  Schema::dropIfExists('account_detail_types');
  Schema::dropIfExists('transfers');
  Schema::dropIfExists('branch_capital');
  ```
- 1 migration `drop_journal_entry_id_from_transactions.php` — col nullable safe drop (Pt-Online-Schema-Change recomendado se tabela `transactions` >1M rows)
- DROP views legacy criadas em E4 (`chart_of_accounts_legacy`, etc) **OU** mantém indefinido per LGPD retention 5y (decisão Wagner E6 gate)
- Update SCOPE.md `Modules/Accounting/` — `status: deprecated` + `lifecycle: historical` + `link: memory/decisions/0172-...`
- Update BRIEFING.md `Modules/Accounting/` — estado final + log do que aconteceu
- Update `memory/08-handoff.md` — entry nova append-only ([ADR 0167](../../decisions/0167-errata-0130-indice-handoff-historico-longo.md))
- Update `memory/proibicoes.md` — entry nova "NÃO criar features novas em Modules/Accounting deprecated em ADR 0172"

**Gate Wagner:**
- mysqldump 2ª validação (último snapshot antes do drop final irreversível)
- ROTA LIVRE biz=4 smoke prod 24h pós-merge (Wagner avisa Larissa novamente)

---

## 8. Risk register Tier 0 (refinado da inspeção forense + mitigação por onda)

| # | Risco | Sev | Tier 0? | Mitigação | Onda aplicação |
|---|---|---|---|---|---|
| 1 | **Multi-tenant cross-leak** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) IRREVOGÁVEL ao archive/drop tabelas com `business_id` | Crítico | ✅ | Cross-tenant Pest biz=1 vs biz=99 ANTES e DEPOIS de cada migration. Wave 18 D1 já garantiu HasBusinessScope. | E4, E6 |
| 2 | **Append-only ADR** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) p.7) — ADR ARQ-0005 + ARQ-0001 NÃO editáveis | Alto | ✅ | Criar ADR 0172 + 0173 NEW com supersedes; jamais editar existentes. | E1 |
| 3 | **PII em archive SQL dump** (`journal_entries.notes/reference`) — LGPD Art. 7º+16 | Crítico | ✅ | `PiiRedactor` no dump antes de storage S3; AES-256 + chave Vaultwarden separada. Wave 11 privacy audit logger já sanitiza, mas mysqldump bypassa — script archive RE-EXECUTA redact. | E4 |
| 4 | **Audit append-only** ([ADR 0084](../../decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md) padrão) — `journal_entries` Wave J reversal pattern não é DELETE direto; trigger MySQL? | Médio | ⚠️ Validar | Validar se há trigger `BEFORE DELETE` em `journal_entries`. Se sim, `mysqldump` + DROP DROP só APÓS Wagner aprovação manual. | E4-E6 |
| 5 | **SPED compliance** — cliente em prod obrigado ECD/ECF E usando Accounting | Alto | ⚠️ Cond | DB inspection Onda 0 + Portal Advisor (US-FIN-037 entregue) cobre lifecycle outsource. Se ≥1 cliente Lucro Real → bloquear E3 até Portal Advisor entregar export TXT (US-FIN-NNN). | Onda 0, E1 |
| 6 | **Bookmarks admins quebram** — 82 routes `/accounting/*` + 12 `/report/accounting/*` | Médio | ❌ | `Route::redirect 301` catch-all em E3 + `smoke-prod-evidence` skill valida cada URL. | E3 |
| 7 | **Drift `accounting_*` vs nomes nus** ADRs canon (ARQ-0005 linha 14) | Médio | ✅ governance | ADR 0173 errata em E1 (não edita ARQ-0005). | E1 |
| 8 | **ROTA LIVRE Larissa biz=4** UX quebrada sem aviso | Crítico | ✅ | Wagner avisa Larissa 7d ANTES de E3 + canary 24h pós-merge + smoke prod 24h pós-E6. | E3, E6 |
| 9 | **Cliente desconhecido em prod usando Accounting heavy** | Médio | ⚠️ Cond | DB inspection Onda 0 + comunicação Wagner 7d antes E3. | Onda 0 |
| 10 | **AccountingServiceProvider boot fail** se removido sem unregister `bootstrap/providers.php` | Médio | ❌ | E5 remove entry simultaneamente; CI build verde antes merge. | E5 |
| 11 | **Permissions Spatie órfãs** — 11 `accounting.*` rows | Baixo | ❌ | E5 seeder cleanup + Pest count rows antes/depois. | E5 |
| 12 | **AccountingHealthCommand** virou inútil pós-deprecação | Baixo | ❌ | E5 inclui drop comando + schedule (não há schedule). | E5 |
| 13 | **70 entities cópias UltimatePOS core** — drop seguro mas confunde devs | Baixo | ❌ | E5 drop integral; CI test build ok. | E5 |
| 14 | **`accounts_legacy_map`** — infra Financeiro pode ter dependência viva | Médio | ❌ | Onda 0 audit + manter PRESERVE (não é Accounting de fato). | Onda 0 |
| 15 | **`journal_entry_id` col `transactions`** drop full-table lock | Médio | ❌ | Pt-Online-Schema-Change ou GH-OST em E6 se transactions >1M rows; senão deixar NULL forever (cost zero). | E6 |
| 16 | **Webhook externo apontando `/accounting/*`** — Asaas/Inter/Meta/Pluggy? | Baixo | ⚠️ | Audit Wagner manual em painel de cada provider antes E3 (Asaas/Inter callbacks são `/cobranca/*`, não Accounting; Meta e Pluggy idem) — esperado: zero hits. | E3 |

**Bloqueadores Tier 0 ativos:** 1, 2, 3, 7, 8 (5 críticos). Mitigação clara pra todos, gates por onda explícitos abaixo.

---

## 9. Gates Wagner por onda

| Onda | Gate Wagner (critério explícito) |
|---|---|
| **Onda 0** | Aprova passar pra E1 quando: (a) 3 SQLs auditados retornam dados esperados; (b) confirmou pessoalmente Larissa biz=4 vê fin_titulos via Unificado (smoke 5min); (c) JURA não há cliente Lucro Real silencioso (pode ligar 5 maiores businesses em volume) |
| **E1** | Aprova passar pra E2 quando: ADR 0172 + 0173 promovidos `proposals/` → `accepted`, supersedes validados, MERGE PR docs only |
| **E2** | Aprova passar pra E3 quando: PHPDoc `@deprecated` review code OK, phpstan baseline ok, smoke build CI verde |
| **E3** | Aprova passar pra E4 quando: (a) 82+12 URLs retornam 301; (b) canary biz=4 24h sem incidente em log; (c) ROTA LIVRE Larissa explicitamente avisada (mensagem Wagner→Larissa registrada); (d) 14d wait pós-E3 sem incidente |
| **E4** | Aprova passar pra E5 quando: (a) mysqldump archive validado (S3 + chave decifra + tamanho esperado); (b) Pest cross-tenant biz=1 vs biz=99 verde antes E depois; (c) LGPD audit PII no dump validado por amostragem; (d) 60d wait sem incidente |
| **E5** | Aprova passar pra E6 quando: (a) `bootstrap/providers.php` sem Accounting; (b) CI build verde; (c) Sentry/log scrape `/accounting/` ≤5 hits/30d; (d) 90d wait sem incidente; (e) Wagner confirma pessoalmente que NÃO precisa mais |
| **E6** | Aprova merge final quando: (a) 2ª mysqldump (último snapshot pre-drop irreversível) validado; (b) ROTA LIVRE biz=4 smoke prod 24h; (c) Wagner avisa Larissa novamente; (d) SCOPE.md/BRIEFING.md/handoff/proibicoes atualizados |

---

## 10. Critério de rollback

Em qualquer onda, **PARAR + REVERT IMEDIATO** se:

- ❌ **biz=4 ROTA LIVRE** apresenta UX quebrada relatada por Larissa (qualquer tela 500, dado faltando, comportamento estranho)
- ❌ **Sentry/log** mostra >50 errors/24h apontando `Modules\Accounting\` ou `/accounting/*` (sinal de cliente fantasma usando o módulo)
- ❌ **Pest cross-tenant** vermelho em qualquer onda (Tier 0 IRREVOGÁVEL multi-tenant)
- ❌ **mysqldump archive** corrompido ou chave Vaultwarden perdida (E4)
- ❌ **Cliente desconhecido** detectado em DB audit Onda 0 com >1000 `journal_entries` pós-2026-04-01 e regime Lucro Real/Presumido (volta pro caminho A — não deprecar)

**Reversão:**
- E3 revert: PR único `revert: deprecation E3` — restaura sidebar + remove 301s
- E4 revert: PR único + restore mysqldump → `mysql -u root oimpresso < archive.sql.gz` (testado em staging primeiro)
- E5 revert: PR único — `git revert HEAD` em PR único deletion (300 LoC)
- E6 revert: **IRREVERSÍVEL pós-drop tabela.** Por isso E6 só após 90d E5 estável + mysqldump 2ª validado.

---

## 11. Comunicação

| Audiência | Quando avisar | Como | Owner |
|---|---|---|---|
| **Larissa @ ROTA LIVRE biz=4** | 7d antes E3 (UI freeze) + dia E6 (drop final) | Mensagem Wagner→Larissa (WhatsApp) com texto "mudança técnica interna, sem impacto esperado, me avise se algo estranho" | Wagner |
| **Martinho @ Caçambas** | Não aplicável | Martinho não usa Accounting (inspeção forense seção 5) | — |
| **Time MCP entrante** (Felipe/Maiara/Eliana/Luiz) | Antes de E1 | Slack/email — "ADR 0172 proposta deprecar Accounting; revisem ARQ-0005 que será superseded; tokens MCP scoped não afetados (zero permissões `accounting.*` no time)" | Wagner |
| **Cliente desconhecido em prod** (se Onda 0 detectar) | Antes de E3 | Ligação Wagner — "vocês usam Accounting? como?" | Wagner |
| **Webhook externos** (Asaas/Inter/Meta/Pluggy) | Antes de E3 | Audit painel cada provider — esperado: zero callbacks `/accounting/*` | Wagner |
| **Bookmark admins internos** (Wagner/Office Impresso) | Antes E3 | Lista de URLs com novo destino (`/accounting/balance_sheet` → `/financeiro/unificado`); auto-cleanup browser bookmarks | Wagner |

---

## 12. Métricas de sucesso

| Métrica | Alvo | Como medir |
|---|---|---|
| **Zero rota 500 `/accounting/*`** pós-E3 | 0 hits/30d | `grep "/accounting/" storage/logs/laravel-*.log` |
| **Zero novo registro `journal_entries`** pós-E3 (cutoff date) | 0 inserts | `SELECT COUNT(*) FROM journal_entries WHERE created_at >= 'E3_DATE'` |
| **Cross-tenant Pest** verde antes E depois cada onda | 100% | CI Pest job |
| **Sentry alerts `Modules\Accounting`** pós-E5 | 0 alerts/60d | Sentry dashboard |
| **ROTA LIVRE Larissa biz=4 incidents** pós-E3/E6 | 0 incidents | WhatsApp + Sentry biz=4 filter |
| **Archive S3 integridade** | gzip + AES-256 decifra | `gpg --decrypt accounting-archive.sql.gz.gpg \| mysql -u test...` em staging |
| **CI build verde** pós cada onda | 100% | GitHub Actions |
| **Module count antes/depois** | 1 a menos (Modules/Accounting removido) | `ls Modules/ \| wc -l` |
| **Routes count antes/depois** | -94 rotas (82+12) | `php artisan route:list --json \| jq ...` |
| **Permissions count antes/depois** | -11 strings `accounting.*` | `SELECT COUNT(*) FROM permissions WHERE name LIKE 'accounting.%'` |

---

## 13. Próxima ação Wagner (UMA)

> **Rodar as 3 sugestões SQL da Onda 0** (seção 7) em produção, colar os resultados em `memory/requisitos/Accounting/ONDA-0-AUDIT-RESULT-2026-MM-DD.md` (criar novo arquivo append-only), e confirmar visualmente que Larissa biz=4 vê fin_titulos no Unificado (`/financeiro/unificado`).

Com esse retorno (~30min de Wagner), libera-se passar pra E1 (promover as 2 ADRs em `proposals/` pra `accepted`).

---

## Refs

- [memory/requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md](./INSPECAO-FORENSE-2026-05-20.md) — inspeção base (505 linhas, 24 capacidades mapeadas)
- [memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md) — ADR principal (este plano referencia)
- [memory/decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md](../../decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md) — errata drift
- [memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md](../Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) — ARQ-0005 (será superseded)
- [memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md](./adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md) — ARQ-0001 (será superseded)
- [Modules/Financeiro/Observers/TransactionObserver.php](../../../Modules/Financeiro/Observers/TransactionObserver.php) — bridge AR auto (verdade de campo Larissa)
- [Modules/Financeiro/Observers/TransactionPaymentObserver.php](../../../Modules/Financeiro/Observers/TransactionPaymentObserver.php) — bridge baixa auto
- [Modules/Financeiro/Services/TituloAutoService.php](../../../Modules/Financeiro/Services/TituloAutoService.php) — orquestrador idempotente
- [Modules/Financeiro/Database/Migrations/2026_04_24_140004_create_fin_titulos_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140004_create_fin_titulos_table.php) — schema canon AR/AP
- [Modules/Financeiro/Http/Controllers/UnificadoController.php](../../../Modules/Financeiro/Http/Controllers/UnificadoController.php) — Cockpit V2 onde Larissa vê
- [Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php](../../../Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php) — Portal contador externo (mitigação SPED long-term)
- [resources/js/Pages/Financeiro/Unificado/Index.tsx](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx) — tela canon (Cowork-aprovada)
- [resources/js/Pages/Sells/Index.tsx](../../../resources/js/Pages/Sells/Index.tsx) — payment_status + total_paid (origem do dado)
- [ADR 0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0105 Cliente como sinal](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0106 Recalibração fator 10x IA-pair](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0167 Append-only handoff](../../decisions/0167-errata-0130-indice-handoff-historico-longo.md)
- [ADR 0170 PaymentGateway Cobranca](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md)
- [memory/proibicoes.md](../../proibicoes.md)
- [memory/reference/cliente-rotalivre.md](../../reference/cliente-rotalivre.md) — Larissa biz=4 (Simples Nacional confirmado Wagner 2026-05-20)

---

## ERRATA 2026-05-20 — Audit prod confirma ZERO dados; Ondas 3+4 SKIP (ADR 0174)

> **Append-only — não edita seções acima** (ADR 0094 princípio 7). Apenas adiciona nova realidade descoberta pelo audit empírico do mesmo dia.

### Status real do plano (2026-05-20 tarde, pós-PRs #1244 #1246 + audit MySQL Hostinger)

| Onda | Item original do plano | Estado real |
|---|---|---|
| **Onda 0** | Audit produção (3 SQLs + verdade de campo Larissa) | ✅ **DONE** — [session log audit](../../sessions/2026-05-20-audit-accounting-prod-zero-rows.md) confirma 6 tabelas core com ZERO rows |
| **E1** | Promover ADR 0172 + 0173 `proposals/` → `accepted` | ✅ **DONE** — commit `2bd2bedcb` (PR #1234) mergeado |
| **— (não no plano)** | Errata BRIEFING (US-ACCO-011, banner deprecação + 2 ERRATA refutando claims falsos) | ✅ **DONE** — PR #1244 mergeado `eef793ffe` |
| **E2** | PHPDoc `@deprecated` Controllers/Services/Entities | ⏭️ **SKIP simplificado** — pulado pra E3 direto (ROI baixo de PHPDoc num código que vai sair em 30d) |
| **E3** | UI freeze: sidebar oculta + routes 410 + Pest | ✅ **DONE** — PR #1246 mergeado `d88bf9e1e`, smoke prod 410 LIVE validado via curl |
| **~~Onda 3~~** (DEPREC-ACC-005 migration script) | ~~Migrar dados `accounts_legacy_map` → `fin_*`~~ | ❌ **SKIP** (ADR 0174) — origem vazia (0 rows); `accounts_legacy_map` JÁ é Financeiro infra |
| **~~Onda 4 (E4)~~** (DEPREC-ACC-006 view bridge) | ~~Bridge view `accounting_*` → `fin_*` 60d rollback window~~ | ❌ **SKIP** (ADR 0174) — zero código fora de Modules/Accounting consulta essas tabelas (inspeção §6); bridge sem leitor |
| **Canary 30d** | Monitor logs `/accounting/*` em prod | ⏳ **iniciado 2026-05-20 17:44 UTC** — Onda 5 destrava em 2026-06-19 (`d88bf9e1e` + 30d) |
| **Onda 5 (E5)** | `git rm Modules/Accounting/` + `modules_statuses=false` + cleanup permissions seeder + drop entry providers.php | ⏳ pending canary 30d |
| **Onda 6 (E6)** | DROP TABLE 6 vazias + ARCHIVE 2 seed (account_subtypes/account_detail_types) | ⏳ pending Onda 5 + 90d wait |

### Achados do audit que motivam SKIP

```
+-----------------------------------------+------------+
| tabela                                  | rows_total |
+-----------------------------------------+------------+
| chart_of_accounts                       |          0 |
| journal_entries                         |          0 |
| budgets                                 |          0 |
| transfers                               |          0 |
| payment_details                         |          0 |
| branch_capital                          |          0 |
| account_subtypes                        |         15 |   ← seed GAAP
| account_detail_types                    |        139 |   ← seed GAAP
| accounts_legacy_map                     |         19 |   ← Financeiro infra (biz=1 wr-comercial-delphi)
| accounts (UltimatePOS core)             |         26 |   ← PRESERVE
| account_transactions (UltimatePOS core) |      11884 |   ← PRESERVE (biz=4: 11.862)
+-----------------------------------------+------------+
```

Mais: **zero subscriptions ativas em prod com `accounting_module` no `package_details` JSON.** Match perfeito com inspeção forense §1 ("claim espinha dorsal = falso").

### Subscriptions (estado das 3 principais)

- **sub#118 biz=1 Wagner WR2** — UPDATE aplicado 2026-05-20 17:56 UTC: `JSON_SET(package_details, '$.financeiro_module', '1')`. Wagner agora vê Financeiro na sidebar dele.
- **sub#153 biz=4 Larissa ROTALIVRE** — já tinha `financeiro_module:"1"` desde sub creation.
- **sub#116 biz=164 Martinho** — package_details snapshot legacy só tem connector+manufacturing+project. Sem `financeiro_module` (embora package#11 entity catalog tenha). Aguarda decisão Wagner se libera.

### Cronograma compressed

- 2026-05-20: Ondas 0-2 done (1 dia útil real)
- 2026-05-20 → 2026-06-19: **canary 30d** (humano-limitado wait, monitor logs `/accounting/*`)
- 2026-06-19 → 2026-06-21: **Onda 5** (~2d trabalho — `git rm` + cleanup seeder + provider)
- 2026-06-21 → 2026-09-19: **wait 90d** Onda 5 estável
- 2026-09-19 → 2026-09-21: **Onda 6** (~2d trabalho — DROP TABLE + ARCHIVE mysqldump 5y retention)

**Total trabalho ativo: ~5d úteis** (vs ~18d planejados originalmente — -72%).
**Total tempo corrido: ~17-18 semanas** (vs ~26 semanas planejadas — -33%).

### Critérios de reverter SKIP durante canary

Detalhados em [ADR 0174 §Critérios pra reverter SKIP](../../decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md#critérios-pra-reverter-skip). Critério único objetivo: dados aparecendo em `chart_of_accounts`/`journal_entries` durante canary (não esperado).

### Ações pendentes Wagner

1. **Validar sidebar biz=1** (logout+login) — entry "Financeiro" deve aparecer pós UPDATE sub#118
2. **biz=164 Martinho libera `financeiro_module:"1"`?** — decisão pendente
3. **Rotacionar senha MySQL `u906587222_oimpresso`** (exposta no contexto Claude via tailscale ssh + grep) — hPanel + Vaultwarden + atualizar `/opt/whatsapp-baileys/build/.env` CT 100 + `.env` Hostinger
4. **Acompanhar canary 30d** — re-rodar audit semanalmente (suficiente: query "0 rows nas 6 tabelas"). Skill `loop` automatizável.

### Refs adicionais

- [ADR 0174](../../decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md) — esta errata canon
- [Session log audit](../../sessions/2026-05-20-audit-accounting-prod-zero-rows.md) — raw output das queries
