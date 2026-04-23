# ADR ARQ-0001 (Crm) · CRM estende `contacts` do UltimatePOS sem duplicar

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

UltimatePOS já tem tabela `contacts` (clientes + fornecedores). Criar tabelas paralelas `crm_contacts`, `crm_leads` duplicaria dado e criaria sync pesadelo.

## Decisão

Módulo Crm **estende** `contacts` via colunas adicionais (`crm_contact_extra` bridge) e cria tabelas próprias pra conceitos CRM-específicos:
- `crm_leads` — leads antes de virar contact
- `crm_follow_ups` — interações agendadas
- `crm_life_stages` — funil de vendas
- `crm_calls` — log de chamadas

Lead vira Contact quando ganha, mantendo histórico.

## Consequências

**Positivas:**
- Zero duplicação de dados principais.
- Fluxo "lead → cliente" natural.
- POS vende pra contact sem saber que veio do CRM.

**Negativas:**
- Bridge exige FK bem mantida (cascade delete importante).

## Alternativas consideradas

- **Tabelas próprias completas**: rejeitado — sync impossível de manter.
