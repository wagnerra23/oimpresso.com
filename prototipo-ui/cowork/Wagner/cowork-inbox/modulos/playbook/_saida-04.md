---
sessao: "_saida-04"
thread: "04 · PR-8: remover o legado /manage-modules (último, com portão)"
dono: "[CL]"
data: 2026-10-14
tipo: recibo + desvio de escopo medido
base_lida: wagnerra23/oimpresso.com@main d2af916e11
---
# _saida-04

## O que esta sessão fez
- `routes/web.php`: saíram o `Route::resource('manage-modules', …)->only(['index','update'])` e o `DELETE manage-modules/destroy/{module_name}`. Entrou só `GET manage-modules` → `ModulesController@index`. `upload-module` e `regenerate` **ficam**, como o `../PR-8-REMOVER-LEGADO.md` manda.
- `ModulesController@index` agora redireciona com 301 para `route('modules.index')` (`/modulos`). Antes disso chama `session()->reflash()`, para o `status` gravado pelo instalador chegar a `/modulos`.
- `ModulesController`: saíram `create`, `store`, `show`, `edit`, `update`, `destroy` e `__available_modules`. Ficam `aceitaPost` (o `InstalacaoSoPorPostTest` usa), `regenerate` e `uploadModule`.
- Apagados `resources/views/install/modules/index.blade.php` e `partials/acao.blade.php`. O `confirmar.blade.php` **fica**: a trait `ConfirmaInstalacaoPorPost` o renderiza.
- `AdminSidebarMenu`: o item "Módulos" aponta para `route('modules.index')` e acende no segmento `modulos`. Antes ele nunca acendia em `/modulos`.
- `BaseModuleInstallController`: install e uninstall voltam direto para `/modulos`, sem passar pelo redirecionamento.
- `phpstan-baseline.neon`: saíram as 9 entradas dos métodos removidos.
- `tests/Feature/Install/ManageModulesPostTest.php`: saíram os 3 casos que liam a view e a partial apagadas. Entraram 3 casos: só GET em `/manage-modules`, redirecionamento 301 levando o flash, e a view apagada com `confirmar` mantido.

## Desvio do pedido, medido
A thread pedia `GET /manage-modules ⇒ 404` e citava só o `BaseModuleInstallController` como chamador. Contado em `d2af916e11`: o `action([ModulesController::class, 'index'])` é chamado também pela trait `ConfirmaInstalacaoPorPost`, por `install-module.blade.php` e por 6 `InstallController` em `Modules/*` (Arquivos, Compras, Connector, Essentials, Officeimpresso, Superadmin). Sem rota, o `action()` lança e o install desses módulos termina em 500. Por isso a rota GET fica como redirecionamento, e os 6 controllers de módulo não foram tocados (fora do prefixo). O `LegacyMenuAdapter` (`'/manage-modules' => '/modulos'`) continua como estava.

## Portão
| item | estado |
|---|---|
| 01 `feito` no placar | ✓ (`node scripts/qa/placar.mjs --indice … --thread 04` mostrava `proximo`) |
| trio + contrato de `Modules/Index` | ✓ (`npm run screen:files -- Modules/Index`: trio completo, sem declaração quebrada) |
| lane verde nos testes de módulo | `ManageModulesPostTest` e `ModuleManagerServiceTest` estão no `ci-sqlite-pest.list`; o resultado fica no PR. `ModuleManagementTest` **não** está em lane de PR (já registrado no `_saida-01`) |
| smoke 1280/1440 aprovado por [W2] | **não feito por esta sessão** — decisão [W] no merge |
| `/modulos` exercitada em produção com as 3 ações | **não medido**: o `ModuleManagerService` não grava log, o `route-hits.json` é de 2026-08-23 e não tem a rota, e o Hostinger não expõe log de acesso |

## Prova
As duas provas do índice passam nesta árvore: `index.blade.php` ausente e `routes/web.php` sem `Route::resource('manage-modules'`.
