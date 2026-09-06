---
id: requisitos-kb-kb-index-v2-gap
tela: kb/Index.v2 (/kb/v2)
prototipo: prototipo-ui/cowork/kb-page.jsx
tela_viva: resources/js/Pages/kb/Index.v2.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — kb/Index.v2

## Por que esta tela tem mapa próprio, e não é a mesma que `kb/Index`

As duas telas declaram o **mesmo** `related_prototype` (`kb-page.jsx`), mas são telas distintas:
rotas diferentes (`/kb` e `/kb/v2`), controllers diferentes (`KbController@index` e `@indexV2`),
US diferentes (US-KB-002 e US-KB-001) e charters próprios, ambos `status: live`.

**E o mapa da irmã não serve aqui, por um motivo que inverte o veredito dela.** O
[`kb-gap.md`](kb-gap.md) (2026-06-30) tem como achado central que o mockup é maior mas não é a
mesma tela: ele descreve uma base editorial de procedimentos de uma gráfica, enquanto a `kb/Index`
é um navegador de documentos canônicos de governança — dois produtos diferentes com o mesmo nome.
Aquilo continua verdadeiro **para a v1**.

Para a **v2**, não: ela **é** a tela editorial que o mockup desenha. Medido no `Index.v2.tsx`:

| O que o `kb-gap.md` listou como gap editorial da v1 | Na v2 |
|---|---|
| Botão "Trilhas" | `Index.v2.tsx:375-383` (abre `PathsDialog`) |
| Botão "Perguntar ao KB" (IA/RAG) | `:382-393`, sob a permissão `can.ai_ask` |
| Botão "Saúde do KB" | `:394-403` ("Dashboard", abre `HealthPanel`) |
| Botão "Troubleshooter" | `:404-413` (abre `TroubleshooterDialog`) |
| Command palette | `:425-435` mais o componente `_components/KbCommandPalette.tsx` |
| "Novo artigo" | `:441-450` ("Novo SOP", sob `can.write`) |
| Tri-pane categorias, lista e leitor | `:487-554` (`CategorySidebar`, `NodeList`, `NodeReader`) |
| Favoritos | `_components/KbFavStar.tsx` |

Os seis botões que a v1 não tinha existem todos aqui, com os mesmos papéis. O título da v2 é
"Procedimentos Operacionais Padrão" e o subtítulo conta SOPs, leituras e ordens de serviço
vinculadas (`:365-372`) — o mesmo trio de métricas do mockup (`kb-page.jsx:713-720`).

