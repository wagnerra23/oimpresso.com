# Auditoria · Financeiro · 2026-04-25 (bugs descobertos pelo integration test)

- **Score**: N/A (audit pontual de bugs, não estrutural)
- **Issues**: 2 critical · 1 warning · 1 info
- **Origem**: integration test `tests/Feature/Modules/Financeiro/TransactionObserverIntegrationTest.php` rodado contra DB dev real revelou que a Onda 1 do MVP entregou Titulo (criação) mas não o ciclo completo de baixa.

## Sumário

| ID | Severidade | Área | Resumo |
|----|----|----|----|
| BUG-1 | 🔴 critical | Baixa automática | `transaction_payment` não cria `TituloBaixa` nem `CaixaMovimento` |
| BUG-2 | 🔴 critical | Baixa automática | Idem BUG-1 para pagamento total |
| BUG-3 | 🟡 warning | Compras | `purchase` não gera Titulo a pagar |
| BUG-4 | ℹ️ info | Lifecycle | `due → paid` marca Titulo como `cancelado` em vez de `quitado` |

---

## 🔴 BUG-1 / BUG-2 — Baixa automática quebrada

### Repro

1. Criar Sell via UltimatePOS com `payment_status='due'` → ✅ `TransactionObserver::created()` dispara `TituloAutoService::sincronizarDeVenda()` → cria `fin_titulos` row (status=`aberto`).
2. Registrar pagamento (parcial OU total) via `transaction_payment` (UltimatePOS standard flow no `/sells/{id}/payment`) → ❌ **nenhuma mudança** em `fin_titulos`, `fin_titulo_baixas` ou `fin_caixa_movimentos`.

### Root cause

- Não existe Observer registrado em `App\TransactionPayment` no `FinanceiroServiceProvider::boot()`.
- `Modules\Financeiro\Services\TituloAutoService` não tem método `registrarPagamento(TransactionPayment $tp)`.
- `Modules\Financeiro\Models\TituloBaixa` não tem coluna `transaction_payment_id` (sem ancoragem pra idempotência da baixa em re-tries de pagamento).

### Impacto

- Cliente paga venda no PDV → caixa do dia não bate (`CaixaMovimento` não é criado).
- Relatório de contas a receber mostra título como aberto perpetuamente (`valor_aberto` não é decrementado).
- Reconciliação manual obrigatória, anulando a proposta de valor "Financeiro automático" da Onda 1.

### Fix sugerido (Onda 2 — ramo `feat/financeiro-onda2-baixa-automatica`)

1. Adicionar coluna `transaction_payment_id` (UNIQUE) em `fin_titulo_baixas` via migration nova.
2. Criar `Modules/Financeiro/Observers/TransactionPaymentObserver.php` com hooks `created/updated/deleted`.
3. Registrar Observer no `FinanceiroServiceProvider::boot()`: `\App\TransactionPayment::observe(\Modules\Financeiro\Observers\TransactionPaymentObserver::class);`.
4. Adicionar métodos no `TituloAutoService`:
   - `registrarPagamento(TransactionPayment $tp): TituloBaixa` — cria `TituloBaixa`, atualiza `valor_aberto` + `status` do Titulo, cria `CaixaMovimento`.
   - `cancelarPagamento(TransactionPayment $tp): void` — usado em `deleted` do payment.
5. Reabilitar cenários 3 e 4 do `TransactionObserverIntegrationTest.php` (remover `->skip(...)`) → devem passar.

### Cobertura de teste após fix

- Cenários 3 e 4 do integration test passando.
- Novo `TransactionPaymentObserverTest.php` cobrindo: pagamentos múltiplos somam, idempotência, deleted estorna, cross-tenant isolation.

---

## 🟡 BUG-3 — Compras ignoradas

### Repro

Criar `App\Transaction` com `type='purchase'` e `payment_status='due'` → nenhum Titulo a pagar é criado.

### Root cause

- `Modules\Financeiro\Services\TituloAutoService::sincronizarDeVenda()` retorna `null` quando `$tx->type !== 'sell'`.
- Existe `Modules\Financeiro\Jobs\CriarTituloDeVendaJob` que cobre purchase (gera numeração `P000001` com `lockForUpdate`), mas está **órfão** — `TransactionObserver::created()` chama o Service direto e nunca dispara o Job.

### Impacto

- Compras de fornecedores não viram contas a pagar automaticamente.
- A tela `/financeiro/contas-pagar` (entregue na Onda 1) só mostra dados se forem inseridos manualmente — o que defeats o "automático".

### Fix sugerido

1. Renomear `TituloAutoService::sincronizarDeVenda()` → `sincronizarDeTransacao()` e suportar `type='purchase'`:
   - `tipo='pagar'`, `numero='P000001'`, `origem='compra'`.
2. Migrar lógica de numeração `R/P000001` (com `lockForUpdate`) do Job pro Service.
3. Após validar, `@deprecated` ou deletar `CriarTituloDeVendaJob.php` (era código órfão).
4. Reabilitar cenário 9 do integration test.

---

## ℹ️ BUG-4 — Lifecycle ambíguo (cosmético)

### Repro

Sell com `payment_status='due'` → Titulo aberto. Mudar `payment_status='paid'` no Transaction → Observer dispara `cancelarSeExistir(motivo: 'venda paga')` → Titulo vira `status='cancelado'`.

### Root cause

`TituloAutoService::sincronizarDeVenda()` aplica `cancelarSeExistir` quando `payment_status` saiu de `['due', 'partial']`. Tecnicamente correto (não há mais título aberto), mas reporting fica confuso: "cancelado" sugere venda anulada, não venda paga.

### Impacto

- Dashboards e relatórios precisam tratar `status='cancelado' AND origem_id REFERENCES transactions WHERE payment_status='paid'` como "quitado" — lógica indireta.
- Histórico não distingue venda cancelada de venda paga rapidamente sem passar por baixa.

### Fix sugerido

Adicionar status `'quitado'` no enum de `fin_titulos.status` e aplicar quando `payment_status` muda pra `paid` sem existirem `transaction_payments` registrados (caminho rápido). Manter `'cancelado'` apenas para Transaction deletada.

Não-bloqueante para Onda 2. Pode ser feito como ajuste cosmético em qualquer onda.

---

## Estado atual da Onda 1 (revisão honesta)

A Onda 1 entregou **5 telas operacionais + criação automática de Titulo de venda + telas de cadastro**, mas **NÃO entregou o ciclo de baixa completo**. A descrição "Onda 1 quase completa" no histórico subestima o gap: o módulo cria títulos mas não consegue baixá-los automaticamente quando o usuário paga.

**Onda 2 deve focar em fechar o ciclo de baixa antes de qualquer expansão de feature** (Caixa projetado, Boleto, etc).

## Referências

- Integration test que descobriu: `tests/Feature/Modules/Financeiro/TransactionObserverIntegrationTest.php` (branch `feat/financeiro-integration-test`)
- Service afetado: `Modules/Financeiro/Services/TituloAutoService.php`
- Observer afetado: `Modules/Financeiro/Observers/TransactionObserver.php`
- Job órfão a remover: `Modules/Financeiro/Jobs/CriarTituloDeVendaJob.php`
- ADR aplicável: TECH-0001 (idempotência) — vai precisar de migration nova pra `transaction_payment_id` em `fin_titulo_baixas`.
