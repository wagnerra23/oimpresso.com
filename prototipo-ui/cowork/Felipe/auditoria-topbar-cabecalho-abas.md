# Auditoria — Topbar (B-03), Cabeçalho da página (B-04) e Abas (B-05)

> **Posterior a esta auditoria (27/08/2026):** a **topbar foi retirada do produto** por decisão da Maiara,
> com o design system e o `templates/pt-01-lista` já atualizados — o `<main>` começa no `PageHeader`.
> Tudo o que este documento mede sobre a faixa (§1 itens 1-5, D5, V1) é **histórico**: trilha, campo de
> busca global e botão de ajuda não existem mais. A paleta ⌘K continua, só por teclado. O bloco B-03 foi
> aposentado no manual; §2 (D1, D2, D3), §4 e as notas de abas seguem valendo.

Tela: `Consulta de Produtos.dc.html` · Data: 27/08/2026 · Base: DS `OfficeImpressoDesignSystem_49a36f`
(`_ds/…-49a36f76…/`), template canônico `templates/pt-01-lista/Pt01Lista.dc.html`, `manual-de-blocos.md` v1.

Procedência: **[DS]** citado do componente/folha · **[TPL]** citado do template · **[TELA]** decidido aqui ·
**[RUNTIME]** medido no navegador (viewport 835 px, densidade **compacta** — `--d-cpad-x: 14px`, `--d-fontsz: 13px`, dpr 1.44).

Medições feitas por leitura do DOM/`getComputedStyle` na própria tela. Listas abaixo são **fechadas** (item
não listado = não auditado, e está dito no §5).

---

## 1 · Conforme — nada a fazer

| # | Item | Medido [RUNTIME] | Contrato |
|---|---|---|---|
| 1 | Faixa da topbar | altura **45,99 px**, `padding 0 14px`, borda inferior 1 px `--border`, fundo `--surface` | 46 px / `0 var(--d-cpad-x)` / `--surface` — B-03 item 1 `[TPL]` L32 |
| 2 | Trilha | `Breadcrumb` do DS, `400 12px/1.4` IBM Plex Sans, altura 21 px, último item 600 sem link | B-03 item 2 · `[DS]` `_ds_bundle.js` L2077-2124 |
| 3 | Geometria da busca global | altura **30 px**, largura **267 px** (teto `32vw` ativo), fundo `--bg-2`, raio `--radius-md`, lupa 14 px, `<kbd>⌘K</kbd>` mono 10 px | B-03 item 3 `[TPL]` L34-38 |
| 4 | Paleta ⌘K | `Ctrl/Cmd+K` em `document` (L968) abre o `Command`; `placeholder="Buscar em tudo…"` = rótulo do gatilho; o `Command` **limpa o campo e foca** ao abrir e devolve o foco ao fechar (`_ds_bundle.js` L2590-2602) | B-03 itens 3-4 + teste 1 ✔ |
| 5 | Botão Ajuda | 30 × 30, borda 1 px `--border`, `--surface`, `--text-dim` | B-03 item 5 `[TPL]` L39 |
| 6 | Anel de foco | **coberto pelo DS**, não pela tela: `:where(button, a[href], [role=button], [tabindex]:not([tabindex="-1"])):focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px }` e, sob `.cockpit`, `outline-color: var(--accent)` — `colors_and_type.css` L417-423. Vale para busca, ajuda, `⋯`, Novo, tema e todas as abas | acessibilidade ✔ |
| 7 | Título | um único `h1`, `600 22px/1.3` (28,6 px de linha), `letter-spacing -.015em`, `--text`, com `ellipsis` | B-04 itens 1 e teste 1 · `[DS]` L5180-5190 |
| 8 | Ação primária única | só **Novo** é `variant="primary"` (26 px, `600 12px`, `--accent`/`--accent-fg`); tema é `ghost` | B-04 itens 3 e 5 |
| 9 | Natureza do menu `⋯` | itens são apresentação/dados (densidade, colunas, exportar, imprimir) — **nenhum navega** | B-04 item 4 + teste 3 |
| 10 | Abas | `TabBar` do DS; rótulos sentence case; contadores **derivados** de `porAba()`, pílula mono `600 10.5px`; ativa = sublinhado 2 px `oklch(0.55 0.15 295)` (= `--accent` = `--color-primary`) + fundo `--accent-soft` a 50 %, peso 600, `--text`; inativa 500 `--text-dim` com hover de cor + fundo | B-05 itens 1-2 e 4 + teste 2 |
| 11 | Troca de aba | `trocarAba` (L1751) zera `pagina`, limpa `sel`, limpa `kpi` e o filtro de tipo → KPI são reavaliados | B-05 item 3 |
| 12 | Overflow do título e das ações | `h1` com `white-space:nowrap; overflow:hidden; text-overflow:ellipsis`; bloco de ações `flex:0 0 auto` (não comprime) | B-04 · responsividade |

