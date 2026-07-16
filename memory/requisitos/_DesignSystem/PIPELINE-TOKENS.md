# PIPELINE de tokens do Design System — de onde vêm, como são aplicados, como usar nas telas

> **Pergunta que este doc responde (Wagner 2026-07-08):** *"descreva todo processo e arquivos, de onde vem e como é aplicado, e como deve ser aplicado nas telas."*
> **Regra-mãe:** git = SSOT ([ADR 0239](../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md)). Tokens são DTCG JSON compilados por Style Dictionary. **Nunca** cor crua (hex/oklch) na tela — só token.

---

## 1. A cadeia — de onde vem cada arquivo

```
┌─ AUTORIA ────────────────────────────────────────────────────────────────┐
│ claude.ai/design  (projeto "Office Impresso — Design System")             │
│   Wagner desenha visualmente → colors_and_type.css  (espelho dos tokens)  │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │  PULL determinístico (design→git)
                                │  DesignSync get_file → ds-token-diff.mjs → triagem
                                ▼
┌─ FONTE DA VERDADE (git) ── resources/css/tokens/ ─────────────────────────┐
│  base.tokens.json       tokens primitivos (escalas cruas)                 │
│  semantic.tokens.json   tokens SEMÂNTICOS ← É AQUI QUE SE EDITA            │
│      $value            = valor LIGHT (default)                            │
│      $extensions.com.oimpresso.dark = valor DARK                         │
│  style-dictionary.config.mjs   o compilador                              │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │  npm run tokens:build   (= node style-dictionary.config.mjs)
                                ▼
┌─ GERADOS (não editar na mão — são output) ── resources/css/tokens/ ───────┐
│  _generated-inertia-theme.css   @theme { }         (Tailwind utilities · light) │
│  _generated-inertia-dark.css    .dark,[data-theme=dark] { }  (Tailwind · dark)  │
│  _generated-foundations-light/dark.css   :root type ramp (--fs-*), sombras     │
│  _generated-cockpit-light/dark.css       .cockpit { } shell (--bg/--surface/…) │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │  @import
                                ▼
┌─ AGREGAÇÃO ───────────────────────────────────────────────────────────────┐
│  resources/css/cockpit.css   @import _generated-cockpit-{light,dark}.css   │
│                              + estilos de componente usando var(--bg) etc. │
│  resources/css/inertia.css   camada @theme Tailwind + imports              │
└───────────────────────────────┬──────────────────────────────────────────┘
                                │  import no bundle (Vite/Inertia)
                                ▼
┌─ TELAS (React/Inertia) ── resources/js/Pages/<Mod>/<Tela>.tsx ────────────┐
│  pintam SÓ com token: var(--bg), bg-background, text-foreground, …         │
└───────────────────────────────────────────────────────────────────────────┘
```

## 2. Como é aplicado (o mecanismo)

- **Dois "vocabulários" de token, mesma fonte:**
  1. **Camada Tailwind `@theme`** (`--color-*`) → gera utilities: `bg-background`, `bg-card`, `text-foreground`, `border-border`, `bg-primary`, `text-muted-foreground`, `bg-success`/`bg-destructive`/`bg-warning`/`bg-info`.
  2. **Camada shell `.cockpit`** (`--bg`, `--surface`, `--text`, `--border`, `--accent`, `--sb-*`, `--pos`/`--neg`/`--warn`) → usada via `var(--…)` nas páginas operacionais e no `cockpit.css`.
- **Dark é automático:** o shell põe `[data-theme="dark"]` na raiz ([ADR 0281](../../decisions/0281-dark-mode-bridge-data-theme-tokens.md)). O mesmo token vira o valor dark sozinho — **a tela nunca escreve cor dark**.
- **Sidebar:** DARK-FIXED (preta nos 2 modos) via `--sb-*` (FASE 2 — supersede UI-0009).

## 3. Como DEVE ser aplicado nas telas (regras duras)

| Precisa de… | Página operacional (`.cockpit`) | Componente Tailwind/shadcn |
|---|---|---|
| Fundo da tela | `background: var(--bg)` | `bg-background` |
| Card/painel | `var(--surface)` | `bg-card` |
| Borda | `var(--border)` | `border-border` |
| Texto | `var(--text)` / `--text-dim` / `--text-mute` | `text-foreground` / `text-muted-foreground` |
| Ação primária (roxo) | `var(--accent)` | `bg-primary` |
| Status | `--pos` / `--neg` / `--warn` | `bg-success` / `bg-destructive` / `bg-warning` |
| Sidebar | `--sb-bg` / `--sb-text` / `--sb-active` | — |
| Raio · tipo | `--radius*` · ramp `--fs-1..9` | `rounded-*` · escala Tailwind |

**Proibido (DS gate `cor-crua` bloqueia o CI):**
- ❌ `#hex` ou `oklch(...)` cru numa tela/componente. Sempre token.
- ❌ Editar `_generated-*.css` na mão (é output — edite `semantic.tokens.json` e rode o build).
- ❌ Escrever variante dark na tela (o token já vira sozinho no `[data-theme=dark]`).
- ❌ Usar o accent (roxo) pra status, ou status pro accent.

**Tela nova:** charter + processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)); pinta com os tokens acima; nada de cor nova sem ADR (Fundações é imutável via ADR — Constituição UI v2).

## 4. Mudar um token (o ciclo completo)
1. (autoria) desenha no claude.ai/design **ou** decide o valor com Wagner por imagem.
2. `DesignSync get_file colors_and_type.css` + `node scripts/design-sync/ds-token-diff.mjs` → triagem ([runbook design-sync-pull](../../../.claude/runbooks/design-sync-pull.md)).
3. edita `semantic.tokens.json` (só as divergências adotadas).
4. `npm run tokens:build` (regenera os `_generated-*.css`).
5. `node scripts/governance/palette-generate.mjs --write` (atualiza `PALETA.html` — o Governance Gate cobra).
6. PR **draft** → Wagner aprova o **screenshot buildado** (Fundações Tier 0; merge no `main` dispara `deploy.yml`) → merge/deploy → smoke real na tela logada.

**Criado:** 2026-07-08 — descrição canônica do pipeline de tokens (autoria→git→build→tela). Pareia com o [runbook design-sync-pull](../../../.claude/runbooks/design-sync-pull.md).
