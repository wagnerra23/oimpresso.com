---
id: resources-js-pages-essentials-metas-charter
page: /hrm/sales-target
component: resources/js/Pages/Essentials/Metas.tsx
owner: wagner
status: draft
last_validated: "2026-09-05"
parent_module: Essentials
related_prototype: prototipo-ui/cowork/hrm-extras.jsx (Metas) · herda PT-01 Lista
related_adrs: [104, 93, 358]
runbook: RUNBOOK-metas.md
alcance:
  rota: /hrm/sales-target
  rota_nome: (sem name — a rota é anônima em Modules/Essentials/Routes/web.php:99)
  permission: essentials.access_sales_target
  menu_hook: Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php
  pacote: essentials_module
tier: B
charter_version: 1
---

# Page Charter — Essentials/Metas (`/hrm/sales-target`)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança de padrão).
> F1 do MWART: [`RUNBOOK-metas.md`](../../../../memory/requisitos/Essentials/RUNBOOK-metas.md).
> Contrato de teste ao lado: [`Metas.casos.md`](Metas.casos.md).
>
> Onda 9 do `EXPORT-HRM-2026-09-04` · PR-9 do `PEDIDO-CL-hrm`. Substitui
> `Modules/Essentials/Resources/views/sales_targets/index.blade.php` (2 colunas + modal jQuery).

## Mission

Deixar o administrador ver, numa tela só, **quem tem meta de venda cadastrada e qual faixa paga
qual comissão** — e editar essas faixas sem abrir um colaborador por vez.

## Goals — Features (faz)

- Lista os colaboradores do business (`allow_login = 1`) com as faixas **já gravadas**: quantas
  são, o valor inicial da menor, o final da maior e o percentual (ou intervalo) que pagam.
- Busca server-side por nome, usuário ou e-mail (`?q=`), com o mesmo predicado da lista legada.
- Diálogo de edição das faixas (vendido de · até · comissão %), com adicionar e remover linha.
- Mostra o erro do servidor quando o conjunto de faixas é recusado, dizendo **qual** faixa e
  **por quê** (mensagem do `SalesTargetFaixaValidator`).
- Declara, na própria tela, que a apuração do realizado **não acontece aqui**.
- PT-BR em todo label, placeholder e mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ **Não calcula comissão.** Nenhuma aritmética de valor roda nesta tela. Quem transforma
  faixa em dinheiro é o `PayrollController`; um segundo cálculo aqui divergiria do que a folha
  paga.
- ❌ **Não apura o realizado.** `Mês anterior`, `Mês atual`, `Faixa atingida`, `Progresso na
  faixa` e `Comissão em dinheiro` — as 5 colunas de apuração do protótipo — ficam **fora desta
  onda**. O único produtor desse número é `DashboardController::getUserSalesTargets`, que é
  admin-only e responde DataTables. Trazê-lo é caminho de valor e exige a dupla prova da regra
  mestre (`memory/proibicoes.md`): PR próprio.
- ❌ **Não envia float** para `/hrm/save-sales-target`. Os valores vão como texto pt-BR de 2
  casas (`formatDecimalPtBR`), porque `Util::num_uf` é parser pt-BR — float cru com mais de 2
  decimais é lido como separador de milhar (incidente 2026-06-05).
- ❌ **Não renomeia os campos do POST.** `montarFaixas` lê `edit_target[id][...]`,
  `sales_amount_start[]`, `sales_amount_end[]` e `commission[]` literalmente.
- ❌ **Não remove o ramo `request()->ajax()`** do `index` — o DataTables da Blade legada
  consome a mesma rota até a HRM-O8.
- ❌ **Não cria rota, migration nem permission nova.**

## Automation Anti-hooks

- ❌ Nenhum agente pode afrouxar `SalesTargetFaixaValidator` (fim > início · sem sobreposição ·
  percentual 0–100) para "deixar salvar". As três regras vêm de defeitos medidos na query do
  `PayrollController`.
- ❌ Nenhum agente pode remover o gate Tier 0 de `saveSalesTarget`
  (`User::where('business_id', ...)->findOrFail($request->user_id)`). O global scope filtra
  `SELECT`, não `INSERT` — sem ele, o `user_id` cru do body cria meta no colaborador de outro
  tenant. Provado por `SalesTargetShiftCrossTenantTest`.
- ❌ Nenhum agente pode usar `biz=4` (ROTA LIVRE) em teste, fixture ou smoke desta tela.

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE).
- Lista atrás de `Inertia::defer` + `Skeleton` — a prop é `paginate()` + `whereIn` (Tier 0
  desde 2026-05-15).
- Colaborador sem faixa é estado legítimo: `Badge` "sem meta" e travessão, nunca zero fabricado.

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros) · Constituição UI v2 UI-0013
- [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) MWART ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) multi-tenant Tier 0 ·
  [ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) tenant 98
- Regra mestre de valor: `memory/proibicoes.md`
