---
id: requisitos-oficina-auto-ordens-servico-board-visual-comparison
slug: oficinaauto-ordens-servico-board-visual-comparison
title: "OficinaAuto — Comparativo de runtime do Board (oficina-page.jsx)"
type: visual-comparison
module: OficinaAuto
status: draft
date: "2026-11-06"
canon_reference: prototipo-ui/cowork/Wagner/oficina-page.jsx (via visual_source do charter)
inertia_target: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
related_gap: memory/requisitos/OficinaAuto/ordens-servico-board-gap.md
revisions:
  - 2026-11-06 V1 — registro do lote 6 da auditoria do módulo; vivo não medido (403)
---

# OficinaAuto · Board — comparativo de runtime

> Este arquivo guarda a **medição de runtime** (computed style, mesma sonda nos dois lados).
> A comparação por partes, com `arquivo:linha` nos dois lados, está no gap:
> [`ordens-servico-board-gap.md`](ordens-servico-board-gap.md). Os dois não se repetem.
>
> **Âncora:** `node scripts/design/ancora.mjs OficinaAuto/ServiceOrders/Board` →
> `oficina-page.jsx` (charter `visual_source`; shell route `oficinaauto`, `window.OficinaPage`).

## Medição de 2026-11-06: não medido (403)

O vivo **não pôde ser medido** no staging: a tela devolve **403** para todo usuário de tenant de
teste com login visreg.

| Verificação | Resultado |
|---|---|
| Rota | `GET /oficina-auto/ordens-servico` |
| Usuário | u24 (empresa 99, tenant fictício adversário) |
| Status | **403**, `document.title = "Forbidden"`, texto `403 Forbidden` |
| Causa | `ServiceOrderController::board()` exige `superadmin` ou `oficinaauto.service_order.view` (`ServiceOrderController.php:89-90`); u24 não tem nenhuma das três permissões `oficinaauto.service_order.{view,create,update}` |
| Outros usuários de teste | u2 (empresa 98), u3 (empresa 2) e u385 (empresa 99): também 403 |
| Checkout do staging | `8723405fbe` (2026-10-14), atrás do `main` |

Sonda (somente leitura, Playwright): login em `/_visreg-login/24?to=/home&t=$VISREG_LOGIN_TOKEN`,
depois `goto` na rota e leitura de status, título e texto. Rodada em 2026-11-06T18:33Z.

**Nada foi gravado no banco do staging** para destravar a medição (decisão da coordenação: a regra
"mexeu, registra" proíbe dar permissão direto no banco, e o banco é compartilhado). O conserto que
dura é o seed do tenant de teste ganhar `oficinaauto.service_order.{view,create,update}`. É item
separado deste registro.

### Achado de instrumento: a página 403 sai como `DIVERGE (bug)`

Rodando o driver contra o 403, ele **não acusa a não-medição**: grava `DIVERGE (bug)` (título
18px/700 × 22px/600, exit 1) como se tivesse medido o Board.

```bash
MSYS_NO_PATHCONV=1 VISREG_LOGIN_TOKEN=<.env do oimpresso-staging> node scripts/design/design-diff-lote.mjs \
  --incluir-comparadas --tela OficinaAuto/ServiceOrders/Board \
  --base-url https://staging.oimpresso.com --user-id 24 --tema light
```

O driver não lê o status HTTP da navegação nem confere `componenteInertia`. A assinatura do caso é
`prod.assinatura` com o texto `403 Forbidden`, KPI `count: 0`, `primary: null`, título preto e
`saude.tokensMortos` com os tokens do app (a página de erro não carrega o CSS). Os JSONs dessa
rodada foram descartados, não commitados. Detalhe: nota `oficinaauto-l6-instrumento.md` da pasta da
auditoria. O driver **não foi consertado** neste lote.

## Medição de 2026-09-18 (em `governance/design/targets/medidas/OficinaAuto--ServiceOrders--Board/`)

Veredito gravado: `DIVERGE (bug)`, D4 título `18px` (prod) × `22px` (design). Lida em 2026-11-06:

- **Mediu o Board de verdade.** `prod.assinatura` traz o texto da tela ("Oficina Auto · Recepção,
  diagnóstico, peças, execução e entrega de veículos · Imprimir fila · Nova OS · Quadro Lista Grade
  Fila · Visão Quadro ainda não configurado"). `componenteInertia: null` sozinho não indica página
  de erro: o que separa os dois casos é a assinatura.
- **O tenant não tinha FSM cadastrado** ("O processo FSM não está cadastrado pra este negócio"):
  KPI 0 e quadro vazio vêm do estado do tenant, não do desenho.
- **Os temas não estavam pareados** (`compare.sameTheme: false`). Tamanho de fonte não depende de
  tema, mas a leitura de cor dessa rodada não serve.
- **Medido no staging `8723405fbe`** (ou anterior), não no `main`.

Item da lista (não conserto): **título do Board 18px no vivo × 22px no protótipo**. Precisa ser
remedido com tema pareado e usuário com permissão antes de virar ação.

## Pendências

1. Seed do tenant de teste com `oficinaauto.service_order.{view,create,update}` (fora deste lote).
2. Driver `design-diff-lote` acusar página de erro como não-medição (fora deste lote).
3. Remedir o Board com tema pareado depois de 1.
