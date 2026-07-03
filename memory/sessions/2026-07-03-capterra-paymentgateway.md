# Session — capterra-senior PaymentGateway (2026-07-03)

> **Tarefa:** Adversário de mercado do módulo PaymentGateway (Passo 1 do [template-onda-modulo](../requisitos/_Governanca/programa-ondas/template-onda-modulo.md)). Aprovado Wagner [W] 2026-07-03. Read-only, worktree fresco de `origin/main`@`7442c27c43`.
> **Encaixe:** a ficha **complementa** o [PLANO-ONDA5-SIMPLIFICADA.md](../requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md) ativo — NÃO abriu roadmap paralelo (gate T6).

## Entregável

- [`memory/requisitos/PaymentGateway/CAPTERRA-FICHA.md`](../requisitos/PaymentGateway/CAPTERRA-FICHA.md) — 10 seções, 6 concorrentes × 8 dimensões, 19 capacidades P0-P3, **nota 67/100 (Médio)**.

## Método

1. Base guard: checkout estava −4688 de origin/main → criado worktree fresco `origin/main`@`7442c27c43` (`.claude/worktrees/capterra-pg-fresh`).
2. Passo 1 lido: template-onda-modulo + PLANO-ONDA5 + BRIEFING + SPEC (US-PG-001..009) + SCOPE + README + CONTRACTS.
3. Inventário de código real (não docs): 6 drivers API + 11 CNAB + contratos + reconciliação + webhooks + 43 testes.
4. Pesquisa concorrente em 2 agents paralelos (WebSearch): (a) Asaas/Iugu/Pagar.me; (b) Stripe/MercadoPago/Cielo — 8 dimensões cada, com citações.
5. Síntese: matriz + score ponderado (P0=4/P1=2/P2=1/P3=0.5) + gaps impacto×esforço.

## Estado real do código (origin/main)

**Drivers API (6):** Inter, Asaas, C6, BcbPix, Pagarme, SicoobApi.
- Boleto: Inter/Asaas/C6/Pagarme/Sicoob (5) · Pix cob/cobv: Inter(+cobv)/Asaas/C6/Pagarme/Sicoob · Pix Automático (recv): só BcbPix · Cartão: só Asaas+Pagarme · Refund: Asaas/Inter-PIX/Pagarme (C6/Sicoob/BcbPix → NotSupported).

**CNAB (11):** Ailos, BB, Banrisul, Bradesco, BTG, Caixa, Cresol, Itaú, Santander, Sicoob, Sicredi (remessa/retorno via CnabBoletoAdapter + CnabRetornoProcessor).

**Webhook/reconcile:** HMAC real em Pagarme/InterPix/Sicoob; legacy 4 fixados (US-PG-002 @98cae0a); idempotência `gateway_webhook_events` UNIQUE; reconcile single-source `ReconciliarCobrancaService` (push job + pull polling). Pendente: throttle/replay (US-PG-003), Inter mTLS (US-PG-006), URL pública CT100 (US-PG-007), cutover webhooks Onda 3 + orphan-retry flag OFF (US-PG-008).

**Segurança:** config_json `encrypted:array` (US-PG-001 landed) + RewrapCredentialsCommand.

## Nota e diferenciais

- **67/100 Médio** (sobe de 57 do audit inline 2026-05-25 após US-PG-001+002). `module-grade v3`=63.
- **2 diferenciais que nenhum dos 6 concorrentes tem:** CNAB 240/400 multi-banco (11 drivers) + multi-gateway isolado por `business_id` (banco-direto, sem spread de PSP).
- **Fato regulatório:** Pix Automático LIVE 16/06/2025; 5 dos 6 concorrentes expõem API (Stripe NÃO no BR). O `BcbPixDriver` é aposta early correta mas sem homologação/smoke.

## Gaps P0/P1 priorizados (alimentam Passo 2 `/comparativo`)

- **G1** Webhook hardening (US-PG-003) — P0, baixo esforço.
- **G2** Refund uniforme nos 6 drivers — P0.
- **G4** Cutover webhooks Onda 3 + habilitar orphan-retry (dry-run + REGRA MESTRE) — P0.
- **G3/G12** Pix Automático homolog + smokes Onda 5 (US-PG-009) — humano-limitado (ADR 0106).
- **G6** Split — P2, só com sinal de cliente (ADR 0105); feature-wish hoje.

## Próximo passo (não executado — aguarda Wagner)

- Passo 2 do template: `/comparativo PaymentGateway` → CAPTERRA-INVENTARIO.md + batch tasks-create (aguarda OK [W], publication-policy).
- Deliverável desta sessão (FICHA + log) escrito no worktree fresco; **não commitado/pushed** (R10 — aguarda aprovação Wagner pra virar PR).
