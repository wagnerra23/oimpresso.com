---
id: modules-superadmin-pages-superadmin-pagar-index-charter
page: /subscription/{id}/pay
component: Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.tsx
related_prototype: "n/a (protótipo gerado na migração e publicado como Artefato: https://claude.ai/artifact/N96PHZscAcmPfWYeTeNJjJ — não há tela de pagamento em cowork/Wagner/)"
owner: wagner
status: draft
last_validated: "2026-10-27"
related_us: [US-SUPER-003]
parent_module: Superadmin
related_adrs: [104, 93, 170]
tier: B
charter_version: 1
runbook: memory/requisitos/Superadmin/RUNBOOK-pagar.md
---

# Page Charter — /subscription/{id}/pay (Pagar assinatura)

> Nasce `draft` na migração Blade → Inertia de 2026-10-27. Backend: `SubscriptionController@pay`
> (e `registerPay`, que chama `pay($id, 1)`), `montarPagamento()` e `confirm()`. Ver
> [RUNBOOK-pagar](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-pagar.md).

## Mission

Responde, para o **dono do negócio**, *"quanto custa este pacote e como eu pago?"*. Persona: admin
do negócio com `superadmin.access_package_subscriptions`, vindo de Minha assinatura ou do cadastro
de empresa nova.

## Goals — Features (faz)

- Mostra o pacote: nome, preço (texto pronto do servidor), ciclo, limites e dias de teste.
- Oferece **só** as formas ligadas entre Pix automático e pagamento offline, nessa ordem.
- No offline, mostra os dados de pagamento cadastrados nas Configurações do Superadmin.
- Confirmar envia a forma escolhida para `confirm()`, que volta para Minha assinatura com o aviso.

## Non-Goals — Features (NÃO faz)

- **Não oferece Stripe, PayPal, Razorpay, Paystack, Pesapal nem Flutterwave** (decisão [W] 2026-10-27).
- **Não calcula nem formata dinheiro**: o preço chega em texto; o valor cobrado é lido de novo no
  servidor.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

- ❌ **Não religar outro gateway na tela** nem tirar a recusa em `confirm()` sem decisão [W].
- ❌ **Não mandar preço do front para o back.** `confirm()` recebe só `gateway`; o preço é o do pacote.

## Refs

- Casos: [Index.casos.md](Index.casos.md)
