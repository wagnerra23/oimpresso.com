# Sessão 2026-05-21 — Sidebar v3: comparativo Linear/Stripe/Shopify/Notion/Vercel

> Dossiê de pesquisa estado-da-arte que sustenta [ADR 0180](../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md). Inclui scores, dimensões, gaps, decisões de nomes.

## TL;DR

- Sidebar oimpresso v2 (hoje, 11 grupos, ~50 labels): **58/100**
- Proposta v3 inicial (5 grupos + ghosts header): **81/100** (+23pts)
- v3 final (+ 5 ajustes: Cmd+K · mobile · pinned · kbd · ARIA): **91/100** — Linear-tier (93)
- Decisão: aprovado por Wagner 2026-05-21 → [ADR 0180](../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) aceita

## Benchmark consolidado

| Sistema | Score | Pontos fortes |
|---|---|---|
| Linear | 93/100 | Cmd+K global · sidebar 240px slim · headers contextuais · `G X X` shortcuts · ARIA tablist · mobile scroll-x |
| Stripe Dashboard | 89/100 | Sidebar 240-280px · secondary nav consistente · primary action proeminente |
| Vercel | 88/100 | Escala 5→50 features sem restructure · A11y forte |
| Shopify Admin | 85/100 | Pinned/favoritos · role contextuality · 5 seções |
| Notion | 82/100 | Favoritos no topo · collapsible groups · teamspaces (multi-context) |
| **oimpresso v2** | **58/100** | Multi-tenant Tier 0 sólido · hue OKLCH por grupo (UI-0008) |

## 10 dimensões pesadas — nota proposta v3 (com ajustes)

| Dimensão | Peso | Nota | Comentário |
|---|---|---|---|
| Hick's Law | 15% | 9/10 | 14 labels visíveis vs 50 antes. Linear tem ~14. |
| Progressive disclosure | 12% | 9/10 | Hierarquia in-screen (canônico Linear/Stripe) |
| Spatial orientation | 10% | 9/10 | Sidebar slim persistente = mapa estável |
| Discoverability sub-features | 12% | 9/10 | Cmd+K + ghosts + pinned cobre todos cenários |
| Scalability 50+ features | 10% | 9/10 | 5 grupos absorvem módulos novos |
| Primary action clarity | 8% | 9/10 | "+ Novo" colorido + ghosts é padrão Linear/Stripe |
| Secondary action ergonomics | 8% | 9/10 | Ghosts ARIA tablist + overflow "Mais" |
| Mobile/responsive (1366px+) | 8% | 9/10 | Scroll-x snap em <1280px |
| Multi-tenant contextualidade | 7% | 10/10 | DataController declara per-business |
| Acessibilidade (WCAG + kbd) | 10% | 9/10 | ARIA tablist + `G X X` + foco visível |
| **Total ponderado** | | **91/100** | |

## Decisão de nomes (refinamento iterativo)

| Iteração 1 (rejeitada) | Iteração 2 (rejeitada) | Iteração 3 (final) | Por quê final |
|---|---|---|---|
| OPERAÇÃO + PRODUTO/SERVIÇO + COMERCIAL + PRODUÇÃO + ESTOQUE + RH + RELATÓRIOS + GOVERNANÇA + PLATAFORMA + FINANCEIRO + FISCAL (11 grupos) | VENDER + ENTREGAR + DINHEIRO + GENTE + AJUSTES (5 grupos) | **VENDER + OPERAR + FINANÇAS + PESSOAS + SISTEMA** (5 grupos) | "Entregar" sugeria delivery; "Operar" abraça OS+Produção+Estoque. "Dinheiro" coloquial demais; "Finanças" universal. "Gente" calor mas "Pessoas" profissional. "Ajustes" mistura demais; "Sistema" claro. |

## Decisões críticas que viram regras canônicas

1. **Relatórios NÃO é grupo** — vira ghost contextual dentro de cada domínio + IA/Brief consolida cross-domínio
2. **Fiscal entra em FINANÇAS** — para PME-BR, NF-e é parte do fluxo de dinheiro, não ilha
3. **Hierarquia in-screen, não in-sidebar** — sidebar é mapa de destinos, não de ações
4. **Default tab = "Unificado"** — segue pattern [ADR 0178](../decisions/0178-sells-unified-tabs-visao-supersede-0136.md)
5. **DataController declara `ghosts[]`** — frontend NUNCA hardcode (Wagner regra 2026-05-19)
6. **Cmd+K cobre power-user** — Larissa por sidebar, Wagner por palette
7. **Backward-compat via LEGACY_GROUP_MAP** — migração modulo-a-modulo, não big-bang

## Gaps que não viraram bloqueador (mas viram trigger de revisão)

- Tab "Contábil" pode precisar entrar em FINANÇAS pra SPED/DRE separado — ghost cobre por ora
- Multi-split (Financeiro + Vendas lado-a-lado) — power-user pediria; tabs/ghosts não escalam — review_trigger declarado
- Telemetria de adoção Cmd+K — se >40% entrada via palette, considerar sidebar rail-only default

## Próximos passos pós-aprovação

1. ✅ ADR 0180 escrita
2. ✅ Protótipo Cowork `prototipo-ui/prototipos/sidebar-v3-unificado/visual-source.html`
3. Wagner revisa visual (smoke browser)
4. PR Fase 1 — `MenuItemContract` schema novo
5. PR Fase 2 — `SIDEBAR_GROUPS` 11→5 keys + LEGACY_GROUP_MAP
6. PRs Fase 4 — 17 DataControllers em ordem de uso (paralelizável em 4 subagents)
7. PRs Fase 5 — ~30 telas adotam PageHeader

## Sources WebSearch

- [Linear UI redesign — sidebar, headers, panels](https://linear.app/now/how-we-redesigned-the-linear-ui)
- [Stripe Dashboard navigation](https://docs.stripe.com/dashboard/basics)
- [Dashboard Design Patterns 2026 — Linear/Notion/Vercel/Stripe sidebar 240-280px](https://artofstyleframe.com/blog/dashboard-design-patterns-web-apps/)
- [Sidebar Design for Web Apps — UX Best Practices 2026](https://www.alfdesigngroup.com/post/improve-your-sidebar-design-for-web-apps)
- [Hick's Law and UX Decision Making](https://fastercapital.com/content/User-experience--UX---Hick-s-Law--Hick-s-Law-and-Its-Impact-on-UX-Decision-Making.html)
- [Notion sidebar — frequency-of-access + favorites + teamspaces](https://www.notion.com/help/guides/structure-sidebar-focused-work-teamspaces)
- [Shopify Admin sidebar — pinned apps + drop-down grouping](https://help.shopify.com/en/manual/online-store/menus-and-links/drop-down-menus)
- [Ghost buttons em UX (secondary/tertiary sem competir com primary)](https://blog.logrocket.com/ux-design/using-ghost-buttons-effective-ctas/)
- [Enterprise UX best practices — progressive disclosure](https://uxpilot.ai/blogs/enterprise-ux-design)
