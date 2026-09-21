# Módulo Fabricação (Manufacturing) — arquitetura dos arquivos

Camada 4 do sistema. Consome as camadas 0–2 e **não cria** token, cor, tamanho de fonte nem padrão
de tela novo.

Este documento fala de **arquivo**. Comportamento, regra de negócio e diff estão no `README.md` da
raiz do pacote — aqui não se repete nenhum dos dois.

---

## CSS — cascata por camada

A ordem dos `<link>` no HTML **é o contrato**: camada de cima nunca é sobrescrita por camada de
baixo.

| # | Camada | Arquivo | Responsabilidade | Origem |
|---|---|---|---|---|
| 0 | Design System | `_ds/office-impresso-design-system-019dd02f-…/colors_and_type.css` | tokens de identidade: `.cockpit` (claro) e `.cockpit[data-theme="dark"]` (escuro), rampa `--fs-1..9`, estilos de elemento | cópia **verbatim** — não editar |
| 0 | Shell | `styles.css` | folha do shell cockpit: `:root` da sidebar, `--accent`, `--radius*`, `--font-*`, e os padrões de página que o módulo consome (`.os-page-h`, `.os-btn` e variantes) | cópia **verbatim** do protótipo — não editar |
| 1 | Fundações | *(vazia)* | — | **por que vazia:** nenhuma correção de CSS do DS foi aplicada nesta tela. Os 4 defeitos medidos estão em `adr/` e três deles são de **componente React do repo alvo**, não de folha de estilo; o quarto (paleta de impressão) não tem token para corrigir. Corrigir `--text-mute` por `!important` aqui esconderia o defeito em vez de resolvê-lo na origem |
| 2 | Shell | `02-shell/css/otimiza-ondas.css` | `thead` fixo, `tabular-nums`, **foco visível universal**, alvo de toque ≥44px em `pointer: coarse`. Tudo em `:where()` → especificidade 0 | cópia **verbatim** |
| 2 | Shell | `02-shell/css/guia-standalone.css` | **só do pacote**: `html/body`, piso de altura da região de dados, barra do guia (`.guia-*`) | escrito para o pacote — **descartado na reimplementação** |
| 3 | Padrões de tela | *(vazia)* | — | **por que vazia:** os padrões que esta família usa (cabeçalho de página, botão, campo, tabela densa) já existem na camada 0 (`styles.css`) e no alvo vêm de `Components/shared/*` + `Components/ui/*`. Nenhum padrão novo foi inventado |
| 4 | Módulo | `04-modulos/manufacturing/css/manufacturing.css` | todo o CSS da família, escopado em `.mfg-*`: abas, KPIs, toolbar, 4 variantes de tabela, drawer, modal, editor, campos, paginação, simulador e a folha de prova `@media print` | cópia **verbatim** de `manufacturing-page.css` |

### Regra de decisão (antes de escrever CSS, pergunte onde ele pertence)

- vale para **qualquer** tela do cockpit → **camada 2**;
- vale para **qualquer** tela que use este padrão (lista, formulário, overlay) → **camada 3**;
- é **correção de defeito do DS** → **camada 1 + ADR** (para morrer quando a origem for corrigida);
- só faz sentido **nesta família de telas** → **camada 4**.

Se a regra usa uma classe do módulo (`.mfg-*`), ela é camada 4 por definição.

### Escopo do arquivo de módulo

Duas fronteiras dentro de `manufacturing.css`:

1. **Tela** (linhas 1-230): tudo prefixado `.mfg-`. As duas únicas regras que tocam classe de
   fora são `.os-btn.ghost.danger` (acrescenta borda ao botão de perigo) e `.mfg-ed-f.mfg-inline`.
   Nenhuma outra regra alcança elemento fora de `.mfg-root`.
2. **Papel** (bloco `@media print`, linhas 231-264): a folha de prova. `body > *` é escondido e só
   `.mfg-print-host` aparece. Este bloco **sai do escopo `.mfg-root` de propósito** — o portal
   monta no `<body>`, senão qualquer ancestral com `overflow` recortaria a folha.

---

## JS — ordem de carga e responsabilidade

Cada arquivo termina publicando em `window` (escopos Babel não se comunicam). **A ordem dos
`<script>` no HTML é a ordem de dependência.**

