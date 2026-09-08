---
sessao: "01"
titulo: Tenant na subconsulta de revoke — vazamento multi-tenant em produção
dono: "[CL]"
base: cb475c0ca2f4
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: Modules/AssetManagement/Services/AssetAllocationService.php · Modules/AssetManagement/Tests/Feature/CrossTenantAssetTest.php
nao_toca: os 7 controllers · resources/js/** · as outras 3 Services
depende: — (vaga 1) · **primeira da fila: é Tier 0 em produção**
antes:  AssetAllocationService.php:112 sem filtro de business_id na subconsulta AR
depois: subconsulta filtrada + teste que falha sem a correção
---
# 01 · Tenant na subconsulta de revoke

## ÂNCORA (congelada — remedir se o sha mudou)
```
arquivo  Modules/AssetManagement/Services/AssetAllocationService.php   5.054 B  sha bc036e9ed701
símbolo  quantidadeDisponivel(AssetTransaction $allocated): int        :101–:116
ler      SÓ essa faixa + a assinatura de criar() (:31–:39) pra saber quem chama
NÃO ler  AssetController.php (22 KB) · as 17 views Blade · patrimonio-page.jsx
```

## A · O defeito, literal
A consulta externa **filtra** o tenant:
```php
->where('assets.business_id', $allocated->business_id)   // :107
->where('assets.id', $allocated->asset_id)               // :108
```
A subconsulta de revogados, logo abaixo, **não**:
```php
DB::raw('(SELECT SUM(COALESCE(AR.quantity,0)) FROM asset_transactions AS AR
          WHERE (AR.asset_id=assets.id AND AR.transaction_type=\'revoke\')) as revoked_qty')  // :112
```
E o retorno é `allocated_qty - revoked_qty` (`:116`).

**Consequência:** revogações de **outra empresa** sobre um `asset_id` colidente entram na conta. O saldo disponível fica **maior** do que é, e o vazamento é silencioso — não há erro, só número errado. Fere **Tier 0 (ADR 0093)**, que o cabeçalho do próprio arquivo declara cumprir na linha 18.

## B · Não inventar
- **Reusar** o padrão que já está no arquivo: o mesmo `$allocated->business_id` de `:107` — sem parâmetro novo, sem mudar assinatura.
- **Oráculo:** `CrossTenantAssetTest.php` (6.863 B) e `MultiTenantIsolationTest.php` (6.556 B). Ler o formato deles; não reimplementar factory.
- Nada de `whereRaw` novo se um `AND AR.business_id = assets.business_id` resolve dentro do `DB::raw` que já existe.

## Execução
```
ARQUIVOS  AssetAllocationService.php (:112) · CrossTenantAssetTest.php (+1 caso)
PASSO     1) gh pr list --state open × Services/ e Tests/
          2) acrescentar o predicado de tenant na subconsulta AR
          3) TESTE PRIMEIRO: caso que cria revoke no business B sobre asset_id
             colidente de A e prova que quantidadeDisponivel(A) NÃO muda.
             Rodar ANTES da correção — tem que FALHAR. Se passar, a análise
             está errada e a thread PARA.
          4) aplicar; rodar os 9 Pest do módulo
          5) _saida-01.md
DADO      nenhum campo novo; nenhuma migration.
PARAR SE  (a) o teste do passo 3 passar sem a correção → reporte e pare. É o
              PARAR SE que mais importa: significa que existe guarda em outro
              lugar que eu não li.
          (b) a correção mudar o valor em cenário single-tenant → havia
              dependência do bug; vira RESÍDUO pra [W], e NÃO se "ajusta o teste".
```

## Checklist de saída (numerada — o `_saida` marca item a item)
1. predicado de tenant na subconsulta · 2. caso novo em `CrossTenantAssetTest` · 3. o caso falha sem a correção (colar a saída) · 4. os 9 Pest verdes · 5. nenhum arquivo fora do prefixo · 6. placar no corpo do PR (C10)
