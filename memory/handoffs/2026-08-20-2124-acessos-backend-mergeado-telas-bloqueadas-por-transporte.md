---
date: "2026-08-20"
time: "21:24"
slug: acessos-backend-mergeado-telas-bloqueadas-por-transporte
tldr: "Os 5 PRs de backend do grupo Usuários (/roles, /sales-commission-agents) estão no main, mais 3 que não estavam no pedido — incluindo um furo Tier 0 que deixava editar papel de outro negócio. As telas (PR-6/7/8) estão bloqueadas pelo MESMO transporte de design do handoff das 11:38. Achado transversal: 3 testes desta leva passavam pelo MOTIVO ERRADO, e quem denunciou foi sempre o caso positivo."
decided_by: [W]
prs: [5959, 5960, 5962, 5964, 5970, 5971, 5972, 6025]
next_steps:
  - "Design emitir sync/payload.acessos.json com os 9 arquivos de prototipo-ui/cowork/acessos/ + deps (cabe no transporte, mesmo remédio do handoff 11:38)"
  - "Com ele: aplicar-payload --dry --require-complete-shell, revisar, aplicar, e então o trio da tela Roles/Index"
  - "D-A (permissão própria de comissionado) e D-B (unificar) exigem migration com antes→depois — chips abertos"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0344-two-strikes-cobre-processo
---

# Handoff — Acessos: backend no `main`, telas bloqueadas por transporte

## Estado no fechamento

| PR | O que entrou | Estado |
|---|---|---|
| #5959 | fuso padrão deixa de ser `Europe/London` (e o exemplo, `Asia/Kolkata`) | mergeado |
| #5960 | fuso de um negócio para de vazar para o resto do job — **4 sites**, não 2 | mergeado |
| #5962 | **Tier 0**: papel de outro negócio deixa de ser editável via `/roles/{id}` | mergeado |
| #5964 | catálogo FECHADO de permissões + grupo de preço que sumia no create | mergeado |
| #5970 | excluir comissionado com venda vinculada passa a ser bloqueado | mergeado |
| #5971 | excluir papel em uso passa a ser bloqueado, com a contagem | mergeado |
| #5972 | ledger **LC-26** (par de barra invertida colapsa no transporte da escrita) | mergeado |
| #6025 | o caso `destroy` do Tier 0 estava verde **sem executar o controller** | mergeado |

## Estado MCP no momento do fechamento

⚠️ **O servidor MCP não respondeu nesta sessão.** O hook de `SessionStart` registrou
`[brief-fetch hook] FALLBACK ATIVADO — motivo: servidor MCP não respondeu no tempo (timeout)`, e a
sessão rodou pelo **fallback filesystem** — caminho legítimo previsto em
[`how-trabalhar.md`](../how-trabalhar.md) §Fallback. Confirmei ao fechar: as tools do projeto não
estão registradas nesta sessão (`ToolSearch` devolve só `scheduled-tasks`).

Não há snapshot de `cycles-active` / `my-work` / `sessions-recent` aqui, porque nenhuma respondeu.
Inventar um seria pior que registrar a ausência. Quem retomar roda `brief-fetch` antes de assumir
estado de cycle/task.

Fonte de estado que substituiu o MCP: `gh pr view/list`, `git log origin/main`, e os gates do CI.

## Três correções ao pedido de origem (`cowork-inbox/acessos/PEDIDO-PARA-CODE.md`)

O intake do [CC] foi verificado contra o código antes de qualquer linha. Cinco claims conferiram;
três não:

1. **Não era hard delete.** `App\User` usa `SoftDeletes` — o registro era recuperável. A guarda
   ainda se justifica (a venda perde o nome do agente), mas o risco era menor que o descrito.
2. **A varredura de fuso estava incompleta**: 4 sites, não 2. Faltava `app/Utils/Util.php:1572`,
   dentro do `activityLog`, que roda no ERP inteiro — o pior dos quatro.
3. **Grupo de preço é `spg_permissions[]` e é checkbox**, não radio. O `store()` lia `radio_option`
   duas vezes (duplicando os *radios*) e **nunca lia `spg_permissions`** — ao criar um papel, o
   grupo marcado era **perdido**. O `update()` sempre esteve certo.

## Dois achados que não estavam no pedido

