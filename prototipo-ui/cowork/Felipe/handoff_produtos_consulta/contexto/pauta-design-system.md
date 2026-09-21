# Pauta para o design system — propostas abertas

Levantado na tela **Consulta de Produtos** (protótipo unificado v2). Nada aqui bloqueia a tela:
todas têm contorno em pé. São lugares onde o contorno é inferior à solução canônica.

Classificação: **P1** vale propor já · **P2** esperar um segundo caso · **D** defeito, não recurso.

---

## D · `DrawerSection`: divisor entre blocos invisível no tema escuro

**Hoje:** `_ds_bundle.js` L3911-3914 — `DrawerSection` desenha `borderTop: 1px solid var(--border-2)`.
No tema escuro do shell, `colors_and_type.css` L331/L333: `--surface: oklch(0.30 0.008 240)` e
`--border-2: oklch(0.31 0.008 240)` — 0,01 de lightness de diferença. O divisor existe no DOM e
não é perceptível (no claro, `--surface #fff` vs `--border-2 0.93` separa bem).

**Efeito na tela:** Grade, Preço e margem, Reposição, Estoque, Giro, Identificação e Observações
aparecem como um fluxo contínuo, sem régua entre blocos (apontado pelo Felipe na revisão).

**Contorno na tela:** nenhum. O valor do DS foi aplicado como está — separar por conta da tela
seria inventar régua onde o componente já define uma.

**Proposta:** subir `--border-2` no `.cockpit[data-theme="dark"]` para `oklch(0.36 0.008 240)`
(≈ +0,06 sobre a superfície, o mesmo contraste relativo que o tema claro entrega), ou fazer
`DrawerSection` usar `--border` no escuro.

**Teste de aceite:** abrir o drawer no tema escuro — deve haver uma linha visível entre cada dois
`DrawerSection` consecutivos, medida com contraste ≥ 1,2:1 contra `--surface`.

**Prioridade:** D — o token derruba um divisor que o componente pediu.

---

## P1 · `DataTable` sem densidade

**Hoje:** o padding da célula é fixo no componente (`10px 12px`). Sem prop de densidade.

**Contorno na tela:** o modo compacto colapsa o *conteúdo* das células (segunda linha de Produto
e de Disponível) em vez de reduzir o espaçamento. Ganho de altura parecido, mas o preço é
informação escondida, não ar removido.

**Proposta:** `density: 'comfortable' | 'compact'`, default `comfortable` (nada quebra).

**Por que é a mais forte:** serve a toda tela de lista do ERP; o `DataTablePro` já tem a mesma
prop com esse vocabulário, então não há nome novo a inventar; e resolve o caso real dos clientes
que operam em monitor pequeno (1280×720 passa de ~9 para ~13 linhas visíveis).

## P1 · `Drawer` sem variante ancorada

**Hoje:** abre sobreposto, com scrim. Em 1280px cobre metade da área útil e a lista sai de vista.

**Contorno:** nenhum honesto. Recriar o Drawer localmente foi descartado — perderia correção de
acessibilidade, tokens e paridade visual. A pendência ficou aberta.

**Proposta:** `mode="docked"` — empurra o conteúdo em vez de cobrir, sem scrim.

**Escopo:** não é uma prop pequena. Muda layout, foco, comportamento responsivo e o contrato do
scrim. Merece ADR com desenho antes de código.

## P2 · `DrawerSection` não colapsável

**Hoje:** renderiza título + conteúdo, sem estado aberta/fechada.

**Contorno na tela:** barra fixa de atalhos no topo do drawer (Venda · Composição · Grade ·
Preço · Reposição · Estoque), que rola até a seção. Resolve *achar* a seção; não reduz a
altura — o painel de um kit tem ~3,5 telas de rolagem.

**Proposta:** `collapsible` + `defaultOpen`.

**Por que P2:** o contorno atende o problema real (navegação). Esperar um segundo caso pedindo o
mesmo antes de propor.

## D · `TabBar`: 1px de barra de rolagem vertical (ADR 0403)

Declara `overflow-x: auto`; o navegador força `overflow-y: auto` e, com botões de 36px numa caixa
de 35px, sobra 1px — aparece barra vertical. **Não é suprimível de fora** (o overflow do ancestral
não afeta o descendente que rola). A tela carrega um override no `<helmet>` mirando
`nav[aria-label="Sub-navegação"]` até a altura ser corrigida na origem.

## D · `--accent-fg` não acompanha o acento clareado do escuro — 2,61:1 no botão primário

**Estado atual.** O `[TPL]` `applyTheme` (`Pt01Lista.dc.html` L135-140) clareia o acento no escuro
(L `0.55` → `0.72`), e o DS **não** reescreve `--accent-fg` em `.cockpit[data-theme="dark"]`
(`colors_and_type.css` L328-341 só troca `--accent-soft` e os tons de status). O texto do botão
primário continua branco.

