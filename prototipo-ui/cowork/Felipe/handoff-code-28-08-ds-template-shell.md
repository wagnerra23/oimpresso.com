# Conversa completa — 28/08/2026 · questões de DS / template / shell

Registro integral da sessão entre **Maiara** (dona do protótipo Consulta de Produtos V2) e o agente de
design, para envio ao **agente de código**. Nada aqui é pedido de implementação no repo: é o estado
medido do protótipo, o que já foi alterado nele, e as decisões de DS/template/shell que estão paradas
esperando resposta.

---

## §0 Contrato de leitura

- **Procedência marcada em todo valor.** `[DS]` citado do design system, com arquivo e linha ·
  `[TPL]` citado do template canônico · `[TELA]` decidido no protótipo · `[RUNTIME]` observado no
  navegador (**nunca é regra**) · `[REPO]` medido em `oimpresso.com` (código real).
- **Nenhum número deste documento foi inferido de prosa.** Onde eu não medi, está escrito "não medido".
- **Toda lista é declarada fechada ou ilustrativa.** Lista sem marca não existe aqui.
- **Este documento não altera o repo.** As alterações descritas no §5 aconteceram **no protótipo**
  (`produto-blade.css`, `produto-blade.jsx`), não em `oimpresso.com`.
- **Proibições explícitas** no §8. Regra ausente não é proibição — o que não está proibido lá está
  liberado, e o que está pendente no §7 **não deve ser preenchido por conta**.
- **Erros do agente estão declarados** no §9, com o que cada um invalidou. Se algum documento anterior
  desta linha contradiz este, **este vence**.

**Vocabulário dos nomes de tela, para não haver troca** (foi a maior fonte de erro da sessão):

| Apelido nesta conversa | O que é | Onde |
| --- | --- | --- |
| **V2** / "meu protótipo" | Consulta de Produtos V2, da Maiara — a **doadora** | `Consulta de Produtos.dc.html` |
| **Alvo** / "tela do Wagner" | Todos os produtos, no protótipo — **recebe e é autoridade** | `produto-blade.jsx`, rota `prod-lista` |
| **Tela React** | índice do catálogo em produção | `[REPO]` `resources/js/Pages/Produto/Unificado/Index.tsx`, `/products/unificado` |
| **Blade legado** | tela AdminLTE antiga (não é alvo de nada) | `[REPO]` `resources/views/product/index.blade.php`, 875 linhas |

---

## §1 O que a sessão apurou, em uma tela

A Maiara pediu a comparação entre a V2 e o alvo, para o alvo absorver o que a V2 tem de bom. Ao longo
da sessão a conversa migrou de **absorção** para **fidelidade**, por uma pergunta dela:

&gt; *"Não é mais fácil primeiro colocar a tela do Wagner dentro do DS/template/comportamento de tela e
&gt; só depois fazer a absorção?"*

A resposta foi **sim, para os itens de moldura**, e o plano virou ondas. Três ondas de fidelidade já
rodaram no protótipo. Uma quarta está autorizada. Uma quinta foi adiada com condições. E **duas
questões de DS/template/shell ficaram abertas** — são elas que interessam ao agente de código:

1. O cabeçalho de módulo do protótipo (`M.Header`) não é o `PageHeader` do DS, e serve **nove** telas.
2. O `PageHeader` do DS não tem prop de glyph — conformar apaga a placa de ícone.

---

## §2 Direção da absorção (fechada, não reabrir)

- **Doa:** V2 (`Consulta de Produtos.dc.html`). Ao fim, o item sai da sidebar do protótipo e a rota
  sai do `app.jsx`. **O arquivo não é apagado** sem ordem da Maiara.
- **Recebe e é autoridade:** o alvo (`produto-blade.jsx`). Nenhuma linha dele é renomeada por
  iniciativa do agente.

**Contexto para o agente de código, medido, que não muda essa autoridade:** a tela React
`/products/unificado` **já absorveu** boa parte da V2 em ondas anteriores. Medido em
`[REPO]` `Unificado/_components/catalogo.ts`:

| Achado | Linha |
| --- | --- |
| `Permissoes { custo, preco, composicao, inativar }` | L12-24 |
| `ABAS_CATALOGO` — 6 abas por tipo | L218-226 |
| `KpiKey = 'min' \| 'zero' \| 'parado' \| 'margem' \| 'total'` | L235 |
| `EstadoEstoque` — Disponível / Abaixo do mínimo / Sem saldo / Não estocável | L120-160 |
| `marcadorGrade` — "4 de 6 com saldo" | L100-110 |
| `locais?: LocalSaldo[]` | L60-66 |
| Paginação server-side 10/25/50/100, padrão 25 | charter, onda 2 |

Ou seja: parte do que o protótipo vai absorver **já existe no repo**. Quem implementar deve conferir
antes de escrever.

---

## §3 Nomenclatura — decidido pela Maiara em 28/08

Ela escolheu conceito por conceito, entre o léxico do protótipo e o do repo:

