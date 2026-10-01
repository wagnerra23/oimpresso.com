---
sessao: "_saida-05"
thread: "05 · Painel de clientes OAuth duplicado (ClientController)"
dono: "[CL]"
data: 2026-10-01
tipo: entregue PARCIAL (metade de segurança); aposentar o painel fica decisão [W]
base_lida: wagnerra23/oimpresso.com@main 4b680571e
---
# _saida-05

## Por que parcial
A D1 ([W] 2026-10-01, `_DECISOES-W-2026-10-01.md`) manda aposentar este painel e redirecionar
para o do Connector. Isso não dá para fazer sem uma segunda decisão, que é do [W]:

- este painel aceita `superadmin` **ou** a permissão delegável `officeimpresso.clientes.liberar`
  (`ClientController::authorizeLiberar()`). Funcionários com login próprio liberam clientes
  Delphi por aqui;
- o painel do Connector aceita só `superadmin`, e o charter dele proíbe delegar:
  *"❌ Delegar emissão de credencial por permissão ([W] 2026-08-19): fica em `superadmin`"*
  (`Modules/Connector/Resources/js/Pages/Api/Index.charter.md`, Non-Goals; também R4 e
  UC-CONN-08 do `Index.casos.md`).

Um redirect simples tiraria o acesso dos delegados, e abrir o Connector para a permissão
violaria uma proibição do charter que só o [W] pode mudar.

## O que entregou (segurança, sem mudar quem acessa)
- `ClientController::index` não seleciona mais a coluna `secret`: a lista traz
  `id, name, password_client, personal_access_client`. O `makeVisible('secret')` saiu. O
  precedente é o painel do Connector (#8350), em que a Blade também lia o atributo direto.
- `ClientController::store` grava o segredo num flash próprio (`officeimpresso_credencial`),
  lido uma vez pela lista e descartado pela sessão. Ele nunca vai para o log nem para o
  `status.msg`. O valor guardado não muda (sem hash, sem rotação), então o Delphi que já está
  em campo continua autenticando.
- `views/clients/index.blade.php`: saíram a coluna "Secret", o `data-secret` e o botão de
  revelar. Entrou um bloco "Credencial criada" com Client ID, Secret e o botão Copiar. Ele
  fica na tela até o usuário clicar em Fechar.
- A permissão ficou igual à de antes: `superadmin` **ou** `officeimpresso.clientes.liberar`.
  Não houve redirect, e o Connector não foi tocado.
- Teste `Modules/Officeimpresso/Tests/Feature/ClientesSegredoUmaVezTest.php`, com 4 casos: a
  lista não imprime o segredo; o delegado cria e vê o segredo uma única vez (na 2ª abertura
  ele não aparece mais); sem nenhuma das duas permissões dá 403 na lista e na criação, e nada
  é criado; e o tenant 98 não vê o client do 99. O arquivo entrou na allowlist da lane
  `officeimpresso-pest.yml`, porque sem isso ele não rodaria no CI.

## Provas do json conferidas
| prova | resultado |
|---|---|
| `nao_contem` `makeVisible('secret')` em `ClientController.php` | verde (o padrão saiu do arquivo) |

## Pendente: decisão [W] para aposentar o painel
- **(a)** Emendar o charter do Connector para aceitar `officeimpresso.clientes.liberar` na
  lista e na criação (excluir segue só para superadmin). Depois disso o painel daqui
  redireciona para `/connector/client`.
- **(b)** Manter este painel só para os delegados e redirecionar o superadmin para o Connector.
- **(c)** Acabar com a delegação: só o superadmin emite credencial, e este painel redireciona
  para o Connector.

## Achado no CI (fora do escopo, para o [W]/sessão-mãe)
O 1º run da lane do #8362 mostrou dois fatos do ambiente:
- **O Passport 13.7.5 grava o secret com hash** (bcrypt de 60 caracteres). O texto puro só
  existe no momento da criação, e por isso o flash é a única fonte dele. Antes disso, a lista
  "revelava" o hash dos clients novos, não o segredo.
- **O model do Passport 13 gera UUID no `id`, e o schema legado é `int` auto-increment.** No
  MySQL do CI o UUID virou `1` e o 2º client do mesmo teste deu `Duplicate entry '1'`. Em
  produção, se o MySQL estiver em modo estrito, o `store` dos **dois** painéis (Officeimpresso
  e Connector) pode estar falhando, ou gravando com um id diferente do que o model devolve.
  NÃO MEDI isso em produção. Por causa disso o bloco da credencial não mostra o
  `$client->id`: ele aponta para a linha da lista, que lê o id gravado.

## Fora do escopo, sem mexer
- `destroy` (apaga sem revogar tokens) e `regenerate` (GET que roda `passport:install --force`)
  continuam como estavam, só para superadmin. As ondas O2/O2b do Connector consertaram isso
  lá; aqui o conserto depende de D1: se o painel for aposentado, os dois somem junto.
- O índice ainda marca D1 como `respondida: false`, porque o json é do Cowork. Até a edição
  pedida em `_DECISOES-W-2026-10-01.md` ser aplicada, o placar mostra esta thread como
  `pendente · decisão D1`.

## PR
A preencher pela sessão-mãe / ver o PR da branch `claude/officeimpresso-thread-05`.
