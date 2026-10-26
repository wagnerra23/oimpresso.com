---
id: requisitos-produto-telas-produto-index-visual-comparison
slug: inventory-produto-index-visual-comparison
title: "Produto — Comparativo visual da tela Lista de produtos"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
# canon_reference: o path abaixo foi removido em 2026-05-20, 1070e3759b7
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx _(removido em 2026-05-20, 1070e3759b7)_
blade_source: resources/views/product/index.blade.php
inertia_target: resources/js/Pages/Produto/Index.tsx
approved_by: pending_wagner_screenshot_approval
generated_retroactively: false
pattern_reuse: true
# blueprint_cowork: o path abaixo foi removido em 2026-05-20, 1070e3759b7
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/ _(removido em 2026-05-20, 1070e3759b7)_
---

# Comparativo visual — Lista de produtos (`/products`)

> ## ⚠️ 2026-10-11 — a tela foi refeita; a âncora agora é `prototipo-ui/cowork/Felipe/produto-blade.jsx` (vista `lista`)
>
> O texto abaixo desta seção descreve a v2 de **cards** (âncora `produto-cockpit`, removida em
> 2026-05-20) e fica como histórico. A lista atual segue o charter v3 (#9118): dados #9119, tela
> #9124, menu ligado #9125, indicadores #9127, aba de estoque #9129, painel de detalhe #9131/#9132.
>
> **Medição 2026-10-11** — `scripts/design/design-diff.mjs`, a MESMA sonda nos dois lados, tema
> escuro, 1593px: produção `https://oimpresso.com/products` (biz 1) × espelho
> `cowork/Felipe/oimpresso.com.html` rota `prod-lista`. Papéis: KPI = filhos de `produto-kpis`,
> título = `h1`, filtros = `.pb-fld` de `produto-filtros`, linha = 1ª de `produto-tabela`.
>
> | Medida | Produção | Protótipo | Veredito |
> |---|---|---|---|
> | KPI: quantidade, alinhamento, cor, valor 22px | iguais | iguais | IGUAL |
> | Cor do botão principal | igual | igual | IGUAL |
> | Preço de compra / de venda | mono 12px | normal 13px | **corrigido** (sem mono) |
> | Botão "Ações" da linha | 14px | 12px | **corrigido** (12px) |
> | Título | 22px / 600 | 19px / 700 | mantido — decisão [W] D-PH-0923 (h1 600 em todas as telas, `PageHeader`) |
> | Linhas da área de filtros | 3 | 2 | não é defeito — a empresa medida tem 2 filtros de módulo (Modelo do dispositivo, WooCommerce) que também existem na lista Blade |
> | Coluna Imagem | imagem padrão | marcador "IMG" | lido em 2026-10-11 como dado; **reclassificado como defeito por [F] em 2026-10-14 e corrigido** (UC-PIDX-22: o servidor manda `null` quando não há foto, e a tela mostra o "IMG") |
> | Coluna Categoria | 1 linha | 2 linhas | não é defeito — a 1ª linha medida não tem categoria; a subcategoria aparece quando existe |
> | Larguras declaradas (`<col>`), checkbox como botão | sim | não | produção à frente, não é defeito |
>
> Não medido NAQUELA rodada: toolbar, abas e rodapé da lista (a sonda cobria KPI, título, filtros e a
> 1ª linha da tabela). Foram medidos em 2026-10-12 — tabela logo abaixo. O painel de detalhe foi
> medido em 2026-10-11 (noite, horário de Brasília) — seção seguinte.
>
> ### Funções da lista — medido em 2026-10-12 (produção biz 1, pelo DOM e por clique)
>
> Inventário tirado do protótipo (`TelaLista`, `ListaProdutos`, `MenuColunas`, modais e `PaletaProduto`)
> e conferido na tela em produção **pelo que a tela faz**, não pelo rótulo.
>
> | Item do protótipo | Produção em 2026-10-12 | Veredito |
> |---|---|---|
> | Cabeçalho: contexto, "Atualizado", Baixar Excel, Novo produto | presentes | IGUAL |
> | Filtros (7 + "Não para venda"), recolher, chips "Filtrando por", "Limpar tudo" | presentes (+ 2 filtros de módulo) | IGUAL |
> | Abas com contagem, busca com atalho `/`, atalho `n`, densidade, menu de colunas | presentes | IGUAL |
> | Coluna "Campo personalizado" | aparece quando a empresa deu nome ao campo (até 7) | não é defeito — dado |
> | Ordenar clicando no cabeçalho | cabeçalho era texto puro; o clique não fazia nada | **defeito — corrigido** (UC-PIDX-13) |
> | Arrastar a borda da coluna para redimensionar | não existia em 2026-10-12 (larguras fixas); **feito em 2026-10-13** (UC-PIDX-16), com a regra do `DataGrid` do DS (alça 7px, piso 48px, Ação e Imagem fixas, sem alça na última) | feito |
> | Botão "Cadastro rápido" | não existia em 2026-10-12; **entrou em 2026-10-12** (UC-PIDX-14), com "Gerenciar estoque" e locais (2+) a mais que o protótipo, por decisão [F] | feito |
> | Menu da linha: 13 ações | 10 presentes em 2026-10-12; **11 em 2026-10-13**, com "Dados fiscais (NCM/CFOP)" (UC-PIDX-17, abre o painel na aba Fiscal) | falta "Transferir entre locais" (vira requisição de transferência); "Usar em uma OS" saiu por decisão [F] 2026-10-11 |
> | Estoque inicial em janela | abria a página de estoque inicial em 2026-10-12; **virou janela em 2026-10-13** (UC-PIDX-15), com tudo o que a tela Blade tem (variações, várias linhas, data, observação, segunda unidade) | feito |
> | Barra de seleção (6 ações + limpar), com confirmação | presente (+ WooCommerce Sync) | IGUAL |
> | Lista vazia, destaque de inativo e de estoque baixo, "Exportar filtrados" | presentes | IGUAL |
> | `Ctrl+K` com as telas do módulo e ir a um produto | `Ctrl+K` abre a busca geral de tarefas | FALTA |
> | Aviso "Você veio de… / Voltar e usar" | não existe | FALTA |
> | Aba Relatório: linhas por página (10/25/50/100) | fixo em 25 até 2026-10-13; **feito em 2026-10-13** (UC-PIDX-18) com as opções da lista Blade (25 … 1000, Todos), mantendo Anterior/Próxima | feito, com opções diferentes de propósito |
> | Aba Relatório: lucro potencial em verde | cor comum do texto até 2026-10-13; **feito em 2026-10-13** (UC-PIDX-18): verde se positivo, vermelho se negativo, total sem cor | feito |
> | Aba Relatório: a busca da lista filtra o relatório | não filtra | de propósito — a aba Blade também não usava a busca (UC-PIDX-08) |
> | Lista inteira na grade, sem páginas | paginada no servidor, com "Mostrar N por página" | produção à frente |
>
> Lição da medição: o 1º clique na caixa de seleção foi simulado por script e a barra não apareceu;
> com clique de verdade ela aparece completa. Clique simulado em componente Radix não é medida.
>
> ### Painel de detalhe — medido em 2026-10-11 (mesma sonda nos dois lados, tema escuro, 1593px)
>
> Produção `https://oimpresso.com/products?ver=1817` (biz 1) × espelho `cowork/Felipe/oimpresso.com.html`
> rota `prod-lista`, `DetalheDrawer`. A sonda lê caixa, tipografia e cor **computadas** de cada papel
> (raiz, selos, título, subtítulo, fechar, abas, corpo, campos, imagem, rodapé) — nunca classe.
>
> **Causa da maioria das diferenças (medida, não deduzida):** o `<Sheet>` abre num portal no `<body>`,
> fora do `.cockpit`. Dentro do painel `--text`, `--text-mute`, `--border`, `--accent`, `--surface` e
> `--font-sans` vinham **vazios** (`getComputedStyle(...).getPropertyValue`), enquanto na lista, dentro do
> `.cockpit`, todos resolvem. Conserto: o painel passa a montar **dentro do `.cockpit`** (prop
> `container` do `Sheet`), e os tokens da fundação chegam sozinhos — nenhum é redefinido.
>
> A 1ª tentativa foi um CSS à parte repondo os tokens no painel (o padrão legado do Financeiro,
> `fin-cowork.css` Onda 22b). O `foundation-guard` reprovou, com razão: token só se define na
> fundação (ADR UI-0013). Medido em produção antes de trocar: movendo o painel aberto para dentro
> do `.cockpit`, a posição e o tamanho ficam idênticos (773, 0, 820×863), a fonte vira IBM Plex,
> `--text`/`--border` resolvem, e o fundo escurecido segue cobrindo o menu lateral.
>
> | Medida | Produção antes | Protótipo | Depois do conserto |
> |---|---|---|---|
> | Fonte | `ui-sans-serif` | IBM Plex Sans | IBM Plex Sans |
> | Cor do texto | `oklch(0.965 0.004 240)` | `oklch(0.94 0.005 90)` | igual ao protótipo |
> | Rótulo dos campos | cor cheia do texto | `oklch(0.67 0.005 90)` | igual ao protótipo |
> | Selo do tipo | sem fundo, sem cor | fundo `oklch(0.33 0.09 295)`, texto `oklch(0.7 0.15 295)` | igual ao protótipo |
> | Fundo do painel | `oklch(0.26 0.006 240)` | `oklch(0.30 0.008 240)` | igual ao protótipo |
> | Borda acima do rodapé | cor do texto (branca) | `oklch(0.34 0.008 240)` | igual ao protótipo |
> | Linha das tabelas | sem borda | 1px `oklch(0.31 0.008 240)` | igual ao protótipo |
> | Aba ativa | traço na cor do texto, sem fundo | traço `oklch(0.7 0.15 295)`, fundo `oklch(0.33 0.09 295 / 0.5)` | igual ao protótipo |
> | Faixa das abas | sem fundo, sem linha | fundo `oklch(0.26 0.006 240)`, linha 1px | igual ao protótipo |
> | Cabeçalho | um bloco de 110 | faixa de selos + fechar (57) e bloco do título (76) | estrutura do protótipo |
> | Subtítulo | 14px | 12,5px | 12,5px |
> | Fechar | 44×44, cor cheia | 28×28, raio 6, esmaecido | 28×28, raio 6, esmaecido |
> | Rodapé | botões à esquerda, 32 de altura, 14px | à direita, 30 de altura, 12,5px | à direita, 30, 12,5px |
> | Títulos de seção nas abas | não havia (só `caption` oculto) | 11px / 700, maiúsculas, esmaecido | iguais ao protótipo |
> | Caixa da imagem sem foto | não aparecia | moldura 168 com texto | moldura 168 com "Sem imagem" |
> | Variação de produto único | "DUMMY" | "Padrão" | "Padrão" |
> | Largura, grade de 3 colunas, vãos, tamanho dos campos, ordem das abas | iguais | iguais | IGUAL |
>
> **As colunas "Depois" de fonte, cor do texto, rótulo, selo, borda do rodapé e aba ativa foram
> medidas** em produção com o painel movido para dentro do `.cockpit` (só no navegador da medição).
> As medidas da moldura (faixa do topo, fechar, subtítulo, faixa das abas, rodapé à direita) são
> classes novas na tela e **só se confirmam depois do deploy**. O mecanismo (montar dentro do
> `.cockpit`), os títulos, "Sem imagem" e "Padrão" estão no teste de componente
> `tests/js/produto-painel-detalhe.test.tsx`.
>
> **Ficam diferentes do protótipo, de propósito ou por dado:**
> - Texto das abas: a aba ativa usa a cor de texto do `PageHeaderTabs` (`oklch(0.965 0.004 240)`), que é o
>   componente das abas do app inteiro; mudar ali muda todas as telas. Não é decisão desta tela.
> - Texto do botão principal: escuro em produção, branco no protótipo — é o `Button` do Design System.
> - (2026-10-14) A caixa "Sem imagem" não aparecia em produção para produto sem foto: o servidor mandava a imagem genérica `/img/default.png`. Corrigido no UC-PIDX-22 (`imagem = null`).
> - Texto da caixa sem foto: "Sem imagem" (o protótipo traz a dica de envio "1:1, até 5 MB", que num
>   painel só de leitura afirmaria algo que a tela não faz).
> - Aba Fiscal: a produção mostra as colunas fiscais reais do cadastro (CFOP dentro/fora, CST…); o
>   protótipo tem um conjunto menor e um botão "Abrir no módulo Fiscal". Em 2026-10-13 entrou o
>   "Tipo de imposto na venda" (UC-PIDX-19). **2026-10-15 (UC-PIDX-25), pedido de [F]: os três itens abaixo
>   entraram**, sem inventar o que não existe — a origem aparece como o valor que a nota usa (`0 —
>   Nacional`, fixo nas duas emissões) com o aviso de que é o mesmo para todos; o texto de ajuda é o
>   do protótipo, corrigido onde ele afirmava o que a emissão não faz; e "Abrir no módulo Fiscal"
>   leva à Tributação do NfeBrasil com o produto no simulador (UC-NFTR-29), só para quem tem o
>   módulo Fiscal no pacote e a permissão (a 1ª versão checava o pacote do NfeBrasil e não aparecia;
>   corrigido no mesmo dia). O registro abaixo fica como era em 2026-10-13. **Não feitos, com motivo medido:** "Origem da
>   mercadoria" (o campo não existe no cadastro nem no banco), "Abrir no módulo Fiscal" (não há
>   tela fiscal por produto) e o texto de ajuda (o do protótipo chama de serviço todo produto sem
>   NCM, o que é falso para cadastro incompleto).
> - Aba Estoque: o protótipo tem 3 indicadores (estoque atual, valor pelo custo, valor pela venda) e
>   um botão "Transferir" por linha. Os **indicadores entraram em 2026-10-12** (UC-PIDX-12): a soma das
>   linhas do relatório de estoque do produto, feita no servidor. A legenda do custo é "pelo preço pago
>   nas compras" (o protótipo diz "preço de compra padrão", que não é a conta do relatório). O
>   "Transferir" segue **não feito**: vira a requisição de transferência combinada com [F] em 2026-10-11.
> - "Gerar OP" no rodapé de composição: **não feito** (medido em 2026-10-13). A Fabricação produz por
>   receita de uma variação (`MfgRecipe`); composição não é receita, e não há tela de destino para
>   "nova OP a partir da composição".
> - Aba Composição e imagens — **entraram em 2026-10-14** (UC-PIDX-20): a linha "Total líquido" e o
>   preço de venda da composição do protótipo, e o que a janela "Ver" da Blade mostrava e o painel
>   não: as colunas de preço de cada item e as imagens das variações. As colunas da Blade ficam numa
>   tabela à parte ("Preços dos itens") e as imagens numa seção abaixo: medido em produção, a tabela
>   com todas as colunas teria 973 px num painel de 779 px. O preço da composição sai sem e com
>   imposto (o protótipo mostra sem, a Blade com).
> - Produto que não controla estoque — **entrou em 2026-10-14** (UC-PIDX-21): a aba Estoque aparece
>   com o aviso, como no protótipo (antes a aba sumia). O texto fica só em "Este produto não gerencia
>   estoque.": o protótipo traz o nome da coluna (`enable_stock = 0`) e afirma que serviços e
>   composições entram assim, o que não vale para composição com estoque ligado no cadastro.
>
> ### Painel de detalhe — 2ª medição, 2026-10-14 (todas as abas, mesma sonda nos dois lados)
>
> Tema escuro, 1593px. Protótipo `cowork/Felipe/oimpresso.com.html` rota `prod-lista`, produto
> "Adesivo de recorte — vinil colorido" × produção biz 1, produto "Broca" (os dois variáveis, com
> estoque, ativos). `produto-blade.jsx` do espelho conferido igual ao do Cowork vivo antes de medir.
> A sonda acha cada papel pelo texto (as classes diferem) e lê o estilo calculado de topo, abas,
> títulos, campos, cartões, tabelas, botões e rodapé, em cada aba.
>
> **Corrigido (grupo A, mesmo dia):**
>
> | # | Onde | Protótipo | Produção antes |
> |---|---|---|---|
> | A1 | Altura de linha do painel | 1,45 | 1,5 (cada linha 0,5–1px mais alta) |
> | A2 | Títulos de seção | altura de linha 1,2 (13px) | 1,5 (17px) |
> | A3 | Faixa das abas | 39px | 36px |
> | A4 | "Abrir histórico de estoque" | 26px de altura, 11,5px | 32px, 14px |
> | A5 | Aba Estoque: tabela → texto → botão | 10px e 16px | 12px e 12px |
>
> Medido antes de publicar, aplicando as cinco mudanças no painel aberto em produção (só no navegador
> da medição) e rodando a sonda de novo: as cinco diferenças somem e as posições da aba Estoque
> batem com o protótipo (faixa 133–172, tabela→texto 10px, texto→botão 16px, botão 26px).
>
> **Ficam, por vir de componente usado no app inteiro (decisão [W]/[F], não desta tela):** contador
> da aba (fundo e altura — `PageHeaderTabs`), texto do botão principal (branco no protótipo),
> fundo dos botões de contorno (sólido × translúcido) e cor do texto do "Fechar" (cinza × normal) —
> `Button` do Design System.
>
> **Botões do rodapé — ajustados só neste painel em 2026-10-14 (decisão [F]):** o texto do "Editar
> produto" passa a branco (`rgb(255,255,255)`, `text-white`), o fundo de "Comprar", "Preços por grupo" e
> "Imprimir" passa a sólido (`var(--surface)` = `oklch(0.3 0.008 240)`) e o texto do "Fechar" a
> esmaecido (`var(--text-dim)` = `oklch(0.72 0.005 90)`), com os estados de passar o mouse do
> protótipo (`.os-btn`). O `Button` do DS não muda. Medido em produção antes de publicar, aplicando os
> mesmos valores por estilo inline no painel aberto. ⚠️ Lição da medição: com a aba do navegador em
> segundo plano a `transition-all` do botão não avança, e o estilo calculado devolve o valor antigo
> por segundos — desligue a transição no elemento antes de ler.
>
> **Abas — ajustadas só neste painel em 2026-10-14 (decisão [F]):** texto da aba ativa
> `var(--text)` = `oklch(0.94 0.005 90)` (era `oklch(0.965 0.004 240)`), das inativas `var(--text-dim)`
> = `oklch(0.72 0.005 90)` (era `oklch(0.74 0.008 240)`) e fundo do contador inativo `var(--border-2)` =
> `oklch(0.31 0.008 240)` (era `oklch(0.23 0.006 240)`), como no `.cli-moduletopnav-tab` do protótipo.
> O fundo do contador vem de estilo inline do `PageHeaderTabs`, que classe não vence; por isso o
> componente ganhou a opção `tone="prototype"`, que só este painel liga — sem ela, todas as outras
> telas seguem com as cores do DS (casos no `pageHeaderTabsFidelity.spec.tsx`, com controle).
>
> **3ª medição, 2026-10-15 (tema escuro, produto variável com 4 variações, mesma sonda):** depois dos
> dois ajustes acima, botões, abas, contadores (ativo e inativo), rótulos, valores, selo do tipo,
> caixa "Sem imagem", tabelas e cartões da aba Estoque batem com o protótipo. Sobrou uma diferença:
> o **corpo do painel** (a área com os dados, entre a faixa das abas e o rodapé) não tinha fundo
> próprio e herdava o `--surface` = `oklch(0.3 0.008 240)` do cabeçalho; no protótipo ele é
> `--bg-2` = `oklch(0.23 0.006 240)`, e é isso que destaca os cartões e a caixa "Sem imagem". Corrigido
> com `bg-[var(--bg-2)]` no corpo (nenhuma regra fora de `@layer` pinta esse elemento — conferido no
> navegador). Ficam como estão, por serem mínimos: o fundo escurecido atrás do painel (preto a 50% em
> produção, `oklch(0.15 0 0 / 0.45)` no protótipo) e a sombra do painel.
>
> **2026-10-15 — linha entre a faixa do código e o título ([F]):** no protótipo a faixa do código +
> selo + fechar é a do `Drawer` do DS, com `border-bottom: 1px solid var(--border)` (medido no espelho,
> PRD-0009: faixa 57px com borda `oklch(0.34 0.008 240)`, título logo em seguida). Em produção a
> faixa (`TOPO` em `PainelDetalhe.tsx`) não tinha borda e compensava com 15px embaixo. Corrigido:
> 14px + borda de 1px, mesma altura de 57px. A medição de 2026-10-11 registrou a estrutura
> ("faixa de selos + fechar (57) e bloco do título (76)") mas não a borda.
>
> **De propósito ou por dado:** "Transferir" por local (espera a requisição de transferência);
> campos da aba Fiscal; coluna "Variação" no estoque de produto variável; Prateleira sem cadastro
> mostra o aviso, como a Blade, em vez de uma linha por local com "—"; e diferenças de conteúdo
> (subtítulo, foto, grupos de preço, nome longo de variação). As abas do protótipo usam a fonte Arial
> porque os botões dele não herdam a fonte do sistema — defeito do protótipo, não copiado.
> **Não medido:** aba Composição em produção (a empresa de teste não tem produto composto), tema
> claro, estados de passar o mouse e de foco.
>
> ### 2026-10-26 — decisões do [F] na âncora: o protótipo mudou, a produção ainda não
>
> O [F] comentou a âncora (`prototipo-ui/cowork/Felipe/produto-blade.jsx`) e, na conversa do mesmo
> dia, fechou a regra do imposto: aparece por relevância, não por permissão. O protótipo mudou no PR
> #9925. A produção foi **lida no código** do `main` em 3886121, não medida na tela.
>
> | Item | Protótipo depois do #9925 | Produção em 2026-10-26 | O que falta |
> |---|---|---|---|
> | "Comprar" (menu Ações e rodapé do painel) | não existe | aparecia com `purchase.create` (`AcoesProduto.tsx:74`, `PainelDetalhe.tsx:710`) | **feito** junto com o UC-PIDX-11 reescrito |
> | "Gerar OP" (rodapé do painel) | não existe | nunca existiu | nada; a pendência registrada em 2026-10-13, acima, deixa de existir |
> | Preço de venda na lista | com o imposto do produto, como a produção (o protótipo mostrava sem) | com imposto (`ProductController.php:408`, `sell_price_inc_tax`) | nada |
> | Aba Dados | "Preço de venda" primeiro; com imposto, também o preço sem imposto, o imposto aplicável e a frase de que o preço já o inclui | sem preço; "Imposto aplicável" e "Tipo de imposto na venda" sempre (`PainelDetalhe.tsx:386-387`) | **feito** com o UC-PIDX-19 reescrito |
> | Tabelas de Variações e Composição | sem imposto, uma coluna de preço; com imposto, "sem imposto" e "com imposto" | sempre as duas colunas, com "(excl.)" e "(incl.)" (`PainelDetalhe.tsx:436-440,507-508,546-549`) | rótulos **feitos** junto com o UC-PIDX-11; coluna única **feita** com o UC-PIDX-19 (decide pelos valores: uma coluna quando sem e com imposto são iguais) |
> | Aba Fiscal e "Dados fiscais (NCM/CFOP)" do menu | só em empresa que emite nota; a aba começa dizendo se o produto está pronto para nota | sempre (`PainelDetalhe.tsx:314`, `AcoesProduto.tsx:77`) | **feito** (UC-PIDX-17 e 25): o servidor manda `contexto.emite_nota`, com o mesmo teste de pacote do botão "Abrir no módulo Fiscal" |
> | Coluna e filtro Imposto da lista | só se a empresa tem alíquota; a coluna nasce desligada | sempre (`colunas.tsx:213`, `FiltrosLista.tsx:85`) | **feito** (UC-PIDX-34) |
> | Coluna Imposto nos dois exports | segue a coluna; Compra e Venda com imposto, como a grade | "Imposto" sempre (`ProductController.php:1086`; `app/Exports/ProductsExport.php`) | CSV **feito** (UC-PIDX-34); o "Baixar Excel" é o arquivo de reimportação da Blade, e mexer nele é decisão à parte |
>
> Sem conflito com o Non-Goal "Função a menos que a lista Blade": nada do que a Blade mostra deixa de
> existir na tela nova. **Descartado no mesmo dia:** esconder o imposto por permissão
> (`fiscal.access`), que chegou a ir para o protótipo neste PR. Escondia de quem vende um dado que a
> nota imprime, e deixava a tela nova mostrar menos que a Blade.
>
> **Medido no protótipo** (Playwright, 1440px, Tweaks "papel", "empresa emite nota" e "empresa tem
> alíquota"), produto "Vinil adesivo brilho" (ICMS 18% sobre o preço sem imposto):
> - Administrador e Balcão veem o mesmo: o preço de venda com os 18% somados, igual na lista, no CSV
>   e na aba Dados; na aba Dados também o "Preço sem imposto" e "Imposto aplicável ICMS 18% ·
>   exclusivo"; tabela de Variações com as duas colunas; aba Fiscal com "Pronto para nota".
> - Produto sem imposto ("Ilhós metálico nº 12"): só "Preço de venda" na aba Dados e uma coluna de
>   preço na tabela.
> - Empresa que não emite nota: sem aba Fiscal e sem "Dados fiscais" no menu.
> - Empresa sem alíquota: sem filtro e sem opção de coluna Imposto (11 opções no menu, não 12), CSV
>   sem a coluna, preços iguais ao sem imposto.
> - Serviço sem NCM ("Instalação em fachada"): "Sem NCM — a nota sai com o NCM padrão da empresa".
> - 0 erro no console nos quatro cenários.

> **Tipo de tela:** list cockpit list+detail (drawer pattern)
> **Persona:** Larissa (ROTA LIVRE biz=4), 1280px
> **Refs:**
> - Blade legacy: `resources/views/product/index.blade.php` (DataTables jQuery + Yajra server-side)
> - Canon Cowork: `produto-cockpit/produto-cockpit-page.jsx` _(removido em 2026-05-20, 1070e3759b7)_
> - Page MWART: `resources/js/Pages/Produto/Index.tsx`
> - RUNBOOK: [`RUNBOOK-produto-index.md`](RUNBOOK-produto-index.md)
> - ADR 0149 screen-pattern reuse (este é o blueprint pra Show/Edit/Create/SellingPrices)

## Resumo executivo

Blade legacy tem topbar de filtros + DataTables com 11 colunas + ações dropdown por linha. Cowork blueprint substitui por: header sticky + KPI strip 4 cards + filter tabs categoria + lista cards densa + drawer detalhe lateral. **MWART entrega:** versão Page Inertia que reusa AppShellV2 + tokens OKLCH Cowork + header pattern, com `Inertia::defer()` em props caras (KPIs, rows, categorias) — alinhado RUNBOOK-inertia-defer-pattern (Tier 0 desde 2026-05-15).

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Blade legacy | Cowork blueprint | Decisão MWART |
|---|---|---|---|
| Sidebar | UPOS 250px submenus | AppShellV2 260px (canon Cockpit) | AppShellV2 — paridade canon |
| Header | `<h1 class="content-header">` simples | Header sticky h1 + subtitle + ações primárias | PageHeader shared — paridade |
| Topnav módulo | Submenu dentro sidebar | Tabs `Catálogo · Categorias · Insumos · Tabelas · Histórico` | Tabs (segue Unificado pattern, opcional Wave 3) |
| Grid breakpoints | Bootstrap | `max-w-7xl` + grid responsivo | `max-w-7xl` |

### 2. Hierarquia visual

| Aspecto | Blade | Cowork | MWART |
|---|---|---|---|
| Ação primária | "+ Adicionar produto" topo direito | Botão primary topo direito | PageHeader action slot — paridade |
| Ações secundárias | Dropdown bulk 12 actions | Botão "Importar" outline | "Importar" + "Novo" — simplificado |
| KPIs top | Ausente | 4 cards: Total · Ativos · Categorias · Populares | 4 cards Deferred — paridade Cowork |

### 3. Densidade

| Aspecto | Blade | Cowork | MWART |
|---|---|---|---|
| Linhas tabela | DataTables ~38px linha | Lista cards densa Linear | Cards 1 col 1280px (Larissa) |
| Padding seções | `panel-body 15px` | `mx-6 mt-4` (24px) | `space-y-6` — paridade Cowork |

### 4-8. Cor, tipografia, interação, acessibilidade, fiscal

- Cores: tokens OKLCH Cowork (emerald/stone/rose semânticos)
- Tipografia: h1 24px, table 12-13px, SKU mono 11.5px, valores `tabular-nums`
- Interação: click card → drawer lateral (pattern Cowork) ou link `/products/{id}`
- Acessibilidade: `aria-label` em ações, focus visible (default Tailwind)
- Fiscal: SKU + categoria + unit visíveis (Larissa diferencia produtos rápido)

## Gaps catalogados

| Gap | Impacto | Quando atacar |
|---|---|---|
| Drawer lateral pattern Cowork | UX melhora — usuário não troca de rota pra ver detalhe | Wave 3 (out of scope desta migração) |
| Tabs categoria interativos (filter client-side) | Larissa filtra por tipo rápido | Wave 3 |
| Toggle "Mostrar inativos" persisted | Larissa às vezes precisa ver inativo | Wave 3 |
| Topnav módulo (afeta 78 telas MWART) | Navegação intra-módulo | PR separado (ADR 0039 §1 gap conhecido) |

## Aprovação

⏳ **Pendente Wagner screenshot approval** — quando build:inertia gera bundle, capturar screenshot 1280×800 e Wagner aprova via SYNC_LOG.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto (Agent paralelo). |
