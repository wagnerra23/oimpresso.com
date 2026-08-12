---
date: "2026-08-11"
slug: venda-v3-o-smoke-fechou-r1-cumprido
hour: "18:16 BRT (21:16 UTC)"
topic: "Venda V3 — o smoke pós-deploy fechou, R1 cumprido"
authors: [C, W]
prs: [5613]
us: [US-SELL-058]
tldr: "Desfecho do handoff das 17:43, que fechou com o smoke NEGATIVO. O deploy concluiu 21:15:42Z e a 2ª medição em prod deu DEPLOY_CHEGOU=true com os 13 campos em 34,19px e o controle negativo intacto (30px fora do escopo). R1 cumprido."
outcomes:
  - "R1 CUMPRIDO — smoke pós-deploy positivo, medido em prod às 21:16Z"
  - "13 campos visíveis em 34,19px (3 cw-input + 8 grade + 2 select) + h1 22px"
  - "Controle negativo intacto: .cw-input fora do escopo segue 30px — não vazou pras ~120 telas"
  - "As 3 réguas de campo (30/32/36px) viraram 1, e é a do protótipo"
---

# Venda V3 — o smoke fechou, R1 cumprido

Desfecho do handoff das
[17:43](2026-08-11-1743-venda-v3-mergeada-e-o-smoke-que-nao-fechou.md), que
fechou com o smoke **negativo**. Aquele retrato fica como está — append-only:
às 20:43Z o deploy realmente não tinha chegado, e apagar isso seria reescrever
o que aconteceu. Este registra o depois.

## O deploy concluiu

`Deploy to Hostinger`, run `31533787946`, SHA `8e8ba116` (o merge do
[#5613](https://github.com/wagnerra23/oimpresso.com/pull/5613)):
**`completed/success` em `2026-08-11T21:15:42Z`**.

## 2ª medição em prod — 21:16 UTC ✅

Mesma sonda da medição negativa, para ser comparável:

```
DEPLOY_CHEGOU   : true
regra servida   : .venda-v3 .cw-input { border-radius:6px; height:auto;
                  padding:7px 10px; font-size:13px; line-height:1.4 }
wrapper_no_dom  : true
campos .cw-input: 3x 34,19px   ✓   (era 30px)
campos da grade : 8x 34,19px   ✓   (era 32px)
select trigger  : 2x 34,19px   ✓   (era 30px)
h1              : 22px · caixa 28,59px   ✓   (era 20px)
folhasCegas     : 0
CONTROLE NEG.   : .cw-input fora do escopo = 30px f12,5  ✓
```

O controle negativo é a metade que importa tanto quanto o resto: **o DS global
segue 30px**, então a mudança não vazou para as ~120 telas que a
[ADR UI-0015](../requisitos/_DesignSystem/adr/ui/0015-padrao-cowork-default-forms.md)
governa. Screenshot da tela em produção capturado.

**As três réguas viraram uma, e ela é a do protótipo.**

## A distinção que o smoke existe pra fazer

Antes do deploy eu já tinha provado a **regra** — injetando o CSS compilado na
prod real e medindo 34,19px dentro do escopo × 30px fora. Isso nunca provou que
o **build chegou ao servidor**; só a 2ª medição provou. São coisas diferentes, e
declarar "pronto" na primeira teria violado o R1. O handoff das 17:43 registrou
o negativo justamente por isso.

## Erro de método, pego a tempo

A 1ª tentativa da 2ª medição saiu com o **DOM vazio** (`h1: null`, zero campos)
porque naveguei com `?smoke=1` e a página não montou. E era tentador ler como
sucesso: o `DEPLOY_CHEGOU` **já dava `true`** naquela leitura, porque a regra
estava no CSS servido mesmo sem a página montar. Um campo verdadeiro ao lado de
um DOM vazio é exatamente a forma de "número plausível que responde outra
pergunta". Não concluí nada e refiz sem o query param.

## Estado final do trabalho

- `main` tem o código (`8e8ba116a74`), deployado e **verificado em produção**.
- **R1 cumprido** para esta entrega.
- Resíduos que continuam abertos, nenhum tocado aqui:
  - os **14 `h-8 text-[12.5px]`** inertes na V3 (higiene — removê-los não muda
    um pixel, por isso não entraram no PR de densidade);
  - o alinhamento **global** do `.cw-input` (30 → 34,19px), que é a opção 2 do
    fork e decisão de produto: atinge ~120 telas, incluindo o drawer Cliente
    que a Larissa opera.
- **MCP indisponível** a sessão toda — as tools não aparecem no registro.
  Declarado, não inventado.
