---
slug: 0300-errata-0239-nome-real-fonte-design-system
number: 300
title: "Errata ao ADR 0239 — a fonte (SSOT) do Design System que o build consome é resources/css/inertia.css + foundations.css + cockpit.css, não tokens.css/design-system.css"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-22"
module: governance
tags: [governance, design-system, ssot, errata, correcao-documental, css, build, anti-fantasma, append-only]
supersedes: []
superseded_by: []
related:
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0235-ds-v4-accent-roxo-universal
  - 0236-governanca-evolucao-doc-design
  - 0299-figma-nao-e-fonte-de-design
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Proposta por [CL] (Claude Code) em 2026-06-22.** Ratificação formal = merge por [W].
> Origem: auditoria de maturidade da governança — a regra **R1** do [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md) cita um **alvo fantasma**.

# ADR 0300 — Errata ao 0239: o nome real da fonte do Design System

## Natureza desta ADR

**Errata append-only.** Esta decisão **não edita** o corpo do [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md) (canon é append-only — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)). Ela **corrige a referência documental** da regra R1: aponta para os arquivos `.css` que o build **de fato** consome. **Não renomeia, não move e não cria** nenhum arquivo — renomear quebraria o build (os imports concretos apontam para os nomes atuais). A regra R1 do 0239 (git é a fonte única; tudo mais é derivado) **continua válida na intenção**; muda só o **nome do arquivo** que ela cita.

## Contexto (verificado em `origin/main`, worktree desta branch)

A regra **R1** do ADR 0239 afirma (verbatim, linhas 47-50 do `memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md`):

> «`design-system.css` + `tokens.css` em `wagnerra23/oimpresso.com@main` **são** o Design System.»

**Problema:** esses dois nomes de arquivo **não existem** no path implícito da fonte do build (`resources/css/`). Eles existem **apenas** em `prototipo-ui/` — que o próprio 0239, na tabela de Mecanismo (linha 87), chama de **"espelho da raiz do git"**, ou seja, um **derivado/representação**, justamente a categoria que a R1 diz **não** ser a fonte.

Risco real (motivo da errata): um humano ou agente lê a R1, procura `tokens.css`/`design-system.css` na raiz do app, **não acha**, e ou (a) edita o espelho `prototipo-ui/*.css` achando que é a fonte (não afeta o build — mudança silenciosamente perdida), ou (b) cria um `tokens.css` novo na raiz achando que "regulariza" (cisma a fonte). Ambos contrariam o espírito da R1.

### Evidência (o que verifiquei)

**1. Os nomes citados não existem na fonte do build.** `ls resources/css/` lista 29 arquivos `.css`; **nenhum** se chama `tokens.css` nem `design-system.css`. `git ls-files | grep -E '(tokens|design-system)\.css$'` retorna **somente** dois caminhos, ambos sob `prototipo-ui/`:

```
prototipo-ui/design-system.css
prototipo-ui/tokens.css
```

**2. O que o build realmente consome (imports concretos no código):**

| Arquivo real | Quem importa | Papel no DS |
|---|---|---|
| `resources/css/inertia.css` | `resources/js/app.tsx:1` → `import '../css/inertia.css';` | **entry do DS**: `@import "tailwindcss"` + bloco `@theme { --color-* }` (tokens de cor/primary roxo 295) + orquestra todos os `@import "./*.css"` |
| `resources/css/foundations.css` | `resources/js/Layouts/AppShellV2.tsx:87` → `import '../../css/foundations.css';` | escala tipográfica `--fs-1..9` + atmosfera (`--atmo`) |
| `resources/css/cockpit.css` | `resources/js/Layouts/AppShellV2.tsx:88` → `import '../../css/cockpit.css';` | tokens semânticos do cockpit (`--pos/--neg/--warn` + `-soft`, pares dark via `[data-theme]`) |

O entry do Vite é `resources/sass/tailwind/tailwind.scss` (`vite.config.js:7`) para o pipeline Blade/legacy; o **app Inertia/React** (que é onde o Design System v4+ vive) entra por `resources/js/app.tsx`, que importa `inertia.css`, que por sua vez puxa `foundations.css`/`cockpit.css` via os imports do `AppShellV2`. Não há, em nenhum desses caminhos, um arquivo chamado `tokens.css` ou `design-system.css`.

## Decisão (a correção)

### C1 · Onde a R1 diz «`design-system.css` + `tokens.css`», leia-se a tríade real

