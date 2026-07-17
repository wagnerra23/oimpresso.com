---
slug: 0253-primitivos-layout
number: 253
title: "Primitivos de layout (Components/layout/): Box/Stack/Inline/Grid/Container/Text — a camada que falta entre tokens DS v6 e telas"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-06"
accepted_at: "2026-06-06"
accepted_via: "Wagner 2026-06-06 — 'Maravilha, eu aceito, pode fazer' (após ver preview dos primitivos renderizados via mock fiel DS v6 — roxo oklch 0.55 0.15 295 confirmado)"
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, ds-v6, layout-primitives, componentes, governanca-ui, gap-f3, m-ap-6]
supersedes: []
amends: []
superseded_by: []
related:
  - 0235-ds-v4-accent-roxo-universal
  - 0249-ds-v6-naming-amends-0235
pii: false
---

# ADR 0253 — Primitivos de layout: `Box/Stack/Inline/Grid/Container/Text`

> Fecha o **GAP-F3** do roadmap de design ([`MANUAL-CSS-JS.md §2.1 + §5`](../requisitos/_DesignSystem/MANUAL-CSS-JS.md)).
> Pré-requisito de **governança M-AP-6** ("não inventar componente") — nenhuma linha de
> `Components/layout/` é escrita antes deste ADR ser aceito por [W].

## Contexto

O REGISTRY do projeto cobre primitivos de **UI/form** (`@/Components/ui` — Radix + CVA), mas
**não existe nenhum primitivo de LAYOUT**. Hoje o espaçamento e o arranjo de tela são feitos com
`<div className="flex gap-4 ...">` solto, espalhado por centenas de telas, **ou** dentro de CSS
bespoke (os mega-bundles `cowork-canon-financeiro` e `sells-cowork`). Isso é o **oposto de um
sistema**: a mesma decisão de espaço/tipo é re-tomada por tela, sem contrato.

A auditoria de design **2026-06-06** (MANUAL §2.1) identificou essa como a **única lacuna estrutural
fora do canon de design atual** — identidade e governança já estão resolvidas no SSOT
([`INDEX-DESIGN-MEMORIAS.md`](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md), DS v6 ADR 0235/0249,
git-SSOT ADR 0239). Os melhores design systems (Shopify Polaris, Radix Themes, Vercel Geist, GitHub
Primer) têm uma **camada fina de primitivos de layout** onde *espaço só vem de token*. Nós não temos.

A regra **M-AP-6** ([INDEX §"anti-padrões"](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md))
proíbe "criar componente se não existe" como reflexo — institucionaliza invenção. Por isso a criação
da camada de layout **exige um ADR primeiro**. Este é esse ADR.

## Decisão ([W], 2026-06-06 — aceita)

Criar uma camada fina e fechada de **6 primitivos de layout** em
`resources/js/Components/layout/`, governada pelas regras abaixo:

| Primitivo | Responsabilidade | Referência best-in-class |
|---|---|---|
| `Box` | container neutro; props de espaço/cor **via token** | Polaris Box · Radix Themes Box |
| `Stack` | empilha vertical com `gap` token | Polaris BlockStack · Geist |
| `Inline` | alinha horizontal com `gap` token + `wrap` | Polaris InlineStack |
| `Grid` | grid responsivo por colunas-token | Radix Grid · Polaris Grid |
| `Container` | largura-máx + padding de página | Geist Container |
| `Text` | tipografia 100% via type-scale token (sem `text-[22px]` solto) | Radix Text · Primer |

**Regras duras da camada:**

1. **Wrappers finos Tailwind + CVA.** Cada primitivo é um wrapper sobre `div`/elemento semântico;
   zero `.css` novo (proibido por MANUAL §3 / ADR 0239). Variantes type-safe via CVA + `tailwind-merge`.
2. **Props = tokens DS v6, sempre.** `gap`, `padding`, `align`, `justify`, `columns`, `size` aceitam
   **apenas** valores da escala de token (ex.: `gap="4"`, `padding="6"`), **nunca** px/hex literal.
   Não cria token novo — consome a escala existente (ADR 0235 cor âncora roxo `oklch(0.55 0.15 295)`
   intacta; DS v6 ADR 0249 para os semânticos).
