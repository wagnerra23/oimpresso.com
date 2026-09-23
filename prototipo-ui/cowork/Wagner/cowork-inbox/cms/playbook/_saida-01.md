---
sessao: "_saida-01"
thread: "01 · Admin de conteúdo do site em Inertia (F3)"
dono: "[C]"
data: 2026-09-23
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-01

## Entregue (fase 1 de 5 — a LISTA)

A thread pede o admin inteiro, e o próprio `01-admin-content.md` manda quebrar se passar de 300
linhas. Quebrei em 5 fases no `memory/requisitos/Cms/RUNBOOK-admin-content.md`; este PR é a fase 1.

- `Modules/Cms/Resources/js/Pages/Admin/Content/Index.tsx` — lista por tipo (Páginas · Blog ·
  Depoimentos) com contadores, selos em PT-BR, endereço público e excluir só em página livre.
  **É a prova do índice.**
- `CmsPageController::index()` → `Inertia::render('Admin/Content/Index')`, `paginas` deferida.
  Ordem `priority` com vazio no fim (R6) — o Blade punha NULL primeiro.
- Trio: `Index.charter.md` (draft, curto) + `Index.casos.md` (UC-CMS-01/02/03/20/21) +
  `Modules/Cms/Tests/Feature/CmsConteudoIndexContratoTest.php`, ligado na lane `verticais-pest.yml`
  (trigger + `paths-filter` + run-set).

## Placar

entregue 1 de 1 prova do índice · 5 de 18 UC do F1 cobertos · ausentes os outros 13 UC do F1
(editor, exclusão recusada no servidor, lote, demo, formulário público) por serem das fases 2–4.

## Não fiz (e por quê)

- Não copiei o charter/casos completos do [CC] (`../Index.charter.md`, 18 UC): Non-Goals e
  Anti-hooks são decisão [W], e UC sem teste que o cite reprova o casos-gate.
- Não mexi no whitelist `type`. ⚠️ Corrigido depois de escrito: o pedido §3.b (e a 1ª versão deste
  recibo) dizia que Store/Update validavam `in:page,post,banner` — **não validavam**, o #5992 já os
  alinhara. Sobrava só o `DeleteCmsPageRequest`, sem uso; [W] decidiu remover `post`/`banner` e isso
  foi no #7869.
- Não rodei o Pest no CT 100: o checkout de lá fica atrás do `main` e não se dá `git pull`
  sem combinar. O veredito vem da lane `verticais-pest` no PR.
- Smoke em produção (R1) fica para depois do merge, que é do [W].
