---
id: requisitos-superadmin-runbook-negocios
title: "RUNBOOK — /superadmin/business (Negócios · DataTables → Inertia)"
module: Superadmin
tela: superadmin/Negocios/Index
owner: W
status: ativo
last_validated: "2026-08-19"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/business` (lista de negócios, Inertia/React)

F1 do MWART (ADR 0104) para a onda **SA-O2**. A tela servia DataTables por AJAX
(`superadmin::business.index` + `Datatables::of(...)`) e passa a `Inertia::render` com
paginação **server-side**.

- **Fonte de design:** `prototipo-ui/cowork/superadmin-page.jsx` → `ViewNegocios()` (L860+).
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx` — dentro do
  módulo, como decidido em 19/08. Namespace `superadmin/Negocios/Index`.
- **Rota:** `Route::resource('/business', BusinessController::class)` → `index()`.

---

## 1. Por que a query mudou (e não é cosmético)

O legado fazia `leftJoin('business_locations')` e corrigia a multiplicação com
`groupBy('business.id')`. Isso funciona para o DataTables, que conta à parte — mas **quebra o
`paginate()` do Laravel**, porque o `COUNT` passa a ser por grupo e o total vira o número de
grupos da página, não o da consulta.

Duas trocas resolvem, e as duas mudam a forma da linha:

| Antes | Agora | Por quê |
|---|---|---|
| `leftJoin('business_locations')` + `groupBy` | subquery escalar `(SELECT bl.city … LIMIT 1)` | negócio tem N locais; o join duplicava a linha |
| `leftJoin('subscriptions')` por vigência | join pela **mais recente** (`s.id = MAX(s2.id)`) | negócio tem N assinaturas históricas; sem isso, N linhas |

Resultado: **1 negócio = 1 linha**, e `paginate(20)` conta certo.

## 2. Os 4 filtros (paridade com o F1)

| Filtro | Query string | Implementação |
|---|---|---|
| Pacote | `pacote=<id>` | `where('p.id', …)` |
| Assinatura | `assinatura=vigente\|vencida\|sem` | vigência sobre a assinatura mais recente |
| Status do negócio | `status=ativo\|inativo` | `business.is_active` |
| Última venda | `venda=today\|yesterday\|this_week\|this_month\|this_year` | **reusa** `filterTransactionDate()`, o mesmo helper que servia o DataTables |

Valor fora da lista vira `null` (`opcaoValida`) — nada do request chega cru na query.

**A busca (`q`)** cobre nome, e-mail e nome do dono; o **número do negócio** só entra quando o
termo é dígito puro (`ctype_digit`), senão a comparação vira cast implícito e casa linha errada.

## 3. Quando esta tela quebra (sintomas)

- **Total certo, páginas erradas** — alguém reintroduziu um `join` 1-para-N sem subquery. O
  sintoma é a última página vir vazia ou o total oscilar ao paginar.
- **Filtro some ao paginar** — o `paginate()` precisa de `withQueryString()`, e o front precisa
  mandar os filtros atuais junto (é o que `irPara()` faz, mesclando sobre a base).
- **Busca por número não acha** — o termo tinha espaço ou caractere; só `ctype_digit` puro entra
  no `where('business.id')`.
- **Lentidão com filtro de última venda** — é o único que faz subquery em `transactions` por
  linha. Herdado do legado, não introduzido aqui; se doer, é o primeiro a otimizar.

## 4. Smoke prod (R1 — evidência, não narração)

```bash
curl -sv https://oimpresso.com/superadmin/business 2>&1 | grep '^< HTTP'
```

Esperado: `302` para `/login` sem sessão. Autenticado como superadmin: `200`, e o `data-page`
traz `"component":"superadmin/Negocios/Index"`.

Regressão adjacente (não podem mudar):

```bash
curl -sv https://oimpresso.com/superadmin 2>&1 | grep '^< HTTP'
curl -sv https://oimpresso.com/superadmin/usuarios 2>&1 | grep '^< HTTP'
```

## 5. Tier 0 — invariantes

- **Cross-tenant é intencional** (ADR 0093 §exceções Superadmin): a lista mostra TODOS os
  negócios por desenho. Nenhuma onda adiciona escopo de tenant aqui.
