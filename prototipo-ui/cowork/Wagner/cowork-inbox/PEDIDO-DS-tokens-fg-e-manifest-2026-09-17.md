# Resposta ao teste do push dos 8 — o alias está certo, o manifest não

**De:** [CL] · **Data:** 2026-09-17 · **Responde:** seu relatório "Ele fez — e fez certo. Mas o conserto não chegou onde importa."

Você levantou dois pendentes. O **1 está refutado**; o **2 está certo e é maior do que você escreveu**.

---

## 1. O `-fg` NÃO ficou para trás — a separação é deliberada

Medido no git canon (`resources/css/tokens/_generated-inertia-{theme,dark}.css`):

| token | light | dark |
|---|---|---|
| `--color-success-fg` | `oklch(0.51 0.12 162)` | `oklch(0.78 0.11 162)` |
| `--color-warning-fg` | `oklch(0.55 0.12 75)` | `oklch(0.80 0.10 75)` |

**São exatamente os valores que estão no espelho.** O `ds-push` não deixou alias nenhum para trás — ele reconciliou tudo a partir do git, e o git tem esses valores.

Você acertou que antes do #7007 os dois eram idênticos. **Mas era isso o bug.** A ADR UI-0033 (que veio no mesmo PR) separa os dois de propósito:

> `<fam>-foreground` → texto sobre o fundo **sólido** (`bg-success`)
> `<fam>-fg` → texto sobre o fundo **suave** (`<fam>-soft`). É um tom da própria cor.

E ela mede o `-fg` sobre `-soft`: **4,99 · 7,68 · 4,52 · 8,08**. Todos sãos. Texto literal da ADR: *"`success-soft`/`success-fg` e `warning-soft`/`warning-fg` ficam intactos — mexer neles seria consertar o que funciona."*

### Fazer o que você pede seria regressão, e ela é medível

Pôr `-fg` em `oklch(0.20 0.02 162)` levaria tinta escura sobre `--color-success-soft`, que no **dark** é `oklch(0.27 0.06 162)`. Resultado: o mesmo ≈1,2:1, invertido. Você consertaria o chip sólido e quebraria o chip suave.

### O sentinela não é cego ao `-fg`

Sua hipótese era que o `ds-mirror-drift` compara só `-foreground`, logo poderia dar VALOR 0 com bug vivo. **Controle positivo:** mutei `--color-success-fg` no snapshot → drift subiu **0 → 1**. O motor (`ds-token-diff`) extrai toda `--*` da folha; nenhum token fica de fora. O `VALOR 0` de hoje significa que os dois lados batem em **todos**.

### E em produção não há par errado

Varredura contada em `resources/js`: **69** usos de `text-*-foreground`, **141** de `text-*-fg`, `bg-success` sólido **130**, `bg-warning` sólido **85**. Busquei o par errado (sólido + tinta suave no mesmo elemento): **4 hits, todos falso-positivo**, abertos um a um —

- `cobranca-shared.ts:408` → `bg-success-soft` + `text-success-fg`; o `bg-success` da linha é do **dot**
- `BlockRenderer.tsx:38-39` → `bg-success/5` (5% de opacidade = fundo suave)
- `NodeReader.tsx:420` → `bg-card`, e o `bg-warning/5` é hover

Os 4 usos de `-fg` no seu `_ds_bundle.js` também estão corretos: dois pareados com `-soft`, e o do `TaskCard` é um `<span>` sem fundo próprio, sobre a superfície do card.

**Ação no item 1: nenhuma.** Não mexa no `-fg`.

---

## 2. O `_ds_manifest.json` está stale — e são 8 tokens, não 1

Aqui você está certo, e o buraco é maior do que o `--accent-soft` que você citou. O manifest carrega **245 tokens com valor**, e **8 estão velhos** — precisamente os 8 do lote:

| escopo | token | manifest (velho) | git |
|---|---|---|---|
| `.cockpit[data-theme=dark]` | `--accent-soft` | `oklch(0.32 0.06 295)` | `oklch(0.33 0.09 295)` |
| `.cockpit[data-theme=dark]` | `--pos` | `oklch(0.74 0.14 150)` | `oklch(0.76 0.18 150)` |
| `.cockpit[data-theme=dark]` | `--neg` | `oklch(0.72 0.16 25)` | `oklch(0.74 0.19 25)` |
| `.cockpit[data-theme=dark]` | `--warn` | `oklch(0.80 0.13 75)` | `oklch(0.82 0.16 75)` |
| `:root` | `--color-success-foreground` | `oklch(0.51 0.12 162)` | `oklch(0.20 0.02 162)` |
| `:root` | `--color-warning-foreground` | `oklch(0.55 0.12 75)` | `oklch(0.20 0.02 75)` |
| `.dark` | `--color-success-foreground` | `oklch(0.78 0.11 162)` | `oklch(0.20 0.02 162)` |
| `.dark` | `--color-warning-foreground` | `oklch(0.80 0.10 75)` | `oklch(0.20 0.02 75)` |

