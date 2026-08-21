# Sessão 2026-08-20 — Acessos: backend de `/roles` e `/sales-commission-agents`

> Intake: `cowork-inbox/acessos/PEDIDO-PARA-CODE.md` ([CC] → [CL]), escopo aprovado por [W].
> Handoff do fechamento: [`2026-08-20-2124-acessos-backend-mergeado-telas-bloqueadas-por-transporte.md`](../handoffs/2026-08-20-2124-acessos-backend-mergeado-telas-bloqueadas-por-transporte.md).
> ⚠️ MCP fora o tempo todo (timeout no `brief-fetch`) — sessão inteira pelo fallback filesystem.

## O que foi feito

8 PRs em `main`. Cinco eram o pedido (PR-1 a PR-5); três não estavam nele.

| PR | Entrega |
|---|---|
| #5959 | `config/app.php` deixa de cair em `Europe/London`; o env de exemplo deixa de ser `Asia/Kolkata` |
| #5960 | fuso de um negócio para de vazar para o resto do job — `RecurringExpense`, `RecurringInvoice`, **`Util::activityLog`** e (fora de escopo, registrado) `WoocommerceSyncProducts` |
| #5962 | **Tier 0** — `RoleController::update()` sem filtro de `business_id` |
| #5964 | catálogo FECHADO de permissões (151 core derivadas das views + módulos + 1 padrão dinâmico) e o grupo de preço que sumia no `store()` |
| #5970 | comissionado com venda vinculada não é mais excluível (422 com a contagem) |
| #5971 | papel em uso não é mais excluível (422 com a contagem) |
| #5972 | ledger **LC-26** |
| #6025 | o caso `destroy` do #5962 estava verde **sem executar o controller** |

## Verificar o intake antes de codar rendeu 3 correções

O documento do [CC] foi conferido contra o código. Cinco claims bateram; três não:

1. **`SalesCommissionAgentController::destroy()` não é hard delete** — `App\User` usa `SoftDeletes`.
2. **A varredura de fuso estava incompleta** — 4 sites, não 2. O que faltava é o pior:
   `app/Utils/Util.php:1572`, dentro do `activityLog`, que roda no ERP inteiro. E
   `SocialAuthController:162`, que o doc listava, **não é vazamento** — é `'time_zone' =>
   'America/Sao_Paulo'` num array de criação de negócio, outra categoria.
3. **Grupo de preço é `spg_permissions[]` e é checkbox**, não radio. O `store()` lia `radio_option`
   duas vezes (duplicando os *radios*) e **nunca lia `spg_permissions`** — ao criar papel, o grupo
   marcado era **perdido**. O `update()` sempre esteve correto.

E os 10 artefatos que o doc chamava de "prontos" **não estavam no git**: vivem em
`cowork-inbox/acessos/repo/` no Cowork, nunca desceram. O `TimezoneGuardTest` de lá tinha 3 defeitos
(regex que não compila — `Unterminated group`; `.env.example` inexistente; baselines que não batiam
com o corpus). Copiar fiel teria entrado quebrado.

## O achado que vale mais que os PRs

**Três testes desta leva passaram pelo MOTIVO ERRADO.** Nas três vezes, quem denunciou foi o **caso
positivo** ao lado — nunca o negativo.

| Teste | Por que o negativo passava sem provar nada |
|---|---|
| `RoleTenantIsolationTest` | `403` — a instância de usuário do `assignRole()` carrega `roles` em memória e o `actingAs` usava esse retrato |
| `RolePermissionCatalogTest` | mesma causa, no helper irmão |
| `RoleDeleteGuardTest` + comissionado | faltava `X-Requested-With` — o `destroy()` inteiro vive dentro de `if (request()->ajax())` |

Em todos, *"o papel alheio ficou intacto"* é igualmente verdade **quando o controller não roda**.

**Regra que fica:** num teste de bloqueio, quem prova o bloqueio é o **caso positivo**; o negativo
sozinho é indistinguível de uma requisição que nunca chegou. E, específico do UltimatePOS:
`deleteJson`/`postJson` precisam do header `X-Requested-With: XMLHttpRequest`, porque o padrão do
repo é `request()->ajax()`, **não** `wantsJson()` — sem ele o método devolve **200 vazio**.

