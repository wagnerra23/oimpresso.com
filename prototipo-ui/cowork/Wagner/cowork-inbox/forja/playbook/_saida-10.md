---
sessao: "_saida-10"
thread: "10 · TeamScopes: provar a junção com user_businesses; se quebrada, trocar pela tabela real (D6)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main eecb5a50f
---
# _saida-10

## Veredito: a junção estava quebrada
D6 ([CC] 2026-10-07, pergunta pulada pelo [W]): *"teste primeiro"*.

`UserScopeService::listUsersWithAccess` (a prop deferida `users` da tela
`/ads/admin/team-scopes`) fazia `JOIN user_businesses ON users.id = user_businesses.user_id`.
Essa tabela **não existe**: 0 `CREATE TABLE \`user_businesses\`` em
`database/schema/mysql-schema.sql` e 0 usos em código fora deste serviço (`git grep`, 1 de 1).
O achado já estava no `TeamScopes.casos.md` como hipótese desde 2026-10-07
(`Schema::hasTable('user_businesses') = false` no staging). A prop lançava `QueryException` e a
lista de devs nunca carregava.

## Entregue
- O serviço passou a filtrar pelo vínculo real do UltimatePOS, `users.business_id`
  (lido no schema: coluna + FK `users_business_id_foreign`). Mantido o `deleted_at IS NULL`.
- `TeamScopes.casos.md`: **UC-TSCOPE-05** `[T0]`, que substitui o `[BACKLOG]` do achado.

## Provas
`Modules/Forja/Tests/Feature/ForjaTeamScopesDevsTest.php`, na allowlist da lane MySQL
`forja-pest.yml`: 2 devs do tenant 98 entram, o vizinho do 99 não (com controle positivo do
lado dele); dev com soft delete fica fora. No `main` antes deste PR o teste nasce vermelho
(tabela inexistente) — é o vermelho que a D6 pedia; com a troca, verde. Pest local é
proibido: o veredito é a lane.

## Fora do prefixo, declarado
O prefixo listava o `TeamScopes.casos.md` e o teste; a nota do índice diz que *"o controller
entra no prefixo do PR depois de achado"*. O defeito está no **serviço** que o controller
chama (`UserScopeService`), não no controller — toquei só o serviço. `TeamScopes.tsx`
(`nao_toca`) não foi tocado.
