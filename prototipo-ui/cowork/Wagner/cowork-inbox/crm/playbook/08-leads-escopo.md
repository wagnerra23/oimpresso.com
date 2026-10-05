---
sessao: "08"
titulo: Leads: escopo
dono: "[CL]"
base: 99e6fa3e08f0
---
# 08 · Dois achados do `_saida-02`

1. `show()` Blade de `/crm/leads/{id}` filtra só `business_id`, sem `type = lead`: abre um cliente do mesmo negócio pelo id.
2. `CrmUtil::getLeadsListQuery` seleciona `contacts.prefix/first_name/middle_name/last_name`, colunas que não existem mais → 500. O `LeadController` contorna; a raiz e o outro chamador (`Connector/Api/Crm/FollowUpController`, ~:968 segundo o recibo) seguem expostos. **Não reli essas linhas neste turno** — confirme antes.
