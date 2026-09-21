---
date: "2026-09-21"
time: "17:30"
slug: "grade-analises-3-colunas-e-a-tabela-que-mentia"
tldr: "A grade de Análises do Painel da Jana foi a 3 colunas (gap 12px) e o bloco de Ações perdeu o respiro de 24px — medido em prod pós-deploy, idêntico à âncora. Três itens do enunciado estavam caducos: o h2 fechara em 18/09, e a tabela de 09-07 sem nota de fechamento fez TRÊS sessões pedirem conserto de código correto."
decided_by: ["W"]
prs: [7638]
related_adrs:
  - "0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias"
next_steps:
  - "Decisão [W]: margin-bottom 18px (âncora) × 16px (prod) — vem do space-y-4 do container, rege TODAS as seções"
  - "Sessão dos gráficos rebaseia e re-mede o G16 na grade nova (o svg 40px → 60px é dela)"
---

# A grade de Análises foi a 3 colunas — e a tabela que mandava consertar o que já estava certo

**1 PR mergeado pelo [W]** ([#7638](https://github.com/wagnerra23/oimpresso.com/pull/7638), 130 pass · 0 fail),
**em produção e medido lá**.

## O que entregou

A grade de Análises do Painel (`/ia`) converge ao protótipo: **3 colunas** (era 2), **gap 12px**
(era 16px), e o bloco de Ações perde o respiro de 24px. **36 linhas de código**; o resto é o
UC-JPAIN-31 (149) e os dois donos de documentação da tela (138).

**Smoke real pós-deploy** em `oimpresso.com/ia`, sem injeção: `737.656px 737.672px 737.672px` ·
gap `12px` · ações `padding: 0px` · h2 `700 11px ls 0.88px`. **Byte a byte igual à âncora.**

## As três coisas que a medição corrigiu no enunciado — e uma era gatilho de regressão

1. **O `h2` já estava fechado desde 2026-09-18** (UC-JPAIN-27). O enunciado pedia
   `14px/600/1.4px → 11px/700/0.88px`; medido em runtime, os dois h2 batiam **8 de 8** com a
   âncora. Aplicar teria derrubado o UC-JPAIN-27 e respingado em METAS e Ações — **os três h2 são
   o mesmo `SectionTitle`**.
2. **O `gap: 24px` das Ações era o sintoma, não a causa.** O `Card` tem **um** filho, e gap sem
   segundo filho não separa nada. Quem produzia o respiro é o `py-6` do `Card` canon. A rodada de
   09-07 mediu certo e nomeou a propriedade inerte.
3. **Os breakpoints não podiam ser os do Tailwind.** A âncora quebra em **1100px** e **760px**;
   `lg:` é 1024px — com ele a prod parava em 2 colunas no monitor de **1280px** da ROTA LIVRE,
   onde a âncora já mostra 3.

## A causa comum: a tabela de 09-07 guarda o ANTES e não ganhou nota de fechamento

O `Index-visual-comparison.md` **se contradizia dentro do mesmo arquivo**: a linha do `h2 ações`
tinha `✅ (2026-09-18)`, a do `h2 análises` seguia `❌ DIVERGE`, e as duas descrevem o mesmo
componente. **Três sessões diferentes leram a linha caduca hoje** e pediram conserto de código
correto — uma retratou-se por escrito depois de a sessão do KPI medir. Corrigido no `f8c2107e3c2`,
preservando o fato datado (*"era 14px · 600 · 1.4px"*) e trocando só o veredito.

É a §5 2026-09-03 na prática: **tabela preserva o FATO do dia, nunca o ESTADO de hoje** — e o custo
de não anotar o fechamento foi 3 rodadas perdidas num único dia.

## O que ficou aberto, medido e declarado (decisão [W])

**`margin-bottom` 18px (âncora) × 16px (prod).** O 16px **não é da grade**: vem do `space-y-4` do
container da página, logo rege **todas** as seções (KPIs, Metas, Análises, Ações). Convergir muda o
ritmo vertical da tela inteira e toca território de 4 chips vivos. Ficou fora do PR de propósito.

**Cor não foi medida no lado da âncora**, e não é veredito omitido: `--text-3` resolve para
`var(--text-mute)`, não declarado no escopo, então tudo computa preto naquele render. Layout e
tipografia carregaram (valores não-default) — é só sobre eles que o PR afirma.

## Coordenação: 5 sessões no mesmo arquivo

`whats-active` revelou **5 sessões vivas** tocando `JanaCockpit.tsx` (KPI, gráficos, Metas, `h1`,
gating-pro). Declarei escopo às 5 **antes** do primeiro Edit; nenhuma colidia — mas **três
trouxeram correção que mudou meu trabalho**, e uma trouxe o achado do `Sparkline` (mora no `window`
do shell, `chat-jana.jsx:271`, não no `jana-merge.jsx`) que eu não teria encontrado.

**Colisão de `UC-JPAIN-30`** com a sessão do `h1`: medimos unicidade no mesmo dia, as duas vimos 29
como máximo, e o PR dela abriu primeiro. **Cedi** (fiquei com 31), porque reverter id em PR
publicado custa mais que renumerar localmente. É a **2ª vez** nesta tela — a nota do UC-JPAIN-27 já
dizia por quê: *a checagem responde pelo INSTANTE, e o id só está livre quando o PR entra*. Com 5
sessões simultâneas, deixou de ser exceção.

## Duas armadilhas de instrumento que o controle pegou

- **`resize_window` reportou sucesso e foi inerte** (`outerW` seguiu 2563; janela maximizada em
  monitor 3840). Em vez de aceitar o número, igualei os dois lados na mesma janela — daí a medição
  ter saído em 2560, não nos 1440 da rodada anterior.
- **`grep` no CSS de prod deu 0 para `min-width:1101px`** — e o **controle positivo também deu 0**
  num arquivo de 13.659 bytes contra 617.497 do local. Era o chunk do `/login`, não o CSS de
  `/ia`: **cegueira do instrumento, não ausência da regra**. Sem o controle eu teria reportado "a
  regra não chegou ao deploy". A prova válida foi medir a tela.

## Para a próxima sessão

- **Não mexa no `SectionTitle`** sem re-medir: serve 3 seções e está travado pelo UC-JPAIN-27.
- **Antes de tratar "prod diverge do token do DS" como dívida**, pergunte se a âncora daquela tela
  **declara** ou **herda** o valor — o token só governa o caso `herda`. Dois casos independentes
  hoje (a `.jc-h2` da Jana e a âncora de Vendas, da sessão do `h1`) apontam a mesma estrutura.
- **A sessão dos gráficos** vai subir o `<svg>` de 40px → 60px: com 3 colunas os cards ficam mais
  estreitos **e** mais altos. O `visual-regression` do #7638 passou sem rebake, então o rebake que
  sobrar é do PR dela.