| Conceito | Fica | Origem | O que muda no protótipo |
| --- | --- | --- | --- |
| Identificador do item | **Referência** (SKU cadastrado) + **Código** (id interno) | `[REPO]` `catalogo.ts` L43-46 | renomear coluna "SKU" (L317), item do menu de colunas (L260), linha do drawer (L474), placeholder da busca, cabeçalhos do Relatório de estoque (L402) e das Variações (L491) |
| Quantidade em estoque | **Estoque atual** | `[TELA]` alvo (L312, L403) | nada |
| Limite que dispara reposição | **Mínimo** ("Abaixo do mínimo") | `[REPO]` `estadoEstoque` | renomear KPI "ABAIXO DO ALERTA" e linha "Quantidade de alerta" do drawer (L477) |
| Estado do saldo | **Disponível / Abaixo do mínimo / Sem saldo / Não estocável** | `[REPO]` `estadoEstoque` | conceito **novo** no alvo, que hoje só tem o número com classe `low` |

**Pendência dentro dela** `[DECIDIR]`: "Disponível" já existe no alvo com outro sentido —
`Disponível nos locais` (L478) é a **lista de locais** onde o produto é vendável, não quantidade. Com o
estado do saldo chamado "Disponível", a palavra passa a significar duas coisas na mesma tela.
Sugestão do agente, **não implementada**: a linha do drawer vira **"Vendável nos locais"**.

**Teste de aceite:** buscar "Disponível" no DOM da tela absorvida → cada ocorrência é estado de saldo,
e a lista de locais usa outra palavra.

---

## §4 Os quatro eixos de comparação (§9 do doc de absorção)

Registrado porque o agente entregou os eixos em ordem errada e teve de ser cobrado três vezes. Vale
para qualquer comparação futura entre duas telas:

1. **Função** — o que uma tela faz e a outra não, com arquivo e linha nas duas pontas.
2. **Comportamento** — o que as duas fazem, mas diferente (o que persiste, o que o clique dispara, o
   que acontece no vazio, no erro, sem permissão, com a janela curta).
3. **Usabilidade** — teclado, foco, o que a linha declara sem clique, quantos gestos para a resposta,
   o que a pessoa perde ao recarregar.
4. **Fidelidade ao template e ao DS** — três leituras, **nesta ordem**: (a) o componente do DS que
   renderiza o elemento; (b) o **template canônico** do mesmo tipo de tela (`templates/pt-01-lista`
   para índice) — markup, `<style>` do `<helmet>` e a lógica que escreve tokens; (c) o guia. Só o que
   não está nos três é decisão da tela. **O resultado do eixo 4 não é "o que absorver" — é
   divergência: valor citado com arquivo e linha, e nada alterado até a decisão vir.**

Frase que basta, para o próximo pedido: *"compare A com B nos quatro eixos; para cada achado diga qual
eixo, a linha nas duas pontas e se duplica algo que já existe."*

---

## §5 Fidelidade — o que foi medido e o que já mudou no protótipo

### 5.1 Auditoria original (9 itens) — e o que ela não olhou

Lista **fechada** para o que foi varrido; **incompleta por construção**, como o §5.2 mostra.

| # | Está (alvo) | `[TPL]` / `[DS]` diz | Status |
| --- | --- | --- | --- |
| 4.1 | padding de slot cravado: `.pb-body 16px 20px 28px` (css L4) · `.pb-toolbar 8px 12px` (L96) · `.pb-chips 8px 12px` (L106) · `.pb-pag 9px 12px` (L114) | `padding:var(--d-cpad-y,12px) var(--d-cpad-x,14px)` em todo slot `[TPL]` L34-74 | **adiado** (§6) |
| 4.2 | densidade é classe local: `.pb-dense .pb-body{padding:12px 16px 22px}` + `.pb-tbl.densa td{padding:3px 8px}` (L261-263) | densidade é **contrato de tokens no shell** — `--d-fontsz`, `--d-cpad-x/y`, `--d-tb-y`, `--d-td-y`, `--d-th-y` + rampa `--fs-1..9`, escritos na raiz `[TPL]` L143-152 | **adiado** (§6) |
| 4.3 | rampa não usada: px fixos por regra | `--fs-1..9` + `font-size:var(--d-fontsz)` na raiz `[TPL]` L23, L143-152 | **adiado** (§6) |
| 4.4 | piso tipográfico rompido: `10px` | piso 10,5px = `--fs-1` | ✅ **feito** |
| 4.5 | KPI é CSS local: `.pb-kpi` raio 10px, padding 10/12, valor 20px (L176-182) | `[DS]` `KpiCard` / `KpiFilterCard` | ✅ **feito** |
| 4.6 | `.pb-widget{border-radius:12px}` sem sombra; `.pb-tblwrap{border-radius:0 0 12px 12px}` | `var(--radius-lg)` + sombra `[TPL]` L75 | ✅ **feito** |
| 4.7 | tabela própria `.pb-tbl` no Relatório de estoque e no drawer | `[DS]` `DataTablePro`/`DataTable` | **dívida** — risco alto, ganho baixo (a lista principal já usa a grade do DS) |
| 4.8 | sem rodapé de paginação; CSS local `.pb-pag` | slot footer com `[DS]` `Pagination` `[TPL]` L79-81 | **não se aplica** — a tela recusou paginar (§7 item 4) |
| 4.9 | `cursor:default` em elemento clicável | gesto de clique pede cursor de clique | ✅ **feito** |

