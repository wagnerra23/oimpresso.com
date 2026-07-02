---
distilled_at: "2026-07-01"
distilled_by: jana:distill-module-truth
module: Sells
---

# BRIEFING — Sells (verdade destilada)

## Estado atual
O módulo "Sells" é responsável pela funcionalidade de vendas, integrando transações do legado UltimatePOS e novas interações através de uma interface moderna em Inertia/React. Os recursos principais estão implementados; o canário de 7 dias foi iniciado em **biz=1** no deploy de 2026-05-25 — biz=4 (ROTA LIVRE) habilita pós-canário verde (estado atual da expansão a re-verificar).

## Capacidades
- **Listagem de Vendas**: interface rica com filtros e ações interativas.
- **Cadastro de Vendas**: checkout com busca de produtos e clientes.
- **Integração com Oficina**: permite vincular veículos a transações de modo agilizado.
- **Edição e Comissionamento**: edição de vendas com gestão de comissões por profissional.
- **Exibição de Vendas em Caixa**: análise de vendas diárias com visualização por origem.
- **Compartilhamento de Dados**: suporte à Web Share API em dispositivos móveis.

## Gaps
- Implementação de dashboard `/relatorios/vendas-origem` para insights adicionais.
- UI para reversão de vendas canceladas.
- Observer de venda (ADR 0192): manter p95 <50ms — acima disso, mover side-effects pra Job.

## Última mudança
A integração venda↔Oficina (vínculo de veículo à transação, ADR 0251) entrou em 2026-06-05; o módulo seguiu evoluindo em jun-jul/2026 — ex.: menu Ações por linha restaurado (#3494) e visão Caixa do dia por origem.

## Proveniência (destilado de)

- audit `requisitos/Sells/AUDIT-cockpit-runbook-Create-2026-05-15.md` — AUDIT-cockpit-runbook-Create-2026-05-15.md
- audit `requisitos/Sells/CAPTERRA-DESIGN-FICHA.md` — CAPTERRA-DESIGN-FICHA.md
- handoff `handoffs/2026-06-22-2324-sells-caixa-link-visoes.md` (2026-06-22) — 2026-06-22-2324-sells-caixa-link-visoes.md
- session `sessions/2026-06-13-prompts-burndown-f2b-pos-triage.md` (2026-06-13) — 2026-06-13-prompts-burndown-f2b-pos-triage.md
- session `sessions/2026-06-13-sdd-f2b-triage-q2.md` (2026-06-13) — 2026-06-13-sdd-f2b-triage-q2.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
- handoff `handoffs/2026-06-08-1435-identidade-sells-compras-roxo.md` (2026-06-08) — 2026-06-08-1435-identidade-sells-compras-roxo.md
- handoff `handoffs/2026-06-03-1815-ds-v6-port-sells-list-view.md` (2026-06-03) — 2026-06-03-1815-ds-v6-port-sells-list-view.md
- session `sessions/2026-06-02-incidente-revert-pr2-sells-endereco.md` (2026-06-02) — 2026-06-02-incidente-revert-pr2-sells-endereco.md
