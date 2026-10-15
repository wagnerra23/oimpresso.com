---
sessao: "19"
titulo: Portal do contato · Perfil (+ senha) em React
dono: "[CL]"
base: 93ff8a960b74
---
# 19 · Portal do contato · Perfil (+ senha)

Hoje (lido @93ff8a960b74): `ManageProfileController:51 → crm::profile.edit (+ POST contact-password-update / contact-profile-update)`. Rota `contact/contact-profile` no grupo `prefix('contact')` de `Modules/Crm/Routes/web.php:6` (middleware `ContactSidebarMenu` + `CheckContactLogin`). Fonte de forma e copy: view `perfil` de `crm-portal.jsx` (rota `crm-portal-perfil`). Charter de rascunho do portal: `handoff-crm/Portal.charter.md` (goals, non-goals, anti-hooks).

- **Persona = contato logado**, não operador: outro guard e outro menu. Casca = a da **D6** (pendente [W]); não escolher sozinho.
- **Escopo por contato logado + `business_id`**: tela que mostre dado de outro contato do mesmo negócio é vazamento. Teste de contrato tenant 98×99 **e** contato A×B.
- Processo MWART (ADR 0104): trio (.tsx + charter + casos) + contrato; Blade fica como fallback (`?classico=1`) até o cutover.
- Nome e CNPJ são read-only (cadastro fiscal); troca de senha mantém as rotas POST atuais.

**Casos e teste:** `handoff-crm/Portal.casos.md` — UC-CRMPO-04, 05. Teste: `Modules/Crm/Tests/Feature/CrmPortalPerfilContratoTest.php`, um `it` por UC, tenant 98×99 **e** contato A×B (molde `CrmPedidosContratoTest.php`). O índice só conta a thread como feita com esses ids no teste.
