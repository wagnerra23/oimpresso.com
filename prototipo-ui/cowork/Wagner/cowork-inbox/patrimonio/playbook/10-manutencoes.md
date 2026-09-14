---
sessao: "10"
titulo: Manutenções — e o D1 que ainda vive
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Manutencoes/ · AssetMaitenanceController
nao_toca: _shared/ · os outros controllers
depende: — (fundação no main); ⚠️ o `AssetMaitenanceController` mudou no PR #7034 — remeça a âncora
---
> ⚠️ **DESTRAVADA em 2026-09-08 [CL] — a fundação existe; `depende: 07` caiu.**
> O `_shared/PatrimonioSubNav.tsx` está no `main` desde o PR
> [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035) (17:42Z), fundado pela tela
> de **Bens** e não pela 07, porque [W] reordenou a frente. Esta thread **importa** o SubNav;
> ele segue `nao_toca`.
>
> **Quatro coisas medidas que você herda — não redescubra:**
> 1. **Escreva o charter ANTES do `.tsx`.** O hook `block-mwart-violation` deriva o RUNBOOK
>    do nome da pasta de `Pages/` (`Patrimonio` ⇒ `memory/requisitos/Patrimonio/`, que não
>    existe **nem deve** — o dono é `AssetManagement/`) e **bloqueia sem override**. A saída
>    prevista pelo próprio hook é `related_runbook:` no charter, apontando pro RUNBOOK real.
> 2. **O SubNav DERIVA as abas** de `shell.menu` (`DataController::modifyAdminMenu`). Não
>    declare lista de abas — seria um segundo dono, que droga no primeiro rename.
> 3. **São 6 ghosts vivos, não as 7 do protótipo:** tem *Devoluções*, não tem
>    *Garantias*/*Auditoria* (decisão ABERTA do [W] — §6, itens 4 e 5).
> 4. **`PAGES_NS` já declara `Patrimonio → AssetManagement`** (`module-surface.mjs`), pelas 7
>    telas de uma vez. Ao acrescentar uma tela, **regenere o derivado**
>    (`node scripts/governance/module-surface.mjs AssetManagement --write`) — o gate roda
>    DOIS modos (`--namespaces --check` e `--all --check`) e o segundo cobra o `SUPERFICIE.md`.
>
> Modelo de referência (charter + casos + teste de contrato, com os UC citados por `it()`):
> `Bens.charter.md` · `Bens.casos.md` · `BensContratoTest.php`. Recibos em
> [`_saida-06-bens.md`](_saida-06-bens.md).

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
