---
id: requisitos-admin-index-visual-comparison
---

# Visual Comparison — `/admin` Index (Sprint 1 dia 3-4)

> ⚰️ **REGISTRO HISTÓRICO — a tela que este documento descreve não existe mais.**
> O `Modules/Admin` (Admin Center) foi **removido em 2026-07-30** pelo commit `2dfc5a183b`
> ([PR #5062](https://github.com/wagnerra23/oimpresso.com/pull/5062)), executando a
> [ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md) — decidida por [W] em
> 2026-07-29, que **supersede a [ADR 0122](../../decisions/0122-admin-center-ct100.md)** citada abaixo.
> Medido em 2026-09-07: `git ls-files "Modules/Admin/*"` = **0 arquivos**, e não há Page Inertia em
> `resources/js/Pages/admin/` (nunca houve — o painel era Blade no CT 100).
>
> **O arquivo fica.** Ele preserva o que era verdade em 2026-05-10: o baseline de 15 dimensões que
> orientou o Sprint 1 do painel. Não é ponteiro vivo — **nada abaixo desta linha descreve tela
> existente**, e nenhum charter o aponta, então ele segue órfão no `design-coverage` (correto: órfão
> mede ausência de vínculo, não defeito). O que sobreviveu do módulo foi resgatado **antes** da
> remoção, em 2026-07-29: o `CuradorStatsReader` foi para o `Modules/Arquivos` (`ba36bd28c5`,
> [PR #5046](https://github.com/wagnerra23/oimpresso.com/pull/5046)) e o gerador de UI-CATALOG para
> o `Modules/Governance` (`5874d20e8f`, [PR #5045](https://github.com/wagnerra23/oimpresso.com/pull/5045)).

**Status:** baseline-only · **Data:** 2026-05-10 · **ADR:** [0122](../../decisions/0122-admin-center-ct100.md)

> ⚠️ **Não há prototipo Cowork prévio aprovado** pra essa página. Admin Center é greenfield Wagner-only — não passou pelo loop normal Cowork ↔ Claude Code formalizado em [`prototipo-ui/PROTOCOL.md`](../../../prototipo-ui/PROTOCOL.md). Visual-comparison aqui é **baseline pra futuro**, não comparativo retroativo.

---

## 15 dimensões — preenchimento baseline

| # | Dimensão | Decisão Sprint 1 | Justificativa |
|---|---|---|---|
| 1 | **Layout / grid** | AppShellV2 + container `mx-auto p-4` + grid 1/2-col responsivo | Canon Cockpit V2 (ADR 0110) |
| 2 | **Tipografia** | Default Tailwind (sem fontes custom) | Read-mostly, sem decoração tipográfica |
| 3 | **Cores** | Tailwind palette (red-600/amber-100/green-100/gray-500) | Acessível, consistente com app principal |
| 4 | **Espaçamento** | gap-4 entre cards, p-4 container, space-y-4 vertical | Confortável em laptop 1280px |
| 5 | **Hierarquia visual** | Top-bar Tier 0 (red) > PageHeader > 4 cards > footer | Wagner vê emergência primeiro |
| 6 | **Estados** | available / unavailable empty states + loading não aplicável (server-render) | Inertia v3 SSR |
| 7 | **Iconografia** | Icon helper canon (shield-check, newspaper, activity, kanban, alert-triangle) | Lucide consistent |
| 8 | **Microcopy** | PT-BR ("Centro de Operações", "ADR(s) Tier 0 violada(s)") | CLAUDE.md exige PT-BR |
| 9 | **Acessibilidade** | Cor + emoji + texto pra status (não só cor) | WCAG 2.1 AA proxy |
| 10 | **Interatividade** | Read-mostly · 1 link clickable (badge ADR no GitHub) | Sprint 1 = leitura |
| 11 | **Mobile** | 1-col em < lg breakpoint | Wagner Tailscale celular |
| 12 | **Performance** | Cache 5min Brief + snapshot file Health = <2s render | Meta Sprint 1 dia 5 |
| 13 | **Segurança visual** | Banner BYPASS_LOCAL chamativo amber-100 | Wagner ciente do modo dev |
| 14 | **Empty state** | Cada widget tem `data.available=false` path com instruções | Graceful sem snapshot/tabelas |
| 15 | **Dark mode** | Não implementado MVP | Sprint 2+ se Wagner pedir |

---

## Decisões pendentes Wagner (post-Sprint 1 smoke)

- [ ] Validar 4 widgets em laptop Wagner via Tailscale (após DNS+container US-PRE)
- [ ] Decidir se layout 2-col cabe em 1280px ou se prefere 4-col fixed
- [ ] Decidir se Brief markdown render como `<pre>` (atual) ou via lib `react-markdown` (Sprint 2)
- [ ] Decidir se ícone PageHeader é `shield-check` ou outro

---

## Comparativo retroativo (não aplica)

Nenhum prototipo Cowork pré-aprovado. Sprint 1 dia 3-4 entrega baseline mínimo funcional. Próximas iterações (Sprint 2 widgets W5-W10) **devem passar pelo loop Cowork → visual-comparison ANTES** de codar (skill `mwart-comparative` Tier A).

## Validação visual local (Wagner)

```bash
# Em D:\oimpresso.com:
echo 'ADMIN_BYPASS_LOCAL=true' >> .env
echo 'APP_ENV=local' >> .env
php artisan config:clear
npm run build:inertia
# Abre http://oimpresso.test/admin (Herd) — 4 widgets renderizam
```
