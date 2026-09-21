# Absorção da Consulta de Produtos V2 pela tela "Todos os produtos"

Lista para verificação. **Nada foi implementado** — cada item espera SIM / NÃO / ajuste.

---

## §-1 Alvo confirmado, e uma errata retratada (28/08/2026)

**O §1 está certo.** A tela é a `Todos os produtos` do protótipo — `produto-blade.jsx`, rota
`prod-lista`, cabeçalho `Produtos · Catálogo`, item marcado na sidebar do
`oimpresso.com.html`. Confirmado por screenshot da usuária. Uma errata escrita antes desta, que
apontava o alvo para o repo React, **está retratada**: eu troquei "o protótipo traduz o Blade" por
"o alvo é o Blade", o que não segue.

**Fato medido que fica registrado** (não muda a autoridade do §1 — é contexto para quem implementar
no repo): a tela React `resources/js/Pages/Produto/Unificado/Index.tsx` (`/products/unificado`,
charter `owner: wagner`, tier A) **já absorveu o handoff "Consulta de Produtos" em ondas**. Medido em
`Unificado/_components/catalogo.ts`: `Permissoes {custo, preco, composicao, inativar}` (L12-24) ·
`ABAS_CATALOGO` com 6 abas por tipo (L218-226) · `KpiKey` de KPI-filtros clicáveis (L235) ·
`EstadoEstoque` **Disponível / Abaixo do mínimo / Sem saldo** (L120-160) · `marcadorGrade`
"4 de 6 com saldo" (L100-110) · `locais?: LocalSaldo[]` (L60-66); paginação server-side 10/25/50/100
com padrão 25 (charter, onda 2).

Isso cria uma divergência real de nomenclatura entre protótipo e repo: o §4 deste documento crava
**SKU / Estoque atual / Alerta** por autoridade da tela do protótipo, e a tela React já roda
**Referência / Disponível / Abaixo do mínimo**. Fica como pendência (§7, item novo), sem alteração
em nenhum §.

---

## §0 Contrato de leitura

**Autoridade.** Manda a tela **Todos os produtos** (do Wagner). Em toda divergência, o valor, o nome
e o comportamento dela ficam como estão. A V2 entra só onde acrescenta algo que ela não tem.

**Procedência de cada valor citado.** `[ALVO]` = medido em `produto-blade.jsx`, com linha.
`[V2]` = medido em `Consulta de Produtos.dc.html`. `[DS]` = citado do design system.
`[DECIDIR]` = não existe em nenhum dos dois; depende de você.

**Nada duplicado.** Toda absorção abaixo diz explicitamente se cria uma segunda via do mesmo
recurso e, quando cria, qual das duas morre. Item que duplicaria sem morte do par está marcado
**RECUSADO**.

**Listas.** Toda lista neste documento é **fechada** (é o conteúdo real do arquivo, não amostra),
salvo onde estiver escrito "ilustrativa".

**Proibições.** Não acrescentar item que não esteja aqui. Não renomear nada do alvo. Não substituir
componente do DS. Não completar lacuna com valor plausível — lacuna volta como pergunta.

---

## §1 Arquivos-alvo

| Papel | Arquivo | Onde aparece |
| --- | --- | --- |
| **ALVO da absorção** | `produto-blade.jsx` → `TelaLista` (L752) e `DetalheDrawer` (L437) | rota `prod-lista`, aba "Todos os produtos" |
| Fonte das melhorias | `Consulta de Produtos.dc.html` | rota `prod-consulta-v2` (provisória) |
| **NÃO é alvo** | `produtos-page.jsx` → `ProdListPage` | rota `produtos` — hub do módulo, visual Picker Mecânica |

Atenção: a comparação que eu te mandei antes no chat foi contra o `produtos-page.jsx` — tela
**errada**. Esta lista é contra o `produto-blade.jsx`, que é a "Todos os produtos" de verdade.

**Consequência da absorção:** quando ela terminar, a rota `prod-consulta-v2` e o item
"Consulta de Produtos (V2)" saem da sidebar (`data.jsx`), e o iframe sai do `app.jsx`. Duas telas de
consulta de produto no mesmo menu é a duplicação que este trabalho existe para acabar.

---

## §2 O que a V2 tem de melhoria — candidatos à absorção

Cada item: **está** (no alvo hoje) / **deve ficar** / **como conferir** / **duplica?**

### 2.1 KPI que filtra ao ser clicado

- **Está** `[ALVO L879]`: quatro placas de leitura, sem clique — Produtos cadastrados / Abaixo do
  alerta / Valor em estoque (custo) / Inativos. São números mortos: mostram "12 abaixo do alerta" e
  não há como ver quais são os 12.
- **Deve ficar:** as mesmas quatro placas, clicáveis, com anel de seleção `[DS KpiFilterCard]`.
  O clique escreve **no mesmo estado `f`** do widget Filtros — "Abaixo do alerta" liga o recorte de
  alerta, "Inativos" escreve `f.active = "inactive"` — e o chip correspondente aparece na faixa
  "Filtrando por".
- **Como conferir:** clicar "Inativos" → aparece o chip `Situação: Inativo`, o select Situação do
  widget mostra "Inativo", e a contagem da grade cai para o número da placa.
- **Duplica?** Não, **se** escrever no `f` existente. Se criar um filtro próprio ao lado, duplica —
  aí é RECUSADO. "Produtos cadastrados" e "Valor em estoque" seguem sem clique (não são recorte).
- **Falta decidir** `[DECIDIR]`: hoje não existe campo de filtro "abaixo do alerta" no widget. Ele
  passa a existir só como KPI, ou entra também como select?

### 2.2 O número diz que é subconjunto

- **Está** `[ALVO L340]`: coluna "Estoque atual" mostra `p.stock` + unidade, com classe `low` quando
  bate o alerta. Produto sem controle mostra `—`. Nada diz que esse número pode não ser vendável.
- **Deve ficar:** o número principal fica **igual**. Abaixo dele, em linha secundária, a ressalva
  quando ela existe: quantos estão em local bloqueado, ou que o total é soma das variações.
- **Como conferir:** produto com saldo em local não-vendável mostra o total na primeira linha e
  "X em local bloqueado" na segunda; produto sem essa condição não ganha segunda linha nenhuma.
- **Duplica?** Não.
- **Nome:** fica **"Estoque atual"** `[ALVO]`. A V2 chama "Disponível" — descartado por autoridade.
- **Falta decidir** `[DECIDIR]`: hoje `LOCATIONS` não tem natureza (venda / bloqueado). Sem esse
  campo no dado, a ressalva não tem de onde sair. Criar o campo é pré-requisito.

### 2.3 Custo da composição fechando com a soma

- **Está** `[ALVO L514]`: aba Composição lista Produto / SKU / Qtd / Compra (excl.) / Total (excl.)
  por linha. Não há linha de soma, e o custo do produto-pai vem de `variations[0].dpp`, digitado.
- **Deve ficar:** linha de fecho "Custo da composição (soma dos itens)" e o custo do pai **derivado**
  dessa soma.
- **Como conferir:** somar a coluna Total (excl.) na mão = o valor da linha de fecho = o "Preço de
  compra un." que a grade mostra para o mesmo produto. Três números iguais.
- **Duplica?** Não — substitui um número digitado por um derivado.
- **Risco declarado:** se hoje os dois divergem em algum produto de exemplo, a mudança vai expor a
  divergência. Isso é o objetivo, não um defeito.

### 2.4 Quantos dá para montar (composição)

- **Está** `[ALVO]`: nada. A aba Composição mostra o que o kit consome, não quanto dá para montar.
- **Deve ficar** `[V2]`: por item, "dá para montar N"; e o mínimo entre eles como o montável do kit.
- **Como conferir:** kit com item limitante de 3 unidades e consumo de 1 → o kit mostra "3", e o item
  limitante fica marcado como quem limita.
- **Duplica?** Não.

### 2.5 Permissões de custo, preço, composição e margem

- **Está** `[ALVO]`: nenhum gate. "Preço de compra un.", Composição e os totais de custo aparecem
  para qualquer usuário; o menu de colunas permite esconder, mas isso é preferência, não permissão.
- **Deve ficar** `[V2]`: as quatro permissões (`preco`, `custo`, `composicao`, `margem`), com
  `margem` exigindo `custo` — preço mais margem revelam o custo.
- **Como conferir:** usuário sem `custo` não vê a coluna "Preço de compra un." **nem** no menu de
  colunas, e a aba Composição mostra o bloco "Restrito ao administrador" em vez dos valores.
- **Duplica?** Não. O menu de colunas continua existindo, mas só oferece o que a permissão libera.
- **Falta decidir** `[DECIDIR]`: no protótipo, as permissões vêm de onde? Chave fixa no arquivo de
  dados, ou seletor visível para demonstração?

### 2.6 Consequência do estado, escrita

- **Está** `[ALVO L450]`: badge do drawer mostra a pílula "Inativo". O modal de desativar explica bem
  a consequência (`[ALVO L843]`: "sai da busca do PDV e dos orçamentos novos"), mas o drawer de um
  produto **já** inativo não repete isso.
- **Deve ficar** `[V2]`: faixa de aviso no topo do drawer do produto inativo, com a mesma frase que o
  modal já usa. Sem inventar texto novo — reusar a frase do L843.
- **Como conferir:** abrir um produto inativo → faixa âmbar no topo dizendo que ele não entra em
  venda nem orçamento novo, e que histórico e estoque seguem intactos.
- **Duplica?** Não. O mesmo vale para a flag "Não para venda", que hoje só aparece como texto no
  `sub` da coluna Produto.

### 2.7 Andar de produto em produto sem fechar o drawer

- **Está** `[ALVO]`: nada. Fechar → clicar na próxima linha.
- **Deve ficar** `[V2]`: ◂ ▸ no rodapé do drawer + posição ("12 de 240"), respeitando a ordem e os
  filtros da grade.
