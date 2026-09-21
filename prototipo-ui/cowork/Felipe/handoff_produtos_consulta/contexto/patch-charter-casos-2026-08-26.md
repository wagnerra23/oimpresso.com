# Patch para os documentos de comportamento do `main` — 2026-08-26

Alvo: `resources/js/Pages/Produto/Unificado/Index.charter.md` e `Index.casos.md` (branch `main`).
Origem: decisões do Wagner, da Maiara e revisão do Felipe em 26/08/2026, registradas no §19 do
handoff `Consulta de Produtos`.

**Como usar.** Cada item traz **está** (texto atual do documento no `main`) e **deve ficar** (texto
de substituição, já na linguagem do documento). Aplicar no mesmo PR das mudanças de código, como a
regra de precedência do projeto manda (`proibicoes.md`: a onda corrige no seu PR os itens do charter
que ela contradiz). **Não** reescrever nada além dos blocos citados.

---

## 1 · charter · Goals — saídas do drawer

**Está:**

> Rodapé com esteira `‹ ›` ("2 de 13") e três saídas: Abrir cadastro · Formar preço\* · Usar em orçamento.

**Deve ficar:**

> Rodapé com esteira `‹ ›` ("2 de 13") e **duas** saídas: **Formar preço\*** (ghost) e
> **Abrir cadastro** (primária, última). **"Usar em orçamento" não existe nesta tela** — decisão do
> Wagner de 2026-08-26: o caminho para o orçamento parte do orçamento, não da consulta. Não
> recolocar em outro lugar da tela (linha, menu `⋯`, BulkBar, paleta `⌘K`).

Consequência para a Mission, que hoje diz "levar ao cadastro completo quando a resposta exige ação":
segue valendo, com **duas** saídas em vez de três.

---

## 2 · charter · Goals — visibilidade dos KPI-filtros

**Está:**

> **Até quatro KPI-filtros**, contados sobre a aba ativa e **clicáveis** (toggle): Abaixo do mínimo ·
> Sem saldo · Sem venda 90d\* · Margem baixa\*. Os dois com \* são recortes de gestão e só existem
> pra quem vê custo — o gate vale no servidor, não só na tela.

**Deve ficar:**

> **Até quatro KPI-filtros**, contados sobre a aba ativa e **clicáveis** (toggle): Abaixo do mínimo ·
> Sem saldo · Sem venda 90d\* · Margem baixa\*. A visibilidade de cada um tem regra própria — os
> gates valem **no servidor**, não só na tela:
>
> | KPI | Existe quando |
> |---|---|
> | Abaixo do mínimo | permissão de **reposição** (perfil ≠ vendedor) **e** aba ≠ Serviços |
> | Sem saldo | aba ≠ Serviços |
> | Sem venda 90d | permissão de **custo** **e** aba ≠ Serviços **e** aba ≠ Matéria-prima |
> | Margem baixa | permissão de **margem** **e** aba ≠ Matéria-prima |
>
> Motivos, decididos em 2026-08-26: repor estoque não é responsabilidade do vendedor (ele mantém o
> selo da linha e a faceta Disponível → Abaixo do mínimo); serviço não tem saldo, então "Abaixo do
> mínimo" e "Sem saldo" contariam sempre zero; **matéria-prima não se vende**, então giro de venda e
> margem de venda não se aplicam a ela.
>
> **KPI invisível não filtra.** O recorte ativo é **ignorado** quando o cartão que o representa não
> está na faixa — vale para os quatro, e vale no servidor. Sem isso, trocar de aba ou de perfil
> deixa a lista filtrada por um critério que o usuário não vê e não consegue desligar. Um único
> predicado de visibilidade, consultado nos dois lugares: ao montar a faixa **e** ao aplicar o
> recorte.
>
> **A faixa tem 4 colunas fixas** (`repeat(4, minmax(0,1fr))`), não `auto-fit`: com um cartão só,
> `auto-fit` colapsa as trilhas vazias e o cartão estica pela faixa inteira. Colunas fixas também
> mantêm a posição de cada KPI estável entre abas e perfis.

---

## 3 · charter · UX Anti-patterns — a linha do `auto-fit`

**Está:**

> ❌ "Consertar" a faixa de 6 KPIs com `auto-fit` no desktop declarado — quebrar em duas linhas
> diverge da referência e foi reprovado

**Deve ficar:**

> ❌ `auto-fit` na faixa de KPI — a proibição de 2026-08-18 (quebrar em duas linhas) **continua**, e
> ganhou um segundo motivo medido em 2026-08-26: com um cartão só (aba Serviços, depois das regras de
> visibilidade acima) o `auto-fit` colapsa as trilhas vazias e o cartão ocupa a faixa inteira. A
> faixa é `repeat(4, minmax(0,1fr))`.

---

## 4 · charter · Goals — o painel de detalhe (identidade e cabeçalho)

