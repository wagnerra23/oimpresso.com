---
id: resources-js-pages-sells-shipments-index-charter
page: /shipments
component: resources/js/Pages/Sells/Shipments/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/venda-blade.jsx — função TelaRemessas, rota venda-remessas (alvo medido em governance/design/targets/vendas--remessas--index.*)
related_runbook: memory/requisitos/Sells/RUNBOOK-shipments.md
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Sells
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — Remessas (`/shipments`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Remessas.charter.md`
> (Cowork, 2026-08-22), trazido pra cá na thread 02 do playbook venda-menu (2026-10-01).
> **Fonte legado:** `sell/shipments.blade.php` · **Permissão:** `access_shipping · access_own_shipping · access_commission_agent_shipping`
> **Padrão de Tela:** PT-01 Lista + drawer PT-02 de edição (o modal Blade de status vira Sheet lateral).

## Mission

Fila de entrega: quem leva, em que status e com qual documento. É a tela do entregador e de quem monta o romaneio.

## Regras

- R1 Status de envio e entregador pertencem à **transação** — editar aqui atualiza a venda, não cria documento novo. A escrita segue em `PUT /sells/update-shipping/{id}`; nenhuma rota nova.
- R2 Filtros do blade: local, cliente, período, usuário, status de pagamento, status de envio e entregador.
- R3 Romaneio imprime sem preço (`packing_slip`); nota de entrega imprime com assinatura (`delivery_note`).
- R4 Campos personalizados de remessa (`custom_labels.shipping.custom_field_1..5`) aparecem só quando o negócio os nomeia.

## Goals — Features (faz)

- Lista paginada no servidor a partir do mesmo DataTables do Blade (`GET /sells?only_shipments=true`), com os filtros de R2 e busca por fatura ou cliente.
- Ações por linha: Editar remessa (drawer), Imprimir romaneio (com `print_invoice`), Ver venda (com permissão de ver venda).
- Drawer de edição com status de envio, entregador, entregue a, detalhes de envio, endereço de entrega, campos personalizados (R4) e observação da alteração.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Não criar entrega avulsa sem venda.
- ❌ Não abrir modal full-screen para detalhe — detalhe/edição é drawer lateral.

## UX Targets

- Cabe em 1280px; a tabela rola na horizontal dentro do card quando o negócio nomeia campos personalizados.
- Célula sem dado mostra "—", nunca vazio mudo.

## Fora desta versão (declarado, segue no Blade/`Sells/Show`)

- Upload e lista de documentos da remessa (`shipping_document`) e o histórico de atividades do modal.
- Filtro de garçom (`service_staffs`), que só existe com o módulo `service_staff`.

## Refs

- Casos: [`Index.casos.md`](./Index.casos.md) · RUNBOOK: [`RUNBOOK-shipments.md`](../../../../../memory/requisitos/Sells/RUNBOOK-shipments.md)
- Padrão de Tela: PT-01 Lista · PT-02 Form/Drawer · Constituição UI v2 (UI-0013)
