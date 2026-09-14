---
sessao: "03"
titulo: Guarda `asset.view` no índice de Bens
dono: "[CL]"
base: cb475c0ca2f4
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: Modules/AssetManagement/Http/Controllers/AssetController.php · Tests/Feature/SmokeRoutesTest.php
nao_toca: Services/** · AssetAllocationController · as views Blade
depende: — (vaga 1; prefixo disjunto de 01 e 04)
antes:  index() checa só a assinatura do módulo
depois: index() exige asset.view, como create() já exige asset.create
---
# 03 · Guarda `asset.view`

## ÂNCORA
```
arquivo  Modules/AssetManagement/Http/Controllers/AssetController.php   22.531 B  sha 085fd16d516a
símbolo  index()    :73–:100    ← só gate de assinatura, sem permissão de tela
         create()   :271–:277   ← o padrão CORRETO, no mesmo arquivo
ler      SÓ essas duas faixas (~4 KB de 22 KB)
NÃO ler  o resto do controller · os outros 6 controllers
```

## A · O defeito
`index()` (`:73–:76`) verifica **só assinatura do módulo**:
```php
if (! (auth()->user()->can('superadmin')
    || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module'))) abort(403);
```
`create()` (`:271`) faz o que falta: `if (! auth()->user()->can('asset.create')) abort(403);`

As linhas `:145`, `:154` e `:163` usam `can('asset.view_all_maintenance')`, `can('asset.update')` e `can('asset.delete')` — mas só pra **desenhar botão**. Botão escondido não é autorização: **qualquer usuário da empresa com o módulo assinado lista o patrimônio inteiro.**

## B · Não inventar
- **Reusar** exatamente o formato de `create()`: mesma ordem (permissão de tela **antes** do gate de assinatura), mesma string de abort.
- **Não inventar a permissão.** Usar `asset.view` só se ela **existir** no seeder/registro. Se não existir, a thread PARA — criar permissão é decisão ([W] 2 do RESÍDUO discute `asset.*` × `assetmanagement.*`).
- `SmokeRoutesTest.php` (1.174 B) já toca a rota.

## Execução
```
PASSO   1) LOCALIZAR onde asset.* é registrada como permissão. Não achou ⇒ PARE ([W] 2)
        2) teste: usuário sem asset.view → 403 no GET asset/assets
        3) aplicar a guarda em index(), no formato de create()
        4) conferir se dashboard() tem o mesmo buraco; se tiver e couber, entra junto
        5) _saida-03.md
PARAR SE (a) asset.view não estiver registrada
         (b) a guarda quebrar perfil legítimo (ex: colaborador que vê só o
             próprio bem alocado) → então a regra não é "view", é escopo por
             dono — e isso é [W], não código
```

## Checklist de saída
1. `can('asset.view')` em `index()` · 2. teste de 403 · 3. veredito sobre `dashboard()` · 4. permissão confirmada como existente (colar a origem) · 5. 9 Pest verdes · 6. placar no PR
