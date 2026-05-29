# AUDITORIA DS v4 (accent roxo 295) — raio de explosão

> Gerada pelo Claude Code em 2026-05-29 · branch `feat/design-system-v4-roxo`.
> **Read-only:** este relatório NÃO altera telas. Serve pra guiar a migração tela-por-tela.

## 0. Correção factual vs briefing inicial

O briefing do Cowork dizia *"supersede ADR 0190 (shell azul)"*. A auditoria do repo mostrou que isso estava **incorreto**:

- **[ADR 0190](../memory/decisions/0190-primary-button-roxo-universal-295.md)** = *"Primary button roxo universal 295"* — aceito 2026-05-25, `supersedes: []`. Não é "shell azul".
- O **app já é roxo** no shell/primary: `resources/css/cockpit.css:32` → `--accent: oklch(0.55 0.15 295)`.
- Logo o v4 **estende (amends)** a 0190 — leva o roxo do *primary button* pro *accent universal*. Formalizado em **[ADR 0235](../memory/decisions/0235-ds-v4-accent-roxo-universal.md)**, não em um supersede.

## 1. Fundação de referência (`prototipo-ui/`) — SINCRONIZADA neste PR

| Arquivo | Antes | Depois |
|---|---|---|
| `prototipo-ui/tokens.css` | `--accent: oklch(0.58 0.09 220)` (azul) | `oklch(0.55 0.15 295)` (roxo) + `--accent-2`/`--accent-soft` |
| `prototipo-ui/design-system.css` | — | +4 linhas (Ondas A–D já presentes) |
| `prototipo-ui/Design System v4.html` | — | showcase novo |

`ds-behavior.js` e `CODE_DESIGN_CONTRACT.md` baixados eram **idênticos** ao repo (no-op) — não recopiados.

## 2. Débito — bundles CSS do app que AINDA redeclaram `--accent` em azul 220

Violam *"não redeclarar tokens"* (CODE_DESIGN_CONTRACT) e ficarão azuis enquanto o shell é roxo:

- `resources/css/cowork-canon-financeiro-bundle.css`
- `resources/css/cowork-financeiro-bundle.css`
- `resources/css/fin-cowork.css`
- `resources/css/sells-cowork-edit.css`
- `resources/css/sells-cowork-show.css`
- `resources/css/sells-cowork.css`

→ **✅ RESOLVIDO** (PR `feat/ds-v4-cowork-bundles-accent-roxo`, 2026-05-29): flip dos 6 bundles `--accent`/`--accent-2`/`--accent-soft` (claro + escuro) azul 220 → roxo 295, **escopado só nas linhas `--accent*`** — `--bubble-me` e `--status-partial` (que compartilham o valor azul `oklch(0.58 0.09 220)`) ficam azuis de propósito. Optou-se por **flip de valor** (não remover a redeclaração) por ser independente da ordem de carga do CSS; o débito DRY (herdar de `cockpit.css`) fica pra depois.

## 3. Telas Inertia/React com `blue-*` hardcoded — VARRIDO

A contagem inicial de "106 arquivos com `blue-*`" era **enganosa**: a grande maioria é azul **SEMÂNTICO** (paletas de status/tipo/categoria/chart — `paid`/`partial`/`overdue`, `remessa`/`retorno`, NFe/NFSe, Débito×Crédito, dots de evento, cores de gráfico/hue, marca Asaas, convenção "lida" do WhatsApp) que **deve ficar azul** — converter quebraria o código de cor que o usuário lê.

O **azul-de-marca** (o accent do app — links, focus rings, seleção/ativo, botões primários) foi migrado pro token `primary` (roxo) em ondas:

| Onda / PR | Escopo | Estado |
|---|---|---|
| `Cliente/` (ADR 0235 §4, prévio) | cadastro KB-9.75 — primeira tela | ✅ roxo |
| #1975 `feat/financeiro-cobranca-accent-roxo` | Sells (já só-semântico) + Financeiro/Cobrança (foco de linha + funil ativo) | ✅ roxo |
| `feat/ds-v4-finish-brand-blue-roxo` (este) | **28 telas** restantes: ads/Admin, OficinaAuto, ProjectMgmt, Financeiro, team-mcp, governance, kb, Admin, Settings, Modules, Manufacturing, Purchase (links + focus + seleção + botões primários) | ✅ roxo |

**Regra aplicada:** azul entre cores-irmãs de status/tipo → SEMÂNTICO, preserva. Azul de link/foco/seleção/ativo/ação-primária → BRAND, vira `primary`. Na dúvida, preserva.

**Pendências menores (preservadas — decisão de design p/ Claude Design):** stripe decorativo `border-l-blue-500` ("Goal do cycle" em ProjectMgmt Burndown/Board), gradiente do logo (`ConsultaOs` `from-blue-500 to-violet-600`), tick "lida" do WhatsApp, dot de não-lido (MyWork). Flagados mas mantidos azuis por não serem claramente accent.

## 4. Telas Blade legado (UltimatePOS) — fora do DS

`resources/views/contact/*.blade.php` (**14 arquivos**) — renderizadas quando `MWART_CLIENTE_*` está OFF pro business. Migração MWART F1→F3 futura.

## Resumo executivo

| Camada | Estado roxo |
|---|---|
| Shell/primary do app (`cockpit.css`) | ✅ roxo (ADR 0190) |
| Fundação de referência (`prototipo-ui/`) | ✅ roxo (este PR) |
| Bundles CSS financeiro/sells | ✅ roxo 295 (flip neste PR · §2) |
| Telas Inertia (azul-de-marca) | ✅ roxo — Cliente + #1975 + sweep 28 telas (§3); resto é azul semântico que fica |
| Blade legado `contact.*` | ⬜ fora do DS (§4) |
