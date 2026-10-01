---
sessao: "_saida-03"
thread: "03 · CONN-O2b · ninguém lê o segredo (sem tocar no valor guardado)"
dono: "[CL]"
data: 2026-10-01
base_lida: wagnerra23/oimpresso.com@main 3a0f3f8bb
---
# _saida-03

## Entregue
- `Modules/Connector/Http/Controllers/ClientController.php`
  - `index`: sai o `makeVisible('secret')` **e** sai o `select('oauth_clients.*')`. A lista
    seleciona colunas explícitas, sem `secret`. Motivo de tirar a coluna e não só o
    `makeVisible`: `$hidden` do Passport só vale para `toArray`/JSON; a Blade lê
    `{{$client->secret}}` direto do atributo e continuaria imprimindo o segredo.
  - `store`: o segredo gerado aparece **uma vez**, na mensagem de sucesso da criação (flash da
    sessão, lido na tela seguinte e descartado). Não entra no log nem na auditoria (`auditar()`
    já gravava só `client_id` + nome).
- `Modules/Connector/Tests/Feature/ClientControllerBaselineTest.php`: o caso que travava o
  segredo em texto puro ("comportamento ATUAL, não endosso — mascarar é decisão [W]") virou o
  oposto, porque a decisão veio (D6). Precedência: o teste era o perdedor, corrigido no mesmo PR.
  +1 caso: a criação entrega o segredo uma vez e a lista depois não o mostra. Os dois têm âncora
  positiva (o nome do client aparece) para o `assertDontSee` não passar por vácuo.
- `ApiClientsPanelTest.php`: só o comentário "hoje chama makeVisible" virou fato datado.

**Não mudou:** o valor guardado (sem hash, sem rotação, sem migração de coluna), `Routes/api.php`,
`Http/Controllers/Api/`, `config/auth.php`, `AuthServiceProvider`. O WR Comercial em campo segue
autenticando com o segredo que já tem.

## Provas do json, medidas no branch
1. `ClientController.php` não contém `makeVisible('secret')` — ✅ (`grep -c` = 0)
2. `app/Providers/AuthServiceProvider.php` não contém `hashClientSecrets` (guarda D6) — ✅ (`grep -c` = 0)

## Placar
`03 [pendente]` antes de começar — único motivo: "depende de 01 (não feita)". A 01 foi entregue
no #8336; a prova do charter dela foi para a 04 por causa da IT2 (ver `_saida-01`, errata 1).

## Pendente / para a 04
- A Blade `clients/index.blade.php` (fora do prefixo) ainda tem a coluna "Client secret"; agora
  sai vazia. Quem remove o cabeçalho é a 04, que troca a Blade pela tela Inertia.
- O segredo único hoje sai num toast (`status.msg`), que some sozinho. A ficha diz "só na resposta
  da criação, uma vez" e não diz a forma. Na 04, a tela nova deve mostrar num bloco copiável que
  não some até fechar — decisão de forma da 04, não desta thread.
- `memory/requisitos/Connector/RUNBOOK-connector-index.md` (§3 e §10.2) ainda descreve o segredo
  visível na lista. Fora do prefixo; atualizar na 04 junto com a tela.

## NÃO MEDI
Pest: os dois arquivos fazem `markTestSkipped` em SQLite e a lane do módulo é SQLite; o run MySQL
é no CT 100, que esta sessão não toca. `php -l` também não rodou (sem PHP local); o CI faz.

## PR
(no corpo do PR)
