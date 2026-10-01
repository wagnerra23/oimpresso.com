---
slug: sells-runbook-sell-return-index
title: "Sells — Runbook da lista de devoluções /sell-return (migração MWART, PR 1 de 2)"
type: runbook
module: Sells
tela: SellReturn/Index
status: ativo
owner: W
last_validated: "2026-10-01"
date: 2026-10-01
preconditions:
  - "Permissão access_sell_return OU access_own_sell_return"
  - "Escopo business_id da sessão em toda query (ADR 0093)"
steps:
  - "Ramo X-Inertia em SellReturnController@index ANTES do ramo ajax() do DataTable"
  - "Page resources/js/Pages/SellReturn/Index.tsx no desenho VendasDevolucoesPage"
  - "Contrato Pest em tests/Feature/Sells/SellReturnIndexContratoTest.php na lane sells-pest"
---

# RUNBOOK — Lista de devoluções (`/sell-return`)

> **Tipo:** runbook MWART (Blade → Inertia/React), **só a lista**.
> **Thread:** `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/01-telas-legadas.md` §03 — PR 1 de 2.
> **Refs:** ADR 0104 (MWART) · ADR 0093 (multi-tenant) · `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md` (domínio).
> **Por que mora em `Sells/`:** a devolução é domínio de Vendas e o contrato dela já vive aqui
> (`CASOS-USO-DEVOLUCAO.md`). A pasta de Pages é `SellReturn/` porque o controller é
> `SellReturnController`; o charter aponta este arquivo pelo campo `runbook:`, que o hook
> `block-mwart-violation` aceita depois de conferir que o arquivo existe. Abrir
> `memory/requisitos/SellReturn/` criaria um "módulo" de requisitos sem SPEC só para
> carregar um RUNBOOK.

## 1. Objetivo

Servir a lista de devoluções em React quando a visita é Inertia, sem tocar no registro de
devolução (`add`/`store`), que move **valor** e **estoque** (REGRA MESTRE de
`memory/proibicoes.md`). A Blade continua sendo a resposta da carga completa de página; o
cutover (F5) é humano.

## 2. Pré-condições

- [x] Permissão `access_sell_return` (vê todas) ou `access_own_sell_return` (vê as próprias).
- [x] Ramo `request()->ajax()` do DataTable legado preservado byte a byte.
- [x] Escopo `business_id` + locais permitidos + filtro "só as minhas" iguais ao DataTable.

## 3. Passo a passo

### 3.1 Controller — ramo Inertia antes do ramo ajax

O cliente Inertia manda `X-Inertia` **e** `X-Requested-With: XMLHttpRequest`. Se o teste do
`X-Inertia` viesse depois do `if (request()->ajax())`, a visita Inertia receberia o JSON do
DataTable. Por isso o ramo novo fica logo depois de `$business_id`.

Props:

| prop | tipo | carga |
|---|---|---|
| `kpis` | `{com_saldo, no_mes, valor_mes}` | `Inertia::defer` (grupo `lista`) |
| `devolucoes` | `{linhas[], total, limite}` | `Inertia::defer` (grupo `lista`) |
| `permissions` | `{ver_todas, ver_proprias}` | eager (booleano barato) |

Os KPIs são leitura: `COUNT` e `SUM(final_total)` sobre devoluções já gravadas. Nenhum cálculo
de valor muda.

### 3.2 Front — `resources/js/Pages/SellReturn/Index.tsx`

Desenho `VendasDevolucoesPage` de `prototipo-ui/cowork/Wagner/vendas-extras.jsx`
(alvo `governance/design/targets/vendas--devolucao--index.secoes.json`): `os-head` ·
navegação de Vendas · `os-kpis` (3) · `os-table-wrap`. Classes de `resources/css/sells-cowork.css`
sob o wrapper `.sells-cowork`, como `Sells/Caixa/Index.tsx`.

### 3.3 Teste — `tests/Feature/Sells/SellReturnIndexContratoTest.php`

Lane `sells-pest.yml` (allowlist no próprio workflow). Tenant 98 × 99, `DatabaseTransactions`.

## 4. Fora deste PR

- **PR 2 — `SellReturn/Add`** (`GET /sell-return/add/{id}` + `store`): espera a REGRA MESTRE
  (prova dupla + tabela antes→depois + aprovação [W]).
- Ações que escrevem (excluir devolução, adicionar pagamento) e impressão seguem na Blade.

## 5. Rollback

Remover o ramo `if (request()->header('X-Inertia'))` do `index()`. A Blade nunca deixou de
responder à carga completa.
