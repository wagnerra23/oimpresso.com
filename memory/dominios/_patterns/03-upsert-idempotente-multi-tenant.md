---
id: dominios-patterns-03-upsert-idempotente-multi-tenant
---

# Pattern 03 — UPSERT idempotente per-tenant

**Status**: canônico desde 2026-05-09

## Contexto

Importer pode rodar N vezes contra o mesmo banco legacy (debug, retry após falha, atualização de mapping). Cada run deve ser **seguro re-rodar** — sem duplicação, sem perda.

## Problema

- 50 clientes Delphi diferentes podem ter mesmo `CODIGO=1` em `CONTAS` — não pode colidir entre tenants
- Re-run mesmo cliente: importer encontra `CONTA 1` já importada → atualiza, não duplica
- SQL `INSERT` simples não é idempotente; `INSERT IGNORE` perde diff

## Solução

**UNIQUE composto `(business_id, legacy_source, legacy_id)`** + `INSERT ... ON DUPLICATE KEY UPDATE` (MySQL) ou pre-check via lookup (mais portável).

### Schema

```sql
UNIQUE KEY uq_biz_source_legacy (business_id, legacy_source, legacy_id)
```

3 dimensões garantem:
- `business_id` — tenant isolation Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- `legacy_source` — múltiplos sistemas externos coexistem (Delphi + Bling + Tiny no mesmo cliente)
- `legacy_id` — PK original (string pra acomodar int/uuid/composto)

### Pseudocódigo importer

```python
def upsert(business_id, legacy_source, legacy_id, data):
    existing_id = find_by_legacy(business_id, legacy_source, legacy_id)
    if existing_id is None:
        new_id = INSERT INTO <core> (...) VALUES (...)
        INSERT INTO <core>_legacy_map (business_id, <core>_id, legacy_source, legacy_id, ...)
        stats['inserts'] += 1
    else:
        UPDATE <core> SET ... WHERE id = existing_id
        stats['updates'] += 1
    return existing_id or new_id
```

## Validação smoke

PR [#354](https://github.com/wagnerra23/oimpresso.com/pull/354) — re-run com 3 contas reais:

```
Run 1: 1 inserts, 0 updates, 0 erros (limit=1, CONTA 1)
Run 2: 2 inserts, 1 updates, 0 erros (limit=3 — CONTA 1 vira UPDATE, 2-3 INSERTs)
```

Pest test que valida ([AccountsLegacyMapMultiTenantTest](../../../Modules/Financeiro/Tests/Feature/AccountsLegacyMapMultiTenantTest.php)):
- `test_unique_constraint_permite_mesmo_legacy_id_em_tenants_diferentes` — biz=1 e biz=2 podem ter ambos `legacy_id='1'`
- `test_unique_constraint_bloqueia_duplicidade_no_mesmo_tenant` — biz=1 não pode ter `(wr-comercial-delphi, '1')` duplicado

## Quando NÃO usar

- Tabela de log/audit (cada linha é evento único — UPSERT não faz sentido). Use INSERT-only.
- Importer **one-shot definitivo** sem re-run esperado (raro — quase sempre re-run acontece em algum momento).

## Variantes

- **`INSERT ... ON DUPLICATE KEY UPDATE`** (MySQL nativo) — atomic, mais rápido. Usado em `fin_contas_bancarias` (módulo próprio).
- **Lookup + INSERT/UPDATE separados** — mais portável (PostgreSQL, SQL Server). Usado em `accounts` core porque o INSERT precisa retornar `lastrowid` separado.

Trade-off: nativo é atomic (sem race condition); lookup+separado é mais legível e debugável.
