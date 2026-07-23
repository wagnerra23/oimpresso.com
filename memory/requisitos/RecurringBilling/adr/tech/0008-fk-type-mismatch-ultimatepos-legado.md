---
id: requisitos-recurring-billing-adr-tech-0008-fk-type-mismatch-ultimatepos-legado
---

# ADR TECH-0008 (RecurringBilling) · FK type-mismatch em tabelas UltimatePOS legadas + migrations idempotentes

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: tech
- **Aplica a**: qualquer migration nova que crie FK pra tabela do core UltimatePOS

## Contexto

Deploy do PR #101 quebrou em produção com:
```
SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'conta_bancaria_id'
```

Causa raiz (descoberta após drop manual e retry):
- `fin_contas_bancarias.id` é `int(10) unsigned` (legado UltimatePOS, era a convenção Laravel pré-5.8 antes de `bigIncrements` virar default)
- Migration declarou `unsignedBigInteger('conta_bancaria_id')` → tipos divergentes
- MySQL `ALTER ADD COLUMN` + `ADD FOREIGN KEY` em statements separados: coluna criou OK, FK falhou silenciosa por type-mismatch
- Coluna ficou órfã (sem FK, sem registro em `migrations` table)
- Próxima retry de `migrate` tentou recriar a coluna → `Duplicate column`
- Loop infinito até intervenção manual

## Decisão

**Regra 1 — sempre verificar tipo da PK do parent antes de criar FK:**

Tabelas do core UltimatePOS (anteriores a Laravel 5.8) usam **`int unsigned`** como PK auto-increment, **não bigint**:

| Tabela legada | Tipo do `id` |
|---|---|
| `users` | `int unsigned` |
| `business` | `int unsigned` |
| `accounts` | `int unsigned` |
| `transactions` | `int unsigned` |
| `contacts` | `int unsigned` |
| `products` | `int unsigned` |
| `business_locations` | `int unsigned` |
| `fin_contas_bancarias` (módulo Financeiro pré-2026) | `int unsigned` |

Tabelas modernas (Laravel 5.8+ default, todos os módulos novos) usam **`bigint unsigned`**:

| Tabela | Tipo do `id` |
|---|---|
| `rb_boleto_credentials` | `bigint unsigned` |
| `rb_invoices` | `bigint unsigned` |
| `nfe_certificados` | `bigint unsigned` |
| `mcp_*` | `bigint unsigned` |

**Antes de criar FK** que aponta pra tabela legada, validar:
```bash
mysql> SHOW COLUMNS FROM <tabela_legada> WHERE Field='id';
```

Se `int(10) unsigned` → usar `$table->unsignedInteger('fk_id')`.
Se `bigint(20) unsigned` → usar `$table->unsignedBigInteger('fk_id')` ou `$table->foreignId('fk_id')`.

**Regra 2 — toda migration que adiciona coluna em tabela existente deve ser idempotente:**

Quando uma migration falha parcialmente em produção (FK silenciosa, charset mismatch, deadlock), MySQL não rolla back DDL. A coluna fica criada mas a migration não é registrada como concluída. Retry quebra com "Duplicate column".

Padrão obrigatório:
```php
public function up(): void
{
    if (Schema::hasColumn('tabela', 'coluna_nova')) {
        return;
    }
    Schema::table('tabela', function (Blueprint $table) {
        // ...
    });
}
```

Para `Schema::create`, usar `Schema::hasTable`. Para `Schema::table` que apenas adiciona índice, usar query em `information_schema.STATISTICS`.

**Regra 3 — runbook de recuperação quando deploy falha por column órfã:**

1. Identificar colunas e migrations parciais (script `_fix_migration.php` em produção):
   - Lista FKs órfãs em `information_schema.KEY_COLUMN_USAGE`
   - Lista índices na coluna em `SHOW INDEX`
2. Drop FK (se houver) → drop índice → drop column
3. Delete registro stale em `migrations` table (caso a migration tenha sido marcada como executada)
4. Re-run `php artisan migrate --force`

## Consequências

- **Positivas:**
  - Migrations seguras pra deploy multi-step (Hostinger pode ser interrompido por timeout)
  - Documenta gotcha do schema legado UltimatePOS pra todo dev novo
  - Recuperação automática de runs parciais sem intervenção SSH

- **Negativas:**
  - Toda nova migration vira mais verbosa (3 linhas de guard)
  - Time precisa lembrar de checar tipo da PK em tabelas legadas — error-prone

## Migrations afetadas (corrigidas no PR #102)

- `Modules/Financeiro/Database/Migrations/2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias.php` — guard `Schema::hasColumn`
- `Modules/Financeiro/Database/Migrations/2026_05_06_000002_add_saldo_cached_to_fin_contas_bancarias.php` — guard
- `Modules/RecurringBilling/Database/Migrations/2026_05_06_000002_add_conta_bancaria_fk_to_rb_boleto_credentials.php` — `unsignedInteger` (não BigInteger) + guard

## Validação

- [x] PR #102 mergeado e migrations rodaram limpo em produção
- [x] `php artisan migrate --force` é idempotente (rerun não quebra)
- [x] FK `rb_boleto_cred_conta_fk` ativa em produção
- [ ] Code review checklist menciona "tipo da PK parent" pra novas FKs

## Referências

- PR #102: fix(migrations): conta_bancaria_id type mismatch + idempotência
- Sessão 2026-05-06 — diagnóstico via `information_schema.COLUMNS`
- UltimatePOS schema baseline — Laravel pré-5.8
