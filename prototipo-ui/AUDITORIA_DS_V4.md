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

→ **Ação futura:** remover a redefinição local; herdar `--accent` roxo do `cockpit.css`. (Fora do escopo deste PR.)

## 3. Débito — telas Inertia/React com AZUL HARDCODED (`blue-*` Tailwind)

**106 arquivos `.tsx`** com `blue-[3-8]00` / hex azul. Não seguem o token → ficam azuis no shell roxo. Piores no cadastro citado pelo Wagner (`Pages/Cliente/`):

| Arquivo | Onde aparece o azul |
|---|---|
| `Pages/Cliente/Index.tsx` | abas ativas, seleção de linha (`ring-blue-300`), checkbox (`bg-blue-500`), chips de filtro |
| `Pages/Cliente/Import.tsx` | ícones, dropzone hover, barra de progresso |
| `Pages/Cliente/Map.tsx` | item selecionado, link |
| `Pages/Cliente/Show.tsx` + `_show/*` + `_drawer/*` | abas, links de venda, focus ring, cards IA/OS |

→ **Ação:** PR `feat/cliente-accent-roxo` corrige a `Cliente/` (azul de marca/seleção/foco → token roxo; preserva azuis de status/info semântico). Demais telas por prioridade.

## 4. Telas Blade legado (UltimatePOS) — fora do DS

`resources/views/contact/*.blade.php` (**14 arquivos**) — renderizadas quando `MWART_CLIENTE_*` está OFF pro business. Migração MWART F1→F3 futura.

## Resumo executivo

| Camada | Estado roxo |
|---|---|
| Shell/primary do app (`cockpit.css`) | ✅ roxo (ADR 0190) |
| Fundação de referência (`prototipo-ui/`) | ✅ roxo (este PR) |
| Bundles CSS financeiro/sells | ❌ azul 220 redeclarado (§2) |
| Telas Inertia (`blue-*` hardcoded) | ❌ 106 arquivos (§3 — PR-2 começa pela `Cliente/`) |
| Blade legado `contact.*` | ⬜ fora do DS (§4) |
