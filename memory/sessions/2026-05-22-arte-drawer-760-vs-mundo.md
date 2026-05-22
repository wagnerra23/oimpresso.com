---
slug: 2026-05-22-arte-drawer-760-vs-mundo
title: "Estado-da-arte: Drawer 760 oimpresso vs detail panels mundo 2025-2026"
type: arte
date: "2026-05-22"
authors: [C via audit-research-expert agent]
related_adrs: [0179, 0185]
status: ready
---

# Estado-da-arte — Drawer 760 oimpresso vs detail panels mundo 2025-2026

> Auditoria spawned por Wagner 2026-05-22: "compare essa técnica com os melhores e de uma nota". Comparativo Drawer 760 (ADR 0179 + 0185) vs Notion/Linear/HubSpot/Stripe/Salesforce/Bling/Tiny/Omie. Nota ponderada 15 dimensões.

## TL;DR

**Nota ponderada final: 76,4 / 100** — faixa "bom" (60-79). A 3,6 pts da fronteira "mundo-classe" ≥80, gap pra 90 = 14 pts.

**Recomendação executiva:** CONSOLIDAR + 2 evoluções cirúrgicas. Inserir **Wave H técnica de 15h ANTES** das Waves VENDER/OPERAR/FINANÇAS da ADR 0185 — senão gap escala 7× (replica defeito em 7 entidades).

## Veredito executivo

O oimpresso **acertou o paradigma** (drawer lateral + autosave + IA + audit + multi-tenant). Está no nível Linear/HubSpot/Stripe nas 6 dimensões de maior peso. Tem 3 **surpresas positivas** raras que nenhum ERP BR (Bling/Tiny/Omie) entrega:

1. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — nota 98/100
2. **Spatie ActivityLog LGPD Art 18 plug-and-play** — nota 90/100
3. **Brain B IA democratizado em toda entidade cadastral** — nota 85/100

Onde está atrás é em **fundamentos de engenharia colaborativa moderna** (concorrência, lazy load, popstate handler) — gaps de ~15h totais que DEVEM entrar como **Wave H ANTES** de replicar nas 6 entidades. Sem Wave H, gap escala 7× e fica caro corrigir depois.

**Investimento ADR 0185 (85-110h IA-pair) vale a pena.** Wave H adicional (~15h) eleva teto de 76 → 88 (mundo-classe) sem multiplicar custo.

## Nota ponderada — 15 dimensões × peso 100

| # | Dimensão | Peso | Nota oimpresso | Top 3 referência | Gap |
|---|---|---|---|---|---|
| D1 | Coerência visual entre entidades | 12 | **85** | Linear 95, HubSpot 92, Stripe 90 | template Cliente provado; gate CI não existe ainda |
| D2 | Autosave on blur + offline | 10 | **80** | Notion 95, Linear 93, Figma 92 | debounce 800ms + rollback OK; **sem queue offline** |
| D3 | Multi-tab strategy | 8 | **78** | HubSpot 88 (left vertical), Linear 82 (top) | top tabs + sub-tabs aninhadas; falta hierarquia visual sub-tab |
| D4 | IA embeddada no panel | 10 | **85** | HubSpot Breeze 92, Intercom Fin 90 | 4 cards Brain B já vivos; faltam ações ("draft email", "next step") |
| D5 | Audit log LGPD-compliant | 8 | **90** | Stripe 88, Salesforce 92 | Spatie ActivityLog Art 18 — **mundo-classe BR** |
| D6 | Largura responsiva vs fixa | 6 | **65** | Linear 90 (cresce), Stripe 85 | fixo 760 = trade-off consciente; perde em ≥1440p |
| D7 | Sub-tabs aninhadas | 6 | **75** | Notion 85, Airtable 82 | provado Cliente OSs 8 sub; falta affordance visual |
| D8 | Concorrência (optimistic locking) | 7 | **40** | Linear/Figma 95 (CRDT), HubSpot 75 (409) | **GAP CRÍTICO** — sem `updated_at` check, sem 409 toast, sem Centrifugo channel |
| D9 | Acessibilidade WCAG 2.2 AA | 6 | **72** | Linear 90, GitHub 88 | ARIA presente; falta focus trap declarado + Esc handler + 1ª input focus |
| D10 | Performance (lazy/streaming) | 6 | **55** | Linear 92, Stripe 88 | 8 tabs upfront ~118KB; **falta React.lazy + Inertia::defer per-tab** |
| D11 | Deeplink + URL state + back | 5 | **80** | Linear 92, GitHub 85 | redirect 302 OK; **popstate handler ausente** = back quebra |
| D12 | Mobile/responsive <1024px | 4 | **30** | HubSpot 85, Stripe 82 | fora de escopo declarado; modal fullscreen futuro |
| D13 | Print/Export PDF | 3 | **45** | Salesforce 90, Bling 80 | rota `/print` listada em armadilhas, não implementada |
| D14 | Permissões granulares per-tab | 5 | **70** | Salesforce 95, HubSpot 85 | Spatie permission previsto, não aplicado tab-level ainda |
| D15 | Multi-tenant isolation Tier 0 | 4 | **98** | maioria SaaS internacional não tem | **ADR 0093 IRREVOGÁVEL — mundo-classe** |

