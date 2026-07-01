---
distilled_at: "2026-07-01"
distilled_by: jana:distill-module-truth
module: Financeiro
---

# BRIEFING — Financeiro (verdade destilada)

O módulo "Financeiro" oferece uma visão unificada de Contas a Receber (AR), Contas a Pagar (AP), Fluxo de Caixa, Boletos, Conciliação OFX, Plano de Contas BR e um workflow de aprovação. Está em operação com paridade VISUAL 9.5/10 vs canon e cobertura funcional de 87% (53/61, medições 2026-05-20).

## Capacidades
- Emissão de boletos real via Banco Inter, com integração completa (mTLS, Webhook).
- Conciliação de pagamentos automatizada através de eventos de cobrança.
- Workflow para aprovação de transações com visualização integrada de AR/AP.
- Interface de usuário com paridade visual de 9.5/10 vs canon (medição 2026-05-20).

## Gaps
- Drivers de pagamento adicionais (Asaas, C6, BCB Pix, etc.) aguardando credenciais ativas para ativação.
- Melhora na documentação de processos e integração para novos usuários.
- Implementação de relatórios financeiros avançados e métricas de performance.

## Última mudança
Em 2026-06-08 a emissão de boletos foi corrigida de mock para operação real, com a transição para o `PaymentGateway` (o `CnabDirectStrategy` legado está sendo aposentado, não depurado). A cobertura funcional subiu de 75% para 87% e 7 funções novas entraram nas Ondas 12-21 (medição 2026-05-20).

## Proveniência (destilado de)

- audit `requisitos/Financeiro/AUDIT-FUNCOES-2026-05-19.md` — AUDIT-FUNCOES-2026-05-19.md
- audit `requisitos/Financeiro/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-06-22-adversario-sag-financeiro.md` (2026-06-22) — 2026-06-22-adversario-sag-financeiro.md
- session `sessions/2026-06-21-verificacao-rede-onda0-estado-real.md` (2026-06-21) — 2026-06-21-verificacao-rede-onda0-estado-real.md
- handoff `handoffs/2026-06-16-2006-financeiro-hero-gabarito-licao-copiar.md` (2026-06-16) — 2026-06-16-2006-financeiro-hero-gabarito-licao-copiar.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- session `sessions/2026-06-11-financeiro-resync-larissa-numuf-residue.md` (2026-06-11) — 2026-06-11-financeiro-resync-larissa-numuf-residue.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
- handoff `handoffs/2026-06-08-2115-boleto-unificado-merge-c-comando-existente.md` (2026-06-08) — 2026-06-08-2115-boleto-unificado-merge-c-comando-existente.md
- handoff `handoffs/2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md` (2026-06-07) — 2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md
- session `sessions/2026-06-06-migracao-wr-comercial-financeiro-eliana.md` (2026-06-06) — 2026-06-06-migracao-wr-comercial-financeiro-eliana.md
- session `sessions/2026-06-06-plano-inventario-anti-duplicacao.md` (2026-06-06) — 2026-06-06-plano-inventario-anti-duplicacao.md
- handoff `handoffs/2026-06-06-1312-deprecar-dashboard-deploy-manual-visreg-harness.md` (2026-06-06) — 2026-06-06-1312-deprecar-dashboard-deploy-manual-visreg-harness.md
- handoff `handoffs/2026-06-06-1650-trilha-css-f6-juiz-f4.md` (2026-06-06) — 2026-06-06-1650-trilha-css-f6-juiz-f4.md
- session `sessions/2026-06-05-arte-programacao-autonoma-rapida-qualidade.md` (2026-06-05) — 2026-06-05-arte-programacao-autonoma-rapida-qualidade.md
- handoff `handoffs/2026-06-05-2152-css-prune-freeze-gate-statstrip-pilot.md` (2026-06-05) — 2026-06-05-2152-css-prune-freeze-gate-statstrip-pilot.md
- session `sessions/2026-06-04-financeiro-suite-landscape.md` (2026-06-04) — 2026-06-04-financeiro-suite-landscape.md
- handoff `handoffs/2026-06-02-1805-dedupe-financeiro-bundle-duplo.md` (2026-06-02) — 2026-06-02-1805-dedupe-financeiro-bundle-duplo.md
- handoff `handoffs/2026-06-01-1510-financeiro-reimpl-fase12-prod-gatilho-ct100.md` (2026-06-01) — 2026-06-01-1510-financeiro-reimpl-fase12-prod-gatilho-ct100.md
