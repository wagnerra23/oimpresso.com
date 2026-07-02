---
distilled_at: "2026-07-02"
distilled_by: jana:distill-module-truth
module: RecurringBilling
---

# BRIEFING — RecurringBilling (verdade destilada)

# BRIEFING — `RecurringBilling`

## Estado atual
O módulo de assinaturas recorrentes, `RecurringBilling`, gerencia planos, assinaturas e faturas com suporte a múltiplos gateways de pagamento. Atualmente, o módulo apresenta uma cobertura de 75% em operação PME, com progressos significativos em testes e documentação, embora ainda dependa de ajustes pós-deploy.

## Capacidades
- **Planos**: CRUD para `rb_plans` com configurações de ciclo e trial.
- **Contratos**: Gerencia `rb_subscriptions` em diversos estados.
- **Cobrança**: Resolução dos gateways de pagamento via `BoletoService::driver()`.
- **Cancelamento**: Implementação de cancelamento de faturas.
- **Webhooks**: Idempotência garantida via `webhook_event_id`.
- **NFe**: Emissão automática após pagamento via `NfeBrasil`.
- **Sync bancário**: Sincronização de saldos bancários e extratos.
- **Interface**: Página Inertia completa com funcionalidades de gestão de assinaturas.
- **Nova assinatura**: Criação facilitada por interface intuitiva (PR #2369).

## Gaps
- **Integrações adicionais**: Necessidade de expansión para mais gateways.
- **Testes automatizados**: Cobertura de testes deve ser ampliada para todas as funcionalidades.
- **Documentação técnica**: Algumas áreas ainda carecem de documentação detalhada para uso completo.

## Última mudança
Recentemente, houve a integração completa dos processos de retroatividade na cobrança, melhorando a gestão de assinaturas e garantindo maior eficiência nas operações financeiras.

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
- session `sessions/2026-06-07-recurring-billing-retroatividade-eliana.md` (2026-06-07) — 2026-06-07-recurring-billing-retroatividade-eliana.md
- handoff `handoffs/2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md` (2026-06-07) — 2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md
- handoff `handoffs/2026-06-07-1855-recurring-billing-retroatividade-completa.md` (2026-06-07) — 2026-06-07-1855-recurring-billing-retroatividade-completa.md
