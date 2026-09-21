# Manual de Blocos — Office Impresso · produto unificado

> **O que este documento é:** o contrato de **cada bloco de tela** do produto. Um bloco é uma
> região com fronteira própria (cabeçalho, faixa de KPI, região de dados, rodapé, painel…). Cada
> bloco tem ficha própria: quem o renderiza no design system, os itens que ele **obrigatoriamente**
> cobra, as dependências, as proibições e o teste de aceite.
>
> **Para que serve:** aplicar o mesmo contrato em qualquer tela sem reescrever o contrato, e sem
> carregar no contexto o que a tela não usa. Quem implementa lê a **ficha da tela** (§2), vê a lista
> de blocos dela, e lê **só esses blocos**.
>
> **Regra de manutenção:** apareceu item novo no cabeçalho de alguma tela? Atualiza-se **o bloco
> B-04**, sobe a versão dele, e todas as telas herdam. Nenhuma ficha de tela redescreve bloco.
>
> Versão do manual: **v1 · 27/08/2026** · Base medida: DS `OfficeImpressoDesignSystem_49a36f`
> (`_ds_bundle.js`), template canônico `templates/pt-01-lista/Pt01Lista.dc.html`, e a tela
> `Consulta de Produtos.dc.html` com seu handoff.

---

## 0 · Contrato de leitura — obrigatório antes de qualquer bloco

### 0.1 Precedência, sem grau

**design system > template canônico do tipo de tela > este manual > ficha da tela > protótipo >
auditorias.**

Valor de cor, tamanho, raio, espaçamento ou tipografia de elemento que um **componente do DS
renderiza** não se escreve: passa-se a prop e o componente decide. Se este manual e o DS
divergirem, vale o DS — e a divergência **autoriza pergunta, nunca ação**: aplica-se o valor do DS
como está, relata-se com o número medido, e registra-se em `pauta-design-system.md`. Exceção só
assinada e registrada na pauta.

**Esta proibição NÃO proíbe:** transcrever literalmente um valor do DS como **citação** (com
arquivo e linha) para documentar o que o componente faz; nem estilizar por fora aquilo que o
componente comprovadamente não expõe — desde que declarado como contorno (§0.5).

### 0.2 Marcas de procedência — todo valor citado leva uma

| Marca | Significa | Quem pode mudar |
|---|---|---|
| `[DS]` | valor do componente do design system, **citado** com arquivo e linha | só o DS |
| `[TPL]` | valor do template canônico do tipo de tela (`pt-01-lista`, `pt-05-dashboard`, `pt-07-os-detail`) | só o template |
| `[TELA]` | decisão desta tela — o DS e o template não cobrem | a tela, com registro |
| `[RUNTIME]` | observado no navegador; **não é regra** e não vira contrato | ninguém — é medição |

Antes de marcar algo `[TELA]`, três leituras obrigatórias: (1) o componente que renderiza o
elemento; (2) o **template canônico** do mesmo tipo de tela — markup, `<style>` do `<helmet>` e a
lógica que escreve tokens; (3) o guia do DS. **"O componente não tem" nunca é conclusão** — é
resultado de leitura do docblock **e** do código.

### 0.3 Anatomia da ficha de bloco — o que cada §3+ entrega

1. **Código, versão e data** — `B-07 v1 · 27/08`. Item novo no bloco sobe a versão **do bloco**.
2. **Vale para** — quais tipos de tela obrigam o bloco, quais o dispensam.
3. **Quem renderiza** — componente do DS ou slot do template, com arquivo e linha.
4. **Itens que o bloco cobra** — a lista, marcada **FECHADA** (nada se acrescenta sem atualizar
   este manual) ou **ILUSTRATIVA** (a tela completa com o que existir no seu domínio).
5. **Dependências** — o que precisa existir antes, e o que quebra em silêncio se faltar.
6. **Proibido** — com o que a proibição **não** alcança.
7. **Teste de aceite** — uma linha por item, verificável na tela pronta. Reprovado = não entregue.

### 0.4 Regras que valem em todo bloco (não se repetem nas fichas)

1. **Copy visível em PT-BR**, sentence case; chaves de dado e `data-*` em inglês.
2. **Cor sempre por token** `var(--*)` ou `color-mix` sobre token. Zero hex, zero OKLCH inventado.
   `--color-primary` é o contrato do acento; **`--accent` é reescrito em runtime** pelo seletor de
   matiz do shell (`Pt01Lista.dc.html` L136-139) e não serve como contrato.
3. **Espaçamento na grade 4/8**; padding de slot sempre `var(--d-cpad-x)` / `var(--d-cpad-y)`.
4. **Piso tipográfico 10,5px**; números com `font-variant-numeric: tabular-nums`.
5. **Ícones só do pacote do DS** (`window.Icon` no protótipo, Lucide no produto). Sem emoji na UI.
6. **Todo número exibido é derivado (com fórmula) ou digitado (com campo de origem)** — nunca as
   duas coisas. Dois números do mesmo fato têm de fechar por cálculo, não por digitação.
7. **Derivados nunca em estado.** Total, saldo, margem, montáveis, contadores: calculados a cada
   render.
8. **`localStorage` com prefixo do produto** e chave por tenant+usuário quando guardar rascunho.
9. **Regra de negócio não vive em texto digitado** — percentual, prazo e pedido mínimo são campos
   calculados; a observação descreve a condição, não o número.
10. **Quando o número exibido é um subconjunto, a linha declara isso** (saldo vendável vs. físico,
    soma da grade, montáveis de kit). Nunca omitir em silêncio.

### 0.5 Contorno — como se declara

Contorno é a tela fazendo por fora o que o componente não expõe. Só existe declarado, com quatro
campos: **onde · o que o componente não dá · o que a tela faz · registro na pauta**. Contorno não
declarado é divergência. **Recriar componente do DS localmente está descartado** em qualquer caso.

### 0.6 Escrever para quem implementa é um agente

Agente em dúvida **preenche, não pergunta**: lacuna vira código plausível e errado. Por isso, em
todo bloco: valor em número (nunca nome de prop de outro framework), lista declarada fechada ou
ilustrativa, proibição explícita, e um teste de aceite por requisito. As leis completas estão em
`manual-escrita-para-agente.md` — este manual é a aplicação delas por bloco.

### 0.7 Qual template é canônico — e o que fazer quando dois discordam

Os valores marcados `[TPL]` neste manual vêm de **`templates/pt-01-lista`**, que é o canônico do
**índice de balcão**. Não são universais. O DS tem mais de um template, e eles **divergem entre si
por decisão**:

| Tipo de tela | Template canônico | Divergências conhecidas contra o PT-01 |
|---|---|---|
| Índice de balcão | `templates/pt-01-lista` | — (é a referência) |
| Índice financeiro | `templates/financeiro` | padding `0 26px` cravado (não `--d-cpad-x`); moldura raio `12px` e sombra `.18`; KPI **dentro** da área que rola; busca inline, sem faixa de toolbar com borda; `DataTablePro` **sem** `selectable`; **sem** rodapé e sem `Pagination` |
| Índice de CRM | `templates/clientes-crm` | faixa de KPI-filtros com frescor/tags |
| Board | `templates/oficina-auto` | não tem região tabular |
| Dashboard | `templates/pt-05-dashboard` | não tem toolbar nem lista |
| Detalhe / prova | `templates/pt-07-os-detail` | não tem índice |

**Regra de auditoria:** compara-se a tela com o template **do seu tipo**. Uma tela financeira que
não tem `--d-cpad-x`, nem rodapé, nem seleção **não está divergindo** — está seguindo o template
dela. Quando os dois templates discordam sobre a mesma coisa, o do módulo ganha **para aquele
módulo**, e a discordância vai para `pauta-design-system.md` como pergunta ao DS. **Nunca**
"corrigir" um template pelo outro dentro da tela.

**Esta regra NÃO dispensa:** B-01 itens 1-4 (raiz `.cockpit`, `<main>` único, tema, acento), que
valem em todo template.

---

## 1 · Blocos existentes — índice

