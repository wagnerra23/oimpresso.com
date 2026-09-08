---
id: requisitos-design-system-adr-ui-0033-foreground-de-success-e-warning-vira-cor-de-contraste
---

# ADR UI-0033 · O `-foreground` de `success` e `warning` vira cor de CONTRASTE (corrige 1,41:1 e 1,25:1 no escuro)

- **Status**: proposto
- **Data**: 2026-09-08
- **Ratificação**: merge deste PR por [W]. O escopo ("comece pelo 1") foi autorizado por [M] na sessão
  de 2026-09-08; a decisão de FUNDAÇÃO é [W] e se exerce no merge.
- **Decisores**: Wagner (merge), Claude Code (medição + execução)
- **Categoria**: ui · fundações · tokens · acessibilidade
- **Refs**: [UI-0013](0013-constituicao-ui-v2-camadas.md) (Fundações mudam por ADR) ·
  [UI-0031](0031-fundacao-dark-adota-o-accent-do-prototipo.md) (precedente: fundação escura por ADR) ·
  [ADR 0300](../../../../decisions/0300-errata-0239-nome-real-fonte-design-system.md) (errata 0239 — nome real da fonte do DS) · [ADR 0239](../../../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) (DS em git é SSOT; DTCG é fonte, CSS é saída)

## Contexto — como apareceu

Comparação design × produção da tela `Manufacturing/Index` (`/manufacturing/production`), pedida por [M].
A pergunta era sobre a pílula de situação. Medindo o runtime em produção (tema escuro, Chrome, `getComputedStyle`
**por elemento** — nunca agregado de card, conforme a proibição da skill `comparar-design-prod`):

| pílula | texto sobre fundo | contraste | WCAG AA (4,5:1) |
|---|---|---|---|
| **Finalizada** | `oklch(0.78 0.11 162)` sobre `oklch(0.68 0.13 162)` | **1,41:1** | ❌ |
| Rascunho (controle) | `oklch(0.965 …)` sobre `oklch(0.235 …)` | 15,05:1 | ✅ |

O controle é o que fecha o diagnóstico: o **componente** está certo (`StatusBadge`), o defeito é do **par de cor**.

Varrendo os 7 pares da página no mesmo runtime: `success` **1,41** ❌ · `warning` **1,25** ❌ ·
info 5,86 ✅ · destructive 4,82 ✅ · primary 6,35 ✅ · secondary 15,05 ✅ · muted 7,24 ✅.

## A causa — dois nomes parecidos, um valor copiado

O DS tem DUAS cores de texto por família, para dois fundos diferentes:

- `<fam>-foreground` → texto sobre o fundo **sólido** (`bg-success`). Convenção shadcn: quase-branco ou quase-preto.
- `<fam>-fg` → texto sobre o fundo **suave** (`<fam>-soft`). É um tom da própria cor.

Nas famílias sãs os dois divergem, como devem. Nas duas quebradas eles são **idênticos, caractere por caractere**:

| token | light | dark |
|---|---|---|
| `success-foreground` | `oklch(0.51 0.12 162)` | `oklch(0.78 0.11 162)` |
| `success-fg` | `oklch(0.51 0.12 162)` | `oklch(0.78 0.11 162)` |
| `warning-foreground` | `oklch(0.55 0.12 75)` | `oklch(0.80 0.10 75)` |
| `warning-fg` | `oklch(0.55 0.12 75)` | `oklch(0.80 0.10 75)` |
| info-foreground *(são)* | `oklch(0.98 0 0)` | `oklch(0.20 0.02 244)` |
| info-fg *(são)* | `oklch(0.50 0.13 244)` | `oklch(0.78 0.11 244)` |

O valor do par SUAVE foi escrito no slot do par SÓLIDO.

