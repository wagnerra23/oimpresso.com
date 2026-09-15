---
id: requisitos-payment-gateway-briefing
module: PaymentGateway
status: parcial
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — PaymentGateway (verdade destilada)

## Estado atual
O módulo "PaymentGateway" é a camada técnica de cobrança do oimpresso, englobando boleto, PIX, cartão, conciliação e retorno CNAB. O código está implementado, mas a funcionalidade está parcial; a flag de retries de webhooks órfãos está desativada e a documentação precisa de atualização, pois o módulo ainda é considerado Onda 0 e não está habilitado para clientes.

## Capacidades
- Integração com APIs REST (Inter, Asaas, C6, BCB Pix, Pagar.me, Sicoob) e drivers CNAB.
- Validação de assinatura em webhooks com métodos fail-secure (HMAC-SHA256, token estático, mTLS).
- Conciliação por polling e mecanismo de retry para webhooks órfãos.
- Tela para configuração de credenciais e retorno CNAB.

## Gaps
- Falta o cadastro da URL de webhook PIX no Inter via wizard.
- A autenticação dos webhooks do Inter precisa de definição (mTLS vs HMAC).
- A implementação de endurecimento nos webhooks (throttle, timestamp) ainda está pendente.
- PesaPal está presente apenas como vestígio, sem driver funcional.

## Última mudança
Recentemente, houve a correção de um erro fatal relacionado ao carregamento do job, mas não houve mudanças nas capacidades ou configurações desde a auditoria em julho. As atividades foram focadas em higiene de código e documentação.

## Proveniência (destilado de)

- audit `requisitos/PaymentGateway/AUDITORIA-PAYMENTGATEWAY-2026-07.md` — AUDITORIA-PAYMENTGATEWAY-2026-07.md
- audit `requisitos/PaymentGateway/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/PaymentGateway/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-distill-12-portas.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-distill-12-portas.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6919-r1.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6919-r1.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6919-r3.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6919-r3.md
- session `sessions/2026-08-20-backup-migracao-ondas-0-a-3.md` (2026-08-20) — 2026-08-20-backup-migracao-ondas-0-a-3.md
- handoff `handoffs/2026-08-20-1138-backup-ondas-mergeadas-f3-bloqueada-por-transporte.md` (2026-08-20) — 2026-08-20-1138-backup-ondas-mergeadas-f3-bloqueada-por-transporte.md
