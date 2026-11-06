---
id: requisitos-oficina-auto-service-orders-create-edit-visual-comparison
slug: oficinaauto-service-orders-create-edit-visual-comparison
title: "OficinaAuto — Comparativo de runtime de Create e Edit da OS (OsCreateDrawer)"
type: visual-comparison
module: OficinaAuto
status: draft
date: "2026-11-06"
canon_reference: prototipo-ui/cowork/Wagner/oficina-forms.jsx (OsCreateDrawer, via related_prototype dos dois charters)
inertia_target: resources/js/Pages/OficinaAuto/ServiceOrders/{Create,Edit}.tsx
revisions:
  - 2026-11-06 V1 — registro do lote 6 da auditoria do módulo; vivo não medido (403)
---

# OficinaAuto · Create e Edit da OS — comparativo de runtime

> As duas telas ficam num arquivo só porque têm a **mesma âncora**: o `OsCreateDrawer` de
> `oficina-forms.jsx` serve create/store e edit/update (cabeçalho `oficina-forms.jsx:3`,
> função em `:208`).
>
> **Âncora:** `node scripts/design/ancora.mjs OficinaAuto/ServiceOrders/Create` (e `/Edit`) →
> `related_prototype (charter)` → `prototipo-ui/cowork/Wagner/oficina-forms.jsx`.

## Medição de 2026-11-06: não medido (403)

Nenhuma das duas telas pôde ser medida no staging. Sonda somente leitura (Playwright), login em
`/_visreg-login/24?to=/home&t=$VISREG_LOGIN_TOKEN` e depois `goto` na rota, 2026-11-06T18:33Z,
staging `8723405fbe` (2026-10-14, atrás do `main`).

| Tela | Rota | Status | Causa |
|---|---|---|---|
| Create | `GET /oficina-auto/ordens-servico/create` | **403** `Forbidden` | `create()` exige `superadmin` ou `oficinaauto.service_order.create` (`ServiceOrderController.php:465-466`) |
| Edit | `GET /oficina-auto/ordens-servico/68/edit` (OS da empresa 99) | **403** `This action is unauthorized.` | `edit()` chama `authorize('update', $order)`; a policy exige `oficinaauto.service_order.update` e o mesmo tenant (`ServiceOrderPolicy.php:49-51`) |

Usuário: u24 (empresa 99). Nenhum usuário de tenant de teste com login visreg tem as permissões
`oficinaauto.service_order.{view,create,update}` (u2 empresa 98, u3 empresa 2, u24 e u385 empresa 99).
A empresa 98 não tem OS.

**Nada foi gravado no banco do staging** para destravar (decisão da coordenação). O conserto que dura
é o seed do tenant de teste ganhar as três permissões, item separado deste registro.

Rodar o `design-diff-lote` aqui gravaria a página 403 como `DIVERGE (bug)`: o driver não confere o
status HTTP nem `componenteInertia`. Assinatura e detalhe no comparativo do Board
(`ordens-servico-board-visual-comparison.md`, §Achado de instrumento, PR #10585). Por isso esta rodada não gravou medidas.

## O que falta para medir, além da permissão

- **O driver não deriva rota do shell para nenhuma das duas** (`design-diff-lote.mjs --dry`): o
  protótipo abre o drawer a partir do Board, não por rota própria. Precisa de override em
  `governance/design/targets/roles/OficinaAuto--ServiceOrders--{Create,Edit}.json` com `token` e
  passo `abrir.design` (clicar "Nova OS" no Board do protótipo; no Edit, abrir uma OS e editar).
- **Edit é rota parametrizada:** precisa de `--url` com uma OS do tenant de teste.

## Itens da lista (não consertos)

1. **O `page:` do charter do Create aponta para uma rota que não existe.**
   `Create.charter.md` diz `/oficina-auto/service-orders/create`; essa rota devolve **404** no staging.
   A rota real é `/oficina-auto/ordens-servico/create` (`Modules/OficinaAuto/Routes/web.php:80`,
   nome `oficinaauto.orders.create`). O `page:` do Edit (`/oficina-auto/ordens-servico/{id}/edit`)
   está certo.
2. Seed do tenant de teste com `oficinaauto.service_order.{view,create,update}` (fora deste lote).
3. Overrides de `abrir` para os dois drawers, depois de 2.
