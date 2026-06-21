---
slug: 0281-dark-mode-bridge-data-theme-tokens
number: 281
title: "Dark mode ativa por [data-theme=dark] (mecanismo real do AppShellV2), não só pela classe .dark — bridge no inertia.css"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-16"
module: design-system
tags: [ui, dark-mode, tokens, foundations, tailwind, cockpit, constituicao-ui, tier-0]
supersedes: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
---

# ADR 0281 — Dark mode ativa por `[data-theme="dark"]`, não só pela classe `.dark`

## Contexto

O dark mode do app tinha **dois mecanismos desconectados**, e por isso telas inteiras
ficavam **brancas no escuro** (incidente reportado por [W] na Caixa Unificada,
investigação durante a PR #2818):

1. **Camada Cockpit/Cowork** — `foundations.css` e `cockpit.css` redefinem os tokens
   Cowork (`--surface`, `--bg`, `--text`, sombras, atmosfera) sob **`[data-theme="dark"]`**.
   O `AppShellV2.tsx` aplica `data-theme={userTheme}` no `.cockpit` (o `userTheme` vem de
   `auth.user.ui_theme`, server-side). → a **casca** do cockpit escurece.

2. **Camada Tailwind (`@theme`)** — o `inertia.css` redefine os tokens `--color-*`
   (`card`, `background`, `foreground`, `warning-*`, `info`, `destructive-*`, etc.) e a
   variant `dark:` **apenas sob a classe `.dark`**.

O furo: **nada nunca aplica a classe `.dark`** (o `AppShellV2` só seta `data-theme`).
Um comentário stale no `inertia.css` ainda afirmava o contrário ("ativa quando
`<html class="dark">`" + "`data-theme=dark` não é ativado em nenhuma tela"). Resultado
medido ao vivo em produção (`.cockpit[data-theme="dark"]`): `--color-card` resolvia para
`oklch(100%)` (branco) — então toda tela construída com utilities Tailwind
(`bg-card`, `text-foreground`, `bg-warning-soft`, …) ficava **clara dentro de uma casca
escura** (o "miolo branco"). Telas que herdam o visual via vars Cowork escureciam; as
"modernas" tokenizadas, não.

Isto é Fundação (Constituição UI v2 · [ADR UI-0013] — tokens cor/tipo/espaço imutáveis
via ADR), por isso a decisão é registrada aqui.

## Decisão

**Os tokens Tailwind e a variant `dark:` passam a ativar pelos DOIS seletores**, com os
mesmos valores dark já afinados. Duas linhas no `inertia.css`:

- `@custom-variant dark (&:where(.dark, .dark *, [data-theme="dark"], [data-theme="dark"] *))`
- bloco de tokens dark: `.dark, [data-theme="dark"] { … }`

Assim, quando o `AppShellV2` seta `data-theme="dark"` no `.cockpit`, os `--color-*`
flipam e **toda tela Tailwind escurece de verdade** — sem cunhar token novo, sem tocar a
paleta, sem override por-tela. A classe `.dark` (legado shadcn) continua válida para
qualquer contexto fora do `.cockpit`.

## Consequências

**Positivas**
- A Caixa Unificada (e qualquer Page com utilities Tailwind) escurece corretamente.
  A tokenização da PR #2818 (`bg-white`→`bg-card`, âmbar→`warning-*`) passa a ser
  **efetiva** — antes era correta mas inerte no mecanismo real.
- Fix sistêmico de uma fonte única, não 1-por-tela.

**Riscos / pegadinhas**
- Blast radius = app inteiro. Telas que "pareciam ok no escuro" só porque os tokens
  **não** flipavam podem revelar contraste a corrigir (cor clara hardcoded `bg-white`/
  `#fff`/oklch claro cru). **Latente**, não introduzido — agora visível. Spot-check em
  Sells, Financeiro (unificado) e Cliente: escurecem limpo, sem regressão.
- **Portals fora do `.cockpit`** (Radix dialog/popover renderizados no `body`) não herdam
  `[data-theme]` nem `.dark` → permanecem claros no escuro. Pré-existente, fora do escopo
  desta ADR (item separado se virar sinal).
- `foundation-guard`/`conformance-gate` seguem verdes (o `inertia.css` já é arquivo
  token-def allowlisted; só ganhou um seletor irmão).

## Alternativas consideradas

- **Aplicar `.dark` no `<html>` via JS no AppShell** (espelhando `ui_theme`): dual de
  estado (classe + atributo), mais JS, risco de flash. Rejeitada — o bridge de seletor é
  declarativo e reusa o que já existe.
- **Override dark escopado só nos componentes da Caixa Unificada**: zero blast radius, mas
  não-canônico (contradiz tokens), não conserta as outras telas e multiplica dívida.
  Rejeitada.

## Verificação

Probe de injeção em produção replicando o bridge sob `.cockpit[data-theme="dark"]`:
`--color-card` → `oklch(0.208 …)` (escuro), bolha inbound e fundo da thread flipam,
texto legível nos 2 temas; Sells/Financeiro/Cliente conferidos no escuro. Confirmação
final do build real fica no smoke pós-deploy.
