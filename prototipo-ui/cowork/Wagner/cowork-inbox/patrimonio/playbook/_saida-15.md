---
sessao: "15"
titulo: Saída da thread 15 — as 8 chamadas de view sem arquivo
dono: "[CL]"
medido_em: 2026-09-24
base_medida: 68e071305601 (origin/main fresco) · runtime CT 100 `oimpresso-staging`, checkout `e57b78bf5`
arquivos_de_producao_tocados: 0
invalida: "nada — confirma o defeito da ficha nos 8 sítios; propõe thread nova (§D.4)"
---

# 15 · Saída — as 8 chamadas estouram, e nenhuma tela leva até elas

## Veredito
**Os 8 sítios quebram quando alcançados, e nenhuma interface os alcança.** Cada um é um método de `Route::resource` sem guarda de permissão, que devolve `view('assetmanagement::show|create|edit')`. Nenhuma das 3 views existe em lugar nenhum dos caminhos de busca do módulo. É **defeito latente**: dá erro para qualquer usuário logado que digitar a URL, de qualquer empresa, e nenhum link, botão ou `action()` do repo aponta para esses métodos.

| # | sítio (`main`) | rota viva | view | `View::exists` | chamada + render | veredito |
|---|---|---|---|---|---|---|
| 1 | `AssetController.php:537` show | `GET /asset/assets/{asset}` | `::show` | false | `InvalidArgumentException: View [show] not found` | quebra · só por URL |
| 2 | `AssetAllocationController.php:371` show | `GET /asset/allocation/{allocation}` | `::show` | false | idem | quebra · só por URL |
| 3 | `AssetMaitenanceController.php:373` show | `GET /asset/asset-maintenance/{asset_maintenance}` | `::show` | false | idem | quebra · só por URL |
| 4 | `AssetSettingsController.php:150` create | `GET /asset/settings/create` | `::create` | false | `View [create] not found` | quebra · só por URL |
| 5 | `AssetSettingsController.php:228` show | `GET /asset/settings/{setting}` | `::show` | false | `View [show] not found` | quebra · só por URL |
| 6 | `AssetSettingsController.php:239` edit | `GET /asset/settings/{setting}/edit` | `::edit` | false | `View [edit] not found` | quebra · só por URL |
| 7 | `RevokeAllocatedAssetController.php:202` show | `GET /asset/revocation/{revocation}` | `::show` | false | `View [show] not found` | quebra · só por URL |
| 8 | `RevokeAllocatedAssetController.php:213` edit | `GET /asset/revocation/{revocation}/edit` | `::edit` | false | `View [edit] not found` | quebra · só por URL |

**Middleware das 8 rotas** (registro vivo, `Router::getRoutes()` + `gatherMiddleware()`): `web, throttle:60,1, authh, auth, SetSessionData, language, timezone, AdminSidebarMenu`. Nada de `can:`, e os 8 métodos não fazem checagem própria antes do `return view(...)`.

## Como foi medido
1. **Sítios:** `git grep -nE "view\(\s*'assetmanagement::(show|create|edit)'"` no `main` dá 8 de 8. O container tem as mesmas 8 chamadas, com o `AssetController` na linha 531 em vez de 537, porque o checkout lá está atrás.
2. **Árvore de views:** os mesmos 17 caminhos no `main` e no container. Os hashes diferem, mas o que se mede aqui é a existência dos arquivos, e o conjunto de nomes é igual. `resources/views/modules/assetmanagement` (o primeiro caminho do `loadViewsFrom`) não existe no container.
3. **`View::exists`**, com o app inicializado: `::show`, `::create` e `::edit` devolvem `false`. **Controle positivo:** `::asset.index` devolve `true`.
4. **Rotas:** lidas do router vivo, não do `Routes/web.php`.
5. **Chamada:** cada método foi instanciado pelo container e renderizado. **Controle do render:** `::index` foi encontrada e quebrou só no layout pai (`ViewException: View [layouts.master] not found`). A exceção é de classe diferente, o que prova que o instrumento separa "view ausente" de "view encontrada que falhou depois".
6. **Alcance pela UI:** `git grep` de `<Controller>::class, '<metodo>'` em `Modules/`, `resources/` e `app/` dá **0** referências aos 8 métodos (as dos outros métodos aparecem, o que serve de controle). `route('….show|edit|create')` tem 1 resultado, `asset-maintenance.create`, que não está entre os 8 e tem view própria. `resources/js` não faz GET em nenhuma das 8 URLs; `Bens.tsx` usa `/asset/assets/{id}` só com `router.delete`.

As sondas foram transportadas em base64, com o sha256 conferido nos dois lados. Nenhuma escreve no banco.

## O que NÃO foi medido
- **Status HTTP real.** O banco do staging (`oimpresso_staging`) está com 190 tabelas e sem `users`, então não há usuário para autenticar a requisição. Pelo mecanismo, a exceção acima vira **500** no handler, mas isso é dedução e não medição. Não reprovisionei o banco, porque não é escopo desta thread.
- **Produção.** Medi no staging. A árvore de views vem do repo, então a conclusão deve valer lá, mas não foi conferido.

## Proposta (§D.4) — thread nova, não executada aqui
"Os 8 métodos mortos de `Route::resource` saem do ar". Há dois caminhos, e a escolha entre eles é da thread nova:
- **(a)** `->except([...])` / `->only([...])` no `Route::resource`, para a URL passar a dar 404;
- **(b)** apagar os métodos.

⚠️ **Dependência com D-FORMS:** 4 dos 8 (`settings` create/show/edit e `revocation` edit) são justamente as sub-telas que a D-FORMS pode trazer para React. Se a D-FORMS disser "migra", essas rotas ganham tela e não devem ser tiradas. Os 3 `show` de assets, allocation e maintenance e o `revocation show` não dependem dela.

Esta thread **não** mexe na 16 (§D.4).

## Checklist (§D da thread)
1. Uma linha por sítio, com arquivo, linha, view, `View::exists`, resultado e veredito ✅ (status HTTP: declarado como não medido)
2. Caso de sanidade antes do veredito: `::asset.index` = `true` ✅
3. Zero arquivo de app alterado; o diff tem só `_saida-15.md` ✅
4. Quebra alcançável vira thread própria: proposta acima, sem editar o índice (`nao_toca: *`) ✅
5. Placar no corpo do PR ✅
