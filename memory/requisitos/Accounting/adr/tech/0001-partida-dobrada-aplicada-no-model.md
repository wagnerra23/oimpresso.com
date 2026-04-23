# ADR TECH-0001 (Accounting) · Partida dobrada aplicada no Model

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Cada lançamento contábil deve ter débito + crédito que somam zero (princípio da partida dobrada). Sem validação, sistema aceita lançamentos desbalanceados — bomba-relógio contábil.

## Decisão

`AccountingAccountTransaction::saving()` model event valida:
```php
$totalDebit  = $this->entries->where('type', 'debit')->sum('amount');
$totalCredit = $this->entries->where('type', 'credit')->sum('amount');
if (abs($totalDebit - $totalCredit) > 0.01) {
    throw new UnbalancedEntryException();
}
```

UI de Journal Entry não permite save até somar zero. Tolerância de R$ 0,01 pra arredondamento.

## Consequências

**Positivas:**
- Impossível corromper contabilidade via UI.
- Import batch valida linha por linha.

**Negativas:**
- Lançamento complexo (ex.: estorno multi-conta) força o usuário a somar na calculadora.

## Alternativas consideradas

- **Trigger MySQL**: funciona, mas erro difícil de apresentar ao usuário.
- **Apenas frontend**: rejeitado — API pública burlaria.