## A lane que não existia

Varredura das **17** lanes `*-pest.yml`: nenhuma executava `tests/Feature/Roles/` nem
`tests/Feature/Users/`. Sem lane, teste nasce mudo — e isso foi **provado ao vivo**, não deduzido:

| Momento | Resultado |
|---|---|
| Antes do wiring | `23 passed (59 assertions)` · **0 menções** ao `TimezoneGuardTest` no log |
| Com o wiring, `ParseError` meu | job falha `exit 1`, apontando arquivo e linha |
| Depois do fix | `30 passed (71 assertions)` — **+7**, exatamente os casos escritos |

Daí nasceu `acessos-pest.yml` (advisory, registrada em `gates-registry.json` e no
`MAQUINAS-INVENTARIO`, como os dois donos de inventário exigem).

## Erros meus, e o que cada um ensinou

- **LC-26 (registrado)** — par de barra invertida **colapsa** no transporte da escrita: `'\\'` virou
  `'\'` e abriu a string (`ParseError`). Barra **solteira** sobrevive, o que torna o defeito
  seletivo. Consertado com `DIRECTORY_SEPARATOR`, que **remove a barra** em vez de reescapá-la. E
  reincidiu no mesmo dia, ao contrário: escrevi **duas** onde o certo era uma.
- **Merge não valida esquema** (§5 2026-08-05) — duas vezes no mesmo arquivo: import duplicado e
  depois **método duplicado** (`Cannot redeclare __somenteDoCatalogo`), este último um **fatal** que
  matava o processo antes de qualquer output. Quem diagnosticou foi o **PHPStan**; o Pest morre
  antes de imprimir. Conferi o bloco de imports depois do merge e **não** conferi o corpo da classe.
- **Diagnóstico do gate visual, errado 3×** — afirmei que estava "quebrado" e recusei o label que
  [W] havia autorizado. A causa real: o `afterAll` do `FinanceiroFlowBaselineTest` lança exceção de
  propósito na zona cinza, e o PHPUnit **engole** o `Throwable` (só o XML logger o renderiza) → `exit
  2` mudo com `12 passed`. A zona cinza era **real** (`0,1158%`). Li o resumo do Pest em vez de abrir
  o `afterAll`.
- **`is_cmmsn_agnt` é bool** — `tinyint(1)`, o Larastan recusa `int` na atribuição de propriedade.
- **Tenant de teste é o 98** (ADR 0358 / R6) — escrevi em `biz=1` e o `firstOrFail` estourou, porque
  a action `pest-mysql-setup` só semeia `business_locations`/`contacts` para o 98.

## Fila do repositório, destravada de passagem

[W] pediu "o que falha, do jeito que a Maiara olha". A primeira versão do painel classificou pelo
**nome do gate** — e não explicava nada. Ao abrir o log de um PR, a causa real apareceu: dos 24
abertos, **8 estavam parados reclamando de problema já consertado**, entre **31 e 221 commits** atrás.
O `casos-coverage-guard` em `main` fresco passa, e o commit `786ce9bb2d` diz no título *"main estava
vermelha e travava todo PR"*.

`gh pr update-branch` destravou 4. [W] aprovou a zona cinza e o label entrou no #5937/#5907
(`VISREG_GRAY_APPROVED: 1` no run). O #6007 foi **desarmado** do auto-merge: os 14 arquivos dele já
estão nos 74 do #6032 — verificado com paths normalizados, porque `git show --name-only` escapa
acento em octal e a primeira comparação deu **falso negativo**.

Painel para o time: <https://claude.ai/code/artifact/c2bf5175-801b-4868-90d5-5ff136219a12>

## O que ficou (chips abertos)

PR-6/7/8 (telas) — bloqueadas pelo teto de transporte do design, o mesmo do handoff das 11:38;
remédio é `sync/payload.acessos.json` parcial. D-A e D-B — decididas por [W], exigem migration com
antes→depois. Drift `PHP / Pest (KB · MySQL)` — pré-existente, decisão de [W].
