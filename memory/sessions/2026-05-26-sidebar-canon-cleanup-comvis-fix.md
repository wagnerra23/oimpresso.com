---
date: 2026-05-26
hour: "05:00 BRT"
duration: "3.5h"
topic: "Sidebar canon cleanup — 4 ondas Wagner: FINANÇAS flat, Vendas absorve Catalogue+Woo como ghosts, Fiscal remove cockpit duplicado, ComVis hub stub"
authors: [W, C]
outcomes:
  - "4 PRs mergeados (#1588 #1591 #1594 #1595) — sidebar refactor + bug fix módulo ComVis"
  - "Pattern emergente: NO-OP modifyAdminMenu quando módulo vira ghost de hub (4 aplicações)"
  - "Descoberto: CompanyPicker switch-business é STUB desde commit origem (Sprint 0 cockpit) — não regressão"
  - "Descoberto: Comunicação Visual Sprint 2 (4 telas Inertia) nunca entregue — sidebar apontava pra URLs 404 desde scaffold"
  - "Gotcha catalogado: git worktree remove --force segue mklink /J e deleta dir compartilhado (vendor)"
prs: [1588, 1591, 1594, 1595]
us:  []
related_adrs:
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0061-conhecimento-canonico-git-mcp-zero-automem"
---

# Session log 2026-05-26 — sidebar canon cleanup + ComVis fix

## TL;DR

Wagner começou perguntando "o que aconteceu com sidebar parece regrediu". Diagnóstico achou: CompanyPicker switch-business é STUB pré-existente (não regressão). Sessão pivotou pra 4 ondas de cleanup de sidebar dirigidas por ele direto, todas mergeadas via `--admin --squash`. Pivot final: módulo Comunicação Visual reportado "todo quebrado" — Sprint 2 nunca foi entregue, sidebar apontava pra URLs 404 desde scaffold Sprint 1. Corrigido via hub stub.

## Contexto

