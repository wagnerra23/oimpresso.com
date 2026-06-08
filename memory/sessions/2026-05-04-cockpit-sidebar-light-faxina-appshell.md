---
title: Cockpit sidebar light + faxina AppShell legado (ADR UI-0009)
date: 2026-05-04
type: session
authors: [W, Claude]
related_adrs: [UI-0008, UI-0009, 0039]
related_prs: []
---

# Sessão 2026-05-04 — Cockpit sidebar light + faxina AppShell legado

## Sintoma reportado

Wagner abriu `oimpresso.com/copiloto` em produção e mandou screenshot com sidebar PRETA + main escuro feio. Comparou com screenshot do protótipo Cowork "Oimpresso ERP - Chat.html" no claude.ai/design — sidebar BRANCA/clara, main creme. Frase exata: **"branca é a correta muito mais linda"**.

Pedido secundário: **"deixe só 1 [shell] limpe o resto e padronize. não precisa ter 2? eu acho que tem que apagar 1 deles para não confundir"** — autorizou apagar AppShell legado.

## Diagnóstico

**Causa-raiz visual**: `resources/css/cockpit.css` linhas 7-15 hardcoded sidebar dark (`oklch(0.21 0 0)` etc.) "espelho AppShell legado". Tokens `--sb-*` **sem variante por tema** — sidebar preta SEMPRE, em light e dark.

**Conflito doc vs decisão**: ADR UI-0008 + BRIEFING_CLAUDE_DESIGN.md §2/§6 dizem "**dark sidebar é fixa**". Mas o protótipo Cowork (verdade visual mais atual segundo DESIGN.md §6.3) evoluiu pra sidebar light sem virar ADR. Wagner agora formaliza a transição.

**Faxina AppShell**: grep `@/Layouts/AppShell` (sem V2) retornou **0 imports**. AppShell legado era arquivo órfão. Refs em `Types/index.ts`, `Hooks/usePageProps.ts`, `Components/shared/ModuleTopNav.tsx`, `Pages/ConsultaOs/Index.tsx` eram só JSDoc desatualizado. Nenhuma das 78 páginas Inertia importa o legado — todas em V2.

## Mudanças

### 1. `resources/css/cockpit.css`

- Tokens `--sb-*` light (paleta creme, espelhando protótipo Cowork): `--sb-bg: oklch(0.985 0.003 90)`, `--sb-text: oklch(0.34 0.01 80)`, etc.
- Override em `[data-theme="dark"]` com paleta dark elegante azul-cinza (não preto puro): `--sb-bg: oklch(0.18 0.006 240)`. Hierarquia visual: sidebar ligeiramente mais escura que o main.
- Adicionados tokens auxiliares `--sb-scroll`, `--sb-bullet-out`.
- Removidos hardcodes: `oklch(0.20 0 0)` (linha 190 dropdown empresa), `oklch(0.32 0 0)` (linhas 246/248 scrollbar), `oklch(0.40 0 0)` (linha 303 bullets), `oklch(0.22 0 0)` (linha 441 user-menu) — todos viraram tokens.
- Paleta dark do main também ajustada (oklch 0.16 → 0.22) pra sair do "preto demais".

### 2. `resources/js/Layouts/AppShell.tsx`

- **Deletado** (`git rm`). Era órfão (zero imports) desde Cockpit virar default.

### 3. JSDoc updates

- `Types/index.ts`, `Hooks/usePageProps.ts`, `Components/shared/ModuleTopNav.tsx`, `Pages/ConsultaOs/Index.tsx`, `Layouts/AppShellV2.tsx` — comentários "AppShell" → "AppShellV2".

### 4. Documentação canônica

- **`memory/requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md`** — ADR UI-0009 nova: sidebar segue `data-theme` do usuário (light default, dark elegante). Substitui parcialmente UI-0008 trecho "dark fixo".
- **UI-0008** patchado: header com `Substituído parcialmente por UI-0009`; trecho `Sidebar (260px, dark fixo na vibe workspace)` aponta pra UI-0009.
- **BRIEFING_CLAUDE_DESIGN.md** §2 e §6 atualizados — "dark sidebar fixa" → "segue data-theme".
- **CHANGELOG.md** entrada `[0.3.1] - 2026-05-04`.

## Validação

- ✅ `npm run build:inertia` passou (14.7s, AppShellV2 bundlou normal, 0 imports quebrados)
- ✅ `npm run typecheck` — erros pré-existentes (variáveis não-usadas em outras páginas), nenhum novo erro
- ⏳ Smoke visual em local + prod — Wagner valida em PR
- ⏳ `_DesignSystem/ui_kits/cockpit.html` re-exportar do Cowork (sessão futura — fora do escopo desta PR)

## Aprendizado meta

Conflito doc × decisão exposto: ADR UI-0008 fixou "dark sidebar" em 27-abr, mas Wagner evoluiu o protótipo Cowork sem virar ADR. **Quando o owner contradiz a ADR, a decisão dele rege — desde que vire ADR nova**. DESIGN.md §11 prevê isso ("Padrão muda por ADR, nunca por commit solto"). Foi seguido: ADR UI-0009 antes de mergear.

Lição replicável: antes de aplicar mudança visual proposta pelo Wagner, sempre validar **ADR canônico atual** + criar/patchar antes de codar — caso contrário, próximo agente desfaz.

## Pendências fora do escopo desta PR

- `_DesignSystem/ui_kits/cockpit.html` ainda tem tokens dark — Wagner re-exportar do Cowork em sessão Claude Design dedicada.
- Erros TS pré-existentes (~25) — issue separada de cleanup.
- Smoke visual em prod após merge — Wagner valida `/copiloto` (chat), `/copiloto/cockpit` (ref), `/financeiro/dashboard`, `/ponto/dashboard`, etc.
