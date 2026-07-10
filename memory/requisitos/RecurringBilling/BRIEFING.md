---
distilled_at: "2026-07-10"
distilled_by: jana:distill-module-truth
module: RecurringBilling
---

# BRIEFING — RecurringBilling (verdade destilada)

## Estado atual
O módulo de assinaturas recorrentes, `RecurringBilling`, gerencia planos, assinaturas e faturas com suporte a múltiplos gateways de pagamento. Atualmente, o módulo apresenta uma cobertura de 75% em operação PME, com avanços significativos em testes e documentação, mas ainda demanda ajustes pós-deploy.

## Capacidades
- **Planos**: CRUD para `rb_plans` com configurações de ciclo e trial.
- **Contratos**: Gerenciamento de `rb_subscriptions` em diversos estados.
- **Cobrança**: Resolução dos gateways de pagamento via `BoletoService::driver()`.
- **Cancelamento**: Implementação de cancelamento de faturas.
- **Webhooks**: Idempotência garantida via `webhook_event_id`.
- **NFe**: Emissão automática após pagamento via `NfeBrasil`.
- **Sync bancário**: Sincronização de saldos bancários e extratos.
- **Interface**: Página Inertia completa com funcionalidades de gestão de assinaturas.
- **Nova assinatura**: Criação facilitada por interface intuitiva (PR #2369).

## Gaps
- **Integrações adicionais**: Necessidade de expansão para mais gateways.
- **Testes automatizados**: Cobertura de testes deve ser ampliada para todas as funcionalidades.
- **Documentação técnica**: Algumas áreas ainda carecem de documentação detalhada para uso completo.

## Última mudança
Recentemente, ocorreram revisões na lógica de cálculo dentro do módulo, além de melhorias na gestão de retroatividade, visando otimizar o processo de cobrança e a eficiência nas operações financeiras.

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-07-03-1215-dente-calculo-recurringbilling.md` (2026-07-03) — 2026-07-03-1215-dente-calculo-recurringbilling.md
- handoff `handoffs/2026-07-03-1245-us-rb-056-materializada.md` (2026-07-03) — 2026-07-03-1245-us-rb-056-materializada.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
