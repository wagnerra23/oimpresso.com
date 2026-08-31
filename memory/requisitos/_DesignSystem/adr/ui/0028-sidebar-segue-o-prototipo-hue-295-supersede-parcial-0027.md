---
id: requisitos-design-system-adr-ui-0028-sidebar-segue-o-prototipo-hue-295-supersede-parcial-0027
---

# ADR UI-0028 · O sidebar segue o PROTÓTIPO (hue 295) — supersede parcial da UI-0027

- **Status**: accepted
- **Data**: 2026-08-28
- **Aprovado em**: 2026-08-28 — [W] decidiu, e repetiu depois de eu ter voltado a perguntar:
  *"eu quero o prototipo e nunca fica igual"* · *"ui 27 deve estar errada e deve ser revogada"* ·
  *"a cor igual ao do protótipo, qual parte igual ao protótipo não está compreendendo […] elimine
  as coisas que estão proibindo"*.
- **Decisores**: Wagner (decisão), Claude Code (medição + redação)
- **Categoria**: ui · fundações · tokens · sidebar
- **Supersede parcial**: [UI-0027](0027-dark-hue-240-supersede-0020-0022.md) — **somente** os tokens
  `--sb-*` do shell. O resto dela (o dark do APP: `--bg`, `--surface`, `--border`, `--text`,
  `--text-dim`, `--text-mute`, e as cores funcionais) **permanece vigente e intocado**.

## Contexto — por que a UI-0027 precisava de sucessora, e não de revogação inteira

A UI-0027 (aceita hoje, mais cedo) fixou o dark do app em superfícies hue **240** e textos hue
**90**, e incluiu os `--sb-*` do sidebar nesse regime. Ela numerou uma escolha que [W] fez **por
imagem** em 2026-07-08.

O que a medição desta sessão trouxe de novo — e que não existia quando aquela escolha foi feita:

**1. As duas fontes de design discordam entre si.** Medido no render do protótipo, via
`document.styleSheets`: há **duas** folhas declarando `--sb-*`.

| Fonte | Seletor | `--sb-bg` | `--sb-text` |
|---|---|---|---|
| `colors_and_type.css` — projeto **Design System** | `.cockpit` | `oklch(0.18 0.006 240)` | `oklch(0.78 0.005 90)` |
| `styles.css` — protótipo **de tela** | `:root` | `oklch(0.21 0.025 295)` | `oklch(0.80 0.008 295)` |

**2. A divergência tem causa nomeável.** `prototipo-ui/cowork/styles.css:3`, literal:

```
/* Sidebar dark — espelho AppShell · tingido p/ a marca
   (roxo canon hue 295, ADR 0235) em vez de preto neutro croma-0 */
```

O protótipo tingiu os **neutros** do sidebar citando a
[ADR 0235](../../../../decisions/0235-ds-v4-accent-roxo-universal.md), que governa o **accent**
(título: *"accent oklch 0.55 0.15 295"*, croma **0.15**). Os valores do protótipo têm croma
0.008–0.05 — neutro tingido, não accent. **Uma regra cruzou a fronteira da família de token da
outra.**

**3. A fidelidade foi medida, não estimada.** Sonda idêntica nos dois lados, mesmo tema (dark),
espelho fresco: **47 divergências** entre produção e protótipo no shell — 18 no atalho de topo, 15
no header de grupo, 14 no item de grupo. **6 delas são de cor.**

## Decisão

**D-1 — Os 8 tokens `--sb-*` que o protótipo declara passam a ser os do protótipo.**

```
--sb-bg        oklch(0.21 0.025 295)     --sb-text-dim  oklch(0.55 0 0)
--sb-bg-2      oklch(0.18 0.025 295)     --sb-text-hi   oklch(0.97 0.004 295)
--sb-border    oklch(0.30 0.03  295)     --sb-hover     oklch(0.28 0.035 295)
--sb-text      oklch(0.80 0.008 295)     --sb-active    oklch(0.34 0.05  295)
```

Fonte: `prototipo-ui/cowork/styles.css`. Copiados **exatamente**, sem arredondar nem reinterpretar.

**D-2 — A fronteira da supersessão é o prefixo `--sb-`.** A UI-0027 continua sendo a lei do dark do
APP. Só o shell muda de regime. Isso evita reabrir a UI-0022 e a UI-0020, que a UI-0027 superseded —
revogá-la inteira as ressuscitaria sem que ninguém tenha pedido isso.

**D-3 — `--sb-scroll` e `--sb-bullet-out` ficam como estão (hue 240).** O protótipo **não os
declara**, e inventar valor pra eles seria alucinar fonte. Consequência honesta: a scrollbar e os
bullets ficam em hue 240 ao lado de um shell em 295. É resíduo **declarado**, não esquecido — some
quando o protótipo declarar os dois.

**D-4 — Quando as duas fontes de design divergirem, o protótipo de tela vence no que ele declara.**
É a regra que faltava e que produziu meses de "nunca fica igual": não havia precedência escrita
entre `prototipo-ui/cowork/styles.css` e o projeto Design System, então cada alinhamento escolhia um
lado e parecia errado contra o outro. Escopo desta regra: **tokens de shell**. Ela não decide nada
sobre o dark do app nem sobre cores funcionais.

## Consequências

- **O Design System passa a divergir de produção nos `--sb-*`.** É o preço explícito desta decisão,
  e a direção certa de conserto é o DS adotar o 295 — não produção voltar. Registrado como dívida.
- **A UI-0027 vira parcialmente histórica** no que toca ao sidebar. Ela **não** é revogada: o
  corpo dela sobre o dark do app continua sendo a lei, e o raciocínio dela sobre o split-brain
  continua correto — o que mudou foi qual lado do split vence.
- **Toda baseline visual precisa ser regenerada.** O sidebar rende em toda tela.
- **Métrica e cor agora vêm da mesma fonte.** Antes deste PR a métrica seguia o protótipo e a cor
  seguia o DS — o que é, em si, uma incoerência que ninguém tinha nomeado.

## O que esta ADR NÃO decide

- O dark do app (`--bg`, `--surface`, `--text`…) — segue na UI-0027.
- Cores funcionais, charts, personas, `--color-primary` — seguem intocadas.
- Se o Design System deve adotar o 295: é decisão de quem cuida do DS, e precisa de ADR própria.
- Os outros 41 itens das 47 divergências medidas (caixa, tipografia, ícone) — tratados como
  alinhamento de métrica no mesmo PR, sem mudar regime de token.

## Refs

- [UI-0027](0027-dark-hue-240-supersede-0020-0022.md) — supersedida em parte (só `--sb-*`)
- [UI-0023](0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) — sidebar dark-fixo nos dois
  modos: **vigente e não afetada** (esta ADR muda o hue, não o fato de ser dark)
- [ADR 0235](../../../../decisions/0235-ds-v4-accent-roxo-universal.md) — accent roxo; é a ADR que o
  protótipo citou fora do escopo dela
- [ADR 0374](../../../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md) — o espelho
  `prototipo-ui/cowork/` é read-only; a raiz é o Cowork vivo
- [UI-0013](0013-constituicao-ui-v2-camadas.md) — Fundações mudam por ADR; é o motivo desta existir
