# Auditoria de aderência ao DS — módulo Fabricação (Manufacturing)

> **Estado em 08/09/2026 — a lista A foi executada.** Os 23 itens do §2 estão no código; o
> registro completo (o que virou o quê, com arquivo e linha, teste de aceite e os contornos que
> ficaram) é o **§19 do `handoff_fabricacao/README.md`**. As listas **B** e **C** continuam
> abertas — o Wagner resolve. Um defeito novo apareceu na execução (fuso do `DatePicker`) e cinco
> itens foram para `pauta-design-system.md`.

Levantamento original, antes da execução.

---

## §0 · Escopo medido

| Árvore | Arquivos | Situação |
|---|---|---|
| Raiz (é a que roda) | `manufacturing-page.jsx` (369L) · `-recipe.jsx` (268L) · `-producao.jsx` (344L) · `-insumos.jsx` (98L) · `-print.jsx` (123L) · `-data.jsx` (191L) · `manufacturing-page.css` (264L) | montada por `oimpresso.com.html` L190-195 (lazy) + CSS L68 |
| Pacote de handoff | `handoff_fabricacao/design/manufacturing-*.jsx` | **byte a byte idêntica** à raiz nos 5 arquivos de tela (comparado nesta auditoria) — uma auditoria cobre as duas |
| Componentes do DS | `_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js` (9.204L) | toda citação `[DS]` abaixo |

**Terceira superfície, fora das duas árvores — existe e você pediu para dizer qual é:**

- **Mockup "Manufacturing · BOM"** — `mockup-bodies.js` (bloco `.mfg-grid` / `.bom`, ~offset 35.700) + `mockup-pages.css` L293-295. É uma maquete estática de baixa fidelidade (4 `stat` + árvore de BOM em `div`), com **classes homônimas mas independentes** (`.mfg-grid`, `.bom-row` — não são as `.mfg-*` do módulo). Não consome DS, não compartilha CSS com a família. **Fora do escopo desta auditoria** — se entrar, é substituição inteira, não troca de peça.

Não são tela e não entram: `funcoes-perms.jsx` L105-107 (catálogo de permissões `manufatura`), `icons.jsx` (primitivo de ícone), `handoff_fabricacao/design/adr/*` e os `.md` de conferência.

**Ponto de partida:** zero componente do DS consumido — `grep "_ds_bundle|OfficeImpressoDesignSystem_49a36f"` nas duas árvores volta 0 import.

---

## §1 · Camada visual — varredura de valores crus

Medido no `manufacturing-page.css` (264L) e nos JSX.

| Achado | Onde | Resolve em |
|---|---|---|
| **6 tamanhos de fonte fora da rampa `--fs-*`** (10,5/11,5/12,5/13,5/15/18/22/28/38): **10px** em 9 seletores (L15 `.mfg-kpi-l`, L34 `.mfg-th`, L67 `.mfg-sec span`, L76, L103 `.mfg-fld>span`, L148 `.mfg-ing-h`…), **11px** ×5 (L17, L109, L116, L135, L154), **12px** ×9 (L44 `.mfg-num`, L71, L72, L73, L80, L114…), **14px** ×2 (L124, L198), **16px** (L62 `.mfg-drw-h h2`), **21px** (L16 `.mfg-kpi-v`) | css | A-01…A-07 (o valor passa a vir do componente) |
| **3 raios fora do canon** (6/8/12/16/24/full): `3px` L8, L87, L96 · `5px` L154 · `10px` L139, L173 | css | A-01, A-12, C-07 |
| **4 sombras/scrim em `rgba(0,0,0,…)`** — L54 bulk `0 6px 24px .28` · L59 scrim `.42` · L122 modal `0 24px 64px .4` · L177 toast `0 10px 30px .32` | css | A-01…A-05, A-09, A-25 (o DS traz scrim e sombra próprios); o que sobrar vira pauta — **o DS não publica token de scrim nem de sombra elevada** |
| **4 tintas de processo em hex inline** `#00AEEF #EC008C #FFF200 #231F20` | `manufacturing-print.jsx` L99 | A-23 (`ProofStrip kind="cmyk"`) |
| **Escada de cinza calculada em runtime** — `"rgb(" + (255 − k×2,55) + …)` | `manufacturing-print.jsx` L100 | A-23 (`ProofStrip kind="density"`) |
| **11 cinzas literais** no bloco `@media print` | css L203-264 | **não resolve** — C-08 (ADR 0413) |
| **`--accent` como cor de texto, inline, em 4 lugares** — `style={{fontSize:15,fontWeight:600,color:"var(--accent)"}}` | page L349 · recipe L247 · producao L167, L223 | A-01/A-02; a reprovação de contraste no escuro (2,64:1) é ADR 0411, **não** se corrige aqui |
| **`--text-mute` em texto de 10-11px** (rótulo de coluna, SKU, unidade, meta) | css L15, L34, L67, L76, L103, L135 | ADR 0410 — o token é do DS; trocar por `--text-dim` é decisão sua, não da troca de componente |