| Código | Bloco | Versão | Obrigatório em |
|---|---|---|---|
| **B-01** | Shell, tema e densidade | v1 · 27/08 | toda tela |
| **B-02** | Sidebar de navegação | v1 · 27/08 | toda tela do cockpit |
| ~~**B-03**~~ | ~~Topbar (trilha, busca global, ⌘K, ajuda)~~ | **aposentado** · 27/08 | — (retirado do produto) |
| **B-04** | Cabeçalho da página | **v2** · 27/08 | toda tela |
| **B-05** | Abas de módulo | v1 · 27/08 | índice com recortes |
| **B-06** | Faixa de KPI-filtros | **v2** · 27/08 | índice, dashboard |
| **B-07** | Toolbar de recorte | v1 · 27/08 | índice |
| **B-08** | Região de dados | **v2** · 27/08 | índice |
| **B-09** | Anatomia da linha | **v2** · 27/08 | índice |
| **B-10** | Seleção e ações em lote | v1 · 27/08 | índice com ação de lote |
| **B-11** | Rodapé de lista | **v2** · 27/08 | índice paginado (opcional — ver ficha) |
| **B-12** | Painel de detalhe (Drawer) | v1 · 27/08 | índice com detalhe |
| **B-13** | Modal de confirmação | **v2** · 27/08 | tela com ação destrutiva ou irreversível |
| **B-14** | Feedback efêmero (Toast) | v1 · 27/08 | tela com ação que muda dado |
| **B-15** | Estados de exceção | v1 · 27/08 | toda tela que carrega dado |
| **B-16** | Pilha de overlays | v1 · 27/08 | tela com 2+ overlays |
| **B-17** | Formulário de cadastro | v1 · 27/08 | tela de cadastro/edição |
| **B-18** | Board de produção | v1 · 27/08 | Produção, Oficina |
| **B-19** | Teclado, foco e acessibilidade | v1 · 27/08 | toda tela |
| **B-20** | Persistência de preferência | v1 · 27/08 | tela com recorte |
| **B-21** | Permissões e visibilidade | v1 · 27/08 | toda tela com preço/custo |
| **B-22** | Números exibidos e derivação | v1 · 27/08 | toda tela com número |
| **B-23** | Recorte de período | v1 · 27/08 | Financeiro, BI, relatório, ponto |

**Blocos ainda NÃO cobertos** — declarados, para não virarem invenção do implementador:
Dashboard/painéis de gráfico (usar `templates/pt-05-dashboard` até existir ficha), Impressão/PDF
(usar `templates/pt-07-os-detail`), PDV/caixa, conciliação bancária e importação de arquivo
(OFX/retorno), mobile (projeto separado, decisão do cliente). Tela desses tipos **não** está coberta
por este manual além dos blocos B-01 a B-04, B-19 e B-21.

**Domínio que os blocos NÃO decidem** (é da ficha da tela, e silêncio ali é pendência): plano de
contas, centro de custo, regime de caixa vs. competência, juros/multa/desconto por atraso, rateio de
parcela, e o que é "saldo previsto". O bloco cobra que o número esteja marcado derivado ou digitado
(B-22); **de onde vem a fórmula é da tela**.

---

## 2 · Ficha de tela — o formulário que cada tela preenche

Uma página por tela, no topo do handoff da tela. **Não descreve bloco** — declara quais usa, com o
que é próprio dela.

```markdown
# Ficha: <Tela> (<rota>) · <módulo>
Tipo de tela: índice | cadastro | board | detalhe | dashboard
Template canônico: templates/pt-01-lista
Persona e peso: <quem usa, quantas vezes por dia>
Perfis que abrem a tela: <vendedor, gerente, admin…>

| Bloco | Usa? | Particular desta tela |
|---|---|---|
| B-01 Shell | sim | — |
| B-04 Cabeçalho | sim | stats: 14 itens · 3 abaixo do mínimo |
| B-06 KPI-filtros | sim | 4 cartões; "Abaixo do mínimo" só com permissão `custo` |
| B-07 Toolbar | sim | facetas: Categoria, Tipo, Marca, Disponível |
| B-18 Board | não | — |
| … | | |

Blocos NÃO usados e por quê: <um por linha — silêncio aqui é pendência>
Regra de negócio própria: <seções do handoff da tela>
Diff aberto: <se a tela já está implementada>
```

**Matriz tela × bloco** (viva; uma linha por tela conforme forem entrando):

| Tela | Template | B-01 | B-02 | B-03 | B-04 | B-05 | B-06 | B-07 | B-08 | B-09 | B-10 | B-11 | B-12 | B-13 | B-14 | B-15 | B-16 | B-17 | B-18 | B-19 | B-20 | B-21 | B-22 | B-23 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Consulta de Produtos | pt-01 | ✔ | ✔ | — | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | ✔ | ✔ | ✔ | ✔ | — |
| Consulta de Pessoas | pt-01 | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | ✔ | ✔ | ✔ | ✔ | — |
| Cadastro de Produto | pt-01 | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | — | — | — | — | — | ✔ | ✔ | ✔ | ✔ | ✔ | — | ✔ | — | ✔ | ✔ | — |
| Cadastro de Pessoa | pt-01 | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | — | — | — | — | — | ✔ | ✔ | ✔ | ✔ | ✔ | — | ✔ | — | ✔ | ✔ | — |
| Venda / Orçamento | pt-01 | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | ✔ | ✔ | — | — | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | ✔ | ✔ | ✔ | ✔ | — |
| Compra | pt-01 | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | ✔ | ✔ | ✔ | ✔ | ✔ |
| Produção / Oficina | oficina-auto | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | — | — | ✔ | ✔ | ✔ | ✔ | ✔ | — | ✔ | ✔ | ✔ | ✔ | ✔ | — |
| Financeiro — Títulos | financeiro | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | — | ✔ | ✔ | ✔ | ✔ | ✔ |

A coluna **Template** decide contra o que a tela é auditada (§0.7). Duas telas do mesmo tipo com
templates diferentes não se comparam entre si.

---

## 3 · B-01 · Shell, tema e densidade — v1 · 27/08

**Vale para:** toda tela do cockpit, sem exceção.

**Quem renderiza:** o template. `[TPL]` `Pt01Lista.dc.html` L23 (raiz `.cockpit`), L14-22
(`<style>` do `<helmet>`), L128-153 (`applyTheme`, que escreve os tokens).

### Itens que o bloco cobra — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Raiz `.cockpit` | `display:flex; min-height:100vh`, `background` = dois `radial-gradient` + `var(--bg)`, `color:var(--text)`, `font-family:var(--font-sans)`, `font-size:var(--d-fontsz,13px)`, `line-height:1.4` — `[TPL]` L23 |
| 2 | Coluna principal | `<main style="flex:1; min-width:0; display:flex; flex-direction:column">` — **um `<main>` por documento** `[TPL]` L29 |
| 3 | Tema | atributo `data-theme="dark"` + classe `dark` na raiz. Padrão do **produto unificado: claro**, escuro disponível — exceção assinada pela Maiara em 27/08/2026, que supersede o `[TPL]` L132 (dark salvo se `theme === 'light'`) |
| 4 | Acento | `--accent`, `--accent-2`, `--accent-soft` **e** `--color-primary` escritos na raiz a partir da matiz (295 = roxo da marca) `[TPL]` L135-140. **Por tema:** L `0.55` no claro e `0.72` no escuro (`--accent-2` `0.62`/`0.78`, `--accent-soft` `oklch(0.95 0.04 295)`/`oklch(0.32 0.07 295)`). Tela que não escreve isso fica com o roxo do claro no escuro — o DS só troca `--accent-soft` em `.cockpit[data-theme="dark"]` (`colors_and_type.css` L337) |
| 5 | Densidade | **contrato de tokens no shell, não prop de componente**: `--d-fontsz`, `--d-cpad-x`, `--d-cpad-y`, `--d-tb-y`, `--d-td-y`, `--d-th-y` e a rampa `--fs-1..9` inteira, reescritos por modo `[TPL]` L142-152 |
| 6 | Compacto (padrão) | `13px` · cpad `14/12` · tb-y `7` · td/th-y `6` · rampa `10/11/11.5/12.5/13.5/15.5/19/23/31` `[TPL]` L143-147 |
| 7 | Confortável | `13.5px` · cpad `22/16` · tb-y `11` · td-y `10` · th-y `9` · rampa `10.5/11.5/12.5/13.5/15/18/22/28/38` `[TPL]` L149-152 |
| 8 | Padding de célula | aplicado por CSS de topo mirando `.cockpit table td/th` com `var(--d-td-y)` / `var(--d-th-y)` `[TPL]` L21-22 |
| 9 | Garantia de altura | **piso na região de dados** (B-08 item 2) + `overflow:auto` no shell como válvula |

