---
date: '2026-05-30'
time: '21:29 BRT'
slug: screen-grade-estado-arte-governanca
tldr: "Estado-da-arte das 222 telas: workflow de 19 agentes aplicou SCREEN-GRADE 9.75 (16 dim, 0-100), média 75 (0 Champion·24 Leader·154 Advanced·42 Developing·2 Beginner) → board+baseline+HTML no git (#2011) → integrado na governança do ERP (ScreenReview dashboard ganha seção Maturidade lendo o baseline, #2012) + screenshot via Claude Preview. Antes: Q&A DS (Design System.html version-less; método por tela já existe). 2 PRs, tudo em main."
topic: "Estado-da-arte das 222 telas (método 9.75 · workflow 19 agentes) + integração na governança (ScreenReview dashboard maturidade)"
duration: ~2h
prs: [2011, 2012]
adrs: []
decided_by: [W]
authors: [W, C]
---

# Handoff — Screen-grade estado-da-arte + integração na governança

## Estado MCP no momento
Cycle **CYCLE-07** (Fundações pós-4.8). Sessão = **governança/design transversal** (adjacente). Continuação direta do handoff `1940` (ADR 0238 soberania + 0239 governança DS + gate do índice). `decisions-search`: nenhum ADR novo neste chunk. MCP conectado.

## O que aconteceu
1. **Q&A de DS** (Wagner perguntou): (a) `Design System.html` deve ter nome **version-less** (nome fixo = ponteiro, conteúdo versionado pelo git + snapshots em `_arquivo/ds/`) — confirmei, é o R4 do ADR 0239 melhorado; (b) **Método 9.75 por tela JÁ EXISTE** como `memory/requisitos/<Mod>/<tela>-visual-comparison.md` + skill `screen-grade` + `SCREEN-GRADE-METODO.md` — não criar pasta nova; (c) como aproveitar = ligar o motor numa tela real.
2. **Wagner: "faça o estado da arte … crie agentes em paralelo, máximo 80, faça todas."** → **Workflow** de grade.
   - **2 tentativas falharam**: (1) `args` chegou como **string** (Array.isArray=false → 0 telas) → embutir a lista no script; (2) 75 agentes concorrentes → **429 rate-limit** (todos morreram, 0 tokens) → reduzir pra **19 agentes em ondas de 4**.
   - **3ª rodou**: 19 agentes × 12 telas, **222 telas graduadas**, média **75/100**, 0 falhas.
3. **Board (#2011)** — consolidei em `memory/governance/scorecards/`: `SCREEN-GRADE-BOARD-2026-05-30.md` (ranking + prioridades + goldens + por-módulo) + `screen-grade-board.html` (interativo, busca/filtro/sort) + `screen-grades-baseline-2026-05-30.json` (ratchet). Memória no git (ADR 0061/0239 R1).
4. **Integração na governança (#2012)** — `ScreenReviewController` (`buildGradeSummary` + merge nota/nível em cada tela) → `ScreenReviewDashboard.tsx` ganha seção **"Maturidade de design · método 9.75"** (média + distribuição + prioridades/goldens + por-módulo) via `<Deferred>`. **Mostrei o screenshot** via Claude Preview MCP (preview_start + python http.server + preview_screenshot).

## Artefatos gerados (tudo em main)
- **#2011**: `memory/governance/scorecards/{SCREEN-GRADE-BOARD-2026-05-30.md, screen-grade-board.html (177KB), screen-grades-baseline-2026-05-30.json}`.
- **#2012**: `Modules/Admin/Http/Controllers/ScreenReviewController.php` (+131) · `resources/js/Pages/Admin/ScreenReviewDashboard.tsx` (+seção) · `resources/css/screen-grade.css` (cores de nível, fora do .tsx).

## Persistência
Git: **#2011 + #2012 mergeados em main** (--admin, sob direção [W] "salve tudo"). MCP: webhook propaga ~2min. Top goldens: Financeiro/Cobranca 94 · Inbox 91 · Sells/Index 90. Pior módulo: Manufacturing 50.

## Próximos passos pra retomar
- **Ligar o motor numa tela**: aplicar o método na #1 prioridade real ou subir um golden de receita (`Sells/Create` 88→Champion, Larissa). Cada fix cria teste anti-regressão (ADR 0230 Inv. A); nota só sobe (ratchet).
- **Deploy** pra ver a seção Maturidade live em prod/staging (staging up).
- **R3 enforcement** (`ds-regression-gate.yml`) ainda deferido (ADR 0239 R3).
- Per-screen YAMLs (score-as-code do método) não gerados — o baseline JSON guarda os dados.

## Lições catalogadas
- **Workflow `args` chega como STRING** — embutir a lista no script (`const SCREENS=[...]`), não confiar no `args` param pra arrays grandes.
- **Concorrência alta → 429**: 75 agentes de uma vez estouraram o rate-limit (0 tokens). Ondas de ~4 resolveram. Menos agentes-maiores > muitos-pequenos.
- **DS lint estrito** (`php artisan ui:lint`): **R1** = cor crua/`bg-COR-NNN` em Page → cores vão pra **CSS com classe semântica** (`.sg-bg-*`); **R3** = emoji em UI → **lucide icon**. ESLint `no-adhoc-status-text` = status color em heading. Espelhar `governance/ModuleGrades` (`scoreColorClass`).
- **"Abra a tela" sem rodar o app**: Claude Preview MCP (`preview_start` + `.claude/launch.json` python http.server + `preview_screenshot`) renderiza preview fiel.
- **PHPStan vermelho = débito env() pré-existente** (ADR 0208) — PRs que tocam PHP surfaçam; --admin merge por cima (controller meu é phpstan-clean).
- Grade **estático** (lido do .tsx, sem render) — ótimo pra priorizar/Pré-Flight; nota visual fina confirma rodando.

## Pointers detalhados
- `SCREEN-GRADE-METODO.md` (16 dim) · `screen-grade-board.html` (board) · ADR 0239 (governança DS) · ADR 0236 (ratchet/freshness) · `Modules/Admin/.../ScreenReviewController.php` (buildGradeSummary).