---

## §2 · Lista A — troca direta ✅ EXECUTADA (2026-09-08)

Ordenada por risco: primeiro o que hoje **perde comportamento**.

| # | Risco | Elemento | Onde | Componente do DS | O que a troca ganha, medido |
|---|---|---|---|---|---|
| A-01 | 🔴 acessibilidade | Drawer da receita | `manufacturing-page.jsx` L310-346 | `Drawer` + `DrawerSection` `[DS]` L3753 | focus trap real (Tab circula, L3777-3792), `Escape` no próprio painel, **devolve o foco ao elemento anterior** ao fechar (L3798) — hoje nada disso existe |
| A-02 | 🔴 acessibilidade | Drawer da ordem de produção | `manufacturing-producao.jsx` L196-236 | idem | idem |
| A-03 | 🔴 acessibilidade | Drawer do insumo (simulador) | `manufacturing-insumos.jsx` L54-88 | idem | **o pior caso da família**: o `Escape` da tela é um listener global em `page.jsx` L45-50 que fecha `openId/opAberta/novaOpen/confirma` — o estado deste drawer é `sku`, local (`insumos.jsx` L11). **Esc não fecha este overlay hoje.** O `Drawer` do DS resolve por dentro |
| A-04 | 🔴 acessibilidade | Modal "Nova receita" | `manufacturing-recipe.jsx` L36-80 | `Modal` `[DS]` L5041, `width={520}` | focus trap + Esc + devolução de foco (L5049-5086); scrim marcado `aria-hidden` (L5099) |
| A-05 | 🔴 acessibilidade | Modal de confirmação de exclusão | `manufacturing-page.jsx` L279-290 | `Modal`, `width={400}` | idem |
| A-06 | 🟠 acessibilidade | KPI-filtro ×2 (margem, desperdício) | `manufacturing-page.jsx` L177, L182 | `KpiFilterCard` `[DS]` L4856 | `aria-pressed={selected}` (L4876) — hoje o estado ligado é só a classe `.act` |
| A-07 | 🟠 acessibilidade | Paginação ×2 | page L236-247 · producao L79-89 | `Pagination` `[DS]` L5263 | `<nav aria-label="Paginação">` (L5357) + `aria-current="page"` na página atual (L5325) — hoje é `div` + `button` sem semântica |
| A-08 | 🟠 acessibilidade | Abas do módulo | `manufacturing-page.jsx` L159-166 | `TabBar` `[DS]` L6605 | `aria-current="page"` na ativa (L6625). **Não resolve teclado** — ver C-01 |
| A-09 | 🟡 comportamento | Barra de seleção | `manufacturing-page.jsx` L249-256 | `BulkBar` `[DS]` L2137 | some 1 das 4 sombras cruas; ação `tone:'danger'` padronizada |
| A-10 | 🟡 comportamento | Dica do sufixo `fix` | `manufacturing-producao.jsx` L69 (`title=`) | `Tooltip` `[DS]` L6948 | hoje é `title` nativo: só mouse, ~1 s de atraso do SO, invisível por teclado. O `Tooltip` abre em **foco** também |
| A-11 | 🟡 comportamento | 4 estados vazios | page L229 · producao L75, L283 | `EmptyState` `[DS]` L4126 | `variant='no-results'` + ícone + ação — hoje é `b` + `span`, sem saída |
| A-12 | 🟡 comportamento | Switches de Configurações ×2 | `manufacturing-producao.jsx` L303-312 | `Switch` `[DS]` L6526 | o DS tem label + sublabel; hoje é `checkbox` dentro de `label.mfg-check.big` com `small` |
| A-13 | 🟡 comportamento | Checkbox "Só finalizadas" ×2 | producao L51, L265 | `Checkbox` `[DS]` L2511 | — |
| A-14 | 🟡 comportamento | 5 campos de data | producao L49, L50, L147, L263, L264 | `DatePicker` `[DS]` L3360 | calendário PT-BR dd/mm/aaaa; hoje `input type="date"`, formato do SO |
| A-15 | 🟡 visual | Cabeçalho da tela | `manufacturing-page.jsx` L148-157 | `PageHeader` `[DS]` L5158 | usa hoje `.os-page-h`, classe de **outro** módulo |
| A-16 | 🟡 visual | 2 KPIs de leitura | page L172, L187 | `KpiCard` `[DS]` L4650 | o sub-rótulo é `description` (não `sub`) |
| A-17 | 🟡 visual | ~23 botões `os-btn` | page (12: L154,155,252-254,287,288,356…) · recipe (5: L73,74,255,257,258) · producao (5: L181,182,232,233,316) · insumos (1: L87) | `Button` `[DS]` L2246 | variantes primary/ghost/danger + tamanhos 26/30/36 |
| A-18 | 🟡 visual | Pílula de faixa de margem ×3 | page L225 · insumos L46, L80 | `StatusBadge` com **`tone`** (a prop existe, L6282) | success/warning/danger com dot; hoje `.mfg-pill.ok/.warn/.bad` |
| A-19 | 🟡 visual | Barra de % do período | `manufacturing-producao.jsx` L280 | `Progress variant="bar"` `[DS]` L5796 | — |
| A-20 | 🟡 visual | Avisos de bloco (estoque insuficiente, permissão de leitura, qtd bloqueada) | producao L137 · recipe L249, L250 | `Alert` `[DS]` L738 | fundo 6% + borda 22% no tom, com `action` — hoje `<p class="mfg-err">` / `.mfg-note` |
| A-21 | 🟡 visual | Toast | `manufacturing-page.jsx` L301 | `Toast` `[DS]` L6900 | troca visual **apenas** — o anúncio continua faltando, ver B-06 |
| A-22 | 🟡 visual | Campos de texto/select/textarea | recipe (17 usos de `.mfg-inp`) · producao (13) | `Input` / `Select` / `Textarea` `[DS]` L4481/4546/4511 | exceto os numéricos com passo — ver B-04 |
| A-23 | 🟡 visual | Folha PT-07: mira, marcas de corte, cotas, tira CMYK | print L8 (`REG`), L23 (`.cm`×4), L39-45 (cotas), L99-100 (tiras) | `RegistrationMark` L6147 · `ProofFrame` L5931 · `Dimension` L3642 · `ProofStrip` L6023 | **direta em código, mas exige imprimir uma folha e medir**: os quatro pintam por token de tela e o cockpit não publica paleta de impressão (C-08) |