3. **Composição substitui flex solto.** Assim que a camada existir, layout é **composição de
   primitivos** — `<div className="flex gap-4">` repetido e CSS bespoke passam a ser dívida a dissolver.
4. **Acessibilidade:** `asChild` (Radix `Slot`) para não inflar a árvore DOM; `Text` expõe `as`
   (`h1..h6`/`p`/`span`) para semântica correta.
5. **Entra no REGISTRY** quando cada primitivo estiver pronto + documentado no DS.

## Escopo — o que ESTE ADR entrega (e o que NÃO)

**Entrega (Onda F3):**
- a camada `Components/layout/` (começando por `Box`/`Stack`/`Inline`; `Grid`/`Container`/`Text`
  conforme demanda real — anti over-engineering);
- doc no Design System + entrada no REGISTRY;
- **≥1 tela piloto** composta **100% por primitivos** (zero `flex` solto, zero `.css`) como prova viva.

**NÃO entrega aqui (decisão/gate próprio de [W]):**
- **migração das 104 telas** do `shared/PageHeader`/flex-solto → isso é **F4**, sob gate visual
  por-tela (MWART + juiz de design). Este ADR não migra nada visualmente.
- **token novo** — usa só os existentes (DS v6). Se faltar token, abrir ADR à parte.
- **lint que proíbe `flex`/`grid` solto** fora de `Components/layout/` — desejável como enforcement,
  mas fica como **follow-up** (precisa da camada existir + telas migradas antes de cobrar por máquina).

## Alternativas consideradas

- **(a) Adotar Radix Themes / Polaris inteiro como dependência de layout.** Rejeitado: peso +
  acoplamento + diverge da identidade DS v6 própria (cor âncora, type-scale já decididos). Queremos a
  *ideia* (primitivos token-only), não o tema deles.
- **(b) Manter `flex`/`grid` solto + disciplina humana.** Rejeitado: é exatamente a doença atual
  ("diferente = errado" no nível do espaço). Não escala com time MCP entrando.
- **(c) Só um lint anti-`flex`-solto, sem primitivo substituto.** Rejeitado: proibir sem oferecer a
  alternativa canônica só gera atrito. A primitiva tem que existir **antes** do enforcement.

## Consequências

- ✅ Espaço/tipo viram **contrato** (token), não decisão por-tela — fecha a lacuna estrutural do §2.1.
- ✅ Base técnica para **dissolver os mega-bundles** (F5) tela-a-tela com substituto canônico.
- ✅ REGISTRY ganha a camada que falta; M-AP-6 deixa de ser violado "por necessidade".
- ➖ Custo de criar + documentar (incremental: 3 primitivos primeiro, resto sob demanda).
- ➖ Risco de over-engineering de API — mitigado pela regra "só o que tela real pede" + tela piloto.
- 🔜 Enforcement por lint (proibir flex solto) = ADR/PR seguinte, depois da camada + piloto.

## Critério de pronto (validação)

1. `Components/layout/` com ≥3 primitivos (`Box`/`Stack`/`Inline`) tipados (TS strict, sem `any`).
2. **≥1 tela piloto** 100% primitivos — `grep` não acha `className="...flex` solto nem `.css` da tela.
3. `npm run foundation:check` + `npm run conformance:check` verdes.
4. REGISTRY + doc no DS atualizados.

## Refs

- [`MANUAL-CSS-JS.md`](../requisitos/_DesignSystem/MANUAL-CSS-JS.md) §2.1 (a lacuna) · §5 F3 (roadmap)
- [`INDEX-DESIGN-MEMORIAS.md`](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) (SSOT · M-AP-6)
- [ADR 0013 — Constituição UI v2 (4 camadas)](0013-constituicao-ui-v2-camadas.md)
- [ADR 0235 — DS v4 accent roxo universal](0235-ds-v4-accent-roxo-universal.md) · [ADR 0249 — DS v6 naming](0249-ds-v6-naming-amends-0235.md)
- Handoff origem: [`2026-06-06-1650-trilha-css-f6-juiz-f4.md`](../handoffs/2026-06-06-1650-trilha-css-f6-juiz-f4.md)
