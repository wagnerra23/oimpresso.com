# MAPA DE CAPACIDADES DO REPO — o que JÁ EXISTE (ler ANTES de propor guarda/componente/regra)

> **Por que este arquivo existe (lição 2026-06-08):** [CC] propôs reinventar guardas que já existiam (`--accent`-guard, PageHeader-guard) por não ter lido `package.json` + `scripts/` + `Components/ui/` antes de propor. **Regra: antes de propor QUALQUER guarda/componente/regra/script novo, conferir aqui se já existe.** Este índice é cache; a verdade é o `main` — confirmar com `github_read_file` no turno. Marcado: `✓lido` = li o arquivo nesta linhagem · `~nome` = sei só pelo `package.json`, ainda não li o código.

## 🛡️ Suíte de GUARDAS / GATES (CI bloqueia merge · ratchet baseline · "testam o teste" 2 lados)

Fonte: `package.json` scripts (✓lido @main 2026-06-08). NÃO criar guarda novo sem checar se um destes já cobre.

| Comando | Script | O que protege | Status leitura |
|---|---|---|---|
| `conformance:check` | `scripts/conformance-gate.mjs` | **Cor crua em CSS de TELA (ratchet) + invariante `--accent` roxo (hue 250–330) em todo `resources/css/*.css`.** Fora do roxo = 🔴. | ✓lido |
| `foundation:check` | `scripts/foundation-guard.mjs` | **Token (`@theme`/`--accent`/`--color-*`) só pode ser DEFINIDO em `foundations.css`/`cockpit.css`** + **allowlist de arquivo `.css`** (`.foundation-guard-files.json`). Arquivo `.css` novo ou token-def fora da fundação = 🔴. | ✓lido |
| `stylelint` | stylelint ^17 + `scripts/stylelint-baseline.mjs` | `#hex` global congelado (ratchet `config/stylelint-baseline.json`). | ~nome |
| `pageheader:guard` | `scripts/pageheader-migration-guard.mjs` | Migração/duplicação do `<PageHeader>`. **(= a "trava de PageHeader duplicado" JÁ existe aqui.)** | ~nome |
| `reuse:check` / `reuse:duplicates` / `reuse:gate` | `scripts/reuse-index.mjs` | Índice de reuso + duplicação de componente/código. | ~nome |
| `dup:check` | `jscpd resources/js` | Copy-paste detection (código duplicado). | ~nome |
| `layout:check` | `scripts/layout-primitives-guard.mjs` | Uso dos primitivos de layout (Box/Stack/Inline/Grid/Container/Text · ADR 0253) vs flex solto. | ~nome |
| `a11y:check` / `a11y:axe` | `scripts/a11y-ratchet.mjs` + `tests/a11y-primitives.test.tsx` | Acessibilidade (axe-core ratchet). | ~nome |
| `no-mock:check` | `scripts/no-mock-in-prod.mjs` | Mock/rand em código de produção. | ~nome |
| `design-spec:check` | `scripts/design-spec-gen.mjs` | Spec de design por tela. | ~nome |
| `ds:report` | `scripts/ds-report.mjs` | Relatório de conformidade DS. | ~nome |
| `css:size:check` | `scripts/css-size-baseline.mjs` | Tamanho de CSS (ratchet). | ~nome |
| `scorer:sync` | `scripts/scorer-sync-check.mjs` | Sync do scorer de crítica. | ~nome |
| **ESLint `ds/*`** | `eslint.config.js` (bloco `no-restricted-syntax`, escopo `Pages/**`+`Modules/**`, ignora `Components/ui/**`) | `ds/no-native-radio|checkbox|select` · `ds/no-rounded-xl` · `ds/no-arbitrary-color` (só `bg-[#hex]`) · `ds/no-adhoc-status-text` (só `text-(rose\|red\|emerald\|green)-(500\|600\|700)`). **BURACO confirmado: não pega paleta crua `bg-blue-100`/`bg-green-100` nem cor inline `style={{oklch}}` em `.tsx`** → handoff `PROMPT_PARA_CODE_DS-LINT-TSX-COR-CRUA.md`. | ✓lido |

