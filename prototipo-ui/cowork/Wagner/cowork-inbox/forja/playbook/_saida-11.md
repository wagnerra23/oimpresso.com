---
sessao: "_saida-11"
thread: "11 · Tier 0 · Ferramentas: permissão própria em grant/revoke/execute + auditoria por empresa + autor real (D7)"
dono: "[CL]"
data: 2026-10-08
tipo: recibo
entregue_em: "#9062"
base_lida: wagnerra23/oimpresso.com@main 265b507860
---
# _saida-11

## Entregue
O [#9062](https://github.com/wagnerra23/oimpresso.com/pull/9062) foi mergeado em 2026-10-08 18:10 UTC
(`17ec41ca22`). Ele cumpre a decisão [W] D7: *"permissão própria + auditoria filtrada por empresa +
autor = usuário logado"*.

- **Permissão própria:** `forja.tools.execute` e `forja.team_scopes.manage` foram declaradas em
  `DataController::user_permissions` e aparecem no cadastro de papéis. São checadas no controller:
  `ToolsController::execute`, `TeamScopesController::grant` e `revoke`. Sem a permissão, a
  resposta é 403. Antes, bastava estar logado.
- **Auditoria por empresa:** em `/ads/admin/tools`, `recent_executions` e `kpis.executions_7d`
  passam a filtrar pelo `business_id` da sessão. O `execute` não cai mais em `business_id = 1`
  quando não há sessão.
- **Autor real:** `triggered_by` (execução) e `granted_by` (concessão) gravam o `username` de quem
  está logado. Antes, as duas colunas gravavam `'wagner'` fixo.

## Provas
Teste `Modules/Forja/Tests/Feature/ForjaToolsPermissaoTest.php` cobre UC-TOOLS-03, UC-TOOLS-04,
UC-TOOLS-05 e UC-TSCOPE-04, com as empresas 98 e 99. Ele está na allowlist do `forja-pest.yml`,
uma entrada por linha.

Rodei no CT 100 numa cópia isolada do branch, em `/tmp`. Não usei o checkout do container porque
ele está em 21/09 e outras sessões o usam. A cópia foi apagada depois.

| Rodada | Resultado |
|---|---|
| Controllers do `main` | `4 failed`: 403 esperado, recebido 200 (execute) e 302 (grant); autor `'wagner'` |
| Com o conserto | `9 passed (60 assertions)`: o teste novo mais `ToolsContratoTest` e `TeamScopesContratoTest` |
| Mutação: sem o `where('business_id')` na auditoria | UC-TOOLS-04 cai, porque a execução da 99 aparece para a 98 |
| Mutação: sem o `abort_unless` no execute | UC-TOOLS-03 cai: 403 esperado, recebido 200 |

O arquivo foi restaurado depois das mutações e conferido pelo sha256: o hash bateu com o do branch.

Na lane, o run `37818785772` (`PHP / Pest (Forja · MySQL)`) saiu success. Os 4 casos aparecem
executados pelo nome no log, num total de `139 passed (668 assertions)`.

Casos: `Tools.casos.md` ganhou UC-TOOLS-03, 04 e 05, e `TeamScopes.casos.md` ganhou UC-TSCOPE-04.
Os itens de BACKLOG que eles resolvem apontam para os UC.

## O que não está provado ou ficou de fora
- **Admin da empresa:** o papel `Admin#{business_id}` continua passando nas duas permissões pelo
  `Gate::before`. Na prática, o dono da empresa executa ferramentas e concede escopos sem marcar
  nada. Tirar `forja.tools.execute` desse atalho, porque as ferramentas agem fora da empresa, é
  decisão [W].
- **Revoke de outra empresa:** o `revoke` não confere se o `user_id` é da mesma empresa. A junção
  `user_businesses` do TeamScopes é a thread 10.
- **`Tools.tsx`:** não muda, porque está no `nao_toca` da thread. Quem não tem a permissão recebe
  403 ao clicar em "Executar", sem mensagem própria na tela.
- **Cache de permissões nos testes:** os dois testes antigos e o novo limpam o cache do Spatie
  antes de `findOrCreate`. Sem isso, a primeira rodada deu FK 1452 em `model_has_permissions`: o
  cache guardava o id de uma permissão criada numa transação já revertida.

## Espelho Cowork
Este arquivo ainda não subiu para os projetos `w`/`copia` do Claude Design. Escrever lá exige
opt-in do [W] (ADR 0315). Como arquivo novo, ele nasce "nunca verificado" e não trava o required
do espelho.
