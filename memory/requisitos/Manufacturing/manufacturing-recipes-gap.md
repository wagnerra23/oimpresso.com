---
id: requisitos-manufacturing-manufacturing-recipes-gap
tela: Manufacturing/Recipes (/manufacturing/recipe)
prototipo: prototipo-ui/cowork/manufacturing-page.jsx
tela_viva: resources/js/Pages/Manufacturing/Recipes.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Manufacturing/Recipes

> **Âncora declarada no charter** — `related_prototype: prototipo-ui/cowork/manufacturing-page.jsx` (a única das 10 telas deste lote com âncora por `related_prototype`, não por bundle).
>
> **Esta tela é o porte mais recente e mais fiel do lote.** O charter registra: *"A fonte visual está no espelho e é **idêntica** ao ZIP — conferido arquivo a arquivo, 0 linhas de diferença nos 6 `.jsx` + o `.css`"*, com smoke em prod em 2026-09-03. A expectativa-base, portanto, é **paridade quase total** — e é o que a comparação abaixo encontra.
>
> **Frescor do espelho (medido 2026-09-06):** `manufacturing-page.jsx` = **STALE** — o Cowork vivo trocou `<nav className="mfg-tabs">` (`:159`) por `window.CliTabs`, na mesma rodada de adoção de DS que atingiu os outros protótipos deste lote. Troca de implementação do mesmo controle, não de capacidade. ⚠️ Consequência prática para esta tela: o vivo (`Recipes.tsx:167`) hoje é **fiel ao espelho**; se o espelho for atualizado, essa parte passa a divergir sem que ninguém tenha mexido no `.tsx`.
>
> **Escopo 1:N.** A região desta tela é a aba **Receitas** de `manufacturing-page.jsx` (`ManufacturingPage`, `:18-304`) mais o `RecipeDrawer` (`:306-365`). As outras 4 abas têm telas próprias.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header do módulo | `os-page-h` com h1 "Manufacturing", subtítulo contando receitas e ordens, e CTA "Nova receita" gated por `permissions.criar` (`Recipes.tsx:144-162`) | Nada — paridade. Mesmo bloco e mesma copy no protótipo (`manufacturing-page.jsx:161-171`). |
| Abas do módulo | `nav.mfg-tabs`, corrente como `<span aria-current="page">`, demais como `Link` do Inertia (`Recipes.tsx:167-195`) | Nada — paridade **com o espelho**. Ver a nota de frescor acima: no Cowork vivo esta região já é `window.CliTabs`. O comentário do vivo (`:190-194`) registra que a aba Configurações deixou de ser âncora crua para rota Blade — correção nascida de uso ([F] clicou e saiu do SPA), ausente do protótipo. |
| KPIs | 4 cards: Custo médio/unidade, Margem abaixo de 45% (filtra), Desperdício ≥ 8% (filtra), Produção do mês (`Recipes.tsx:199-240`) | Nada — paridade. Mesmos 4, mesmos rótulos e o mesmo par clicável no protótipo (`manufacturing-page.jsx:197-221`). |
| Busca e chips de categoria | Busca com atalho `/` e chips derivados das categorias existentes (`Recipes.tsx:246`, `:255-262`) | Nada — paridade. Mesma UI e mesmo atalho no protótipo (`manufacturing-page.jsx:224-232`; handler de `/` em `:46-49`). |
| Tabela / colunas | 7 colunas ordenáveis — Receita, Categoria, Quantidade, Custo total, Custo unitário, Venda, Margem — com seleção por linha (`Recipes.tsx:275-296`, `Th` em `:131-137`) | Nada — paridade. Mesmas 7 e mesmo componente `Th` no protótipo (`manufacturing-page.jsx:75-81`, cabeçalho em `:239-247`). |
| Paginação | Presente (`Recipes.tsx:361`) | Nada — paridade (`manufacturing-page.jsx:252-262`). |
| Ações em massa | `BulkBar` com "Limpar" e "Imprimir fichas" (`Recipes.tsx:393-410`) | Nada — decisão já registrada. A 3ª ação do protótipo, **"Atualizar preço de venda do produto"** (`manufacturing-page.jsx:290`), foi deixada de fora **por escrito** no próprio código (`Recipes.tsx:387-392`): o handoff §18.1 diz *"Não implemente esse fator 2"*, a regra de markup real não foi decidida, e escrever em N preços é Tier 0 de VALOR — exige dupla prova, antes→depois e aprovação [W]. A flag `enable_updating_product_price` já vem no payload esperando essa decisão. **Não reabrir aqui**: é decisão [W] pendente, não gap. |
| Drawer da receita | `mfg-drw` com grupos de ingredientes, subtotais, bloco de custo (ingredientes, extra, desperdício, custo por unidade, venda, margem) e a nota sobre o custo ser recalculado (`Recipes.tsx:451-538`) | Nada — paridade. Mesma estrutura e mesma copy no protótipo (`manufacturing-page.jsx:306-360`), incluindo o link para Compras (`:302` no vivo, `:355` no protótipo). |
| Rodapé do drawer | Fechar, Ficha com custo, Via de produção, Produzir e Editar ingredientes (`Recipes.tsx:539-559`) | Nada — paridade nas cinco ações. O que muda é o destino: no vivo, "Produzir" e "Editar ingredientes" são âncoras para rotas Blade (`ROTA_PRODUZIR` e `ROTA_EDITAR_INGREDIENTES`, `Recipes.tsx:43-44`); no protótipo abrem telas internas (`MfgIngredientesEditor` e `MfgProducaoForm`, montados em `manufacturing-page.jsx:136-153`). Isso é o Non-Goal de CRUD do módulo, já declarado no charter do Index — **não reabrir aqui**. |
| Criar receita | `<a href="/manufacturing/recipe/create">` — rota Blade (`Recipes.tsx:155-159`, `ROTA_NOVA` em `:42`) | Nada — Non-Goal de CRUD. O protótipo tem o modal `MfgNovaReceita` com opção de clonar receita existente (`criarReceita`, `manufacturing-page.jsx:97-112`). Migrar é a mesma US que reabre o Non-Goal. |
| Excluir receita | **Ausente** — varredura contada em `Recipes.tsx`: `Excluir` 0 · `AlertDialog` 0 · `mfg-modal` 0 | Nada — Non-Goal de CRUD (`destroy` fica no Blade legado). O protótipo tem o modal de confirmação com o texto que explica o efeito colateral (*"Ordens de produção já lançadas continuam com o custo registrado"*, `manufacturing-page.jsx:271-283`). Quando o CRUD migrar, **essa frase é o conteúdo a preservar** — ela é a única no protótipo que explica por que excluir uma receita não reescreve o histórico de custo. |
| Toast de feedback | **Ausente** (`mfg-toast` 0 ocorrências) | **Decidir.** O protótipo dá retorno curto após cada ação (`manufacturing-page.jsx:41`, render em `:300`). No vivo as ações que precisariam de toast são navegações para o Blade, onde o feedback é da tela de destino — o que torna o toast pouco útil hoje e útil quando o CRUD migrar. Construir agora, adiar junto do CRUD, ou rejeitar por escrito. |
| Impressão de fichas | `MfgFichaPrint` presente, com e sem custo (`Recipes.tsx:406`, `:544-548`) | Nada — paridade (`manufacturing-page.jsx:296`). |
| Multi-tenant e permissões | `permissions.criar` / `.editar` / `.prod` vindas do servidor; `settings.enable_updating_product_price` no payload | Nada — vivo à frente. O protótipo simula permissões com estado local editável na aba Configurações (`perms`, `manufacturing-page.jsx:25`). |
