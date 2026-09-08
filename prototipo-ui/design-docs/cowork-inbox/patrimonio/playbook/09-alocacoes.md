---
sessao: "09"
titulo: Alocações — a fusão de allocation + revocation
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Alocacoes/ · AssetAllocationController · RevokeAllocatedAssetController
nao_toca: _shared/ · AssetController · Services/AssetAllocationService (é da thread 02)
depende: **07** — e atenção: a thread 02 (trava de saldo) toca o Service que estas telas chamam
---
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