A **fonte única (SSOT) do Design System** que o build consome é, em `wagnerra23/oimpresso.com@main`:

- **`resources/css/inertia.css`** — entry do DS (Tailwind + bloco `@theme` de tokens + agregador de `@import`);
- **`resources/css/foundations.css`** — escala tipográfica + atmosfera;
- **`resources/css/cockpit.css`** — tokens semânticos do cockpit.

Tudo mais — protótipo Cowork (`prototipo-ui/`, incluindo os arquivos `prototipo-ui/tokens.css` e `prototipo-ui/design-system.css` que **são espelhos/derivados**, não a fonte), qualquer `Design System vX.html`, showcases, Figma ([ADR 0299](0299-figma-nao-e-fonte-de-design.md)) — permanece **derivado/representação**. Divergiu do git? **O git vence.** A intenção da R1 é mantida ao 100%; só o nome do arquivo foi corrigido.

### C2 · Não renomear, não mover, não criar arquivo

Esta errata é **puramente documental**. Os imports concretos (`app.tsx`, `AppShellV2.tsx`) apontam para os nomes atuais; **renomear `inertia.css`→`design-system.css` ou criar um `tokens.css` quebraria o build** ou cismaria a fonte. Quem quiser unificar nomenclatura no futuro deve fazê-lo via PR próprio que ajuste **import + arquivo + esta referência juntos** — não é escopo desta errata.

### C3 · O ADR 0239 não é reescrito

O corpo do 0239 fica **intacto** (append-only). Esta ADR é o registro canônico de que a R1 cita nomes que não existem na fonte do build e de qual é a tríade real. Quem ler o 0239 e estranhar a R1 deve seguir esta errata (`related` aponta nos dois sentidos quando o 0239 for tocado em futura emenda; por ora o ponteiro vive aqui).

## Não-goals (honestidade de escopo)

- ❌ **Não reescrevo o 0239** (append-only — só nova ADR com `related`).
- ❌ **Não renomeio/movo/crio** `inertia.css`/`foundations.css`/`cockpit.css`/`tokens.css`/`design-system.css`.
- ❌ **Não toco no gate R3** (regressão-IA) nem na infra de enforcement do 0239 — a errata é só sobre o **nome do alvo** da R1.
- ❌ **Não me pronuncio** sobre se o espelho `prototipo-ui/tokens.css`/`design-system.css` deveria existir — ele é, por R1, derivado legítimo (espelho); só não é a fonte.

## Consequências

✅ **Boas:**
- Some o alvo fantasma: quem aplica a R1 passa a editar a tríade certa (`inertia.css`/`foundations.css`/`cockpit.css`), não um arquivo inexistente nem o espelho do protótipo.
- Zero risco de build: nada é renomeado/movido; a correção é documental.

⚠️ **Tradeoffs:**
- A nomenclatura segue **não-unificada** (a fonte não se chama "design-system.css"). Aceito de propósito — unificar nome é PR de refactor à parte (C2), com custo/risco de build que esta errata recusa carregar.
- Fica uma indireção: o leitor do 0239 R1 precisa chegar a esta errata. Mitigado por `related: [0239-…]` e pela citação explícita do número/título.

## Validação

- ✅ `ls resources/css/` — confirma ausência de `tokens.css` e `design-system.css` na fonte do build.
- ✅ `git ls-files | grep -E '(tokens|design-system)\.css$'` — confirma que existem **só** em `prototipo-ui/` (espelho).
- ✅ Imports concretos lidos: `resources/js/app.tsx:1` (inertia.css), `resources/js/Layouts/AppShellV2.tsx:87-88` (foundations.css/cockpit.css); bloco `@theme` confirmado em `resources/css/inertia.css:112`.
- ✅ `node scripts/governance/memory-health.mjs` — rc 0.
- ✅ `node scripts/governance/adr-index-generate.mjs --check` — rc 0 (índice regenerado neste PR).

## Notas

- O slug `0224-hooks-block-vs-advisory-claude-4.8-aware` (citado por outras ADRs sobre block vs advisory) **não** entra no campo `related:` porque contém ponto (`4.8`) e falharia o regex de slug `^[0-9]{4}-[a-z0-9-]+$`; fica citado aqui em prosa.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-22 | [W] decide + [CL] redige | errata documental à R1 do ADR 0239 — nome real da fonte do DS (sem renomear arquivo) |