**Origem datada:** nasceu assim no CSS em `b73f8a4f3` (2026-05-27, [#1772](https://github.com/wagnerra23/oimpresso.com/pull/1772),
*"UI tokens semanticos"*), já como `--color-success-foreground: hsl(143 70% 78%)` no escuro — um verde claro, não uma
cor de contraste. O DTCG (`4b88a7d48`, 2026-06-22, [#3220](https://github.com/wagnerra23/oimpresso.com/pull/3220))
**importou fielmente** o que existia; não introduziu o defeito. Viveu ~3,5 meses.

## Decisão

`success-foreground` e `warning-foreground` passam a ser **quase-preto do próprio hue**, nos dois temas —
o mesmo padrão que `info-foreground` já usa no escuro (`oklch(0.20 0.02 244)`):

| token | antes (light → dark) | depois (ambos) |
|---|---|---|
| `color.success-foreground` | `0.51 0.12 162` → `0.78 0.11 162` | **`oklch(0.20 0.02 162)`** |
| `color.warning-foreground` | `0.55 0.12 75` → `0.80 0.10 75` | **`oklch(0.20 0.02 75)`** |

Resultado medido:

| par | antes | depois | AA |
|---|---|---|---|
| success · claro | 1,58 | **5,28** | ✅ |
| success · escuro | 1,41 | **6,65** | ✅ |
| warning · claro | 1,82 | **6,63** | ✅ |
| warning · escuro | 1,25 | **7,72** | ✅ AAA |

## Alternativa medida e REPROVADA — texto quase-branco

Seria a escolha reflexa (`destructive-foreground` usa quase-branco). Medida antes de decidir, ela **reprova**
em 3 dos 4 casos, porque `success`/`warning` são fundos CLAROS (L 0,62–0,74), diferente do vermelho:

| par | quase-branco `oklch(0.98 0 0)` | veredito |
|---|---|---|
| success · claro | 3,21 | só título grande |
| success · escuro | 2,55 | ❌ |
| warning · claro | 2,57 | ❌ |
| warning · escuro | 2,21 | ❌ |

## O que esta ADR NÃO toca

- **`success-soft`/`success-fg` e `warning-soft`/`warning-fg` ficam intactos** — medidos e sãos:
  4,99 · 7,68 · 4,52 · 8,08. Mexer neles seria "consertar" o que funciona.
- **Nenhum componente muda.** `StatusBadge` e as 120 telas que usam `bg-success`/`bg-warning` herdam a correção
  sem uma linha de código de tela.
- **Não muda o `-foreground` das outras famílias.**

## Consequências

- Toda pílula sólida verde/âmbar do ERP passa a ter texto legível: *Pago, Quitado, Entregue, Aprovada, Recebido,
  Concluído, Emitida, Ativo, Liberada, Finalizada, Sucesso* e *Parcial, Aguardando peças*.
- Mudança **visível**: o texto dessas pílulas deixa de ser colorido e passa a ser quase-preto. É o efeito pretendido.
- Baselines de regressão visual que fotografam essas pílulas vão acusar diferença — é o ganho, não regressão.
- Superfície de token: `version.json` **1.2.0 → 1.3.0** (`~4`), CHANGELOG atualizado pelo gerador.

## Prova

- `npm run tokens:build` → emitiu 4 escopos; diff dos gerados = **exatamente 4 linhas**, nada mais (teste de identidade).
- `npm run tokens:equivalence` → **exit 0** · 311 provados · **0 divergências**.
- `npm run tokens:selftest` → blocos [1][2][3] verdes (`fixture boa rc0`, `proven=311`, `divergences=0`).
  O bloco [4] (`--schema`) sai `rc=2` = **NÃO MEDIDO** por `ajv` da raiz ser 6.15.0 e o script pedir
  `ajv/dist/2020.js` (ajv 8+). É **pré-existente e independente deste diff** — o lock declara a raiz em 6.15.0,
  então o bloco de schema não mede nem em árvore limpa. Registrado aqui, não consertado (escopo alheio).
- `npm run tokens:version:check` → **exit 0** · v1.3.0 em dia.
- Calculadora de contraste validada por **controle positivo**: reproduziu **6 de 6** dos valores que o navegador
  mediu ao vivo em produção, no mesmo número.