**Conforme, verificado — não "consertar":** foco visível `outline:2px solid var(--accent)` em 6
seletores (css L32) segue a régua do DS · alvos ≥44px em `pointer:coarse` (L127-136) · piso na região
de dados + `overflow:auto` no shell (L3-4) · **nenhuma cor nova** — só tokens (`--surface`, `--border`,
`--accent`, `--neg`, `--warn`).

### 5.2 O que a auditoria não olhou — achado pela Maiara

&gt; *"O pageheader parece um pouco diferente, assim como não vejo a tabbar."*

O eixo 4 tinha olhado KPI, padding, rampa, raio, cursor e tabelas — **e não olhou cabeçalho nem
abas**. Dois itens novos:

- **4.10 · Cabeçalho** — não é caso de tela. Vai no §7 item 1.
- **4.11 · Abas** — era caso de tela, já resolvido (§5.5).

### 5.3 Onda 0 — aplicada em `produto-blade.css`

| Item | Está agora | Estava | Como conferir |
| --- | --- | --- | --- |
| 4.4 | `var(--fs-1)` (10,5px) em **5** seletores: `.pb-tbl thead th` · `.pb-kpi small` · `.pb-chips-l` · `.pb-chip span` · `.pb-dt span` | `10px` cravado | inspecionar cabeçalho da tabela: 10,5px, valor vindo da variável |
| 4.6 | `.pb-widget{border-radius:var(--radius-lg); box-shadow:var(--shadow-soft)}` · `.pb-tblwrap{border-radius:0 0 var(--radius-lg) var(--radius-lg)}` | raio `12px` cravado, **sem sombra** | o card ganha sombra de 1px; o raio **não muda de tamanho** |
| 4.9 | `cursor:pointer` em **7** seletores: `.pb-chip` · `.pb-seg button` · `.pb-chk` · `.pb-sortth` · `.pb-tag button` · `.pb-menu button` · `.pb-uso` | `cursor:default` | passar o mouse em chip, segmented, item de menu e contagem clicável → cursor de mão |

**Erratas medidas ao aplicar** (as duas primeiras contra a auditoria do próprio agente):

1. **4.6 era menos do que o escrito.** Dentro do `.cockpit`, `--radius-lg` **é 12px**
   (`colors_and_type.css` L301) — o mesmo número que estava cravado. O raio não muda de aparência: a
   mudança é de **mecanismo**. O que muda de fato é a sombra, e ela também tem token:
   `--shadow-soft: 0 1px 2px rgba(0,0,0,.04)` (L304) — exatamente o valor que o `[TPL]` L75 escreve
   **literal**. O protótipo usa o token; **o template é que está escrevendo o literal.**
2. **4.4 eram 5 lugares, não 2.**
3. **Abaixo do piso, ainda aberto** — medido, **não** corrigido, porque não estava declarado:
   `.pb-busca kbd` 9px · `.pb-thumb` (texto "IMG") 9px · `.pb-sortic` 8,5px · `.pb-dense .pb-thumb`
   8px. Recomendação: tratar junto do item de acessibilidade (o texto "IMG" desaparece lá).
4. **Fora do escopo, não mexido:** `.pb-modal` (raio 12px cravado, L186) e `.pb-img` (raio 10px, L198).

### 5.4 Onda 0c — KPI pelo componente do DS, em `produto-blade.jsx`

| Está agora | Estava | Como conferir |
| --- | --- | --- |
| Quatro `[DS]` `KpiCard` (`_ds_bundle.js` L4650), lidos de `DS()` como os outros primitivos | quatro `<div class="pb-kpi">` com CSS local | inspecionar uma placa: raio, padding e sombra vindos do componente, nenhum da tela |
| Rótulos, sub-linhas e tons **iguais aos de antes** — "Abaixo do alerta" `tone="danger"` quando há item, "Inativos" `tone="warning"` | idem, via classes `.neg`/`.warn` | ler as quatro placas: mesmo texto, mesma cor de alarme |
| `.pb-kpis` (grade 4 → 2 colunas em 1180px, 2 em 900px) **mantido** | idem | estreitar a janela: caem para duas colunas |
| `.pb-kpi` local sobra só como plano B enquanto o bundle (`defer`) não chegou | era o caminho único | com bundle carregado, nenhum `.pb-kpi` no DOM |

**Decisão tomada dentro da onda, contrariando a recomendação anterior do próprio agente:** usei
`KpiCard` (leitura) nas quatro, **não** `KpiFilterCard`. Motivo medido: o `KpiFilterCard` é um
`<button aria-pressed>` com `cursor:pointer` (`_ds_bundle.js` L4873-4879) — montá-lo sem `onClick`
entregaria quatro botões mortos, dois deles para sempre (os que não recortam). O `KpiFilterCard` entra
no item do KPI clicável, e **só nas duas placas que viram filtro**.

