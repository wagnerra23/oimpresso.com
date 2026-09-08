---
id: requisitos-governance-module-grades-gap
tela: governance/ModuleGrades/Index (/governance/module-grades)
prototipo: prototipo-ui/cowork/governance-page.jsx + governance-telas.jsx
tela_viva: resources/js/Pages/governance/ModuleGrades/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — governance/ModuleGrades/Index

> Protótipo = porte REVERSO do vivo (governance-page.jsx:1-3 "Espelha as telas vivas"; governance-telas.jsx:3 "Espelha … ModuleGradeController (rubrica module-grade-v3, ADR 0155)"; retrato de ~2026-08-23). Fase 1 = PARIDADE. Charter: `resources/js/Pages/governance/ModuleGrades/Index.charter.md` (Non-Goals respeitados, nunca reabertos).

**Veredito:** VIVO-À-FRENTE com 1 item a decidir — o vivo tem a aba "Catálogo & Sinais", drill-down por linha, links no banner do gate e o rodapé da rubrica, nada disso no retrato; o retrato acrescenta só um botão "Limpar" no vazio de filtro.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `Index.tsx:150-159` — `<Head>` + `<PageHeader title="Module Grades" subtitle=… breadcrumbs=[Governança, Module Grades]>`; layout `AppShellV2` em `:513`. Mockup: `governance-page.jsx:403-418` (h1 `TITULOS.notas` "Governança — notas dos módulos" + subtítulo + selo) | Nada — paridade (títulos adaptados; breadcrumb é detalhe do shell vivo) |
| Abas do shell (sub-navegação) | `Index.tsx:151` `<GovernancaSubNav active="module-grades" />`. Mockup: `governance-page.jsx:24-30` + `:420-424` | Nada — paridade |
| Toggle de visão "Notas ⇄ Catálogo & Sinais" | `Index.tsx:161-165` (`ViewTab` ×2, `:384-397`) + `CatalogSignalsView` inteira em `:401-511` (stats de proveniência, tabela Serviço · Nota · Telas · Grafo · Depende de · BRIEFING · Maturidade). Mockup: só a vista de notas — `Catálogo／catalogo／Catalog` → 0 hits em `governance-telas.jsx:224-320` | Nada — vivo à frente (a aba Catálogo/IDP de 2026-07-21 não entrou no retrato) |
| KPIs agregados | `Index.tsx:344-360` `KpiBar` — Média projeto (tone por faixa) · Total módulos · 5 cards por bucket (7 cards), dentro de `<Deferred data="kpis">` (`:174-176`). Mockup: `governance-telas.jsx:252-255` — Média /100 · "Módulos avaliados na rubrica v3" (N ainda sem D6-D9) · "Abaixo da linha de base" (<50). No vivo não há contagem "avaliados na v3" (`avaliad／sem D6／abaixo` → 0 hits); "<50" equivale a Crítico+Embrião (`FAIXAS` `governance-data.jsx:186-192`: Médio começa em 50) | Nada — decisão já registrada (charter §Goals 4: "KPI agregado: média projeto + distribuição buckets" — o conjunto de KPIs é o do charter; a contagem "avaliados na v3" é derivável dos `—` da tabela e não foi pedida) |
| Filtros (chips por bucket + busca) | `Index.tsx:178-202` — chip "Todos" + 5 chips de bucket com contagem (`FilterChip` `:313-342`, cor via `bucketBadgeClass`), `<input type="search">` por nome; estado em `useState` (`:136-137`, sem `localStorage`). Mockup: `governance-telas.jsx:259-268` (5 chips toggle com contagem, busca, hint "o filtro não sobrevive ao recarregar") | Nada — paridade (mesmos filtros; o hint do mockup é a regra do charter §Anti-hooks "NÃO armazenar localStorage filter") |
| Skeleton / carga diferida | `Index.tsx:174` e `:207` `<Deferred>` reais com `KpiSkeletonBar` (`:362-370`) e `TableSkeleton` (`:372-380`). Mockup: `governance-telas.jsx:230` (`setTimeout 950ms`) + `:274` (`Esqueleto count=8`) | Nada — paridade (no vivo é `Inertia::defer` real — charter §Goals 6; no retrato é harness) |
| Tabela rank (Módulo · Nota · Bucket · D1-D9) | `Index.tsx:204-272` — 13 colunas: Módulo como `Link` de drill-down (`:239-241`), Nota colorida (`scoreColorClass` `:126-132`), `Badge` de bucket, D1-D5, D6-D9 com cor canônica e `—` quando ausente (`:253-256`), coluna Ações "Ver →" (`:257-263`); hover `sky-50` e `cursor-pointer` (`:237`); `overflow-x-auto` + `min-w-[1100px]` (`:208-209`). Ordenação vem do backend (charter §Goals 1). Mockup: `governance-telas.jsx:279-306` — 12 colunas (sem Ações), `title` com nome da dimensão no `th` (`:285`), `—` com tooltip "não avaliado na v3" (`:298`); linhas sem link — `href／Link／onClick` → 0 hits em `:279-306` | Nada — vivo à frente (drill-down por linha + coluna Ações + rolagem horizontal só existem no vivo; o `title` por dimensão no mockup é rótulo) |
| Estado vazio do filtro | `Index.tsx:229-234` — linha única `colSpan=13` "Nenhum módulo combina com o filtro."; sem ação de reset (`Limpar／limpar／reset` → 0 hits). Mockup: `governance-telas.jsx:275-278` (`A.Vazio variant="no-results"` + botão "Limpar" que zera faixa e busca) | **Decidir.** Botão "Limpar" (zera bucket + busca num clique — mockup `governance-telas.jsx:277`) ausente no vazio `Index.tsx:229-234` e na barra de filtros `:178-202`; charter §UX targets pede só "empty state quando filtro não bate". Não é Non-Goal. Construir ou rejeitar por escrito. |
| Nota explicativa do `—` em D6-D9 | Vivo: o `—` é renderizado (`Index.tsx:253-256`, comentário "compat") e os `th` D6-D9 têm `title` com "ADR 0155 v3" (`:221-224`); não há parágrafo explicando "não é nota zero" (`não avaliad／travess` → 0 hits). Mockup: `governance-telas.jsx:307` (`<p className="gov-teto">` "Travessão em D6 a D9 quer dizer não avaliado na v3 — não é nota zero…") | Nada — decisão já registrada (charter §Goals 7: "Render `—` quando módulo ainda não tem dimensão v3 avaliada"); o parágrafo do mockup é copy sobre a mesma regra |
| Banner "Gate CI anti-regressão" (rodapé) | `Index.tsx:274-298` — card sky com `Shield`, texto do gate + label `module-grades-allowed-regression`, **4 links** (workflow · baseline JSON · RUNBOOK · ADR 0155). Mockup: `governance-telas.jsx:310-315` (`gov-gate` só texto; `href` → 0 hits no range 224-320) | Nada — vivo à frente (os 4 links externos são o charter §Goals 8; o retrato só tem o texto) |
| Rodapé da rubrica (ADR 0153/0154/0155 + pesos) | `Index.tsx:300-306` — links pros 3 ADRs via `/copiloto/admin/memoria?slug=…` + pesos canônicos (118 raw → /100). Mockup: ausente — `ADR 0153／pesos／0154` → 0 hits em `governance-telas.jsx:224-320` | Nada — vivo à frente |
| Ações proibidas (editar pesos · disparar Brain B · histórico 90d · edição inline de nota) | Vivo: nenhuma (tela read-only — `Index.tsx:134-311` não tem form/post). Mockup: também não desenha | Nada — Non-Goal do charter (❌ Editar pesos da rubrica · ❌ Disparar Brain B · ❌ Histórico 90d) |

## Recibos de ausência
- `grep -nEi 'Limpar|limpar|reset' resources/js/Pages/governance/ModuleGrades/Index.tsx` → 0
- `grep -nEi 'avaliad|sem D6|abaixo' resources/js/Pages/governance/ModuleGrades/Index.tsx` → 0
- `grep -nEi 'não avaliad|travess' resources/js/Pages/governance/ModuleGrades/Index.tsx` → 0
- `sed -n 224,320p prototipo-ui/cowork/governance-telas.jsx ／ grep -cEi 'Catálogo|catalogo|Catalog'` → 0 (ausência no mockup, sustenta "vivo à frente" da aba Catálogo)
- `sed -n 224,320p prototipo-ui/cowork/governance-telas.jsx ／ grep -cEi 'ADR 0153|pesos|0154'` → 0 (ausência no mockup — rodapé da rubrica)
- `sed -n 279,306p prototipo-ui/cowork/governance-telas.jsx ／ grep -cEi 'href|Link|onClick'` → 0 (ausência no mockup — drill-down por linha)
