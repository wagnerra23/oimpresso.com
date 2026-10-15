---
sessao: "24"
titulo: Portal do contato · Solicitações de pedido em React
dono: "[CL]"
base: 93ff8a960b74
---
# 24 · Portal do contato · Solicitações de pedido

Hoje (lido @93ff8a960b74): `OrderRequestController@index:162 → crm::order_request.index · @create:192 → crm::order_request.create · getProductRow:287`. Rota `contact/order-request` no grupo `prefix('contact')` de `Modules/Crm/Routes/web.php:6` (middleware `ContactSidebarMenu` + `CheckContactLogin`). Fonte de forma e copy: view `pedidos` de `crm-portal.jsx` (rota `crm-portal-pedidos`). Charter de rascunho do portal: `handoff-crm/Portal.charter.md` (goals, non-goals, anti-hooks).

- **Persona = contato logado**, não operador: outro guard e outro menu. Casca = a da **D6** (pendente [W]); não escolher sozinho.
- **Escopo por contato logado + `business_id`**: tela que mostre dado de outro contato do mesmo negócio é vazamento. Teste de contrato tenant 98×99 **e** contato A×B.
- Processo MWART (ADR 0104): trio (.tsx + charter + casos) + contrato; Blade fica como fallback (`?classico=1`) até o cutover.
- Lado do contato da thread 17 (que migrou só `listOrderRequests`, lado admin). Respeita `crm_settings.enable_order_request`. Solicitação não vira venda sem ação do operador.

**Casos e teste:** `handoff-crm/Portal.casos.md` — UC-CRMPO-13, 14, 15. Teste: `Modules/Crm/Tests/Feature/CrmPortalPedidosContratoTest.php`, um `it` por UC, tenant 98×99 **e** contato A×B (molde `CrmPedidosContratoTest.php`). O índice só conta a thread como feita com esses ids no teste.
