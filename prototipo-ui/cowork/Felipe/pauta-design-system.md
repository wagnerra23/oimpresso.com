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

**Segundo caso (23/09/2026, Fabricação).** Os botões ✕ de remover grupo e ingrediente
(`manufacturing-recipe.jsx`, `.mfg-mini`) precisam de `aria-label="Remover grupo …"` e `title`.
Com `Button icon` o nome acessível viraria "✕". Ficaram como `<button>` local na onda B. Com dois
casos, o item cumpre a regra do P2 e pode subir para **P1**.

## D · `Drawer`: o × tem uma linha só dele e empurra o título para baixo

**Hoje:** `components/Drawer/Drawer.jsx` L39-51 — primeira linha do painel com `badge` + × e
`borderBottom`, 57px de altura; o título vem num bloco separado abaixo dela. O `Sheet` do produto
(`resources/js/Components/ui/sheet.tsx` L76) põe o × `absolute top-4 right-4` e o título no topo.

**Efeito na tela:** faixa vazia de 57px no alto de todo drawer (apontado pelo Felipe, 25/09/2026).

**Contorno na tela:** `manufacturing-page.css`, escopo `.mfg-root aside[role="dialog"]` — a linha
do × vira canto absoluto, o título sobe para o topo (recuo de 58px à direita para não passar sob o
×) e a borda desce para baixo do título. Vale para os 3 drawers da Fabricação; nenhum usa `badge`.

**Proposta:** quando não há `badge`, o × vai para a linha do título (como no `Sheet`); com `badge`,
a linha atual continua.

**Teste de aceite:** abrir um drawer sem `badge` — o `<h3>` do título começa a ≤16px do topo do
painel e o × fica na mesma linha, à direita.

**Prioridade:** D — o protótipo diverge do componente do produto.

---

## D · `DataGrid`: calha da barra de rolagem reservada mesmo sem rolagem

**Hoje:** `components/DataGrid/DataGrid.jsx` L166 — `scrollbarGutter: 'stable'` no contêiner.
Com poucas linhas não há barra, mas a calha fica: faixa vazia de 15px só à direita da tabela
(Relatório da Fabricação: caixa 1291px, tabela 1276px). O produto não reserva calha (nenhum
`scrollbar-gutter` em `resources/js/Components` nem `resources/css`).

**Contorno na tela:** `manufacturing-page.css`, `.mfg-root .mfg-grid div[style*="scrollbar-gutter"]`
com `scrollbar-gutter:auto!important` (o valor é inline). Com rolagem de verdade a barra aparece.

**Proposta:** `scrollbarGutter: 'auto'` no componente, ou prop para quem precisa de estabilidade.

**Teste de aceite:** grade com 5 linhas e `maxHeight` 420 — largura da tabela = largura do contêiner.

**Prioridade:** D.

---

## D · Primitivos de impressão (`ProofFrame`, `Dimension`, `ProofStrip`) pintam com o tema da tela

**Hoje:** pintam por token de tela (`background: var(--surface)`, `var(--text-mute)`,
`var(--text-dim)`). Com o cockpit em tema escuro, a folha da ficha técnica, que é papel, saía
cinza-escuro: a moldura em `oklch(0.30 0.008 240)`, que é o `--surface` do escuro (medido em
25/09/2026).

**Contorno na tela:** `manufacturing-page.css` — `.mfg-sheet` redefine `--bg`, `--bg-2`, `--surface`,
`--border`, `--border-2`, `--text`, `--text-dim` e `--text-mute` com os valores do `.cockpit` claro,
transcritos de `design-system/colors_and_type.css` L278-285.

**Proposta:** os primitivos print-craft (ou o `PresenterMode`, no papel) fixam a paleta clara, porque
papel não tem tema.

**Teste de aceite:** tema escuro → abrir a ficha com custo → nenhum elemento da folha com fundo
`--surface` do escuro; moldura `#fff`.

**Prioridade:** D.

---

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


## PERGUNTA AO SHELL — o cabeçalho de módulo do protótipo não é o `PageHeader` do DS (28/08/2026)