---

## §3 · Lista B — bloqueado pelo DS (a peça existe, falta prop ou variante)

| # | Risco | Elemento | Onde | Peça do DS | Falta exatamente |
|---|---|---|---|---|---|
| B-01 | 🔴 acessibilidade + estrutura | **4 tabelas** (receitas, ordens, insumos, relatório) — linha clicável é `<div onClick>` | page L204-233 · producao L55-77, L267-282 · insumos L32-51 | `DataTable` L2894 / `DataTablePro` L3099 | **A linha não é focável nem acionável por teclado hoje** (sem `tabIndex`, sem `role`, sem Enter/Espaço) — o `DataTable` entrega os quatro (L2996-3006) e `aria-sort` no cabeçalho (L2957). O bloqueio: o `DataTable` **não tem cabeçalho fixo nem largura mínima**, e a tela depende dos dois (`.mfg-thead{position:sticky}` css L33; `min-width` 960/1100/900/940 css L30, L90, L92, L94). O `DataTablePro` tem header fixo mas **não aceita ordenação/seleção controladas** (P1 já aberta na pauta) e pede `height`. Falta: `stickyHeader` + `minWidth` no `DataTable`, **ou** ordenação/seleção controladas no `Pro` |
| B-02 | 🔴 comportamento | **Busca com atalho `/`** ×3 | page L195-198 (ref L38, listener L45-50) · insumos L25-27 · recipe L94-99 | `Input` L4481 | a assinatura não tem `ref` (o `/` foca por `buscaRef.current.focus()`), não tem slot de ícone (**já é P1 na pauta**, item *"`Input` sem slot de ícone ou prefixo"*) e **não tem `onKeyDown`** — a busca de insumo depende de `Enter` (escolhe o primeiro) e `Esc` (fecha), recipe L96. Sem os três, trocar a busca **apaga R-04 e o Enter da busca de insumo** |
| B-03 | 🟠 estrutura | Situação da ordem (Finalizada / Rascunho) | `manufacturing-producao.jsx` L71 | `StatusBadge kind="documento"` (o docblock destina `documento` à OP, L6388) | a chave **`finalizada`** — o mapa tem `rascunho · pendente · aprovado · rejeitado · aplicado · cancelado` (L6389-6395). Pauta aberta hoje (P2). Contorno: `label` livre sobre valor mapeado |
| B-04 | 🟠 comportamento | Quantidade de ingrediente, passo de milésimo | recipe L168 (`step="0.001"`) · producao L126 (idem) · + 7 campos `type="number"` com `min`/`step` (recipe L56, L209, L221, L225, L234, L238 · producao L158) | `Input type="number"` L4481 | **`min` / `max` / `step` não existem na assinatura** (L4482-4493). Trocar hoje derruba o passo de 0,001 e o teto de 100% do desperdício (recipe L234) |
| B-05 | 🟠 comportamento | Campo com erro inline ("Já existe receita para essa variação") | `manufacturing-recipe.jsx` L43 | `Input error` L4481 | o `Input` pinta a borda e grava `data-invalid` (L4504), mas **não emite `aria-invalid` nem `aria-describedby`** — o leitor de tela não liga o erro ao campo. Hoje é pior (o `<p class="mfg-err">` nem é vizinho do input), mas a troca não fecha o buraco |
| B-06 | 🟠 acessibilidade | Retorno de ação ("Receita salva", "Configurações atualizadas") | page L301 (`aviso()` L41) | `Toast` L6900 | **nenhum componente do bundle declara `aria-live`** — grep por `aria-live` nos 9.204L volta 0. Falta `role="status"` no `Toast`. Sem isso, salvar não é anunciado nem antes nem depois da troca |
| B-07 | 🟡 estrutura | Trilha de volta do editor ×2 | recipe L140-145 · producao L110-115 | `Breadcrumb` L2077 | só aceita `items [{label, href}]`; a trilha volta por **estado** (`onCancel`), não por rota. Falta `onSelect` no item |
| B-08 | 🟡 acessibilidade | Chips de escolha ×3 usos (categoria, permissões simuladas, novo grupo) | page L199-201 · producao L323-326 · recipe L194-199 | `FilterChip` L4236 | o `FilterChip` é pílula de filtro **ativo com ✕** (`label`,`value`,`onRemove`) — não é seletor. Falta variante de escolha com `aria-pressed`. Hoje os três também não têm `aria-pressed` |
| B-09 | 🟡 visual | Contador da aba com duas informações (`6 · 1 rasc.`) | `manufacturing-page.jsx` L164 | `TabBar count` L6661-6669 | a pílula é `padding: 0 6px; minWidth: 18` — o LAUDO já mediu o rótulo vazando a 914px. Falta acomodar contador composto (ou encurtar o rótulo, decisão da tela) |

