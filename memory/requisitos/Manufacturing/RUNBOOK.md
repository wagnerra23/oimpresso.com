# Runbook · Manufacturing

## Problema: Production order falha "Insufficient stock"

**Sintoma**: Operador inicia ordem, erro diz que falta insumo.

**Causa**: Estoque real < quantidade demandada pela recipe.

**Correção**:
1. Verificar se há transferência pendente de outra localização.
2. Se demanda for futura, criar ordem de compra antes.
3. Se insumo está disponível mas não aparece: `php artisan stock:recalculate --business=1`.

## Problema: Recipe com dependência circular

**Sintoma**: Tenta salvar recipe e recebe erro "circular dependency detected".

**Causa**: Produto A tem recipe que usa B, que tem recipe que usa A.

**Correção**:
- Revisar qual produto é "raiz" (produto final) vs sub-produto.
- Se realmente precisa (caso raro), quebrar em 2 recipes com nomes distintos.

## Problema: Custo de produto final diverge do esperado

**Sintoma**: Recipe soma R$ 10 de insumos, produto custa R$ 15.

**Causa**: Custos de mão de obra ou overhead aplicados automaticamente.

**Correção**: ver em `manufacturing_recipes.overhead_percent` e ajustar.

## Problema: MRP não gera ordens de compra

**Sintoma**: Rodou `manufacturing:mrp-calculate` mas nada aparece em ordens.

**Causa**: Pode não ter demanda (previsão de vendas) cadastrada no período.

**Correção**:
```bash
php artisan tinker
>>> \Modules\Manufacturing\Entities\SalesForecast::where('business_id', 1)
    ->where('period_start', '>=', now())->get()
# Se vazio, cadastrar previsão antes.
```

## Comandos úteis

```bash
# Rodar MRP pro próximo mês
php artisan manufacturing:mrp-calculate --business=1 --from=2026-05-01 --to=2026-05-31

# Recalcular custos em cascata
php artisan manufacturing:recalculate-costs --business=1

# Audit Manufacturing
php artisan docvault:audit-module Manufacturing --save
```