- A tela é **leitura**. Criar/editar/desativar é a SA-O3; mudar assinatura passa pelo
  `SubscriptionLifecycleService`, nunca por `update(['status' => …])`.
- Nenhum valor em R$ entra em log, PR ou commit.
- O enum de `subscriptions.status` **nunca** aparece cru — o mapa PT-BR é o mesmo do
  [RUNBOOK-dashboard §2](RUNBOOK-dashboard.md).

## 6. O que NÃO entrou nesta onda

- ~~**Drawer de detalhe (PT-02)**~~ e ~~**uso contra o teto do pacote**~~ — **entregues na
  SA-O2b** ([#5982](https://github.com/wagnerra23/oimpresso.com/pull/5982), 2026-08-19). O
  drawer é estado da lista (`?negocio=<id>`, partial reload), a linha é clicável e `esc`
  fecha. Teto `0` = ilimitado (confirmado por [W]) e não desenha barra.
- **Seleção múltipla + BulkBar** — depende das ações da SA-O3 (ver §7).
- **Ordenação por coluna** — hoje é `business.id` desc (mais recentes primeiro).

## 7. Pré-flight da SA-O3 (ações sobre o negócio) — **a onda não é o que parecia**

> F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)):
> medido em 2026-08-20 contra `origin/main`, antes de tocar qualquer `.tsx`.

O handoff descrevia a SA-O3 como *"create/edit/deactivate"*. A medição mostra que **a metade
`edit` não existe pra migrar** — é andaime quebrado do gerador do UltimatePOS, exposto como
rota viva atrás do middleware `superadmin`:

| rota (do `Route::resource('/business')`) | código | comportamento REAL |
|---|---|---|
| `GET /superadmin/business/{id}/edit` | `edit()` → `view('superadmin::edit')` | **500** — `Modules/Superadmin/Resources/views/edit.blade.php` **não existe** |
| `PUT/PATCH /superadmin/business/{id}` | `update(Request $request)` | **corpo vazio** — responde e não faz nada |

Recibo da ausência (claim negativa precisa do inventário, não do olho): `git ls-tree -r
origin/main --name-only Modules/Superadmin/` traz **4** `edit.blade.php`, todos em subpasta
(`packages/`, `pages/`, `superadmin_settings/`, `superadmin_subscription/`). Nenhum resolve
`superadmin::edit`, que aponta pra raiz de `Resources/views/`.

**Consequência pro escopo:** a SA-O3 é três coisas distintas, não uma.

1. **`create`/`store` — migração de verdade.** `create.blade.php` existe e `store()` tem ~90
   linhas com `StoreBusinessRequest`. É o único pedaço que é F3 clássico.
2. **`edit`/`update` — decisão [W], não migração.** Ou se implementa a edição (feature
   nova, precisa de charter e contrato) ou se **removem as duas rotas** do `Route::resource`
   via `->except(['edit','update'])`. Migrar não é opção: não há comportamento a preservar.
3. **`destroy`/`toggleActive` — migração com uma pergunta aberta.** Ambas estão registradas
   como **`GET`** (`/business/{id}/destroy` e `/{business_id}/toggle-active/{is_active}`).
   Ação destrutiva por GET é disparável por link, prefetch de browser e crawler autenticado.

   **Medido (2026-08-20):** `toggle-active` aparece em **2 lugares no repo inteiro** — a
   definição da rota e o método do controller. `business/{id}/destroy` idem. **Zero
   chamadores** em Blade, JS ou template. Verificado com controle positivo (o mesmo comando,
   com um padrão que eu sabia existir, retornou os 2 hits esperados — §5 proibicoes
   2026-08-01: vazio pode ser comando que falhou).

   Ou seja, trocar o verbo pra `POST`/`DELETE` **não quebra chamador nenhum hoje**. O que
   sobra é o risco de link externo/favorito, que o repo não enxerga.

**Nada disso foi corrigido nesta passagem** — é pré-flight, e o item 2 é soberania [W].

## 8. Refs

- Protótipo: [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx) `ViewNegocios()`
- Charter/casos: ao lado do `.tsx`
- Irmão: [RUNBOOK-dashboard.md](RUNBOOK-dashboard.md) — inclusive §6, o atrito conhecido do `visual-regression`
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
