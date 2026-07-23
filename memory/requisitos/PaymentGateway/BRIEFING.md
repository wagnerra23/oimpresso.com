---
id: requisitos-payment-gateway-briefing
module: PaymentGateway
status: parcial
updated_at: "2026-07-23"
distilled_at: "2026-07-23"
distilled_by: jana:distill-module-truth
---

# BRIEFING — PaymentGateway (verdade destilada)

O módulo PaymentGateway é a camada técnica de cobrança do sistema Oimpresso, que permite a emissão de boletos, PIX e cobranças via cartões, além de gerenciar a conciliação e o processamento de retornos CNAB. O código está implementado e maduro, porém as flags estão OFF em produção e o módulo ainda não tem cliente real em prod; o boleto Inter está disponível pelo caminho do RecurringBilling (ADR 0170, extração deste módulo).

## Estado atual
Código implementado, mas com flags OFF em produção — sem cliente real (auditoria 2026-07: D5 0/15, "docs only até Wagner ativar"). As especificações que ainda citam "Onda 0 não habilitado" estão corretas: o módulo segue não habilitado (cron de retry dormente; ADR 0170 hoje arquivada). Boleto Inter disponível via caminho RecurringBilling.

## Capacidades
- Seis drivers API REST operacionais (Inter, Asaas, C6, BCB Pix Automático, Pagar.me, Sicoob).
- Onze drivers CNAB para arquivos integrados a bancos como Bradesco, Itaú e Santander.
- Webhooks com validação de assinatura e mitigação de vulnerabilidades críticas.
- Conciliação de pagamentos via polling e suporte a retry de webhooks.
- Interface de configuração de credenciais acessível.

## Gaps
- Implementação do cadastro automático da URL de webhook PIX para o Inter (US-PG-005).
- Correção na autenticação do webhook do Inter (mTLS vs HMAC; US-PG-006).
- Necessidade de medidas de segurança adicionais em webhooks (US-PG-003).
- Situação da integração com PesaPal, considerada vestigial.

## Última mudança
Atividade recente foi de auditoria/avaliação — CAPTERRA-FICHA (67/100) e a Onda 2 de avaliações — mais um "dente de cálculo" test-only (PR #3739). Não houve mudança de capability nem ativação de flag.

## Proveniência (destilado de)

- audit `requisitos/PaymentGateway/AUDITORIA-PAYMENTGATEWAY-2026-07.md` — AUDITORIA-PAYMENTGATEWAY-2026-07.md
- audit `requisitos/PaymentGateway/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/PaymentGateway/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-07-05-onda2-avaliacoes-compras-paymentgateway.md` (2026-07-05) — 2026-07-05-onda2-avaliacoes-compras-paymentgateway.md
- session `sessions/2026-07-03-capterra-paymentgateway.md` (2026-07-03) — 2026-07-03-capterra-paymentgateway.md
- handoff `handoffs/2026-07-03-1415-capterra-paymentgateway-passos-1-3.md` (2026-07-03) — 2026-07-03-1415-capterra-paymentgateway-passos-1-3.md
- handoff `handoffs/2026-07-03-1710-dente-calculo-paymentgateway.md` (2026-07-03) — 2026-07-03-1710-dente-calculo-paymentgateway.md
- session `sessions/2026-07-02-dossie-triagem-onda4-revisao-adr.md` (2026-07-02) — 2026-07-02-dossie-triagem-onda4-revisao-adr.md