**Estado atual, medido.** A tela "Todos os produtos" (`produto-blade.jsx` L1021-1026) monta
`M.Header` — `modulo-padrao.jsx` L18, cujo próprio comentário o define como "o equivalente do
JanaHeader". Anatomia: `modulo` · `papel` · `contexto[]` · `atualizadoAs` (clicável, reapura) ·
`glyph` · `acoes`. O `[DS]` `PageHeader` existe no bundle carregado (`_ds_bundle.js` L8280 /
L9169) e tem outra anatomia: `title` · `stats` · `actions`, montado pelo `[TPL]` dentro de
`<div style="padding:0 var(--d-cpad-x)">` (`Pt01Lista.dc.html` L34-36).

**Raio.** O mesmo `M.Header` é montado por **nove** telas do protótipo: `produto-blade` ·
`venda-blade` · `venda-index` · `crm-blade` · `crm-portal` · `compras-extras` ·
`financeiro-legado` · `integra-extras` (WooCommerce e Restaurante). Lista **fechada** — é a varredura
de `M.Header &&` no projeto.

**Por que não corrigi na tela.** Trocar `M.Header` por `PageHeader` só na tela de produtos criaria
duas gramáticas de cabeçalho no mesmo shell — divergência pior que a atual, e fora da autoridade de
uma tela. Aplicar o valor do DS aqui significaria arrastar as outras oito.

**A pergunta (não é proposta).** Qual das duas:

1. O `M.Header` do shell passa a **compor o `PageHeader` do DS por dentro**, mantendo a assinatura
   atual (`modulo`, `papel`, `contexto`, `atualizadoAs`, `onRefresh`, `glyph`, `acoes`) para que
   nenhuma das nove telas mude de chamada: `title` recebe módulo e papel, `stats` recebe o contexto,
   `actions` recebe as ações da tela. Uma mudança, nove telas.
2. O `M.Header` fica como **exceção assinada**: o cabeçalho de módulo do protótipo é contrato do
   shell, e o `PageHeader` do DS vale para telas que não vivem nele. Registrado, e nenhuma auditoria
   futura o marca como gap.

**Correção de 28/08 — a terceira saída que eu havia escrito não existe.** Eu propunha "o `PageHeader`
do DS ganha linha de contexto e atualizado-às". Medido depois (`_ds_bundle.js` L5158-5216): o
`PageHeader` **já** aceita `stats` como lista de `{value, label, tone}`, impressa separada por `·`
com `tabular-nums` — é exatamente a linha de contexto. E o "atualizado às" cabe em `actions`. Não há
nada a pedir ao DS para a saída 1 funcionar. Proposta retirada antes de virar pedido.

**Três consequências medidas da saída 1, para a decisão ser informada:**

1. **A placa do glyph desaparece.** O `PageHeader` aceita `title`/`subtitle`/`stats`/`actions` — não
   tem prop de ícone. A tela React em produção também não tem glyph, então a perda é **conformidade**,
   não defeito. Se o glyph for identidade a preservar, aí **sim** é proposta ao DS (`glyph` no
   `PageHeader`) — e é decisão da Maiara, não minha.
2. **O `stats` corta em 56ch** (L5197, `maxWidth: '56ch'` + ellipsis). O contexto atual do protótipo
   tem quatro itens e seria truncado. Recomendação: cair para três — `12 produtos · 4 grupos de
   preço · matriz` — porque **"papel: Administrador" já aparece no rodapé da sidebar**.
3. **"Atualizado às" deveria virar texto, não botão:** horário no `stats` (é contexto) e "Atualizar"
   dentro do `⋯` (é ação). Padrão da tela React: **uma** ação primária visível + `⋯`.

**Levantado por.** A dona da tela (Maiara, 28/08), olhando a tela: *"o pageheader parece um pouco
diferente, assim como não vejo a tabbar"*. A minha auditoria de fidelidade (§10 do
`absorcao-v2-em-todos-os-produtos.md`) tinha nove itens e **não** incluía cabeçalho nem abas — buraco
meu, achado por ela. As abas eram caso de tela e já foram trocadas pelo `TabBar` do DS; o cabeçalho é
esta pergunta.

**Não alterar nada até a decisão vir.**

---

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

## Sidebar: o `main` foi para hue 295 (UI-0028) e o DS espelhado segue em 240  ·  D (divergência declarada na origem)

**Estado atual, medido em 01/09/2026 contra `wagnerra23/oimpresso.com@main` (árvore `f026223995a3`).**
Três fontes, três valores, e a divergência é **da origem** — não desta tela:

