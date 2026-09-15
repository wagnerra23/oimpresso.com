---
sessao: "09"
titulo: Alocações — a fusão de allocation + revocation
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Alocacoes/ · AssetAllocationController · RevokeAllocatedAssetController
nao_toca: _shared/ · AssetController · Services/AssetAllocationService (é da thread 02)
depende: — (fundação no main); ⚠️ a thread 02 (trava de saldo) toca o Service que estas telas chamam
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

# 09 · Alocações — a fusão de allocation + revocation

## ÂNCORA (congelada — remedir se o sha mudou)
```
rotas     Route::resource('allocation')  ->  AssetAllocationController        13.419 B  sha 0371c2680548
          Route::resource('revocation')  ->  RevokeAllocatedAssetController   10.802 B  sha a97009749359
views     views/asset_allocation/ (3)  +  views/asset_revocation/ (2)
proto     patrimonio-page.jsx  aba "Alocações" — UMA aba para as DUAS rotas
```

## A · O alvo
**O protótipo funde o que o código separa.** Hoje são duas rotas (`allocation` e `revocation`), dois
controllers e cinco views; o desenho mostra **uma** aba com as duas ações. Essa fusão é o trabalho
principal desta thread — não é detalhe de layout.

Decida e **declare no PR**: as duas rotas continuam existindo por trás de uma tela só, ou a devolução
vira ação dentro de Alocações? A segunda opção mexe em rota, então é decisão [W].

## B · Não inventar
- `RevokeAllocatedAssetController:151-153` pega `asset_id` do request **sem `validate()`** — achado da
  thread 01. Não "conserte de passagem": é outro intent, registre.
- A trava de saldo é da **thread 02**. Se ela ainda não mergeou, a tela mostra saldo sem trava —
  declare no PR em vez de implementar a trava aqui.

## Execução
```
PASSO  1) confirmar 07 mergeada
       2) decidir e DECLARAR: 1 tela com 2 rotas, ou fusao de rota (esta e [W])
       3) RUNBOOK + charter + casos cobrindo os DOIS fluxos
       4) Inertia nos dois controllers
       5) _saida-06c.md com a decisao de fusao explicita
PARAR SE a fusao exigir mudar rota -> e decisao [W]; pare e reporte
```

## Checklist de saída
1. 07 mergeada · 2. decisão de fusão declarada · 3. charter + casos dos 2 fluxos · 4. 2 controllers em Inertia · 5. 9 Pest verdes · 6. placar
