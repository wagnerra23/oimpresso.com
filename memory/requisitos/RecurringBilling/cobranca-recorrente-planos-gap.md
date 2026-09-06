---
id: requisitos-recurring-billing-cobranca-recorrente-planos-gap
tela: RecurringBilling/Planos/Index (/recurring-billing/planos)
prototipo: prototipo-ui/cowork/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Planos/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — RecurringBilling/Planos/Index

> No protótipo esta aba é um placeholder honesto (cobranca-recorrente-page.jsx:332-342, :370) que se declara atrás do vivo ("Espelha /recurring-billing/planos do git"). Tela-mãe: `cobranca-recorrente-gap.md` (veredito MOCKUP-STALE). Charter: `resources/js/Pages/RecurringBilling/Planos/Index.charter.md` (Non-Goals respeitados, nunca reabertos).
>
> Base medida: `origin/main` 80bc4ef8b9 · âncora resolvida por `node prototipo-ui/ancora.mjs RecurringBilling/Planos/Index --staging prototipo-ui/cowork` → `âncora ✓ [-page.jsx (bundle · bundle_source)] cobranca-recorrente-page.jsx`. O `desc` do placeholder (:370) cita: CRUD (nome, ciclo, valor, tipo fiscal, dias de trial) · distribuição por ciclo · drawer lateral pra criar/editar. Cada capacidade foi conferida no `.tsx` abaixo.

**Veredito:** VIVO-À-FRENTE — 0 itens a decidir; as 3 capacidades citadas no placeholder existem no vivo (CRUD em páginas dedicadas por decisão do charter, distribuição por ciclo em `CicloDistribuicao`), e o vivo tem 8 partes além do que o placeholder descreve.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / sub-nav | `Planos/Index.tsx:361-402`: h1 "Planos · cobrança recorrente" (:364-366) + subtítulo mono com contagens vindas de `PlanController::buildKpisPayload` (`PlanController.php:303`) (:367-375) + link Voltar pra `/recurring-billing` (:378-383) + botão `?` (:384-391) + CTA "Novo plano" com atalho N (:392-399, handler :300-302). Sem tab-strip nesta Page (grep `SubNav／PageHeaderNav` → 0); a navegação entre abas vive na Index-mãe (`RecurringBilling/Index.tsx:533-539`, `router.visit` por aba). | Nada — mock/harness do protótipo (`TABS` :344-348 + `window.PageHeaderNav` :367 + `window.__selectRoute` :338 são do harness Cowork; as abas já são a linha "Abas Planos/Faturas/Config" da tela-mãe, veredito `— · —`) |
| KPIs | 4 cards em `<Deferred data="kpis">` (:407-445): Ticket médio hero com `Sparkline` (:411-422), Total planos (:423-428), Total ativos (:429-434), Plano top vendido (:435-444); agregados no Controller (`PlanController.php:303-330`). A série do sparkline é sintética client-side (:417-421, comentário "sparkline mock 12 pontos"), não dado histórico. | Nada — vivo à frente (o placeholder :370 não desenha nem descreve KPI; a série sintética é observação do vivo, sem âncora no protótipo) |
| Distribuição por ciclo | `CicloDistribuicao` (:172-203) renderizado abaixo dos KPIs (:447-449): 5 ciclos com barra proporcional e contagem, dado de `buildKpisPayload` (`PlanController.php:330`). | Nada — paridade (desc :370 "distribuição por ciclo" existe no vivo) |
| Busca / filtros | Busca server-side por nome ou slug (:457-481) via `router.reload only:['plans','kpis']` (:331-337), atalho `/` foca o input (:282-284). Sem filtro por ciclo/fiscal/ativo (grep `<Select／filters.(ciclo／fiscal／ativo)` → 0). | Nada — Non-Goal do charter ("❌ Filtros por ciclo/fiscal_type/ativo na sidebar — busca cobre v1", Index.charter.md:60); a busca em si é vivo à frente (placeholder não tem busca) |
| Tabela | `<table>` (:488-560) com 7 colunas: Plano (nome + slug mono + descrição curta :511-517), Ciclo com dias quando custom (:518-523), Valor (:524-526), Assinaturas ativas (:527-529), Fiscal badge NFe/NFS-e/Não emite (`FiscalBadge` :219-226), Status ativo/inativo (`StatusBadge` :205-217), Ações (:536-556). Linha ativa por clique e navegação j/k (:287-299, :502-509). `trial_days` chega no tipo (:50) mas não é coluna. | Nada — vivo à frente (desc :370 lista nome/ciclo/valor/tipo fiscal — todos são colunas; "dias de trial" vive no form, ver linha Criar/editar) |
| Ações por linha | Editar → link `/recurring-billing/planos/{id}/editar` (:538-545); Excluir → `handleDelete` (:339-353) com `confirm()` nativo (:344) que avisa a contagem de assinaturas ativas e o 422 esperado, `router.delete` (:346) + reload de `flash` (:350). Sem ConfirmDialog/AlertDialog (grep → 0); sem toggle ativo inline (grep `onToggle／toggleAtivo／patch(` → 0). | Nada — Non-Goal do charter ("❌ Modal de confirmação custom — usa `confirm()` nativo no v1", Index.charter.md:62 · "❌ Toggle ativo inline", :59); o CRUD que o desc :370 cita é paridade |
| Criar / editar (form) | Páginas dedicadas `Planos/Create.tsx` e `Planos/Edit.tsx` (rotas `Modules/RecurringBilling/Routes/web.php:94-109`), com `trial_days` no form (`Create.tsx:19,52,254-262` · `Edit.tsx:26`). No Index não há drawer/Sheet/Dialog de criação (grep `<Drawer／<Sheet／<Dialog` → 0). Mockup: o placeholder :370 escreve "drawer lateral pra criar/editar" sem desenhá-lo. | Nada — decisão já registrada (Index.charter.md:79 "❌ Modal/Dialog pra Create/Edit — canon = páginas dedicadas (Create.tsx / Edit.tsx)"); drawer×página é FORMA que o protótipo não desenhou (é texto de placeholder, não âncora), então só reabre via gate F1.5 + [W], mesma família da "única ideia visual" da tela-mãe |
| Paginação | Rodapé (:566-575) mostra total de planos e "Página X de Y"; não há controles Anterior/Próxima nem `data: { page }` (grep → 0) — `per_page` vem de `filters` (:77, :334). | Nada — vivo à frente (o protótipo não tem paginação; a ausência de prev/next é observação do vivo sem âncora no protótipo nem no charter — não é gap de protótipo) |
| Estados vazio / skeleton / flash | `EmptyState` com CTA "Criar primeiro plano" (:612-631); `KpiSkeleton` (:633-641) e `ListSkeleton` (:643-658) como fallback dos `<Deferred>` (:407, :483); `FlashBanner` success/error (:228-243, renderizado :404). | Nada — vivo à frente (placeholder :332-342 não tem estado nenhum) |
| Overlays / atalhos | `TourOnboarding` (:580-582), `CheatSheet` (:583), `CmdPalette` ⌘K com planos como itens (:584-603); atalhos J/K/N/`/`/`?`/⌘K/Esc (:277-313). | Nada — vivo à frente (o placeholder :332-342 só tem o botão "← Voltar pras assinaturas" via `window.__selectRoute`, harness) |
| Linguagem visual | `stone-` 47 ocorrências, `zinc-`/`violet-` 0; acento via token `bg-primary` (:394, :507, :615); badges semânticos `bg-success-soft`/`bg-destructive-soft` (:207, :236). Protótipo declara stone + `var(--accent)` (:1-6; `cobranca-recorrente-page.css` → `var(--accent)` 10 ocorrências, zinc/violet 0). | Nada — paridade de família (stone/warm nos dois lados); equivalência exata de token acento×`bg-primary` não foi medida aqui — é escopo do `design-diff`, não deste gap |