---

## §4 · Lista C — não existe no DS (precisa nascer)

| # | Risco | O que é | Onde | Por que não dá para compor |
|---|---|---|---|---|
| C-01 | 🔴 acessibilidade | **Navegação por seta ←/→ entre abas** | page L159-166 | o `TabBar` (L6605-6672) trata clique e marca `aria-current`; não é `role="tablist"`, não escuta seta nem Home/End. Teclado é interno ao componente — não há como acrescentar por fora sem reimplementar o `TabBar` |
| C-02 | 🔴 acessibilidade | **Região de anúncio (live region)** | todo retorno de ação: page L41/L301, producao L~ (config) | zero `aria-live` no bundle inteiro. `Toast`, `Alert` e `EmptyState` são visuais. Compor exigiria um nó fora do DS — que é exatamente o que a regra proíbe fazer em silêncio |
| C-03 | 🟠 estrutura | **Grupo de ingredientes editável** (cabeçalho com nome + contagem + subtotal derivado; linhas de 4/5/6 colunas com campo numérico, select de sub-unidade e remoção) | recipe L151-190 (6 col) · producao L119-133 (5 col) · page L323-333 (4 col, leitura) · insumos L72-82 (5 col) — **4 variantes** | `DataTable` não aceita célula editável, cabeçalho de grupo com subtotal, nem linha tingida por regra (`.mfg-ing.falta`, css L149). `DrawerSection` não tem grade. É a peça central do módulo e não tem equivalente |
| C-04 | 🟠 estrutura | **Slider** (simulador de variação de preço, −30…+60, passo 5) | `manufacturing-insumos.jsx` L66 | não há `Slider` no bundle. `Progress` (L5796) é leitura, não entrada. Hoje o `input[type=range]` **também não tem rótulo acessível** (sem `aria-label`, sem `aria-valuetext`) |
| C-05 | 🟠 estrutura | **Campo de dinheiro pt-BR** | recipe L221, L225, L234, L238 · producao L158 | o alvo tem `ui/numeric-input-ptbr.tsx`; o espelho do DS não tem. `type="number"` aceita separador conforme o locale do navegador — ambiguidade em `1.234,56` |
| C-06 | 🟡 estrutura | **Rodapé de total de tabela** | producao L87, L285 (`.mfg-foot`) | `DataTable` não tem slot de rodapé. Hoje é um `<p>` **fora** da tabela — não é `<tfoot>`, então nem a semântica existe |
| C-07 | 🟡 estrutura | **Quadro de custo** (`<dl class="mfg-tot">`: pares, régua, linha de destaque) — 4 usos | page L342-352 · recipe L242-248 · producao L162-170, L218-226 | não há `KeyValue`/`SummaryList` no DS; `KpiCard` é tile, `DrawerSection` é só título+conteúdo. É onde moram os 4 usos inline de `--accent` como texto |
| C-08 | 🟡 visual | **Paleta de impressão** | css L203-264 (11 cinzas) | o cockpit não publica token de papel/tinta (ADR 0413). Mesmo depois de A-23, a folha continua com cinza literal |
| C-09 | 🟡 visual | **Inventário de ícones** | `icons.jsx` (22 glyphs) usados em page L154/155/253, recipe L188, producao L182/233, insumos L26 | o espelho do DS tem o mesmo limite — já registrado na pauta como **D** (*"Icon — inventário do espelho vs. Lucide do produto"*). Contorno declarado, não peça nova |

