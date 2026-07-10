---
distilled_at: "2026-07-10"
distilled_by: jana:distill-module-truth
module: RecurringBilling
---

# BRIEFING — RecurringBilling (verdade destilada)

# BRIEFING — `RecurringBilling`

## Estado atual
O módulo de assinaturas recorrentes, `RecurringBilling`, gerencia planos, assinaturas e faturas com suporte a múltiplos gateways de pagamento. Atualmente, o módulo apresenta uma cobertura de 75% em operação PME, com progressos em testes e documentação, embora ainda dependa de ajustes pós-deploy.

## Capacidades
- **Planos**: CRUD para `rb_plans` com configurações de ciclo e trial.
- **Contratos**: Gerencia `rb_subscriptions` em diversos estados.
- **Cobrança**: Resolução dos gateways de pagamento via `BoletoService::driver()`.
- **Cancelamento**: Implementação de cancelamento de faturas.
- **Webhooks**: Idempotência garantida via `webhook_event_id`.
- **NFe**: Emissão automática após pagamento via `NfeBrasil`.
- **Sync bancário**: Sincronização de saldos bancários e extratos.
- **Interface**: Página Inertia completa para gestão de assinaturas.
- **Nova assinatura**: Criação facilitada por interface intuitiva (PR #2369).

## Gaps
- **Integrações adicionais**: Expansão necessária para mais gateways.
- **Testes automatizados**: Cobertura de testes precisa ser ampliada para todas as funcionalidades.
- **Documentação técnica**: Algumas áreas carecem de documentação detalhada para uso completo.

## Última mudança
Recentemente, houve a integração dos processos de retroatividade na cobrança, aperfeiçoando a gestão de assinaturas e aumentando a eficiência das operações financeiras.

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-07-03-1215-dente-calculo-recurringbilling.md` (2026-07-03) — 2026-07-03-1215-dente-calculo-recurringbilling.md
- handoff `handoffs/2026-07-03-1245-us-rb-056-materializada.md` (2026-07-03) — 2026-07-03-1245-us-rb-056-materializada.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