### 5.5 Onda 0d — abas pelo `TabBar` do DS

Ordem literal da Maiara: *"Rode a troca pelo tabBar, mas não remova mais nada da tela."*

**Está agora:** `[DS]` `TabBar` (`_ds_bundle.js` L6605), com `tabs`/`active`/`onChange` e o contador de
"Todos os produtos" preservado. **Estava:** `nav.cli-moduletopnav` com dois botões escritos à mão,
estilizados por `clientes-page.css` L70-82 — imitação da `TabBar`, não a `TabBar`. **Nada mais saiu:**
o invólucro mantém `padding:0 12px` e o `data-contract="produto-abas"`; o `nav` antigo ficou como plano
B enquanto o bundle (`defer`) não chegou.

Três consequências declaradas:

1. O `aria-label` do `nav` passa a ser **"Sub-navegação"** — o componente escreve o dele; o antigo dizia
   "Abas do índice". Valor do DS: **citado, não escolhido.**
2. O sublinhado ativo passa a usar `--accent` (componente, L6645). Fica registrado que `--accent` é
   reescrito em runtime pelo shell — defeito já aberto na pauta, não desta tela. **O contrato é
   `--color-primary`.**
3. A `TabBar` tem defeito conhecido de 1px de barra de rolagem vertical (pauta, ADR 0403). O contorno
   (`nav[aria-label="Sub-navegação"]{overflow-y:hidden}`) **não** foi aplicado, por ordem expressa de
   não acrescentar nada.

**Não tocado, declarado:** o `nav.cli-moduletopnav` **dentro do drawer** (L466, abas do detalhe do
produto) continua como estava. Mesmo caso, tela diferente do índice.

### 5.6 Onda 0e — controles do widget Filtros (autorizada, ainda não rodada)

&gt; *"E os filtros da tela estão de acordo com o ds/template?"* — Medido: **não.**

O widget "Filtros" fica (decisão da tela, já fechada). Os **controles** dentro dele são HTML cru com
CSS da tela: sete `<select>` e um `<input type="checkbox">` estilizados por `.pb-fld`
(`produto-blade.css` L24-38), quando o DS tem `[DS]` `Select` (`_ds_bundle.js` L4546) e `Checkbox`
(L2511) no bundle já carregado. **Mesmo caso do `.pb-kpi` da onda 0c: componente do DS recriado na
tela.**

**Muda:** os oito controles. **Não muda:** o widget recolhível, o título "Filtros", a grade de quatro
colunas, os rótulos, a ordem dos campos e o botão de limpar. O `.pb-fld` continua servindo os
formulários do módulo (cadastro, edição em massa), que não são desta onda.

**Teste de aceite:** abrir o widget → cada campo com rótulo em caixa alta, altura e anel de foco do DS;
nenhum `<select>` sem componente sobra na região de filtros.

**Item vizinho, NÃO incluído:** o campo de busca da toolbar. O `Input` do DS **não tem slot de ícone**
(proposta P1 já aberta na pauta), e é por isso que a busca é desenhada à mão **aqui e no template**.
Fica como está.

---

## §6 Dívida 0b — tokens de espaçamento e rampa (adiada com condições)

**Pergunta da Maiara:** *"Vai me gerar retrabalho depois? Atrasar alguma modificação? Recomendação
sincera e realista."*

**O que está adiado:** os itens 4.1, 4.2 e 4.3 — trocar os espaçamentos em pixel pelas variáveis
`--d-cpad-x`/`--d-cpad-y`/`--d-tb-y`, fazer o segmented de densidade **escrever esses tokens no shell**
em vez de ligar a classe `.pb-dense`, e passar os tamanhos de fonte fixos para a rampa `--fs-1..9`.

**Por que adiou:** o `produto-blade.css` serve **11 telas** do módulo Produto. A mudança altera as 11
juntas, não tem ganho visível na lista, e entraria misturada com a absorção — se uma tela de cadastro
quebrasse, não haveria como saber qual mudança quebrou.

**Condições combinadas:**

1. A 0b roda **sozinha**, numa rodada em que nada mais está mexendo no módulo.
2. **Todo CSS novo escrito no módulo já usa as variáveis** — para a fila da 0b não crescer.

**Defeito que fica aberto até ela rodar, e a usuária vê:** o segmented **Compacto** promete adensar a
tela e adensa **só a tabela** — a moldura em volta (corpo, toolbar, chips, rodapé) continua com o
espaçamento largo, porque esses valores estão em pixel e a `.pb-dense` só reescreve parte deles. **Não
é pureza de design system: é botão que entrega metade do que promete.**

**Cuidado a levar para a 0b, que já custou retrabalho antes:** copiar template não é copiar trechos. O
`min-height:100vh` do shell do PT-01 só funciona porque ele passa `height` ao `DataTablePro`; com
`DataTable` (sem essa prop) o mesmo CSS faz o documento rolar e o cabeçalho fixo sair da tela, **sem
erro no console**.

---

## §7 As questões abertas de DS / template / shell

