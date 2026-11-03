---
id: requisitos-superadmin-runbook-pagar
title: "RUNBOOK — /subscription/{id}/pay (Pagar assinatura · Blade → Inertia)"
module: Superadmin
tela: superadmin/Pagar/Index
owner: W
status: ativo
last_validated: "2026-11-03"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0170-paymentgateway-extracao-camada-cobranca
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/subscription/{id}/pay` (Pagar assinatura, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)). É a tela em
que o **negócio** confere o pacote escolhido em "Minha assinatura" e escolhe como pagar.

- **Fonte de design:** `prototipo-ui/cowork/Wagner/superadmin-pagar-page.jsx` (protótipo de 27/10/2026).
- **Decisão [W] 2026-10-27** (registrada no cabeçalho do próprio protótipo): a tela oferece **só Pix
  automático e pagamento offline**. Os outros 6 gateways (Stripe, PayPal, Razorpay, PesaPal, Paystack,
  Flutterwave) não aparecem na tela nova.
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.tsx`.
- **Flag:** `useV2SuperadminPagar` (GrowthBook, por empresa). Desligada = Blade de sempre.
  `?classico=1` força a Blade mesmo com a flag ligada. Canary: só `business_id=1`.

## 1. O que mudou no backend

Só o fim de `SubscriptionController::pay()`: com a flag ligada e fora do fluxo de cadastro
(`/register-pay` segue Blade), em vez de `view('superadmin::subscription.pay')` devolve
`Inertia::render('superadmin/Pagar/Index', ['pagamento' => ...])`. Todas as checagens antes disso
são as mesmas: permissão, pacote privado, pacote de uso único, pacote grátis (assina direto e volta).

O payload traz o pacote (nome, preço já formatado por `moedaComoBlade()`, ciclo, limites, dias de
teste), as formas de pagamento **que já estão configuradas** (o mesmo `_payment_gateways()`,
filtrado para `paymentgateway_pix_automatico` e `offline`), os dados bancários do pagamento offline
(`offline_payment_details`, como texto) e a URL do `confirm()`.

## 2. Valor — nada muda

- O preço exibido é texto do servidor (`moedaComoBlade`, o mesmo de "Minha assinatura", que reproduz o
  `display_currency` da Blade). A tela não formata nem calcula dinheiro.
- O valor cobrado continua calculado no servidor: os botões postam no **mesmo** `confirm()` com o mesmo
  campo `gateway`. Nenhum preço, gateway ou registro de assinatura mudou. Por isso a regra mestre de
  cálculo de valor não se aplica — não há antes→depois de valor.

## 3. Tier 0 — invariantes

- `pay()` e `confirm()` usam `session('user.business_id')`; a tela não recebe nem envia `business_id`.
- Pacote privado segue barrado para quem não é superadmin (mesma checagem, antes do render).
- O botão fica desabilitado depois do primeiro clique (evita duas cobranças Pix).

## 4. Smoke prod (R1)

Com a flag ligada para a empresa 1: abrir `/subscription`, clicar em um pacote pago e conferir a tela
nova (pacote, preço, as duas formas). **Não** concluir pagamento real. Conferir `?classico=1` abre a Blade.

## 5. O que NÃO entrou

- `/register-pay` (cadastro de empresa nova, layout de login) segue Blade.
- As 7 parciais `pay_*.blade.php` dos outros gateways continuam no repo, servindo a Blade.
- A mensagem de retorno do `confirm()` vai para "Minha assinatura" (redirect de sempre), não fica na
  tela de pagamento como no protótipo.
