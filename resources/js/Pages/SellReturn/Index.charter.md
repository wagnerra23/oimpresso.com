---
page: /sell-return
component: resources/js/Pages/SellReturn/Index.tsx
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Sells
related_prototype: prototipo-ui/cowork/Wagner/vendas-extras.jsx
runbook: memory/requisitos/Sells/RUNBOOK-sell-return-index.md
alcance:
  rota: /sell-return
  rota_nome: n/a (Route::resource do núcleo em routes/web.php — o nome sell-return.index é implícito, sem ->name literal)
  permission: n/a (permissão do núcleo access_sell_return / access_own_sell_return, checada no corpo de SellReturnController@index; não vem de DataController::user_permissions)
  menu_hook: n/a (item do menu de Vendas montado pelo núcleo, não por DataController de módulo)
tier: B
charter_version: 1
---

# Charter — Devolução de venda · lista (`/sell-return`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Devolucao.charter.md`
> (2026-08-22), dividido em duas telas: esta é a **lista** (PR 1). O registro da devolução
> (`/sell-return/add/{id}` → `SellReturn/Add`) é o PR 2 e espera a REGRA MESTRE de
> `memory/proibicoes.md` (valor + estoque).

**Fonte legado:** `sell_return/index` (DataTable) · **Permissão:** `access_sell_return · access_own_sell_return`
**Desenho:** `vendas-extras.jsx` → `VendasDevolucoesPage` (`.vd-dev-page`: os-head · navegação · os-kpis · os-table-wrap). Alvo medido em `governance/design/targets/vendas--devolucao--index.secoes.json`.

## Missão

Mostrar as devoluções de venda já registradas: qual venda originou, de quem, quanto e se o
valor já foi pago ao cliente.

## Regras (desta tela)

- L1 A lista é a mesma do DataTable legado: `type=sell_return`, `status=final`, com venda de
  origem, só do business da sessão e só dos locais permitidos ao usuário.
- L2 Quem tem só `access_own_sell_return` vê apenas as devoluções que criou.
- L3 Os três números do topo são leitura do que já está gravado: devoluções com saldo a pagar,
  quantidade no mês e soma de `final_total` no mês. A tela não recalcula valor.
- L4 "Editar" abre o registro legado `/sell-return/add/{venda}`; o link vindo de `Sells/Index`
  não muda.

## Regras do registro (PR 2 — `SellReturn/Add`, não implementadas aqui)

- R1 A devolução é por LINHA: quantidade devolvida nunca maior que a vendida.
- R2 O total devolvido é somatório das linhas — a tela mostra antes de salvar.
- R3 Motivo é campo de texto livre e entra no histórico da venda.
- R4 Item produzido sob medida costuma não voltar: a tela avisa antes, não depois.

## Non-goals

- ❌ Não estornar pagamento aqui (é do Financeiro).
- ❌ Não registrar, editar nem excluir devolução nesta tela — escrita mexe em estoque e valor.
- ❌ Não inventar "status de análise", "motivo" ou "tipo de retorno" que o banco não guarda; o
  protótipo os mostra com dado de exemplo.

## UX Targets

- Cabe em 1280px sem rolagem horizontal.
- PT-BR em todo rótulo; termo canônico é **devolução**, nunca "estorno" (`memory/dominio/vendas.md`).

## Refs

- Casos: [`Index.casos.md`](./Index.casos.md) · Domínio: `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md`
- Runbook: `memory/requisitos/Sells/RUNBOOK-sell-return-index.md`