> **Escopo desta tabela:** as partes abaixo são as da v2. Onde a região é a mesma que a irmã já
> ancora, digo qual parte do [`kb.map.json`](kb.map.json) a cobre, em vez de duplicar.
>
> **Frescor da fonte:** `kb-page.jsx` **foi verificado** contra o Cowork vivo em 2026-09-06 — é um
> dos 7 arquivos que a última rodada mediu. É a única tela deste lote com âncora comprovadamente
> fresca.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho e ações | `Index.v2.tsx:363-445` monta o `PageHeader` canônico com título, o subtítulo de três métricas e os seis botões do mockup (`kb-page.jsx:712-735`), três deles sob permissão (`can.ai_ask`, `can.graph_view`, `can.write`). O mockup não tem controle de permissão — mostra os seis a todos. | Nada — vivo à frente. O gate por permissão é exigência multi-tenant do repo, ausente no protótipo. Cobre a região que o `kb.map.json` ancora como `header-pagina` para a tela irmã. |
| Faixa de indicadores | Não existe como faixa na tela: os quatro cartões do mockup (`kb-page.jsx:737-758` — mais lido, fixados, recém-atualizados, a revisar) foram movidos para dentro do modal "Dashboard" (`_components/HealthPanel.tsx`, 223 ln, aberto em `Index.v2.tsx:591-599`). | Nada — decisão registrada. O `Index.v2.tsx:449-452` grava a razão e a data: Wagner, 2026-05-17, os cartões ocupavam cerca de 150px de topo sem retorno suficiente, e os quatro quadrantes do painel de saúde já os cobrem. Reabrir seria desfazer decisão tomada. |
| Busca | `Index.v2.tsx:454-480` tem uma busca sempre visível com atraso de 350ms e atalho de teclado, **separada** do command palette; o palette é o componente `_components/KbCommandPalette.tsx` (162 ln), montado em `:558-574`. O mockup tem só o palette (`kb-page.jsx:1036-1044`). | Nada — vivo à frente. As duas afordâncias coexistem no vivo; o mockup tem uma. Cobre a região que o `kb.map.json` ancora como `busca` para a tela irmã. |
| Navegação por categorias | `_components/CategorySidebar.tsx` (333 ln), montado em `Index.v2.tsx:487-509`, ocupa a primeira coluna do tri-pane e tem **as cinco seções** da barra lateral do mockup (`kb-page.jsx:777-903`): categorias com subcategorias (`CategorySidebar.tsx:27`, `:47`), favoritos (`:16`, `:33`), recentes (`:34`), nuvem de etiquetas (`:38`, `:40`) e a lista de atalhos (`:19`). As subcategorias que no mockup vinham de biblioteca externa (`window.KB_SUBCATS`, `kb-page.jsx:784`) viraram tabela no repo: `Modules/KB/Database/Migrations/2026_05_15_100002_create_kb_subcategories_table.php`, com seeder, e `Index.v2.tsx:489` passa a prop. | Nada — paridade. |
| Lista de SOPs | `_components/NodeList.tsx` (262 ln), montado em `Index.v2.tsx:510-527`, é a coluna do meio e tem o cabeçalho do mockup (`kb-page.jsx:906-984`) inteiro: o contador de itens (`NodeList.tsx:80`) e a ordenação segmentada com as quatro opções do protótipo — recentes, mais lidos, mais úteis, a revisar (`:36-41`, com `aria-label="Ordenar por"` em `:85`). A métrica de leitura existe **por nó**, não só no agregado: `:229` renderiza a contagem de cada SOP. | Nada — paridade. |
| Leitor do SOP | `_components/NodeReader.tsx` (622 ln, com charter próprio ao lado), montado em `Index.v2.tsx:528-554`, é a terceira coluna, como a seção do mockup (`kb-page.jsx:986-1033`), incluindo o estado vazio com atalhos rápidos. O leitor do vivo carrega o corpo sob demanda a partir de `mcp_memory_documents.content_md` (`Index.v2.tsx:16`). | Nada — vivo à frente. O mockup lê blocos de um array em memória; o vivo busca o conteúdo real do documento. Cobre a região que o `kb.map.json` ancora como `editor-detalhe` para a tela irmã. |
| Diálogos auxiliares | `Index.v2.tsx:575-599` monta os três diálogos do mockup: trilhas (`_components/PathsDialog.tsx`, 241 ln), solucionador de problemas (`_components/TroubleshooterDialog.tsx`, 371 ln) e painel de saúde (`_components/HealthPanel.tsx`, 223 ln) — os dois primeiros com charter próprio ao lado. No mockup são componentes externos carregados do escopo global (`kb-page.jsx:1045`, `:1079`), com alternativa quando ausentes. | Nada — paridade. Cobre a região que o `kb.map.json` ancora como `drawer-modais-auxiliares` para a tela irmã. |
| Composer de SOP | Não existe: o botão "Novo SOP" (`Index.v2.tsx:441-450`) abre um estado que hoje só emite um aviso de "em breve" (`:355-357`, marcado como pendência da Onda 3). O mockup tem o composer completo (`kb-page.jsx:1068-1078`). | **Decidir.** Região do mockup: `kb-page.jsx:1068-1078`; ponto no vivo: `Index.v2.tsx:355-357`. O próprio código nomeia a onda que o entregaria; o que falta é a decisão de puxá-la. Escrever SOP cria conteúdo por negócio, logo exige escopo por `business_id` (ADR 0093) desde o primeiro traço. Construir ou rejeitar por escrito. |
| Visualização em grafo | Não existe nesta tela: o botão "Grafo" (`Index.v2.tsx:412-431`) emite aviso de Onda 5 em vez de navegar. Os componentes existem no repo (`_components/GraphCanvas.tsx`, `GraphFilters.tsx`, `GraphLegend.tsx` e `GraphNodeDetail.tsx`, 729 linhas somadas) e a rota do grafo é tela própria, com veredito `n/a` na porta viva. **O protótipo não desenha grafo nenhum** — medido: as 4 ocorrências de "graph" em `kb-page.jsx` são todas a marca de plotter "Graphtec" (`:25`, `:154`, `:155`, `:159`). | Nada — vivo à frente. O botão é adição do vivo, sem contraparte no mockup; ligá-lo é roteamento para outra tela, cujos componentes já estão escritos. A onda está nomeada no próprio código. |