## Recibos de ausência
- `grep -nEc 'zinc-|violet-' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (linguagem visual: sem zinc/violet)
- `grep -nEc 'stone-' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 47   (controle positivo da família stone)
- `grep -nEc '<Drawer|<Sheet|<Dialog' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (sem drawer/dialog de criar/editar no Index)
- `grep -nEc '<Select|<select|filters\.(ciclo|fiscal|ativo)' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (sem filtros por ciclo/fiscal/ativo)
- `grep -nEc 'Anterior|Próxima|data: \{ page' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (sem controles de paginação)
- `grep -nEic 'clonar|reorder|drag|csv|hist[oó]rico de' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (Non-Goals :56-58, :61 de fato ausentes)
- `grep -nEc 'ConfirmDialog|AlertDialog' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0 · `grep -n 'confirm(' …/Planos/Index.tsx` → :344   (confirm nativo, sem componente custom)
- `grep -nEic 'onToggle|toggleAtivo|patch\(' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (sem toggle ativo inline)
- `grep -nEc 'SubNav|PageHeaderNav|subnav' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 0   (sem tab-strip nesta Page)
- `grep -nc 'trial' resources/js/Pages/RecurringBilling/Planos/Index.tsx` → 1 (só o tipo :50) · `grep -nc 'trial_days' …/Planos/Create.tsx` → 5 · `…/Planos/Edit.tsx` → 5   (trial vive no form, não na tabela)
- `grep -nEc 'zinc|violet' prototipo-ui/cowork/cobranca-recorrente-page.css` → 0 · `grep -nc 'var(--accent)' …page.css` → 10   (linguagem do protótipo)