### Dependências e armadilhas que falham **sem erro no console**

1. `min-height:100vh` no shell só funciona no PT-01 porque ele passa `height` ao `DataTablePro`.
   Com `DataTable` (sem essa prop) o mesmo CSS faz o **documento** rolar e o cabeçalho fixo sair da
   tela. Ao levar estrutura de template, conferir de que **outra** parte dela o valor depende.
2. Limitar o shell e deixar a área de dados com `min-height:0` inverte a garantia: em janela curta a
   lista mostra 1 de 10 linhas e, com `overflow:hidden` no shell, não há escape.
3. `hint-size` **não é medida** — é placeholder de streaming, host `display:contents`. Nunca citar
   `hint-size` como largura de componente.
4. `--d-sidebar` e `--d-navpy` são escritos pelo template e **ninguém os consome** (o `AppSidebar`
   crava a própria largura). Não citá-los como contrato.

### Proibido

- Escrever px cravado onde existe token de densidade (`--d-*`) — quebra a densidade da página
  inteira. **Não proíbe** px em geometria de elemento que o DS não parametriza (ex.: altura de 30px
  de campo do template).
- Criar token novo. **Não proíbe** dar valor escuro, na camada de fundações, a token que o DS só
  declara no claro — isso exige ADR.
- Dois `<main>`; `overflow:hidden` no shell sem válvula.

### Teste de aceite

1. Raiz tem `data-theme="dark"` e `--color-primary` resolvido → inspecionar `getComputedStyle`.
2. Trocar densidade para confortável muda padding de **toda** a página, não só da tabela.
3. Janela de 700px de altura: a lista mostra pelo menos 8 linhas e a página rola.
4. Buscar no fonte da tela: zero `padding` de slot em px cravado.

---

## 4 · B-02 · Sidebar de navegação — v1 · 27/08

**Vale para:** toda tela do cockpit. Não se recria por módulo.

**Quem renderiza:** `[DS]` `AppSidebar` — `_ds_bundle.js` L860; largura **cravada** `width: 260,
flex: 'none'` (L1827-1830) e **sem prop de largura**.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Switcher de empresa | do componente; nada a passar |
| 2 | Grupos com contadores/badges | nav canônico embutido no componente |
| 3 | Tela ativa | prop `active` = key do item |
| 4 | Rodapé institucional | do componente |
| 5 | Cor | **sidebar é preta (dark-fixo) nos dois temas** — protótipo com sidebar escura está de acordo, não gera gap |

### Proibido

Passar largura, recolorir, acrescentar item de navegação pela tela. Colocar ação de tela (novo
produto, importar) na sidebar. **Não proíbe** passar `active`, nem discutir item novo de navegação —
isso é decisão de fora da tela, registrada em `recomendacoes-outras-telas.md`.

### Teste de aceite

1. `AppSidebar` montado com `active` correspondente à tela; nenhuma prop de estilo passada.
2. Medir em runtime: largura 260px. Se o handoff citar outro número, o handoff está errado.

---

## 5 · B-03 · Topbar — **APOSENTADO** · 27/08/2026

**Vale para:** nada. A faixa de 46 px do App Shell foi **retirada do produto** por decisão da Maiara em
27/08/2026, com o design system já atualizado: o `templates/pt-01-lista` não traz mais a faixa e a
primeira coisa dentro do `<main>` é o `PageHeader` (slot 1).

**Proibido:** reintroduzir a faixa em qualquer tela; recriar trilha, campo de busca global ou botão de
ajuda no topo do `<main>`. **Não proíbe** o `Breadcrumb` do DS em tela de detalhe cuja hierarquia não
esteja no título, nem a paleta ⌘K.

**O que aconteceu com cada item do bloco antigo:**

| Item aposentado | Destino |
|---|---|
| Trilha (`Breadcrumb`) | fora do índice — a sidebar marca o módulo e o `PageHeader` nomeia a tela |
| Busca global (campo + `<kbd>⌘K</kbd>`) | fora da tela. A paleta continua existindo |
| Paleta ⌘K (`Command`) | **mantida**, só por teclado (`Ctrl/Cmd+K`) — sem afordância visual, consequência declarada da remoção |
| Ajuda (`?`) | removido |

### Teste de aceite

1. `document.querySelector('main > header')` não existe; o primeiro filho do `<main>` é o invólucro do `PageHeader`.
2. `Ctrl/Cmd+K` continua abrindo a paleta.

---

## 6 · B-04 · Cabeçalho da página — v2 · 27/08

**Vale para:** toda tela. É o slot 1 do template.

**Quem renderiza:** `[DS]` `PageHeader` — `_ds_bundle.js` L5158; montado dentro de
`<div style="padding:0 var(--d-cpad-x)">` `[TPL]` L41-43.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Título | sentence case, nome do que a tela lista/edita. Sem contador no título |
| 2 | `stats` | **opcional** (`_ds_bundle.js` L5192: `(stats || subtitle) &&`). Quando existe: pares tabulares do **recorte atual**, com `tone` `danger`/`warn` quando o número for alarme, cada um derivado (B-22). Número que já vive na toolbar (B-07 item 5), nas abas (B-05), nos KPI (B-06) ou no rodapé (B-11) **não** se repete aqui — se todos já vivem, o cabeçalho fica sem `stats` (Consulta de Produtos, 27/08) |
| 3 | `actions` | nó à direita: **no máximo uma primária**; as demais `ghost`. `Button` do DS, `size="sm"`. Gatilho transcrito (o `⋯`, que precisa de `aria-haspopup`) usa a **altura do `size` dos irmãos**: 26 px com `sm` (`Button.jsx` H sm = 26, `_ds_bundle.js` L2256) |
| 4 | Menu `⋯` | `DropdownMenu` (L3945) com **apresentação da tela e dados** — colunas, densidade, exportar, imprimir. **Navegação não entra**. Botão só de ícone leva `aria-label` ("Mais ações desta tela"); o glyph é `menu` (três linhas) porque o `Icon` do DS não tem `ellipsis` — pauta, defeito de origem |
| 5 | Ação primária | o verbo da tela (novo, lançar, cadastrar) — e só existe se o perfil tem permissão |

**A lista de itens do menu `⋯` de cada tela é FECHADA e vive na ficha da tela**, não aqui: o que este
bloco fixa é a **natureza** do menu (apresentação e dados, nunca navegação) e o componente.

### Dependências

- Se a tela tem abas (B-05), o cabeçalho **não** repete o recorte das abas nos `stats`.
- Todo item do `⋯` que muda apresentação precisa de linha em B-20 (persistência) — senão a
  preferência morre ao recarregar, em silêncio.

### Proibido

Segundo título dentro da página; contador no título; item de navegação no `⋯`; mais de uma primária.
**Não proíbe** ação secundária em `ghost` ao lado da primária, nem `stats` com tom semântico.

### Teste de aceite

1. Um só `h1` na página, vindo do `PageHeader`.
0. Nenhum `stat` repete número que já aparece em abas, toolbar, KPI ou rodapé.
2. Cada `stat` reflete o recorte: trocar aba/faceta muda o número.
3. Abrir o `⋯`: nenhum item navega para outra tela.
4. Perfil sem permissão da ação primária: o botão não é renderizado (não é desabilitado).

---

## 7 · B-05 · Abas de módulo — v1 · 27/08

**Vale para:** índice com recortes mutuamente exclusivos. Cadastro usa abas **internas** de
formulário (B-17), que não são este bloco.

**Quem renderiza:** `[DS]` `TabBar` — `_ds_bundle.js` L6605, `tabs:[{key,label,icon?,count?}]`,
`active`, `onChange`; slot 2 do template `[TPL]` L46-48.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Aba | rótulo em sentence case + contador mono do recorte |
| 2 | Ativa | sublinhado em `--color-primary`; nunca pílula |
| 3 | Troca de aba | zera página, **limpa a seleção** (B-10) e reavalia quais KPI existem (B-06 item 5) |
| 4 | Contador | derivado do recorte, nunca digitado |

