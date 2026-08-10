---
date: "2026-08-09"
time: "03:45"
slug: forja-mesa-topnav-handoffs-e-a-fusao-6a
tldr: "5 PRs em produção com smoke — Mesa de Aprovações (superfície que a ADR 0368 deixou pendente), topnav em 3 grupos com trava de paridade, Handoffs como tela própria e a fusão 6a (/forja/trabalho, 500 tasks). Falta 6b quadro, 7 Gantt e os 301 — estes travados na decisão [W] da US-FORJA-006 (qual implementação sobrevive), com recomendação medida: as nativas."
prs: [5456, 5469, 5476, 5479, 5486]
us: [US-FORJA-006, US-FORJA-010]
next_steps:
  - "Decidir US-FORJA-006: qual das implementações de backlog sobrevive (recomendação: as nativas)"
  - "6b — Quadro unificado com os 2 eixos (Pipeline F0→F3.5 × Execução)"
  - "7 — Gantt como 3ª sub-visão de /forja/trabalho"
  - "301 das rotas antigas + remoção da perdedora, com smoke por tela afetada"
related_adrs:
  - 0368-funil-admissao-feature-pesquisa-propoe-w-admite
  - 0283-handoff-loop-zero-paste
  - 0093-multi-tenant-isolation-tier-0
  - 0253-primitivos-layout
---

# Handoff — Forja: Mesa de Aprovações, topnav em grupos, Handoffs à luz e a fusão 6a

> **Por que este handoff existe:** [W] pediu para continuar em sessão nova ("aqui tá gigante").
> 5 PRs mergeados, todos com smoke real em produção.

## O que foi entregue

| PR | Entrega | Smoke |
|---|---|---|
| [#5456](https://github.com/wagnerra23/oimpresso.com/pull/5456) | **Mesa de Aprovações** `/forja/aprovacoes` — a superfície que a ADR 0368 deixou pendente | ✅ |
| [#5469](https://github.com/wagnerra23/oimpresso.com/pull/5469) | **Topnav em 3 grupos** (Trabalho·Esteira·Histórico) + trava `UC-FORJA-14` | ✅ |
| [#5476](https://github.com/wagnerra23/oimpresso.com/pull/5476) | Fix de largura — rótulos só em `2xl` | ✅ |
| [#5479](https://github.com/wagnerra23/oimpresso.com/pull/5479) | **Handoffs vira tela** `/forja/handoffs` (ForjaMcp 694→263 ln) | ✅ |
| [#5486](https://github.com/wagnerra23/oimpresso.com/pull/5486) | **Fusão 6a** — `/forja/trabalho`, a lista única | ✅ |

**Testes na lane Forja (main, run 31291803256): `42 passed · 166 assertions`** — UC-TRAB-01..06,
UC-APROV-01..08 e UC-FORJA-01/02/05/07/14, todos com duração real (não skip).

## A decisão que trava o resto

**`US-FORJA-006`: qual implementação de backlog sobrevive.** A onda 6a criou `/forja/trabalho` e
**não deletou nem redirecionou nada** — as três antigas seguem no ar (`/project-mgmt/backlog`,
`/forja/backlog`, `/team-mcp/tasks`).

Foi deliberado: remover implementação em uso é irreversível na prática, e a US exige que [W] veja
qual sobrevive. Com as quatro no ar, a comparação é **olhando**, não lendo diff.

**Recomendação medida (e executada como base do 6a): as NATIVAS vencem.** `wc -l` em 2026-08-08 —
`Pages/Forja/Backlog` 416 · `Board` 529 · `Triage` 471 (com `casos.md` defendido por gate), contra
o cockpit `ForjaBacklog` 207 · `ForjaQuadro` 130 · `ForjaTriage` 210. O cockpit é a versão enxuta;
a intuição de "levar tudo pro cockpit" está invertida.

**Argumento novo colhido hoje:** com 13 destinos a faixa não cabe em 1512px. A fusão colapsa
Backlog+Quadro+Tarefas num item e devolve folga.

## Estado medido em produção (03:30)

`/forja/trabalho` serve **500 tasks** (o teto), agrupadas por Frente:
`— sem frente — (375)` · COPI (101) · FORJA (9) · FIN (6) · PONTO (3) · GROW (2) · INFRA (2) ·
OFFICE (1) · PROJECT (1). KPIs: **48 P0 abertas · 1 fazendo · 374 sem dono**.

As **375 sem frente** são o argumento da fusão em uma linha: ficariam invisíveis num backlog com
recorte `project=FORJA`, que mostra 9.

## Armadilhas que custaram tempo (não repita)

1. **Deploy do Hostinger falhou 2× com `ssh: Connection timed out`** no pré-check. O sintoma é
   **500 na tela nova** (PHP subiu, bundle do Vite não). Conserto: `gh workflow run deploy.yml --ref main`.
2. **`casos-gate` G-6 só diz a verdade DEPOIS do commit** — compara data-git do `.tsx` contra
   `last_run` do `casos.md`. Rodar antes de commitar passa, e o CI reprova.
3. **`static $cache` em service vaza entre testes** (e em worker de fila, que é processo longo).
   Use propriedade de instância. Foi achado pelos próprios UC-TRAB-01/06 — os testes pegaram um
   defeito de produção, não um detalhe de setup.
4. **Guards varrem comentário também** — o `layout-primitives-guard` reprovou um comentário que
   citava a classe literal do anti-padrão. Descreva sem o literal.
5. **`## Infra Contract` no PR body** é obrigatório ao tocar `routes.php`. Esqueci 2×.
6. **Ícone novo de topnav exige entrada no `TOPNAV_ICON_MAP`** (`AppShellV2.tsx`), senão o item
   aparece sem ícone. Valeu para `Gavel`, `Workflow` e `ListChecks`.
7. **As DUAS superfícies de navegação** (`config/core_topnavs.php` = shell · `FORJA_TABS` = faixa
   do hub) têm que ser atualizadas juntas. Agora há trava: `UC-FORJA-14`.

## Estado MCP no momento do fechamento

- `main` em `2c9fbc8a3d9`; deploy re-disparado com sucesso (run 31292514527).
- Nenhum PR meu aberto pendente.
- `/forja/trabalho` → 200 autenticado, 302 anônimo; controle negativo
  `/forja/rota-inexistente-xyz` → 404.
- Branch `claude/forja-trabalho-6a` já mergeada (o conteúdo está no main).

## Próxima ação verificável

Sessão nova, nesta ordem: **(1)** resolver a `US-FORJA-006` com [W] — ou executar a recomendação
e remover a perdedora com smoke por tela afetada; **(2)** 6b quadro com os 2 eixos; **(3)** 7 Gantt
como sub-visão. A seção `[BACKLOG]` do
[`Forja/Trabalho/Index.casos.md`](../../resources/js/Pages/Forja/Trabalho/Index.casos.md) é o roteiro.
