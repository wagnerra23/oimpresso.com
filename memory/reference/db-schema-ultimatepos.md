---
name: Estrutura do banco de dados oimpresso (UltimatePOS)
description: Tabelas críticas do schema UltimatePOS. Multi-tenant por business_id. transactions é o core de vendas/compras/despesas. time_zone vive em business, não em location. Usar pra escrever queries e entender relações.
type: reference
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Schema UltimatePOS customizado. **Multi-tenant por `business_id` em todas as tabelas operacionais** — sempre filtrar por `business_id` em queries cross-table pra evitar vazamento entre clientes.

## Tabelas críticas

### `business` — tenant raiz
Colunas importantes: `id`, `name`, **`time_zone`** (string IANA, ex `America/Sao_Paulo`), `date_format`, `time_format` (12/24), `currency_id`, `default_sales_tax`, `fy_start_month`, `common_settings` (JSON), `custom_labels` (JSON), `pos_settings` (JSON), `enable_rp`, `enable_product_expiry`.

### `business_locations` — filiais
Colunas: `id`, `business_id`, `location_id` (código tipo BL0001), `name`, `cnpj`, `razao_social`, `landmark`, `state`, `city`, `zip_code`, `is_active`, `default_payment_accounts` (JSON). **Não tem coluna `time_zone`** — TZ sempre vem de `business.time_zone`.

### `transactions` — core de TUDO operacional
Tipos: `sell`, `purchase`, `sell_return`, `purchase_return`, `expense`, `opening_stock`, `opening_balance`, `production_sell`, `production_purchase`, `payroll`, `stock_adjustment`, `sales_order`, `purchase_order`, `transfer`.

Colunas principais:
- `business_id`, `location_id`, `contact_id`, `type`, `status`, `payment_status`
- `invoice_no`, `ref_no`, `transaction_date` (datetime sem TZ info — "horário de parede"), `final_total`, `total_before_tax`, `tax_amount`, `discount_amount`
- `created_by` (user), `created_at`, `updated_at`
- `sub_type` (pra diferenciar subtipos dentro do mesmo type)

**IMPORTANTE:** `transaction_date` é sempre o horário escolhido pelo operador (pode ser retroativo). `created_at` é o horário real do insert. **Diff entre eles ≠ bug de timezone** — ver `feedback_carbon_timezone_bug.md`.

### `transaction_sell_lines` / `purchase_lines`
Itens da venda/compra. FK em `transaction_id`, `variation_id`, `quantity`, `unit_price`, `line_discount_*`, `item_tax`.

### `transaction_payments`
Pagamentos. `transaction_id`, `amount`, `method` (cash/card/cheque/bank_transfer/other/custom_pay_1..7), **`paid_on`** (datetime), `account_id`.

### `contacts`
Clientes + fornecedores na mesma tabela. `type` ∈ `customer`/`supplier`/`both`. `business_id` obrigatório.

### `products`, `variations`, `variation_location_details`
Estoque por localização fica em `variation_location_details` (um registro por variação × localização). `qty_available` é o saldo.

### `users`
Multi-tenant. `business_id` aponta pro dono. `user_type` ∈ `admin`/`user`/`superadmin`. `status` ∈ `active`/`inactive`. Permissões via Spatie (tables `model_has_roles`, `model_has_permissions`, `roles`, `permissions`).

### `activity_log`
Log de auditoria (tabela da package `spatie/laravel-activitylog`). `log_name`, `subject_type`, `subject_id`, `causer_id`, `properties`, `created_at`. Cresce rápido (~23k rows só pra datas antigas).

### `roles` + `permissions` (Spatie)
Roles nomeados `{NomeRole}#{business_id}` (ex: `Admin#4`, `Vendas#4`, `Caixa#4`). **Role sem permissão `location.{id}` nem `access_all_locations` deixa o user com `permitted_locations() = []`** — trava telas que filtram por location (ver `/sells/create` incidente ROTA LIVRE).

## Convenções importantes

- **Sem foreign keys declaradas** em muitas tabelas — integridade é controlada em app, não DB. Delete manual pode quebrar dados.
- **Soft deletes** em `products`, `contacts`, `business_locations`, `users` via `deleted_at`. Queries devem filtrar `deleted_at IS NULL` ou usar Eloquent.
- **JSON em várias colunas** (`custom_labels`, `pos_settings`, `common_settings`, `default_payment_accounts`). Nunca editar direto em string — sempre decodificar/encodificar.
- **Datetime sem timezone info** — valor armazenado é "horário de parede" conforme `app.timezone` do momento da escrita. Se o app estava em UTC e agora está em SP, leituras podem dar offset. Ver a memória de Carbon timezone.

## How to apply

Antes de escrever uma query cross-business ou tocar `transactions`:
1. `business_id` no WHERE é obrigatório (ou escopo explícito)
2. `type='sell'` se quer só vendas (muitos tipos convivem na mesma tabela)
3. `deleted_at IS NULL` em tabelas com soft delete
4. Pra timezone: confiar em `business.time_zone` e não inferir por location