**Defeito conhecido do DS:** a `TabBar` gera barra de rolagem vertical em certos contextos — ADR
0403, contorno mirando `nav[aria-label="Sub-navegação"]`. O contorno é declarado; não inventar
outro. **Efeito colateral medido (27/08, viewport 835 px):** quando as abas não cabem
(`scrollWidth 617` > `clientWidth 527`), a barra horizontal ocupa 11 px, o `clientHeight` cai a
35 px e 1 px do `border-bottom` de 2 px da aba ativa (posicionado por `margin-bottom:-1px`) fica
cortado. Não há segundo contorno a aplicar — a correção é na altura, na origem.

### Proibido

Aba que abre outra rota; aba sem contador quando as irmãs têm; recorte por aba **e** por KPI
apontando para o mesmo critério sem controle visível.

### Teste de aceite

1. Trocar de aba: URL/estado do recorte muda, seleção fica vazia, contadores do cabeçalho batem.
2. Nenhuma aba com estilo de pílula; a ativa usa `--color-primary`.

---

## 8 · B-06 · Faixa de KPI-filtros — v2 · 27/08

**Vale para:** índice com alarme operacional e dashboard. Tela de cadastro não tem.

**Quem renderiza:** `[DS]` `KpiFilterCard` — `_ds_bundle.js` L4856; placa do ícone **fundo 18%, sem
borda** (L4866-4872); card raio 8, `padding` 12, `box-shadow: 0 1px 2px rgba(0,0,0,.05)`;
selecionado = borda `--color-primary` + anel de 1px cheio. Grade do template: `[TPL]` L52-59.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Cartão | `label` · `value` · `sub` · `tone` · `icon` · `selected` · `onClick`. A tela **não escreve** cor |
| 2 | Grade | colunas **fixas** (`repeat(N, 1fr)`, `gap:9px`) para N cartões — não `auto-fit`, que estica cartão em telas largas |
| 3 | Comportamento | clicar **alterna** o filtro; segundo clique limpa. Filtro de KPI compõe com aba, faceta e busca |
| 4 | Valor | derivado do recorte (B-22), com a mesma conta do `stat` equivalente do cabeçalho |
| 5 | Existência condicional | KPI que depende de permissão (`custo`) ou que não faz sentido no recorte (saldo em Serviços) **não é renderizado**; e ao desaparecer, **limpa o filtro que aplicava** |
| 6 | Alinhamento | com menos cartões, a faixa fica alinhada à esquerda, sem esticar |
| 7 | Posição | fora da área que rola (`[TPL]` pt-01 L51-59) **ou** dentro dela (`[TPL]` financeiro L37-43) — decide o template do tipo de tela (§0.7), não a tela |
| 8 | KPI de valor | `value` já formatado em BRL abreviado (`R$ 28,4k`) e `sub` com a contagem (`14 títulos`); os dois derivados do mesmo recorte |
| 9 | KPI que é projeção | rótulo diz o horizonte (`Saldo previsto` · sub `fim do mês`) e a fórmula fica declarada na ficha da tela — projeção sem horizonte escrito é pendência |
| 10 | Série temporal | se a tela mostra série (previsto vs. realizado), é `Chart` (`_ds_bundle.js` L2359), nunca barra desenhada à mão. Painel de gráficos como conteúdo principal segue **não coberto** (§1) |

### Dependências

Item 5 é a regra que evita o pior defeito desta faixa: KPI que sai da tela deixando a lista
filtrada por critério **sem controle visível**. Toda tela que esconde KPI por perfil ou por aba
precisa de linha de teste para isso.

### Proibido

Escrever fundo/borda/glyph do cartão na tela; `auto-fit` na grade; KPI que só informa (sem filtrar)
na mesma faixa dos que filtram. **Não proíbe** KPI puramente informativo em tela de dashboard, onde
o componente é o `KpiCard` (L4650), outro bloco.

### Teste de aceite

1. Clicar um KPI filtra; clicar de novo limpa; o cartão mostra o anel quando ativo.
2. Perfil sem `custo`: o KPI dependente não existe no DOM e a lista não está filtrada por ele.
3. Trocar para a aba onde o KPI não se aplica: cartão desaparece **e** a lista volta ao recorte
   cheio.
4. Buscar no fonte: zero valor de cor escrito para KPI.

---

## 9 · B-07 · Toolbar de recorte — v1 · 27/08

**Vale para:** índice. É o slot 3 do template.

**Quem renderiza:** `[TPL]` `Pt01Lista.dc.html` L62-70 · `[DS]` `Input` (L4481), `DropdownMenu`
(L3945), `FilterChip` (L4236).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Faixa | `display:flex; gap:8px; align-items:center`, `padding:var(--d-tb-y) var(--d-cpad-x)`, `border-bottom:1px solid var(--border)`, `background:var(--bg)` `[TPL]` L63 |
| 2 | Busca do recorte | campo `height:30`, `min-width:240`, `background:var(--surface)`, raio `var(--radius-md)`, com lupa; atalho `/` com `aria-keyshortcuts` `[TPL]` L64-67 |
| 3 | Facetas | um gatilho `DropdownMenu` por faceta; **o rótulo do gatilho mostra o valor escolhido** ("Categoria: Impressão digital") e o item ativo leva `✓` |
| 4 | Ordenação | gatilho próprio, mostrando campo **e** direção ("Código ↑") |
| 5 | Contagem | à direita (`margin-left:auto`), mono 11px `var(--text-dim)`, contando o **recorte**, não a página `[TPL]` L69 |
| 6 | Densidade | não vive aqui — é item do `⋯` do cabeçalho (B-04) e escreve os tokens de B-01 |
| 7 | Limpeza | qualquer mudança de recorte (aba, KPI, faceta, busca, ordem) **limpa a seleção** |

**Facetas de cada tela são lista FECHADA na ficha da tela.** Aqui fixa-se a forma, não o conteúdo.

### Contornos declarados (em pé)

| Onde | O que o DS não dá | O que a tela faz | Registro |
|---|---|---|---|
| Lupa no campo | `Input` não tem slot de ícone | glyph absoluto no invólucro + `padding-left:30px !important` (o componente escreve `padding` inline) | pauta · P1 |
| Gatilho de ícone do menu | `Button` não repassa `aria-haspopup`/`aria-expanded`; `DropdownMenu` acrescenta caret a gatilho-nó | forma **render-função** do `trigger` (L3991-3993) + valores do `Button ghost sm` citados (L2258-2288: 26px, `0 10px`, `500 12px/1`) | pauta · P2 |

### Proibido

`FilterChip` **e** rótulo-com-valor no gatilho ao mesmo tempo (dois vestígios do mesmo filtro);
segundo campo de texto competindo com a busca; filtro que não tem controle visível na tela.
**Não proíbe** `FilterChip` em tela cujo padrão seja filtro atrás de um botão "Filtros" — é o que o
PT-01 faz; a escolha é da ficha da tela, declarada.

### Teste de aceite

1. `/` foca a busca do recorte; `esc` a devolve.
2. Escolher faceta: o gatilho passa a exibir o valor e o item marcado tem `✓`.
3. Contagem à direita muda com a faceta e **não** muda ao paginar.
4. Selecionar linhas e mudar qualquer recorte: seleção zerada, `BulkBar` sai.

---

## 10 · B-08 · Região de dados — v2 · 27/08

**Vale para:** índice e qualquer tela com lista tabular.

