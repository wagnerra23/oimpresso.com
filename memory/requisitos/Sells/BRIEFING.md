---
id: requisitos-sells-briefing
module: Sells
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Sells (verdade destilada)

## Estado atual
Sells é a funcionalidade central do UltimatePOS, abrigando telas em `resources/js/Pages/Sells/` e lógica em múltiplos controladores. A versão V2 da tela de venda está ativa desde 2026-05-27, com a lógica de operação em produção. A preview V3 foi introduzida em 2026-08-07, permitindo melhorias, embora ainda sem integração total.

## Capacidades
- Tela de venda V2 em produção, com cálculo de itens na preview V3.
- Drawer `SaleSheet` com FSM que gerencia ações, RBAC e auditoria.
- Integração com oficinas, ativa apenas quando `OficinaAuto` está instalado.
- Emissão fiscal de NFe/NFS-e em lote.
- Ferramentas de cobrança e pagamento otimizadas.
- Painéis de controle, incluindo caixas e ferramentas de IA de vendas.
- Guards de segurança implementados após incidente relacionado ao `num_uf`.

## Gaps
- Necessidade de teste HTTP para comprovar a correção do `final_total` na gravação.
- Remoção da Blade legacy pendente após cutover planejado.
- Implementação de rota para reverter/cancelar vendas sem especificação clara.
- Dashboard de relatórios em `/relatorios/vendas-origem` desatualizado.

## Última mudança
Recentemente, foram formalizados contratos de comportamento do editor e implementadas melhorias nas abas do drawer de item, além de ajustes de design no sistema de preview.

## Proveniência (destilado de)

- audit `requisitos/Sells/AUDIT-cockpit-runbook-Create-2026-05-15.md` — AUDIT-cockpit-runbook-Create-2026-05-15.md
- audit `requisitos/Sells/CAPTERRA-DESIGN-FICHA.md` — CAPTERRA-DESIGN-FICHA.md
- audit `requisitos/Sells/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-09-06-refutacao-gt-g5-distill-12-portas.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-distill-12-portas.md
