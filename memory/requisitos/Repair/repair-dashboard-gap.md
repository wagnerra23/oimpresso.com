---
id: requisitos-repair-repair-dashboard-gap
tela: Repair/Dashboard/Index (/repair/dashboard)
prototipo: prototipo-ui/cowork/repair-page.jsx
tela_viva: resources/js/Pages/Repair/Dashboard/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Repair/Dashboard/Index

> Fase 1 do protocolo (`prototipo-ui/PROTOCOL.md`) em modo **PARIDADE**. `repair-page.jsx` declara no cabeçalho (l.1-2) que foi *importado dos blades* `Modules/Repair/Resources/views/*` — é porte REVERSO do Blade que esta tela substitui (§5 2026-08-28); a aba comparada é `Painel` (repair-page.jsx:44-101, origem `dashboard/index.blade.php`). A pergunta por parte é "o vivo cobre o que o mockup mostra?", não "o que falta do protótipo". Estado no vivo medido em 2026-09-06 sobre `origin/main` `80bc4ef8b9`, com `grep -n` (linha real). Lidos antes: `Index.charter.md` (Non-Goals l.41-50) e `Index.casos.md` (UC-RDSH-01..04). Dado mock do protótipo (contagens, nomes, valores) não é gap.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header do módulo (busca · ⌘K · "Adicionar folha") | `PageHeader` com título e descrição, sem ação (Index.tsx:41-45); o shell é `AppShellV2` (Index.tsx:225) | Nada — paridade. O header do mockup (repair-page.jsx:693-706) é o shell do módulo, camada Shell da UI-0013 (`AppShellV2`, 1× pro app); "Adicionar folha" é CRUD de OS, Non-Goal do charter (Index.charter.md:43). |
| Alerta de entrega vencida | Inexistente: 0 ocorrências de `vencid`／`<Alert` no Index.tsx (225 ln); o Controller manda só `total_repairs` e `service_staff_count` (DashboardController.php:79-81), nenhuma contagem de atrasadas | **Decidir.** O banner "N folha(s) com entrega vencida" (repair-page.jsx:54-58) é adição do designer — não existe no Blade de origem (dashboard/index.blade.php:19-99 só tem status／equipe／tendências). Construir exige o Controller contar `delivery_date` vencida entre não-concluídas; o CTA "Ver as atrasadas" é drilldown, Non-Goal (Index.charter.md:47), então entraria só o aviso, sem clique. Construir ou rejeitar por escrito. |
| KPIs | 2 cards: "Status únicos" = `kpis.total_repairs` = contagem de status distintos, e "Service staff" = contagem de técnicos com OS (Index.tsx:47-55; DashboardController.php:79-81). UC-RDSH-02 pina a semântica do 1º com teste verde (Index.casos.md:40-52) | **Decidir.** Conjuntos disjuntos: nenhum dos 4 KPIs do mockup (Folhas pendentes com sparkline e "sem técnico", Concluídas, Entrega vencida, Ticket médio — repair-page.jsx:59-67) existe no vivo, e os 2 do vivo não existem no mockup. "Ticket médio" é média de `estimated_cost` ⚠️ toca valor (Regra Mestre: só exibir agregado do backend, com dupla prova). Trocar KPIs = mudar o charter (Goals l.30-31) e UC-RDSH-02 no mesmo PR; UC-RDSH-02 já deixa registrada para [W] a pergunta sobre o nome da chave. Construir ou rejeitar por escrito. |
| Folhas por status · por técnico | `BarChartCard` "OS por status" (Index.tsx:58-66) e "OS por service staff" (Index.tsx:68-79), barras SVG acessíveis, top 10 | Nada — paridade. As barras do mockup são clicáveis (`onIr`, repair-page.jsx:68-91); clique é drilldown, Non-Goal explícito (Index.charter.md:47). O conteúdo está coberto. |
| Tendências (marcas · equipamentos · modelos) | 3 painéis: Top marcas (Index.tsx:81-93), Top modelos (Index.tsx:95-107), Top aparelhos (Index.tsx:110-122) | Nada — paridade estrutural com os 3 `Chart` do mockup (repair-page.jsx:92-99). O 3º painel recebe `[]` literal do Controller (DashboardController.php:86) e fica vazio para sempre — isso já é decisão [W] em aberto, medida e pinada em UC-RDSH-03 (Index.casos.md:54-75); não se duplica aqui. |