**Quem renderiza:** `[TPL]` `Pt01Lista.dc.html` L73-77 (invólucro + moldura) · `[DS]` `DataTable`
(L2894) ou `DataTablePro` (L3099).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Invólucro | `flex:1; overflow:auto`, `padding:var(--d-cpad-y) var(--d-cpad-x) var(--d-cpad-x)` `[TPL]` L74 |
| 2 | Piso de altura | a região de dados recebe o piso; o shell recebe a válvula (`overflow:auto`) — B-01 dep. 2 |
| 3 | Moldura | `border:1px solid var(--border)`, `border-radius:var(--radius-lg)`, `overflow:hidden`, `background:var(--surface)`, `box-shadow:0 1px 2px rgba(0,0,0,.04)` — **`[TPL]` L75, não decisão da tela** |
| 4 | Escolha do componente | `DataTablePro` quando a tela precisa de header fixo interno, resize e ordenação embutida; `DataTable` quando precisa de **ordenação controlada** (`sortKey`/`sortDir`/`onSort`) ou `state` por linha — o `Pro` só tem `defaultSort` |
| 5 | Cabeçalho fixo | com `Pro`, é canon do componente (`position:sticky; top:0; zIndex:1` no `th`, fundo `var(--bg-2)` — L3188-3190). Com `DataTable`, é contorno da tela, declarado, e o `th` precisa de **fundo opaco** |
| 6 | Colunas | catálogo por tela, com largura, alinhamento e `mono` declarados; coluna de seleção do DS mede **36px** (`DataTable.jsx` L48 e L104) — largura mínima da tabela = Σ colunas visíveis + 36 |
| 7 | Coluna de ações | última, largura fixa; menu `⋯` por linha (B-16 rege a coexistência com o painel) |
| 8 | Ordenação | indicador mostra campo **e** direção; clicar no `th` alterna |
| 9 | Densidade | vem de B-01 (tokens). Modo compacto pode colapsar **conteúdo** de célula (2ª linha), nunca só o padding |
| 10 | Agrupamento | se a lista agrupa (por vencimento, parte, categoria, etapa), o **critério é declarado na ficha da tela** e a linha de grupo traz subtotal **derivado** (B-22). Lista agrupada sem subtotal, ou subtotal sem critério escrito, é pendência |
| 11 | Paginação | obrigatória quando o recorte pode passar de uma página; template que não pagina (ex.: `templates/financeiro`) rola a região inteira — e a ficha da tela **declara** qual dos dois, com o teto de linhas |
| 12 | Seleção | `selectable` só quando existe ação de lote (B-10). Tabela sem lote **não** ganha coluna de checkbox — e isso não é divergência |

### Proibido

Reimplementar tabela; `:nth-child()` para estilizar célula (mira atributo e comportamento, não
posição); somar a coluna de seleção duas vezes na largura mínima; deixar linha passar por baixo do
cabeçalho fixo. **Não proíbe** `th` sticky por CSS quando a tela usa `DataTable` — é contorno
declarado, com fundo opaco.

### Teste de aceite

1. Rolar a lista: `th.getBoundingClientRect().top` fica no topo da área de dados e nenhuma linha
   aparece sobre ele.
2. Moldura com raio `--radius-lg` e sombra de 1px — não borda reta.
3. Janela curta: a lista rola dentro da região; a página não corta linha sem scrollbar.
4. Largura mínima = Σ colunas visíveis + 36.

---

## 11 · B-09 · Anatomia da linha — v2 · 27/08

**Vale para:** índice. A ordem dos elementos é do bloco; o conteúdo é da tela.

**Quem renderiza:** células compostas dentro do `DataTable`; selos por `[DS]` `StatusBadge`
(L6282 — fundo **16%**, borda **30%**, peso **500**, pílula **9999**, `--fs-2`: L6357-6373 e
L6484-6497), tags por `TagChip` (L6697 — *unknown tags fall back to neutral*, docblock), pessoas por
`Avatar` (L1893).

### Itens — lista **FECHADA** (ordem obrigatória, da esquerda)

| # | Item | Contrato |
|---|---|---|
| 1 | Miniatura / avatar | 30px; sem imagem, quadro de borda tracejada com glyph do DS |
| 2 | Identificação | linha 1 = nome (peso 600); linha 2 = marcadores semânticos e código, 10,5px mínimo |
| 3 | Selo de situação | **abaixo da miniatura/identificação**, não no cabeçalho do painel. `StatusBadge` com `tone`/`label` — a tela não escreve cor |
| 4 | Números | mono, `tabular-nums`, alinhados à direita; rótulo e valor na mesma linha quando o número precisa de unidade ("Disponível 96 m²") |
| 5 | Unidade | sigla visível na lista e **explicada na dica** (`Tooltip`, L6948) |
| 6 | Estado da linha | `state:'urgent'` (trilho) / `selected` / `archived` (dim) — do componente; prioridade `selected > archived` é do `DataTable` |
| 7 | Subconjunto declarado | número que é subconjunto leva a declaração na própria linha ou na dica (regra §0.4-10) |
| 8 | Ações | `⋯` na última coluna; ação destrutiva com tom `danger` e confirmação por B-13 |
| 9 | Sinal do valor | valor com direção (entrada/saída, crédito/débito) mostra **o sinal e o tom**, nunca só a cor: `+ R$ 1.240` / `− R$ 380`. A cor vem de token semântico; quem não distingue cor lê o sinal |
| 10 | Data e vencimento | data em `dd/mm/aaaa` mono; atraso e proximidade por `StatusBadge kind="frescor"` com `rel` (o componente aceita sufixo relativo — "há 3 dias"). A tela **não** escreve o cálculo de cor do atraso |
| 11 | `kind` do selo | escolhido pelo **domínio do dado**, nunca por semelhança visual — tabela abaixo |

**Mapa de `kind` do `StatusBadge` — lista FECHADA** (`_ds_bundle.js` L6282; ERP: `documento`,
`fiscal` (NF-e), `os`, `prioridade`, `payment`, `sla`, `atendimento`, `frescor`, `tipo` (pj/pf);
módulo Ponto: `intercorrencia`, `rep`):

| O dado é | `kind` |
|---|---|
| situação do documento (rascunho, aprovado, cancelado) | `documento` |
| situação fiscal da nota | `fiscal` |
| etapa de ordem de serviço | `os` |
| urgência | `prioridade` |
| pago / parcial / vencido | `payment` |
| prazo de atendimento estourado | `sla` |
| recência (último contato, último movimento) | `frescor` (+ `rel`) |
| pessoa jurídica / física | `tipo` |

Dado que não cabe em nenhum `kind` usa `tone="outline"` (neutro) — e a escolha é declarada `[TELA]`.

### Proibido

Mais de um selo semântico competindo na mesma célula; escrever fundo/borda de selo; usar `kind`
de outro domínio por parecer semelhante (`kind="tipo"` é PJ/PF de pessoa — L6454-6457). **Não
proíbe** `tone="outline"` para selo neutro de tipo, nem `TagChip` neutro para categoria.

### Teste de aceite

1. Nenhum literal de cor de selo no fonte da tela.
2. Linha sem foto mostra o quadro tracejado, não espaço vazio.
3. Passar o mouse na unidade: a dica explica a sigla.
4. Número que é subconjunto: a declaração aparece na linha ou na dica.

---

## 12 · B-10 · Seleção e ações em lote — v1 · 27/08

**Vale para:** índice com ação aplicável a várias linhas.

**Quem renderiza:** `[DS]` `BulkBar` — `_ds_bundle.js` L2137; o componente **já é**
`position:sticky; bottom:16; margin:0 auto` (L2143-2148).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Caixa por linha | `selectable` no componente de tabela |
| 2 | Caixa do cabeçalho | marca a **página**, **somando** à seleção existente — não substitui |
| 3 | Barra | `count` + `label` + `actions` (com `tone:'danger'` onde couber) + `onClose` |
| 4 | Posição | dentro da área que rola, **sem invólucro** — envolvê-la em outro sticky quebra a ancoragem |
| 5 | Limpeza | qualquer mudança de recorte limpa a seleção (B-07 item 7) |
| 6 | Ação destrutiva em lote | passa por B-13, nomeando a quantidade |

### Proibido

Invólucro sticky ao redor da `BulkBar`; ação de lote que não existe para o perfil aparecer
desabilitada (não renderizar). **Não proíbe** ação de lote que abre um fluxo em outra tela.

### Teste de aceite

1. Marcar 2 linhas, ir para a página 2, marcar o cabeçalho: contagem soma, não zera.
2. `BulkBar` acompanha a rolagem sem invólucro extra.
3. Mudar faceta: barra desaparece.

---

## 13 · B-11 · Rodapé de lista — v2 · 27/08

**Vale para:** índice paginado. **Opcional** por template: `templates/financeiro` fecha a lista sem
rodapé, com a contagem e o saldo na linha da busca. A ficha da tela declara qual dos dois — o que
não pode é a tela não ter **nenhum** lugar onde o total do recorte apareça.

**Quem renderiza:** `[TPL]` `Pt01Lista.dc.html` L80-82 (`border-top:1px solid var(--border)`,
`padding:7px var(--d-cpad-x)`, `background:var(--surface)`) · `[DS]` `Pagination` (L5263:
`page`/`pageCount`/`total`/`pageSize`/`onChange`).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Presença | o rodapé existe sempre que houver linha |
| 2 | Totais à esquerda | totais do **recorte** (não da página), com rótulo que diz de que conjunto é |
| 3 | Permissão | total de valor só aparece para quem tem a chave correspondente (B-21) |
| 4 | Paginação à direita | `Pagination` com "N–M de T" e itens por página |
| 5 | Derivação | todo total é derivado (B-22); nunca digitado em dois lugares |

