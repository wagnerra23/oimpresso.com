---
id: requisitos-crm-briefing
module: Crm
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Crm (verdade destilada)

## Estado atual
`Modules/Crm` é um módulo em produção, dividido em duas partes: o A abrange o cadastro de Clientes, que inclui funcionalidades como autosave e auditoria LGPD; o B é o pipeline pré-venda herdado do UltimatePOS, atualmente em depreciação. Desde 2026-06-08, o módulo não recebe novos investimentos, apenas correções.

## Capacidades
- Cadastro de Clientes com dados pessoais e comerciais em várias abas.
- Funcionalidade de autosave através do `ClienteAutosaveController`.
- Cálculo de score de risco do cliente disponível desde 2026-05.
- Exportação de dados em CSV com compliance LGPD, sem expor `tax_number`.
- Integração com API externa do módulo Connector e acesso a um portal de contato (zona cinza).

## Gaps
- Bloqueio na depreciação relacionado ao contagem de registros em tabelas `crm_*`, devido a ausência de réplica em produção.
- Outro bloqueio referente ao consumidor externo `Connector/api/crm`, que precisa ser encerrado para avançar no plano.
- Uso do portal `/contact/*` está atrelado à medição real, com necessidade de decisão sobre seu futuro no contexto de depreciação.

## Última mudança
Em 2026-09-11, as fontes de protótipo foram separadas por dono e os paralelos removidos (#7224). Antes disso, em 2026-09-04, houve progresso na medição de bloqueios do plano de depreciação, com o fechamento de bloqueios e a avaliação de uso do portal, que se revelou nulo — sinalizando a necessidade de um controle positivo.

## Proveniência (destilado de)

- session `sessions/2026-09-08-onda7-paridade-crm-jana-forja.md` (2026-09-08) — 2026-09-08-onda7-paridade-crm-jana-forja.md
- handoff `handoffs/2026-09-08-0924-onda7-lote-crm-jana-forja.md` (2026-09-08) — 2026-09-08-0924-onda7-lote-crm-jana-forja.md
