---
id: requisitos-dashboard-home-index-gap
tela: Home/Index (/dashboard-legacy)
prototipo: prototipo-ui/cowork/dash-legacy-page.jsx
tela_viva: resources/js/Pages/Home/Index.tsx
gerado_em: 2026-09-06
comparacao: memory/requisitos/Dashboard/Index-visual-comparison.md
---

# GAP-SPEC — Home/Index

> **Esta tabela deriva do dono do inventário**, `Index-visual-comparison.md` (33 itens medidos em
> três rodadas, a última em 2026-09-03), e **não** de leitura nova do código. O que fiz de novo foi
> re-medir os itens que aquele documento deixou marcados como abertos — porque lápide que declara
> gap tem prazo de validade, e o `main` andou desde então. O número muda a cada hora, então fica o
> comando em vez do valor: `git rev-list --count --since=2026-09-03 origin/main` (era **234** na
> geração deste arquivo).
>
> **Duas linhas do inventário CADUCARAM e ficam corrigidas aqui** (a correção do próprio
> `Index-visual-comparison.md` é PR separado — este arquivo registra o achado, não reescreve o dono):
>
> | Item do inventário | Veredito lá (2026-09-03) | Medido hoje | Recibo |
> |---|---|---|---|
> | #10 Sparkline no KPI Líquido | ❌ ABERTO e NÃO DECLARADO | **fechado** | `Index.tsx:180-181` (`<Chart type="area" …>`) e `:306` (`spark={charts?.dia}`); 11 ocorrências de `spark` no arquivo. Entrou em `5b51e2917f` (2026-09-03, #6690 — *"token do hero, sparkline e as 6 dimensões"*) |
> | #8 Painel Pendências | ⏸️ declarado — Backlog do charter | **fechado** | `Index.tsx:429-458` (`data-contract="pendencias"`), charter v6 de 2026-09-04. Entrou em `a58db24d23` (2026-09-04, #6763) |
>
> **Non-Goals que NÃO são gap** (charter, com razão escrita): contagem por aba, exportar CSV no
> rodapé da grade, aba "Fluxo de caixa", widgets pluggable, ação de mutação inline. O protótipo
> desenha os cinco; nenhum entra nesta tabela como pendência.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | `Index.tsx:250-273` usa `PageHeader` com o título "Visão geral" do protótipo (`dash-legacy-page.jsx:215-222`) e um subtítulo com os três totais do período. Item #1 do inventário: ✅. A linha do protótipo que cita o Blade antigo (`:222`) é instrumento do mockup, não capacidade. | Nada — paridade. |
| Cartões de indicador | `Index.tsx:301-327` traz os quatro cartões na ordem do protótipo (`dash-legacy-page.jsx:250-255`). O hero usa os tokens `--kpi-feature-*` (`Index.tsx:136-137`), o sparkline existe (`:180-181`, alimentado por `spark={charts?.dia}` em `:306`) e os três tons semânticos estão aplicados (`success` em `:310`, `warning` em `:318`, `info` em `:323`). | Nada — paridade. Fecha os itens #10 e #13 do inventário, que lá constam abertos. O `KpiCard` do repo segue sem `hero`/`spark` (`Components/shared/KpiCard.tsx:77-86`); a tela contorna com o componente local `KpiHero` (`Index.tsx:118-186`), decisão registrada no próprio arquivo. |
| Contrapartidas | `Index.tsx:387-405` repete os quatro pares do protótipo (`dash-legacy-page.jsx:258-274`) — Compras, A pagar, Devolução de venda, Devolução de compra — na mesma ordem. Três dos quatro subtítulos são idênticos; o de "Devolução de venda" diverge: o protótipo mostra um bruto derivado (`dash-legacy-page.jsx:265`) e o vivo diz "no período" (`Index.tsx:381`). Item #2 do inventário: ✅. | Nada — vivo à frente. O subtítulo do protótipo multiplica o valor por um fator fixo para simular o bruto; o vivo não inventa esse número. Adotá-lo exigiria a devolução bruta como dado real do controller. |
| Pendências | `Index.tsx:429-458` renderiza o painel com os itens em botão e selo, na coluna de 240px à direita, como o protótipo (`dash-legacy-page.jsx:275-288`). Chega por `Inertia::defer` (`Index.tsx:335-340`), fora do first-paint. | Nada — paridade. Fecha o item #8, que o inventário registra como Backlog não decidido. |
| Gráficos de vendas | `Index.tsx:521-530` tem os dois painéis do protótipo (`dash-legacy-page.jsx:291-303`): vendas por dia e vendas por mês, na mesma proporção de colunas. Itens #3 e #4 do inventário: ✅. | Nada — paridade. |
| Grade por abas | Vive em `Home/_components/GradesPainel.tsx:230-328` (`data-contract="grades"`). Já fechou o que o inventário abriu: colunas ordenáveis (#6), ausência do campo de busca (#7) e o rodapé "N de M linhas · clique para abrir o detalhe" (`GradesPainel.tsx:275`, #11). O que resta do protótipo é a densidade: `dash-legacy-page.jsx:317` passa `height={300}` e `density="compact"` ao `DataTablePro`. | **Decidir.** Região do mockup: `dash-legacy-page.jsx:317`; ponto no vivo: `GradesPainel.tsx:230-328`. **Pré-condição medida:** o `DataTable` canônico do repo não tem essas props — procurar por `density` e por `height` em `Components/shared/DataTable.tsx` devolve **0** ocorrências de cada. É o item #14 do inventário, que já o classificava como limite do componente canônico. Adotar significa estender um componente do Design System, decisão [W]. Construir ou rejeitar por escrito. |
