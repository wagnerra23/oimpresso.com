---
sessao: "22"
titulo: Portal do contato · Extrato em React
dono: "[CL]"
base: 93ff8a960b74
---
# 22 · Portal do contato · Extrato

Hoje (lido @93ff8a960b74): `LedgerController:46 → crm::ledger.index · getLedger:75 → contact.ledger (core)`. Rota `contact/contact-ledger` no grupo `prefix('contact')` de `Modules/Crm/Routes/web.php:6` (middleware `ContactSidebarMenu` + `CheckContactLogin`). Fonte de forma e copy: view `extrato` de `crm-portal.jsx` (rota `crm-portal-extrato`). Charter de rascunho do portal: `handoff-crm/Portal.charter.md` (goals, non-goals, anti-hooks).

- **Persona = contato logado**, não operador: outro guard e outro menu. Casca = a da **D6** (pendente [W]); não escolher sozinho.
- **Escopo por contato logado + `business_id`**: tela que mostre dado de outro contato do mesmo negócio é vazamento. Teste de contrato tenant 98×99 **e** contato A×B.
- Processo MWART (ADR 0104): trio (.tsx + charter + casos) + contrato; Blade fica como fallback (`?classico=1`) até o cutover.
- **Mostra valores.** Se a migração mexer em cálculo de saldo, vale a regra mestre de valor (dupla prova + aprovação [W]). Só trocar a tela, não.

**Casos e teste:** `handoff-crm/Portal.casos.md` — UC-CRMPO-09, 10. Teste: `Modules/Crm/Tests/Feature/CrmPortalExtratoContratoTest.php`, um `it` por UC, tenant 98×99 **e** contato A×B (molde `CrmPedidosContratoTest.php`). O índice só conta a thread como feita com esses ids no teste.
