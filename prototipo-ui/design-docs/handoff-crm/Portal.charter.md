---
id: resources-js-pages-crm-portal-charter
page: /contact/contact-dashboard
component: resources/js/Pages/Crm/Portal.tsx
related_prototype: prototipo-ui/cowork/crm-portal.jsx
owner: wagner
status: draft
last_validated: "2026-08-24"
parent_module: Crm
related_adrs: [93, 264, 286]
tier: A
charter_version: 1
related_blade: Modules/Crm/Resources/views/{dashboard,profile,purchase,sell,ledger,booking,order_request}/*
related_controller: Modules/Crm/Http/Controllers/{DashboardController,ManageProfileController,PurchaseController,SellController,LedgerController,ContactBookingController,OrderRequestController}.php
---

# Page Charter — /contact/* (DRAFT)

> **Status:** draft — é a **outra persona** do módulo: o cliente logado (middleware `ContactSidebarMenu` + `CheckContactLogin`), não o operador. Sete Blades hoje, um shell só no protótipo.

## Mission

Deixar o cliente resolver sozinho o que hoje vira ligação para o balcão: quanto ele deve, o que comprou, quando pode retirar e pedir de novo o que já pediu.

## Goals

- Painel com os números do próprio cadastro: total em vendas/compras, pago, a pagar, saldo de abertura — conforme o `type` do contato (customer/supplier/both)
- Perfil editável (pessoa de contato, e-mail, telefone, endereço) + troca de senha; nome e CNPJ são read-only (cadastro fiscal)
- Minhas vendas e minhas compras com status de pagamento e PDF do documento
- Extrato com débito/crédito/saldo + PDF (o `getLedger`)
- Agendamentos: lista + criar (local, janela, observação) — o balcão confirma
- Solicitação de pedido: linhas de produto com preço da tabela do cliente, total estimado, envio para aprovação

## Non-Goals

- ❌ Não é loja: não fecha venda, não cobra, não emite NF-e — solicitação de pedido é pedido, não faturamento
- ❌ Não mostra custo, margem, nem dado de outro contato (LGPD + Tier 0)
- ❌ Não altera cadastro fiscal (nome/CNPJ/IE) pelo portal
- ❌ Não expõe o CRM interno: nada de lead, acompanhamento, campanha ou comissão

## UX Targets

- Funciona em celular (o cliente abre do WhatsApp): alvos ≥ 44px
- Extrato e vendas legíveis sem zoom em 390px de largura
- Solicitação de pedido em ≤ 4 toques por item

## Automation Anti-hooks

- ❌ Solicitação de pedido não vira venda sem ação do operador
- ❌ Agendamento não bloqueia agenda da produção — é pedido de horário
- ❌ Portal só é habilitado com `enable_order_request` para a parte de pedidos (config do módulo)
- ❌ Toda query escopada por `contact_id` **e** `business_id`

## Refs

- Rotas: grupo `prefix('contact')` em `Modules/Crm/Routes/web.php`
- Config: `crm_settings.enable_order_request` + `order_request_prefix` (`CrmSettingsController`)