---

## 2 · Defeitos da tela — **rodados em 27/08/2026**

Decisões da Maiara nesta rodada: D1 vira remoção (cabeçalho limpo), V1 vira campo real que abre a
paleta, D4 **não é defeito** (claro é o padrão do produto — exceção assinada, B-01 item 3 atualizado),
e o acento passa a ser escrito por tema.


### D1 · `stats` removidos — B-04 item 2 (v2)  ·  ✔ feito

**Está:** `headerStats: [{ value: this.dados.length, label: 'cadastrados' }]` (L1825) → **"14 cadastrados"**
[RUNTIME]. O número é o total da base: não muda ao trocar de aba, KPI, faceta ou busca. E repete o contador
da aba "Todos" (14), o que a **dependência do B-04** proíbe ("o cabeçalho não repete o recorte das abas").

**Ficou:** cabeçalho sem `stats` — título + ações. Levantamento que levou a isso: contagem do recorte
já está na toolbar ("13 registros", B-07 item 5), contagem por recorte nas abas (B-05 item 4), alarmes
nos KPI (B-06) e valor em estoque no rodapé (B-11). Não sobrava número novo. `stats` é opcional no
componente (`_ds_bundle.js` L5192) e o B-04 v2 passa a proibir repetir número de outro bloco.

**Como conferir:** o cabeçalho não tem linha de números; nenhum número da tela aparece duas vezes.

### D2 · Gatilho `⋯` do cabeçalho a 26 px — B-04 item 3 (v2)  ·  ✔ feito

**Está:** [RUNTIME] ações do cabeçalho medem **tema 26 px · `⋯` 30 px · Novo 26 px**. `Button size="sm"` do DS
é `H = 26` (`_ds_bundle.js` L2256), e `gatilhoIcone()` (L1183) crava `width: 30, height: 30`. São 4 px de
desalinhamento vertical entre irmãos da mesma linha de ações.

**Deve ficar:** `width: 26, height: 26` no gatilho do **cabeçalho** (o `⋯` da **linha da tabela** continua
30 px — ele convive com célula, não com `Button size="sm"`), ícone 15 px. Nada mais do gatilho muda: o
`<button>` transcrito segue necessário por `aria-haspopup`/`aria-expanded` (pauta: *`Button` não repassa
`aria-*`*, P2).

**Como conferir:** medir os três filhos do bloco de ações → 26 px cada.

### D3 · Nome acessível nos gatilhos `⋯` — acessibilidade  ·  ✔ feito

*Onde está na tela:* é o botão à direita do cabeçalho, entre "Claro" e "Novo". O glyph são **três
linhas horizontais**, não três pontos — o `Icon` do DS não tem `ellipsis` (defeito já na pauta), então
ele parece um ícone de menu. Mantido: guarda densidade, colunas, exportar e imprimir (B-04 item 4).

**Está:** [RUNTIME] `aria-label: null`, `title: null` nos dois gatilhos `⋯` (cabeçalho e linha). Botão só de
ícone sem nome: leitor de tela anuncia "botão".

