---
id: resources-js-pages-sells-pos-index-charter
page: /pos
component: resources/js/Pages/Sells/Pos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/venda-blade.jsx (TelaPos)
related_us: [US-SELL-064]
related_runbook: memory/requisitos/Sells/RUNBOOK-pos.md
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Sells
related_adrs: [104, 93]
tier: B
charter_version: 1
---

# Charter — Lista de POS

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/ListaPos.charter.md` (2026-08-22), trazido para o lado do `.tsx` na thread 01 do playbook venda-menu (2026-10-01). Herda o Padrão de Tela PT-01 Lista.

**Fonte legado:** `sale_pos/index.blade.php + partials/sales_table` · **Permissão:** `sell.view (ver) · sell.create (abrir POS) · sell.delete (excluir)`
**Frescor:** medido no `main` em 2026-10-01: o `SellPosController@index` responde `Inertia::render('Sells/Pos/Index')` para o cliente Inertia; o GET comum segue no Blade até o cutover F5.

## Mission

Índice das vendas de balcão (`is_direct_sale = 0`) — o que a Larissa confere no fim do turno. Mesmas colunas do `sales_table`, mais o rodapé de totais que o DataTable calculava.

## Regras

- R1 A lista mostra só venda de POS; venda direta vive em "Todas as vendas" (`Sells/Index.tsx`, vivo).
- R2 Rodapé soma total, pago e em aberto **do filtro atual**, e conta por status de pagamento e por forma.
- R3 Linha vencida (`overdue`) recebe trilho de urgência; nunca cor crua.
- R4 "Adicionar pagamento" só aparece com saldo devedor.
- R5 Excluir exige `sell.delete`; sem a permissão o item aparece dizendo o motivo, não some.
- R6 Período filtra por `transaction_date` (o daterangepicker do blade).

## Non-Goals

- ❌ Não refazer o detalhe da venda: `Sells/Show.tsx` é vivo — o drawer daqui é ponte, não dono.
- ❌ Não emitir NF-e desta lista (é do `Sells/Index` vivo).

## Estado desta onda (2026-10-01)

- R2: total, pago e em aberto vêm do servidor (`totals` de `/sells-list-json`). A contagem por status e por forma **ainda não** está no rodapé — o endpoint não a devolve.
- Filtros de local, cliente, vendedor e tipo de serviço do protótipo ficam para a próxima onda (o endpoint não filtra por eles).

## Refs

- Casos: [`Index.casos.md`](Index.casos.md) · RUNBOOK: [`RUNBOOK-pos.md`](../../../../../memory/requisitos/Sells/RUNBOOK-pos.md)
- Alvo de design: `governance/design/targets/vendas--pos--index.secoes.json`
- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