| fonte | `--sb-bg` | `--sb-text` | `--sb-active` |
| --- | --- | --- | --- |
| `resources/css/cockpit.css` L193-200 (`.cockpit .sb`) — **vigente no produto** | `oklch(0.21 0.025 295)` | `oklch(0.80 0.008 295)` | `oklch(0.34 0.05 295)` |
| `resources/css/tokens/_generated-cockpit-{light,dark}.css` (saída DTCG, v1.1.0 de 26/08) | `oklch(0.18 0.006 240)` | `oklch(0.78 0.005 90)` | `oklch(0.32 0.008 240)` |
| `_ds/…-49a36f76…/colors_and_type.css` L308-315 — **DS espelhado deste projeto** | `oklch(0.18 0.006 240)` | `oklch(0.78 0.005 90)` | `oklch(0.32 0.008 240)` |

O 295 entrou pela **ADR UI-0028** (accepted 28/08/2026, `importado_ds_git/adr/ui-0028-sidebar-hue-295.md`): os 8 tokens `--sb-*`
que o protótipo Cowork declara passam a ser os do protótipo, copiados exatamente. Ela **prevê e assume** esta divergência
— *"O Design System passa a divergir de produção nos `--sb-*`. É o preço explícito desta decisão […] Registrado como dívida"* —
e declara que **se o DS deve adotar o 295 é decisão de quem cuida do DS, e precisa de ADR própria**. `--sb-scroll` e
`--sb-bullet-out` ficam em 240 por D-3 da mesma ADR (o protótipo não os declara), resíduo declarado.

**Contorno na tela:** nenhum, e nenhum é devido. Nenhuma tela deste projeto escreve `--sb-*`; a sidebar vem do
`AppSidebar` do bundle. Vale a regra do projeto: **o DS ganha** — aplicar o 240 que o espelho serve, relatar o número
medido, não converter nada.

**Proposta:** nenhuma até a ADR do DS existir. Item de **pergunta ao dono do DS**, não de ação: adotar os 8 valores de
UI-0028 em `colors_and_type.css` e nos `_generated-cockpit-*.css`, ou manter o 240 e registrar que o espelho é
deliberadamente anterior à UI-0028.

**Teste de aceite:** `getComputedStyle(document.querySelector('.sb')).getPropertyValue('--sb-bg')` no protótipo devolve
o mesmo valor que `cockpit.css` L193 do `main` — hoje devolve o de 240 e isso está **de acordo** com o espelho vigente.


## Protótipo é soberano na FORMA sobre ADR UI (UI-0029) — muda o que esta tela pode alegar

**Estado atual.** ADR UI-0029 (redigida 28/08, **ratificada 31/08/2026** por [W] com "accepted"), em
`importado_ds_git/adr/ui-0029-prototipo-soberano.md`. Duas consequências que tocam o modo de trabalho deste projeto:

1. **Divergência ADR UI × protótipo é defeito, não pauta** — a ADR está errada e é emendada; não se devolve a [W]
   "qual dos dois vale?". Escopo: **forma** (layout, hierarquia, espaçamento, cor, tipografia, ícone, rótulo, afordância,
   estado). **Visibilidade** (permissão, pacote por business, módulo) e **dado** seguem com código/ADR — protótipo não
   revoga permissão nem tenancy, e Tier 0 fica intocado.
2. **Cadeia de precedência bifurcada:** forma ⇒ `protótipo > teste > casos > charter > SPEC`; comportamento/visibilidade/
   dado ⇒ a cadeia original intacta, sem o protótipo. Teste que fixa forma antiga **perde** e se reescreve no mesmo PR
   (desabilitar é fuga).

**Residual honesto declarado pela própria ADR:** a regra **não é enforçável hoje** (lado vivo não renderiza sem sessão
logada; não existe gatilho "protótipo mudou ⇒ confere a tela"). Vale culturalmente, verificação manual e só no eixo
estrutural (DOM). **Não criar gate.**

**Teste de aceite:** ao encontrar divergência de forma entre esta tela e o protótipo Cowork, o handoff cita UI-0029 e
trata a ADR/teste divergente como perdedor — sem abrir pergunta a [W].


## Âmbar escopado por vertical revogado (ADR 0386) — roxo 295 é a única identidade de chrome

