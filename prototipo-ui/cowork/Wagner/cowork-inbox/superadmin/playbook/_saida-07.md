---
sessao: "07"
titulo: Assinatura do negócio — recibo (Minha assinatura em Inertia)
dono: "[CL]"
data: "2026-10-05"
base: "origin/main cb1fe1d6f4"
---

# _saida-07 — Assinatura do negócio → Inertia

Placar: **entregue 1 de 1** (a prova do json passa: `SubscriptionController.php` contém
`Inertia::render(`). Conferir com `placar.mjs --thread 07` depois do merge.

## Entregue

- `SubscriptionController@index` → `Inertia::render('superadmin/MinhaAssinatura/Index')`, com uma
  prop `assinatura` em `Inertia::defer`: ativa, próximas, aguardando aprovação, histórico, catálogo
  de pacotes e os dois quadros do recibo (emissor e negócio).
- O modal `show_subscription_modal` virou **drawer** no histórico, com Imprimir.
- **Valor idêntico ao da Blade** (REGRA MESTRE): o preço sai como texto de `moedaComoBlade()`, que
  reproduz o `__currency_trans_from_en` (moeda do sistema, precisão e posição do negócio,
  arredondamento do accounting.js). Prova por 2 caminhos no PR: accounting.js real extraído do
  `public/js/vendor.js` × a fórmula nova, 8 de 8 iguais, incluindo o discriminante `1.005`.
- **Tier 0:** tudo do negócio filtra por `session('user.business_id')`; UC-SAMA-03 prova 98 × outro
  negócio. Pacote privado segue só para superadmin.
- Trio novo: `MinhaAssinatura/Index.tsx` + `.charter.md` + `.casos.md` (UC-SAMA-01..05), teste
  `Modules/Superadmin/Tests/Feature/SuperadminMinhaAssinaturaContratoTest.php`, ligado na lane
  `verticais-pest.yml`. RUNBOOK F1: `memory/requisitos/Superadmin/RUNBOOK-minha-assinatura.md`.
- `SPEC.md` US-SUPER-003 ganhou a Page na âncora; `SUPERFICIE.md` regerado.

## Provas do json

| prova | estado |
|---|---|
| `SubscriptionController.php` contém `Inertia::render(` | ✅ |

## Pendente / decisões [W]

- **Pagar (`pay`) segue Blade.** A ficha cita `:161`, mas a página carrega 9 parciais de gateway
  (Stripe, PayPal, Pix automático, offline…), cada uma com o checkout do provedor. Migrar é outra
  onda, com prova de valor própria. Nenhum gateway foi ligado ou mudado.
- `GET /all-subscriptions` e `GET /subscription/{id}` ficaram sem consumidor; não removidos.
- **Contrato visual:** a tela não tem contrato nem protótipo (o `superadmin-page.jsx` só desenha a
  visão do superadmin). As âncoras `data-contract` já estão no `.tsx`; aprovação F1.5 e a entrada
  no contrato são do [W]. Baseline de visual-regression não foi regravada (ADR 0409).
- Medida/alvo da tela: **NÃO MEDI** (a ficha não pede).

## PR

#8669 — `feat(superadmin): Minha assinatura em Inertia — thread 07`.
