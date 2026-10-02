---
page: /sales-order
component: resources/js/Pages/SalesOrder/Index.tsx
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Sells
related_prototype: prototipo-ui/cowork/Wagner/venda-blade-telas.jsx
related_runbook: memory/requisitos/Sells/RUNBOOK-sales-order.md
tier: B
charter_version: 1
---

# Page Charter — Pedido de venda (`/sales-order`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/PedidoVenda.charter.md`
> (frescor medido em 2026-08-22: a tela ainda era Blade). Thread 06 do playbook `venda-menu`.
> **Fonte legado:** `sales_order/index` + `edit_status_modal` · **Permissão:** `so.view_own ·
> so.view_all · so.create` (item de menu condicional a `enable_sales_order`).
> **Protótipo:** `VendaPedidos` em `venda-blade-telas.jsx` · alvo medido
> `governance/design/targets/vendas--pedidos--index.secoes.json` (header · tabs · filtros · lista).

## Mission

Pedido que ainda não virou venda: acompanha status e quantidade restante a atender.

## Regras

- R1 O item só existe no menu com `enable_sales_order` ligado nas configurações do POS
  (`AdminSidebarMenu`, inalterado). A tela avisa quando o recurso está desligado.
- R2 Status do pedido: pedido → parcial → concluído; muda por drawer lateral (PT-02), nunca
  por modal full-screen. Só quem o endpoint marca como editável (admin, status ≠ concluído).
- R3 "Quantidade restante" é o que falta faturar do pedido.
- R4 Gerar venda a partir do pedido não apaga o pedido (fluxo no Create de venda, fora desta tela).

## Goals — Features (faz)

- Lista de pedidos com filtros local · cliente · status · status de envio.
- Busca local por nº do pedido ou cliente.
- Editar status pelo drawer, refletindo na linha sem recarregar a lista.
- "Adicionar pedido" só para quem tem `so.create`.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Rota nova: lista e status usam os endpoints que já existem.
- ❌ Gerar venda a partir do pedido nesta tela.
- ❌ Excluir pedido nesta tela.

## UX Targets

- Cabe em 1280px sem scroll horizontal da página (a tabela rola dentro do card).

## Refs

- Casos: `Index.casos.md` ao lado.
- Padrão de Tela: PT-01 Lista · drawer de status PT-02.
- Constituição UI v2: UI-0013.