**Tier 0 (#5962).** `RoleController::update()` carregava o papel com `Role::findOrFail($id)` sem
filtrar `business_id`. O `Role` é o Spatie puro (`config/permission.php:27`), sem global scope, e
nenhum provider registra um. Varredura contada: dos **7** acessos a `Role` no controller, **6 já
filtravam** — só o `update()` não. Omissão pontual, não desenho.

**A lane `acessos-pest` não existia.** Varredura das 17 lanes `*-pest.yml`: nenhuma executava
`tests/Feature/Roles/` ou `tests/Feature/Users/`. Sem ela o teste nasce mudo — e isso não é teoria:
o `TimezoneGuardTest` **passou verde no CI sem ser executado** (0 menções no log, `23 passed`).
Depois do wiring, o mesmo job falhou com um `ParseError` real meu; com o fix, `30 passed
(71 assertions)`.

## O achado transversal — e é o que eu levaria desta sessão

**Três testes desta leva passaram pelo MOTIVO ERRADO**, e nas três vezes quem denunciou foi o
**caso positivo ao lado**:

| Onde | Por que o negativo passava sem provar nada |
|---|---|
| `RoleTenantIsolationTest` | `403` por cache de permissão do Spatie — a requisição nem chegava ao `update()` |
| `RolePermissionCatalogTest` | mesma causa, no helper irmão |
| `RoleDeleteGuardTest` + comissionado | faltava `X-Requested-With` — o `destroy()` inteiro vive dentro de `if (request()->ajax())` |

Em todos, *"o papel alheio ficou intacto"* é igualmente verdade **quando o controller não roda**.
Num teste de bloqueio, **quem prova o bloqueio é o caso positivo**; o negativo sozinho é
indistinguível de uma requisição que nunca chegou.

Corolário operacional, para quem escrever teste de rota do UltimatePOS: o padrão do repo é
`request()->ajax()`, **não** `wantsJson()` — `deleteJson`/`postJson` precisam do header
`X-Requested-With: XMLHttpRequest`, senão o método cai no fim e devolve **200 vazio**.

## O que bloqueia as telas (PR-6/7/8)

**O mesmo transporte do handoff das 11:38** — e a mesma conclusão, medida de novo por outro
caminho:

| Caso | Medido | Serve? |
|---|---|---|
| `funcoes-page.jsx` (~15 KB), `funcoes-perms.jsx` (~20 KB), `acessos-page.css` (~14 KB) | vêm **inline** no contexto | ❌ escrever = transcrição (§5 2026-08-11) |
| `sync/payload.json` (3.504.544 bytes) | **persiste em disco**, mas `truncated: true` → 271 KB | ⚠️ 7,7% |

O limiar de persistência do harness fica entre ~20 KB e ~76 KB — os 9 arquivos de `acessos/` estão
todos **abaixo** dele.

**O remédio é o do handoff das 11:38, e é melhor que fatiar os 3,5 MB:** um payload **parcial da
área** — `sync/payload.acessos.json` com os 9 de `prototipo-ui/cowork/acessos/` + deps. Cabe no
transporte de uma vez.

## Fila do repositório — destravada de passagem

Ao investigar por que os PRs não entravam, a causa acabou sendo outra: dos 24 abertos, **8 estavam
parados reclamando de um problema já consertado**. Estavam entre **31 e 221 commits atrás** de
`main`; o `casos-coverage-guard` rodado em `main` fresco passa (`✅ Sem violações novas`), e o
commit `786ce9bb2d` diz no título *"main estava vermelha e travava todo PR"*.

`gh pr update-branch` destravou 4 (#6007, #6005, #5943, #5940). [W] aprovou a zona cinza visual e o
label `visreg-gray-approved` foi aplicado no #5937 e #5907 — o run confirmou `VISREG_GRAY_APPROVED: 1`.

⚠️ **Correção de rota registrada:** durante a sessão afirmei 3× que o `visual-regression` estava
"quebrado" e recusei aplicar esse label. Estava **errado** — o `afterAll` do
`FinanceiroFlowBaselineTest` lança exceção de propósito quando há zona cinza sem aprovação, e o
PHPUnit **engole** o `Throwable` (só o XML logger o renderiza), o que produz `exit 2` mudo com
`12 passed`. A zona cinza era real (`0,1158%`). O #5994 conserta o silêncio; o #6032 (74 arquivos)
já refez as baselines em `main` e torna o #6007 **redundante** (14 de 14 arquivos dele já estão lá —
verificado com os paths normalizados, porque `git show --name-only` escapa acento em octal e a
primeira comparação deu falso negativo).

## Pendências (chips abertos)

- **Traduzir /roles para Inertia (PR-6)** — bloqueado pelo transporte acima. Tamanho real medido:
  não é uma tela, são duas (grid + editor de tela cheia, rail de domínios, busca global, barra de
  diferenças, 5 formas de controle sobre 53 grupos e ~400 permissões).
- **D-A — permissão própria de comissionado** — decidida por [W]; exige migration de backfill,
  senão todo papel com `user.*` perde acesso no deploy.
- **D-B — unificar comissionado** — decidida por [W] **sem dedupe automático**; só o caminho novo.
- **Drift de governança** — `PHP / Pest (KB · MySQL)` está no `required-checks-baseline.json` e não
  na proteção viva. **Pré-existente** (provado contra retrato tirado antes da manutenção).

## Nota de operação — branch protection

Para mergear #5970/#5971 com o gate visual vermelho, [W] autorizou desligar a proteção. Feito pela
via **mais estreita**: só o endpoint `enforce_admins`, **sem tocar a lista de contexts** — que é
onde o mojibake deadlockou o repo em julho. Restaurado em seguida e verificado **byte a byte**:
44 → 44 contexts idênticos, zero `Ã`/`Â`/`â`. Contagem sozinha não prova nada (em julho ela se
manteve e os nomes é que estavam podres).