São estas que motivam o envio ao agente de código. **Nenhuma deve ser resolvida por iniciativa
própria.**

### Item 1 · O cabeçalho de módulo não é o `PageHeader` do DS `[DECIDIR]`

**Estado medido.** O alvo monta `M.Header` (`modulo-padrao.jsx` L18), cujo próprio comentário o define
como "o equivalente do JanaHeader". Anatomia: `modulo` · `papel` · `contexto[]` · `atualizadoAs`
(clicável, reapura) · `glyph` · `acoes`.

O `[DS]` `PageHeader` existe no bundle carregado (`_ds_bundle.js` L5158; export L9169) com outra
anatomia: `title` · `subtitle` · `stats` · `actions`. O `[TPL]` o monta dentro de
`<div style="padding:0 var(--d-cpad-x)">` (`Pt01Lista.dc.html` L34-36).

**Raio — lista fechada** (varredura de `M.Header &&` no projeto): **nove** telas do protótipo montam o
mesmo `M.Header` — `produto-blade` · `venda-blade` · `venda-index` · `crm-blade` · `crm-portal` ·
`compras-extras` · `financeiro-legado` · `integra-extras` (WooCommerce e Restaurante).

**Por que não foi corrigido na tela.** Trocar só na tela de produtos criaria duas gramáticas de
cabeçalho no mesmo shell — a tela ficaria "certa" pelo template e **órfã dentro do produto**.
Divergência autoriza pergunta, não ação.

**A pergunta, com duas saídas** (a terceira foi retirada — ver §9):

1. O `M.Header` do shell passa a **compor o `PageHeader` do DS por dentro**, mantendo a assinatura
   atual (`modulo`, `papel`, `contexto`, `atualizadoAs`, `onRefresh`, `glyph`, `acoes`) para que
   **nenhuma das nove telas mude de chamada**: `title` recebe módulo e papel, `stats` recebe o
   contexto, `actions` recebe as ações da tela. Uma mudança, nove telas.
2. O `M.Header` fica como **exceção assinada**: cabeçalho de módulo é contrato do shell, e o
   `PageHeader` do DS vale para telas que não vivem nele. Registrado, e nenhuma auditoria futura o
   marca como gap.

**Três consequências medidas da saída 1** — a decisão precisa delas:

1. **A placa do glyph desaparece.** O `PageHeader` aceita `title`/`subtitle`/`stats`/`actions` — **não
   tem prop de ícone**. A tela React em produção também não tem glyph, então a perda é
   **conformidade**, não defeito. Se o glyph for identidade a preservar, aí **sim** é proposta ao DS
   (`glyph` no `PageHeader`). `[DECIDIR]` — item 2 abaixo.
2. **O `stats` corta em 56ch** (`_ds_bundle.js` L5197: `maxWidth:'56ch'` + ellipsis). O contexto atual
   do protótipo tem quatro itens — "OFFICEIMPRESSO · matriz · 12 produtos · 4 grupos de preço ·
   papel: Administrador" — e **seria truncado**. Recomendação: cair para três —
   `12 produtos · 4 grupos de preço · matriz` — porque **"papel: Administrador" já aparece no rodapé da
   sidebar**.
3. **"Atualizado às" deveria virar texto, não botão:** horário no `stats` (é contexto) e "Atualizar"
   dentro do `⋯` (é ação). O padrão da tela React é **uma** ação primária visível + `⋯` — inclusive
   "Baixar Excel" mora dentro do `⋯` lá, não solto no cabeçalho.

**Teste de aceite da saída 1:** as nove telas continuam chamando `M.Header` com os mesmos props e
renderizam `PageHeader` do DS; nenhuma delas perde linha de contexto nem ação.

### Item 2 · Glyph no `PageHeader` `[DECIDIR]`

Perder a placa roxa com o ícone é conformidade (a tela React não tem glyph), **ou** o glyph é
identidade do protótipo a preservar — e aí é **proposta ao DS**, uma linha na pauta, em vez de perda
silenciosa. Decisão da Maiara. Nada feito.

### Item 3 · Abas por tipo na `TabBar` `[DECIDIR]`

**Pergunta dela:** *"Na tabbar não está aparecendo os tipos de produto porquê? Por que não dá de deixar
os tipos de produto e o relatório de estoque na tabbar?"*

**Por que não apareceram:** a onda 0d trocou a **moldura**, não o conteúdo. As abas do alvo sempre
foram duas — "Todos os produtos" e "Relatório de estoque" — e o recorte por tipo mora no select "Tipo
de produto" do widget Filtros. As seis abas por tipo são da V2, e haviam sido descartadas por
autoridade do alvo.

**Dá para juntar** — nada no `TabBar` impede. O problema é de desenho: **uma barra com dois eixos**.
Cinco abas de tipo filtram a mesma tabela; "Relatório de estoque" **troca a tabela** por outra, com
colunas próprias. Misturadas, quem clica em "Serviços" e depois no relatório não sabe se o recorte
sobreviveu.

