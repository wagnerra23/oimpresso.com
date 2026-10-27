---
id: requisitos-superadmin-runbook-pagar
title: "RUNBOOK — /subscription/{id}/pay (Pagar assinatura · Blade → Inertia)"
module: Superadmin
tela: superadmin/Pagar/Index
owner: W
status: ativo
last_validated: "2026-10-27"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0170-paymentgateway-extracao-camada-cobranca
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/subscription/{id}/pay` (Pagar assinatura, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)). É a tela em
que o **negócio** paga um pacote, aberta pelo botão "Pagar e assinar" de Minha assinatura e, no
cadastro de empresa nova, por `/subscription/{id}/register-pay`.

- **Decisão [W] 2026-10-27:** a tela oferece só **Pix automático** e **pagamento offline**. Stripe,
  PayPal, Razorpay, Paystack, Pesapal e Flutterwave saíram da tela, e `confirm()` passou a recusá-los.
  O código desses 6 gateways (métodos `pay_*`, callbacks e rotas) sai num PR separado.
- **Fonte de design:** protótipo gerado nesta onda e publicado como Artefato (não há tela de
  pagamento em `cowork/Wagner/`): https://claude.ai/artifact/N96PHZscAcmPfWYeTeNJjJ.
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.tsx`.

## 1. O que mudou no backend

- `pay()` mantém as mesmas checagens (pacote privado, pacote de uma vez só, pacote grátis que assina
  direto) e, para pacote pago, devolve `Inertia::render('superadmin/Pagar/Index')` com a prop
  `pagamento` em `Inertia::defer`. `registerPay()` continua chamando `pay($id, 1)`.
- `montarPagamento()`: pacote (preço em TEXTO via `moedaComoBlade`), formas ligadas entre
  `GATEWAYS_DA_TELA` e os dados do pagamento offline (só quando o offline está ligado).
- `confirm()`: gateway fora de `GATEWAYS_DA_TELA`, ou desligado, volta para `/subscription` com erro
  e **não cria assinatura**. Pix e offline seguem exatamente o caminho de antes.
- Apagadas `subscription/pay.blade.php` e as 8 parciais `subscription/partials/pay_*.blade.php`.

## 2. Valor (regra mestre)

O valor **cobrado** não mudou de caminho: Pix cobra `round(price * 100)` centavos em
`confirm_paymentgateway()`; offline cria a assinatura `waiting` com o preço do pacote. A tela só
**mostra** o preço, em texto pronto do servidor, no mesmo formato que o `__currency_trans_from_en`
da Blade produzia (prova por dois caminhos no PR).

## 3. Tier 0 — invariantes

- Tudo do negócio vem de `session('user.business_id')`; a tela não recebe nem mostra outro negócio.
- Pacote privado segue só para superadmin.
- Permissão: `superadmin.access_package_subscriptions` (é a tela do cliente, não do superadmin).

## 4. Smoke prod (R1)

Após o deploy, com um negócio de teste (nunca biz=4): abrir `/subscription`, clicar em "Pagar e
assinar" num pacote pago e ver as formas ligadas e o preço igual ao do cartão do pacote. **Não**
confirmar pagamento real.
