# ADR UI-0015 (_DesignSystem) · Padrão Cowork como visual default dos campos de formulário

- **Status**: accepted
- **Data**: 2026-05-27
- **Decisores**: Wagner, Claude
- **Categoria**: ui
- **Supersedes**: nenhuma (consolida pattern já introduzido em UI-0013)
- **Related**: UI-0001 (Tailwind 4), UI-0002 (shadcn copy-paste), UI-0004 (Dark mode via .dark class), UI-0010/UI-0012 (Cowork canon visual), UI-0013 (Constituição UI v2)

## Contexto

Até 2026-05-26 o oimpresso usava o `<Input>` / `<SelectTrigger>` / `<Textarea>` / `<Label>` shadcn canônico em ~120 telas Inertia. Visual padrão shadcn: `bg-transparent`, `text-sm`, ring opaco, label preto principal.

O protótipo Cowork (`prototipo-ui/prototipos/clientes/clientes.css`) — referência visual do design system desde UI-0010 — tinha pattern distinto que o time preferia: bg sólido `--surface`, text 13px, label dim cinza, ring suave `--accent-soft`, radio pill com `--accent-soft`. Wagner reportou pós-PR #1695 (telefones/emails Cliente):

> "as tonalidades fazem diferença. incrivel pouca coisa faz mesmo muito diferença. quero o padrão"

Sequência PRs #1698 → #1699 → #1700 introduziu `variant="cowork"` **opt-in** nos 5 tabs do drawer Cliente, com `cowork-fields.css` definindo classes `.cw-input`, `.cw-label`, `.cw-radio`, `.cw-toggle`, etc usando tokens namespaced `--cw-*` em `:root` (pra resolver fora de wrapper `.cockpit`).

Após validar visual em prod (drawer Cliente Identificação/Contato/Endereço/Comercial/Classificação), Wagner pediu padronização do site inteiro:

> "vai ter que documentar tudo inclusive o dark mode, ache tudo sobre isso estais fazendo o padrão do site inteiro"

## Decisão

**Inverter o default dos 4 componentes shadcn principais de formulário:**

1. `<Input>` (resources/js/Components/ui/input.tsx)
2. `<SelectTrigger>` (resources/js/Components/ui/select.tsx)
3. `<Textarea>` (resources/js/Components/ui/textarea.tsx)
4. `<Label>` (resources/js/Components/ui/label.tsx)

**Default novo:** `variant="cowork"` — aplica classes `.cw-*` do `cowork-fields.css`.
**Opt-in legacy:** `variant="shadcn"` — visual shadcn antigo (use APENAS em containers escuros onde bg branco quebra hierarquia, raro).

**Cobertura instantânea**: ~120 telas mudam visual no merge.

### Tokens (resources/css/cowork-fields.css)

Definidos em `:root` (light) + `.dark` (override dark mode), namespaced `--cw-*` pra evitar colisão com tokens `.cockpit { ... }` legacy:

```css
:root {
  --cw-surface:      #ffffff;
  --cw-bg:           oklch(0.985 0.003 90);
  --cw-bg-2:         oklch(0.965 0.004 90);
  --cw-border:       oklch(0.90 0.004 90);
  --cw-text:         oklch(0.22 0.01 80);
  --cw-text-dim:     oklch(0.50 0.01 80);
  --cw-text-mute:    oklch(0.65 0.01 80);
  --cw-accent:       oklch(0.58 0.09 220);
  --cw-accent-soft:  oklch(0.94 0.025 220);
  --cw-error:        oklch(0.65 0.18 25);
  --cw-error-soft:   oklch(0.94 0.04 25);
}
```

Valores `oklch()` IDÊNTICOS ao Cowork canon (`prototipo-ui/prototipos/clientes/cockpit.css`).

### Dark mode

Aplicado apenas via class `.dark` no `<html>` — canon definido em **UI-0004** (accepted 2026-04-22).

**ANTI-PATTERN documentado:** `@media (prefers-color-scheme: dark)` em CSS de componentes individuais. Bug catalogado pós-PR #1699 em 2026-05-27: Wagner em Windows OS dark mode viu drawer Cliente com campos pretos sobre fundo light enquanto resto do app continuava light. Causa: `@media` ativa baseado em OS, ignorando preferência manual gerenciada por [useTheme.ts](../../../../../resources/js/Hooks/useTheme.ts). Fix em #1700 removeu o `@media` — manteve apenas `.dark { ... }` selector.

Regra: **componente NÃO deve ter `@media (prefers-color-scheme: dark)` no CSS** — confiar na class `.dark` aplicada no root.

## Consequências

**Positivas:**

- Visual consistente em 120+ telas (Sells, OficinaAuto, Compras, Financeiro, Ponto, etc).
- Daniela/Larissa não percebem mismatch entre módulos.
- Fonte única de truth (`cowork-fields.css`) — ajustar tokens uma vez, propaga.
- Reversibilidade: `variant="shadcn"` opt-in mantém visual antigo onde necessário.
- Tokens namespaced `--cw-*` não colidem com `.cockpit { ... }` legacy nem com Tailwind utilities.

**Negativas:**

- Telas que tinham visual shadcn de propósito (modais escuros, drawers coloridos) vão ficar visualmente quebradas até receber `variant="shadcn"` explícito.
- Mudança massiva instantânea — risco de visual regression em telas raramente acessadas que não foram testadas.

**Mitigação:**

- Smoke prod nos 5 módulos top (Sells/Cliente/Financeiro/Essentials/Ponto).
- Rollback simples: re-inverter o `if (variant === ...)` nos 4 componentes.

## Alternativas consideradas

### A) Manter `variant="cowork"` opt-in, propagar tela a tela (~10 PRs por módulo)

Rejeitado — leva 3-5 dias de PRs sequenciais, deixa app com 2 visuais coexistindo, e Wagner explicitamente pediu padrão do site inteiro.

### B) `variant="shadcn-legacy"` mais explícito

Rejeitado — só "shadcn" é suficiente; "legacy" no nome cria atrito quando alguém legitimamente precisa do visual antigo.

### C) Manter ambos como variants iguais (sem default)

Rejeitado — TypeScript não permite default obrigatório opcional ao mesmo tempo. Definir default é compatível com retro-compatibilidade (telas existentes não precisam mudar nada).

## Implementação

PR `feat/cowork-default-global` 2026-05-27:

1. `input.tsx`, `select.tsx`, `textarea.tsx`, `label.tsx` — inverter `variant` default
2. `cowork-fields.css` — manter (tokens já namespaced `--cw-*` desde #1699)
3. Remover `variant="cowork"` redundantes nos 5 tabs do drawer Cliente (agora default — code smell de redundância)
4. Smoke prod via `mcp__Claude_in_Chrome__*` em 3-5 telas top (Sells/Create, OficinaAuto/Edit, Financeiro)

PRs anteriores históricos:

- **#1698** [feat(cliente/ui): campos do drawer fiéis ao Cowork](https://github.com/wagnerra23/oimpresso.com/pull/1698) — introduziu `variant="cowork"` opt-in
- **#1699** [fix(cliente/ui): tokens em :root](https://github.com/wagnerra23/oimpresso.com/pull/1699) — fix scope `.cockpit`
- **#1700** [fix(cliente/ui): remove dark media query + SelectTrigger variant](https://github.com/wagnerra23/oimpresso.com/pull/1700) — fix dark mode bleed
