---
distilled_at: "2026-07-10"
distilled_by: jana:distill-module-truth
module: Sells
status: producao
updated_at: "2026-07-10"
---

# BRIEFING — Sells (verdade destilada)

# BRIEFING — Sells (verdade destilada)

## Estado atual
O módulo "Sells" é encarregado da funcionalidade de vendas, conectando transações do legado UltimatePOS a uma interface moderna em Inertia/React. O canário de 7 dias foi iniciado em **biz=1** no deploy de 2026-05-25, com **biz=4** (ROTA LIVRE) aguardando reavaliação pós-canário verde.

## Capacidades
- **Listagem de Vendas**: interface interativa com filtros.
- **Cadastro de Vendas**: checkout facilitado com busca de produtos e clientes.
- **Integração com Oficina**: vinculação ágil de veículos a transações.
- **Edição e Comissionamento**: gerenciamento de vendas e comissões por profissional.
- **Exibição de Vendas em Caixa**: análise diária das vendas por origem.
- **Compartilhamento de Dados**: suporte à Web Share API em dispositivos móveis.

## Gaps
- Desenvolvimento do dashboard `/relatorios/vendas-origem` para insights detalhados.
- Criação de UI para reversão de vendas canceladas.
- Implementação do Observer de venda (ADR 0192) para manter p95 <50ms — movimentar side-effects para Jobs se exceder.

## Última mudança
Recentemente, a integração venda↔Oficina foi implementada em 2026-06-05. O módulo teve evoluções em jun-jul/2026, como a restauração do menu Ações por linha e a atualização da visão Caixa do dia por origem.

## Proveniência (destilado de)

- audit `requisitos/Sells/AUDIT-cockpit-runbook-Create-2026-05-15.md` — AUDIT-cockpit-runbook-Create-2026-05-15.md
- audit `requisitos/Sells/CAPTERRA-DESIGN-FICHA.md` — CAPTERRA-DESIGN-FICHA.md
- audit `requisitos/Sells/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-07-03-onda11-sells-backlog-materializado.md` (2026-07-03) — 2026-07-03-onda11-sells-backlog-materializado.md
- handoff `handoffs/2026-07-03-0835-onda11-capterra-sells-backlog.md` (2026-07-03) — 2026-07-03-0835-onda11-capterra-sells-backlog.md
- session `sessions/2026-07-02-capterra-sells.md` (2026-07-02) — 2026-07-02-capterra-sells.md
- handoff `handoffs/2026-06-22-2324-sells-caixa-link-visoes.md` (2026-06-22) — 2026-06-22-2324-sells-caixa-link-visoes.md
- session `sessions/2026-06-13-prompts-burndown-f2b-pos-triage.md` (2026-06-13) — 2026-06-13-prompts-burndown-f2b-pos-triage.md
- session `sessions/2026-06-13-sdd-f2b-triage-q2.md` (2026-06-13) — 2026-06-13-sdd-f2b-triage-q2.md