> **Padrão dos guardas:** determinístico, sem browser, ratchet (`.X-baseline.json`, só-desce), CI `*-gate.yml` falha em delta>0, e cada um tem **controle-negativo versionado** (`tests/*.spec.*` — testa o teste, 2 lados). Comandos baseline: `:baseline:write` re-crava após remoção intencional, `:baseline:check` valida. ADR 0209 (ratchet gêmeo) · ADR 0238 (soberania tooling).

## 🎨 IDENTIDADE / TOKENS (fonte única)

- **Chrome = roxo `oklch(0.55 0.15 295)`** (ADR 0235/0190). Runtime: `app.jsx` seta inline via tweak `accentHue`; CSS é fallback.
- **Token só vive em `foundations.css` + `cockpit.css`** (allowlist do `foundation-guard`). No Cowork o canon espelhado é `ds-v6/tokens.css`.
- **Camada semântica (2 camadas):** chrome=1 roxo · significado=N (`--origin-*` origem · `--stage-*` etapa · `--pos/--warn/--neg` status). Padronizar chrome NÃO mata wayfinding. Âmbar do Oficina = `--origin` da OS, não accent redefinido.
- **Estado de identidade no git (✓ lido 2026-06-08):** Oficina/Sells/Financeiro/Compras **todos roxo/aliasados**. ZERO ilha. NÃO há trabalho de identidade aberto.

## 🧩 COMPONENTES (reusar, não recriar — `M-AP-6` "não inventar componente")

- **`@/Components/ui/*`** — shadcn canon (Button, Card, Sheet, Select, Checkbox, Badge, Input, Label, Textarea, RadioGroup, Alert…). É a camada onde os padrões vivem (ds/* a ignora).
- **Primitivos de layout** `@/Components/layout/*` — Box/Stack/Inline/Grid/Container/Text (ADR 0253, props=token). `layout:check` guarda o uso.
- **`@/Components/PageHeader`** (named) = canon vivo · `@/Components/shared/PageHeader` (default) = duplicado em migração (`pageheader:guard`).
- **`@/Components/shared/StatusBadge`** — badge de status centralizado (`kind="financeiro_titulo"` etc.). Usar em vez de `STATUS_LABELS` inline.
- Compartilhados de domínio: `VendaDerivadaCard`, `MercosulPlate`, `KanbanDndProvider`, `ServiceOrderRichSheet`, `FinanceiroSubNav`.

## 🏗️ STACK / FATOS

- Laravel 13.6 + Inertia v3 + nWidart Modules · React 19 + TS + **Tailwind 4** (`max-w-*`, não `max-w-screen-*`) · @dnd-kit · radix · sonner (toast) · vitest.
- `lint` = `eslint resources/js --max-warnings=999999` (o gate real é o baseline `scripts/eslint-baseline.mjs`, não o max-warnings).
- Multi-tenant **Tier 0 IRREVOGÁVEL** (ADR 0093): `business_id` SEMPRE de `session('user.business_id')`, nunca query param. Tem Pest GUARD por tela.
- Cliente piloto LIVE: ROTA LIVRE (Larissa, 1280px) + Martinho biz=164 (caçamba, `cacamba_locacao`, ADR 0194 — NÃO mexer no DB).

## 📋 COMO USAR (ritual)

1. Vou propor guarda/regra/componente/script? → procurar aqui + confirmar no `main`. Se já existe, **estender/referenciar, não recriar**.
2. Toquei uma área e li o script de verdade? → mudar `~nome` pra `✓lido` + corrigir o resumo.
3. Achei capacidade nova no repo? → adicionar linha aqui (este mapa é vivo).
