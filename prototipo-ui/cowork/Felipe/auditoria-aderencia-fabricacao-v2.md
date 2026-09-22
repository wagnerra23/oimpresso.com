# Auditoria de aderência ao DS — módulo Fabricação (Manufacturing) · v2

> 🔴 **CORREÇÃO DE 09/09/2026 — leia antes de usar as listas B e C.** Esta auditoria foi levantada
> contra o **espelho compilado** do DS, e o espelho **não é fonte** (git é SSOT — ADR 0239/0299/0315).
> **Dois erros meus, o segundo maior que o primeiro:**
> **(1)** o teclado na linha **já está no espelho** (`_ds_bundle.js` **L3041-3052**: `tabIndex`,
> `role="button"`, `aria-label`, Enter/Espaço) e no repo (`shared/DataTable.tsx` L370-381) — **B-01
> nunca esteve bloqueado**; largura mínima e cabeçalho fixo vêm do CSS do módulo, como o repo faz
> (docblock L173), não de prop.
> **(2)** **B-02 está errado duas vezes:** com `role="button"` e sem `aria-label`, o nome acessível é
> **computado do conteúdo** — a linha fica **verbosa, não anônima**; e o repo resolve outro problema
> (nome da **tabela**, via `caption` obrigatório L126-142, com medição axe-core de 2026-09-04).
> **C-01** também está retirado (`shared/PageHeaderTabs.tsx` L146-220). Nada disso é defeito do DS:
> é `ds-mirror-drift`, resolvido por push git→design.
> **Todo item restante das listas B e C precisa ser remedido contra o repo antes de ir à pauta** —
> foram levantados pelo mesmo método. Ver `decisao-wagner-fabricacao-ds.md`. O §2 (pacote de handoff
> velho), o §6 (CSS da tela) e o §8.11 (rede de proteção) não dependem do DS e seguem válidos.

> **Remedida em 09/09/2026, no código de hoje.** A v1 (`auditoria-aderencia-fabricacao.md`) foi
> levantada **antes** da onda A; os 23 itens da lista A dela estão no código e não aparecem mais
> aqui. Esta v2 audita o estado atual, corrige **dois erros da v1** (§7) e traz achados novos —
> incluindo dois de risco máximo que não são de tela, mas de **pacote** (§2).
>
> Nenhum arquivo foi alterado. Aguardo sua aprovação.

**Procedência de cada valor:** `[DS]` citado do bundle compilado, com a linha da declaração ·
`[TELA]` decidido na tela · `[RUNTIME]` observado no navegador (não aparece como regra aqui).

---

## §0 · Escopo medido — quatro superfícies, não duas