**Estado atual.** ADR 0386, aceita **31/08/2026**, supersede parcial da 0244 (só a decisão 4):
não há `--accent` de cor própria para vertical nenhuma; `oklch(0.55 0.15 295)` é a única identidade de chrome
(botão, foco, link, estado ativo, primary). A camada **semântica** não é afetada — status, `--origin-*` e `--stage-*`
continuam variando de propósito (wayfinding, não decoração). O gate da 0263 (invariante `--accent*` em hue 250-330)
deixa de ter exceção pendente.

**O que isso proíbe nesta tela:** escopar `--accent` por módulo (`.oficina-scope`, `.grafica-scope` etc.). **O que não
proíbe:** usar os tons semânticos existentes para status e estágio, que é o caminho registrado caso wayfinding por
vertical volte a doer.

**Teste de aceite:** `grep -rE '\-\-accent[a-z0-9-]*\s*:' ` nos arquivos desta tela não encontra matiz fora de 250-330.


## Tokens DTCG: pacote parado na v1.1.0 (26/08) — nada novo desde a última sync

Medido em `resources/css/tokens/version.json`: `version 1.1.0`, `tokenCount 308`, `updated 2026-08-26`,
`fingerprint d797f34a…`. O `CHANGELOG.md` do pacote (importado em `importado_ds_git/css/tokens/`) fecha na v1.1.0,
cuja única entrada MINOR são os 12 `--sb-accent*`/`--sb-pos*` de 26/08 — **anteriores** à sync de 28/08. Ou seja:
**a rampa de tipo, cores funcionais e o resto das fundações não mudaram.** O que mudou no visual do produto desde então
não passou pelo pipeline de tokens: entrou como CSS de shell em `cockpit.css`.


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


## `StatusBadge.d.ts` declara 8 `StatusKind`, o componente implementa 11  ·  D (defeito de origem)

**Estado atual, medido em 08/09/2026.** `components/StatusBadge/StatusBadge.d.ts` (projeto do DS)
L1-6 fecha o tipo em oito: `intercorrencia · prioridade · payment · rep · sla · atendimento ·
frescor · tipo`. O `MAP` do próprio componente (`StatusBadge.jsx` L41-85; espelho compilado
`_ds_bundle.js` L6389-6470) implementa **onze** — os três a mais são `documento`, `fiscal` e `os`,
justamente os domínios de ERP. O README do DS também lista os onze
(*"kind (ERP): documento · fiscal (NF-e) · os · prioridade · payment · sla · atendimento · frescor · tipo"*).

**Onde dói.** Quem tipar em TypeScript perde `documento` (o workflow genérico de pedido/requisição/OP),
`fiscal` (NF-e) e `os` — e conclui que o DS não cobre esses casos. Foi o erro que essa medição
quase cometeu na Fabricação: sem abrir o `.jsx`, o `.d.ts` faz parecer que falta um `kind` de
produção quando o `documento` já é ele.

**Proposta.** Acrescentar `'documento' | 'fiscal' | 'os'` ao `StatusKind` e declarar o
`tone?: string` (a assinatura do `.jsx` tem `tone`, o `.d.ts` não). **D**, não P — é o tipo em
desacordo com a implementação, não pedido de recurso.

**Teste de aceite:** `<StatusBadge kind="documento" value="rascunho" />` compila sem `@ts-expect-error`.


## ~~`StatusBadge kind="documento"` não tem estado terminal `finalizada`~~  ·  ✅ RESOLVIDO NA ORIGEM (2026-09-08)

> ⚠️ **REABERTO em 23/09/2026.** O domínio `producao` não existe mais na origem. Ver
> *"Regressão na origem: `StatusBadge` perdeu `producao` e os tons `soft-*`"*, no fim desta pauta.

**O DS respondeu melhor que a proposta.** Em vez de acrescentar `finalizada` ao `documento`,
criou o domínio próprio `producao` — `finalizada: ['Finalizada','soft-success']` e
`rascunho: ['Rascunho','soft-warning']` (bundle L6537-6540). Fabricação passou a usar
`kind="producao"` e o contorno tone+label saiu da tela.

**Detalhe que vale registrar:** o rascunho ficou **âmbar**, não cinza — o comentário do
componente diz que adotou a forma que este protótipo já pintava em `.mfg-pill.warn`, citando
UI-0029 (soberania da FORMA ao protótipo). O caminho protótipo→DS funcionou.

Texto original da proposta, para histórico:

### ~~P2 original~~

