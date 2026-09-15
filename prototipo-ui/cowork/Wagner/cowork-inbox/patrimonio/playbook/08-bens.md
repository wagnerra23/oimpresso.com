---
sessao: "08"
titulo: Bens — o CRUD principal
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Bens.tsx · AssetController (create/edit/show — o index JÁ MIGROU)
nao_toca: `_shared/` (já no main) · o `index()` (entregue) · as outras telas
depende: — (a fundação está no main; a dependência de 07 caiu)
---

> ⚠️ **ERRATA 2026-09-08 [CL] — a LISTAGEM já foi entregue; sobra o CRUD.**
> O `AssetController::index()` migrou para `Inertia::render('Patrimonio/Bens')` no
> PR [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035) (mergeado 17:42Z), com
> charter, casos e 4 UC verdes no CT 100. **O que resta desta thread é `create` / `edit` /
> `show`**, que seguem Blade — Non-Goal declarado no charter, com motivo escrito.
>
> **Duas correções de mapa que vinham desta ficha e do JSON:**
> - **caminho:** era `Pages/Patrimonio/Bens/Index.tsx` (subpasta); o arquivo mergeado é
>   `Pages/Patrimonio/Bens.tsx` (flat). A prova do §7 nunca passaria, e o placar dizia
>   *"pendente · arquivo ausente"* para uma tela em produção.
> - **dependência:** `depende: 07` caiu — o `_shared` foi fundado pela própria tela de Bens.
>
> **Antes de mexer no `create`/`edit`, leia o que já está decidido** (`Bens.charter.md`):
> o drawer de criação é onda própria, e o `Alocado` da listagem **não é número auditado**
> enquanto o resíduo Tier 0 do gêmeo não fechar — a medição está em
> [`_saida-06-bens.md`](_saida-06-bens.md) §5 (20 de 128 assets divergem).
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