**Defeito conhecido do DS:** `Pagination` não cobre primeira/última página — ADR 0402. Contorno
declarado ou ausência declarada; não inventar controle novo.

### Proibido

Total de página apresentado como total do recorte; rótulo genérico ("Total") quando o número é
subconjunto. **Não proíbe** dois totais lado a lado, se cada um disser de que conjunto é.

### Teste de aceite

1. Paginar não muda os totais da esquerda.
2. Perfil sem permissão de preço: nenhum total de valor no DOM.
3. "N–M de T" bate com a contagem da toolbar.

---

## 14 · B-12 · Painel de detalhe (Drawer) — v1 · 27/08

**Vale para:** índice cujo item tem ficha. É o PT-02 do sistema.

**Quem renderiza:** `[DS]` `Drawer` (L3753; `zIndex:60` em L3807; faixa do cabeçalho L3833-3843;
tipografia L3876-3889; rodapé `padding 12px 18px`, `gap 8`, `justify-content:flex-end`, `border-top`
L3893-3903) + `DrawerSection` (L3907; `border-top:1px solid var(--border-2)` L3911-3914; `h4`
`600 10.5px/1.4` uppercase `.05em` `var(--text-mute)` L3916-3922; `title` é opcional — L3916).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Largura | por tela, declarada na ficha; mais larga quando há grade/composição |
| 2 | Cabeçalho | nome + código **dentro da faixa do ✕** — passa-se `badge` (único slot que renderiza na faixa) e **não** `title`/`subtitle`; `aria-label` do diálogo preenchido |
| 3 | Seções | `DrawerSection`; `title` omitido quando a informação já está acima. Nenhuma régua ou fundo escrito pela tela entre seções |
| 4 | Subtítulos internos | mesmos valores do `h4` do `DrawerSection` (citados, não escolhidos) — um só nível de subtítulo no painel |
| 5 | Par rótulo→valor | `[TPL]` L101-104: `flex`, `space-between`, `align-items:baseline`, rótulo `12px var(--text-dim)`, valor em `var(--font-mono)` |
| 6 | Rodapé | `Button` do DS dentro da prop `footer`; **uma primária, e ela é o último botão** (ghost antes, primária depois) |
| 7 | Entrega na tela responsável | o painel **não faz o trabalho de outra tela**: "Abrir cadastro", "Formar preço", "Usar em orçamento" levam à tela dona do assunto. Quais ações existem é decisão da ficha da tela |
| 8 | Coexistência | abrir menu de linha **fecha o painel** — B-16 |
| 9 | Alerta interno | `Alert` (L738) no fluxo do painel para condição que exige atenção, não `Toast` |

### Contornos declarados (em pé)

| Onde | O que o DS não dá | O que a tela faz | Registro |
|---|---|---|---|
| Rolagem por seção | `DrawerSection` não é colapsável | barra fixa de atalhos no topo do painel, que rola até a seção | pauta · P2 |
| Separação no tema escuro | `--surface` e `--border-2` quase não se distinguem no escuro | **aplicado como está**, registrado como defeito | pauta · D |

### Proibido

Título repetido abaixo da faixa; segundo nível de subtítulo; régua desenhada pela tela entre seções;
mexer em `zIndex` do DS; formar preço/calcular pedido dentro do painel (a consulta é leitura).
**Não proíbe** `!important` mirando invólucro **declarado** para suprimir a régua de uma seção
específica — nunca mirando posição de filho.

### Teste de aceite

1. Abrir o painel: nome e código na faixa do ✕; nenhum bloco de título abaixo dela.
2. Buscar no painel: um só tamanho de subtítulo uppercase (10,5px).
3. Rodapé: última posição é a primária; nada desenhado pela tela na faixa.
4. Abrir o `⋯` de uma linha com o painel aberto: o painel fecha; em nenhum quadro os dois aparecem.

---

## 15 · B-13 · Modal de confirmação — v2 · 27/08

**Vale para:** toda ação destrutiva ou irreversível — inclusive as **irreversíveis por lançamento**
(baixar título, receber, pagar, estornar, fechar período).

**Quem renderiza:** `[DS]` `Modal` — `_ds_bundle.js` L5041 (`open`/`onClose`/`title`/`children`/
`footer`/`width` padrão 420).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Título | o que vai acontecer, em sentence case |
| 2 | Corpo | **nomeia o alvo** ("Inativar o produto 1088 — Lona 440g?") e a consequência |
| 3 | Rodapé | cancelar (`ghost`) + confirmar (`danger` quando destrutivo), confirmar por último |
| 4 | Escopo em lote | quando a ação é de lote, o corpo nomeia a **quantidade** |
| 5 | Depois | resultado por `Toast` (B-14); o modal fecha |
| 6 | Lançamento com data | ação que gera lançamento pede **a data da ocorrência** dentro do modal (`DatePicker`, L3360), com o padrão em hoje — baixa é lançamento com janela, não mudança de saldo. Se o período está fechado, o modal recusa e diz por quê |
| 7 | Valor parcial | ação que aceita valor diferente do título mostra o valor original, o informado e a diferença — os três, e a diferença é derivada |

### Proibido

Modal para **detalhe** (isso é B-12); confirmação sem nomear o alvo; ação destrutiva direta na linha
sem modal. **Não proíbe** ação reversível sem modal, se houver desfazer no `Toast`.

### Teste de aceite

1. Toda ação destrutiva abre `Modal` nomeando o alvo (ou a quantidade).
2. Nenhum `Modal` usado como ficha de detalhe.

---

## 16 · B-14 · Feedback efêmero (Toast) — v1 · 27/08

**Vale para:** toda ação que muda dado ou preferência.

**Quem renderiza:** `[DS]` `Toast` — `_ds_bundle.js` L6900 (`tone` default/ok/warn/danger, `icon`,
`kbd`).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Mensagem | o que aconteceu, no passado, nomeando o alvo |
| 2 | Tom | `ok` para conclusão, `danger` para falha, `warn` para conclusão parcial |
| 3 | Desfazer | quando a ação é reversível, o `Toast` traz a dica de atalho (`kbd`) |
| 4 | Fronteira | condição **persistente** não é `Toast` — é `Alert` no fluxo (B-12 item 9) |

### Proibido

`Toast` para erro de validação de campo (isso é B-17), nem para condição que continua verdadeira
depois de ele sumir.

### Teste de aceite

1. Ação de lote conclui → um `Toast`, com contagem.
2. Nenhuma informação necessária ao usuário existe **só** dentro de um `Toast`.

---

## 17 · B-15 · Estados de exceção — v1 · 27/08

**Vale para:** toda tela que carrega dado.

**Quem renderiza:** `[DS]` `EmptyState` (L4126 — variantes `default`/`first`/`no-results`/`no-perm`/
`offline`/`done`/`filtered`/`error`) e `Skeleton` (L6187 — `text`/`title`/`caption`/`avatar`/
`avatar-md`/`row`/`card`).

### Itens — lista **FECHADA** (os cinco estados são obrigatórios em toda lista)

| # | Estado | Contrato |
|---|---|---|
| 1 | Carregando | `Skeleton` na forma do conteúdo (linhas de tabela, não spinner) |
| 2 | Zero absoluto | `variant="first"` — diz o **porquê** e a **ação** para criar o primeiro |
| 3 | Zero por recorte | `variant="no-results"` ou `filtered` — e oferece limpar o recorte |
| 4 | Sem permissão | `variant="no-perm"` — diz qual permissão falta, sem revelar o dado |
| 5 | Erro | `variant="error"` — o que falhou e como tentar de novo |

Todo estado traz **por que** e **o que fazer**. Estado vazio sem ação é pendência.

### Proibido

Lista vazia mostrando só a moldura; spinner no lugar de `Skeleton`; texto de erro genérico.

### Teste de aceite

1. Filtrar por algo inexistente: `no-results` com botão de limpar recorte.
2. Simular falha: `error` com ação de repetir.
3. Perfil sem permissão: `no-perm`, e nenhum dado do bloco no DOM.

---

## 18 · B-16 · Pilha de overlays — v1 · 27/08

**Vale para:** toda tela com dois ou mais overlays (painel, menu, dica, modal, paleta).

