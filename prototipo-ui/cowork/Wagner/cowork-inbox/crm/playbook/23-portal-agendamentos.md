---
sessao: "23"
titulo: Portal do contato · Agendamentos em React
dono: "[CL]"
base: 93ff8a960b74
---
# 23 · Portal do contato · Agendamentos

Hoje (lido @93ff8a960b74): `ContactBookingController:89 → crm::booking.index (+ stubs crm::create/show/edit :99/:143/:154)`. Rota `contact/bookings` no grupo `prefix('contact')` de `Modules/Crm/Routes/web.php:6` (middleware `ContactSidebarMenu` + `CheckContactLogin`). Fonte de forma e copy: view `agendamentos` de `crm-portal.jsx` (rota `crm-portal-agendamentos`). Charter de rascunho do portal: `handoff-crm/Portal.charter.md` (goals, non-goals, anti-hooks).

- **Persona = contato logado**, não operador: outro guard e outro menu. Casca = a da **D6** (pendente [W]); não escolher sozinho.
- **Escopo por contato logado + `business_id`**: tela que mostre dado de outro contato do mesmo negócio é vazamento. Teste de contrato tenant 98×99 **e** contato A×B.
- Processo MWART (ADR 0104): trio (.tsx + charter + casos) + contrato; Blade fica como fallback (`?classico=1`) até o cutover.

**Casos e teste:** `handoff-crm/Portal.casos.md` — UC-CRMPO-11, 12. Teste: `Modules/Crm/Tests/Feature/CrmPortalAgendamentosContratoTest.php`, um `it` por UC, tenant 98×99 **e** contato A×B (molde `CrmPedidosContratoTest.php`). O índice só conta a thread como feita com esses ids no teste.
