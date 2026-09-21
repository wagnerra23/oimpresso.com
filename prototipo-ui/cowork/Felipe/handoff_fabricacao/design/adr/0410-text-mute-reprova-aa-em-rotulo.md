# ADR 0410 — `--text-mute` reprova contraste AA em texto pequeno, nos dois temas

**Data:** 2026-09-01 · **Status:** proposto (aguarda decisão) · **Camada:** 1 · Fundações
**Origem:** conferência da família de telas Fabricação (Manufacturing)

## Contexto

`--text-mute` é o token de "texto terciário" do `.cockpit`. A família Fabricação o usa em seis
lugares, todos com fonte de 10 a 11,5px — nenhum é texto grande pela WCAG (que exige ≥18,66px
bold ou ≥24px regular):

| Elemento | Tamanho | Arquivo |
|---|---|---|
| Rótulo de coluna (`.mfg-th`, `.mfg-th.sort`) | 10px, uppercase, tracking .07em | `manufacturing.css` |
| Rótulo de KPI (`.mfg-kpi-l`) | 10px, uppercase | idem |
| Código / SKU (`.mfg-sku`, `.mfg-ing .n small`) | 10–10,5px, mono | idem |
| Unidade ao lado do número (`.mfg-u`) | 10,5px | idem |
| Rótulo de campo (`.mfg-fld > span`) e dica (`.mfg-fld small`) | 10 / 10,5px | idem |
| Meta da trilha (`.mfg-crumb-meta`) | 11px | idem |

Medição (WCAG 2.1, sRGB, tokens de `_ds/…/colors_and_type.css` L274-340):

| Par | Razão | Mínimo | Veredito |
|---|---|---|---|
| `--text-mute` sobre `--surface` · tema claro | **3,24:1** | 4,5 | reprova |
| `--text-mute` sobre `--bg-2` · tema claro | **2,92:1** | 4,5 | reprova |
| `--text-mute` sobre `--surface` · tema escuro | **3,18:1** | 4,5 | reprova |
| `--text-mute` sobre `--bg-2` · tema escuro | **3,94:1** | 4,5 | reprova |
| `--text-dim` sobre `--surface` · claro / escuro | 6,00 / 5,49:1 | 4,5 | aprova |
| `--text-dim` sobre `--bg-2` · claro / escuro | 5,42 / 6,80:1 | 4,5 | aprova |

Valores dos tokens: claro `--text-mute: oklch(0.65 0.01 80)`, `--text-dim: oklch(0.50 0.01 80)`;
escuro `--text-mute: oklch(0.58 0.005 90)`, `--text-dim: oklch(0.72 0.005 90)`.

O contexto agrava: é justamente o texto que **nomeia a coluna de dinheiro**, o **código do
insumo** e a **unidade da quantidade**. A operadora do balcão trabalha sob luz de loja, não em
monitor calibrado — e um SKU lido errado troca o insumo do lote.

**Este é o terceiro registro do mesmo token** em telas diferentes (ver ADR 0322, aberta na família
de Venda, e `contexto/pauta-design-system.md`). Três correções para o mesmo token é a evidência de
que a correção pertence ao DS, não às telas.

## Decisão

**Nesta tela (proposto, não aplicado):** trocar `--text-mute` por `--text-dim` nos seis usos da
tabela acima. É troca de token, não cor nova, e não muda uma linha de layout.

**Nas fundações (proposto):** ou `--text-mute` recebe valor que passe 4,5:1 nos dois temas, ou o
DS declara no guia que `--text-mute` **não é para texto** (só para ícone de chrome, divisor,
placeholder) e as telas param de usá-lo em rótulo.

**Não aplicado dentro da tela sem decisão.** O valor do DS foi aplicado como está; a medição está
aqui e no LAUDO.

## Consequências

- **Se aprovado no DS:** a tela não muda — o token melhora sozinho, e o mesmo acerto vale para
  Clientes, Inbox, Venda e todas as consultas.
- **Se aprovado só na tela:** seis linhas de CSS; hierarquia visual fica levemente mais chapada
  (rótulo e valor ficam mais próximos em peso de cor).
- **Se não aprovado:** a família nasce com um achado ALTA de acessibilidade em aberto, e o alvo
  herda o mesmo problema, porque o token é o mesmo.

## Referências

- `design/LAUDO-conferencia-fabricacao.md` §4 (tabela de contraste completa)
- ADR 0322 — mesmo token, família de Venda
- `contexto/pauta-design-system.md`