| # | Arquivo | Publica | Papel | Tamanho |
|---|---|---|---|---|
| 1 | `manufacturing-data.jsx` | `window.MFG` | **fonte única** de dado de cena **e de cálculo**: `INSUMOS`, `SUBUN`, `GRUPOS`, `RECIPES`, `LOCAIS`, `PRODUCOES`, `SETTINGS`, `bySku`, `subUnsDe`, `multDe`, `custos`, `consumoOP`, `usosDoInsumo`, `fmt`, `num`, `fmtDate` | 191 linhas |
| 2 | `icons.jsx` | `window.I` | primitivo de ícone (22 glifos, traço 1.6px, grade 24×24). **Contorno**: o alvo usa `lucide-react` inteiro | 89 linhas |
| 3 | `manufacturing-recipe.jsx` | `MfgNovaReceita`, `MfgIngredientesEditor`, `MfgCampo` | CRUD da receita: modal de criação, busca de insumo, editor de ingredientes. Publica também o primitivo de campo que os outros arquivos consomem | 268 linhas |
| 4 | `manufacturing-insumos.jsx` | `MfgInsumosView` | impacto reverso do insumo + simulador de variação de preço | 98 linhas |
| 5 | `manufacturing-producao.jsx` | `MfgProducaoView`, `MfgProducaoForm`, `MfgProducaoDrawer`, `MfgRelatorio`, `MfgConfig` | ordens de produção, relatório e configurações. **Depende de `MfgCampo`** → carrega depois do item 3 | 344 linhas |
| 6 | `manufacturing-print.jsx` | `MfgFichaPrint` | folha de prova PT-07 em portal no `<body>`, impressão em lote | 123 linhas |
| 7 | `manufacturing-page.jsx` | `window.ManufacturingPage` | a tela: abas, KPIs, consulta, seleção, drawer da receita. Orquestra todos os anteriores | 369 linhas |
| 8 | `manufacturing-app.jsx` | — | **único ponto de mount** + shell mínimo + stub de `window.__go`. **Contorno do pacote**, não é portado | 55 linhas |

### Por que a divisão é essa

1. **Dado e cálculo nunca moram em arquivo de UI.** Uma fonte só: nenhuma lista de domínio e
   nenhuma fórmula nasce dentro de componente.
2. **Cada peça grande vira arquivo próprio.** Editor de ingredientes, formulário de ordem e folha
   impressa são telas dentro da tela.
3. **Regra de negócio com vocabulário próprio vira arquivo próprio** — o impacto reverso do insumo
   fala de "peso no custo" e "variação simulada", vocabulário que não aparece em nenhum outro
   lugar.
4. **Primitivos carregados cedo** (ícone no item 2, campo no item 3).
5. **Um único arquivo monta a aplicação.**
6. Alvo prático de 15–50 KB por arquivo; o maior aqui tem 21 KB.

### Dependências externas (o que cada arquivo espera existir)

| Espera | Vem de | Se faltar |
|---|---|---|
| `React`, `ReactDOM` | UMD 18.3.1 no manifesto | nada renderiza |
| `window.MFG` | item 1 | `TypeError` na primeira leitura de custo |
| `window.I` | item 2 | ícone quebra (`I.plus is not a function`) |
| `window.MfgCampo` | item 3 | ordens/relatório/config não renderizam campo |
| `window.__go(rota)` | shell (`app.jsx` no protótipo; `manufacturing-app.jsx` aqui) | as pontes entre módulos silenciam — o código já protege com `window.__go && window.__go(...)` |

**A família consome o bundle compilado do DS** desde a onda A (2026-09-08): 23 elementos vêm de
`window.OfficeImpressoDesignSystem_49a36f`, carregado por `oimpresso.com.html` L121. O que
continua local está declarado como contorno no §19 do handoff, com o motivo medido em cada caso.
(Até 2026-09-07 esta linha dizia o contrário: nenhum componente compilado era consumido, só os
tokens de `colors_and_type.css`.)

---

## Convenções aplicadas

1. Copy visível em **PT-BR**; chaves de dado e `data-*` em inglês.
2. Cor **sempre** por token ou `color-mix` sobre token — as 3 exceções de cor crua estão
   declaradas no `README.md` §10 e na `adr/0413`.
3. Espaçamento na grade 4/8 (exceções herdadas listadas no `CHECKLIST-15D` Anexo A).
4. Comentário de topo em **todo** arquivo: o que é, o que espelha do legado, o que publica.
5. `Object.assign(window, {…})` ou `window.X = X` na última linha — sempre explícito.
6. Derivados calculados no render, nunca em estado.
7. Número com `font-variant-numeric: tabular-nums` + `--font-mono`. Dinheiro sempre por
   `MFG.fmt()`; quantidade sempre por `MFG.num(valor, casas)`.
8. Sem emoji na UI. Ícone de uma fonte só.
9. `localStorage`: **nada**. A tela não persiste.

---

## O teste do manifesto

Apagar tudo do `Fabricacao - Guia de Producao.html` menos `<meta>`, `<title>`,
`<link rel="stylesheet">`, `<div id="app">` e os `<script src>` — o protótipo continua idêntico.
Não há `<style>`, não há `style="…"`, não há `<script>` com código na página, não há componente
nem dado dentro do HTML.

Uma nota sobre o ponto de mount: o método pede `<div id="root">`; aqui é **`<div id="app">`**
porque a camada 2 (`otimiza-ondas.css`) está escopada em `:where(#app)` — trocar o id desligaria
foco visível, `thead` fixo e alvo de toque sem erro nenhum no console.
