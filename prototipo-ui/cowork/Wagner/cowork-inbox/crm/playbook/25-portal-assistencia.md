---
sessao: "25"
titulo: Portal do contato · Assistência em React
dono: "[CL]"
base: 93ff8a960b74
---
# 25 · Portal do contato · Assistência

Tela nova do portal (não existe Blade): as OS do contato logado. Forma: view `assistencia` de `crm-portal.jsx` (rota `crm-portal-assistencia`), casca própria da D6. Caso: UC-COS-24 em `cowork-inbox/repair/ConsultaOs-link.casos.md`.

- Fonte: `repair_job_sheets` do `contact_id` logado **e** `business_id` da sessão; mesma whitelist do ConsultaOs (UC-COS-04) + o código de acompanhamento (Repair 06).
- Teste de contrato tenant 98×99 **e** contato A×B do mesmo negócio.
- Depende da Repair 06 para o código; sem ela, a aba sai sem "Copiar link".

**Casos e teste:** `handoff-crm/Portal.casos.md` — UC-CRMPO-16. Teste: `Modules/Crm/Tests/Feature/CrmPortalAssistenciaContratoTest.php`, um `it` por UC, tenant 98×99 **e** contato A×B (molde `CrmPedidosContratoTest.php`). O índice só conta a thread como feita com esses ids no teste.