**Está:** o item do drawer descreve só a ordem das seções ("alertas → identidade → Disponível → …").

**Deve ficar** — acrescentar ao mesmo item, sem mexer na ordem das seções:

> **Cabeçalho do painel:** nome do produto e código ficam **na faixa do cabeçalho, acima da linha**,
> à esquerda do ✕ — não em bloco abaixo dela. O rótulo acessível do diálogo é "nome · código".
>
> **Tira de identidade:** miniatura → **selo de situação de saldo abaixo da miniatura** (Disponível ·
> Abaixo do mínimo · Sem saldo · Não estocável) → ao lado, **selo do tipo em código curto**
> (`PROD` · `SERV` · `M-PRIMA` · `KIT`, o mesmo da coluna Tipo) e, abaixo dele, a categoria. O selo
> de saldo **não** fica no cabeçalho do painel.
>
> **Primeiro bloco de dados sem título e sem régua:** o bloco de saldo é continuação da tira de
> identidade — sem o rótulo "DISPONÍVEL" (que repetiria o selo) e sem a régua acima dele. As demais
> seções mantêm título e régua. O bloco de kit continua com o título "Disponível para venda".
>
> **Unidade:** o selo da coluna Disponível e o painel mostram a **sigla** ("0 br", "3 PC"); a
> **descrição** da unidade aparece na dica ("br = barra"), lida do **cadastro de unidades**. Sem
> descrição cadastrada, não há dica — a tela não inventa nome de sigla.

---

## 5 · charter · Goals — modelo de rolagem: **nada muda**

O charter diz, e **continua valendo**:

> A rolagem vertical é da PÁGINA — a altura fixa (460) e o teto de 500 linhas que a substituíam
> saíram junto.

Decisão da Maiara em 2026-08-26: **o modelo de rolagem é o do `main`.** A versão do handoff que
mandava limitar a área de dados e rolar por dentro dela (§3.1.1 / §15.3 nº20, vinda da auditoria do
template PT-01) fica **desconsiderada** — o protótipo foi corrigido para a rolagem de página.

**Acrescentar ao mesmo item, porque é o que faz o cabeçalho fixo funcionar:**

> **O cabeçalho da tabela é fixo, preso ao topo da JANELA** (`thead th { position: sticky; top: 0 }`,
> com fundo opaco e acima do trilho da linha). Requisito assinado pelo Wagner em 2026-08-26: rolar a
> lista **nunca** exibe linha de produto sobre a faixa de títulos (Código · Produto · Tipo ·
> Disponível · Preço de venda · Margem).
>
> ⚠️ **Nenhum ancestral da tabela pode declarar `overflow`** — nem `overflow-x: auto` no invólucro
> que hoje segura a rolagem horizontal. Um eixo em `auto` faz o navegador computar `auto` no outro; o
> scrollport do `sticky` passa a ser aquele contêiner e o cabeçalho volta a sair de vista junto com a
> página. Com rolagem de página, a rolagem horizontal da tabela larga é a **do documento** — e o
> seletor de colunas do menu `⋯` é o que a elimina de verdade (`min-width` recalculado).
>
> Medida de aceite: com a página rolada, `th.getBoundingClientRect().top === 0` e nenhuma linha
> renderiza acima do `th`.
>
> **Em largura estreita a tabela corta à direita** e a página rola para o lado — decisão da Maiara de
> 2026-08-26, tomada ciente do custo. O caminho do usuário é esconder Custo e Margem no menu `⋯`
> (`min-width` recalculado). **Não** implementar ocultação automática por largura, nem compressão de
> coluna sem largura mínima, nem barra horizontal própria da tabela — esta última exige contêiner com
> `overflow`, que reintroduz o cabeçalho que sai de vista.

---

## 6 · casos · UC-PUNI-07 — completar

**Está:** `UC-PUNI-07 · O contador "Margem baixa" segue o gate do custo · must`.

**Deve ficar:** mesmo UC, com o gate por aba acrescentado — "Margem baixa" **não é servido** na aba
Matéria-prima, e "Sem venda 90d" **não é servido** nas abas Serviços e Matéria-prima, mesmo para
perfil com custo. Aceite: com `aba=materia`, a resposta não traz as chaves dos dois contadores.

---

## 7 · casos · UC novo · KPI invisível não filtra · `must` `[V0]`

**Cenário.** Recorte ativo `kpi=min` (Abaixo do mínimo) e, em seguida, (a) troca para `aba=servicos`;
(b) a mesma requisição com perfil **vendedor**.

**Esperado.** Nos dois casos o recorte é **ignorado**: a resposta traz todas as linhas da aba, o
total confere com a contagem da aba, e a chave do KPI não vem no payload. Nunca uma lista filtrada
por um KPI que a tela não exibe.

**Aceite (uma linha).** `GET /products/unificado?aba=servicos&kpi=min` devolve o mesmo `total` que
`?aba=servicos`, e `kpis.min` está ausente.

