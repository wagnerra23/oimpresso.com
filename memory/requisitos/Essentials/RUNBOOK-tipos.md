---
title: "RUNBOOK — Tipos de licença (Blade → Inertia · HRM-O7 PR-9)"
module: Essentials
tela: Essentials/Tipos
owner: W
status: ativo
last_validated: "2026-09-05"
preconditions:
  - "Rota, permission e menu JÁ existem — esta migração não cria alcance novo"
  - "PR #6789 mergeado: EssentialsLeaveTypeController::destroy responde 422 com blocked_by"
steps:
  - "F1 PLAN — este documento"
  - "F2 BACKEND BASELINE — Pest do index (tenant 98 vs adversário 99) antes de tocar a Page"
  - "F3 FRONTEND — Inertia::render + Tipos.tsx"
  - "F4 QA — lane essentials-pest (MySQL real), nunca local"
  - "F5 COEXISTÊNCIA — Blade legado preservado; cutover é decisão [W]"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
---

# RUNBOOK — Tipos de licença (`/hrm/leave-type`)

> **Migração Blade → Inertia/React** da lista de tipos de licença do HRM. É o **PR-9 (HRM-O7)**
> do pedido [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md).
> A tela não é afetada por D1/D2/D3 da emenda [W] de 2026-09-05: aquelas respostas movem
> **jornada** (presença/folha) para o `Modules/Ponto`; **licença** permanece no Essentials.

**Charter gate:** ADR 0104 (MWART — único caminho)
**Multi-tenant Tier 0:** ADR 0093 (`HasBusinessScope` em `EssentialsLeaveType`)
**Tenant de teste:** 98 canônico · 99 adversário (ADR 0358 — nunca biz=4, nunca biz=1)

---

## Tela migrada

| Rota | Blade legado | Component Inertia | Controller |
|---|---|---|---|
| `/hrm/leave-type` | `leave_type/index.blade.php` | `Essentials/Tipos.tsx` | `EssentialsLeaveTypeController@index` |

**Decisão registrada — tela própria, não subview.** No protótipo (`hrm-page.jsx`) "Tipos" é a 3ª
subview de um `Seg` dentro de Licenças. Aqui ela nasce **tela própria**, e a razão é o backend, não
o gosto: a rota é um `Route::resource` independente (`/hrm/leave-type`), a permission é própria
(`essentials.crud_leave_type`, distinta de `crud_all_leave`), e o menu já a lista como item
separado (`nav_hrm.blade.php:18`). O próprio export do Cowork a trata como onda com Page própria
(`EXPORT-HRM-2026-09-04.md`, onda 3). Fundir numa subview exigiria colapsar três gates de alcance
distintos num só — o oposto do que o módulo já faz.

**Decisão registrada — path flat.** `resources/js/Pages/Essentials/Tipos.tsx`, não
`Essentials/Hrm/Tipos/Index.tsx`. O gerador `criar-tela.mjs` (a régua canônica) só emite
`<Mod>/<Tela>.tsx`, e é ele que garante `pt-conformance` **por construção**. Reorganizar o layout
à mão desfaz essa garantia — que é exatamente o "fazer na mão é sorteio" que o gerador existe para
evitar. Custo assumido: as outras 9 Pages do Essentials são aninhadas. Ganho: a assinatura do PT-01
não depende de eu ter acertado à mão.

---

## F1 PLAN (Discovery)

O Blade legado (`leave_type/index.blade.php`) renderizava uma **DataTable jQuery server-side** que
chamava o próprio `index()` com `request()->ajax()`, e cujo botão de ação abria um **modal
Bootstrap** apontando para `edit()`. Colunas do datatable: `leave_type`, `max_leave_count`, ação.

**O que o protótipo acrescenta** (`hrm-page.jsx`, subview `tipos` — copy literal preservada):