| # | Superfície | Arquivos | Consome DS? | Entra na auditoria |
|---|---|---|---|---|
| 1 | **Raiz — é a que roda** | `manufacturing-page.jsx` (401L) · `-recipe.jsx` (267L) · `-producao.jsx` (383L) · `-insumos.jsx` (111L) · `-print.jsx` (129L) · `-data.jsx` (191L) · `manufacturing-page.css` (221L) | **sim**, 25 componentes | ✅ é o objeto das três listas |
| 2 | **Pacote de handoff** | `handoff_fabricacao/design/manufacturing-*.jsx` + `04-modulos/manufacturing/css/manufacturing.css` (21.032 ch) | **não** — snapshot pré-onda-A | ⚠️ §2 · P-1 e P-2 |
| 3 | **Mockup estático** | `mockup-bodies.js` L13 (chave `"manufacturing"`) + `mockup-pages.css` L293-307 (`.mfg-grid`/`.bom`/`.mfg-orders`) | não | ❌ fora — maquete de baixa fidelidade, classes homônimas e independentes das `.mfg-*` do módulo. Se entrar, é substituição inteira, não troca de peça |
| 4 | **Árvore antiga do shell v2** | `erp-shell-v2/Telas Faltantes Onda 2.html` L387-420 (tela **#4 "Manufacturing · BOM + ordens"**) + `erp-shell-v2/mockup-pages.css` L313-327 | não | ❌ fora — **esta é a "outra tela do módulo" que você pediu para eu nomear.** É a maquete #4 da Onda 2, com BOM em árvore de 3 níveis e coluna de ordens; não compartilha CSS nem dados com a família |

**Não são tela e não entram:** `funcoes-perms.jsx` L105-107 (catálogo de permissões `manufatura`),
`icons.jsx` (primitivo `window.I`, 22 glyphs), `handoff_fabricacao/design/adr/*` e os `.md` de
conferência.

**Qual espelho do DS é o normativo:** desde 21/09/2026 há um só — `oimpresso.com.html` carrega
`_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js`, cópia byte a byte do DS vivo
(349.364 B), sem shim de alias. A pasta `019dd02f` citada na medição original foi apagada. Toda linha `[DS]` abaixo
vale nos dois. **Defeito de documentação:** o comentário de `oimpresso.com.html` L119 ainda diz que
o bundle publica `window.OfficeImpressoPontoWR2DesignSystem_019dd0` — ele publica
`window.OfficeImpressoDesignSystem_49a36f` (bundle L5) e o global antigo é só um alias (L9301).

**Inventário do DS, fechado (41 exports, lidos um a um):** Alert · AppSidebar · Avatar ·
BoardColumn · Breadcrumb · BulkBar · Button · Chart · Checkbox · Command · DataTable ·
DataTablePro · DatePicker · Dimension · Drawer + DrawerSection · DropdownMenu · EmptyState ·
FilterChip · FsmStepper · Input · Textarea · Select · KpiCard · KpiFilterCard · Logo · Modal ·
PageHeader · Pagination · PeriodBar · PlacaVeiculo · Progress · ProofFrame · ProofStrip ·
**RadioGroup** · RegistrationMark · Skeleton · StatusBadge · Switch · TabBar · TagChip · TaskCard ·
Toast · Tooltip. **Não existem:** `Card`, `Slider`, `Combobox`, `Separator`/`Divider`,
`KeyValue`/`SummaryList`, rodapé de tabela, região de anúncio. Lista **fechada** — foi conferida por
`Object.assign(__ds_scope, …)`, não por leitura de README.

---

## §2 · Risco máximo — os dois achados que não são de tela

Estes vêm antes de qualquer item das três listas: eles quebram a entrega, não um elemento.

**P-1 🔴 O pacote de handoff é o código velho.** Os 5 arquivos de tela em
`handoff_fabricacao/design/` divergem da raiz — medido byte a byte nesta auditoria:

| arquivo | raiz | pacote |
|---|---|---|
| `manufacturing-page.jsx` | 21.655 ch | 20.595 ch |
| `manufacturing-recipe.jsx` | 15.578 ch | 15.254 ch |
| `manufacturing-producao.jsx` | 22.449 ch | 20.804 ch |
| `manufacturing-insumos.jsx` | 5.977 ch | 5.316 ch |
| `manufacturing-print.jsx` | 6.768 ch | 6.456 ch |
| `manufacturing-data.jsx` | 11.412 ch | 11.412 ch — **o único idêntico** |

O conteúdo do pacote é o estado **pré-onda-A**: `handoff_fabricacao/design/manufacturing-insumos.jsx`
L46 e L80 ainda pintam `.mfg-pill`, L54-55 ainda montam `.mfg-scrim` + `.mfg-drw` à mão, L87 ainda
usa `os-btn ghost`. O CSS do pacote também: `.mfg-pill` L47-50, `.mfg-kpi*` L11-18, `.mfg-tab*`
L9 — classes que a raiz já apagou. **E o próprio pacote afirma o contrário:**
`handoff_fabricacao/design/README.md` L87-89 diz *"A família consome o bundle compilado do DS desde
a onda A (2026-09-08): 23 elementos vêm de `window.OfficeImpressoDesignSystem_49a36f`"*. O documento
foi atualizado; o código ao lado dele não. Quem implementar lendo o pacote reconstrói a versão que
não passa por nenhuma das correções da onda A.

**P-2 🔴 O guia do pacote não carrega o bundle do DS.**
`handoff_fabricacao/design/Fabricacao - Guia de Producao.html` L20-32 lista os 4 `<link>` de CSS e
L43-50 os 7 `<script type="text/babel">` — **nenhuma tag carrega `_ds_bundle.js`**. Mesmo depois de
sincronizar P-1, `ds()` (page L15) devolveria `{}`, `PageHeader`/`TabBar`/`Drawer`/`Modal` viriam
`undefined` e o React lançaria *"Element type is invalid"* na primeira pintura. O guia está hoje
consistente consigo mesmo só porque o código dele é o velho.

*Teste de aceite dos dois:* `diff` dos 6 `.jsx` volta vazio, e
`grep -c "_ds_bundle" "Fabricacao - Guia de Producao.html"` volta ≥ 1.

---

## §3 · Lista A — troca direta (a peça do DS resolve como está)

Ordenada por risco. Cada linha: **o que é · onde · peça do DS · o que a troca ganha ou custa.**

| # | Risco | Elemento · onde · peça do DS |
|---|---|---|
| A-01 | 🟠 comportamento | **Chips de categoria** (escolha única, 4 opções) · `manufacturing-page.jsx` L232-234 · **`RadioGroup`** `[DS]` L6067 com `direction="row"` — é a peça oficial de escolha única: monta `role="radiogroup"` (L6076) e o grupo de rádios nativo traz navegação por seta, que os chips não têm. **Custo declarado:** perde a aparência de pílula. *(A v1 chamou isto de bloqueado por olhar só o `FilterChip` — ver §7.)* |
| A-02 | 🟠 comportamento | **Chips de permissão** (4 alternâncias independentes) · `manufacturing-producao.jsx` L362-366 · **`Checkbox`** `[DS]` L2511 ×4 — alternância múltipla é caixa, não pílula; ganha rótulo associado e alvo do próprio componente. Mesmo custo visual do A-01 |
| A-03 | 🟡 comportamento | **Dica de remover** em `title=` nativo · `manufacturing-recipe.jsx` L157 e L183 · **`Tooltip`** `[DS]` L7023 — abre em **foco** além de hover (L7107-7108); hoje `aria-label` garante a leitura, mas a dica é só de mouse |
| A-04 | 🟡 visual | **"Carregando …" em `<p class="mfg-note">`** · `manufacturing-page.jsx` L33-35, usado em L162, L174, L292, L297, L301, L305 · **`Skeleton`** `[DS]` L6187 (`variant="row"`/`"card"`) — silhueta no lugar de uma frase. **Não resolve o anúncio** (o `Skeleton` também é só visual) — ver C-02 |
| A-05 | 🟡 visual | **Botão-ícone de 22 px** (remover grupo / remover ingrediente) · css L122 · `manufacturing-recipe.jsx` L157, L183 · **`Button`** `[DS]` L2246 com `icon` + `size="sm"` — some 1 raio fora do canon (5 px). **Custo declarado:** 22 → 26 px `[DS]` L2257; continua abaixo de 44 px |
| A-06 | 🟡 visual | **Botão "esc" da busca de insumo** · css L129 · `manufacturing-recipe.jsx` L96 · **`Button`** `size="sm"` `kbd="esc"` — a assinatura tem `kbd` (L2251); hoje é a palavra "esc" em texto de 10 px |

---

## §4 · Lista B — bloqueado pelo DS (a peça existe; falta prop ou variante)

| # | Risco | Elemento · onde · peça do DS · **falta exatamente** |
|---|---|---|
| B-01 | 🔴 acessibilidade | **4 tabelas em `div` com `onClick`** · `manufacturing-page.jsx` L239-249 (cabeçalho) e L251 (linha) · `manufacturing-producao.jsx` L77-82 e L84 · `-insumos.jsx` L41-46 e L48 · `-producao.jsx` L297-301 (relatório, não clicável) · **`DataTable`** `[DS]` L2894. **A linha não é focável nem acionável por teclado, e nunca foi:** sem `tabIndex`, sem `role`, sem Enter/Espaço. O `DataTable` entrega os quatro (L2988-3004) e `aria-sort` no cabeçalho (L2957). **Falta:** cabeçalho fixo e largura mínima — o `thBase` do `DataTable` (L2911-2921) **não tem `position:sticky`** e a tabela não aceita `minWidth`, e a tela depende dos dois (`.mfg-thead{position:sticky}` css L37; `min-width` 960/1100/900/940 css L34, L74, L76, L78). O `DataTablePro` (L3099) tem header fixo (L3186) e largura mínima **derivada** (`tableMin`, L3183), mas ordena e seleciona **por dentro** (L3110-3113) e exige `height` (L3103) — a tela perde o controle do estado que ela já tem. **Ou** `stickyHeader` + `minWidth` no `DataTable`, **ou** ordenação/seleção controladas no `Pro`. **É a falha mais grave da auditoria** |
| B-02 | 🔴 acessibilidade | **Nome acessível da linha** · dentro do próprio `DataTable` `[DS]` L2993: `const label = row.cells && row.cells.cli && row.cells.cli.primary` — a chave **`cli`** está cravada (é do Clientes). Com `cells.name`, as linhas de receita viriam `role="button"` **sem nome nenhum**. **Falta:** `rowLabel` (chave ou função) no `DataTable`. Achado novo — não estava na v1, e ele torna B-01 uma troca com defeito se rodar como está |
| B-03 | 🔴 comportamento | **Busca com atalho `/`** · `manufacturing-page.jsx` L228-231 (ref L70, listener L76-79) · `-insumos.jsx` L32-35 · **`Input`** `[DS]` L4481. Assinatura medida (L4481-4493): `{label, help, error, value, defaultValue, placeholder, type, disabled, readOnly, onChange, name}`. **Falta:** `ref` (o `/` foca por `buscaRef.current.focus()`), `onKeyDown` (a busca de insumo depende de Enter e Esc, recipe L95) e slot de ícone (**já é P1 na pauta** — não abrir item novo). Sem os três, trocar a busca **apaga o atalho `/` e o Enter do seletor de insumo** |
| B-04 | 🟠 acessibilidade | **Erro inline não ligado ao campo** · `manufacturing-recipe.jsx` L59-60 (o `Alert tone="danger"` fica **fora** do `Input` de L57-58) · **`Input error`** `[DS]` L4481. O componente pinta a borda e grava `data-invalid` (L4504) mas **não emite `aria-invalid` nem `aria-describedby`** — nenhum dos dois aparece na função (L4494-4508). O leitor de tela não liga o erro ao campo, nem antes nem depois da troca |
| B-05 | 🟠 comportamento | **9 campos numéricos com passo** · quantidade de ingrediente `-recipe.jsx` L170 e consumo `-producao.jsx` L152 (`step="0.001"`); `-recipe.jsx` L64, L212, L221, L225 (`max="100"`), L232, L236 · `-producao.jsx` L189 · **`Input type="number"`** `[DS]` L4481. **Falta:** `min` / `max` / `step` — não existem na assinatura. Trocar hoje derruba o passo de milésimo e o teto de 100% do desperdício |
| B-06 | 🟠 estrutura | **Select compacto dentro da linha** (sub-unidade do ingrediente, 26 px) · css L91 · `-recipe.jsx` L175 · **`Select`** `[DS]` L4546. **Falta:** tamanho/variante sem moldura de campo — o `Select` sempre passa por `fieldShell` (L4581) com rótulo em caixa alta acima, o que não cabe numa célula de grade |
| B-07 | 🟡 estrutura | **Trilha de volta do editor** ×2 · `-recipe.jsx` L141-145 · `-producao.jsx` L135-139 · **`Breadcrumb`** `[DS]` L2077. **Falta:** `onSelect` no item — a assinatura só aceita `items [{label, href}]` (L2078) e a volta aqui é por **estado** (`onCancel`), não por rota |
| B-08 | 🟡 estrutura | **Botão tracejado "＋ Ingrediente em ⟨grupo⟩"** (3 usos) · css L124-126 · `-recipe.jsx` L190, L202, L204 · **`Button`** `[DS]` L2246. **Falta:** `variant="dashed"` — as variantes são `primary`/`ghost`/`danger` (L2274-2296) |
| B-09 | 🟡 estrutura | **Botão-ponte em texto corrido** (7 usos: page L393, `-producao.jsx` L261, L323, L372, L373, L374, `-insumos.jsx` L93, L101) · css L94 · **`Button`** `[DS]` L2246. **Falta:** `variant="link"` — todas as variantes têm caixa e altura fixa (L2257), e estes vivem dentro de `<p>`/`<li>` |
| B-10 | 🟡 estrutura | **"Grupo sem ingredientes." / "Escolha uma receita com ingredientes."** (3 usos: page L376, `-recipe.jsx` L187, `-producao.jsx` L172) · css L137 · **`EmptyState`** `[DS]` L4126. **Falta:** variante compacta — o componente é um bloco centrado com ícone, título, descrição e ação (L4165-4225); o vão aqui é de uma linha dentro de um grupo |
| B-11 | 🟡 visual | **Contador de aba com duas informações** (`6 · 1 rasc.`) · `manufacturing-page.jsx` L196-201 · **`TabBar count`** `[DS]` L6734-6746. A pílula é `padding: 0 6px; minWidth: 18` (L6738-6740). **Falta:** acomodar contador composto — ou encurtar o rótulo, que é decisão da tela. *(O botão da aba declara `whiteSpace:'nowrap'` (L6707), então o vazamento medido no LAUDO a 914 px não se repete igual — remedir depois de decidir.)* |

---

## §5 · Lista C — não existe no DS (precisa nascer)

| # | Risco | O que é · onde · **por que não dá para compor** |
|---|---|---|
| C-01 | 🔴 acessibilidade | **Navegação por seta entre abas** · `manufacturing-page.jsx` L204-206 · o `TabBar` `[DS]` L6677-6749 monta `<nav aria-label>` + `<button aria-current>` (L6683, L6700): trata clique, **não é `role="tablist"`, não escuta ←/→ nem Home/End**. O teclado é interno ao componente — não há como acrescentar por fora sem reimplementar o `TabBar`, o que está descartado |
| C-02 | 🔴 acessibilidade | **Região de anúncio (live region)** · todo retorno de ação: `manufacturing-page.jsx` L71 (`aviso()`) → L332-334 (o `Toast`), `-producao.jsx` L354 ("Configurações atualizadas") · **medido: `aria-live` aparece 0 vez nas 9.290 linhas do bundle**; o `Toast` (L6975-7011) é uma `<span>` estilizada, sem `role="status"`. `Alert`, `EmptyState` e `Skeleton` também são só visuais. Compor exigiria um nó fora do DS — exatamente o que a regra proíbe fazer em silêncio. **Vale para o sistema inteiro, não só para a Fabricação** |
| C-03 | 🟠 estrutura | **Seletor de insumo com busca ao vivo** (combobox inline) · `-recipe.jsx` L91-107 · sem `role="combobox"`, `aria-expanded`, `aria-controls` ou `aria-activedescendant`; ↑/↓ não andam pelos 7 resultados; Enter escolhe o primeiro (L95). O `Command` `[DS]` L2581 tem filtro ao vivo, ↑/↓/↵/esc e foco preso — **mas é paleta modal de tela cheia** (`open`/`onClose`/`groups`), e aqui o seletor nasce **dentro da linha do grupo**. Forma errada, não prop faltando |
| C-04 | 🟠 estrutura | **Grade de ingredientes editável** — cabeçalho com nome + contagem + subtotal derivado; linhas de 4/5/6 colunas com campo numérico, select de sub-unidade e remoção; linha tingida por regra · 4 variantes: `-recipe.jsx` L149-191 (6 col) · `-producao.jsx` L144-172 (5 col) · page L362-378 (4 col, leitura) · `-insumos.jsx` L89-100 (5 col) · o `DataTable` aceita nó em célula, então o campo cabe — mas **não tem linha de grupo com subtotal, não tem rodapé, e o estado de linha é fechado em `urgent`/`archived`/`selected`** (L2985-2988), enquanto `.mfg-ing.falta` (css L118) tinge por regra de negócio. `DrawerSection` (L3907) é só título + conteúdo. É a peça central do módulo e não tem equivalente |
| C-05 | 🟠 estrutura | **Slider** (simulador de variação de preço, −30…+60, passo 5) · `-insumos.jsx` L78-86 · não há `Slider` nos 41 exports. `Progress` (L5796) é leitura, não entrada. *(O `input[type=range]` de hoje já tem `aria-label` e `aria-valuetext` — L80-81 — então o teclado funciona; o item é estrutural, não de acessibilidade.)* |
| C-06 | 🟡 estrutura | **Quadro de custo** (`<dl class="mfg-tot">`: pares, régua, linha de destaque) — 4 usos · page L382-392 · `-recipe.jsx` L241-247 · `-producao.jsx` L194-202, L250-259 · não há `KeyValue`/`SummaryList`; `KpiCard` é tile, `DrawerSection` é só moldura. É onde moram os 4 usos inline de `--accent` como texto |
| C-07 | 🟡 estrutura | **Rodapé de total de tabela** · `-producao.jsx` L110 e L323 (`.mfg-foot`) · o `DataTable` não tem slot de rodapé (L3046-3049 fecham no `tbody`). Hoje é um `<p>` **fora** da tabela — nem `<tfoot>` existe, então o total não é lido como parte dela |
| C-08 | 🟡 estrutura | **Cartão de painel** (Configurações, 3 usos) · css L141 · `-producao.jsx` L337, L358, L369 · **não há `Card` nos exports** — o README do DS descreve a anatomia canônica do `<Card>` shadcn, mas o espelho compilado não publica o componente. Enquanto isso o cartão é `div` + borda + raio 10 px |
| C-09 | 🟡 estrutura | **Divisória de seção** (rótulo em caixa alta + régua) — 8 usos · css L51-53 · `-recipe.jsx` L208, L240 · `-producao.jsx` L143, L175, L193, L338, L359, L370 · não há `Separator`/`Divider` nos exports |
| C-10 | 🟡 visual | **Paleta de impressão** · css L179-221 · o cockpit não publica token de papel/tinta (ADR 0413). Mesmo com os quatro print-craft já em uso (`-print.jsx` L30, L27, L44, L103-104), a folha continua com cinza literal — ver §6 |
| C-11 | 🟡 visual | **Inventário de ícones** · `icons.jsx` (22 glyphs) usado em page L214, L215, L229, L266, L280 · `-recipe.jsx` L190, L204 · `-producao.jsx` L114, L233 · `-insumos.jsx` L33, L68 · o espelho tem 22, o produto usa Lucide inteiro. Já registrado na pauta como **D**; é contorno declarado, não peça nova |

---

## §6 · Camada visual — varredura de valor cru no código de hoje

Medido no `manufacturing-page.css` (221L, já enxugado pela onda A) e nos JSX.

| Achado | Quanto · onde | Resolve em |
|---|---|---|
| **4 tamanhos fora da rampa `--fs-*`** (10,5 / 11,5 / 12,5 / 13,5 / 15 / 18 / 22 / 28 / 38) | **10 px** em 8 seletores (L38 `.mfg-th`, L52 `.mfg-sec span`, L61 `.mfg-ing .n small`, L85 `.mfg-fld>span`, L116 `.mfg-ing-h`, L129 `.mfg-pick-x`, L135 `.mfg-pick-i small`, L146 `.mfg-th.sort`) · **11 px** ×3 (L91, L104, L122) · **12 px** ×8 (L48, L56, L57, L58, L65, L134, L136, L142) · **14 px** ×1 (L153) — **20 seletores** | B-01, B-06, C-04, C-06, C-09 (o valor passa a vir do componente) |
| **3 raios fora do canon** (6/8/12/16/24/full) | `3px` L71 e `.mfg-grp-n` (bloco de hosts) · `5px` L122 · `10px` L108, L141 | A-05, C-08 |
| **Sombra/scrim em `rgba`** | **zero** no CSS de tela — as 4 da v1 saíram com o `Drawer`/`Modal`/`BulkBar`/`Toast`. Confirmado | — |
| **`--accent` como cor de texto** | 6 seletores (L31 `.mfg-chip.act`, L68 `.mfg-tot .big dd`, L94 `.mfg-link`, L101 `.mfg-crumb button`, L125 `.mfg-add:hover`, L148 `.mfg-th.sort.act`) + **4 inline** (page L389 · `-recipe.jsx` L246 · `-producao.jsx` L199, L255) | A-01, B-09, C-06. A reprovação no escuro (2,64:1) é **ADR 0411** — não se corrige aqui |
| **`--text-mute` em texto de 10-11,5 px** | 15 seletores (L27, L38, L45, L47, L50, L52, L61, L85, L86, L104, L116, L122, L129, L135, L137) | **ADR 0410** — o token é do DS; trocar por `--text-dim` é decisão sua, não da troca de componente |
| **9 valores literais de cinza no `@media print`** | `#fff` L182 · `#111 #555 #666 #777 #999 #bbb #ccc #f0eeeb` em L184-221 | **não resolve** — C-10 (ADR 0413). *(A v1 escreveu "11 cinzas"; medido agora, são 9 valores distintos.)* |
| **Estilo inline no JSX** | posicionamento do toast (page L332: `position:fixed`, `left:50%`, `bottom:24`, `zIndex:80`) · largura de filtro (`-producao.jsx` L70, L71, L72: `width:180`/`150`) · `maxWidth:260` L339 · `padding:"0 18px 8px"` page L360 · os 4 pares `fontSize:15 / fontWeight:600 / color:var(--accent)` de C-06 | o toast é achado próprio: **o DS não publica host/posição de toast** — hoje a tela decide onde ele aparece. Vai para a pauta junto com C-02 |

---

## §7 · Duas correções à v1 (li de novo o que eu mesmo escrevi)

1. **B-08 da v1 era um bloqueio falso.** Ela concluiu que os chips de escolha estavam bloqueados
   porque o `FilterChip` é pílula de filtro com ✕. Verdade — mas o DS publica **`RadioGroup`**
   (L6067, `direction` coluna/linha) para escolha única e **`Checkbox`** (L2511) para alternância
   múltipla, e nenhum dos dois foi consultado. Os dois usos viram **troca direta** (A-01, A-02), com
   perda de aparência declarada. *"O componente não tem" nunca é conclusão.*
2. **B-03 da v1 está resolvido pelo DS, não pela tela.** O `StatusBadge` ganhou `kind="producao"`
   com `finalizada`/`rascunho` (bundle L6520-6523, com o comentário citando UI-0029), e a tela já
   consome em `-producao.jsx` L100. Sai da lista.
3. **Contagem de cinzas de impressão corrigida** de 11 para 9 (§6).

---

## §8 · Os defeitos de comportamento que sobrevivem à onda A

A camada que você disse que costuma ficar de fora, junta, na ordem em que dói:

1. **Nenhuma das 4 tabelas é operável por teclado.** Quem só tem teclado não abre receita, ordem
   nem insumo. B-01 — e trocar sem B-02 troca a falha por linhas sem nome.
2. **Nenhum salvamento é anunciado.** "Receita salva", "Produção finalizada", "Configurações
   atualizadas" aparecem e desaparecem em 2.600 ms sem `aria-live` em lugar algum do sistema. C-02.
3. **As abas não andam com seta.** C-01.
4. **A ordenação não é anunciada.** Os cabeçalhos são `<button>` (page L102, `-producao.jsx` L55) —
   clicáveis e focáveis, mas sem `aria-sort`, e em Insumos (L43-45) e Relatório (L299-300) são
   `<span>`: ali não há ordenação nenhuma. B-01.
5. **O seletor de insumo não é um combobox.** C-03.
6. **A busca não tem nome acessível além do placeholder** (page L230, `-insumos.jsx` L34,
   `-recipe.jsx` L94): nem `<label>`, nem `aria-label`. B-03.
7. **Os grupos de chips não têm nome de grupo.** Os itens têm `aria-pressed` (page L233,
   `-producao.jsx` L364), o contêiner não tem `role="group"`/`aria-label` (page L232,
   `-producao.jsx` L362) — e a categoria, que é escolha única, está marcada como alternância.
   A-01/A-02.
8. **Alvos abaixo de 44 px em ponteiro fino:** `.mfg-inp.num` 26 px (css L92 · usos `-recipe.jsx`
   L170, `-producao.jsx` L152), `.mfg-inp.sel` 26 px (css L91 · L175), `.mfg-mini` 22×22 (css L122 ·
   L157, L183), caixa de seleção nativa (page L241, L252), `.mfg-pick-x` sem caixa (css L129). A
   camada transversal força 44 px só em `pointer: coarse` — no desktop fica como está, o que o §11.4
   do handoff já declara aceito. **Continua declarado, não corrigido.**
9. **A impressão toma o foco e não devolve.** `-print.jsx` L118-124: `window.print()` 120 ms após
   montar; `afterprint` chama `onDone`, e o foco anterior não é restaurado. *(Os overlays do DS
   fazem isso certo — `Drawer` L3799, `Modal` L5085 — a folha de prova não passa por eles.)*
10. **Entrar no editor não move o foco.** page L157-176 troca o corpo do módulo por uma tela cheia;
    o foco fica no `<body>` e o leitor não sabe que a tela mudou.
11. **Sem rede de proteção se o bundle falhar.** page L48 desestrutura 12 componentes do DS na
    primeira linha do render. `ds()` devolve `{}` se o bundle não carregar (page L15) e o módulo
    inteiro cai em *"Element type is invalid"* — o mesmo sintoma que a fila lazy já obrigou a
    contornar para os irmãos (page L21-31). **Achado novo.**

**O que a onda A de fato pagou, para não perder o crédito:** os 5 overlays prendem o foco, fecham
no Esc por dentro e devolvem o foco ao sair (`Drawer` L3764-3800, `Modal` L5050-5086); todos têm
`aria-modal="true"` (L3821, L5110); o Esc do drawer de insumo passou a funcionar; a paginação virou
`<nav>` com `aria-current` (L5325); os KPI-filtros ganharam `aria-pressed` (L4876); a dica do
sufixo `fix` abre em foco (`-producao.jsx` L96-98 + Tooltip L7107).

---

## §9 · Para decidir antes de rodar qualquer onda

1. **P-1/P-2 antes de tudo.** Enquanto o pacote de handoff carrega o código velho e não carrega o
   bundle, qualquer item aprovado aqui vai ser implementado a partir da versão errada.
2. **B-01 é o gargalo, e agora tem dois caminhos com preço conhecido:** `DataTable` + `stickyHeader`
   + `minWidth` + `rowLabel` (3 props novas, a tela mantém o estado que já tem), **ou**
   `DataTablePro` + ordenação/seleção controladas + `rowLabel` (a P1 da pauta já pede a primeira
   parte). Sem escolher, quatro telas ficam onde estão e a falha de teclado continua.
3. **C-01, C-02 e B-02 não são da Fabricação** — são do DS inteiro, e valem igual para Clientes,
   Financeiro e Oficina. Vale abrir como proposta de sistema, não como item de tela.
4. **A-01/A-02 custam aparência.** Trocar chip por rádio/caixa conserta semântica e teclado e muda
   o visual de duas telas. É decisão sua, não minha.
5. **O que vai para a pauta se você aprovar:** `Input` sem `ref`/`onKeyDown`/`min`/`max`/`step`
   (B-03, B-05) e sem `aria-invalid`/`aria-describedby` (B-04) · `DataTable` sem `stickyHeader`,
   `minWidth` e `rowLabel` (B-01, B-02) · `Select` sem variante compacta (B-06) · `Breadcrumb` sem
   `onSelect` (B-07) · `Button` sem `dashed` e sem `link` (B-08, B-09) · `EmptyState` sem variante
   compacta (B-10) · `TabBar` sem teclado de seta (C-01) · ausência de live region e de host de
   toast (C-02) · ausência de `Slider`, `Card`, `Separator`, `KeyValue`, rodapé de tabela e combobox
   inline (C-03…C-09) · e como **D**, o `cli` cravado no `DataTable` L2989 e o comentário errado de
   `oimpresso.com.html` L119.

Nada foi alterado. Aguardo sua aprovação para escolher as ondas.