**Fato que resolve:** "Relatório de estoque" **já tem porta própria na sidebar**, abaixo de "Novo
produto" — tirá-lo da barra não perde acesso. É também o que a tela React faz: abas por tipo na barra,
outras visões fora dela.

**Recomendação:** seis abas por tipo na `TabBar`, relatório pela sidebar. Se ficar na barra, **com a
condição** de o recorte de tipo atravessar para o relatório e não sumir em silêncio.

### Item 4 · Paginação — recusada, com dívida declarada

A tela não pagina: rola com cabeçalho fixo. Mantido. **Dívida a registrar:** num catálogo grande não há
caminho para a linha 5.001 — rolar 5.000 linhas não é caminho. O `[TPL]` tem slot de footer com
`[DS]` `Pagination` (L79-81) que **não se aplica** por essa decisão.

### Item 5 · `--accent` reescrito em runtime

O sublinhado ativo da `TabBar` usa `--accent`, que o shell reescreve via `localStorage` (seletor de
matiz). **O contrato é `--color-primary`.** Defeito já aberto na pauta; registrado aqui porque agora
uma tela depende dele. **`[RUNTIME]` nunca é regra** — não tratar o valor observado no navegador como
cor da empresa.

### Item 6 · Permissões — os nomes certos existem no legado

Os dois sistemas de permissão não se encostam, e isso é vazio, não conflito:

- `[TELA]` `produto-perms.jsx`: 13 permissões com nomes reais do legado — `unit.*`, `brand.*`,
  `category.*`, `barcode_settings.access` — e 4 papéis. **Todas governam cadastros de apoio.**
  Nenhuma governa preço, custo, composição ou margem.
- `[V2]` `perm()` (L994-999): `custo`, `preco`, `composicao`, `compras`, `margem`, `reposicao`.
- `[REPO]` o blade legado gateia com os nomes que faltam: `@can('view_purchase_price')` (index L287) ·
  `@can('access_default_selling_price')` (L294) · `product.view` (L140) · `product.create` (L176) ·
  `product.delete` (L364) · `stock_report.view` (L150, L192).

**Consequência:** a permissão de custo **não deve ser inventada** — o nome é `view_purchase_price`, e o
de preço é `access_default_selling_price`. `composicao` e `margem` não existem no legado: `margem` é
derivável (preço + custo) e pode herdar de `view_purchase_price`; `composicao` **precisa de decisão**
`[DECIDIR]`.

**Achado de risco, do próprio protótipo** (`produto-perms.jsx` L6-11, achado A-P1): seis controllers do
legado **não têm gate nenhum** — Warranty, VariationTemplate, SellingPriceGroup, Labels,
ImportProducts, ImportOpeningStock. Qualquer usuário logado cria, edita e exclui. **Então "Importar
produtos" e "Etiquetas" não servem de referência de permissão** ao implementar.

### Item 7 · Exportação — três escopos, dois rótulos parecidos

| Gesto | Escopo real | Onde |
| --- | --- | --- |
| "Baixar Excel" (cabeçalho) | `exportarCsv(PRODUCTS)` — **catálogo inteiro**, ignora filtro, aba e seleção | `[TELA]` L1009 |
| "Exportar filtrados" (rodapé) | `exportarCsv(ordenados)` — **o recorte**, na ordem da tela | `[TELA]` L908 |
| "Exportar planilha" (⋯ e paleta) | o recorte | `[V2]` L1364, L1739 |
| "Exportar seleção" (barra) | só os marcados, e limpa a seleção | `[V2]` L1676 |
| "Etiquetas" (barra) | os marcados | `[TELA]` L918 · `[V2]` L1677 |

- Os dois primeiros ficam a poucos centímetros um do outro e **não dizem qual é qual**. Proposta:
  **"Baixar catálogo (Excel)"** e **"Exportar este recorte"**. Isso **renomeia rótulo do alvo** →
  precisa de autorização expressa. `[DECIDIR]`
- **Confirmação no repo, mesmo padrão:** `[REPO]` `Unificado/_components/BulkBar.tsx` L15 declara que
  `/products/download-excel` "exporta o catálogo inteiro e ignora seleção". **A ambiguidade é do
  endpoint, não só do protótipo.**
- **Colunas exportadas:** `[TELA]` L739 são 13 fixas (Produto, SKU, Tipo, Categoria, Subcategoria,
  Marca, Unidade, Imposto, Compra, Venda, Estoque, Locais, Situação) — **inclui "Compra"**, que é
  custo, **sem consultar permissão**. Com o gate de custo aprovado, a exportação passa a ser a porta
  que o contorna. **Teste de aceite:** usuário sem `view_purchase_price` baixa o CSV e a coluna Compra
  **não existe** no arquivo (ausente, não vazia).

### Item 8 · Modelo de dados — o que trava a absorção

Onze campos que só a V2 tem. Cada um trava um item:

