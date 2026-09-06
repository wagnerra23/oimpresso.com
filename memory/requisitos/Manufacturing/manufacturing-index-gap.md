---
id: requisitos-manufacturing-manufacturing-index-gap
tela: Manufacturing/Index (/manufacturing/production)
prototipo: prototipo-ui/cowork/manufacturing-page.jsx + manufacturing-producao.jsx
tela_viva: resources/js/Pages/Manufacturing/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Manufacturing/Index

> **Âncora resolvida por `bundle_source`** (`ancora.mjs Manufacturing/Index` → `âncora ✓ [-page.jsx (bundle · bundle_source)] manufacturing-page.jsx`); o charter declara `related_prototype: n/a (herda PT-01 Lista)` — coexistência prevista.
>
> **O protótipo se declara ESPELHO do módulo vivo** (`manufacturing-page.jsx:2`: *"Espelho de Modules/Manufacturing"*) → expectativa-base é paridade.
>
> **Frescor do espelho (medido 2026-09-06):** `manufacturing-page.jsx` = **STALE**. O Cowork vivo trocou a navegação inline por componente compartilhado — espelho tem `<nav className="mfg-tabs" aria-label="Manufacturing">` (`manufacturing-page.jsx:159`), vivo tem `window.CliTabs`. Mesma rodada de adoção de DS que atingiu `oficina-page.jsx`, `oficina-os-page.jsx` e `officeimpresso-page.jsx` (esses dois últimos medidos **por hash**, rodada 38 do `.cowork-freshness-ledger.json`). É troca de implementação do mesmo controle, **não de capacidade** — as partes abaixo seguem válidas. Linhas citadas são do **espelho versionado**.
>
> **Escopo 1:N.** `manufacturing-page.jsx` é o módulo em 5 abas; esta tela é a aba **Ordens de produção**, cujo corpo vive em `MfgProducaoView` (`manufacturing-producao.jsx:12-92`). As abas Receitas, Insumos, Relatório e Configurações têm telas próprias (`/manufacturing/recipe`, `/insumos`, `/report`, `/settings`), cada uma com sua âncora — não são gap desta.
>
> **Non-Goals do charter (não reabertos aqui):** CRUD completo continua no Blade legado; sem Kanban de produção; Recipes e BOM são escopo separado.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header do módulo | `os-page-h` com h1 "Produção", subtítulo e CTA que aponta para a rota legada de create (`Index.tsx:144-152`) | Nada — paridade estrutural. O protótipo tem o mesmo `os-page-h` com h1 "Manufacturing" e o CTA "Nova produção" (`manufacturing-page.jsx:161-171`); o vivo especializa o título para a aba corrente, o que é coerente com ter uma rota por aba em vez de um SPA de abas locais. |
| Abas do módulo | `nav.mfg-tabs` com as 5 abas; a corrente é `<span aria-current="page">` e as outras são `Link` do Inertia que **navegam de verdade** (`Index.tsx:163-186`) | Nada — vivo à frente. No protótipo as abas trocam estado local (`manufacturing-page.jsx:173-179`). O comentário do vivo (`:157-162`) registra que a aba corrente virou `<span>` depois de [M] reportar que a barra sumia — correção nascida de uso real, ausente do protótipo. |
| KPIs | 4 cards `KpiCard`: Total, Finalizadas (clicável, filtra), Pendentes, Valor total (`Index.tsx:189-219`) | **Decidir.** O protótipo, nesta aba, **não tem KPIs** — os 4 que ele exibe (`manufacturing-page.jsx:197-221`) são da aba Receitas. Os do vivo nasceram do charter (G1), não do protótipo. O que fica em aberto é o inverso do gap habitual: o protótipo é que está atrás. Decidir se os KPIs desta aba entram no protótipo — ou registrar por escrito que o vivo define esta região. |
| Filtros | Select de local, intervalo De/Até com botão "Aplicar" e checkbox "Só finalizadas" (`Index.tsx:222-283`) | Nada — paridade. Mesmos quatro controles no protótipo (`manufacturing-producao.jsx:41-51`), incluindo o checkbox literal `mfg-check` "Só finalizadas" (`:51`). O vivo faz o mesmo filtro também pelo KPI clicável — o charter (G5) registra que os dois governam o mesmo estado. |
| Tabela / colunas | As 8 colunas do §4.5: Data, Referência, Local, Produto, Qtd, Custo total, Custo unit., Situação (`Index.tsx:319-326`), com sufixo de custo congelado na finalizada (`:356-362`) e `StatusBadge kind="producao"` (`:369`) | Nada — paridade. Mesmas 8 no protótipo (`manufacturing-producao.jsx:21` define as chaves; célula de situação em `:71`). O vivo usa o `StatusBadge` compartilhado em vez do `mfg-pill` local — o comentário em `:391-393` registra que o `StatusPill` local foi removido de propósito. |
| Ordenação por coluna | **Ausente** — varredura contada em `Index.tsx`: `mfg-th` 0 · `<Th` 0 · `ordenar` 0 · `setPag` 0 (os 5 casamentos de um grep frouxo eram `border`/`order`) | **Decidir.** O protótipo ordena por qualquer das 8 colunas, com indicador de direção e default data-desc (`manufacturing-producao.jsx:20`, `CH` em `:21`, `Th` em `:36-42`). É leitura sobre dado já na página. Construir ou rejeitar por escrito. |
| Paginação | **Ausente** — a tela renderiza `productions` inteiro (mesma varredura contada acima: `mfg-pag` 0) | **Decidir.** O protótipo pagina de 10 em 10 com navegação numerada (`manufacturing-producao.jsx:77-85`, `POR_PAG` em `:31`). Sem isso, um período largo renderiza tudo de uma vez. Construir ou rejeitar por escrito. |
| Rodapé do período | Presente e **verbatim**: "N ordens · custo do período X · ordens finalizadas mostram o custo congelado na data" (`Index.tsx:380-386`) | Nada — paridade. Mesma frase no protótipo (`manufacturing-producao.jsx:87`). O comentário do vivo (`:378-379`) registra a diferença que o texto esconde: o custo somado é o **gravado** (`final_total`), não o recalculado do Relatório. |
| Estado vazio | `EmptyState` com título e descrição que mudam conforme haja filtro ativo, e botão de limpar filtros (`Index.tsx:292-310`) | Nada — vivo à frente. O protótipo tem um `mfg-empty` único (`manufacturing-producao.jsx:75`). |
| Criar / editar ordem de produção | Ausente por decisão: o CTA aponta para a rota Blade legada | Nada — Non-Goal do charter (*"Não migrar CRUD completo (create/edit/destroy) — Blade legacy mantém"*). O protótipo tem o formulário inteiro em `MfgProducaoForm` (`manufacturing-producao.jsx:94-189`), com aviso de estoque insuficiente (`:137`) e o efeito de finalizar sobre o preço de custo (`:175`). ⚠️ Quando sair do Non-Goal, é Tier 0 de VALOR **e** ESTOQUE — finalizar consome insumo e pode atualizar preço de custo do produto. |
| Drawer de detalhe da ordem | Ausente | Nada — Non-Goal por herança do anterior. O `MfgProducaoDrawer` (`manufacturing-producao.jsx:191-239`) é a leitura do documento que o CRUD escreve; abrir só a leitura sem o CRUD é decisão possível, mas pertence à mesma US que reabrir o Non-Goal. |
| Multi-tenant | Todas as queries via `ProductionService` escopado por `business_id` (charter G4) | Nada — vivo à frente. O protótipo opera sobre mock (`window.MFG`). |