**Quem renderiza:** o DS. Valores medidos: `Drawer` `zIndex:60` (L3807), `DropdownMenu` `zIndex:70`
(L4040); o `DropdownMenu` fecha por `mousedown` no document (L3960-3965) e aceita `trigger` como
render-função `({open, onClick})` (L3991-3993).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Não empilhar | menu do chrome da lista e painel **nunca abertos juntos**: abrir menu fecha o painel |
| 2 | Alcance da regra | vale para **todo** `DropdownMenu` do chrome (linha, `⋯` do cabeçalho, facetas, ordenação) |
| 3 | Mecanismo | no gatilho: `preventDefault()` + `stopPropagation()`, fechar o painel, e só então repassar o clique ao componente — sem isso o `onRowClick` reabre o painel |
| 4 | Modal sobre painel | `Modal` disparado de dentro do painel mantém o painel aberto atrás |
| 5 | Dica | `Tooltip` recortado em container de rolagem é defeito conhecido — ADR 0404; contorno declarado, não novo |
| 6 | Z-index | **nunca** alterado pela tela |

### Proibido

Mexer em `zIndex` do DS; usar `click` no document para fechar (o DS usa `mousedown`).

### Teste de aceite

1. Painel aberto + clique no `⋯`: painel fecha, menu abre, nenhum quadro com os dois.
2. Fechar por clique fora continua funcionando após o contorno do gatilho.

---

## 19 · B-17 · Formulário de cadastro — v1 · 27/08

**Vale para:** Cadastro de Produto, Cadastro de Pessoa, e qualquer tela de criação/edição.
**Formar preço é cadastro** — não é trabalho da consulta.

**Quem renderiza:** `[DS]` `Input` (L4481), `Textarea` (L4511), `Select` (L4546), `Switch` (L6526),
`Checkbox` (L2511), `RadioGroup` (L6067), `DatePicker` (L3360), `PeriodBar` (L5483). Rótulo
uppercase, help e erro inline vêm do componente.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Agrupamento | seções por assunto, com título; abas internas só quando as seções passam de uma tela de rolagem |
| 2 | Campo | sempre componente do DS, com `label`, `help` quando a regra não é óbvia, `error` inline |
| 3 | Obrigatoriedade | marcada no campo, não só na mensagem de erro |
| 4 | Erro | inline no campo (`aria-invalid`), **nunca** só em `Toast`; o primeiro campo inválido recebe foco ao submeter |
| 5 | Somente leitura | campo derivado (custo do kit, saldo do pai, margem) é `readonly` e diz de onde vem |
| 6 | Dinheiro e número | entrada com guard de locale pt-BR (separador de milhar de 3 dígitos, vírgula decimal); saída com 2 casas |
| 7 | Data | `DatePicker` do DS (dd/mm/aaaa); se o popover for recortado por ancestral com `overflow`, o contorno é portal — declarado |
| 8 | Rodapé de ação | uma primária (salvar) + `ghost` (cancelar); primária por último. Barra fixa quando o formulário rola |
| 9 | Rascunho | se houver, chave `{tenant}.{user}` (B-20) |
| 10 | Autoridade | o servidor recalcula tudo o que a tela calcula (B-22 item 4) |

### Proibido

Campo desenhado à mão; validação só no cliente tratada como garantia; regra de negócio digitada em
campo de texto livre; salvar sem confirmar quando a ação apaga dado (B-13). **Não proíbe** máscara
de entrada e cálculo na tela para dar feedback — desde que declarados como feedback, não contrato.

### Teste de aceite

1. Submeter vazio: cada obrigatório mostra erro inline e o primeiro recebe foco.
2. Digitar `1.234,56` num campo de dinheiro e salvar: valor enviado é `1234.56`.
3. Campo derivado é `readonly` e nomeia a origem.
4. Rodapé: uma só primária, em último lugar.

---

## 20 · B-18 · Board de produção — v1 · 27/08

**Vale para:** Produção/OP e Oficina. Substitui B-08/B-09/B-11 nessas telas (não convive com
tabela na mesma visão).

**Quem renderiza:** `[DS]` `BoardColumn` (L1997 — `status` backlog/todo/doing/review/done/blocked/
cancelled, `count` automático, `onDrop`, estado "vazio") + `TaskCard` (L6776 — `displayId`, `title`,
`priority` p0–p3, `module`, `owner`, `estimateH`, `storyPoints`, `due`, `isBlocked`, `isOverdue`,
`selected`) + `FsmStepper` (L4306) para pipeline no detalhe · referência de tela:
`templates/oficina-auto`.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Etapas | uma coluna por etapa da FSM do módulo; a lista de etapas é da ficha da tela |
| 2 | Contagem | do componente, derivada dos cards |
| 3 | Card | `TaskCard`; a tela **não** escreve cor de prioridade nem de atraso |
| 4 | Coluna vazia | estado "vazio" do componente, não coluna sem nada |
| 5 | Mover | `onDrop`; movimento inválido pela FSM é recusado com `Toast` explicando a regra |
| 6 | Detalhe | abre B-12 com `FsmStepper variant="full"` |
| 7 | Recorte | B-06 e B-07 valem: KPI e facetas filtram o board inteiro |

### Proibido

Card desenhado à mão; mover livremente entre etapas que a FSM não liga; board **e** tabela na mesma
visão.

### Teste de aceite

1. Arrastar para etapa inválida: card volta e o `Toast` diz a regra.
2. Contagem de cada coluna bate com os cards visíveis do recorte.

---

## 21 · B-19 · Teclado, foco e acessibilidade — v1 · 27/08

**Vale para:** toda tela.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Foco visível universal | contorno de 2px em `--accent`, `offset` 2px, em todo campo, botão e link |
| 2 | `prefers-reduced-motion` | animação e transição reduzidas a ~0 |
| 3 | Atalhos de lista | `/` busca · `↑`/`↓` linha ativa · `↵` abre detalhe · `esc` solta a linha e fecha overlay · `⌘K` paleta |
| 4 | Anúncio dos atalhos | `aria-keyshortcuts` + `aria-label` aplicados **depois** que o componente do DS monta |
| 5 | Diálogos | `role="dialog"` com `aria-label` preenchido; foco preso enquanto aberto; devolvido ao gatilho ao fechar |
| 6 | Contraste | mínimo 4,5:1 para texto abaixo de 18px. Medição com transição desligada — o `<a>` do DS tem `transition: color` e medir no meio da transição produz falha fantasma |
| 7 | Alvo de toque | 44px quando a tela for usada em toque (declarar na ficha se for desktop-only) |
| 8 | Não verificado | o que não foi testado é **declarado** na auditoria da tela (zoom 200%, leitor de tela, Lighthouse) |

### Proibido

Remover foco visível; anunciar atalho que não existe; presumir contraste sem medir. **Não proíbe**
declarar a tela como desktop-only — é decisão legítima, registrada.

### Teste de aceite

1. Navegar a tela inteira só com teclado: todo alvo alcançável e visível.
2. `esc` fecha o overlay mais alto e devolve o foco ao gatilho.
3. Tabela de contraste da tela sem par abaixo de 4,5:1, ou com exceção medida e registrada.

---

## 22 · B-20 · Persistência de preferência — v1 · 27/08

**Vale para:** toda tela com recorte, colunas, densidade ou rascunho.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Chave | `oi.<modulo>.<coisa>.v<N>` — versão no nome, para migração |
| 2 | Escopo | rascunho e preferência de usuário: `{tenant}.{user}` (multi-tenant é Tier 0 até em conveniência de UI) |
| 3 | Conteúdo | o que persiste é declarado item a item na ficha da tela (aba, KPI, busca, ordem, itens por página, colunas ocultas, densidade, recentes) |
| 4 | Robustez | JSON inválido ou cota cheia **não quebram a tela**: cai no padrão |
| 5 | Gravação | com debounce (250ms), nunca a cada tecla |
| 6 | Não persistir | seleção de linhas e estado de overlay |

### Proibido

Apagar ou sobrescrever chave que a tela não escreveu; chave sem prefixo do produto; persistir dado
de domínio (isso é servidor).

### Teste de aceite

1. Aplicar recorte, recarregar: recorte de volta. Corromper o valor: tela abre no padrão.
2. Nenhuma seleção de linha sobrevive ao recarregar.

---

## 23 · B-21 · Permissões e visibilidade — v1 · 27/08

