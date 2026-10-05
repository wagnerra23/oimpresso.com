---
id: modules-superadmin-pages-superadmin-minhaassinatura-index-charter
page: /subscription
component: Modules/Superadmin/Resources/js/Pages/superadmin/MinhaAssinatura/Index.tsx
related_prototype: n/a (herda PT-01 Lista; superadmin-page.jsx só desenha a visão do superadmin)
owner: wagner
status: draft
last_validated: "2026-10-05"
related_us: [US-SUPER-003]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
runbook: memory/requisitos/Superadmin/RUNBOOK-minha-assinatura.md
---

# Page Charter — /subscription (Minha assinatura)

> Nasce `draft` na thread Superadmin/07 (Blade → Inertia). Vai a `live` no PR pós-deploy, com o
> smoke. Backend: `SubscriptionController@index`. Decisão D2 de [W] (2026-10-01): fica no Superadmin.

## Mission

Responde, para o **dono do negócio**, *"qual é o meu plano, até quando vale e o que mais posso
contratar?"*. Persona: admin do negócio com `superadmin.access_package_subscriptions`.

## Goals — Features (faz)

- Mostra a assinatura ativa (início, fim, dias restantes), as próximas e as que aguardam aprovação.
- Histórico das assinaturas **do próprio negócio**, com detalhe em drawer (o antigo modal-recibo:
  emissor, negócio, valor, datas, pago via, transação) e botão Imprimir.
- Catálogo de pacotes ativos, com limites (`0` = ilimitado), módulos, dias de teste e o link para
  pagar (`/subscription/{id}/pay`) ou o link próprio do pacote.

## Non-Goals — Features (NÃO faz)

- **Não paga aqui.** O pagamento segue na Blade `pay` (checkouts dos gateways). Nenhum gateway foi
  ligado ou mudado.
- **Não mostra assinatura de outro negócio.**

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

- ❌ **Não formatar dinheiro no front.** O preço chega como texto de `moedaComoBlade()` (moeda do
  sistema, precisão e posição do negócio, arredondamento do accounting.js). Trocar por
  `Intl.NumberFormat('pt-BR', BRL)` muda o texto para quem usa outra moeda ou outra precisão.
- ❌ **Não tirar o `business_id` do histórico** nem das listas ativa/próximas/aguardando.
- ❌ **Não navegar para o pagamento com Inertia `<Link>`**: a página é Blade.
- ❌ **Não mostrar pacote privado** para quem não é superadmin (paridade com a Blade).

## Contrato visual

Esta tela **não tem** `governance/design/contracts/*.contract.json` nem protótipo. As âncoras
`data-contract="superadmin.minha-assinatura.*"` já estão no `.tsx`. A aprovação F1.5 e a entrada no
contrato são decisão do [W].

## Divergências declaradas contra a Blade

| Blade | Produção | Por quê |
|---|---|---|
| tabela DataTables com 11 colunas | 4 colunas + drawer com o resto | densidade em 1280px; nenhum dado some |
| status cru (`approved`) | rótulo PT-BR (`RotuloAssinatura`) | dono único da tradução |
| "/ 1 Meses" | "/ mês" | plural PT-BR por mapa; o **valor** é idêntico |
| botões verdes "Pagar e assinar" | botão `outline` | tela de cliente, sem CTA chamativo (ficha 07) |

## Refs

- Casos: [Index.casos.md](Index.casos.md)