- **Como conferir:** filtrar por uma categoria, abrir o primeiro, apertar ▸ quatro vezes → só passa
  por produtos daquela categoria, e o contador acompanha.
- **Duplica?** Não.

### 2.8 Estado vazio que diz a causa

- **Está** `[ALVO L356]`: um `EmptyState variant="no-results"` só, com texto de filtro.
- **Deve ficar** `[V2]`: a variante muda com a causa — sem nenhum produto cadastrado (`first`),
  filtro que zerou (`no-results`), sem permissão (`no-perm`).
- **Como conferir:** limpar todos os filtros num catálogo vazio → texto de primeiro cadastro, não
  "remova um filtro".
- **Duplica?** Não.

### 2.9 Totais do recorte no rodapé

- **Está** `[ALVO L908]`: rodapé traz a contagem, a dica de ordenar/redimensionar e "Exportar
  filtrados". O valor em estoque aparece só no KPI, e é do catálogo inteiro, não do recorte.
- **Deve ficar** `[V2]`: totais do que está na grade agora, ao lado da contagem.
- **Como conferir:** filtrar uma categoria → o total do rodapé muda; o KPI do topo não muda.
- **Duplica?** **Sim, parcialmente** — "valor em estoque" passaria a existir em dois lugares com
  escopos diferentes. Só entra se cada um declarar o escopo ("catálogo" no KPI, "neste recorte" no
  rodapé). Sem isso, RECUSADO.

### 2.10 Paginação

- **Está** `[ALVO L269]`: sem paginação. A altura da grade é calculada (mín. 260px, máx. 72% do
  corpo) e a rolagem é interna, com header fixo.
- **Deve ficar:** **nada.** RECUSADO. A grade do alvo já resolve lista longa, e paginar por cima de
  rolagem interna cria dois modelos de navegação na mesma tabela.

### 2.11 Reposição e giro no drawer

- **Está** `[ALVO]`: nada de última venda nem de sugestão de reposição.
- **Deve ficar** `[DECIDIR]`: é conteúdo novo, não melhoria de forma. Só entra se você disser que
  entra, e o dado (última venda, pedido mínimo, prazo do fornecedor) precisa existir.

---

## §2-bis Usabilidade — o que ficou fora do §2 (levantado em 28/08)

Mesmo formato. Fechada: é a varredura de `Consulta de Produtos.dc.html` contra `produto-blade.jsx`,
não amostra.

### 2.12 KPI que não recorta não aparece

- **Está** `[ALVO L879]`: as quatro placas aparecem sempre, em qualquer aba e para qualquer papel.
- **Deve ficar** `[V2 L1004 `kpiVisivel`]`: KPI visível = KPI aplicável. "Abaixo do mínimo" e "Sem
  saldo" não aparecem na aba de serviço (serviço não tem saldo), e "Abaixo do mínimo" não aparece
  para quem não tem responsabilidade de reposição. E o recorte ativo cai junto (`aplica`, L1094):
  filtro cujo cartão saiu da tela viraria filtro fantasma.
- **Como conferir:** com o recorte "Abaixo do mínimo" ligado, trocar para uma aba onde ele não se
  aplica → o cartão desaparece **e** a grade volta a listar tudo daquela aba.
- **Duplica?** Não. Depende do §2.1 (KPI clicável) ter entrado.

### 2.13 O recorte sobrevive ao recarregar

- **Está** `[ALVO L764, L772-775]`: persiste **duas** coisas — colunas visíveis
  (`oimpresso.prod.cols`) e densidade (`oimpresso.prod.densa`). Filtros, aba, busca e ordem morrem
  no recarregamento.
- **Deve ficar** `[V2 L915-918]`: persiste também aba, KPI, busca, ordem, itens por página e os
  filtros — sob uma chave só, e com `catch` silencioso quando a cota estoura (L919).
- **Como conferir:** filtrar por categoria, ordenar por preço, recarregar → volta filtrado e
  ordenado, e a contagem do rodapé é a do recorte.
- **Duplica?** Não — amplia o que já é persistido, na mesma mecânica.
- **Falta decidir** `[DECIDIR]`: busca persistida é ajuda ou armadilha? Quem reabre a tela amanhã
  pode não notar o termo antigo no campo. A V2 persiste; posso deixar de fora.

### 2.14 Andar na grade pelo teclado

- **Está** `[ALVO L785, L970]`: `/` foca a busca, `n` abre o cadastro, `⌘K` abre a paleta. Não há
  navegação de linha.
- **Deve ficar** `[V2 L965-985]`: ↑/↓ movem a linha ativa (e **viram a página** quando a seta passa
  da borda da fatia), `Enter` abre o drawer da linha ativa, `Esc` desmarca. Com o drawer aberto, as
  mesmas setas andam de produto em produto (§2.7).
- **Como conferir:** sem tocar no mouse, `/` → digitar → `Esc` → ↓ ↓ → `Enter` abre o produto certo;
  ↓ na última linha da página passa para a próxima.
- **Duplica?** Não. Convive com `/`, `n` e `⌘K`, que ficam como estão.

### 2.15 Recentes na paleta

- **Está** `[ALVO L936]`: a `PaletaProduto` lista as 11 telas do módulo e 8 produtos **fixos** do
  mock.
- **Deve ficar** `[V2 L926, L1746]`: grupo "Recentes" com os 8 últimos produtos abertos, mais novo
  primeiro, sem repetir, persistido junto com o recorte.
- **Como conferir:** abrir três produtos, `⌘K` → os três aparecem em "Recentes", na ordem inversa da
  visita.
- **Duplica?** Não — acrescenta um grupo à paleta do alvo; a lista de telas segue igual.

### 2.16 Observação do produto, com condição declarada

- **Está** `[ALVO]`: nada. A flag "Não para venda" aparece como texto no `sub` da coluna Produto; não
  há campo de observação na lista nem no drawer.
- **Deve ficar** `[V2 L682-689]`: chip na linha para o item que tem nota (`Sob encomenda`,
  `Exige aprovação`) e o texto no drawer. Dois campos separados de propósito: `tag` é o rótulo,
  `critica` governa a cor e o `Alert` — condição declarada (sob encomenda) não é condição que trava a
  venda (exige aprovação).
- **Como conferir:** produto com nota crítica mostra chip e, no drawer, faixa de aviso; produto com
  nota comum mostra a nota sem faixa; produto sem nota não ganha chip nenhum.
- **Duplica?** Não.
- **Falta decidir** `[DECIDIR]`: no alvo a observação sai de qual campo? O `Product` do UltimatePOS
  tem `product_description`, que é texto livre com HTML — não é o mesmo que uma nota operacional.

### 2.17 Rótulo que muda quando o número muda de escopo

- **Está** `[ALVO L881]`: o KPI "Valor em estoque (custo)" já declara o escopo no `sub` — "só
  produtos com estoque gerenciado". Esse é o padrão certo, e ele já é do alvo.
- **Deve ficar** `[V2 L1616]`: o mesmo padrão aplicado ao drawer — "Valor em estoque" vira
  **"Valor em estoque (inclui reservado)"** quando há saldo reservado no item, e a linha
  "Reservado (não vende)" (L521) diz o quanto.
- **Como conferir:** produto com reserva → o rótulo do drawer traz "(inclui reservado)"; produto sem
  reserva → rótulo curto.
- **Duplica?** Não. Depende de §2.2 (natureza do local) existir no dado.

### 2.18 Alçada de desconto como campo, não como texto

- **Está** `[ALVO]`: nada sobre alçada.
- **Deve ficar** `[V2 L416]`: "Sem alçada de desconto: fechar fora do preço de tabela exige aprovação"
  — a frase descreve a **condição**; o percentual é campo calculado sobre o preço de tabela, nunca
  digitado na observação.
- **Como conferir:** trocar o papel do usuário → a frase muda de presença, e nenhum número de alçada
  aparece escrito em texto livre.
- **Duplica?** Não.
- **Falta decidir** `[DECIDIR]`: a alçada é do perfil do usuário ou do grupo de preço? Sem essa
  definição o campo não tem origem.

### 2.19 Exportar a seleção

- **Está** `[ALVO L908, L918, L1009]`: com itens marcados, a barra oferece Etiquetas (usa a seleção) e
  a tela oferece duas exportações que **ignoram** a seleção — "Baixar Excel" (catálogo) e "Exportar
  filtrados" (recorte).
- **Deve ficar** `[V2 L1676]`: "Exportar seleção" na barra, que exporta só os marcados e limpa a
  seleção depois.
- **Como conferir:** marcar 3 de 12, exportar pela barra → 3 linhas no arquivo, e a seleção sai.
- **Duplica?** Não — é o terceiro escopo, e §12 exige que os três se nomeiem.

### 2.20 A lista sabe dizer "erro" e "sem permissão"

- **Está** `[ALVO L1022]`: a prop `estado` existe na página e chega às sub-telas de importação e
  cadastros (L1030-1032), mas **não** a `TelaLista`. A lista só tem carregando e vazio-por-filtro.