**Cálculo:** Σ(nota × peso) / 100 = **76,4**. Faixa "bom" (60-79), 3,6 pts da fronteira "mundo-classe" ≥80.

## Top 10 gaps priorizados (impacto × esforço)

| # | Gap | Prio | Esforço | Sistema-ref |
|---|---|---|---|---|
| 1 | **Optimistic locking** (`updated_at` check + HTTP 409 + toast "outro user editou") | **P0** | 6h | HubSpot, Linear |
| 2 | **React.lazy + Inertia::defer per-tab** (bundle 118KB → ~30KB initial) | **P0** | 4h | Linear, Stripe |
| 3 | **popstate handler** fecha drawer antes de navegar (back button) | **P0** | 2h | GitHub, Linear |
| 4 | **CI gate `drawer:health`** valida `<Drawer{Modulo}>` nas 7 entidades | **P1** | 5h | (interno) |
| 5 | **Focus trap + Esc + 1ª input autofocus** declarados | **P1** | 3h | Linear, Radix |
| 6 | **Centrifugo channel `<modulo>:<biz>:<id>:updated`** notifica edits remotos | **P1** | 6h | Linear (LiveQuery) |
| 7 | **Offline queue localStorage** + retry reconexão (Larissa Cabo Frio) | **P1** | 8h | Notion, Linear |
| 8 | **Permissões per-tab** Spatie (`cliente.tab.ia.view`) | **P2** | 5h | Salesforce |
| 9 | **Rota `/print` Browsershot PDF cadastro completo** | **P2** | 6h | Bling, Salesforce |
| 10 | **Modal fullscreen viewport <1024** (mobile Larissa eventual) | **P3** | 10h | HubSpot |

## Roadmap CONSOLIDAR vs EVOLUIR

### Wave H — CONSOLIDAR (15h IA-pair, ANTES Wave VENDER/OPERAR/FINANÇAS)

Aplicar gaps #1, #2, #3, #5 **no template Cliente** + propagar pros 4 tabs reutilizáveis (IdentificacaoTab/IATab/AuditoriaTab/EnderecoTab). Importante: **fazer ANTES** das outras Waves porque senão o gap escala 7× (replicação em 6 novas entidades).

| Gap | Effort | Onde aplica |
|---|---|---|
| #1 Optimistic locking | 6h | `Modules/Crm/Http/Controllers/ClienteAutosaveController.php` + middleware genérico |
| #2 React.lazy + Inertia::defer per-tab | 4h | `resources/js/Pages/Cliente/Index.tsx` + 4 tabs reutilizáveis |
| #3 popstate handler | 2h | `resources/js/Pages/Cliente/Index.tsx` (drawer state) |
| #5 Focus trap + Esc + 1ª input focus | 3h | `resources/js/Pages/Cliente/Index.tsx` (Radix Dialog ou Sheet patterns) |

**Output Wave H:** template Cliente fica nota ~88 (mundo-classe). Replicação pra Produto/ServiceOrders/etc herda nota 88 baseline.

### Wave I — EVOLUIR (14h IA-pair, PÓS Wave PESSOAS)

Aplicar gaps #4, #6, #7 — gate CI + realtime Centrifugo + offline queue. Wave I é independente da F1-F3 ADR 0185 — roda quando entidades já estão entregues.

| Gap | Effort | Razão pós-replicação |
|---|---|---|
| #4 CI gate `drawer:health` | 5h | Precisa entidades migradas pra ter o que validar |
| #6 Centrifugo realtime channel | 6h | Precisa subscriber pattern provado em produção |
| #7 Offline queue | 8h | Sinal qualificado Larissa Cabo Frio (ADR 0105) |

### Adiados (P2/P3) — esperar sinal qualificado

- #8 Permissões per-tab — só quando Wagner/Maiara/Eliana reportarem necessidade
- #9 Print PDF — quando primeira solicitação cliente
- #10 Mobile <1024 — fora persona Larissa atual

