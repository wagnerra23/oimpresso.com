---
sessao: "10"
titulo: Manutenções — e o D1 que ainda vive
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Manutencoes/ · AssetMaitenanceController
nao_toca: _shared/ · os outros controllers
depende: **07**
---
# 10 · Manutenções — e o D1 que ainda vive

## ÂNCORA (congelada — remedir se o sha mudou)
```
rota      Route::resource('asset-maintenance')  ->  AssetMaitenanceController   15.724 B  sha b9a20bcbb13c
guardas   :63  :208  :243  :286  :322      <- o D1 vive AQUI
views     views/asset_maintenance/ (3)
proto     patrimonio-page.jsx  aba "Manutenções"
```

## A · O alvo
Esta tela carrega o **D1**, medido e confirmado pela thread 04: as guardas usam
`can('asset.view_all_maintenance') && can('asset.view_own_maintenance')` — **`&&`, exigindo as duas**.
Quem tem só `view_own` (o técnico) é bloqueado das próprias manutenções. São 6 sítios.

Corrigir para `||` é trabalho de backend, **outro intent** — mas migrar a tela sem saber disso
reproduz o bug no React.

## B · Não inventar
- **Não conserte o D1 aqui.** Registre no `_saida` e abra thread própria. Migrar tela e consertar
  autorização no mesmo PR mistura dois riscos.
- `asset.view_own_maintenance` **não existe** na tabela `permissions` de produção (medido: só 5 das 6
  `asset.*` existem). A guarda com `&&` é hoje inalcançável por construção.

## Execução
```
PASSO  1) confirmar 07 mergeada
       2) RUNBOOK + charter + casos
       3) Inertia, PRESERVANDO as guardas como estao (nao corrija o &&)
       4) registrar o D1 no _saida com as 6 linhas
       5) _saida-06d.md
PARAR SE a tela so funcionar corrigindo a guarda -> pare; e o D1, e e outro PR
```

## Checklist de saída
1. 07 mergeada · 2. charter + casos · 3. Inertia · 4. guardas preservadas · 5. D1 registrado com as 6 linhas · 6. 9 Pest verdes · 7. placar