**Medido (Consulta de Produtos, 27/08, escuro).** `Button variant="primary"`: `rgb(255,255,255)` sobre
`oklch(0.72 0.15 295)` = `rgb(172,143,248)` → **2,61:1**. Rótulo 12 px/600 exige 4,5:1 (AA). O mesmo par
no claro dá **5,17:1**. Antes de a tela aplicar o acento por tema, o escuro usava 0.55 e passava — ou
seja, cumprir o template é o que reprova o contraste.

**Escopo.** Todo elemento com preenchimento `var(--accent)` e texto `var(--accent-fg)` no escuro —
inclui a pílula de contador da aba ativa (`TabBar`, `_ds_bundle.js` L6665-6672) e o botão primário.

**Valor aplicado como está** (DS/template ganham, CLAUDE.md). Nada alterado na tela.

**Proposta (para o DS decidir).** Ou `--accent-fg` escuro passa a ser texto escuro
(`oklch(0.22 0.01 80)` sobre `rgb(172,143,248)` → ~8,3:1), ou o acento do escuro recua para L ≤ 0.62
com fg branco. Uma das duas, não as duas.

**Teste de aceite:** no escuro, o par preenchimento/texto do `Button primary` mede ≥ 4,5:1.

## `Command` sem `initialQuery` — o campo do topbar não entrega o que foi digitado  ·  P1

**Estado atual.** `Command.jsx` (`_ds_bundle.js` L2589-2602) guarda a busca em estado interno e faz
`setQ('')` a cada abertura; não há prop de texto inicial nem `onQueryChange`.

**Consequência medida (Consulta de Produtos, 27/08).** O B-03 pede campo real no topbar e o DS entrega
a busca como paleta. Com o campo, o melhor possível hoje é abrir a paleta **ao focar**, antes de
qualquer tecla — se a abertura fosse no primeiro caractere, esse caractere se perderia, porque não há
como passá-lo adiante. Some-se a isso o retorno de foco ao fechar (L2602), que exige guarda de
reentrada de 300 ms na tela.

**Proposta.** `initialQuery?: string` (semeia `q` na abertura) e, opcionalmente,
`onQueryChange?(q)`. Com isso o campo do topbar passa a entregar a digitação, e a paleta deixa de
precisar de guarda de tempo na tela.

**Teste de aceite:** `<Command open initialQuery="lona" />` abre já filtrando por "lona", com o
cursor no fim do texto.

## D · Anel de foco: a folha diz 2px, a prosa do guia diz 3px

`colors_and_type.css` L417-419 escreve `outline: 2px solid var(--color-primary); outline-offset: 2px`
para `button, a[href], [role="button"], [tabindex]:not([tabindex="-1"])`, e L421-423 troca a cor por
`var(--accent)` sob `.cockpit`. O README do DS descreve o mesmo estado como
`focus-visible:ring-[3px] ring-ring/50`. **Contradição interna do DS** — dois números para o mesmo
contrato. Aplicado o valor da folha (2px), que é o que renderiza; nada alterado na tela, que não
escreve anel próprio. Medido na Consulta de Produtos em 27/08 (auditoria B-03/B-04/B-05, §3 V2).

## D · `TabBar`: com barra de rolagem horizontal, o contorno do ADR 0403 recorta 1px do sublinhado

Extensão medida do defeito acima. A 835px de viewport, a `TabBar` da Consulta de Produtos tem
`scrollWidth 617` contra `clientWidth 527`: a barra horizontal ocupa 11px e o `clientHeight` cai
para 35px, menor que o botão de 36px. Com o contorno `overflow-y: hidden`, 1px do
`border-bottom: 2px` da aba ativa (posicionado por `margin-bottom: -1px`) fica cortado. Não há
segundo contorno a inventar — corrigir a altura na origem resolve os dois.

## D · `Icon`: falha silenciosa e falta de glyph de "mais ações"

1. Nome fora do mapa `ICONS` renderiza um `<span>` vazio do tamanho pedido, **sem erro** — o que
   me levou a concluir errado, numa onda anterior, que o wrapper não suportava os ícones
   necessários. Um `console.warn` no default evitaria o diagnóstico errado.
2. Não há `ellipsis` / `more-horizontal`. Os menus de overflow usam `menu` (três linhas
   horizontais), que é menos expressivo para ações de linha.
3. Não há glyph de **imagem** (`image` / `photo` / `camera` / `file-image` — todos testados,
   todos renderizam vazio). A miniatura de produto sem foto usa `package`, que é do pacote e
   funciona como marcador de item, mas não comunica "falta imagem". Catálogo de produto com foto
   é caso comum nos clientes: vale um `image` no pacote.

## `DataTable`: célula com nó React perde o estilo tipográfico do componente  ·  observação de contrato

**Estado atual.** `DataTable.jsx` L16-26 (`Cell`): o componente estiliza a célula só em dois ramos —
objeto `{primary, sub}` (13px/600 `-0.006em` + 11,5px `--text-mute`) e `mono: true` com valor de
**texto** (12,5px, `-0.01em`). Qualquer outro valor cai em `return value`, e o nó vai cru para o
`<td>`: `mono: true` na coluna **não** tem efeito.