| Campo `[V2]` | O que é | Trava |
| --- | --- | --- |
| `naturezaLocal` (L622) — `venda`/`bloqueado` | natureza do local; `vendavel()` soma só `venda`, `bloqueado()` o resto, `fisico()` a soma (L664-678) | subconjunto declarado · rótulo com escopo · "+X reservado" |
| `custodiaPorId` | mercadoria de cliente em garantia — posse sem propriedade, fora do saldo e do valor | subconjunto (o alvo somaria custódia no estoque) |
| `perdasPorId` | baixa como lançamento com janela, não como local | subconjunto |
| `faixasQtd` (L709) + `fatoresFaixa` | preço por quantidade comprada no pai | célula de preço com faixa · limite de alçada por faixa |
| `encomendaPorId` (L722) — `prazo`, `janela`, `minimo` | condição de item sob encomenda | chip "Sob encomenda" |
| `obsPorId` (L682) — `tag` + `critica` | observação operacional, criticidade separada do rótulo | observação com condição declarada |
| `compraPorId` | custo de compra por unidade, com procedência | custo da composição |
| `precoPorMarkup` / `precoAlteradoEm` | procedência do preço (markup × manual, com data) | **nenhum item — candidato novo** |
| `alcada()` (L1017) | percentual de alçada do colaborador | alçada de desconto |
| `parado` / `ultimaVenda` | giro | reposição e giro · KPI "sem venda 90d" |
| `minimo` por combinação (L1610) | mínimo é da combinação, não do pai | subconjunto |

Onze que só o alvo tem, e a absorção **não os toca**: `sub` · `tax`+`taxType` · `barcode` · `weight` ·
`expiry`+`expiryType` · `srNo` · `cf` (personalizados 1..4) · `racks` · `prep` · `profit` por variação ·
`PRICE_GROUPS`.

**Divergência de forma, não de campo:** o alvo guarda saldo em `stock` no produto e `locs` como lista
de ids (L53-79); a V2 guarda `locaisPorId` como pares `[nome, qtd]` (L624). Absorver saldo por local
exige mudar a **forma** do dado, não só acrescentar campo.

### Item 9 · Toque e janela — aqui a V2 está atrás

**Nada a absorver.** Registrado para não ser confundido com melhoria:

| | `[TELA]` alvo | `[V2]` |
| --- | --- | --- |
| Toque | `@media (pointer:coarse)`: campos e segmented 44px, checkbox 22px, botões `min-height:44px`, chip 36px (css L127-136) | **nada** |
| Largura | breakpoints 1180px (KPI e grid → 2 colunas) e 900px (1 coluna, busca 100%) (css L138-143, L250) | **nenhuma media query**; faixa de KPI fixa em `repeat(4, minmax(0,1fr))` |
| Altura curta | piso na região de dados + `overflow:auto` no shell (css L3-4) | rolagem da página, header fixo |

**Consequência:** absorver a faixa de KPI da V2 como está **perderia** o breakpoint de 2 colunas e os
alvos de 44px. O componente do DS entra **dentro do grid responsivo do alvo**. `[DECIDIR]`: a tela
absorvida mantém o contrato de 44px (tablet do técnico)?

### Item 10 · Estados da tela

| Estado | `[TELA]` alvo | `[V2]` |
| --- | --- | --- |
| Carregando | Skeleton do DS, `variant="row" count={8}`, 420ms (L777, L899) | `carregando` por prop (L1782) |
| Vazio por filtro | `EmptyState variant="no-results"` (L356-359) | `vazioVariante`, muda com a causa (L160) |
| Catálogo vazio | **não existe** — cai em `no-results` | `first` |
| Erro do servidor | **não existe** | `variant="error"` (L1785-1789) |
| Sem permissão | **não existe** na lista | `variant="no-perm"` |
| Rodapé sem dados | segue montado | `mostraRodape` só com dados (L1809) |

**Achado de mecanismo:** o alvo **tem** a prop `estado` (L956) e a repassa às sub-telas de importação e
cadastros (L1030-1032), mas **não** a `TelaLista` (L1022) — a lista é **a única tela do módulo que não
sabe representar erro nem falta de permissão**.

### Item 11 · Acessibilidade

**Só o alvo tem, e está correto:** `role="menu"`/`menuitem` (L196-199) · `role="dialog"`+`aria-label` no
modal (L218) · `nav aria-label` nas duas barras de abas (L466, L867) · `role="group"` no segmented
(L879) · `aria-hidden` na arte do código de barras (L684) · `aria-label="Dispensar"` (L1020) · passa
`title` ao `Drawer` do DS (nome acessível **já resolvido** — não "corrigir") · `<h3>` nos sub-títulos
do drawer, onde a V2 recorre a `role="heading"`.

**Só a V2 tem — candidatos:** `aria-keyshortcuts="/"` + `aria-label="Buscar produtos"` (L944-946), já
que no alvo o `/` é só um `kbd` visual (css L100) · `role="img"` + `aria-label="Produto sem imagem"` no
espaço da foto (L654), contra o `.pb-thumb` que é um `div` com o texto "IMG" (css L69) — **lido como
conteúdo**.

**Não medido em nenhuma das duas — não afirmo, não proponho:** `aria-live` no toast · ordem de
tabulação completa · contraste medido dos badges em tema escuro.

### Item 12 · Dados extremos — lacuna declarada

