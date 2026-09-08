---
sessao: "08"
titulo: Bens — o CRUD principal
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Bens/ · AssetController (index/create/edit/show)
nao_toca: _shared/ (é da 06a) · as outras telas
depende: **07** (importa o `PatrimonioSubNav` que ela cria)
---
# 08 · Bens — o CRUD principal

## ÂNCORA (congelada — remedir se o sha mudou)
```
rota      Route::resource('assets')  ->  AssetController   :73-:100 (index)  :271-:277 (create)
arquivo   Modules/AssetManagement/Http/Controllers/AssetController.php   24.416 B  sha 3eba5a4faae5
views     Modules/AssetManagement/Resources/views/asset/   (4 arquivos)
proto     patrimonio-page.jsx  aba "Bens"  (11 itens no mock)
```

## A · O alvo
A tela mais usada do módulo. O `index()` ganhou a guarda `can('asset.view')` no PR #7008 —
**preserve-a**: converter para Inertia sem reaplicar a guarda reabre o buraco que acabou de fechar.

## B · Não inventar
- O vocabulário está em disputa: `pt/lang.php` diz "ativo" (14x), o protótipo diz "bens" (54x), o nav
  legado mistura os três. **Não decida sozinho** — use o que o `pt/lang.php` tem e registre a divergência.
- `asset_code` e `series_model` são identificadores do domínio; não renomeie.

## Execução
```
PASSO  1) confirmar 07 mergeada — senao PARE
       2) RUNBOOK + charter + casos
       3) index -> Inertia, PRESERVANDO can('asset.view')
       4) create/edit/show na mesma thread SE couber em <=300 linhas; senao vira 08-2
       5) _saida-06b.md
PARAR SE a guarda de permissao sumir do diff — e regressao Tier 0
```

## Checklist de saída
1. 07 mergeada · 2. charter + casos · 3. `can('asset.view')` PRESERVADA · 4. index em Inertia · 5. decisão sobre create/edit registrada · 6. 9 Pest verdes · 7. placar