**Deve ficar:** `aria-label="Mais ações desta tela"` no do cabeçalho e `aria-label="Ações do produto"` (ou
o nome da linha) no da tabela. Não acrescentar `title` — o `Tooltip` do DS é o caminho se se quiser dica visual.

**Como conferir:** `document.querySelectorAll('button[aria-haspopup="menu"]')` → todos com `aria-label`
não vazio.

### D4 · Tema padrão claro — **não é defeito**, exceção assinada

**Está:** `state.tema = 'light'` (L589); `[RUNTIME]` `--text: oklch(0.22 0.01 80)` (claro). O B-01 item 3 diz
"**escuro é o padrão do produto**" `[TPL]` L132-134.

**Ficou:** claro segue o padrão, escuro disponível (decisão da Maiara, 27/08). O B-01 item 3 foi
atualizado com a exceção assinada. Em troca, entrou o que faltava de verdade: **o acento por tema** —
`aplicaTema()` escreve `--accent`, `--accent-2`, `--accent-soft` e `--color-primary` com L `0.55` no
claro e `0.72` no escuro, transcrito de `applyTheme` L135-140 do PT-01. Sem isso, ao ligar o escuro o
roxo ficava no valor do claro (o DS só troca `--accent-soft` em `.cockpit[data-theme="dark"]`,
`colors_and_type.css` L337).

**Como conferir:** no escuro, `getComputedStyle(document.querySelector('.cockpit')).getPropertyValue('--accent')`
retorna `oklch(0.72 0.15 295)`; o botão Novo e o sublinhado da aba clareiam.

### D5 · Hover igual no `?` e na busca — B-03 item 6 (v2)  ·  ✔ feito

**Está:** o `?` não tem `onMouseEnter`/`onMouseLeave` (L76), enquanto o `⋯` vizinho troca fundo, cor e borda.
Dois botões-ícone de 30 px na mesma faixa, um reage e o outro não. (O `[TPL]` L39 também não tem — o
template é omisso, não normativo aqui.)

**Deve ficar:** mesma transição do `gatilhoIcone`: fundo `--bg-2`, cor `--text`, borda `--text-mute`,
`transition .15s`. Vale também para o gatilho da busca global, que hoje só muda o cursor.

**Como conferir:** hover no `?` e no gatilho de busca → fundo e borda mudam em 150 ms.

---

## 3 · Divergência de contrato — precisa de decisão, não de correção

### V1 · Busca global volta a ser campo real — B-03 item 3 (v2)  ·  ✔ decidido e feito

O B-03 item 3 descreve um **campo** (`<label>` + `<input>` + lupa + `kbd`), copiado do `[TPL]` L34-38. A tela
renderiza um **`<button>`** com a mesma geometria, que abre o `Command` (B-03 item 4). Digitar no topbar é
impossível: o clique abre a paleta.

**Decidido (Maiara, 27/08):** vale a opção que respeita template **e** componente — o markup do template
(`<label>` + `<input>` + lupa + `kbd`) volta, e focar o campo (clique ou Tab) abre o `Command`, que é a
busca global do DS. Regra geral daqui pra frente: quando template e componente divergem, a solução é a
que honra os dois, não a que escolhe um.

Duas consequências registradas: guarda de reentrada de 300 ms na tela, porque o `Command` devolve o foco
ao campo ao fechar (`_ds_bundle.js` L2602) e sem ela a paleta reabre em laço; e proposta **P1** na pauta —
`Command` sem `initialQuery`, que é o que impediria abrir a paleta já com o texto digitado.

### V3 · Contraste do primário no escuro: 2,61:1 — aberto na pauta

