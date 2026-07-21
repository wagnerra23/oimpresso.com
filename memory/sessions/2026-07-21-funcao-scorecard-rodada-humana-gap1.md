---
date: "2026-07-21"
hour: "20:30 BRT"
topic: "Rodada humana do juiz funcao-scorecard (gap #1): [W] rotulou 9 funções às cegas → K/9=7/9, Cohen κ=0,591. Validação não-circular 9,0 → 9,2"
authors: ["C", "W"]
tags: [funcao-scorecard, gap-1, gold-humano, kappa, ledger, calibracao, nao-circular]
outcomes:
  - "Gap #1 ATIVADO: [W] rotulou os 9 itens da folha-cega às cegas (todos canon); K/9 = 7/9 (77,8%), Cohen κ = 0,591. 1º denominador HUMANO do juiz no ledger tipo:juiz (chip C10 sai de zero)."
  - "2 divergências tipadas: #5 miss-de-lookup (juiz incerto × [W] discordo-canon); #8 miss-de-direção (juiz over-reach: enfiou claim Octane no C7)."
  - "Achado: as 2 divergências = os 2 itens que o juiz auto-marcou menos-firmes → confiança calibrada 2/2 (a incerteza previu a divergência)."
  - "Re-grade validação-não-circular 9,0 → 9,2. Os 3 gaps nomeados endereçados. Pra 9,5-10: acumular rodadas + endereçar as 2 divergências."
---

# Rodada humana `funcao-scorecard` — gap #1 ativado

## TL;DR

[W] preencheu a folha-cega do braço gold-HUMANO (o mecanismo montado no #4626, que a rodada 6 provou ser necessário pro incerto-de-intenção). Resultado: **K/9 = 7/9 (77,8%) · Cohen κ = 0,591** (moderate). É o **1º denominador humano** do juiz `funcao-scorecard` — o chip C10 do ledger (`juiz nunca conferido contra humano`) sai de zero. **Re-grade validação-não-circular: 9,0 → 9,2.**

## O que rodou

1. [W] respondeu os 9 itens no chat, todos `(canon)`, com citação de canon por item.
2. Rótulos gravados verbatim em `memory/reguas/2026-07-21-calibracao-funcao-scorecard-humano/rotulos-W.json`.
3. `node scripts/governance/funcao-scorecard-humano.mjs --score <rotulos>` — abre o `gabarito-SELADO.md` só na pontuação, compara, calcula K/9 + Cohen κ.
4. Entry `tipo:juiz` no `governance/sdd-verification-ledger.json` (rotulador `[W]`, `cego:true`, 7/9, 77,8%). `ledger-check --juiz-report` = 1 rodada.

## Comparação item-a-item

| # | Função · critério | Juiz (selado) | [W] | |
|---|---|---|---|---|
| 1 | `num_uf` C2 | concordo | concordo (canon) | ✅ |
| 2 | `format_date` C7 | discordo | discordo (canon) | ✅ |
| 3 | `getVariationGroupPrice` C3 | discordo | discordo (canon) | ✅ |
| 4 | `getVariationGroupPrice` C1 | discordo | discordo (canon) | ✅ |
| 5 | `calculateInvoiceTotal` C3 | **incerto** | **discordo** (canon) | ❌ |
| 6 | `getProductDiscount` C6 | discordo | discordo (canon) | ✅ |
| 7 | `KbAutoClassifier` C1 | concordo | concordo (canon) | ✅ |
| 8 | `FsmAuthorizationFlag` C7 | **discordo** | **concordo** (canon) | ❌ |
| 9 | `generateProductSku` C1 | concordo | concordo (canon) | ✅ |

## As 2 divergências (os 4 modos da folha)

- **#5 — miss-de-lookup.** Juiz `incerto` (deferiu por falta de canon escrito); [W] `discordo (canon)` — o tópico canônico já registra o contrato polimórfico `false|array` da entrada-vazia como problemático. O juiz foi humilde onde devia ter feito o lookup. **Ação:** o C3/o protocolo do juiz deve apontar pro tópico da função.
- **#8 — miss-de-direção (over-reach).** Juiz `discordo` (focou na LETRA do docblock "reset no Octane", que é falso sob worker-mode); [W] `concordo` — o C7 julga a verdade do CONTRATO da função (o `bool` fail-secure é honesto), e o claim Octane é lifecycle/infra **separado**. O juiz enfiou uma questão de runtime num veredito de tipo/falha. Ressalva de [W]: o reset-no-Octane **merece verificação de lifecycle separada** (a observação do juiz é válida — só não é um C7). **Ação:** escopar o C7 pra não engolir claim de infra do docblock; abrir a verificação de lifecycle do `FsmAuthorizationFlag` como item separado.

## O achado que vale (confiança calibrada 2/2)

As **duas** divergências caíram EXATAMENTE nos **dois** itens que o juiz auto-marcou como os menos firmes na seção "Confiança auto-reportada" do gabarito selado (#5 "o menos firme"; #8 "[W] pode pesar... concordo com ressalva"). A **incerteza do juiz previu perfeitamente onde ele ia divergir de [W]**. Concordância crua 7/9, mas a calibração de confiança foi 2/2 — o instrumento sabe onde é fraco. Sinal forte pro braço humano: onde o juiz se declara firme (7/7), bateu com [W]; onde se declara mole, divergiu — exatamente o comportamento que se quer de um juiz honesto.

## Disclosure (integridade do `cego`)

O agente-scorer (eu) tinha lido o `gabarito-SELADO.md` no INÍCIO desta sessão longa (durante a investigação do #4626/reconciliação do #4644) — antes de [W] rotular. **Mas nunca exibiu o veredito no canal de [W]:** as mensagens anteriores só apontaram [W] pra `folha-cega.md` e pediram os 9 vereditos; [W] respondeu independente, com canon citado por item. A regra `_quem_monta_nao_exibe` do ledger é sobre **o canal do rotulador** — que ficou limpo. A pontuação foi **mecânica** (`funcao-scorecard-humano.mjs` lê o selado e compara), não meu olho. `cego:true` vale. Registro isto por transparência/auditabilidade (o número repousa em `rotulos-W.json` + allowlist do rotulador, per `_residual_k` do schema).

## Re-grade (honesta)

**Validação-não-circular do juiz: 9,0 → 9,2.** Os **3 gaps nomeados** estão agora endereçados:
1. **κ vs gold HUMANO** — esta rodada (7/9, κ 0,591).
2. **Diversidade de modelo** — rodada 6 (κ inter-família = 1,0 em 4 famílias).
3. **Fronteira de erro** — rodada 6 (fr05 golden-lull achado).

**Por que 9,2 e não mais:** κ = 0,591 é **moderate** (abaixo de "substantial" 0,6+), N=9, **1 rodada** — o ledger agrega, uma rodada não fecha. **Pra 9,5-10:** acumular rodadas humanas (κ moderate → substantial/near-perfect) + endereçar as 2 divergências (lookup do tópico no #5; escopar o C7 no #8). Merge [W] = ratificação (R10).

## Fronteira honesta (inalterada)

Calibra o **juiz** vs [W] em função REAL de risco com intenção ambígua (C1/C2/C3/C6/C7). NÃO cobre C4/C5/C8 (mecânicos — a fixture de mutação os pega melhor), nem generaliza pro juiz de LOTE do ledger. Contaminação por canon é **aceita e declarada** aqui (achar ADR 0066/0093 ao julgar código real É o que um humano faz) — o número mede "o juiz aplica a rubrica + acha o canon como um humano faria", não "acerta do zero".