**Nenhuma das duas foi exercitada** em: nome de 120 caracteres · saldo negativo (devolução lançada
antes da entrada) · unidade fracionária (`0,375 m²`) · catálogo de 5.000 itens. O alvo não pagina e
calcula altura em 72% do corpo; a V2 pagina de 10 em 10 no cliente. **Lacuna, não divergência.**

### Item 13 · Defeito de origem do shell, candidato a pauta `[DECIDIR]`

`produto-blade.css` L264-268 corrige **dentro do módulo** um `.os-btn.danger` do `superadmin-page.css`
que vencia `.os-btn.ghost.danger` e deixava texto vermelho sobre fundo vermelho (~1,6:1). O contorno
está declarado no próprio arquivo. Levar à `pauta-design-system.md` como defeito de origem do shell?

---

## §8 Proibições explícitas

Valem para quem continuar o trabalho, agente ou pessoa. Regra ausente daqui **não** é proibição — o que
está pendente no §7 simplesmente **não deve ser preenchido**.

1. **Não substituir componente do DS por versão local.** Contornar na tela, ou registrar proposta na
   pauta. Recriar componente localmente está descartado.
2. **Não renomear rótulo do alvo** sem autorização expressa — vale para "SKU", "Baixar Excel",
   "Exportar filtrados", "Disponível nos locais".
3. **Não inventar, calcular, converter ou substituir valor do DS.** Transcrever literal do DS é
   citação, e é permitido. O proibido é derivar número novo e apresentá-lo como medição.
4. **Não tratar `[RUNTIME]` como regra** — `--accent` é reescrito por `localStorage`; o contrato é
   `--color-primary`.
5. **Não completar lista fechada** com itens do legado.
6. **Não corrigir tensão medida em silêncio.** Valor do DS que não serve ao contexto se aplica como
   está e vai para a pauta.
7. **Não aplicar o cabeçalho do DS só nesta tela** — são nove, e a decisão é do shell.
8. **Não usar "Importar produtos" nem "Etiquetas" como referência de permissão** (controllers sem gate).
9. **Não copiar trechos de template sem conferir de que outra parte dele o valor depende** (caso
   `min-height:100vh` + `height` do `DataTablePro`).
10. **Não escrever CSS novo no módulo com espaçamento em pixel** — usar as variáveis, para a fila da 0b
    não crescer.

---

## §9 Erros do agente nesta sessão, e o que cada um invalidou

Declarados porque três deles produziram documento errado que pode ter circulado.

| Erro | O que invalidou |
| --- | --- |
| Apontei o alvo para o repo React e escrevi uma errata inteira sobre isso — **a errata estava errada**, o alvo é o protótipo | uma errata publicada, retratada no §-1 do doc de absorção |
| Afirmei que o `PageHeader` do DS **precisava ganhar** linha de contexto e "atualizado às". Medido depois (L5158-5216): ele **já** tem `stats` e `actions` | a terceira saída da pergunta ao shell — **proposta retirada antes de virar pedido** |
| Recomendei `KpiFilterCard` onde ele entregaria quatro botões mortos | a recomendação escrita no §R; corrigida ao aplicar |
| Contei **2** quebras de piso tipográfico onde havia **5** | a contagem do §10 do doc de absorção |
| Entreguei a auditoria de fidelidade **sem cabeçalho e sem abas** — a usuária achou antes de mim | a completude do eixo 4 |

**Padrão comum aos cinco: parar de medir cedo e escrever conclusão como se fosse medição.** Duas
propostas falsas ao DS nasceram assim.

---

## §10 Estado no fim da sessão

**Aplicado no protótipo:** onda 0 (piso, moldura, cursor) · onda 0c (KPI pelo `KpiCard`) · onda 0d
(abas pelo `TabBar`). Console sem erro nas duas últimas.

**Autorizado, a rodar:** onda 0e (controles do widget Filtros).

**Adiado com condições:** onda 0b (tokens de espaçamento e rampa; 11 telas), com o defeito do botão
Compacto declarado no §6.

**Ordem acordada das ondas seguintes:**

| Onda | Conteúdo |
| --- | --- |
| 0 · 0b · 0c · 0d · 0e | fidelidade (0b adiada) |
| 1 · lógica, em paralelo | aria do `/` · erro/sem-permissão + vazio por causa · recentes na paleta · consequência do estado · permissões + gate do CSV · custo da composição · montáveis |
| 2 · sobre a moldura correta | KPI clicável · KPI que não recorta desaparece · totais com escopo · exportar seleção |
| 3 · teclado e recorte | ↑/↓ na grade · ◂▸ no drawer · recorte persistido |
| 4 · dado | subconjunto declarado · rótulo com escopo · observação com criticidade |

**Esperando decisão da Maiara:** cabeçalho (compor ou exceção) · glyph no `PageHeader` · abas por tipo
na `TabBar` · `composicao` herda de `view_purchase_price`? · renomear os botões de exportação ·
contrato de 44px · defeito do `.os-btn.danger` vai à pauta? · e a marcação dos 21 itens de absorção
com a matriz 9×4 de permissões.
