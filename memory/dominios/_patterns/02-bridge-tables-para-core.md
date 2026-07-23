---
id: dominios-patterns-02-bridge-tables-para-core
---

# Pattern 02 — Bridge tables pra core UltimatePOS

**Status**: canônico desde 2026-05-09
**Fonte canon**: [proibições.md](../../proibicoes.md) — "Não modificar tabelas core UltimatePOS sem bridge table"

## Contexto

Importer precisa rastrear origem legacy de registros em tabelas core (`users`, `business`, `accounts`, `employees`, `contacts`, `products`). Padrão "adicionar coluna `legacy_source`/`legacy_id`" em tabela core seria conveniente mas viola Tier 0.

## Problema

- Modificar core UltimatePOS direto = risco de quebrar update de versão futura
- Sem rastreabilidade legacy = importer não pode UPSERT idempotente
- Time precisa convenção uniforme pra qualquer migração futura (Bling, Tiny, etc)

## Solução

**Tabela bridge** `<core>_legacy_map` paralela à tabela core, com FK pra ela. Schema canônico:

```php
Schema::create('<entidade>_legacy_map', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id')->unsigned()->index();
    $table->integer('<core>_id')->unsigned();    // FK pra core
    $table->string('legacy_source', 50);          // 'wr-comercial-delphi', 'bling', 'tiny', ...
    $table->string('legacy_id', 100);             // PK original (string acomoda tipos diversos)
    $table->timestamp('legacy_imported_at')->useCurrent();
    $table->string('legacy_importer_version', 20)->nullable();
    $table->json('legacy_metadata')->nullable();
    $table->timestamps();
    $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
    $table->foreign('<core>_id')->references('id')->on('<core_table>')->onDelete('cascade');
    $table->unique(['business_id', 'legacy_source', 'legacy_id'], 'uq_biz_source_legacy');
    $table->index(['legacy_source', 'business_id'], 'idx_source_biz');
});
```

## Exemplo validado

`accounts_legacy_map` ([migration 2026-05-09](../../../Modules/Financeiro/Database/Migrations/2026_05_09_210000_create_accounts_legacy_map_table.php)) — PR [#353](https://github.com/wagnerra23/oimpresso.com/pull/353).

```sql
INSERT INTO accounts_legacy_map
  (business_id=1, account_id=10, legacy_source='wr-comercial-delphi',
   legacy_id='1', legacy_imported_at=NOW(), legacy_metadata={...})
```

Lookup reverso: dada uma `accounts.id`, achar origem:
```sql
SELECT * FROM accounts_legacy_map WHERE account_id = 10;
```

## Bridges futuras (template)

| Core UltimatePOS | Bridge proposta |
|---|---|
| `accounts` | `accounts_legacy_map` ✅ (existe) |
| `contacts` | `contacts_legacy_map` (clientes/fornecedores Delphi `PESSOAS`) |
| `products` | `products_legacy_map` (`PRODUTO` Delphi) |
| `transactions` | `transactions_legacy_map` (`VENDA`/`BOLETOS` Delphi) |
| `users` | ❌ Nunca — usuários legacy NÃO migram (Convenção 7 WR) |
| `business` | ❌ Nunca — business é tenant key, criado manualmente |

## Quando NÃO usar bridge

- Tabela é de **módulo próprio** (não core UltimatePOS) — adiciona colunas direto. Ex: `fin_contas_bancarias.legacy_source` (módulo Financeiro próprio, não-core).
- Sem necessidade de UPSERT idempotente — registro one-time create-only.

## Riscos mitigados

- **Drift entre core e bridge**: FK ON DELETE CASCADE garante limpeza
- **Vazamento cross-tenant**: `business_id` no UNIQUE composto + global scope no Eloquent Model bridge
- **Update de versão UltimatePOS**: bridge sobrevive — não tocou no core
