---
sessao: "_saida-05"
thread: "05 · CONN-O4 · menu, /regenerate e connector.access fora"
dono: "[CL]"
data: 2026-10-01
base_lida: wagnerra23/oimpresso.com@main 81141329b
---
# _saida-05

## Entregue
- `Modules/Connector/Routes/web.php` — saiu `Route::get('/regenerate')` ([W] D4). Nenhuma outra rota mudou.
- `Modules/Connector/Http/Controllers/ClientController.php`
  - `regenerate()` removido (rodava `passport:install --force`, que derruba a integração de todos os negócios). O `use Artisan` saiu junto.
  - `create()` redireciona para o painel (`index`). A view `connector::create` nunca existiu e o link do menu dava 500 (UC-CONN-15). Criar é o Dialog do painel.
- `Modules/Connector/Http/Controllers/DataController.php`
  - `user_permissions()` devolve `[]`: `connector.access` saiu do catálogo ([W] D1). O método ficou, vazio, porque o `ModuleUtil` coleta `user_permissions` de todo DataController (e o teste UC-CONN-09 o chama).
  - Menu: o primário abre `/connector/client` (antes `/connector/client/create`); o ghost "API Clients" aponta `/connector/client` (antes `/connector/api`, a Blade antiga); o ghost e o item `/docs` saíram ([W] D5). O `active` do item passou a olhar `segment(2) == 'client'`.
  - O menu só aparece para superadmin com o módulo instalado.

## Quem perde / quem ganha acesso (medido)
- **Painel, criar e excluir credencial:** ninguém muda. Já era `superadmin` em `index`, `destroy` e no `StoreOauthClientRequest`.
- **`connector.access`:** nenhuma checagem no código a usava. Varredura `git grep "connector.access"` fora de docs/testes: só a declaração no `DataController`. Em prod (leitura via `tinker`, sem escrita): a permissão existe (id 434), está em **1 papel** (`Operacional#1`, biz=1) e em **0 usuários diretos**. Esse papel não perde nada que tivesse, porque a permissão nunca liberou nada.
- **Menu para não-superadmin:** antes, quem não era superadmin num negócio com `connector_module` no pacote via o dropdown "Connector" com um único item, "Documentação" (`/docs`). Agora não vê o dropdown. Negócios com assinatura aprovada e vigente que mencionam `connector_module` no `package_details`: **1 e 164** (teto; não medi se o valor está ligado). A página estática `/docs` continua acessível pela URL.
- **Ganha acesso:** ninguém. Nada novo foi aberto a não-superadmin.

## `/regenerate` — dependências conferidas antes de remover
- `Modules/Connector/Routes/api.php` e `routes/api.php`: nenhuma referência.
- Desktop Delphi: usa a API com Bearer e `/oauth/token`. `/connector/regenerate` é rota web com sessão + `superadmin`; nada no contrato do desktop a cita.
- O `regenerate` do Officeimpresso (`/officeimpresso/client/regenerate`) e o do Install (`routes/web.php:1119`) são outros controllers e não foram tocados.
- A Blade órfã `clients/index.blade.php` ainda chama `action([ClientController::class, 'regenerate'])`. Ela não é renderizada desde a thread 04 (o `index` é Inertia) e sai na thread 06. Se alguém a renderizar antes disso, quebra.

## Provas do json, medidas no branch
1. `Routes/web.php` não contém `/regenerate` — ✅
2. `DataController.php` não contém `connector.access` — ✅
Placar após o commit: abaixo, no PR.

## Pendente
- **Revogar a permissão onde foi concedida** (a ficha pede): tirar `connector.access` do papel `Operacional#1` (biz=1) e apagar a linha de `permissions` é escrita em dado de prod. Fica fora do prefixo desta thread (precisa de migration ou seeder idempotente). Como nada verifica a permissão, deixá-la no banco não abre acesso. Decidir em qual thread entra.
- `Index.casos.md`: UC-CONN-09/13/14/15 ainda dizem "thread 05" e `Status: 🧪`. Os testes que os citam (`ApiClientsPanelTest`) devem ficar verdes no MySQL. O casos fica fora do prefixo; o veredito é do CI/CT 100.
- Charter R9 diz que o menu aparece também pelo pacote `connector_module`. Agora o menu é só de superadmin. A regra continua verdadeira como condição necessária, mas o ramo do pacote ficou sem efeito no menu. Errata para o charter.
- A aba Documentação (PR-b da 04) ainda não existe. Até lá, o menu não tem link para a documentação da API.

## NÃO MEDI
Pest (o `ApiClientsPanelTest` faz skip em SQLite; MySQL é no CT 100, que esta sessão não toca). `php -l` limpo nos três arquivos.

## PR
(no corpo do PR)
