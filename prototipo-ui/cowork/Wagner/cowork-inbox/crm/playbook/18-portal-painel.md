---
sessao: "18"
titulo: Portal do contato · Painel do contato em React
dono: "[CL]"
base: 93ff8a960b74
---
# 18 · Portal do contato · Painel do contato

Hoje (lido @93ff8a960b74): `DashboardController:49 → crm::dashboard.index`. Rota `contact/contact-dashboard` no grupo `prefix('contact')` de `Modules/Crm/Routes/web.php:6` (middleware `ContactSidebarMenu` + `CheckContactLogin`). Fonte de forma e copy: view `painel` de `crm-portal.jsx` (rota `crm-portal-painel`). Charter de rascunho do portal: `handoff-crm/Portal.charter.md` (goals, non-goals, anti-hooks).

- **Persona = contato logado**, não operador: outro guard e outro menu. Casca = a da **D6** (pendente [W]); não escolher sozinho.
- **Escopo por contato logado + `business_id`**: tela que mostre dado de outro contato do mesmo negócio é vazamento. Teste de contrato tenant 98×99 **e** contato A×B.
- Processo MWART (ADR 0104): trio (.tsx + charter + casos) + contrato; Blade fica como fallback (`?classico=1`) até o cutover.

**Casos e teste:** `handoff-crm/Portal.casos.md` — UC-CRMPO-01, 02, 03, 17, 18. Teste: `Modules/Crm/Tests/Feature/CrmPortalPainelContratoTest.php`, um `it` por UC, tenant 98×99 **e** contato A×B (molde `CrmPedidosContratoTest.php`). O índice só conta a thread como feita com esses ids no teste.