Sessão não-planejada — Wagner abriu o app e percebeu mudanças no sidebar que confundiram com regressão (na verdade eram PRs anteriores #1540/#1541/#1547/#1573 que tinham mexido no sidebar nos dias anteriores). Investigação inicial → cleanup oportunista → bug genuíno descoberto no final.

## Cronologia

| Quando | Evento |
|---|---|
| 05:00 | Wagner: "o que aconteceu com o sidebar faça um diff e confira? parece que regrediu" |
| 05:05 | Diff `449e5a813..origin/main` — 6 mudanças intencionais (Fiscal flat, Oficina Auto → COMERCIAL, Governança removida, IA → dashboard). Não regressão. |
| 05:10 | Wagner especifica: "a seleção empresa". Achado: CompanyPicker `Sidebar.tsx:375-378` é STUB com `alert("em breve Fase 4")` desde commit origem `d3699b977`. Não regressão — dívida técnica não-paga. |
| 05:30 | Wagner: "Na parte Grupo financeira deve ter → Caixa, Cobrança, Financeiro, Cobrança Recorrente. Gateway deve ir para ghost" |
| 06:00 | PR #1588 — Financeiro DataController: 1 entry "Financeiro" + 13 ghosts → 3 entries flat (Caixa·Cobrança·Financeiro). Gateway vira ghost de Cobrança. PaymentGateway DataController.modifyAdminMenu vira NO-OP. |
| 06:30 | Wagner: "Catalogue QR + Woocommerce podem ir para ghost do modulo de venda no [...] popover" |
| 07:00 | PR #1591 — `app/Http/Middleware/AdminSidebarMenu.php` dropdown `__('sale.sale')` ganha `'ghosts'` no attributes. ProductCatalogue + Woocommerce DataController.modifyAdminMenu viram NO-OP. |
| 07:30 | Wagner: "Fiscal <- esse pode ser removido esta duplicado com o debaixo Notas Fiscais" |
| 08:00 | PR #1594 — Modules/Fiscal DataController: remove Entry 1 "Fiscal" (cockpit dashboard order 93). Active state da Notas cobre `/fiscal` raiz agora. |
| 08:15 | Wagner: "modulo de comunicação visual esta todo quebrado" |
| 08:30 | Diagnóstico: sidebar tinha dropdown com 4 sub-itens apontando pra URLs `/comunicacao-visual/admin/*` **inexistentes** em Routes/web.php. Sprint 1 entregou só APIs JSON. Sprint 2 (UI Inertia das 4 telas) nunca entregue — dívida desde scaffold. |
| 08:45 | PR #1595 — Hub stub: rota GET `/comunicacao-visual` → Inertia::render('ComunicacaoVisual/Index'). DataController dropdown → single entry. Index.tsx vira hub real com 4 cards "em construção" mostrando endpoint API + sprint status. |
| 09:00 | Wagner: "merge e salve memorias" |

## Entregas

- **[PR #1588](https://github.com/wagnerra23/oimpresso.com/pull/1588)** — `feat(financeiro,sidebar): grupo FINANÇAS = 4 entries flat (Caixa·Cobrança·Financeiro·CobRecorrente)` → merged
- **[PR #1591](https://github.com/wagnerra23/oimpresso.com/pull/1591)** — `feat(sidebar,vendas): Catálogo QR + WooCommerce viram ghosts do hub Vendas` → merged
- **[PR #1594](https://github.com/wagnerra23/oimpresso.com/pull/1594)** — `feat(sidebar,fiscal): remove entry "Fiscal" duplicada — 3 entries flat (Notas·Manifestação·Certificado)` → merged
- **[PR #1595](https://github.com/wagnerra23/oimpresso.com/pull/1595)** — `fix(comvis): hub stub /comunicacao-visual — substitui dropdown 4 sub-itens 404` → merged
- **Reference doc novo** — [pattern-sidebar-ghost-no-op-modify-admin-menu.md](../reference/pattern-sidebar-ghost-no-op-modify-admin-menu.md)
- **Reference doc novo** — [gotcha-worktree-junction-vendor-rm.md](../reference/gotcha-worktree-junction-vendor-rm.md)

## Decisões cinzentas resolvidas

| Pergunta | Decisão Wagner | Justificativa |
|---|---|---|
| Gateway de Pagamento vira ghost de quem? | Ghost de Cobrança | Gateway = como receber dinheiro, semanticamente perto de Cobrança. Coerente com FIN-005. |
| Catalogue QR + Woo: ghosts ou esconder? | Ghosts do hub Vendas | Canais de venda subordinados, não merecem entry top-level paralela |
| Fiscal cockpit dashboard: virar ghost ou só remover? | Só remover | Duplicava visualmente com "Notas Fiscais" logo abaixo. Rota /fiscal continua URL direta |
| ComVis: implementar Sprint 2 (4 telas) ou hub stub? | Hub stub | Sprint 2 sem cliente piloto CV ativo. Stub mostra dívida visível em vez de esconder |

## Aprendizados / pegadinhas

- **Pattern emergente "ghost via NO-OP modifyAdminMenu"** aplicado 4× hoje (PaymentGateway, ProductCatalogue, Woocommerce + fiscal cockpit remove). Quando módulo vira ghost de hub: DataController.modifyAdminMenu vira `// No-op intencional. Ver docblock acima.`. Padrão catalogado em `memory/reference/pattern-sidebar-ghost-no-op-modify-admin-menu.md`.
- **LegacyMenuAdapter aceita `'ghosts'` no attributes de `$menu->dropdown(...)` core** — não precisa refactor pra `Menu::url`. Linha 322 do adapter faz pass-through. Aplicado no PR #1591 sem mexer no Menu legacy do UltimatePOS.
- **Wagner regra observada:** quando ele diz "duplicado/redundante", ele quer **remover sem virar ghost** (Fiscal cockpit). Quando ele diz "vai para ghost", quer preservar acessibilidade (Gateway/Catálogo/Woo). Distinção semântica importante.
- **Pegadinha vendor/junção worktree** — `git worktree remove --force` em worktree que tinha `mklink /J vendor → D:/oimpresso.com/vendor` SEGUIU O LINK e deletou o vendor real. Custo: ~5min `composer install` pra recovery. Catalogado em `memory/reference/gotcha-worktree-junction-vendor-rm.md`.
- **Comunicação Visual real state:** Sprint 1 entregou só APIs JSON. Sprint 2 nunca entregue. Sidebar apontava pra 4 URLs `/comunicacao-visual/admin/*` que nunca existiram. Hub stub PR #1595 é interim — quando cliente piloto CV ativar, ghosts ADR 0180 podem voltar.

## Próximos passos (não-bloqueante)

- [ ] `composer install` em `D:/oimpresso.com/` pra restaurar vendor (necessário pra rodar `php artisan`/`vendor/bin/pest` local)
- [ ] Deploy Hostinger (`git pull` + `php artisan optimize:clear` + `npm run build`) pra ver as 4 mudanças no biz=4 Larissa
- [ ] Smoke visual prod: sidebar FINANÇAS 4 entries · COMERCIAL ghost overflow no /sells · FISCAL 3 entries · /comunicacao-visual hub stub
- [ ] Reabrir SPEC ComunicacaoVisual se aparecer cliente piloto CV — US-COMVIS-SPRINT2 com escopo das 4 telas Inertia
- [ ] CompanyPicker switch-business — STUB desde origem, decisão futura sobre implementar Fase 4 ou esconder dropdown

## Referências

- ADR [0180-sidebar-v3-5-grupos-ghosts-header](../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) — pattern href direto + ghosts no PageHeader
- LegacyMenuAdapter.php:322 — pass-through de `ghosts`/`primary`/`shortcut` do attributes
- PR #1540 (OficinaAuto → COMERCIAL canon v3), PR #1541 (Fiscal flat), PR #1547 (Governança removida) — precursores desta onda