**Vale para:** toda tela que mostra preço, custo, composição, compras ou margem.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Chaves | `preco`, `custo`, `composicao`, `compras`, `margem` |
| 2 | Dependência | `margem` **exige** `custo` (preço + margem revelam o custo) |
| 3 | Forma da negativa | elemento sem permissão **não é renderizado** — não desabilitado, não vazio |
| 4 | Efeito colateral | esconder um controle de recorte **limpa o filtro** que ele aplicava (B-06 item 5) |
| 5 | Alçada de desconto | incide sobre o **preço de tabela**, nunca sobre o custo — pode ser exibida a quem não vê custo |
| 6 | Bloco inteiro sem permissão | `EmptyState variant="no-perm"` (B-15 item 4) |
| 7 | Servidor | a permissão vale no servidor; a tela só reflete |

### Proibido

Mostrar campo vazio ou `—` no lugar do valor sem permissão (revela que existe); calcular na tela um
número que a permissão esconde; derivar custo a partir de preço + margem para quem não tem `custo`.

### Teste de aceite

1. Perfil vendedor: nenhum nó de custo/margem no DOM, e a lista sem filtro residual.
2. Conceder `margem` sem `custo`: a tela recusa a combinação (ou trata como sem `margem`).

---

## 24 · B-22 · Números exibidos e derivação — v1 · 27/08

**Vale para:** todo número que aparece em qualquer tela.

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Classificação | todo número é **derivado** (com fórmula escrita) ou **digitado** (com campo de origem). Nunca sem marca |
| 2 | Fechamento | dois números do mesmo fato fecham por cálculo: custo do kit = soma da composição; saldo do pai = soma da grade; montáveis = mín(saldo do componente ÷ qtd) |
| 3 | Subconjunto | número que é subconjunto declara isso no rótulo ou na dica (saldo vendável ≠ físico) |
| 4 | Autoridade | o servidor recalcula; a tela calcula para dar feedback. Cada garantia do servidor vira linha na §"O que o servidor precisa garantir" da tela |
| 5 | Locale | dinheiro `R$ 1.234,56`; entrada com guard de milhar/decimal; saída com 2 casas |
| 6 | Tipografia | mono + `tabular-nums` em todo número de coluna |
| 7 | Natureza do local | `venda` soma no disponível; `bloqueado` existe e não vende; custódia de cliente não é ativo; kits produzidos é transformação, não local (contá-lo duplicaria o material) |
| 8 | Derivados fora do estado | recalculados a cada render |

### Proibido

Digitar em dois lugares o mesmo fato; exibir total de página como total do recorte; tratar cálculo
da tela como contrato.

### Teste de aceite

1. Somar a composição do kit na mão: bate com o custo exibido.
2. Somar a grade: bate com o saldo do pai.
3. Todo número da tela tem, no handoff, a marca derivado/digitado.

---

## 24.1 · B-23 · Recorte de período — v1 · 27/08

**Vale para:** toda tela de consulta cujo dado tem data de referência — Financeiro, BI, relatórios,
Compras, RH/Ponto. **Tela de catálogo não tem** (produto não tem período).

**Quem renderiza:** `[DS]` `PeriodBar` — `_ds_bundle.js` L5483 (`value` `{from, to, preset}` ·
`onChange` · `presets` · `label`), que reusa `DatePicker` (L3360, calendário PT-BR dd/mm/aaaa).

### Itens — lista **FECHADA**

| # | Item | Contrato |
|---|---|---|
| 1 | Presença | o período é **sempre visível**, nunca escondido atrás de menu — é o recorte que muda todo número da tela |
| 2 | Presets | segmented de janelas rolantes (Dia / Semana / Mês); a lista de presets de cada tela é da ficha |
| 3 | De / Até | os dois campos ficam visíveis junto dos presets; clicar preset preenche o intervalo, editar campo comuta para `custom` |
| 4 | Campo de data do período | o do `PeriodBar`; a tela não desenha outro |
| 5 | Qual data | a tela declara **qual campo** o período filtra (emissão, vencimento, pagamento, competência) e o rótulo diz isso — período sem campo declarado é pendência |
| 6 | Efeito | mudar o período recalcula KPI (B-06), totais (B-11) e contagem (B-07), e **limpa a seleção** (B-10) |
| 7 | Regime | quando a tela aceita caixa **e** competência, a escolha é controle visível ao lado do período, não preferência escondida |
| 8 | Persistência | período entra em B-20 com o nome do preset, não com as datas resolvidas — senão "este mês" congela no mês antigo |
| 9 | Vazio por período | `EmptyState variant="filtered"` dizendo o intervalo, com ação de ampliar (B-15) |

### Proibido

Período dentro de menu ou faceta; dois controles de data concorrendo; persistir datas resolvidas no
lugar do preset; número na tela que ignora o período sem dizer que ignora. **Não proíbe** número
deliberadamente fora do período (ex.: saldo atual acumulado) — desde que o rótulo declare
("acumulado, independe do período").

### Teste de aceite

1. Trocar o preset: KPI, totais e contagem mudam juntos; seleção zerada.
2. Editar "Até": o segmented passa a `custom`.
3. Recarregar amanhã com "Mês" salvo: o intervalo é o mês corrente, não o do dia da gravação.
4. Cada número da tela responde ao período **ou** tem no rótulo que não responde.

---

## 25 · Como manter este manual

1. **Achado novo em um bloco** → edita-se **só a ficha daquele bloco**, sobe-se a versão
   (`B-07 v1 → v2`) e a data, e acrescenta-se linha no registro abaixo. Nenhuma ficha de tela muda.
2. **Limite novo do design system** → `pauta-design-system.md`, no formato estado atual / contorno /
   proposta / prioridade — e o bloco passa a citar a pauta.
3. **Bloco novo** (dashboard, impressão, PDV) → entra no índice §1, na matriz §2 e ganha ficha
   própria; até existir, a linha de "não cobertos" fica no §1, visível.
4. **Divergência entre este manual e o DS** → o DS ganha; relata-se com o número medido e **não se
   altera nada** até a decisão vir.
5. Auditoria (LAUDO, CHECKLIST 15D, ADR) continua **datada e separada** — este manual é
   especificação, não medição.

### Registro de revisões por bloco

| Data | Bloco | O que mudou |
|---|---|---|
| 27/08/2026 | B-01 … B-22 | criação do manual, a partir do handoff da Consulta de Produtos, do template PT-01 e do DS medido |
| 27/08/2026 | §0.7 | regra de template canônico por tipo de tela — o PT-01 deixa de ser tratado como universal (achado do teste a seco contra `templates/financeiro`) |
| 27/08/2026 | B-06 v2 | posição da faixa por template · KPI de valor · KPI de projeção com horizonte · série temporal por `Chart` |
| 27/08/2026 | B-08 v2 | agrupamento com subtotal derivado · paginação declarada por template · `selectable` só com ação de lote |
| 27/08/2026 | B-09 v2 | sinal do valor · data/vencimento com `frescor` + `rel` · mapa fechado de `kind` do `StatusBadge` |
| 27/08/2026 | B-11 v2 | rodapé passa a opcional por template, com piso: o total do recorte precisa existir em algum lugar |
| 27/08/2026 | B-13 v2 | irreversível por lançamento · data da ocorrência no modal · valor parcial com diferença derivada |
| 27/08/2026 | B-23 | bloco novo — recorte de período (`PeriodBar`), ausente na v1 e obrigatório em Financeiro/BI/Ponto |
| 27/08/2026 | B-01 itens 3-4 | tema claro como padrão do produto (exceção assinada) · acento escrito **por tema** (L 0.55 claro / 0.72 escuro) |
| 27/08/2026 | B-03 v2 | busca global é campo real que abre a paleta ao focar (botão-fachada descartado) · guarda de reentrada do `Command` · hover dos controles do topbar |
| 27/08/2026 | B-04 v2 | `stats` opcional e proibido repetir número de outro bloco · altura do gatilho `⋯` = altura do `size` dos irmãos (26 px) · `aria-label` em botão só-ícone |
| 27/08/2026 | B-05 | nota medida: com barra horizontal, o contorno do ADR 0403 corta 1 px do sublinhado da aba ativa |
| 27/08/2026 | B-03 | **bloco aposentado** — topbar retirada do produto (DS e template já atualizados); ⌘K sobrevive só como atalho de teclado |
