---
sessao: "07b"
titulo: ACABAMENTO · regras de CSS que nunca chegaram a prod (desdobramento da thread 07)
autor: "[C]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main bbfe763373c8
---

# _saida-07b · Regras do port do Financeiro que o navegador descartava

Este recibo nasceu da thread 07 (drawer): o `.fin-anomaly` da aba IA não aparecia em prod. A causa
não era do protótipo, era do **port**. O `scope-fin-cowork-css.py` escrevia o banner
`/* SCOPED-OK */ - escopo …`, e o texto depois do `*/` virava prefixo do seletor da 1ª regra de cada
arquivo. O navegador descartava essa regra inteira.

**Nada muda no protótipo.** O original do Cowork não tem esse banner. Este recibo existe para o
lado do design saber o que o produto passou a renderizar, e o que ele ainda não renderiza.

## Feito (mergeado no `main`)

| PR | arquivo | o que volta a valer | efeito medido em prod |
|---|---|---|---|
| #7970 | `fin-ia.css` + gerador | `.fin-anomaly` (base) | thread 07 |
| #7972 | `fin-cowork.css`, `fin-curadoria.css` | `.fin-page-h`, `--fin-*` da curadoria | 0 elementos (claro e escuro) |
| #7973 | `fin-output.css` | `.fin-xlink` (base da pílula de referência cruzada) | a pílula fica com 18px de altura, raio 4 e fonte mono 10,5px. A linha da lista que tem referência cruzada cresce 0,875px |
| #7974 | `cowork-canon-financeiro-bundle.css` | bloco de tokens **mantido desligado** | 0 elementos |
| #7975 | `sells-cowork.css` + gerador | bloco de tokens **mantido desligado** | 0 elementos |

**Por que os dois blocos de tokens ficaram desligados.** Eles trazem os valores **claros** do
Cowork (`--text .22`, `--surface #fff`). No Cowork, o escuro vem de um `[data-theme]` **dentro** do
app. No produto, o `data-theme` fica no `.cockpit`, que é **ancestral**. Reinseridos em prod, no
tema escuro, eles:

- em `/financeiro/unificado`: mudariam 24 elementos, deixando o H1 com texto `.22` sobre fundo
  escuro;
- em `/sells`: mudariam 7.361 propriedades, deixando a página inteira clara.

No tema claro, os dois não mudam nada. Por isso receberam o seletor
`[data-tokens-cowork="ligado"]`, que nada define. Ligá-los de verdade exige antes reancorar o
escuro em `.cockpit[data-theme="dark"]`.

## Descoberta — o bundle do Financeiro inteiro nunca foi importado

Ao conferir o deploy, a regra desligada do #7974 também não estava no CSS servido. Medi as outras:
das **1.238 regras do `cowork-canon-financeiro-bundle.css`, nenhuma chega a prod**. As 31 que
"aparecem" têm homônimas em `fin-mobile.css`, `fin-cowork.css` ou `fin-output.css`.

**Causa** (reproduzida com o `compile` do `@tailwindcss/node`, que emite a mesma "Invalid dangling
combinator"): na linha 92 do `resources/css/inertia.css`, dentro de um comentário, há o texto
`` `/* SCOPED-OK */` ``. O `*/` desse marcador fecha o comentário grande antes da hora. O resto do
comentário, junto com o `@import "./cowork-canon-financeiro-bundle.css";` logo abaixo, vira um
seletor inválido, e o build o descarta. O `compile` confirma: o bundle não aparece nas dependências.
Isso acontece desde 2026-05-19, e é o que o comentário da Onda 22b registrava como *"os vars do
bundle não sobreviveram ao build"*.

**Impacto de consertar**, medido só em `/financeiro/unificado`, com o bundle inteiro reinserido
na posição dele na cascata:

- escuro: **128 elementos** mudam, quase só cor, e o fundo da raiz vai a `0.16`;
- claro: **13 elementos** mudam (altura, display, largura).

As outras telas com `.fin-cowork` não foram medidas. **Não está consertado.** Ligar o bundle muda
a forma de um módulo que passou 4 meses evoluindo sem ele, e vai para [W] como decisão, com
medição tela a tela antes.

## Como foi medido

- **Parse:** `CSSStyleSheet.replaceSync` no Chromium, sobre o arquivo real de cada branch (raw do
  GitHub por SHA).
- **Impacto:** cada regra reinserida na posição original da cascata do CSS servido, comparando
  `getComputedStyle` com duas leituras, nos temas claro e escuro. O claro foi medido replicando o
  `applyClass` do `useTheme` no DOM, sem gravar preferência.
- **Deploy:** com o `9225e53f6` no ar, estão presentes `.fin-page-h`, `.fin-xlink`, `.fin-anomaly`,
  o `--fin-*` da curadoria e a regra desligada do Sells. A raiz do Financeiro segue com
  `--text .94` no escuro.
