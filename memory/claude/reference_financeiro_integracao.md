---
name: Financeiro × UltimatePOS — pattern de integração
description: Hooks DataController + Observer Transaction + retro-vínculo transaction_payment + 4 estados na mesma tela; armadilhas timezone/session a checar; status spec-ready com scaffold em Modules/Financeiro
type: reference
originSessionId: dbbb392d-952f-4d8d-9a4a-c93f6603c171
---
Resumo do que precisa lembrar quando trabalhar no módulo Financeiro:

**Status atual (2026-04-24):** spec-ready em `memory/requisitos/Financeiro/` (score audit 85+/100), scaffold criado em `Modules/Financeiro/` (Enabled, vazio), Onda 1 ainda não começou.

## Pattern de integração com core UltimatePOS

1. **Hooks DataController** (`Modules/Financeiro/Http/Controllers/DataController.php`):
   - `user_permissions()` — registra 13+ permissões `financeiro.{area}.{action}` (incluindo `financeiro.dashboard.view`)
   - `modifyAdminMenu()` — injeta sub-menu Financeiro na sidebar admin
   - `superadmin_package()` — Free/Pro R$ 199/Enterprise R$ 599 + take rate 0,5%
   - Usa `\App\Utils\ModuleUtil::moduleData('financeiro', [...])`

2. **Observer em runtime** (no boot do ServiceProvider, NÃO monkey-patch):
   ```php
   \App\Models\Transaction::observe(\Modules\Financeiro\Observers\TransactionObserver::class);
   // created (payment_status=due) → dispatch CriarTituloDeVenda em queue 'financeiro'
   ```

3. **Vínculo bidirecional `transactions ↔ fin_titulos`:**
   - `fin_titulos.origem='venda'`, `fin_titulos.origem_id=transactions.id`
   - `fin_titulo_baixas.transaction_payment_id=transaction_payments.id` (criado retro na baixa)
   - UNIQUE composto `(business_id, origem, origem_id, parcela_numero)` em `fin_titulos` garante idempotência

4. **Idempotência baixa** (`fin_titulo_baixas.idempotency_key` UNIQUE) — frontend manda UUID; retry de webhook = no-op.

5. **Eventos publicados:** `TituloCriado`, `TituloBaixado`, `TituloCancelado`, `BoletoEmitido`, `BoletoPago` — consumidos por NfeBrasil, RecurringBilling, PontoWr2, Officeimpresso.

## Tela unificada (US-FIN-013) — 4 estados juntos

Wagner pediu explicitamente "contas pagas, a pagar, recebida, a receber na mesma tela". Decisão tomada em 2026-04-24:
- Dashboard único `/financeiro` com **KPI grid 4 cards** (A Receber / A Pagar / Recebidos mês / Pagos mês) clicáveis
- Tabela única filtrada por tipo+status, drill-down via click no KPI
- URL state (`?tipo=receber&status=aberto`)
- Server-side aggregation; cache 5 min invalidado por evento
- Mobile: KPIs 2x2, tabela vira cards
- Detalhe ver `Financeiro/adr/ui/0002-dashboard-unificado-4-estados.md`

## Armadilhas críticas (LER antes de codar)

- **`feedback_carbon_timezone_bug.md`** — `format_date()` mantém shift +3h INTENCIONAL; Larissa decorou horários históricos. NÃO resetar.
- **`feedback_format_now_local_e_default_datetime.md`** — pré-popular form com `format_now_local()` (sem shift); `format_date()` é só para dados decorados pelo cliente.
- **`project_session_business_model.md`** — `session('business.time_zone')` retorna null (Eloquent dot-notation falha). Usar `session('business_timezone')`.
- **`feedback_form_shim_bool_attrs.md`** — `disabled=false` no Form shim é OMITIDO automaticamente; checar antes de "fix".
- **`reference_datatables_locale.md`** — toda tabela usa `language: { url: asset('locale/datatables/pt-BR.json') }`.

## Convenção multi-tenant

- `business_id = session('user.business_id')` em **TODAS** as queries
- Trait `Modules\Financeiro\Models\Concerns\BusinessScope` aplica `addGlobalScope`
- Permissão Spatie format `{Nome}#{biz_id}` (ex: `Vendas#4` pra ROTA LIVRE)
- Ver `reference_db_schema.md` + `reference_ultimatepos_integracao.md`

## Relação com módulo Accounting (existente upstream)

ADR `arq/0005`: **paralelo, não substitui, não estende.** Accounting = contabilidade formal (partida dobrada, SPED). Financeiro = operacional (caixa, contas a pagar/receber). Bridge futura opt-in via listener `SyncTituloBaixaToAccounting` quando tenant Pro+ ativar `accounting_sync_enabled`.

## Onda 1 (MVP) — escopo recomendado quando começar implementação

- Migrations: `fin_titulos`, `fin_titulo_baixas`, `fin_caixa_movimentos`, `fin_contas_bancarias`, `fin_categorias`, `fin_planos_conta` (6 tabelas)
- Models com traits `BusinessScope` + `LogsActivity` (Spatie)
- `TransactionObserver` auto-cria título em venda due
- `FinanceiroServiceProvider::boot()` com hooks DataController + 13 permissões Spatie
- Seeder plano de contas BR padrão (47 entries)
- Dashboard endpoint + page (US-FIN-013)
- 1 teste Pest baseline (`MultiTenantIsolationTest` cobrindo 5 rotas)

NÃO incluir em Onda 1: boleto, PIX, OFX, DRE, conciliação — ondas seguintes.

## Decisões pendentes

- Gateway boleto/PIX MVP: Sicoob (banco ROTA LIVRE) ou Asaas (multi-banco)?
- Manter rotas legadas `/contas-receber` e `/contas-pagar` ou redirect 301 pro dashboard?
- Quando ativar bridge `Financeiro → Accounting`?
