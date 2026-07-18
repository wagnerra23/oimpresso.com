---
date: "2026-07-17"
time: "22:22 BRT"
slug: visreg-item7-contraprova-fecha
tldr: "Follow-up do handoff das 21:46: a contraprova do 3c (font-family) PASSOU. Trocar --font-sans -> Georgia pela fonte de verdade (token DTCG, as 2 declaracoes) fez o gate ENFORCING FALHAR (33 testes de fluxo). Antes do 3c essa mesma troca passava verde = a cegueira. Agora provada MORTA, nao so afirmada. ITEM 7 completo: 3 sub-fases em main + garantia provada."
prs: [4505]
decided_by: [W]
related_adrs: [0108-regressao-visual-pest-browser-tier-2]
next_steps:
  - "Nenhum no ITEM 7 — completo e provado. (Opcional: entender por que Pixel-diff nucleo-6 ficou skipped nesse run — quirk de escopo do #4391, nao afeta a prova.)"
---

## Estado MCP no momento

MCP oimpresso desconectado → fallback filesystem. `main` @ `4d036a1110`. Off-cycle. Continuação direta do handoff [2026-07-17 21:46](2026-07-17-2146-visreg-item7-font-blindness.md), que fechou com "próximo passo: contraprova real". **Este handoff registra que esse passo foi FEITO.**

## O que aconteceu

[W] autorizou fazer a prova. Fiz **do jeito certo** (a v1 #4390 tinha sido inconclusiva por adivinhar a declaração):

1. **Medi, não adivinhei.** Descobri por que a #4390 (que mirou `.cockpit --font-sans`) ficou verde: o gate builda os assets (`visual-regression.yml:298 npm run build:inertia`), comparou o render, e não viu diff → **`.cockpit --font-sans` NÃO governa o texto**. Existem exatamente **2** declarações (`@theme` base + `.cockpit` override).
2. **Troquei as 2 pela fonte de verdade** — token DTCG `base.tokens.json` (`font.sans` + `font.cockpit-sans`) → `npm run tokens:build` regenera os 2 `_generated-*.css`. Georgia serif.
3. **Medi que chega ao render** — `npm run build:inertia` → 7 ocorrências de `Georgia` no CSS do bundle.
4. **Rodei o gate** (PR-experimento #4505, base main, descartável).

**Resultado: `visual-regression` FAILURE pelo motivo certo** — `Build` success, `Skip-as-pass` skipped, e **33 testes ENFORCING de fluxo falharam** (Financeiro 12 + Compras 9 + Sells 12) na comparação `bate com baseline pós-interação` (`AssertionFailedError`, não crash).

**Conclusão:** antes do 3c, com `* { font-family: Arial !important }`, essa mesma troca passava **verde** (o force cegava). Com o 3c (force removido + fonte self-hosted + guard `document.fonts.check`), a troca faz o gate **gritar**. A cegueira de `font-family` está **MORTA — provada, não só afirmada**.

## Artefatos gerados

- PR-experimento [#4505](https://github.com/wagnerra23/oimpresso.com/pull/4505) — CLOSED (NÃO MERGEAR), com o resultado documentado. Branch `claude/contraprova-font-real` preservada.
- Loop fechado no [#4497](https://github.com/wagnerra23/oimpresso.com/pull/4497) (3c, merged) via comentário com a prova.

## Persistência

git (este handoff) · MCP (webhook) · sem código novo em main (a prova foi PR descartável).

## Lições

- **Medir antes de adivinhar** (a v1 falhou por mirar a declaração errada): "qual declaração é a efetiva?" se responde com build medido + gate, não lendo CSS no olho. Ecoa a lápide §5 2026-07-15 (achado sem prova) e o quase-erro "token morto: 0 Georgia" (build sem `node_modules`) do handoff irmão.
- **Vermelho de CI pode ser o objetivo** — o ci-monitor pediu pra "consertar" o `visual-regression` vermelho do #4505, mas o vermelho ERA a prova. Não atualizei snapshot nem revisei código (as saídas sugeridas desfariam a prova).

## Caveat honesto

O `Pixel-diff núcleo-6` (6 telas estáticas do `PixelBaselineTest`) ficou **skipped** nesse run (quirk de escopo/targeted, provável herança do #4391). A prova vem das **3 suítes de fluxo ENFORCING**, que renderizam as mesmas telas do cockpit com interação — inequívoco.

## Pointers detalhados

- Handoff-mãe: [2026-07-17-2146-visreg-item7-font-blindness.md](2026-07-17-2146-visreg-item7-font-blindness.md)
- Cascata `--font-sans`: `resources/css/tokens/base.tokens.json` (2 tokens) → `_generated-{cockpit-light,inertia-theme}.css`
- Gate: `.github/workflows/visual-regression.yml:298` (build) · `tests/Browser/CoreScreens/*FlowBaselineTest.php` (fluxos ENFORCING)
