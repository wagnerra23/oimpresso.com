# Runbook · Accounting

## Problema: Journal Entry não salva ("Unbalanced Entry")

**Sintoma**: Usuário tenta salvar lançamento, erro "débito ≠ crédito".

**Causa**: ADR TECH-0001 — partida dobrada é obrigatória.

**Correção**:
- Verifique as entries somam o mesmo (débito total = crédito total).
- Tolerância de R$ 0,01 pra arredondamento — ajuste centavos pra zerar.
- Se valores envolvem cálculo de imposto, separe em duas entries.

## Problema: Saldo de conta não bate com relatório POS

**Sintoma**: Conta "Caixa" em Accounting mostra R$ 5.000 mas POS diz R$ 4.800.

**Causa possível #1**: Bridge `acc_trans_mappings` quebrada — transação POS não virou journal entry.

**Correção**:
```bash
php artisan tinker
>>> \Modules\Accounting\Entities\AccTransMapping::where('transaction_id', X)->exists()
# Se falso, rodar sync
>>> \Modules\Accounting\Services\SyncService::syncTransaction(X)
```

**Causa possível #2**: Estorno no POS não gerou lançamento reverso.

**Correção**: rodar `php artisan accounting:resync --business=1 --from=YYYY-MM-DD`.

## Problema: Import de Chart of Accounts do Excel falha

**Sintoma**: Upload de planilha com plano de contas retorna erro de coluna.

**Causa**: Excel precisa ter colunas exatas: `account_number | name | parent | type`.

**Correção**:
- Baixar template: `/accounting/chart-of-accounts/template`
- Nunca mudar headers da primeira linha.
- `type` deve ser: `asset`, `liability`, `equity`, `revenue`, `expense`.

## Problema: Permissão `accounting.reports.view` não funciona

**Sintoma**: Usuário tem role "gerente" mas recebe 403 em relatórios.

**Causa**: Cache Spatie desatualizado ou role não tem a permission associada.

**Correção**:
```bash
php artisan permission:cache-reset
php artisan tinker
>>> Role::findByName('gerente')->givePermissionTo('accounting.reports.view')
```

## Comandos úteis

```bash
# Re-sync transações POS → Accounting no período
php artisan accounting:resync --business=1 --from=2026-04-01

# Validar balancete
php artisan accounting:validate-balance --business=1

# Audit Accounting
php artisan docvault:audit-module Accounting --save
```