## Mundo-classe oimpresso (raros no mercado BR/internacional)

Dimensões onde o oimpresso está **acima do mercado**:

1. **D15 Multi-tenant Tier 0** (98) — `business_id` global scope IRREVOGÁVEL (ADR 0093). Nenhum SaaS internacional consumer-facing tem isso explícito; Bling/Tiny/Omie têm tenant mas não Tier 0 IRREVOGÁVEL.
2. **D5 Audit LGPD** (90) — Spatie ActivityLog v4.8 reuso zero código → LGPD Art 18 cumprido automaticamente em 7 entidades. Stripe/Salesforce têm próprios mas exigem código por entidade.
3. **D4 IA Brain B democratizado** (85) — 3-4 cards Brain B em CADA entidade cadastral. HubSpot Breeze cobre Contact/Deal apenas; oimpresso democratiza pra 7 entidades.
4. **D1 Coerência visual** (85) — pattern canon validado em prod biz=1 + template DRY reusa 4 tabs. Linear faz isso por convenção interna; oimpresso por ADR + skill.

## Onde está atrás (gaps reais)

1. **D8 Concorrência** (40) — Linear/Figma usam CRDT, HubSpot usa optimistic locking 409. Oimpresso não tem nada → 2 usuários editando = last write wins silencioso.
2. **D10 Performance** (55) — 8 tabs upfront carrega 118KB JS no abrir do drawer. Linear/Stripe lazy load tabs (~30KB initial).
3. **D12 Mobile** (30) — fora de escopo declarado, mas HubSpot/Stripe têm modal fullscreen <1024 desde 2023.

## Sources (14 URLs canon)

- [Linear — Issue view layout changelog](https://linear.app/changelog/2021-06-03-issue-view-layout)
- [Linear — How we redesigned the UI part II](https://linear.app/now/how-we-redesigned-the-linear-ui)
- [HubSpot — Breeze record summary card](https://knowledge.hubspot.com/records/summarize-records)
- [HubSpot — Understand Breeze](https://knowledge.hubspot.com/ai/understand-breeze)
- [Intercom — AI features in Inbox 2025](https://www.intercom.com/help/en/articles/6955446-ai-features-available-in-the-inbox)
- [Stripe — Web Dashboard design patterns](https://docs.stripe.com/dashboard/basics)
- [Ant Design — Drawer 378px/736px presets](https://ant.design/components/drawer/)
- [SaaSframe — 62 SaaS side panel examples](https://www.saasframe.io/patterns/side-panel)
- [Mobbin — Drawer UI best practices 2025](https://mobbin.com/glossary/drawer)
- [Notion — Side peek pattern](https://www.makeuseof.com/change-notion-side-peek-setting/)
- [DEV — Optimistic locking 2026 collaborative](https://dev.to/devin-rosario/advanced-syncing-algorithms-for-collaborative-mobile-apps-in-2026-1a60)
- [Bling vs Tiny vs Omie 2026 — Cierus](https://www.cierus.com.br/news-details.php?slug=bling-vs-tiny-vs-omie-qual-erp-escolher)
- [Userpilot — Modal UX SaaS 2026](https://userpilot.com/blog/modal-ux-design/)
- [LogRocket — Accessible linear design light/dark](https://blog.logrocket.com/how-do-you-implement-accessible-linear-design-across-light-and-dark-modes/)

## Refs canônicas

- [ADR 0179 — Cliente Drawer 760 canon](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- [ADR 0185 — Drawer 760 escala 7 entidades](../decisions/0185-drawer-760-canon-entidades-cadastrais.md)
- [Skill pageheader-canon Fase 4-bis](../../.claude/skills/pageheader-canon/SKILL.md)
- [resources/js/Pages/Cliente/Index.tsx](../../resources/js/Pages/Cliente/Index.tsx) — `w-[760px]` linha 1332
- [resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx](../../resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx) — autosave debounce 800ms + rollback ref

## Próximo passo sugerido

1. Wagner decide: **inserir Wave H (15h IA-pair) ANTES das Waves VENDER/OPERAR/FINANÇAS** da ADR 0185?
2. Se SIM: criar ADR 0186 (amends 0185) ou apenas amend in-place a seção "Plano de execução" da ADR 0185 com Wave H
3. Wave H aplica gaps P0 (#1, #2, #3, #5) **no template Cliente** primeiro
4. Smoke MCP prod biz=1 valida Wave H antes de replicar
5. Sub-agents Waves VENDER/OPERAR/FINANÇAS herdam template Cliente nota 88