**Consequência medida (Consulta de Produtos, 27/08).** As colunas Código e Custo declaram
`mono: true` e passam nó (botão-de-copiar, `<span>` formatado) — renderizaram 13px, herdando a base
da tabela, contra os 12,5px do componente. A célula Produto, que precisa da miniatura, reescreveu
a 2ª linha em 11px/`--text-dim` contra 11,5px/`--text-mute`. Três valores do DS perdidos sem erro
no console. Registrado como diff no handoff §22.5.

**Não é defeito** — é o contrato do componente, igual ao caret do `DropdownMenu`. Vale uma linha na
doc da prop: *`mono` só estiliza valor de texto; célula em nó deve citar os valores do `Cell`.*
Alternativa de componente, se um segundo caso aparecer: aplicar o estilo mono no `<td>` em vez de
dentro do `Cell`, para valer também quando o conteúdo é nó. **P2.**

## `Input` sem slot de ícone ou prefixo — o template desenha a busca à mão por isso  ·  P1

**Estado atual.** `Input.jsx` L36-44: o componente renderiza `label + <input> + erro/ajuda`, e nada
entre a borda e o campo. Não há `icon`, `prefix`, `suffix` nem `children`. O `Command` **tem** lupa
(17×17, `Command.jsx` L78) e o template PT-01 desenha a busca da toolbar **à mão** — `<label>` com
SVG de lupa 14×14 + `<input>` cru (`Pt01Lista.dc.html` L70-72) — exatamente para conseguir o glyph.

**Consequência medida (Consulta de Produtos, 27/08).** A tela usou o `Input` do DS na toolbar e
ficou **sem lupa**, enquanto o gatilho de busca global do topbar (copiado do template) tem. Dois
padrões de busca na mesma tela, e a escolha é binária: componente sem ícone, ou ícone sem
componente. Registrado como divergência em aberto no handoff §24.3.

**Contorno na tela (27/08).** O glyph entra **fora** do componente: o invólucro ganha
`position: relative`, a lupa vai absoluta em `left: 10px; top: 50%` com `pointer-events: none`, e o
controle abre espaço com `#campo-busca input { padding-left: 30px !important }` — `!important`
porque o `Input` escreve `padding` inline. Funciona e mantém o componente (anel de foco, estado
inválido, `readonly`), mas é CSS de tela para um caso que toda consulta do ERP tem.

**Proposta.** `icon?: node` (à esquerda, `flex: none`, `--text-mute`) e opcionalmente `suffix?: node`
para o `<kbd>` de atalho, com o `padding-left` do controle ajustado. É a diferença entre o `Input`
servir a campos de busca — o caso mais comum de ERP — ou só a formulário. **P1**: dois consumidores
já existem (esta tela e o próprio template, que evitou o componente).

**Teste de aceite:** `<Input icon={lupa} placeholder="Buscar…" />` renderiza o glyph dentro da
borda, sem sobrepor o texto digitado, e o anel de foco continua envolvendo a caixa inteira.

## `Button` não repassa `aria-*` nem props nativas — gatilho de menu tem de ser transcrito  ·  P2

**Estado atual.** `Button.jsx` L6: a assinatura é fechada
(`children, variant, size, icon, kbd, disabled, type, onClick, style`) — sem `...rest`. Não há como
passar `aria-haspopup`, `aria-expanded`, `aria-label`, `title` nem handlers além de `onClick`.

**Consequência medida (Consulta de Produtos, 27/08).** O gatilho `⋯` (topo e cada linha da tabela)
é um botão de menu: precisa de `aria-haspopup="menu"` + `aria-expanded` (estado que o
`DropdownMenu` entrega à render-função) e de `stopPropagation` no clique, para não disparar o
`onRowClick` da linha. Com o `Button` do DS, os dois se perdem — então a tela mantém um `<button>`
próprio com **os valores do `Button ghost` transcritos** (30×30, `--surface`, `1px --border`,
`--text-dim`, `--radius-md`, hover `--bg-2`/`--text`/`--text-mute`, transição `.15s`). Handoff
§24.2.

**Proposta.** Espalhar as props desconhecidas no `<button>` (`...rest`), ou pelo menos aceitar
`aria-*`, `title` e `onMouseDown`/`onKeyDown`. Sem isso, todo gatilho acessível do ERP vira
transcrição — e transcrição envelhece quando o componente muda. **P2**: o contorno é fiel, mas o
caso (botão-ícone que abre menu) repete em toda tela de lista.

**Teste de aceite:** `<Button icon aria-haspopup="menu" aria-expanded={open} />` renderiza os dois
atributos no DOM.

## Resolvido, registrado como aprendizado

`DropdownMenu` **acrescenta um caret próprio** quando `trigger` é um nó (só a forma
render-função dá controle do botão inteiro). Não é defeito — é o contrato do componente. Produziu
dois glifos no menu da linha até ser corrigido. Vale uma linha na doc da prop.

## Fora do escopo do DS de componentes

