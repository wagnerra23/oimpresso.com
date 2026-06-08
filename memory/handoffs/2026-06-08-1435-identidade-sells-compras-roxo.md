---
date: "2026-06-08"
hour: "14:35 BRT"
topic: "Mapa de Identidade ERP (Cowork) → fechei os 2 itens visuais reais: Sells chrome residual + Compras navy→roxo"
duration: "~2h"
authors: ["Claude (Opus 4.8)", "Wagner [W]"]
---

## Estado MCP no momento

- **Cycle ativo:** CYCLE-08 "Receita — Onda A" (2026-05-31→06-28, 29% decorrido). Este trabalho é **off-cycle** (identidade visual ≠ Receita) — foi pedido direto via design-file, não task trackada.
- **my-work:** sem task associada (ad-hoc).
- ADRs aceitas: nenhuma criada nesta sessão.

## O que aconteceu

Wagner: *"Fetch this design file… implement: Mapa de Identidade ERP - CC.html"* (bundle Cowork claude.ai/design).

**Decodifiquei: o arquivo NÃO é uma tela — é um artefato de DECISÃO** (rodapé: *"[CC] read-only no git, não escreve no repo"*). Diagnostica a identidade de cor do ERP e propõe um programa de 6 fases [W]-gated. Li os chats (47): Fase 0 ratificada por [W] hoje (chrome = roxo único `oklch(0.55 0.15 295)`; hue-por-módulo → camada semântica). Item executável = **Trilho A: Sells azul→roxo**.

**Lição central (repetiu o padrão dos handoffs 06-01/06-02): o doc de design estava STALE.** Re-levantei o estado REAL no `main` em vez de confiar no doc:
- "Sells azul 220 chrome errado" → o bloco `--accent` **já era roxo**; sobravam só **leaks residuais** (fallbacks `var(--accent-h,220)`, `--focus-ring`, dark roadmap) que rendiam azul ao vivo.
- "Financeiro `--bubble-me 220` stale" + "2 bundles duplicados" → **já corrigidos** (bubble já é `var(--accent)`; só existe `cowork-canon-financeiro-bundle.css`).
- "CRM/Clientes `--accent` indigo 268" → **não existe** CSS de clientes no repo (era prototype-only).
- **Compras navy hex** → confirmado: **única ilha real** (`--cmp-accent:#1f3a5f` + paleta inteira em hex cru).

**AskUserQuestion ×2:** (1) escopo de "implement" → Wagner escolheu "finish Sells chrome cleanup"; (2) como atacar Compras → "convergência total navy→roxo". Gerei **screenshot before/after** (harness HTML standalone, mesmo markup com as 2 paletas) → Wagner aprovou ("a ficou otimo", opção A pedido=roxo) → "merge".

## Artefatos gerados

- **PR #2425 (Sells)** — `sells-cowork.css` (5 leaks: 4× `var(--accent-h,220)→295`, dark roadmap-current) + `sells-cowork-edit.css` (2× `--focus-ring 220→295`). 7 edições, só chrome; semânticos (origin-CRM, avatar, status-partial, vd-link-cli, file-icon, group-tooltip) preservados. **MERGED** (--admin, 10/10 verde).
- **PR #2427 (Compras)** — `cowork-compras-bundle.css` reescrito: bloco `--cmp-*` aliasado ao canon + ~30 hex hardcoded tokenizados. Navy→roxo, superfícies quentes→canon, status (verde/âmbar/vermelho) preservados. **ZERO hex restante**. **MERGED** (--admin após CI verde).

## Persistência

- **git:** 2 PRs squash-merged em `main` (#2425, #2427). Branches/worktrees removidos, branches locais deletadas.
- **MCP:** sem task (off-cycle ad-hoc).
- **Gates:** stylelint baseline −51 no Compras (436 vs 487 — remover 38 hex melhora); css-size Δ0 nos dois.

## Próximos passos pra retomar

O programa "Mapa de Identidade" tem só **arquitetural** sobrando (sem mudança visível) — não urgente:
- **Fase 1:** eleger 1 arquivo canon de token; colapsar os 3+ sites de `--accent`.
- **Fase 3:** escrever a regra das 2 camadas no DS ("chrome=1; semântica=N só de `--origin-*`/`--stage-*`/status").
- **Fase 4:** ligar trava CI "cor crua = erro" (o `Conformance · cor-crua ratchet` JÁ existe) + DS-GUARD barrar redefinição de `--accent`.
- **Financeiro (opcional):** aliasar o bloco `--cmp`/`fin` ao canon em vez de redefinir (valor já correto, é higiene arquitetural, sem bug visível).

## Lições catalogadas

- **Doc de design Cowork = SNAPSHOT, sempre stale.** Re-levantar o estado real no `main` ANTES de implementar (igual L-09 §10.4). Aqui, 3 dos 4 "problemas" do doc já estavam resolvidos.
- **`Mapa de Identidade.html` open_file ≠ tarefa** — a intenção mora nos chats (Fase 0 ratificada lá). README do bundle manda ler os chats.
- **Mudança visual grande (Compras navy→roxo) → gate de SCREENSHOT antes do PR** (protocolo). Harness HTML standalone (canon tokens + 2 paletas + mesmo markup) = prova confiável sem subir o ERP inteiro.
- **Branch hygiene:** comecei editando Compras no worktree do Sells (sobre branch já commitada) → movi via `git stash` + `checkout -b … main` + `pop` pra manter 1 PR = 1 intent.

## Pointers detalhados

- Bundle Cowork extraído: `/tmp/design_bundle/oimpresso-erp-conunica-o-visual/` (README + 47 chats + `Mapa de Identidade ERP - CC.html` + `PROMPT_PARA_CODE_SELLS-CHROME-AZUL-PARA-ROXO.md`).
- Proposta-raiz (Fase 0): `memory/decisions/_PROPOSTA-modelo-unico-identidade-2-camadas.md` (→ [CL] numerar como ADR quando [W] quiser).
- Tokens canon: `resources/css/cockpit.css` :root (L22-42).