---

## §5 · Os cinco defeitos de comportamento que nenhuma lista resolve sozinha

Junto para você ver o tamanho do buraco antes de escolher a onda:

1. **Nenhum dos 5 overlays prende o foco.** Tab a partir do drawer aberto cai no conteúdo atrás do scrim. A-01…A-05 resolvem os cinco de uma vez.
2. **Esc não fecha o drawer de insumo** (insumos L54-88): o listener é global e mora em outro componente (page L45-50).
3. **Nenhum overlay tem `aria-modal="true"`** — só `role="dialog"` + `aria-label` (page L280, L311 · recipe L37 · producao L197 · insumos L55). O leitor continua lendo a página atrás.
4. **As 4 tabelas não são operáveis por teclado** (B-01): a linha é `div onClick`. Quem só tem teclado não abre receita, ordem nem insumo — **é a falha mais grave da auditoria**.
5. **Alvos abaixo de 44px em ponteiro fino:** `.mfg-x` 28×28 (css L63), `.mfg-mini` 22×22 (L153), `.mfg-inp.num` 26px (L110), checkbox 14px (page L207, L218). A camada transversal (`otimiza-ondas.css`) força 44px só em `pointer: coarse` — no desktop fica como está, o que o handoff §11.4 já declara como aceito.

---

## §6 · Para decidir antes de rodar qualquer onda

1. **B-01 é o gargalo de tudo.** Sem escolher `DataTable` + duas props novas **ou** `DataTablePro` + ordenação controlada, quatro telas ficam onde estão — e a falha de teclado continua.
2. **B-02 tem custo de comportamento visível:** trocar a busca hoje apaga o atalho `/` e o Enter da busca de insumo. Ou o DS ganha `ref`/`onKeyDown`, ou a busca fica local como contorno declarado.
3. **C-01 e C-02 não são da Fabricação** — são do DS inteiro. Aparecem aqui, mas valem para Clientes, Financeiro e Oficina do mesmo jeito. Vale abrir como proposta de sistema, não como item de tela.

Aguardo sua aprovação. Nada foi alterado.
