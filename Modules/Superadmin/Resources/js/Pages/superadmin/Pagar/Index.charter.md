---
id: modules-superadmin-pages-superadmin-pagar-index-charter
page: /subscription/{id}/pay
component: Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/superadmin-pagar-page.jsx
owner: wagner
status: draft
last_validated: "2026-11-03"
related_us: [US-SUPER-003]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
runbook: memory/requisitos/Superadmin/RUNBOOK-pagar.md
---

# Page Charter — /subscription/{id}/pay (Pagar assinatura)

> Nasce `draft`, atrás da flag `useV2SuperadminPagar` (canary empresa 1). Backend:
> `SubscriptionController@pay`. Fonte de design: `superadmin-pagar-page.jsx` (27/10/2026).

## Mission

Responde, para o **dono do negócio**, *"é este o pacote que escolhi, quanto custa e como eu pago?"*.
Persona: admin do negócio com `superadmin.access_package_subscriptions`, vindo de "Minha assinatura".

## Goals — Features (faz)

- Mostra o pacote: nome, preço (texto do servidor), ciclo, limites (`0` = ilimitado) e dias de teste.
- Oferece **só** as formas que a decisão [W] de 2026-10-27 manteve e que estão configuradas:
  Pix automático e pagamento offline.
- Cada forma posta no mesmo `confirm()` da Blade, com o mesmo `gateway`.

## Non-Goals — Features (NÃO faz)

- **Não oferece Stripe, PayPal, Razorpay, PesaPal, Paystack nem Flutterwave** (decisão [W] 2026-10-27).
- **Não cobra nem calcula valor.** O valor cobrado é o do pacote, lido de novo no servidor.
- **Não cobre `/register-pay`** (cadastro de empresa nova) — segue Blade.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

- ❌ **Não formatar dinheiro no front.** O preço chega pronto de `moedaComoBlade()`.
- ❌ **Não mandar preço nem `business_id` no POST.** Só `gateway`; o resto é do servidor.
- ❌ **Não reabilitar o botão depois do clique** enquanto a requisição corre (duas cobranças Pix).
- ❌ **Não renderizar os dados bancários como HTML.** São texto do superadmin; vão em `<pre>`.

## Divergências declaradas contra o protótipo

| Protótipo | Produção | Por quê |
|---|---|---|
| mensagem de retorno aparece na própria tela | volta para "Minha assinatura" com o aviso do `confirm()` | o `confirm()` redireciona; mudar isso é mexer no registro da assinatura |
| as duas formas sempre visíveis | só as configuradas (`enable_offline_payment`, credencial Pix) | forma não configurada não tem como cobrar |