Achado que a própria rodada de 27/08 introduziu. Com o acento por tema (B-01 item 4), o escuro passa a
L `0.72`; o `--accent-fg` continua branco e o `Button variant="primary"` mede **2,61:1** — abaixo do AA
de 4,5:1 para rótulo de 12 px/600. No claro o mesmo par dá **5,17:1**. Atinge também a pílula de
contador da aba ativa (`TabBar` usa `--accent` com `--accent-fg`).

Valor mantido como está — o template e o DS ganham. Registrado em `pauta-design-system.md` como defeito
de origem, com as duas saídas possíveis (fg escuro no escuro, ou acento em L ≤ 0.62).

### V2 · Anel de foco: 2 px no DS, "3 px" na prosa do guia

O guia do DS diz `focus-visible:ring-[3px] ring-ring/50`; a folha do espelho escreve `outline: 2px solid` +
`outline-offset: 2px` (`colors_and_type.css` L417-419). Contradição **interna do DS**. Aplicado o valor da
folha (2 px), sem mexer em nada. Registrado para o DS decidir — a tela não escreve anel de foco próprio.

---

## 4 · Medições sobre responsividade e overflow

| # | Achado [RUNTIME] | Situação |
|---|---|---|
| 1 | **Abas com rolagem horizontal a 835 px:** `scrollWidth 617` > `clientWidth 527`; a barra de rolagem ocupa **11 px** e a faixa passa de 36 px para **46 px** de altura | comportamento previsto do `[DS]` (`overflow-x: auto`); ≥ 1024 px as 6 abas cabem |
| 2 | **1 px do sublinhado da aba ativa é cortado:** `clientHeight 35` < botão `36`, e o contorno `nav[aria-label="Sub-navegação"] { overflow-y: hidden }` (ADR 0403) recorta o `margin-bottom:-1px` do botão | efeito colateral **medido** do contorno já declarado em B-05; só aparece quando a barra horizontal existe. Registrar em B-05 como nota do contorno — não inventar segundo contorno |
| 3 | Topbar não quebra linha (`flex-wrap` ausente): busca encolhe por `max-width:32vw`, ajuda fica em 30 px | conforme `[TPL]`; abaixo de ~560 px a trilha começa a comprimir a busca — sem tratamento mobile (a tela é desktop por decisão; mobile é projeto separado, `manual-de-blocos.md` §1) |
| 4 | `hint-size` do `PageHeader` diz `100%,74px`; a altura real é **80 px** (14+14 de padding + 28,6 do `h1` + 4 + 18,9 do stat) | só placeholder de streaming (B-01 armadilha 3) — ajustar para 80 px evita o salto ao carregar. Idem abas: `37px` declarado, 36 px reais (46 com barra) |

---

## 5 · Fora do escopo desta auditoria (declarado)

Sidebar (B-02), KPI-filtros (B-06), toolbar de recorte (B-07), tabela (B-08/B-09), paginação, painel de
detalhe (B-12), seleção em lote (B-10), `Toast`/`EmptyState`. Nada foi verificado nesses blocos aqui.

Também não auditados: contraste medido par a par (só o uso de token foi conferido), navegação por teclado
**dentro** da `TabBar` (o `[DS]` usa `nav` + `aria-current`, sem `role="tablist"` nem tabindex móvel — é
navegação, não widget de abas ARIA, e está coerente com o componente), e impressão.

---

## 6 · Consequências para os documentos

- **B-04 → v2:** acrescentar ao item 3 que o gatilho de menu do cabeçalho tem a **altura do `size` das
  ações irmãs** (26 px com `sm`), e um item de nome acessível obrigatório em botão só-ícone. (D2, D3)
- **B-03 → v2** se V1 for decidido a favor do gatilho de paleta.
- **B-05:** nota do contorno ADR 0403 — quando a barra horizontal existe, 1 px do sublinhado é recortado.
- **`pauta-design-system.md`:** V2 (anel de foco 2 px vs. 3 px da prosa) como **D** (defeito de origem).
- Nenhuma proposta nova de prop: os limites encontrados (`Button` sem `aria-*`, `Input` sem ícone) já estão
  na pauta.
