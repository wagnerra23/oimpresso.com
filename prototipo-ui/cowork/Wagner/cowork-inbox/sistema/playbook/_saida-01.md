---
sessao: "01"
titulo: Usuários (ManageUser) → Inertia — saída
playbook: sistema
thread: "01"
dono: "[CL]"
data: "2026-10-07"
base: wagnerra23/oimpresso.com@main a8cc5d870d
---

# _saida-01 · Usuários (ManageUser) → Inertia

Feita pelo processo MWART (ADR 0104) em 4 PRs, um por fase. A **F5 (cutover) não foi feita**: a tela
React só entra por `X-Inertia` ou pela chave `mwart.sistema_usuarios_index`, que nasce desligada.

## 1 · Feito
- **F1 + F2** (#8918): `memory/requisitos/User/RUNBOOK-usuarios.md` mais a baseline Pest do controller de hoje
  (`tests/Feature/Users/UsuariosContratoTest.php`). O `store` em 5 casos, o 403, `update`, `destroy`, `edit` e a
  DataTable do `index`, no tenant 98 × 99. CT 100: 10 passed (53 assertions). A lane `acessos-pest` passa a
  acordar quando mudam o controller e as views `manage_user`.
- **F2b Tier 0** (#8935): `store`/`update` aceitavam função e contato de outra empresa (inclusive o `Admin#`
  que a tela esconde de quem não é admin), e o `show` dava 500 com usuário alheio. Agora a resposta é 403 antes
  de gravar, o filtro de contatos é por empresa e o `show` responde 404. Os 5 testes saíram vermelhos antes do
  conserto e verdes depois.
- **F3 backend** (#8942): a chave `sistema_usuarios_index` em `config/mwart.php` e o ramo
  `Mwart::telaReact` no `index()`, antes do `ajax()`. As props são `usuarios` (deferido, mesmo recorte da
  DataTable) e `pode`. CT 100: 6 passed (29 assertions).
- **F3 Page** (#8943, draft até #8918 e #8942 entrarem): trio `resources/js/Pages/Usuarios/Index.{tsx,charter.md,casos.md}` no padrão PT-01
  (KPIs, busca, filtro de função e de situação, tabela), com drawer de detalhe PT-02 e exclusão confirmada
  digitando o usuário. A própria conta não oferece excluir. O teste é `UsuariosExcluirTest` (UC-USUA-04).
  Excluir usa o verbo DELETE, como a Blade: `POST` com `_method=DELETE` em `/users/{id}` dá 405 (sonda no CT 100).

## 2 · Não feito e por quê
- **Formulário em drawer:** cadastrar e editar abrem as telas Blade. O `update()` zera login e contatos
  permitidos que não chegam no corpo, e o formulário tem ~30 campos, com partes de módulo. Fica para PR próprio.
- **Sem fonte no legado:** convite por e-mail, link de redefinição, último acesso, 2 etapas, ativar/desativar
  na lista e atividade recente.
- **Guarda de exclusão por vendas/OS no nome (D5, [W] 2026-08-19):** falta decidir o que conta como "no nome"
  (criador da venda, vendedor ou comissionado). Inventar a regra no `destroy()` seria decisão de produto.
- **Permissões `location.<id>` de outra empresa** no `giveLocationPermissions` (`Util`, compartilhado com a
  API): o impacto é baixo, porque `permitted_locations()` filtra por empresa. Fica como ponta solta.
- **Contrato de forma:** depende de um alvo medido. **Smoke biz=1:** depende do deploy dos 4 PRs; vale pela
  navegação Inertia, com a chave ainda desligada.

## 3 · Pedido literal pro [W]
> Depois do deploy, aprovar o screenshot de `/users` em React e decidir quando ligar
> `MWART_SISTEMA_USUARIOS_INDEX` (por empresa, via `MWART_SISTEMA_USUARIOS_INDEX_BIZ`).
> E dizer o que conta como "venda/OS no nome" para a guarda de exclusão (D5).

## 4 · Descobertas que mudam outra sessão
- O padrão `Role::findOrFail($id)` sem `business_id` também mora no `Util::createUser()`, usado pela API. A
  thread `sistema/02` (Funções) deve conferir o `RoleController`.
- O `ACESSOS-F1-2026-08-19.md` usa `UC-USR-01…07` para os casos do protótipo. A tela real usa `UC-USUA-*`
  para não colidir com outro significado.

## 5 · Prefixo tocado
`app/Http/Controllers/ManageUserController.php` · `resources/js/Pages/Usuarios/` · `config/mwart.php`.
Fora dele, por exigência de processo: `memory/requisitos/User/RUNBOOK-usuarios.md`, `tests/Feature/Users/`,
`.github/workflows/acessos-pest.yml` e este `_saida-01.md`.