- **Deve ficar** `[V2 L1785-1789]`: `error` ("Não foi possível carregar o catálogo · A consulta falhou
  no servidor. Tente novamente em alguns instantes.") e `no-perm` ("Você não tem acesso ao catálogo ·
  Peça ao administrador a permissão de consulta de produtos."), com o rodapé oculto (L1809).
- **Como conferir:** forçar `estado="erro"` → a grade dá lugar ao `EmptyState` de erro, sem rodapé e
  sem contagem; `estado="sem_permissao"` → texto de permissão, não de filtro.
- **Duplica?** Não — completa o §2.8, que só trata do vazio.

### 2.21 O atalho `/` também é anunciado

- **Está** `[ALVO css L100]`: o `/` aparece como `kbd` visual dentro do campo. Nada no DOM diz que o
  atalho existe.
- **Deve ficar** `[V2 L944-946]`: `aria-keyshortcuts="/"` e `aria-label="Buscar produtos"` no campo.
- **Como conferir:** inspecionar o campo → os dois atributos presentes; o `kbd` visual continua.
- **Duplica?** Não.
- **Junto disso** `[V2 L654]`: o espaço reservado da foto ganha `role="img"` +
  `aria-label="Produto sem imagem"`, em vez do texto "IMG" do `.pb-thumb` (css L69), que é lido como
  conteúdo.

---

## §3 O que a V2 tem e **não** deve ser absorvido

Fechada. Cada linha é uma duplicação evitada — o alvo já resolve.

| Recurso da V2 | O alvo já tem | Decisão |
| --- | --- | --- |
| Abas por tipo (Todos/Produtos/Serviços/Matéria-prima/Kits/Inativos) | Select "Tipo de produto" + "Situação" no widget Filtros; as abas são Lista / Relatório de estoque | Fica o do alvo |
| Filtros como dropdown na toolbar | Widget "Filtros" recolhível, 7 selects + 1 checkbox | Fica o do alvo |
| Coluna "Código" | Coluna "SKU" | **Superado pela decisão de 28/08** — vira "Referência" + "Código" |
| Coluna "Disponível" | "Estoque atual" | Fica "Estoque atual" |
| Busca "descrição, código, referência" | "Buscar nome ou SKU…" + `/` | Fica o do alvo |
| Paleta ⌘K própria | `PaletaProduto` — 11 telas do módulo + 8 produtos | Fica o do alvo |
| Seleção múltipla + barra de ações | Barra com 6 ações, inclusive Adicionar/Remover do local | Fica o do alvo |
| Skeleton e estado de carregando | Skeleton do DS, 420ms | Fica o do alvo |
| Densidade por tokens do shell | Segmented Confortável/Compacto, persistido | Fica o do alvo |
| Toast de aviso | `avisar()` do módulo | Fica o do alvo |
| Chip de filtro ativo + "Limpar tudo" | Faixa "Filtrando por" com `FilterChip` + "Limpar tudo" (L889-893) | Fica o do alvo |
| Sidebar própria | Sidebar do shell | Já resolvido (`?embed=1`) |
| Confirmação em modal | `ModalConfirmar`, com aviso de recusa do servidor | Fica o do alvo |

---

## §4 Nomenclatura — um termo por conceito

Fechada.

**Decisão de 28/08 (você):** quatro conceitos foram resolvidos escolhendo entre o léxico do alvo e
o da tela React `/products/unificado`. Três vão para o léxico do repo; um fica com o do alvo. Os
seis conceitos restantes seguem com o termo do alvo, por autoridade, e ninguém os reabriu.

| Conceito | Fica | De onde vem | O que muda no alvo |
| --- | --- | --- | --- |
| Identificador do item | **Referência** (SKU cadastrado) + **Código** (id interno) | React `catalogo.ts` L43-46 | renomear a coluna "SKU" (L317), o item do menu de colunas (L260), a linha do drawer (L474), o placeholder da busca e os cabeçalhos do Relatório de estoque (L402) e das Variações (L491) |
| Quantidade em estoque | **Estoque atual** | alvo (L312, L403) | nada |
| Limite que dispara reposição | **Mínimo** ("Abaixo do mínimo") | React `estadoEstoque` | renomear o KPI "ABAIXO DO ALERTA" e a linha "Quantidade de alerta" do drawer (L477) |
| Estado do saldo | **Disponível / Abaixo do mínimo / Sem saldo / Não estocável** | React `estadoEstoque` | conceito **novo** no alvo, que hoje só tem o número com classe `low` |

⚠️ **Choque de palavra, a resolver antes de implementar** `[DECIDIR]`: "Disponível" já existe no
alvo com outro sentido — `Disponível nos locais` (L478) é a **lista de locais** onde o produto é
vendável, não quantidade. Com o estado do saldo chamado "Disponível", a mesma palavra passa a
significar duas coisas na mesma tela. Sugestão: a linha do drawer vira **"Vendável nos locais"**.
Não implementado.

Os seis que seguem com o termo do alvo:

| Conceito | Fica `[ALVO]` | Descartado `[V2]` |
| --- | --- | --- |
| Produto formado por outros | **Composição** | Kit, BOM |
| Desdobramento do produto | **Variações** | Grade, Combinação |
| Onde o saldo está | **Local do negócio** | Local |
| Endereço físico dentro do local | **Prateleira** (aba própria) | — |
| Custo de aquisição | **Preço de compra un.** | Custo |
| Vigência do cadastro | **Situação** (Ativo / Inativo) | Vigência |
| Bloqueio de venda sem inativar | **Não para venda** | — |

Nota: "Não para venda" e "Inativo" são coisas diferentes no alvo `[ALVO L791]` e precisam continuar
diferentes na tela absorvida.

---

## §5 Ligações — verificado

Você disse que as ligações do alvo já estão certas. Verifiquei uma por uma. As internas estão;
achei quatro divergências e uma ausência.

**Está certo** (fechada, `[ALVO L977-985]`):

- Comprar → módulo **Compras**, ação `novo-item`
- Usar em uma OS → módulo **OS**, ação `novo-item`
- Dados fiscais → módulo **Fiscal**, ação `produto`
- Editar / Histórico / Preços por grupo / Edição em massa / Etiquetas / Análises / Atualizar preço /
  Importar produtos / Importar estoque / Cadastros de apoio → as 11 rotas `prod-*`, e o
  `window.__selectRoute` sincroniza a sidebar (a sidebar nunca marca uma tela e mostra outra)

**Divergente ou faltando:**

1. **Não existe "Usar em orçamento" nem "Usar no PDV".** `[ALVO]` O produto vai para OS
   (`os`, novo-item), e o modal de desativar fala em "orçamentos novos" — mas nenhuma ação da tela
   leva a Orçamentos ou ao PDV, que existem na sidebar (`orcamentos`, `venda-pdv`). Se o orçamento é
   onde se calcula pedido, essa é a ligação que falta. `[DECIDIR]`
2. **"Transferir entre locais" vai para Compras.** `[ALVO L979]` O destino é `compras`, ação
   `transferencia`. Mas o texto da própria aba Estoque diz "a transferência é lançada no módulo
   **Estoque**" `[ALVO L550]`, e o módulo Estoque existe. Texto e destino se contradizem — um dos
   dois está errado. `[DECIDIR]`
3. **"Gerar OP" da composição vai para Comunicação Visual.** `[ALVO L980]` Destino `cv`, ação
   `nova-op`. A sidebar tem **Fabricação** com "Ordens de produção" (`mfg-producao`). Composição
   genérica gera OP em CV ou em Fabricação? `[DECIDIR]`
4. **A V2 não navega — avisa.** `[V2 L1875]` "Abrir cadastro" e "Formar preço" só disparam um toast.
   Aqui a V2 está **atrás** do alvo, que navega de verdade. Nada a absorver; registrado para não ser
   confundido com melhoria.

---

## §6 O que sai do projeto quando a absorção terminar

1. Item "Consulta de Produtos (V2)" da sidebar (`data.jsx`) e a rota `prod-consulta-v2` (`app.jsx`).
2. O `<iframe>` — que é um contorno declarado, e o `app.jsx` diz na L69 que este shell não usa mais
   iframes.
3. ~~O contorno `_ds/office-impresso-design-system-019dd02f…/`~~ — **feito em 21/09/2026**: as três
   pastas de DS viraram uma (`_ds/wagner-office-impresso-design-system-49a36f76-2672-43f6-b955-c6cbb52f7f86/`) e o shim de alias foi para o `oimpresso.com.html`.
4. `Consulta de Produtos.dc.html` — **não apagar** sem sua ordem: é o protótipo oficial de onde as
   melhorias saíram, e serve de referência durante a implementação.

---

## §7 Pendências que só você decide

1. **§2.1** — "abaixo do alerta" entra também como select no widget Filtros, ou só como KPI clicável?
2. **§2.2** — criar natureza do local (venda / bloqueado) no dado do protótipo?
3. **§2.5** — as permissões vêm de chave fixa ou de um seletor visível para demonstração?
4. **§2.9** — os dois "valor em estoque" (catálogo × recorte) podem coexistir com escopo declarado?
5. **§2.11** — Reposição e Giro entram, ou ficam fora desta onda?
6. **§5.1** — a ligação para Orçamento/PDV entra? Com qual rótulo?
7. **§5.2** — Transferir vai para Compras ou para Estoque?
8. **§5.3** — OP de composição sai em Comunicação Visual ou em Fabricação?
9. **Ordem de execução** — rodo tudo o que você marcar de uma vez, ou em ondas (forma primeiro,
   dado depois)?
10. **§4** — ✅ **decidido em 28/08:** Referência+Código, Estoque atual, Mínimo, e os quatro estados
    de saldo. Sobra um `[DECIDIR]` dentro dela: a linha `Disponível nos locais` do drawer (L478) vira
    "Vendável nos locais" para liberar a palavra "Disponível"?
11. **§2-bis** — sete itens novos de usabilidade (2.12 a 2.18), cada um com o seu SIM / NÃO. Três
    trazem `[DECIDIR]` próprio: busca persistida (2.13), origem da observação (2.16) e origem da
    alçada de desconto (2.18).

---

## §17 Dívida 0b — tokens de espaçamento e rampa (adiada em 28/08, decisão da Maiara)

**O que está adiado:** os itens 4.1, 4.2 e 4.3 do §10 — trocar os espaçamentos escritos em pixel
(`.pb-body 16px 20px 28px`, `.pb-toolbar/.pb-chips 8px 12px`, `.pb-pag 9px 12px`) pelas variáveis
`--d-cpad-x` / `--d-cpad-y` / `--d-tb-y`, fazer o segmented de densidade escrever esses tokens no
shell em vez de ligar a classe `.pb-dense`, e passar os tamanhos de fonte fixos para a rampa
`--fs-1..9`.

**Por que adiou:** o `produto-blade.css` serve **11 telas** do módulo Produto. A mudança altera as 11
juntas, não tem ganho visível na lista, e entraria misturada com a absorção — se uma tela de cadastro
quebrasse, não haveria como saber qual mudança quebrou.

**Condições combinadas:**

1. A 0b roda **sozinha**, numa rodada em que nada mais está mexendo no módulo.
2. Vale a partir de agora: **todo CSS novo que eu escrever no módulo já usa as variáveis** — assim a
   fila da 0b não cresce. As duas ou três linhas da faixa de totais do rodapé (item 2.9) entram
   assim.

**Defeito que fica aberto até ela rodar, e a usuária vê:** o segmented **Compacto** promete adensar a
tela e adensa **só a tabela** — a moldura em volta (corpo, toolbar, chips, rodapé) continua com o
espaçamento largo, porque esses valores estão em pixel e a classe `.pb-dense` só reescreve parte
deles. Não é pureza de design system: é botão que entrega metade do que promete.

**Reabrir quando:** o Wagner puder olhar as 11 telas do módulo na mesma passada.

---

## §21 Onda 0e — controles do widget Filtros pelo DS (autorizada em 28/08, a rodar)

**4.12 · Filtros.** Levantado pela Maiara em 28/08: *"os filtros da tela estão de acordo com o
ds/template?"*. Medido: não. O widget "Filtros" fica (o §3 já decidiu, por autoridade da tela), mas
os **controles** dentro dele são HTML cru com CSS da tela — sete `<select>` e um
`<input type="checkbox">` estilizados por `.pb-fld` (`produto-blade.css` L24-38) — quando o DS tem
`Select` (`_ds_bundle.js` L4546) e `Checkbox` (L2511) no bundle já carregado. É o mesmo caso do
`.pb-kpi` da onda 0c: componente do DS recriado na tela.

**O que muda:** os oito controles passam a ser `Select` e `Checkbox` do DS. **O que não muda:** o
widget recolhível, o título "Filtros", a grade de quatro colunas, os rótulos, a ordem dos campos e o
botão de limpar. O `.pb-fld` continua servindo os formulários do módulo (cadastro, edição em massa),
que não são desta onda.

**Como conferir:** abrir o widget → cada campo tem o rótulo em caixa alta, a altura e o anel de foco
do DS; nenhum `<select>` sem componente sobra na região de filtros.

**Item vizinho, não incluído:** o campo de busca da toolbar. O `Input` do DS não tem slot de ícone
(já é proposta P1 na pauta), e é por isso que a busca é desenhada à mão aqui e no template. Fica como
está.

---

## §20 Onda 0d — abas pelo `TabBar` do DS, aplicada em 28/08

Levantado pela Maiara ao olhar a tela; **o §10 não tinha esses dois itens** — buraco da minha
auditoria, que olhou KPI, padding, rampa, raio, cursor e tabelas, e não olhou cabeçalho nem abas.

**4.11 · Abas (feito).** Está agora: `[DS]` `TabBar` (`_ds_bundle.js` L6605), `tabs`/`active`/
`onChange`, com o contador de "Todos os produtos" preservado. Estava: `nav.cli-moduletopnav` com dois
botões escritos à mão, estilizados por `clientes-page.css` L70-82 — imitação da `TabBar`, não a
`TabBar`. **Nada mais saiu da tela:** o invólucro mantém `padding:0 12px` e o
`data-contract="produto-abas"`, e o `nav` antigo fica como plano B enquanto o bundle (`defer`) não
chegou.

Três consequências declaradas:

1. O `aria-label` do `nav` passa a ser **"Sub-navegação"** (o componente escreve o dele; o antigo
   dizia "Abas do índice"). É valor do DS — citado, não escolhido.
2. O sublinhado ativo passa a usar `--accent` (o componente, L6645). Fica registrado que `--accent` é
   reescrito em runtime pelo shell — já é defeito aberto na pauta, não algo desta tela.
3. Risco a conferir no olho: a `TabBar` tem defeito conhecido de 1px de barra de rolagem vertical
   (pauta, ADR 0403). **Não** apliquei o contorno CSS (`nav[aria-label="Sub-navegação"]
   { overflow-y: hidden }`) porque você pediu para não acrescentar nem remover mais nada. Se aparecer,
   é uma linha.

**Não tocado, declarado:** o `nav.cli-moduletopnav` **dentro do drawer** (L466, abas do detalhe do
produto) continua como estava. Mesmo caso, tela diferente do índice — decidir depois.

**4.10 · Cabeçalho (não feito, virou pergunta).** A tela monta `M.Header` (`modulo-padrao.jsx` L18),
não o `PageHeader` do DS. O mesmo `M.Header` serve **nove** telas do protótipo, então trocar só aqui
criaria duas gramáticas de cabeçalho no shell. A pergunta está registrada em
`pauta-design-system.md` → "PERGUNTA AO SHELL — o cabeçalho de módulo do protótipo não é o
`PageHeader` do DS", com as três saídas possíveis. Nada alterado até a decisão vir.

---

## §19 Onda 0c — aplicada em 28/08

Feito em `produto-blade.jsx`. **As quatro placas passam a ser componente do DS** e o `.pb-kpi` local
sai de cena.

| Está agora | Estava | Como conferir |
| --- | --- | --- |
| Quatro `KpiCard` do DS (`_ds_bundle.js` L4650), lidos de `DS()` como os outros primitivos | quatro `<div class="pb-kpi">` com CSS local — raio 10px, padding 10/12, valor 20px (css L176-182) | inspecionar uma placa: raio 8, `padding 12`, `box-shadow 0 1px 2px rgba(0,0,0,.05)` — valores do componente, nenhum da tela |
| Rótulos, sub-linhas e tons **iguais aos de antes** — "Abaixo do alerta" fica `tone="danger"` quando há item, "Inativos" fica `tone="warning"` | idem, via classes `.neg` / `.warn` | ler as quatro placas: mesmo texto, mesma cor de alarme |
| `.pb-kpis` (grade responsiva: 4 → 2 colunas em 1180px, 2 em 900px) **mantido** | idem | estreitar a janela: as placas caem para duas colunas, como antes |
| `.pb-kpi` local sobra só como plano B enquanto o bundle não carregou (o script é `defer`) | era o caminho único | com o bundle carregado, nenhum `.pb-kpi` no DOM |

**Decisão tomada dentro da onda, e ela contraria o que eu tinha escrito no §R 2.1:** usei `KpiCard`
(leitura) nas quatro, **não** `KpiFilterCard`. Motivo medido: o `KpiFilterCard` é um
`<button aria-pressed>` com `cursor:pointer` (`_ds_bundle.js` L4873-4879) — montá-lo agora, sem
`onClick`, entregaria quatro botões mortos, dois deles para sempre (os que não recortam). Então o
`KpiFilterCard` entra no **item 2.1**, e só nas duas placas que viram filtro: "Abaixo do alerta" e
"Inativos". As outras duas seguem `KpiCard` — é a resposta do próprio DS para "placa que só informa".

**Não conferido por screenshot:** o shell `oimpresso.com.html` transpila o módulo inteiro no
navegador e a captura automática estoura o tempo. O console subiu **sem erro** (só os avisos de
sempre do Tailwind CDN e do Babel). Conferência visual das quatro placas fica com você.

---

## §18 Onda 0 — aplicada em 28/08

Feito em `produto-blade.css`. Diff, para o Luiz conferir sem ler o código:

| Item | Está agora | Estava | Como conferir |
| --- | --- | --- | --- |
| 4.4 piso tipográfico | `var(--fs-1)` (10,5px) em 5 seletores: `.pb-tbl thead th` · `.pb-kpi small` · `.pb-chips-l` · `.pb-chip span` · `.pb-dt span` | `10px` cravado | inspecionar o cabeçalho da tabela: 10,5px, e o valor vem da variável |
| 4.6 moldura do card | `.pb-widget{ border-radius:var(--radius-lg); box-shadow:var(--shadow-soft) }` · `.pb-tblwrap{ border-radius:0 0 var(--radius-lg) var(--radius-lg) }` | raio `12px` cravado, **sem sombra** | o card ganha a sombra de 1px do sistema; o raio **não muda de tamanho** (ver errata abaixo) |
| 4.9 cursor | `cursor:pointer` em 7 seletores: `.pb-chip` · `.pb-seg button` · `.pb-chk` · `.pb-sortth` · `.pb-tag button` · `.pb-menu button` · `.pb-uso` | `cursor:default` | passar o mouse em chip, segmented, item de menu e contagem clicável → cursor de mão |

**Errata da minha auditoria, medida ao aplicar** (o §10 fica como estava; a correção é esta):

1. **4.6 era menos do que eu disse.** Dentro do `.cockpit`, `--radius-lg` **é 12px**
   (`colors_and_type.css` L301) — o mesmo número que estava cravado. Então o raio não muda de
   aparência: a mudança é de **mecanismo**. O que muda de verdade é a sombra, e ela também tem token:
   `--shadow-soft: 0 1px 2px rgba(0,0,0,.04)` (L304) — exatamente o valor que o `[TPL]` L75 escreve
   literal. Usei o token; o template é que está escrevendo o literal.
2. **4.4 eram 5 lugares, não 2.** Eu contei 2 (cabeçalho de tabela e rótulo de KPI); a varredura ao
   editar achou 5. Todos corrigidos.
3. **Abaixo do piso, ainda em aberto** — medido agora, **não** corrigido, porque não estava declarado:
   `.pb-busca kbd` 9px · `.pb-thumb` (texto "IMG") 9px · `.pb-sortic` (seta de ordenar) 8,5px ·
   `.pb-dense .pb-thumb` 8px. `[DECIDIR]`: subir `kbd` para 10,5px alarga o campo de busca; o texto
   "IMG" do `.pb-thumb` **desaparece** quando o item 2.21 entrar (vira `role="img"`); o `.pb-sortic` é
   de um cabeçalho já aposentado. Recomendo tratar os quatro junto do 2.21, não agora.
4. **Fora do escopo declarado, não mexi:** `.pb-modal` (raio 12px cravado, L186) e `.pb-img`
   (raio 10px, L198). Mesmo caso do 4.6; entram na 0b ou numa varredura própria de raio.

---

## §R Recomendação item a item (28/08) — minha leitura, sua decisão

Coluna **Rec.**: `SIM` absorver como está · `SIM+` absorver com a condição escrita · `DADO` só depois
de o campo existir · `NÃO` não absorver nesta onda. A consequência é o que muda **fora** do item.

### R1 · Absorção (§2 · §2-bis · 2.19-2.21)

| # | Item | Rec. | Condição e consequência |
| --- | --- | --- | --- |
| 2.1 | KPI que filtra ao clicar | **SIM+** | Os quatro KPIs continuam sendo **os do alvo** (cadastrados · abaixo do alerta · valor em estoque · inativos) — não os quatro da V2. Só dois recortam; os outros dois precisam **parecer** leitura (sem anel, sem cursor de clique), senão treinam clique que não faz nada. Escreve no `f` existente. Resolve 4.5 de graça: entra `KpiFilterCard` do DS e sai o `.pb-kpi` local — **dentro do grid responsivo do alvo**, não no `repeat(4)` fixo da V2 (§15). |
| 2.2 | O número diz que é subconjunto | **DADO** | Exige natureza do local (§11). Nome fica "Estoque atual" (§4). Depois dele vêm 2.17 e o "+X reservado" da linha. Sem o campo, é adivinhação exibida como fato. |
| 2.3 | Custo da composição = soma | **SIM** | Vai **expor** divergência nos produtos de exemplo — é o objetivo. Junto de 2.5: a soma revela custo, então a linha de fecho só aparece com `view_purchase_price` **e** composição. |
| 2.4 | Quantos dá para montar | **SIM** | Derivado (mín. saldo ÷ qtd), nada digitado. Risco baixo. Herda o gate de composição. |
| 2.5 | Permissões | **SIM+** | Usar os nomes do legado: `view_purchase_price` e `access_default_selling_price` (§13). `composicao` não existe no legado — recomendo **herdar de `view_purchase_price`** até você decidir, e declarar isso na tela. `margem` exige custo. Consequência obrigatória: o CSV cai no mesmo gate (§12), senão a exportação contorna a permissão em lote. |
| 2.6 | Consequência do estado, escrita | **SIM** | Reusar **a frase do L843**, sem texto novo. `Alert` do DS tone âmbar, não faixa desenhada na tela. |
| 2.7 | ◂ ▸ no drawer | **SIM** | Respeita ordem e filtros da grade. Um mecanismo só com 2.14 (as setas fazem as duas coisas conforme o drawer esteja aberto ou não) — implementar separado daria dois. |
| 2.8 | Vazio que diz a causa | **SIM** | Implementar **junto de 2.20**: é o mesmo `EmptyState`, um `variant` por causa. Separar cria dois caminhos para o mesmo componente. |
| 2.9 | Totais do recorte no rodapé | **SIM+** | Só com escopo no rótulo nos dois lugares — "catálogo" no KPI, "neste recorte" no rodapé. Sem isso, **NÃO**. Herda o gate de custo. |
| 2.10 | Paginação | **NÃO** | Mantida a recusa. Consequência a registrar como dívida: num catálogo grande não há caminho para a linha 5.001 — o alvo rola, mas rolar 5.000 linhas não é caminho. Dívida aberta, não item. |
| 2.11 | Reposição e giro | **NÃO** | Conteúdo novo + dado que não existe (pedido mínimo, prazo, última venda). Depois de 2.2 e do dado de giro, volta como onda própria. |
| 2.12 | KPI que não recorta não aparece | **SIM+** | **Adaptar:** a regra da V2 fala de abas por tipo, que o alvo não tem. No alvo, o gatilho é o select "Tipo de produto" e o papel — "Abaixo do alerta" sai para quem não repõe; com Tipo = Serviço, saem os de saldo. Depende de 2.1. |
| 2.13 | Recorte sobrevive ao recarregar | **SIM+** | Persistir aba, filtros, ordem e itens por página; **não persistir a busca** — termo antigo no campo, um dia depois, faz a pessoa ler a lista errada como se fosse o catálogo. Mesma chave do `oimpresso.prod.*` que já existe. |
| 2.14 | Teclado na grade | **SIM+** | O `DataTablePro` não aceita `state:'selected'` controlado nem ordenação controlada — a V2 contorna por fora e isso já é proposta P1 na pauta. Absorver **com o contorno declarado**, não fingindo que o componente faz. |
| 2.15 | Recentes na paleta | **SIM** | Persistido junto de 2.13. A lista de 11 telas da paleta do alvo fica intacta; entra um grupo. |
| 2.16 | Observação com criticidade | **DADO** | `product_description` (texto livre com HTML) **não serve** — nota operacional é outro campo. Recomendo `observacao_operacional` + `critica` no dado do protótipo. Sem isso, o chip não tem origem. |
| 2.17 | Rótulo que declara escopo | **DADO** | Mesmo dado de 2.2. Entra na mesma onda. |
| 2.18 | Alçada de desconto | **NÃO** | Duas razões: a origem do percentual não está definida (perfil? grupo de preço?) e **quem calcula pedido é Orçamento/PDV**, não a consulta. A consulta pode **exibir** o limite depois que a origem existir — não nesta onda. |
| 2.19 | Exportar seleção | **SIM+** | Junto: renomear os dois existentes para declarar escopo ("Baixar catálogo (Excel)" e "Exportar este recorte"). Isso **muda rótulo do alvo**, então precisa da sua autorização explícita — o §0 me proíbe renomear por conta. |
| 2.20 | Erro e sem permissão na lista | **SIM** | Passar `estado` a `TelaLista` (hoje para em L1022). Junto de 2.8. |
| 2.21 | Atalho `/` anunciado + foto com `role="img"` | **SIM** | Custo perto de zero, nenhum efeito visual. |

**Ordem que eu recomendo — revisada em 28/08: fidelidade primeiro, para os itens de moldura.**

- **Onda 0 · fidelidade cirúrgica** — 4.4 (piso 10px), 4.6 (raio e sombra), 4.9 (`cursor`). Um seletor
  cada, nenhum efeito em outra tela.
- **Onda 0b · tokens do shell** — 4.1 + 4.2 + 4.3 juntos, com passada visual nas **11 telas** que o
  `produto-blade.css` serve. É a onda de maior raio e a única sem ganho visível para quem assiste — o
  ganho é que tudo depois nasce certo. **Não** é reescrever a tela sobre o PT-01: correção pontual.
  (O `min-height:100vh` do shell do PT-01 só funciona porque ele passa `height` ao `DataTablePro`;
  copiado para tela com `DataTable`, o cabeçalho fixo sai da tela sem erro no console.)
- **Onda 0c · componente** — 4.5: entra o `KpiFilterCard` do DS **sem** clique ainda, dentro do grid
  responsivo do alvo. Depois disso, o 2.1 é só `onClick` + `selected`.
- **Onda 1 · lógica, em paralelo à 0b** (não toca moldura, não espera): 2.21 · 2.20+2.8 · 2.15 · 2.6 ·
  2.5 + gate do CSV · 2.3 · 2.4.
- **Onda 2 · sobre a moldura já correta:** 2.1 · 2.12 · 2.9 · 2.19.
- **Onda 3 · teclado e recorte:** 2.14 · 2.7 · 2.13.
- **Onda 4 · dado** (a única que muda a **forma** do dado, §11): 2.2 · 2.17 · 2.16.

A ordem anterior punha a absorção antes da fidelidade. Substituída: é mais fácil pela moldura para
2.1, 2.9, 2.19 e 2.20; e não é para os sete da onda 1, que não têm dependência de moldura.

### R2 · Fidelidade ao template e ao DS (§10)

| # | Rec. | Consequência |
| --- | --- | --- |
| 4.1 padding em px | **corrigir, onda própria** | O `produto-blade.css` serve **11 telas** do módulo, não só a lista. Trocar `16px 20px` por `var(--d-cpad-*)` mexe em todas — por isso onda própria, com uma passada visual nas 11. |
| 4.2 densidade por classe | **corrigir junto de 4.1** | O segmented passa a escrever tokens no shell e `.pb-dense` sai. É o mecanismo do template (manual item 5), não preferência. |
| 4.3 rampa `--fs-*` | **corrigir junto de 4.1** | Mesma onda: sem a rampa, 4.1 e 4.2 entregam metade. |
| 4.4 piso 10px | **corrigir já** | Dois seletores, 0,5px. Independe de tudo. |
| 4.5 KPI local | **corrigir com 2.1** | Não é item separado: entra `KpiFilterCard` quando o KPI virar filtro. |
| 4.6 raio 12px sem sombra | **corrigir já** | `var(--radius-lg)` + `box-shadow:0 1px 2px rgba(0,0,0,.04)` do `[TPL]` L75. Um seletor. |
| 4.7 tabelas secundárias | **NÃO agora** | Relatório de estoque e tabelas do drawer. Risco alto (é muita tabela) e ganho baixo — a lista principal já usa a grade do DS. Dívida declarada. |
| 4.8 rodapé de paginação | **nada** | Decidido em 2.10. |
| 4.9 `cursor:default` | **corrigir já** | Quatro seletores. Hoje quatro controles clicáveis dizem "não sou clicável". |

### R3 · Pendências do §7 — o que eu recomendo (a decisão é sua)

1. "Abaixo do alerta" **só como KPI**, não como select — o widget já tem 7 selects e o oitavo paga menos que o KPI clicável.
2. **Criar a natureza do local** (`venda` / `bloqueado`): é pré-requisito de 3 itens e da regra "quando o número é subconjunto, a linha declara".
3. Permissões por **seletor visível** de papel — o alvo já tem `papel` e 4 papéis prontos (`produto-perms.jsx`); serve para demonstrar em reunião.
4. Os dois "valor em estoque" **podem coexistir**, com escopo no rótulo. É o padrão que o próprio alvo já usa no KPI.
5. Reposição e giro **fora** desta onda.
6. **Entra "Usar em orçamento"** — o modal do alvo já fala em "orçamentos novos", e é lá que se calcula pedido. Rótulo: "Usar em orçamento".
7. Transferir vai para **Estoque** — é o que o texto da aba Estoque do próprio alvo promete (L550); Compras é compra de terceiro.
8. OP de composição sai em **Fabricação** — CV é o módulo de um tipo de produto, Fabricação é o genérico.
9. Ondas, na ordem do R1 — não tudo de uma vez.
10. `Disponível nos locais` → **"Vendável nos locais"**, para a palavra "Disponível" ficar livre para o estado do saldo.
11. Marcar o §2-bis item a item; se preferir, aceito o R1 inteiro como pacote e você veta linha a linha.

---

## §14 Eixo D — estados da tela e dados extremos (28/08)

| Estado | `[ALVO]` | `[V2]` |
| --- | --- | --- |
| Carregando | Skeleton do DS, `variant="row" count={8}`, 420ms (L777, L899) | `carregando` por prop (L1782), Skeleton do DS |
| Vazio por filtro | `EmptyState variant="no-results"` com texto que manda remover chip (L356-359) | `vazioVariante` — muda com a causa (L160) |
| Catálogo vazio (primeiro cadastro) | **não existe** — cai no mesmo `no-results` | `first` |
| Erro do servidor | **não existe** | `variant="error"`, "Não foi possível carregar o catálogo · A consulta falhou no servidor…" (L1785-1789) |
| Sem permissão | **não existe** na lista | `variant="no-perm"`, "Peça ao administrador a permissão de consulta de produtos." |
| Rodapé em tela sem dados | segue montado | `mostraRodape` só com dados e linhas (L1809) |

**Achado de mecanismo:** o alvo **tem** a prop `estado` (L956) e a repassa para as sub-telas de
importação e cadastros (L1030-1032), mas **não** para `TelaLista` (L1022) — a lista é a única tela do
módulo que não sabe representar erro nem falta de permissão. Isso amplia o §2.8, que só falava de
vazio. Virou item **2.20**.

**Dados extremos: nenhuma das duas foi testada.** Declarado como lacuna, não como divergência —
nome de 120 caracteres, saldo negativo (devolução lançada antes da entrada), unidade fracionária
(`0,375 m²`), catálogo de 5.000 itens. O alvo não pagina e calcula a altura em 72% do corpo; a V2
pagina de 10 em 10 no cliente. Nenhum dos dois foi exercitado nesses limites.

---

## §15 Eixo E — janela curta e toque (28/08)

**Aqui a V2 está atrás, e nada deve ser absorvido.** Registrado para não ser confundido com melhoria,
como o §5.4.

| | `[ALVO]` | `[V2]` |
| --- | --- | --- |
| Toque | `@media (pointer:coarse)`: campos e segmented a 44px, checkbox 22px, botões `min-height:44px`, chip 36px (css L127-136) | **nada** |
| Largura | breakpoints 1180px (KPI e grid caem para 2 colunas) e 900px (1 coluna, busca 100%) (css L138-143, L250) | **nenhuma media query**; faixa de KPI fixa em `repeat(4, minmax(0,1fr))` |
| Altura curta | piso na região de dados + `overflow:auto` no shell (css L3-4) | rolagem da página, header fixo |

**Consequência prática:** absorver a faixa de KPI da V2 como está (§2.1) **perderia** o breakpoint de
2 colunas e os alvos de 44px do alvo. O `KpiFilterCard` do DS entra, mas dentro do grid responsivo do
alvo — não com o `repeat(4)` fixo da V2. Escrito aqui porque é exatamente o tipo de detalhe que um
agente "completa" errado.

`[DECIDIR]`: a tela absorvida mantém o contrato de toque de 44px (tablet do técnico)? Ele existe no
alvo hoje e a V2 declara desktop.

---

## §16 Eixo F — acessibilidade além do foco (28/08)

Cada tela tem o que a outra não tem. Fechada.

**Só o alvo tem:** `role="menu"`/`menuitem` no menu de ações (L196-199) · `role="dialog"` +
`aria-label` no modal próprio (L218) · `nav aria-label` nas duas barras de abas (L466, L867) ·
`role="group" aria-label="Densidade da tabela"` no segmented (L879) · `aria-hidden` na arte do código
de barras (L684) · `aria-label="Dispensar"` no ✕ da faixa de contexto (L1020).

**Só a V2 tem** — candidatos:

- **`aria-keyshortcuts="/"` + `aria-label="Buscar produtos"`** no campo de busca (L944-946). No alvo o
  `/` é só um `kbd` visual (css L100): quem usa leitor de tela não sabe que o atalho existe. Virou
  item **2.21**.
- **Nome acessível no Drawer do DS** (L901-907): o `Drawer` deriva o `aria-label` da prop `title`, que
  a V2 não passa — então ela escreve o `aria-label` na mão. O alvo passa `title`, então **já está
  resolvido lá**. Nada a absorver; registrado para ninguém "corrigir" o alvo.
- **`role="img"` + `aria-label="Produto sem imagem"`** no espaço reservado da foto (L654), contra o
  `.pb-thumb` do alvo, que é um `div` com o texto "IMG" (css L69) — lido como conteúdo.
- **`role="heading" aria-level="5"`** nos sub-títulos do drawer (L443, L554), onde o alvo usa `<h3>`
  dentro de seção — o alvo está **correto** e mais simples aqui.

**Não medido em nenhuma das duas** (não afirmo, não proponho): `aria-live` no toast (o alvo usa
`M.useAviso` do módulo; a V2 usa o `Toast` do DS — nenhum dos dois foi inspecionado), ordem de
tabulação completa, e contraste medido dos badges em tema escuro.

---

## §11 Eixo A — modelo de dados, campo a campo (28/08)

Fechada. `[ALVO]` = `produto-blade.jsx` L53-79 · `[V2]` = `Consulta de Produtos.dc.html` L605-735.

**Só o alvo tem** (a V2 não conhece, e nenhum item de absorção depende): `sub` (subcategoria),
`tax` + `taxType` (imposto e inclusivo/exclusivo), `barcode`, `weight`, `expiry` + `expiryType`,
`srNo` (IMEI/série), `cf` (campos personalizados 1..4), `racks` (prateleira/fileira/posição por
local), `prep` (tempo de preparo), `profit` por variação (% de lucro digitado), `PRICE_GROUPS`
(grupos de preço de venda). **Nada a fazer** — a absorção não os toca.

**Só a V2 tem** — e é aqui que a absorção trava. Cada linha diz qual item do §2/§2-bis fica bloqueado
sem o campo:

| Campo `[V2]` | O que é | Trava |
| --- | --- | --- |
| `naturezaLocal` (L622) — `venda` / `bloqueado` | natureza do local; `vendavel()` soma só `venda`, `bloqueado()` soma o resto, `fisico()` é a soma dos dois (L664-678) | §2.2 · §2.17 · o "reservado" da linha |
| `custodiaPorId` | mercadoria de cliente em garantia — posse sem propriedade, fora do saldo e do valor | §2.2 (o alvo somaria custódia no estoque) |
| `perdasPorId` | baixa como lançamento com janela, não como local | §2.2 |
| `faixasQtd` (L709) + `fatoresFaixa` | preço por **quantidade comprada** no pai | célula de preço com faixa e o tooltip; o limite de alçada por faixa |
| `encomendaPorId` (L722) — `prazo`, `janela`, `minimo` | condição de item sob encomenda | §2.16 (chip "Sob encomenda") · §2.11 |
| `obsPorId` (L682) — `tag` + `critica` | observação operacional com criticidade separada do rótulo | §2.16 |
| `compraPorId` | custo de compra por unidade com procedência | §2.3 |
| `precoPorMarkup` / `precoAlteradoEm` | procedência do preço ("Formação de preço (markup)" × "Preço manual", com data) | nenhum item — **candidato novo**, não absorvido |
| `alcada()` (L1017) | percentual de alçada do colaborador | §2.18 |
| `parado` / `ultimaVenda` | giro | §2.11 · KPI "sem venda 90d" |
| `minimo` por combinação (L1610) | mínimo é da combinação, não do pai | §2.2 |

**Divergência de forma, não de campo:** o alvo guarda saldo em `stock` no produto e `locs` como lista
de ids (L54-79); a V2 guarda `locaisPorId` como pares `[nome, qtd]` (L624). Absorver §2.2 exige mudar
a **forma** do dado do alvo, não só acrescentar campo. Declarado, não implementado.

**Pré-requisito, então:** 3 dos 18 itens (2.2, 2.17, 2.16) e 3 dependentes (2.3, 2.11, 2.18) só
entram depois que o dado do protótipo mudar. Isso responde a pergunta 9 do §7 (ordem de execução):
**forma primeiro não é possível para esses seis** — eles são dado, não forma.

---

## §12 Eixo B — escopo de exportação e impressão (28/08)

Achado principal: **o alvo tem dois botões que parecem o mesmo e exportam escopos diferentes.**

| Gesto | Escopo real | Onde |
| --- | --- | --- |
| "Baixar Excel" (cabeçalho) | `exportarCsv(PRODUCTS)` — **catálogo inteiro**, ignora filtro, aba e seleção | `[ALVO L1009]` |
| "Exportar filtrados" (rodapé da grade) | `exportarCsv(ordenados)` — **o recorte**, na ordem da tela | `[ALVO L908]` |
| "Exportar planilha" (menu ⋯ e paleta) | o recorte (`total`) | `[V2 L1364, L1739]` |
| "Exportar seleção" (barra de seleção) | só os selecionados, e limpa a seleção depois | `[V2 L1676]` |
| "Etiquetas" (barra de seleção) | os selecionados, via `window.__PBEtiquetas` | `[ALVO L918]` · `[V2 L1677]` |

- **Três escopos, dois rótulos parecidos.** "Baixar Excel" e "Exportar filtrados" ficam a 40cm um do
  outro e não dizem qual é qual. O nome do gesto tem que trazer o escopo: **"Baixar catálogo (Excel)"**
  e **"Exportar este recorte"**. `[DECIDIR]`
- **Falta "Exportar seleção" no alvo.** Com 12 itens marcados, o alvo oferece Etiquetas (seleção) e
  duas exportações que ignoram a seleção. Candidato novo — **2.19**.
- **Confirmação no repo, pelo mesmo padrão:** `Unificado/_components/BulkBar.tsx` L15 declara que
  `/products/download-excel` "exporta o catálogo inteiro e ignora seleção". A ambiguidade não é só do
  protótipo; é do endpoint. Vale como aviso a quem implementar.
- **Colunas exportadas:** `[ALVO L739]` são 13 fixas (Produto, SKU, Tipo, Categoria, Subcategoria,
  Marca, Unidade, Imposto, Compra, Venda, Estoque, Locais, Situação) — **inclui "Compra"**, que é
  custo, **sem consultar permissão**. Com o §2.5 aprovado, a exportação passa a ser a porta que
  contorna o gate. Teste de aceite: usuário sem `custo` baixa o CSV e a coluna Compra **não existe**
  no arquivo (não vazia — ausente).

---

## §13 Eixo C — papel × o que vê (28/08)

**Os dois sistemas de permissão não se encostam.** Não há conflito a resolver; há um vazio.

- `[ALVO]` `produto-perms.jsx`: 13 permissões com os **nomes reais do legado** —
  `unit.*`, `brand.*`, `category.*`, `barcode_settings.access` — e 4 papéis (administrador, gerente
  de catálogo, balcão só-ver, sem acesso). Todas governam **cadastros de apoio**. Nenhuma governa
  preço, custo, composição ou margem.
- `[V2]` `perm()` (L994-999): `custo`, `preco`, `composicao`, `compras`, `margem`, `reposicao`,
  derivadas de um prop `perfil` de dois valores (autorizado / vendedor).
- `[REPO]` o blade legado gateia com os nomes que faltam: `@can('view_purchase_price')` (index L287) e
  `@can('access_default_selling_price')` (L294), além de `product.view` (L140), `product.create`
  (L176), `product.delete` (L364), `stock_report.view` (L150, L192).

**Consequência para o §2.5:** a permissão de custo **não deve ser inventada** — o nome é
`view_purchase_price`, e o de preço é `access_default_selling_price`. `composicao` e `margem` não
existem no legado: `margem` é derivável (preço + custo) e pode herdar de `view_purchase_price`;
`composicao` precisa de decisão. `[DECIDIR]`

**Matriz proposta** — lista **FECHADA**, para o agente não completar. Marque as células.

| | administrador | gerente de catálogo | balcão (só ver) | vendedor |
| --- | --- | --- | --- | --- |
| Coluna Preço de venda (`access_default_selling_price`) | vê | vê | vê | vê |
| Coluna Preço de compra un. (`view_purchase_price`) | vê | vê | **não** | **não** |
| KPI Valor em estoque (custo) | vê | vê | não | não |
| KPI Margem baixa (derivado) | vê | vê | não | não |
| KPI Abaixo do mínimo (reposição) | vê | vê | vê | **não** `[V2 L1006]` |
| Aba/seção Composição | vê | vê | `[DECIDIR]` | não |
| Ação Inativar (`product.update`) | pode | pode | não | não |
| Ação Excluir (`product.delete`) | pode | **não** | não | não |
| Coluna Compra no CSV exportado | sai | sai | não sai | não sai |

**Achado de risco, do próprio alvo** (`produto-perms.jsx` L6-11, achado A-P1): seis controllers do
legado **não têm gate nenhum** — Warranty, VariationTemplate, SellingPriceGroup, Labels,
ImportProducts, ImportOpeningStock. Qualquer usuário logado cria, edita e exclui. O protótipo
sinaliza em vez de esconder, o que está certo; mas isso significa que **"Importar produtos" e
"Etiquetas" não podem ser usados como referência de permissão** ao implementar. Vale para a pauta do
produto, não para esta tela.

---

## §10 Eixo 4 — fidelidade ao template e ao DS (auditoria de 28/08)

Alvo auditado: `produto-blade.jsx` + `produto-blade.css`. Referências, na ordem do procedimento:
`[DS]` componentes em `_ds_bundle.js` · `[TPL]` `templates/pt-01-lista/Pt01Lista.dc.html` ·
`manual-de-blocos.md`. Lista **fechada**: é o que a varredura achou, não amostra.

**Isto não é lista de absorção.** Nada aqui vem da V2 e nada foi alterado — divergência se aplica
como está e vai para decisão, conforme o §0.

| # | Está (alvo) | `[TPL]` / `[DS]` diz | Peso |
| --- | --- | --- | --- |
| 4.1 | Padding de slot cravado: `.pb-body 16px 20px 28px` (css L4) · `.pb-toolbar 8px 12px` (L96) · `.pb-chips 8px 12px` (L106) · `.pb-pag 9px 12px` (L114) | `padding:var(--d-cpad-y,12px) var(--d-cpad-x,14px)` em todo slot `[TPL]` L34-74; manual item 3 | alto — é o que faz a densidade valer para a página, não só para a tabela |
| 4.2 | Densidade é classe local: `.pb-dense .pb-body{padding:12px 16px 22px}` + `.pb-tbl.densa td{padding:3px 8px}` (L261-263) | densidade é **contrato de tokens no shell** — `--d-fontsz`, `--d-cpad-x/y`, `--d-tb-y`, `--d-td-y`, `--d-th-y` e a rampa `--fs-1..9`, escritos na raiz `[TPL]` L143-152; manual item 5 | alto — mecanismo diferente, não valor diferente |
| 4.3 | Rampa não é usada: tamanhos em px fixos por regra (`thead th 10px`, `.pb-kpi small 10px`, `td small 10.5px`, `.m 11.5px`) | `--fs-1..9` + `font-size:var(--d-fontsz)` na raiz `[TPL]` L23, L143-152 | alto |
| 4.4 | **Piso tipográfico rompido:** `10px` em `.pb-tbl thead th` (L52) e `.pb-kpi small` (L178) | piso 10,5px (manual item 4; `--fs-1` confortável = 10,5px) | médio — dois lugares, 0,5px |
| 4.5 | KPI é CSS local: `.pb-kpi` com `border-radius:10px`, `padding:10px 12px`, valor 20px (L176-182) | `[DS]` `KpiFilterCard` (`hint-size 100%,62px`) montado em grid `gap:9px` `[TPL]` L47-52 | alto — é componente do DS recriado na tela, o que o §0 descarta |
| 4.6 | Moldura: `.pb-widget{border-radius:12px}` sem sombra; `.pb-tblwrap{border-radius:0 0 12px 12px}` | `border-radius:var(--radius-lg)` + `box-shadow:0 1px 2px rgba(0,0,0,.04)` `[TPL]` L75 | médio — raio cravado onde há token |
| 4.7 | Tabela própria `.pb-tbl` (thead sticky, `th 8px/10px`, `td 7px/10px`) no Relatório de estoque e nas tabelas do drawer | `[DS]` `DataTablePro`/`DataTable`, com padding por `--d-td-y`/`--d-th-y` `[TPL]` L21-22 | médio — a lista principal já usa a grade do DS; sobraram as secundárias |
| 4.8 | Sem rodapé de paginação; existe CSS local `.pb-pag` (L114-120) para as telas que paginam | slot footer com `[DS]` `Pagination` `[TPL]` L79-81 | **decidido**: §2.10 recusa paginar a lista. Fica declarado que o rodapé do TPL não se aplica aqui, e o `.pb-pag` local segue nas telas de formulário |
| 4.9 | `cursor:default` em elemento clicável — `.pb-chip` (L108), `.pb-seg button` (L102), `.pb-chk` (L34), `.pb-sortth` (L83) | `[DS]` `Button`/`FilterChip` não declaram `cursor:default`; o gesto de clique pede cursor de clique | baixo, mas é sinal errado em 4 controles |

**Conforme, verificado** (para ninguém "consertar"): foco visível com `outline:2px solid var(--accent)`
em 6 seletores (css L32) segue a régua do DS; alvos ≥44px em `pointer:coarse` (L127-136); piso na
região de dados + `overflow:auto` no shell (L3-4) é exatamente o item 9 do manual; nenhuma cor nova
— só tokens (`--surface`, `--border`, `--accent`, `--neg`, `--warn`).

**Candidato a pauta do DS, não à tela** `[DECIDIR]`: a css L264-268 corrige dentro do módulo um
`.os-btn.danger` do `superadmin-page.css` que vencia `.os-btn.ghost.danger` e deixava texto vermelho
sobre fundo vermelho (~1,6:1). O contorno está declarado no próprio arquivo. Levo para
`pauta-design-system.md` como defeito de origem do shell?

---

## §9 Como pedir uma comparação (para a próxima)

Quatro eixos. Nomear o eixo é o que evita eu responder um e calar sobre os outros — foi o que
aconteceu aqui: rodei 1 e 2, o 3 só em parte, e o 4 não rodei.

1. **Função** — o que uma tela faz e a outra não. Cada achado com arquivo e linha nas duas pontas.
2. **Comportamento** — o que as duas fazem, mas diferente (o que persiste, o que o clique dispara,
   o que acontece no vazio, no erro, sem permissão, com a janela curta).
3. **Usabilidade** — teclado, foco, o que a linha declara sem clique, quantos gestos para a resposta,
   o que a pessoa perde ao recarregar.
4. **Fidelidade ao template e ao DS** — três leituras, na ordem: o componente do DS que renderiza o
   elemento; o **template canônico** do mesmo tipo de tela (`templates/pt-01-lista` para índice) —
   markup, `<style>` do `<helmet>` e a lógica que escreve tokens; o guia. Só o que não está nos três
   é decisão da tela. Aqui o resultado não é "o que absorver", é **divergência**: valor citado com
   arquivo e linha, e nada alterado até a decisão vir.

Frase que basta: *"compare A com B nos quatro eixos; para cada achado diga qual eixo, a linha nas
duas pontas e se duplica algo que já existe."*

Duas cláusulas que valem sempre, e não precisam ser repetidas no pedido: **nada implementado antes da
marcação** (o arquivo é do Wagner) e **lista declarada fechada ou ilustrativa**.

---

## §8 Sincronização com o Wagner e o Luiz

Você, o Wagner e o Luiz acessam o mesmo diretório do projeto. Então este documento é o combinado, e
ele vive aqui — não no chat. Duas consequências práticas:

1. **Eu não altero `produto-blade.jsx` antes da sua marcação.** O arquivo é do Wagner; mudança minha
   sem decisão registrada vira conflito na mão dele.
2. **Quando você marcar, cada item implementado vira um diff** (está / deve ficar / como conferir)
   escrito neste mesmo arquivo, para o Luiz conferir sem precisar ler o código.

Ainda não sei o que o Luiz faz no fluxo — se ele implementa no repo, o formato de entrega dele muda
(handoff com teste de aceite por requisito, conforme `manual-escrita-para-agente.md`). Me diz e eu
ajusto.


## §20 As três atualizações do DS de 28-31/08 contra o ALVO — medido em 01/09, nada a aplicar

Pedido: aplicar na tela do Wagner ("Todos os produtos") as atualizações lidas no `main` em 01/09.
**Medido: as três já estão satisfeitas no protótipo, e em duas delas o protótipo é a FONTE, não o
destino.** Lista fechada.

**1 · ADR UI-0028 — sidebar em hue 295.** Nada a fazer. `styles.css` L3-11 (`:root`) já traz os 8
tokens em 295 — `--sb-bg oklch(0.21 0.025 295)` … `--sb-active oklch(0.34 0.05 295)` — com o
comentário L3 *"tingido p/ a marca (roxo canon hue 295, ADR 0235) em vez de preto neutro croma-0"*.
São **exatamente** os valores que a UI-0028 mandou o repo adotar: a ADR os copiou **daqui**
(*"Fonte: `prototipo-ui/cowork/styles.css`. Copiados exatamente"*). Quem está atrás é o espelho do DS
(`_ds/…/colors_and_type.css` L308-315, hue 240), não esta tela.
**Como conferir:** `getComputedStyle(document.documentElement).getPropertyValue('--sb-bg')` no
protótipo = `oklch(0.21 0.025 295)`.

**2 · Ghosts no sidebar e slot do atalho `G X`.** Nada a fazer, mesma direção. `styles.css` já tem
`.sb-item.sb-sub` (L5173-5179, borda esquerda 2px em `--accent` quando ativo, `padding-left:18px`),
`.sb-ghost-count` (L6593 e L6645, mono 10px, opacidade .36), `.sb-ghost-more` (L6638-6640) e
`.sb-item-end` (L6643-6647, contagem em repouso ⇄ atalho no hover, mesma célula do grid). O
`cockpit.css` do `main` declara no próprio comentário que **portou** esse bloco de
`prototipo-ui/cowork/styles.css` :5173 · :6593 · :6638-6645.

**3 · ADR 0386 — âmbar por vertical revogado, roxo 295 é a única identidade de chrome.**
Já conforme: `styles.css` L23-25 (`--accent oklch(0.55 0.15 295)`), L6342-6347 e L6452-6456
(`--accent-h: 295`, luminância dona do tema), L6582 (`--accent-h: 295`). `grep` por `.oficina-scope`
no protótipo: **0 ocorrências**. `produto-blade.css` não declara matiz própria — os únicos `oklch`
dele são pretos de sombra/scrim (L161, L171, L185-186) e um derivado do próprio accent
(`.pb-abc i.b`, L221, `oklch(from var(--accent) …)`).

**4 · ADR UI-0029 — protótipo soberano na FORMA.** Não é valor, é precedência, e **reforça** os itens
1 e 2: onde o protótipo e uma ADR UI discordarem na forma, a ADR é que se corrige. Registrada no
handoff (§17 do patch de cor). Nada a mudar no código.

### Único achado desta varredura — e é **pergunta**, não ação

O tweak **"Tom do accent"** (`app.jsx` L1012-1017) é um slider `min=0 max=360 step=10` que escreve
`--accent-h` no `:root` (L759-761). O invariante da ADR 0263, que a ADR 0386 acaba de deixar **sem
exceção pendente**, é `--accent*` em **hue 250-330**. Com o slider como está, arrastar para 70° põe
o protótipo em âmbar — exatamente a cor que a 0386 revogou — e para 220° reproduz o incidente do azul
já registrado no manual (§ procedência [RUNTIME]).

**DECIDIDO (Maiara, 01/09): fica como o Wagner deixou** — saída (b). O slider `0-360` **não é
alterado**. Declarado aqui: é ferramenta de exploração do protótipo, fora do invariante da 0263; o
valor de entrega é o default `295` (`app.jsx` L679). Quem for implementar **não deve** copiar a
faixa do slider para o repo, e **não deve** "corrigir" o slider no protótipo.

**Teste de aceite (qualquer que seja a escolha):** com o tweak no default, `--accent-h` = `295` e
nenhum `--accent*` computado sai da faixa 250-330.


## §21 O que ainda está fora do DS no ALVO — remedido em 01/09

Resposta à pergunta *"não tem mais nada fora do DS?"*. Não é lista nova: é o **§10 remedido** depois
das ondas 0, 0c e 0d. Fechada para `produto-blade.css` + `produto-blade.jsx`. Nada alterado.

**Fechou desde 28/08** (para ninguém reabrir):
- **4.4 piso tipográfico** — `.pb-tbl thead th` (L50) e `.pb-kpi small` (L178) agora usam `var(--fs-1)`;
  não há mais `10px` cravado nesses dois.
- **4.6 moldura** — `.pb-widget` (L7) usa `var(--radius-lg)` + `var(--shadow-soft)`; `.pb-tblwrap` (L48)
  usa `var(--radius-lg)`.
- **4.9 cursor** — `grep cursor:default` em `produto-blade.css`: **0 ocorrências**.
- **4.5 / 4.11** — KPI e abas da lista passaram a componente do DS (ondas 0c e 0d).

**Segue aberto** — quatro itens, todos já classificados no §10, nenhum novo:

| # | Está (medido 01/09) | O DS/TPL diz | Situação |
| --- | --- | --- | --- |
| 4.1 | Padding cravado nos slots: `.pb-body 16px 20px 28px` (L4) · `.pb-toolbar 8px 12px` (L86) · `.pb-chips 8px 12px` (L97) · `.pb-pag 9px 12px` (L106) | `var(--d-cpad-y/-x)` em todo slot `[TPL]` | **onda 0b**, adiada por raio: o css serve 11 telas |
| 4.2 | Densidade é classe local: `.pb-tbl.densa td{padding:3px 8px}` (L80-82) + `.pb-dense` | densidade é contrato de tokens no shell (`--d-td-y`, `--d-cpad-*`, rampa) | mesma onda 0b — mecanismo, não valor |
| 4.3 | Rampa usada só em parte: sobram `12.5px` (L23, L33, L49), `11.5px` (L55, L57, L99, L106), `11px` (L8, L28, L40) e `20px` no valor do KPI (L179) | `--fs-1..9` + `font-size:var(--d-fontsz)` na raiz | mesma onda 0b |
| 4.7 | Tabela própria `.pb-tbl` (L48-65) nas tabelas **secundárias**: Relatório de estoque e as do drawer | `[DS]` `DataTable`/`DataTablePro` | aberto — a lista principal já é a grade do DS |

**Mais dois, fora da numeração do §10:**

- **Controles do widget "Filtros" são HTML cru.** Sete `<select>` e um `<input type="checkbox">`
  estilizados por `.pb-fld` (L21-34), com o DS trazendo `Select` (`_ds_bundle.js` L4546) e
  `Checkbox` (L2511) no bundle já carregado. É o mesmo caso do `.pb-kpi` da onda 0c: componente do
  DS recriado na tela. O **widget** fica (decisão da tela, §3); só os controles estão em aberto.
- **`.pb-kpi` sobreviveu no CSS (L176-182)** mesmo depois da onda 0c ter trocado as placas da lista
  pelo componente do DS — ele ainda serve outras telas do módulo. Enquanto servir, é código
  compartilhado, não resíduo; quando não servir mais, sai junto com a onda 0b.

**Pendente de resposta sua** (perguntei no §10 e não voltou): o contorno da css L264-268, que corrige
dentro do módulo um `.os-btn.danger` do `superadmin-page.css` — texto vermelho sobre fundo vermelho
(~1,6:1) — **é defeito de origem do shell**. Levo para `pauta-design-system.md`?

**Teste de aceite do §21:** `grep -nE 'padding:\s*[0-9]|font-size:\s*[0-9]' produto-blade.css` só
devolve linhas dos quatro itens acima; nenhuma cor fora de token (`grep oklch` = 4 linhas, todas
preto de sombra/scrim ou derivado de `--accent`).


## §22 Lista de pendências para implementação (01/09)

Os itens abertos do §21, mais o defeito P-1 medido em 01/09 (o menu "Ações" fechando ao arrastar a
própria barra de rolagem — `produto-blade.jsx` L181, `scroll` capturado chamando `fecha`), foram
consolidados em [`pendencias-todos-os-produtos.md`](pendencias-todos-os-produtos.md), no formato
está / deve ficar / como conferir, com procedência e teste de aceite por item.

São 8 itens: P-1 defeito do menu · P-2/P-3/P-4 a onda 0b (padding, densidade, rampa) · P-5 tabelas
secundárias · P-6 controles do widget Filtros · P-7 `.pb-kpi` compartilhado · P-8 a pergunta do
`.os-btn.danger`. O §6 de lá declara o que **não** é pendência, para não ser reaberto.
