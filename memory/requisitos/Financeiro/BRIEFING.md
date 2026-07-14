---
distilled_at: "2026-07-14"
distilled_by: jana:distill-module-truth
module: Financeiro
---

# BRIEFING — Financeiro (verdade destilada)

# BRIEFING — Financeiro (verdade destilada)

O módulo "Financeiro" fornece uma visão unificada de Contas a Receber (AR), Contas a Pagar (AP), Fluxo de Caixa, Boletos, Conciliação OFX e um workflow de aprovação. Atualmente, está em operação com 87% de cobertura funcional e paridade visual de 9.5/10 em relação ao canon (medição de 2026-05-20).

## Capacidades
- Emissão de boletos real via Banco Inter com integração completa (mTLS, Webhook).
- Conciliação automatizada de pagamentos através de eventos de cobrança.
- Workflow para aprovação de transações com visualização integrada de AR/AP.
- Integração de bulk actions para operações em lote com confirmacão e audit trail.
- Ações em lote na Visão Unificada para até 500 títulos por chamada.

## Gaps
- Drivers de pagamento adicionais (Asaas, C6, BCB Pix) aguardando credenciais ativas.
- Melhora necessária na documentação para novos usuários.
- Implementação de relatórios financeiros avançados e métricas de performance.

## Última mudança
Recentemente, em 2026-07-06, foi concluída a implementação das ações em lote na Visão Unificada, permitindo operações eficientes por meio do novo endpoint `POST /unificado/bulk`. Em junho, a emissão de boletos foi corrigida para operação real, elevando a cobertura funcional de 75% para 87% e introduzindo novas funções.

## Proveniência (destilado de)

- audit `requisitos/Financeiro/AUDIT-FUNCOES-2026-05-19.md` — AUDIT-FUNCOES-2026-05-19.md
- audit `requisitos/Financeiro/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-07-08-financeiro-borda-dark-token.md` (2026-07-08) — 2026-07-08-financeiro-borda-dark-token.md
- session `sessions/2026-07-08-financeiro-fidelidade-fingerprint-protocolo.md` (2026-07-08) — 2026-07-08-financeiro-fidelidade-fingerprint-protocolo.md
- handoff `handoffs/2026-07-08-1044-financeiro-fidelidade-fingerprint-furos.md` (2026-07-08) — 2026-07-08-1044-financeiro-fidelidade-fingerprint-furos.md
- handoff `handoffs/2026-07-08-1431-financeiro-borda-dark-token-ui0022.md` (2026-07-08) — 2026-07-08-1431-financeiro-borda-dark-token-ui0022.md
- handoff `handoffs/2026-07-07-1746-financeiro-fidelidade-dark-mecanismos-comparacao.md` (2026-07-07) — 2026-07-07-1746-financeiro-fidelidade-dark-mecanismos-comparacao.md
- session `sessions/2026-06-22-adversario-sag-financeiro.md` (2026-06-22) — 2026-06-22-adversario-sag-financeiro.md
- session `sessions/2026-06-21-verificacao-rede-onda0-estado-real.md` (2026-06-21) — 2026-06-21-verificacao-rede-onda0-estado-real.md
- handoff `handoffs/2026-06-16-2006-financeiro-hero-gabarito-licao-copiar.md` (2026-06-16) — 2026-06-16-2006-financeiro-hero-gabarito-licao-copiar.md
