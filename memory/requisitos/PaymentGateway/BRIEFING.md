---
distilled_at: "2026-07-02"
distilled_by: jana:distill-module-truth
module: PaymentGateway
---

# BRIEFING — PaymentGateway (verdade destilada)

# BRIEFING — PaymentGateway

O módulo PaymentGateway é a camada técnica de cobrança bancária integrada ao sistema Oimpresso, permitindo a emissão de boletos, PIX e cobrança via cartões, além de gerenciar a conciliação de recebimentos e o processamento de retornos CNAB. Atualmente, No ambiente de produção parcial, a funcionalidade de boleto do Inter já está em operação, enquanto as documentações e especificações precisam ser atualizadas para refletir o estado atual do código.

## Estado atual
Ativo (parcial em produção). A funcionalidade de Boleto Inter está disponível, mas as especificações ainda indicam um estado "Onda 0 não habilitado", o que está desatualizado, considerando que o código já passa por uma avaliação rigorosa.

## Capacidades
- **6 drivers API REST** funcionando (Inter, Asaas, C6, BCB Pix Automático, Pagar.me, Sicoob).
- **11 drivers CNAB** para arquivos (remessa e retorno) integrados a bancos como Bradesco, Itaú e Santander.
- **Webhooks** com validação de assinatura implementada e correção de vulnerabilidades críticas.
- Conciliação de pagamentos via **polling** e suporte para retry de webhooks órfãos.
- **Interface de configuração** de credenciais disponível.

## Gaps
- Cadastro automático de URL de webhook PIX para o Inter (US-PG-005).
- Correção da autenticação do webhook do Inter (mTLS vs HMAC; US-PG-006).
- Implementação de medidas de segurança adicionais em webhooks (US-PG-003).
- Situação da integração com PesaPal — marcada como vestigial.

## Última mudança
A recente auditoria e correção de testes relacionados ao SQLite garantiram que o sistema mantenha sua integridade e confiabilidade. Além disso, um handoff foi realizado para melhor integração da funcionalidade de boleto unificado, reforçando a coesão do módulo.

## Proveniência (destilado de)

- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- handoff `handoffs/2026-06-08-2115-boleto-unificado-merge-c-comando-existente.md` (2026-06-08) — 2026-06-08-2115-boleto-unificado-merge-c-comando-existente.md
