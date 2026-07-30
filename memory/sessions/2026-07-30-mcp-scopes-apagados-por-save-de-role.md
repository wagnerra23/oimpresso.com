---
date: "2026-07-30"
topic: "MCP fora do ar — save em /roles/{id}/edit apagava os 17 jana.mcp.* (syncPermissions destrutivo × scopes invisíveis no form)"
prs: [5060, 5063]
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0093-multi-tenant-isolation-tier-0
outcomes:
  - "Prod restaurado: 4 users bloqueados → 0, com valores isolados de Felipe/Maiara/Luiz"
  - "Armadilha desarmada: scopes MCP viram checkbox derivado do catálogo do seeder"
  - "LC-13 ocorrência 4 — guard que nenhuma lane do CI executava, pego e corrigido"
---

# Sessão 2026-07-30 — o 403 do MCP era a tela de papéis comendo as permissões

Pedido do [W]: **"arrume o mcp"**. O hook do SessionStart já tinha caído em fallback com
`MCP retornou error JSON-RPC`.

## Como o diagnóstico andou

O hook redige o erro **por construção** (nunca interpola resposta/headers — anti-vazamento de
token), então o motivo real não aparecia. Chamando o endpoint direto:

```
HTTP 401 {"error":"Unauthorized","message":"User não tem permission `jana.mcp.use`."}
```

Token válido — passou de `invalid_token` e `user_not_found`. Só então ficou claro que era RBAC,
não servidor nem token.

**Duas hipóteses caíram por medição, e é isso que vale registrar:**

1. *"É drift do rename `copiloto.*` → `jana.*`"* (o [#4928](https://github.com/wagnerra23/oimpresso.com/pull/4928)
   é recente e mexeu numa família de 17). **Falso**: em prod, `copiloto.mcp.* = 0` e
   `jana.mcp.* = 17`; a permission `jana.mcp.use` existe (id 303, guard `web`). O rename estava
   completo.
2. *"É só o meu token"*. **Falso**: a query do `mcp_token_sem_permission` devolveu **4 users** —
   o time MCP inteiro.

## A causa, e por que ela é aritmética

`RoleController@update` → `syncPermissions($request->input('permissions'))`, destrutivo.
O form de `/roles/{id}/edit` renderiza só o que os módulos declaram em `user_permissions()`:
Jana declarava **5**, `TeamMcp` devolvia `[]`.

O fecho: depois do save, o conjunto `jana.*` da role 695 era **exatamente esses 5**. Não é
inferência — é o mesmo conjunto, item por item.

E o detalhe que explica a reincidência (4ª volta): **como as permissions não existiam na tela,
o conserto pela UI era impossível**. Cada rodada anterior consertou por fora, e o próximo save
desfez.

## Escolhas que divergiram do pedido literal, e por quê

O [W] aprovou restaurar **13** scopes ("os do Admin#1 + advance/close"). Restaurei **11** na role
e **2 diretos no user 1**, porque a role 695 é **compartilhada** com Felipe/Maiara/Luiz e dois dos
13 são declarados no próprio catálogo como *"Wagner + Eliana[E]"* (`governanca.financeiro`) e
*"Apenas Wagner/admin"* (`usage.all` — spend cross-team em R$). A regra Tier 0 de valores
prevaleceu sobre a leitura literal; as 13 capacidades do [W] ficaram intactas.

Também **não** criei junction de `vendor/` pra "consertar" o `laravel-boost` do `.mcp.json`:
medi os 12 worktrees e **nenhum** tem `vendor` — ele nunca funcionou fora do repo principal.
Não é regressão, e criar junction é o landmine que já esvaziou `vendor` (2026-05-11) e
`node_modules` (2026-07-14).

## LC-13 cometido aqui — o guard que nenhuma lane executava

Escrevi `McpScopesVisiveisNoRoleEditTest`, rodei no CT 100 (`3 passed`), rodei o contrafactual
(revertendo só o `DataController` → `1 failed` na assertion exata) e ia reportar "CI 100 verde"
como se cobrisse o guard.

Cobria nada:

```
gh run view <CI> --log | grep -c McpScopesVisiveisNoRoleEdit   →   0
```

`ci.yml` roda **lista explícita** (`.github/ci-sqlite-pest.list`); `jana-pest.yml` roda
**allowlist** de arquivos MySQL-only. O teste estava fora das duas — verde que não podia ficar
vermelho. Corrigido em [#5063](https://github.com/wagnerra23/oimpresso.com/pull/5063); depois do
merge o log do `main` mostra `PASS`, de 0 → 3 ocorrências.

**Honestidade sobre a autoria da detecção:** quem disparou a checagem foi a pergunta *"testou?"*
do [W]. Eu tinha rodado o teste, mas não tinha perguntado **onde** ele roda.

## O que NÃO virou máquina nova

A defesa desta classe **já existia e funcionou**: o check `mcp_token_sem_permission`
([#4969](https://github.com/wagnerra23/oimpresso.com/pull/4969)) detectou os 4 users e emitiu
`ALERT` às 06:00. O buraco é **ouvinte do alarme**, não detector. Somar um 2º medidor seria
duplicar régua consolidada (§5 2026-07-09).

## Resíduos declarados (não corrigidos)

- `Modules/Jana/Resources/permissions.php` declara **12 dos 17** — faltam `cycles.manage`,
  `projects.manage`, `handoff.ack/lever/submit`. Não afeta o fix (que deriva do seeder).
- O checkout do CT 100 tem `Modules/TeamMcp/Tests/Feature/ForjaRoutesSmokeTmpTest.php` órfão de
  outra sessão que **quebra o bootstrap da suíte inteira lá** (`Cannot redeclare forjaRotasAbas()`).
  Não toquei — não é meu.
- O `main` **local** deste worktree diverge de `origin/main` com **7 commits nunca pushados**.
  Descoberto porque `gh pr merge --delete-branch` jogou o worktree em cima dele.

## Ref

Handoff: [`2026-07-30-1210-mcp-403-save-de-role-apagava-os-scopes.md`](../handoffs/2026-07-30-1210-mcp-403-save-de-role-apagava-os-scopes.md)
