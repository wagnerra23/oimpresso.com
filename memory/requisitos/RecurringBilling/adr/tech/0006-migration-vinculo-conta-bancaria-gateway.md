---
id: requisitos-recurring-billing-adr-tech-0006-migration-vinculo-conta-bancaria-gateway
---

# ADR TECH-0006 (RecurringBilling) · Migration — vínculo entre fin_contas_bancarias e rb_boleto_credentials

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

ADR ARQ-0007 definiu que `fin_contas_bancarias` e `rb_boleto_credentials` devem se referenciar mutuamente via FK nullable. Precisamos de migrations para materializar isso sem quebrar o que existe.

## Decisão

**2 migrations adicionais:**

### Migration 1 — adiciona FK em `rb_boleto_credentials`
```php
// Adiciona conta_bancaria_id nullable
$table->unsignedBigInteger('conta_bancaria_id')->nullable()->after('business_id');
$table->foreign('conta_bancaria_id')
      ->references('id')
      ->on('fin_contas_bancarias')
      ->nullOnDelete();
$table->index('conta_bancaria_id', 'rb_boleto_cred_conta_idx');
```

### Migration 2 — adiciona FK em `fin_contas_bancarias`
```php
// Adiciona rb_gateway_credential_id nullable
$table->unsignedBigInteger('rb_gateway_credential_id')->nullable()->after('ativo_para_boleto');
$table->foreign('rb_gateway_credential_id')
      ->references('id')
      ->on('rb_boleto_credentials')
      ->nullOnDelete();
$table->index('rb_gateway_credential_id', 'fin_conta_rb_cred_idx');
```

## Regras de negócio

| Cenário | fin_contas_bancarias | rb_boleto_credentials |
|---|---|---|
| Conta sem cobrança | `ativo_para_boleto=false`, FK=NULL | — não existe |
| Inter com API | `ativo_para_boleto=true`, FK → credential | `conta_bancaria_id` → conta |
| C6 CNAB local | `ativo_para_boleto=true`, FK → credential | `conta_bancaria_id` → conta |
| Asaas gateway puro | — não obrigatório ter conta | `conta_bancaria_id=NULL` |

## Ordem de execução

1. Criar `rb_boleto_credentials` (já feito: 2026_05_06_000001)
2. Criar `fin_contas_bancarias` já existia (2026_04_24_140003) ✓
3. Rodar migration 2026_05_06_000002 (FK em rb_boleto_credentials → fin_contas_bancarias)
4. Rodar migration 2026_05_06_000003 (FK em fin_contas_bancarias → rb_boleto_credentials)

Ordem importa: rb_boleto_credentials deve existir antes de ser referenciada por fin_contas_bancarias.