O bundle registra 2 erros pré-existentes ao carregar (`ui_kits/app/Norte/norte-app.jsx`:
"Cannot destructure property 'SCENES' of 'window.NORTE'", + React #299). Não afeta a tela, mas
polui o console de qualquer página que carregue o bundle e dificulta verificação.


## Icon — inventário do espelho vs. Lucide do produto  ·  D (defeito de origem)

**Estado atual.** `window.Icon` do bundle espelhado expõe **22 glyphs** (`clock`, `dollar-sign`,
`package`, `shopping-cart`, `bar-chart-3`, `users`, `lock`, `file-text`, `inbox`, `log-in`,
`log-out`, `coffee`, `chevron-down`, `chevron-right`, `arrow-up-right`, `arrow-down-right`,
`plus`, `search`, `bell`, `check`, `menu`, `user-circle`). O guia do DS declara **Lucide React
exclusivamente** — o espelho é um subconjunto, sem `ellipsis`/`more-horizontal`, `triangle-alert`,
`ban`, `percent`, `image`, `chevrons-up-down`. Nome inexistente **falha em silêncio** (retorna
nulo), então o erro só aparece como buraco no layout.

**Contorno na tela.** Fallback documentado por elemento: `menu` no gatilho ⋯, `bell` em Abaixo do
mínimo, `lock` em Sem saldo, `bar-chart-3` em Margem baixa, `package` na miniatura sem foto.

**Consequência medida.** A implementação em produção (24/08) escolheu ícones por conta e divergiu
do protótipo em cinco lugares — porque o protótipo mostrava fallback e não dizia que era fallback.
Custo real de handoff, não estético.

**Proposta.** (a) Completar o inventário do espelho com os glyphs de uso diário em ERP —
`more-horizontal`, `triangle-alert`, `ban`, `percent`, `image`, `chevrons-up-down`,
`chevron-left`, `chevron-up`, `x`, `copy`, `filter`, `download`. (b) Fazer nome inexistente
avisar no console em dev, em vez de retornar nulo. **P1** — o (b) sozinho já evita a classe do erro.


## `--accent` é reescrito em runtime pelo shell  ·  D (defeito de contrato)

**Estado atual.** `AppShellV2.tsx` mantém um seletor de matiz (`useState(accentHue)`), persiste em
`localStorage` e escreve `['--accent'] = oklch(0.55 0.15 ${accentHue})` no shell. Ou seja: um token
do design system é sobrescrito por preferência de navegador, sem indicação na tela de que o valor
não é o canônico (roxo, hue 295).

**Consequência medida.** Numa revisão de 24/08 o acento em produção estava em hue 220; foi lido como
"a cor da empresa" e a aba ativa foi alterada para acompanhar o azul, contra o README §10, contra o
bundle e contra a ADR 0401. Um valor de runtime derrubou três fontes normativas.

**Contorno na tela.** Toda cor de acento desta tela vem de `--color-primary`, que nenhum código
reescreve; a versão suave é derivada com `color-mix(… 12%, transparent)`.

**Proposta.** (a) Se o seletor é recurso de produto, ele deve escrever um token **próprio**
(`--user-accent`) e não `--accent`. (b) Se é ferramenta de desenvolvimento, sair do shell de
produção. (c) Enquanto existir, documentar `--accent` como **mutável** e `--color-primary` como
contrato. **P1** — o (c) é imediato e barato.

## Mapa `TONE` do `KpiFilterCard` é escrito para fundo escuro  ·  P2

**Estado atual.** `KpiFilterCard.jsx` L4866-4872: fundo do tom a 18% e glyph em lightness 0,78-0,80
(`oklch(0.80 0.13 70)`, `oklch(0.78 0.16 20)`, `oklch(0.80 0.14 295)`). Sobre a placa clara do tema
claro, o glyph fica pálido. O `StatusBadge` resolve o caso equivalente — em `fresc-cold` ele troca
o tom cheio por `oklch(0.74 0.14 18)` justamente por contraste — mas o `KpiFilterCard` não tem par
claro/escuro.

**Contorno na tela.** Nenhum: o valor do DS é aplicado como está, e a tensão fica registrada aqui.
Escurecer na tela seria correção silenciosa.

**Proposta.** Dar ao `TONE` um par por tema, como o `StatusBadge` já faz na prática, ou derivar o
`fg` de token semântico em vez de literal OKLCH. **P2** — esperar segundo caso.


## Família `fresc-*` do `StatusBadge` reprova contraste em tema claro  ·  D (defeito de origem)

**Estado atual.** `StatusBadge.jsx` L6357-6360 define os três tons de frescor com `fg` sobre placa
tintada a 16%. Medido sobre a placa clara do tema claro:

| Tom | `fg` | Contraste | Régua WCAG AA (texto pequeno) |
|---|---|---|---|
| `fresc-hot` | `var(--color-success)` | **2,86:1** | 4,5:1 |
| `fresc-warm` | `var(--color-warning)` | **2,36:1** | 4,5:1 |
| `fresc-cold` | `oklch(0.74 0.14 18)` (literal) | **1,94:1** | 4,5:1 |

**Os três reprovam** — inclusive os dois que usam token semântico. O texto tem 11,5px e peso 500,
o que agrava. Conclusão: a família foi autorada para o cockpit escuro e **não tem par claro**. O
literal do `fresc-cold` é sintoma, não causa: trocá-lo por `--color-destructive` sobe para ~3,7:1,
continua reprovando, e esconde o defeito atrás de uma correção cosmética.

**Contorno na tela.** Nenhum. Os três tons são aplicados como o componente manda, contraste
reprovado, tensão registrada aqui. Corrigir na tela seria correção silenciosa (Lei 13).

**Histórico.** Um agente de código mediu isso corretamente e, por iniciativa própria, substituiu o
literal por `--color-destructive` citando a AP1. Revertido: divergência autoriza pergunta, não ação
(Lei 14). A medição, porém, é o achado que abriu esta entrada.

**Proposta.** (a) Dar à família `fresc-*` um par claro/escuro, como o `Alert` já tem — corrige todas
as telas de uma vez. (b) Alternativa mais barata: escurecer só o `fg` no tema claro, mantendo fundo
e borda. **P1 · aguarda decisão do Wagner** — enquanto isso, a tela fica conforme o DS.


## RETRATAÇÃO — "`DataTable` não tem prop de densidade" era proposta falsa  ·  fechada sem mérito

**O que eu propus (P1).** Que o `DataTable` ganhasse uma prop `density`, porque a tela precisava de
modo compacto e o componente não oferecia. Contorno declarado: colapsar **conteúdo** de célula
(2ª linha de Produto e de Disponível) em vez do padding.

**Por que estava errado.** O DS já resolve, por **template**. `templates/pt-01-lista`, bloco
`<style>` do `<helmet>`:

```css
.cockpit table td { padding-top: var(--d-td-y, 6px) !important; padding-bottom: var(--d-td-y, 6px) !important; }
.cockpit table th { padding-top: var(--d-th-y, 6px) !important; padding-bottom: var(--d-th-y, 6px) !important; }
```

E a lógica do template escreve o par de tokens no elemento `.cockpit`, junto do resto da escala:

| Token | compacto | confortável |
|---|---|---|
| `--d-td-y` / `--d-th-y` | 6px / 6px | 10px / 9px |
| `--d-fontsz` | 13px | 13,5px |
| `--d-cpad-x` / `--d-cpad-y` | 14px / 12px | 22px / 16px |
| `--d-tb-y` | 7px | 11px |
| `--fs-1..9` | 10 · 11 · 11,5 · 12,5 · 13,5 · 15,5 · 19 · 23 · 31 | 10,5 · 11,5 · 12,5 · 13,5 · 15 · 18 · 22 · 28 · 38 |

**Contrato fechado: seis tokens, não oito.** O PT-01 também escreve `--d-sidebar` (208/236) e
`--d-navpy` (5/7), e **esta tela não os escreve — nem deve.** Ninguém os consome: a sidebar é o
`AppSidebar` do DS, que crava `width: 260, flex: 'none'` (`AppSidebar.jsx` L1827-1830) e não tem prop
de largura. São resíduo de quando o template desenhava a própria sidebar. **Não acrescentar os dois**
— escrever token que ninguém lê é ruído que a próxima auditoria vai medir como divergência. Largura de
sidebar é do componente. A lista das seis linhas acima é **fechada**.

⚠ **`hint-size` não é largura.** O `hint-size="222px,100%"` que o PT-01 passa ao `AppSidebar` é
placeholder de streaming (o host é `display: contents`); o componente monta em 260px. Ler `hint-size`
como medida foi a origem de um diff errado — ver `README.md` §15.3, correção da #19.

Ou seja: densidade no DS é **um contrato de tokens no shell**, não uma prop de componente. O
mecanismo existe, é canônico, e cobre tabela, padding de contêiner, toolbar, sidebar e a rampa
tipográfica inteira — muito mais do que a prop que eu pedi.

**Status.** Proposta **retirada**. O contorno de colapsar conteúdo de célula continua válido como
decisão de produto (esconder `unidade · categoria` em modo denso é escolha de informação), mas
**não como substituto do padding** — os dois são coisas diferentes e a tela deve fazer as duas.

**Lição, que virou a Lei 16 do manual:** eu procurei a resposta no componente, não achei, e concluí
que o DS não tinha resposta. O DS decide também por template. Propor ao DS algo que ele já tem é
pior que não propor: gasta a credibilidade da pauta.


## `DataTable` não tem prop `height`, o `DataTablePro` tem  ·  P2

**Estado atual.** `DataTablePro` aceita `height` e limita a tabela por dentro (header fixo + rolagem
interna). `DataTable` não: renderiza um `<table>` que cresce com o conteúdo. O template PT-01 usa
`DataTablePro` com `height={420}`, e é por isso que o `min-height:100vh` do shell dele não causa
problema.

**Consequência medida.** Copiar a estrutura do PT-01 usando `DataTable` produz um shell que nunca
rola por dentro: `flex:1` resolve contra conteúdo, o documento passa a rolar e o cabeçalho fixo sai
da tela. Falha silenciosa, sem erro no console. Aconteceu nesta tela em 25/08.

**Contorno na tela.** O limite vem do shell: `.cockpit { height:100vh; overflow:hidden }` +
`main { min-height:0 }` + área de conteúdo `flex:1; min-height:0; overflow:auto`.

**Proposta.** (a) Dar ao `DataTable` a mesma prop `height` do `DataTablePro`. (b) Ou — melhor —
documentar no template PT-01 que o `min-height:100vh` do shell depende do `height` da tabela, e dar
a variante do shell para quem usa `DataTable`. **P2**: o (b) é documentação e resolve a classe do
erro; o (a) é mudança de componente e pode esperar segundo caso.


## `DataTablePro` não aceita ordenação controlada nem linha selecionada  ·  P1

**Estado atual.** O `DataTablePro` tem o que uma lista de balcão precisa — cabeçalho fixo, resize de
coluna, prop `height`. Mas a assinatura documentada (`DataTablePro.jsx` L3060-3062) é
`columns · rows · height · density · selectable · onRowClick · onSelectionChange` + `defaultSort`:

- **ordenação é interna e só semeada** (`defaultSort`). Não há `sortKey`/`sortDir`/`onSort`. Clicar
  no cabeçalho muda a ordem sem informar a tela.
- **`state` aceita só `urgent`/`archived`** — não `selected`. A seleção é interna, via
  `onSelectionChange`.

O `DataTable` é o oposto: aceita `sortKey`/`sortDir`/`onSort` e `state:'selected'`, e **não** tem
cabeçalho fixo, resize nem `height`.

**Consequência.** Tela que precisa das duas coisas não tem componente: a Consulta de Produtos
controla a ordem por fora (gatilho "Ordem" que reflete e define, paleta `⌘K`, persistência em
`localStorage`) e marca a linha ativa do teclado com `state:'selected'`. Com o `Pro`, o gatilho
dessincronizaria do cabeçalho e a navegação por teclado perderia o realce.

**Contorno na tela.** Fica no `DataTable` e fixa o cabeçalho por CSS:
`.cockpit thead th { position: sticky; top: 0; z-index: 2 }`. Cobre o ganho principal; resize de
coluna fica de fora.

**Proposta.** Dar ao `DataTablePro` o modo controlado que o `DataTable` já tem: `sortKey`,
`sortDir`, `onSort` e `state:'selected'` — mantendo o comportamento atual como padrão quando não
vierem. **P1**: é a diferença entre o `Pro` servir a telas de índice reais ou só a demos.

## `FilterChip` no template PT-01 pressupõe filtros fora da toolbar  ·  observação, sem proposta

**Estado atual.** O PT-01 põe `FilterChip` na toolbar e um botão "Filtros" no `PageHeader` — os
filtros são definidos **em outro lugar** e o chip é o único vestígio visível deles.

**Por que a Consulta de Produtos não usa.** Aqui os filtros **são** a toolbar: cinco
`DropdownMenu` sempre visíveis, cada um com o valor ativo no próprio rótulo ("Disponível: Com
saldo"). O chip repetiria a informação numa linha extra, e altura é o recurso escasso desta tela
(chrome fixo medido em 310–466px conforme a largura).

**Não é divergência do DS** — é o mesmo padrão resolvido para um mecanismo de filtro diferente. Sem
proposta; registrado para não ser "corrigido" por quem comparar as duas telas lado a lado.

## RETRATAÇÃO — "falta um chip de anotação neutro" era proposta falsa  ·  fechada sem mérito

**O que eu propus (P2).** Uma variante `tone="neutral"` no `TagChip`, ou um `AnnotationChip`, porque
a paleta do `TagChip` é de categorias de cliente e reusá-la pintaria uma observação de produto com a
cor de "varejo".

**Por que estava errado.** O `TagChip` **já** tem o caso neutro, e está escrito no docblock dele:
*"A fixed semantic palette keyed by category. **Unknown tags fall back to neutral.**"*
(`TagChip.jsx`, cabeçalho). O código confirma:

```js
const hue = TAG_HUE[key];   const known = hue != null;
const bg = known ? `oklch(0.30 0.05 ${hue})` : 'var(--color-secondary)';
const fg = known ? `oklch(0.84 0.12 ${hue})` : 'var(--color-secondary-foreground)';
const bd = known ? `oklch(0.40 0.07 ${hue})` : 'var(--color-border)';
```

Tag fora de `TAG_HUE` (as nove categorias de cliente) renderiza **neutro por token**, com override de
tema — exatamente o que eu construí à mão. `<TagChip label="observação" />` é o componente certo.

**Como eu errei.** Li a tabela `TAG_HUE` e paguei o preço de não ler a frase acima dela. Repeti a
falha da Lei 11 num nível mais raso: não é só "medir o componente" — é **ler o docblock**, que
existe justamente para dizer o comportamento que a tabela de dados não mostra.

**Status.** Proposta **retirada**. A tela agora usa `TagChip` no caso neutro.

## Contraste — o chip crítico entra na mesma conta dos `fresc-*`  ·  P1 (mesma pendência)

O chip de observação crítica usa `--color-destructive` a 16% de fundo com o **tom cheio** no texto,
receita citada do `StatusBadge`. Medido nesta tela, em `--fs-1` (10,5px): **≈2,4:1**, contra o
mínimo de 4,5:1 da régua de acessibilidade do projeto — e ainda abaixo dos 3:1 de texto grande, que
10,5px não alcança de qualquer forma.

É o **mesmo defeito** já registrado para a família `fresc-*` do `StatusBadge` (2,86 / 2,36 / 3,75:1),
não um novo: a receita "tom cheio sobre fundo a 16%" não passa em nenhum dos casos onde o tom é
claro. Fica na mesma entrada porque a correção é uma só — o par `-fg` do token, ou uma receita de
fundo mais escura.

**Estado da tela:** aplicado como o DS manda, com o número medido registrado. Sem alteração até a
decisão vir. Exceção só assinada.

## `TagChip` não tem tom de perigo nem slot de ícone  ·  P2 (o que de fato falta)

**Estado atual.** O `TagChip` cobre categoria conhecida (nove matizes) e desconhecida (neutro). Não
há como marcar um chip como **crítico**: não existe prop `tone`, e as nove chaves de `TAG_HUE` são
categorias de cliente, não estados. Também não há slot de ícone.

**Contorno na tela.** Observação crítica usa a **geometria transcrita** do `TagChip`
(`TagChip.jsx` L4248-4258: pílula 9999, `--fs-1`, peso 500, `lowercase`, `letter-spacing -.01em`,
`padding 1px 8px`) com os tokens `--color-destructive` na receita 16%/30% do `StatusBadge`. Fica na
mesma família visual do chip neutro; só a cor muda.

**Proposta.** `tone?: 'danger' | 'warn'` no `TagChip`, ortogonal à paleta por categoria. **P2** —
esperar segundo caso, mas o contorno é de 8 linhas e não escala para outras telas.


## Prioridade elevada — cabeçalho fixo passou a ser requisito assinado (26/08/2026)

Não é proposta nova: é a mesma **P1** de *"`DataTablePro` não aceita ordenação controlada nem linha
selecionada"*, com peso novo. Em 26/08 o **Wagner decidiu** que a linha de produtos nunca passa
sobre o cabeçalho da tabela na Consulta de Produtos — motivo declarado: em tela pequena não se
distingue a que coluna pertence cada valor.

Efeito prático: a única tabela do DS com cabeçalho fixo nativo é o `DataTablePro`, e ele continua
inutilizável em tela de índice real (sem `sortKey`/`sortDir`/`onSort`, sem `state:'selected'`).
Enquanto a P1 não sai, o contorno da tela deixa de ser conveniência e passa a ser o que sustenta um
requisito assinado — duas declarações de CSS (`z-index: 6` e régua em `::after`) mantidas fora do
componente. Ver handoff §19.1.

**Aprendizado da mesma rodada (não é proposta).** Tentei acrescentar `background-clip: padding-box`
ao `th`. Inerte: o `DataTable` escreve a shorthand `background` **inline** na célula, que vence o
stylesheet e reseta o `background-clip`. Regra de stylesheet não disputa propriedade que o
componente escreve inline — medir antes de publicar como obrigatória.

**Nada a fazer no DS por conta desta tela.** O registro existe para que a P1 seja priorizada com o
motivo certo: não é refinamento do `Pro`, é a diferença entre o componente servir a telas de índice
ou continuar sendo demo.


## `Drawer`: `title`/`subtitle` renderizam fora da faixa do cabeçalho  ·  P2

**Estado atual.** `_ds_bundle.js` L3833-3843: a faixa do cabeçalho (`border-bottom`) recebe
`badge` + espaçador + botão ✕. `title` e `subtitle` saem num bloco **abaixo** dessa borda
(L3872-3889). O `aria-label` do `[role="dialog"]` é derivado só de `title` (L3820).

**Contorno na tela.** A Consulta de Produtos precisa do nome e do código **na faixa** (revisão do
Felipe, 26/08, e é o que a tela em produção faz). Como `badge` é o único slot que renderiza dentro
dela, o cabeçalho vai por ali — com os valores tipográficos do próprio componente citados
(L3876-3889) — e `title`/`subtitle` deixam de ser passados. Consequência: sem `title`, o diálogo
perde o nome acessível, e a tela grava `aria-label` no elemento por `componentDidUpdate`.

**Proposta.** (a) Renderizar `title`/`subtitle` **dentro** da faixa, à esquerda do ✕ (`badge` passa a
vir depois do espaçador, que é onde um selo de status faz sentido); ou (b) uma prop
`titleInHeader`, mantendo o padrão atual. Em qualquer das duas, derivar o rótulo de
`aria-label || title`, para o caso de título em nó. **P2** — dois consumidores já querem o mesmo
arranjo (esta tela e a tela em produção), mas o contorno é estável e não força a mão.


## Exceção assinada — modelo de rolagem do PT-01 não se aplica à Consulta de Produtos (26/08/2026)

**Estado atual.** O template canônico `templates/pt-01-lista` limita a região de dados
(`DataTablePro` com `height={420}`, rolagem interna, chrome fixo) e eu havia levado esse modelo para
a tela como valor **[TPL]**, que por regra ganha de **[TELA]**.

**Decisão.** A dona da tela (Maiara, 26/08) manteve o modelo do produto: **a página rola**, como o
`Index.charter.md` do `main` declara em Goals. A instrução do handoff em contrário foi
desconsiderada, e o cabeçalho fixo passa a prender no topo da **janela** — o que só funciona porque
nenhum ancestral da tabela declara `overflow`.

**Por que fica registrado aqui:** é divergência declarada contra um valor de template, com
assinatura — não descuido, e não deve ser "corrigida" por quem comparar as duas telas lado a lado.
**Nada a fazer no DS.** Se o PT-01 quiser cobrir telas de índice que rolam a página, o que falta é
uma **variante de shell documentada** (o mesmo pedido que já está na P2 de `DataTable` sem prop
`height`), não mudança de componente.


---

## EXCEÇÃO ASSINADA — sidebar da Consulta de Produtos vai a hue 295 antes do espelho do DS (01/09/2026)

**Pedido.** Maiara, 01/09/2026: *"consegue aplicar essas modificações na tela de consulta de produtos"*,
sobre as três atualizações lidas no `main` no mesmo dia.

**O que foi aplicado.** Os 8 `--sb-*` da **ADR UI-0028**, transcritos de `resources/css/cockpit.css`
L193-200 do `main`, escritos no `<style>` do `<helmet>` de `Consulta de Produtos.dc.html` no escopo
`.cockpit` — o mesmo escopo em que o espelho os declara, e depois dele na ordem das folhas.
O `AppSidebar` do DS **não foi tocado**: ele consome `--sb-*` (bundle L1831-1834 e ss.), então a troca
de token basta.

**Por que é exceção.** A regra do projeto manda aplicar o valor do DS como está e só perguntar
(`pauta-design-system.md`, protocolo de divergência). O espelho serve **hue 240**. O 295 só entra
porque a dona pediu. Sem esse pedido, o correto seria manter 240 e deixar a pergunta aberta.

**O que NÃO entrou, e por quê.**
- `--sb-scroll` e `--sb-bullet-out`: **D-3 da UI-0028** os mantém em 240 (o protótipo não os declara).
  Resíduo declarado, não esquecimento.
- Ghosts no sidebar e o slot do atalho `G X` (`.sb-item.sb-sub`, `.sb-ghost-count`, `.sb-ghost-more`,
  `.sb-item-end`/`.sb-kbd`, novos em `cockpit.css` no mesmo lote): são **estrutura interna do
  `AppSidebar`**, não token. Reproduzi-los na tela seria recriar componente do DS, que está descartado.
  Vira **P2 na pauta**: o `AppSidebar` do espelho está atrás do `main` nessa estrutura.
- ADR 0386: a tela já está conforme (`hue = 295`, sem escopo por vertical). Nada a mudar.
- ADR UI-0029: governança; entrou no handoff (§17), não no código.

**Reverter é uma linha.** Apagar o bloco `.cockpit { --sb-* }` do `<helmet>` devolve a tela ao espelho.
O bloco sai por completo no dia em que o DS adotar o 295.

**Teste de aceite.** `getComputedStyle(document.querySelector('.cockpit')).getPropertyValue('--sb-bg')`
= `oklch(0.21 0.025 295)` na tela; `--sb-scroll` segue não declarado.


## `AppSidebar` do espelho está atrás do `main`: sem ghosts no sidebar nem slot de atalho  ·  P2

**Estado atual, medido em 01/09/2026.** `cockpit.css` do `main` ganhou, no lote da UI-0028, estrutura
que o `AppSidebar` do bundle espelhado não tem: sub-item do item ativo (`.sb-item.sb-sub`, borda
esquerda de 2px em `--accent` quando ativo, `padding-left: 18px`), contador de sub-telas
(`.sb-ghost-count`, mono 10px, opacidade .36), o "⋯ mais N" (`.sb-ghost-more`) e o slot compartilhado
(`.sb-item-end`) onde a contagem cede lugar à dica de atalho `G X` (`.sb-kbd`, opacidade .62 no hover)
sem empurrar o rótulo.

**Contorno na tela: nenhum, e nenhum é devido.** Recriar isso fora do componente seria substituir
componente do DS.

**Proposta:** o `AppSidebar` do pacote ganhar os sub-itens do item ativo (teto de 5 + "⋯ mais N") e o
slot direito com as duas ocupações. **P2** — melhora navegação, mas nenhuma tela deste projeto depende.

**Teste de aceite:** com `active="Produtos"`, o `AppSidebar` renderiza as sub-telas do item ativo e o
slot direito mostra a contagem em repouso e o atalho no hover, sem deslocar o rótulo.
