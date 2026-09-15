---
page: /shipments
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Remessas

**Fonte legado:** `sell/shipments.blade.php` · **Permissão:** `access_shipping · access_own_shipping · access_commission_agent_shipping`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Fila de entrega: quem leva, em que status e com qual documento. É a tela do entregador e de quem monta o romaneio.

## Regras
- R1 Status de envio e entregador pertencem à **transação** — editar aqui atualiza a venda, não cria documento novo.
- R2 Filtros do blade: local, cliente, período, usuário, status de pagamento, status de envio e entregador.
- R3 Romaneio imprime sem preço (`packing_slip`); nota de entrega imprime com assinatura (`delivery_note`).
- R4 Campos personalizados de remessa (`custom_labels.shipping.custom_field_1..5`) aparecem só quando o negócio os nomeia.

## Non-goals
- ❌ Não criar entrega avulsa sem venda.
