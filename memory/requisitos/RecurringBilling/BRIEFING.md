---
id: requisitos-recurring-billing-briefing
module: RecurringBilling
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — RecurringBilling (verdade destilada)

## Estado atual
O módulo RecurringBilling gerencia assinaturas e faturas recorrentes, integrando múltiplos gateways de pagamento em produção. A governança e as funcionalidades estão documentadas, com o módulo operando sem dependências legadas ou riscos conhecidos de regressão.

## Capacidades
- CRUD de planos de assinatura (`rb_plans`) com suporte a ciclos e trials.
- Gerenciamento de estados de contratos de assinatura (`rb_subscriptions`).
- Cobrança via `BoletoService` com suporte a diferentes gateways (Inter, Asaas, C6).
- Cancelamento de faturas por API (Inter e Asaas) e manual para C6.
- Webhooks idempotentes registrados em `pg_webhook_events`.
- Emissão automática de NFe após pagamento processada por listener.
- Sincronização bancária de saldos e extratos.
- Interface de usuário com a página de assinaturas e funcionalidade para criar novas assinaturas.

## Gaps
- Assinatura sem plano para valor negociado é descartada sem notificação.
- A régua de cobrança (dunning/retry) é fixa e não configurável.
- Recursos adicionais como PIX automático, cartão tokenizado e proração ainda não implementados.

## Última mudança
Em 31 de agosto de 2026, a tela de assinaturas foi atualizada para utilizar novos componentes de interface do Design System. Mudanças anteriores incluíram ajustes de internacionalização e melhorias em permissões de acesso. Testes essenciais foram adicionados, mas alguns permanecem falhando, requerendo atenção.

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r6.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r6.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r7.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r7.md