---

## 8 · casos · UC novo · Sigla de unidade tem descrição · `should`

**Cenário.** Produto cuja unidade é `br`, com e sem descrição cadastrada na unidade.

**Esperado.** Com descrição, o payload da linha traz a descrição da unidade e a tela mostra a dica
("br = barra"). Sem descrição, nenhuma dica é servida nem inventada no front.

**Aceite (uma linha).** A resposta traz `unidade: { sigla, descricao|null }`, e `descricao` nunca é
derivada de mapa no cliente.

---

## 9 · charter · UX Anti-patterns + Goals — menu `⋯` nunca sobre o painel

Vem do §18.6 do handoff (26/08, revisão anterior) e ainda não estava nos documentos do repositório.

**Acrescentar em UX Anti-patterns:**

> ❌ Menu de ações aberto **sobre** o painel de detalhe. Não é bug de `z-index` da tela: é a pilha do
> próprio DS — `DropdownMenu` em 70 contra `Drawer` em 60. **Não** sobrescrever esses valores nem
> envolver os dois em contexto de empilhamento novo.

**Acrescentar em Goals, junto do item do drawer:**

> **Painel e menu são exclusivos.** Abrir qualquer `DropdownMenu` do chrome da lista — o `⋯` da
> linha, o `⋯` do cabeçalho e os gatilhos de filtro e de Ordem — **fecha o painel** antes de o menu
> aparecer; em nenhum quadro os dois ficam visíveis. No `⋯` da linha, o gatilho precisa de
> `stopPropagation`: sem ele o clique sobe ao `onRowClick` da tabela, que roda depois e reabre o
> painel na mesma linha — e os dois ficam abertos. Nos gatilhos de filtro, o fechamento vem na fase
> de **captura** do invólucro, sem tocar no componente. O primeiro item do menu da linha é
> "Ver detalhes", que reabre o painel — nada de contexto se perde.

---

## 10 · casos · UC novo · Painel e menu de ações são exclusivos · `should`

**Cenário.** Painel aberto no produto A; usuário clica o `⋯` do produto B (e, no segundo passo, o
`⋯` do cabeçalho e cada gatilho de filtro).

**Esperado.** O painel fecha e o menu abre; nunca os dois visíveis ao mesmo tempo; o painel **não**
reabre sozinho no item cujo `⋯` foi clicado.

**Aceite (uma linha).** Com o painel aberto, um clique no `⋯` de outra linha deixa exatamente **um**
overlay no DOM — o menu — e `abertoId` volta a nulo.

---

## 9 · charter · Histórico — entrada a acrescentar

> | 2026-08-26 | [W+M+F] via [C] | **Rodada de decisões sobre a Consulta de Produtos (handoff §19).** **[W]:** cabeçalho da tabela **fixo** — rolar a lista nunca mostra linha sobre a faixa de títulos; o modelo de rolagem segue o do `main` (página), e por isso **nenhum ancestral da tabela pode ter `overflow`** (um eixo em `auto` força o outro e o `sticky` passa a resolver contra o contêiner). **[W]:** "Usar em orçamento" **removido** do painel — o caminho para o orçamento parte do orçamento. **[W]:** KPI "Abaixo do mínimo" fora do perfil vendedor (chave própria de reposição, não derivada de custo). **[W+M]:** "Abaixo do mínimo" e "Sem saldo" fora da aba Serviços; **[M]:** "Sem venda 90d" e "Margem baixa" fora da aba Matéria-prima (não se vende) — e a regra geral **"KPI invisível não filtra"**, com predicado único na faixa e no recorte. **[M]:** faixa de KPI em 4 colunas fixas (`auto-fit` esticava o cartão único pela faixa inteira) e "Abrir cadastro" passa a **primária**, última do rodapé, casando com o template PT-01. **[M+F]:** painel — nome e código na **faixa do cabeçalho** (o `Drawer` do DS os renderiza abaixo da borda; o slot que fica na faixa é o `badge`, e o `aria-label` passa a ser escrito pela tela), selo de saldo **abaixo da miniatura**, selo do **tipo em código curto** ao lado, primeiro bloco de dados **sem título e sem régua**. **[F]:** sigla de unidade ganha descrição na dica, lida do **cadastro de unidades** — o mapa do protótipo é ilustrativo e não deve ser transcrito. **[F] (rodada anterior, ainda não refletida nos documentos):** painel e menu de ações são exclusivos — abrir qualquer `DropdownMenu` do chrome da lista fecha o painel antes, sem mexer no `z-index` do DS. **Retratação registrada:** a versão anterior do handoff (§3.1.1, §15.3 nº20) mandava limitar a área de dados e rolar por dentro dela, vinda da auditoria do template PT-01; **[M]** manteve o modelo do `main` e a instrução do handoff foi desconsiderada. |
