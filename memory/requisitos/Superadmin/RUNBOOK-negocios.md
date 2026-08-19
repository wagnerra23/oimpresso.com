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

- **Drawer de detalhe (PT-02)** — SA-O2b. Clicar na linha não faz nada de propósito: melhor
  inerte do que abrir drawer vazio.
- **Seleção múltipla + BulkBar** — depende do drawer e das ações da SA-O3.
- **Uso contra o teto do pacote** (`Progress`) — precisa contar usuários/locais/produtos por
  negócio; é query nova e entra com o drawer.
- **Ordenação por coluna** — hoje é `business.id` desc (mais recentes primeiro).

## 7. Refs

- Protótipo: [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx) `ViewNegocios()`
- Charter/casos: ao lado do `.tsx`
- Irmão: [RUNBOOK-dashboard.md](RUNBOOK-dashboard.md) — inclusive §6, o atrito conhecido do `visual-regression`
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
