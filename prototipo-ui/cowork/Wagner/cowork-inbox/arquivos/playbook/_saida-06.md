---
sessao: "_saida-06"
thread: "06 · install/uninstall/update fora de GET"
dono: "[CL]"
data: 2026-10-01
base_lida: wagnerra23/oimpresso.com@main fd3866d9b
---
# _saida-06

## Entregue
- `Modules/Arquivos/Routes/web.php`: `install` ganhou `POST` (→ `install()`); `install/uninstall` e
  `install/update` passaram de `Route::get` para `Route::match(['get', 'post'], …)`.
- `Modules/Arquivos/Http/Controllers/InstallController.php`: `index`/`uninstall`/`update` só agem no
  POST. O GET devolve uma confirmação **sem efeito**, com formulário POST + CSRF e botão Cancelar
  para `/manage-modules`. Mesmo padrão do Connector (CONN-O2, #8344).
- `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` (lane `arquivos-pest`, MySQL):
  - o teste de não-regressão contava 3 `InstallController::class` nas rotas; agora 4;
  - +2 casos: as 3 URIs aceitam POST e não há `Route::get` de uninstall/update; e, nos 3 métodos,
    o `isMethod('post')` vem antes do `parent::<método>(` (o GET não chega à ação).

## Provas do json, medidas no branch
1. `Modules/Arquivos/Routes/web.php` não contém `Route::get('install/uninstall'` — ✅
   (`grep -c` = 0 no branch; o comentário novo cita a regra sem repetir a string).

## Placar
Espera-se `06 [feito]` depois do merge: a única prova é a do json acima.

## Decisões técnicas (não [W]) e por quê
- **O GET não virou 405.** `/manage-modules` (núcleo, fora do prefixo) monta `<a href>` para estas
  rotas; com POST puro o botão do Arquivos daria 405. O GET fica, mas só confirma.
- **`update` do parent chama `$this->index()`**: no POST o override de `index` vê POST e segue
  para `parent::index()`; no GET o override de `update` para antes. Sem caminho em que o GET
  rode migration.

## Pendente
- Trocar os `<a href>` de `/manage-modules` por formulário POST — núcleo (`app/` +
  `resources/views/install/`), vale para todos os módulos, fora de qualquer thread do Arquivos.
  Mesma pendência registrada na `_saida-02` do Connector.
- Teste comportamental "GET de uninstall não desativa" (como o do Connector) não entrou: o arquivo
  de teste do Arquivos não tem fixture de superadmin; a guarda ficou provada por estrutura + rotas.

## PR
(preenchido no corpo do PR)