| coluna | origem do dado |
|---|---|
| Tipo | `essentials_leave_types.leave_type` |
| Limite | `max_leave_count` → `"N dias"` \| `"sem limite"` |
| Intervalo | `leave_count_interval` → `"por ano"` \| `"por mês"` \| `"—"` |
| Pedidos no ano | `count(essentials_leaves)` do ano corrente, por tipo |

**A coluna "Limite" existe.** O export mandava conferir antes de renderizar (*"só entra se existir
em `essentials_leave_types` — senão `—`"*). Conferido na migration `2019_05_17_153306`:
`max_leave_count` (`integer nullable`) + `leave_count_interval` (`enum('month','year') nullable`).
Existe. Como as duas são nullable, o `—`/`"sem limite"` continua sendo o caminho para o valor
ausente — não para a coluna ausente.

**O que caducou no protótipo.** A `Nota` da subview afirma *"`EssentialsLeaveTypeController::destroy`
está vazio — o cadastro só cresce"*. Isso **era** verdade e deixou de ser: o
[PR #6789](https://github.com/wagnerra23/oimpresso.com/pull/6789) implementou o método. É
**protótipo atrasado**, não bug de produção — a tela reflete o `main`, e a nota não desce.

---

## F2 BACKEND BASELINE

`Modules/Essentials/Tests/Feature/HrmTiposIndexTest.php`, na lane `essentials-pest` (MySQL real).
Cobre o `index()` **antes** de a Page existir:

- 200 + componente Inertia correto para admin do tenant 98
- isolamento Tier 0: tipo do adversário 99 **não** aparece na lista do 98
- `leaves_count` conta licenças **do tenant**, nunca as do vizinho
- 403 sem `essentials.crud_leave_type`

A guarda de exclusão **já tem baseline próprio** e não se duplica aqui:
`HrmExclusaoGuardaTest.php` (do #6789) trava o 422 com `blocked_by.leaves`, o 404 cross-tenant e o
DELETE que de fato apaga.

---

## F3 FRONTEND

`Inertia::render('Essentials/Tipos')` com `tipos` em **`Inertia::defer`** (a query tem
`withCount` — prop cara, regra do `inertia-defer-default`) e `can_manage` eager (booleano).

O `request()->ajax()` do datatable legado **permanece** no `index()`: o Blade antigo ainda o
consome e a coexistência da F5 depende dele. O Inertia entra no caminho não-ajax, que hoje devolve
`view('essentials::leave_type.index')`.

**O 422 é o ponto da tela.** A exclusão usa `fetch` e não `router.delete`, porque o Inertia trata
422 como erro de validação e só expõe `errors` — `msg` e `blocked_by` se perderiam, e o usuário
veria "erro" genérico, tornando invisível o trabalho do #6789. Com `fetch`, o diálogo permanece
aberto exibindo **quantas licenças** travam a exclusão. Mesmo padrão de `Cliente/Index.tsx`.

---

## F4 QA

```bash
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=HrmTiposIndex"
```

Pest **nunca** roda local nem no Hostinger (`proibicoes.md` §Ambiente). O gate por-PR é a lane
`essentials-pest`, cuja allowlist precisa citar o arquivo — senão o teste existe e não roda.

---

## F5 — Coexistência (NÃO cutover)

O Blade `leave_type/index.blade.php` e o `nav_hrm.blade.php` **ficam**. O PR-10 do pedido (fim do
topnav Blade) e o HRM-O8 (limpeza das ~50 blades) são ondas próprias, posteriores ao screenshot
aprovado por [W]. Remover o legado aqui misturaria intents no mesmo PR.

---

## Tier 0 Checklist

- [x] `business_id` escopado no `index()` e no `withCount` das licenças
- [x] Tenant de teste 98 / adversário 99 — nunca biz=4 (ROTA LIVRE) nem biz=1
- [x] Permission `essentials.crud_leave_type` verificada antes de renderizar
- [x] Sem migration, sem coluna nova, sem rota nova
- [x] PT-BR em todo label, placeholder e mensagem

## Referências

- [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md) — HRM-O7 PR-9
- [`EXPORT-HRM-2026-09-04.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md) — onda 3
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