**Estado atual.** O docblock destina `documento` à OP — *"Documento / aprovação — workflow genérico
de ERP (pedido, requisição, OP…)"* (`StatusBadge.jsx` L42). As chaves são `rascunho · pendente ·
aprovado · rejeitado · aplicado · cancelado` (L43-46). A ordem de produção da Fabricação tem duas
situações, `Rascunho` e `Finalizada`; a primeira mapeia, a segunda não tem chave.

**Contorno na tela.** Usar `kind="documento"` com `label="Finalizada"` sobre um valor mapeado —
o `label` existe justamente para "quando o banco devolve outra string".

**Proposta.** `finalizada: ['Finalizada', 'success']` no mapa `documento`. Espera segundo caso:
Compras e Orçamento provavelmente têm o mesmo estado terminal — **P2** até o segundo aparecer.

**Teste de aceite:** `grep -n "finalizada:" StatusBadge.jsx` volta 1 linha, dentro de `documento`.


## `TabBar` pinta fundo na aba ativa — o guia do DS diz que nunca deve pintar  ·  D (contradição interna)

**Estado atual, medido em 08/09/2026.** O guia do DS (§Layout rules) escreve *"Tabs
underline-active in primary, never pill-active"*. O `TabBar` que renderiza a aba
(`_ds_bundle.js` L6605) pinta a ativa com `background: color-mix(in oklch, var(--accent-soft)
50%, transparent)` **além** do `borderBottom: 2px solid var(--accent)` (L6645-6648).

**Contorno na tela.** Fabricação aplicou o componente como está (onda A, §4.1 do handoff) e
corrigiu a própria ficha, que repetia a frase do guia. Nenhum override.

**Proposta.** Decidir qual das duas fontes é normativa e alinhar a outra: ou o guia perde a
frase, ou o `TabBar` perde o `background`. Enquanto não decidir, **toda tela que usar
`TabBar` fica em desacordo com o guia** — não é um caso isolado da Fabricação. **D**, não P.

**Teste de aceite:** guia e `TabBar.jsx` dizem a mesma coisa sobre o fundo da aba ativa.


## `DatePicker` não aceita data ISO local — string crua volta um dia em fuso negativo  ·  P1

**Estado atual, medido.** O docblock declara `value (Date|ISO|null)` (`_ds_bundle.js` L3348).
Passando a ISO crua `"2026-08-01"`, o componente a interpreta como meia-noite **UTC**; em UTC-3
o campo exibe **`31/07/2026`**. Todo ERP brasileiro guarda data como `aaaa-mm-dd` — o caminho
que parece certo é o que erra, e erra silenciosamente (nenhum aviso no console).

**Contorno na tela.** Fabricação converte nos dois sentidos com data local
(`manufacturing-producao.jsx` L17-18): `new Date(y, m-1, d)` na entrada, `getFullYear/
getMonth/getDate` na saída. Cinco campos.

**Proposta.** Interpretar a string `aaaa-mm-dd` como **data local**, não UTC — é o que o
formato significa num campo de data sem hora. **P1:** cinco campos numa única tela, e qualquer
tela de período (financeiro, BI, relatórios, ponto) cai no mesmo buraco.

**Teste de aceite:** `<DatePicker value="2026-08-01" />` mostra `01/08/2026` em UTC-3.


## Nenhum componente do DS anuncia mudança a leitor de tela (sem `aria-live`)  ·  P1

**Estado atual, medido.** `grep -n "aria-live"` nos 9.204 linhas do `_ds_bundle.js` volta
**zero**. `Toast` (L6900), `Alert` (L738) e `EmptyState` (L4126) são puramente visuais.

**Onde dói.** "Receita salva", "Configurações atualizadas", "3 preços atualizados" — quem usa
leitor de tela não recebe nenhum deles. Não é da Fabricação: vale para Clientes, Financeiro,
Atendimento e Oficina do mesmo jeito.

**Proposta.** `role="status"` no `Toast` (`aria-live="polite"`) e `role="alert"` no
`Alert` de tom `danger`. **P1** — é retorno de ação, não enfeite.

**Teste de aceite:** salvar uma receita com VoiceOver ligado anuncia o texto do toast.


## `TabBar` não navega por seta ←/→  ·  P2

**Estado atual.** O `TabBar` (L6605-6672) trata clique e marca `aria-current="page"`. Não é
`role="tablist"`, não escuta `ArrowLeft`/`ArrowRight`/`Home`/`End`.

**Contorno na tela.** Nenhum possível por fora: teclado é interno ao componente; acrescentar
listener na tela exigiria reimplementar o `TabBar`, o que está descartado.

**Proposta.** Navegação por seta dentro do componente. **P2** — segundo caso já existe (toda
tela com `TabBar`), mas espera a decisão do item `role="tablist"` vs. `nav`, que muda a
semântica das abas de estado × abas de rota.

**Teste de aceite:** com foco numa aba, `→` move para a seguinte e a ativa.


## `Input` não emite `aria-invalid` nem `aria-describedby` quando tem `error`  ·  P1

**Estado atual, medido.** Com `error`, o `Input` (L4481) pinta a borda e grava
`data-invalid` (L4504). Não há `aria-invalid` nem ligação entre o campo e o texto do erro.

**Onde dói.** O erro existe visualmente e não existe para o leitor de tela. Vale para
`Input`, `Select` e `Textarea`, que compartilham o padrão.

**Proposta.** `aria-invalid={!!error}` e `aria-describedby` apontando para o `id` do texto
de erro/ajuda. **P1** — a régua de acessibilidade do próprio guia diz "acessibilidade é
não-negociável".

**Teste de aceite:** `<Input error="x" />` renderiza `aria-invalid="true"` e o texto do erro
é lido junto com o campo.


---

## Erro meu, corrigido: usei tom SÓLIDO em StatusBadge de estado  ·  ✅ CORRIGIDO NA TELA (2026-09-08)

> ⚠️ **REABERTO em 23/09/2026.** Os tons `soft-*` saíram da origem; a tela voltou ao sólido
> porque é o único tom genérico com cor que o componente ainda tem. Ver o item de 23/09 no fim.

Não é proposta ao DS — é registro de desvio meu, para não repetir.

**O que fiz de errado na onda A.** As pílulas de faixa de margem (3 usos: `manufacturing-page.jsx`
lista de receitas, `manufacturing-insumos.jsx` peso do insumo e margem simulada) saíram com
`tone="success" | "warning" | "danger"`. Esses tons são **fill sólido**: `bg: var(--color-success)`,
`fg: '#fff'` (bundle L6297-6310).

**A regra que isso viola.** O guia do DS, AP7: status badge é *"5–10% tinted background + dot +
colored text"*, e **"nunca `bg-fill` sólido nem pastel"**. A régua estava escrita e eu passei por
cima dela escolhendo o nome de tom mais óbvio.

**Corrigido para** `soft-success` / `soft-warning` / `soft-danger` (L6404-6427): fundo
`--color-*-soft`, texto `--color-*-fg`, borda 20% e **dot ligado** — que é a 3ª perna do AP7.

**Como conferir:** nenhum `StatusBadge` da família Fabricação usa `tone="success|warning|danger"`
sem o prefixo `soft-`. `grep -n 'tone={.*"success"' manufacturing-*.jsx` volta 0.

**Lição:** o tom com o nome mais curto não é o tom padrão. Ler a tabela `C` inteira antes de
escolher — os tons sólidos existem para outra coisa, não para estado.


---

## `TabBar` transborda 1px no eixo vertical (a calha some, o transbordo fica)  ·  P2

**Estado atual, medido em 08/09/2026.** O `TabBar` (bundle L6677) declara só
`overflowX: 'auto'`. Pela regra do CSS, quando um eixo deixa de ser `visible` o outro computa
`auto` — então `overflowY` vira `auto` sem ninguém pedir. Com o botão em `height: 36` e
`marginBottom: -1` dentro de um `<nav>` com `borderBottom: 1px`, o conteúdo mede
`scrollHeight 36` num `clientHeight 35`.

**Sintoma que a usuária viu:** uma barra de rolagem **vertical** de 10px à direita das abas
(medido antes da atualização: `width 624` vs `clientWidth 614`).

**Já mitigado na origem:** a versão de 08/09 acrescentou `scrollbarWidth: 'none'` e
`.ds-tabbar-scroll::-webkit-scrollbar{display:none;height:0}`, que escondem as duas calhas.
Medido depois: calha 0. **O transbordo de 1px continua** — só não é mais desenhado.

**Proposta.** `overflowY: 'hidden'` explícito no `<nav>`, ou tirar o `marginBottom: -1`
compensando no `borderBottom`. **P2:** o sintoma está coberto, mas 1px de scroll vertical numa
faixa de navegação é armadilha para quem usa roda do mouse ou trackpad sobre as abas.

**Teste de aceite:** `n.scrollHeight === n.clientHeight` no `nav[aria-label="Sub-navegação"]`.


---

## Regressão na origem: `StatusBadge` perdeu `producao` e os tons `soft-*`  ·  D (defeito de origem) · pergunta ao Wagner

**Estado atual, medido em 23/09/2026.**
- `components/StatusBadge/StatusBadge.d.ts` (fonte viva) L17-22: o `StatusTone` é
  `success | warning | danger | info | neutral | outline | sla-* | canal-* | fresc-* | tipo-*`.
  Não há `soft-*`. L3-14: o `StatusKind` tem 11 domínios, sem `producao`.
- `_ds/wagner-…49a36f76…/_ds_bundle.js` (espelho regenerado em 21/09): busca por
  `soft-success` e `arquivo_prazo` volta **0**. Busca por `producao` só acha a sidebar (L980) e o
  `em_producao` do domínio `os` (L7493).
- Em 08/09 os dois existiam no bundle que a página carregava (`producao` em L6537-6540 e
  `soft-*` em L6404-6427, registrados nesta pauta e no handoff da Fabricação §21). **Não medi** se
  saíram da origem ou se aquele bundle de 08/09 tinha conteúdo que nunca esteve na origem. Medir
  isso exige o histórico do projeto do DS ou do repo.

**Consequência, sem erro no console.** `StatusBadge.jsx` L97-99 cai em `outline` para tom ou
domínio desconhecido. Até a onda A de 23/09 a Fabricação mostrava as 3 pílulas de margem
transparentes, sem cor, e a situação da ordem em minúsculas ("rascunho").

**Contorno na tela (onda A, 23/09).** Margem com `tone="success|warning|danger"`; situação da
ordem com `kind="documento"` + `tone` + `label` (d.ts L34-35 prevê isso para status não mapeado).

**A tensão que precisa de decisão.** O guia (AP7) manda fundo tintado 5–10% + dot + texto colorido,
*"nunca bg-fill sólido"*. O componente hoje só oferece suave nos tons de domínio (`sla-*`,
`fresc-*`, `canal-*`, `tipo-*`). Os tons genéricos com cor são todos sólidos (L12-15). Não há
combinação que cumpra o AP7 e o docblock ao mesmo tempo. Usar `fresc-*` pintaria margem com a cor de
recência de CRM, que a própria pauta registra reprovando contraste no claro.

**Pergunta ao Wagner.** (a) Voltar `soft-success/warning/danger` e o domínio `producao` ao
componente; ou (b) declarar que o sólido vale para faixa numérica e o AP7 vale só para estado. Até a
resposta, a tela fica no sólido, que é valor do DS aplicado como está.

**Teste de aceite (a):** `/'soft-success'/.test(String(window.OfficeImpressoPontoWR2DesignSystem_019dd0.StatusBadge))` → `true`.


## `DataGrid`: nome da caixa de seleção usa o `id` da linha  ·  P1

**Estado atual, medido em 23/09/2026.** `components/DataGrid/DataGrid.jsx` (fonte viva) L210:
`aria-label={'Selecionar ' + row.id}`. Não há prop para o rótulo.

**Onde dói.** Na lista de receitas da Fabricação o leitor de tela lê "Selecionar 3". Antes da
onda B lia "Selecionar Banner lona 440g". Regressão de acessibilidade aceita para usar a peça do DS.

**Proposta.** `rowLabel?: (row) => string` no `DataGrid`, com o `id` como fallback. **P1**: toda
grade selecionável do ERP tem `id` numérico.

**Teste de aceite:** `<DataGrid selectable rowLabel={(r) => r.cells.name.primary} …/>` renderiza
`aria-label="Selecionar Banner lona 440g"`.


## `DataGrid`: `onRowClick` vale para todas as linhas  ·  P2

**Estado atual.** `DataGrid.jsx` L201-204: com `onRowClick`, **toda** linha ganha
`role="button"`, `tabIndex=0` e cursor de clique. Não dá para marcar uma linha como não clicável.

**Onde dói.** Na aba Insumos, o insumo sem receita não tem o que abrir. Ele recebe foco e cursor,
e o clique não faz nada (a tela ignora no handler).

**Proposta.** Respeitar `row.clickable === false`. **P2**: primeiro caso.

**Teste de aceite:** linha com `clickable: false` não tem `role` nem `tabIndex`.


## `ToolbarSearch`: a tecla mostrada não funciona  ·  D (defeito de origem)

**Estado atual.** `components/Toolbar/Toolbar.jsx` (fonte viva) L59-72: `kbd` só desenha o
`<kbd>`. Não há `focusKey`, `inputRef` nem `aria-label`. O template PT-01 passa `kbd: '/'`,
então o índice canônico anuncia um atalho que não existe. O `SearchInput` (`Input.jsx` L83) faz
o mesmo desenho **e** liga o atalho.

**Contorno na tela.** A Fabricação usa `SearchInput` dentro do `Toolbar` (onda B, 23/09).

**Proposta.** `ToolbarSearch` reusar o `useFocusKey` do `Input`, ou o PT-01 trocar para
`SearchInput`. **D**: o componente mostra uma promessa que não cumpre.

**Teste de aceite:** no PT-01, apertar `/` fora de campo põe o foco na busca.


## `Segmented` limita a 5 opções  ·  observação, sem proposta

A Fabricação usa `Segmented` no filtro de categoria (Todas + 3 categorias, derivadas das receitas).
O d.ts declara 2–5. Registrado para quem acrescentar a 5ª categoria saber que a 6ª pede outra peça.


### Atualização 24/09/2026: medido no repo (`oimpresso.com`, cópia local)

A pergunta de 23/09 muda de forma. O defeito é de **sincronização git → projeto do DS**, não de origem.

- **`resources/js/Components/ui/badge.tsx` L25-33:** `success`/`warning`/`danger`/`info` são o par
  SUAVE (`bg-*-soft text-*-fg border-*/20`). O comentário registra a "Onda M1", o #2641.
  `neutral` = muted.
- **`resources/js/Components/shared/StatusBadge.tsx`:**
  - L24-40: o tipo de variante passou a ser **derivado** do `badgeVariants` (26/08). O comentário
    conta 49 entradas em fill sólido, o que é AP7 violado na camada compartilhada.
  - L292: `dot` ligado por padrão.
  - L227 (`arquivo_prazo`), L242 (`ajuste_estoque`) e L263 (`transferencia_estoque`): migrados
    para o par suave, citando o #6268 e o #6325 (*"`danger` é o par SOFT, `destructive` é o fill"*).
- **Mesmo arquivo, L99-104:** o domínio `producao` existe no repo (US-MANU-004), mas ainda em fill
  sólido (`bg-success`; rascunho `bg-warning`), com a justificativa UI-0029, que dá ao protótipo a
  forma. É um dos 49 que o próprio arquivo chama de erro e ainda não migrou.
- **Projeto do DS, `components/StatusBadge/StatusBadge.jsx` L12-15:** `success/warning/danger` são
  sólidos (`bg: var(--color-*)`, `fg: #fff`). Não há `producao`, `arquivo_prazo`, `transferencia_estoque`
  nem `dot` nos tons genéricos. **O projeto do DS está atrás do repo** nesse componente.