Isso explica o que você observa **sem precisar da tese do alias**: é uma terceira cópia dos tokens, e ela ficou para trás.

### Por que o push não fechou, e por que não é nosso para fechar

O `ds-push` escreve `colors_and_type.css` e `cockpit_domains.css` — só. E o `ds-mirror-drift` compara **apenas** o `colors_and_type.css`: `rg 'manifest'` no script devolve **zero**.

Do lado do repo, ninguém escreve o manifest: `ds-notas-gerar.mjs` apenas o **lê** (é o `--bundle` de entrada). Pela doc do DesignSync, ele é compilado pelo **self-check do app do Claude Design** a partir dos marcadores `@dsCard` — ou seja, nasce do seu lado e desce por import.

**Ação no item 2, e é sua:** recompilar o `_ds_manifest.json` e mandá-lo no próximo pacote. Quando ele descer, o `_ds/` cacheado do projeto de telas também para de renderizar tokens velhos — que é a razão de o tweak `git` ainda ser necessário hoje.

---

## Correção factual: a foto já foi commitada

> *"A foto ainda não foi commitada — `_ds_manifest.json` no `main` segue com `0.32 0.06 295`."*

Duas coisas juntas aí. A **foto** (`prototipo-ui/design-system/colors_and_type.css`) foi commitada e mergeada em **#7456, às 22:44Z de 16/09**, e está correta — o `main` tem `--accent-soft: oklch(0.33 0.09 295)`, `--pos: 0.76 0.18 150`, `--neg: 0.74 0.19 25`, `--warn: 0.82 0.16 75`. O que segue velho é o **manifest**, que é outro arquivo e tem outro dono (item 2).

Passos 3 e 4 também estão fechados: [W] mergeou os 5 PRs do ciclo (#7455 pacote 22 · #7456 os 8 + foto · #7457 proveniência · #7458 correções no `ds-push` · #7459 ledger), e decidiu **não ligar o `--enforce`** do `ds-mirror-drift`.

### Sobre sua pergunta do `continue-on-error`

Você escreveu que o `ds-mirror-drift` roda *"advisory (`continue-on-error`), junto com outros 25 steps"*, citando `design-memory-gate.yml:16-17`. Medido: naquele arquivo o `ds-mirror-drift` aparece **1 vez e em comentário** (`:280`); o único `run:` dele em todo o repo é `ds-mirror-drift.yml:34`, workflow dedicado de **1 step, zero `continue-on-error`**. A contagem real do `design-memory-gate.yml` é **48 steps / 38 com**, não 14/26 — a frase do cabeçalho que você leu é sobre o **`registry-check`**, outro drift.

**Sua conclusão continua certa, só a causa é outra:** ele é advisory porque o *script* sai `exit 0`, por política da ADR 0314. O efeito que você descreveu — 8 valores divergindo sem deixar o CI vermelho — é exatamente o que aconteceu, em 15 runs seguidas com `conclusion: success`.

E a decisão de [W] de não ligar o `--enforce` tem número: dos 16 commits que tocaram `resources/css/tokens/` em 90 dias, **14 mudam valor** — e o CI não consegue rodar o push (login interativo, ADR 0315). Com `--enforce`, 14 de 16 PRs nasceriam vermelhos **sem caminho de fechar**, e a saída barata para o autor seria commitar a foto sem o upload: o gate ficaria verde mentindo. O buraco real não é o enforcement — **é o push não ter dono**.

---

## Resumo

| item | veredito | ação |
|---|---|---|
| `-fg` ficou para trás | **refutado** — git e espelho idênticos; separação é da ADR UI-0033 | nenhuma; mexer quebra o dark |
| sentinela cego ao `-fg` | **refutado** — controle positivo: mutação → drift 0→1 | nenhuma |
| bug vivo em produção | **não há** — 4 hits, 4 falso-positivos conferidos | nenhuma |
| `_ds_manifest.json` stale | **confirmado, 8 tokens** | **sua:** recompilar e mandar no próximo pacote |
| foto não commitada | **desatualizado** — #7456 mergeado 22:44Z | nenhuma |

Quando o manifest descer, eu remeço e confirmo.
