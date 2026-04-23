# Glossário · CRM

## Campaign
Campanha de marketing agrupando leads/contatos por origem (Google Ads, evento, indicação).

## Contact
Pessoa ou empresa com relação formal (cliente, fornecedor, parceiro). Vive em `contacts` do UltimatePOS core.

## Follow-up
Interação agendada com lead/contato — ligação, email, visita. Tem `scheduled_at` + `type` + `notes`.

## Lead
Prospecto — ainda não virou cliente. Vive em `crm_leads` com `life_stage_id` indicando etapa do funil.

## Life Stage
Etapa do funil de vendas — "New", "Contacted", "Qualified", "Proposal", "Won", "Lost". Configurável por business.

## Pipeline
Funil de vendas. Cada estágio tem leads em andamento. Dashboard mostra conversion rate entre estágios.

## Priority
Prioridade do lead — "hot", "warm", "cold". Usado pra ordenação padrão do inbox.

## Source
Origem do lead (referral, cold outreach, landing page, evento). Usado pra análise de ROI por canal.
