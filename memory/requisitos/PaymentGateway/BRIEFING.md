---
id: requisitos-payment-gateway-briefing
module: PaymentGateway
status: parcial
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — PaymentGateway (verdade destilada)

## Estado atual
Camada técnica de cobrança do oimpresso (boleto, PIX, cartão, conciliação e retorno CNAB), extraída do RecurringBilling pela ADR 0170 (hoje arquivada). Código implementado; a flag `PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED` nasce `false` (`Config/config.php`) e o schedule de retry só roda sob ela (`Kernel.php`) — sem cliente pagante (auditoria 2026-07: "docs only até Wagner ativar"). Ressalva: `paymentgateway:inter-importar-recebimentos` roda agendado em `live` com `--business=1`; `paymentgateway:inter-reconcile-pix` roda em `local`+`live` para todos os tenants com credencial Inter ativa (ver `app/Console/Kernel.php`) — consumo interno, não cliente. `README.md` e `SCOPE.md` ainda descrevem o módulo como Onda 0, registrado mas não habilitado — segue verdadeiro, mas os dois ancoram em "ADR 0170 proposto", e a 0170 hoje é `deprecated`/arquivada. O boleto Inter chega ao usuário pela via do RecurringBilling.

## Capacidades
- Drivers API REST (Inter, Asaas, C6, BCB Pix Automático, Pagar.me, Sicoob) e drivers CNAB por banco — inventário vivo em `Modules/PaymentGateway/Services/Drivers/` e `Services/Cnab/Drivers/`.
- Validação de assinatura fail-secure nos webhooks — HMAC-SHA256 em Inter/C6/Sicoob, token estático em Asaas e mTLS no BCB Pix (`WebhookProcessor::validateSignature`, `default => false`, controller responde 401); o Pagar.me valida HMAC-SHA256 no próprio `PagarmeWebhookController`; US-PG-002 segue `todo` no SPEC por falta de aceite formal, não por código ausente — a VULN P0-#2 do dossier de 2026-05-25 (SPEC:67) foi endereçada em código e o SPEC ainda não foi reconciliado.
- Credenciais com cast criptografado (`config_json` → `encrypted:array`; US-PG-001, SEC P0, ainda `todo` no SPEC e na fila `memory/requisitos/_ANCHOR-REVIEW-QUEUE.md`).
- Conciliação por polling (`InterReconcilePixCommand`) e retry de webhooks órfãos (`RetryOrphanWebhookCommand`, flag OFF por default).
- Tela de configuração de credenciais e retorno CNAB.

## Gaps
- Cadastro da URL de webhook PIX no Inter: o comando existe (`RegisterInterWebhookCommand`); falta o passo no wizard e o smoke no sandbox (US-PG-005 `todo`).
- Autenticação do webhook do Inter — mTLS vs HMAC (US-PG-006).
- Endurecimento dos webhooks: throttle, janela de timestamp e nonce (US-PG-003).
- PesaPal é vestigial: consta no enum das migrations, em docblocks e num branch de `warnFor()` que o marca deprecated — não há driver.

## Última mudança
Correção de carga: `$queue` redeclarada colidia com o trait `Queueable` e era fatal ao carregar o job (#5987, 2026-08-19); depois disso, só higiene cross-cutting (quarentena de teste #6018, ponteiros de design #6270, i18n #6307, a11y #6465). Nenhuma capacidade ou flag mudou desde a auditoria de julho (o #5987 mudou comportamento de carga, não capacidade); a atividade de julho foi avaliação — CAPTERRA-FICHA e Onda 2 test-only (PR #3739) — e a nota vive na FICHA, não aqui.

## Proveniência (destilado de)

- audit `requisitos/PaymentGateway/AUDITORIA-PAYMENTGATEWAY-2026-07.md` — AUDITORIA-PAYMENTGATEWAY-2026-07.md
- audit `requisitos/PaymentGateway/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/PaymentGateway/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-08-20-backup-migracao-ondas-0-a-3.md` (2026-08-20) — 2026-08-20-backup-migracao-ondas-0-a-3.md
- handoff `handoffs/2026-08-20-1138-backup-ondas-mergeadas-f3-bloqueada-por-transporte.md` (2026-08-20) — 2026-08-20-1138-backup-ondas-mergeadas-f3-bloqueada-por-transporte.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- session `sessions/2026-08-12-pages-para-dentro-dos-modulos-piloto.md` (2026-08-12) — 2026-08-12-pages-para-dentro-dos-modulos-piloto.md
- handoff `handoffs/2026-08-11-1336-fronteira-que-nao-era-e-o-plano-que-nao-funcionava.md` (2026-08-11) — 2026-08-11-1336-fronteira-que-nao-era-e-o-plano-que-nao-funcionava.md