- **Repo, `Pages/Manufacturing/Recipes.tsx` L345 e `Insumos.tsx` L292:** a margem é `.mfg-pill ok/warn/bad`
  local, com fundo 8% e borda 30% (`cowork-manufacturing-bundle.css` L71-74). Já é suave, só que fora do
  componente.
- **Não medi:** a origem dos `soft-*` que o bundle de 08/09 tinha. A cópia local não traz o histórico git.

**Conclusão:** a margem é faixa de estado, e o repo (SSOT) decide suave: `variant="success|warning|danger"`.
O protótipo não consegue pintar suave com o componente do DS vinculado sem inventar cor. Então continua
sólido, que é o valor do DS aplicado como está, até o Wagner puxar o `StatusBadge` do git para o
projeto do DS.

**Pedido ao Wagner:**
1. Regenerar o `StatusBadge` do projeto do DS a partir de `shared/StatusBadge.tsx` + `ui/badge.tsx`
   do repo: tons suaves, `dot` e os domínios `producao`, `arquivo_prazo`, `ajuste_estoque` e
   `transferencia_estoque`.
2. Migrar o `producao` do repo (L99-104) para o par suave, como os outros domínios de estado.

**Teste de aceite:** no projeto do DS, `<StatusBadge tone="success" label="62%"/>` renderiza fundo
`--color-success-soft`, texto `--color-success-fg` e dot.
