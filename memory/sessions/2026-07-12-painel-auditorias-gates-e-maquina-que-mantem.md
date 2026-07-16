---
date: "2026-07-12"
topic: "Painel Auditorias & Gates na máquina matriz + conserto da 'máquina que mantém' (commit-back GH013 → auto-PR)"
authors: [W, C]
related_adrs: [0256-knowledge-survival-meia-vida-catraca-sentinela, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0298-teto-de-governanca-anti-proliferacao-gates]
---

# Sessão 2026-07-12 — Documentar o sistema de auditorias vira consertar a máquina matriz

**Worktree de build:** `system-map-gates` (branches `claude/system-map-gates`, `claude/baseline-incorpora-ancora`, `claude/system-map-refresh-via-pr`, `claude/watchdog-distiller-freshness`), todas partindo de `origin/main` fresco. O checkout da sessão estava −5077 vs main; **tudo verificado em `origin/main` live** via `git show`/`gh api`, nunca o working tree stale.

## TL;DR

Wagner pediu "documentar o sistema de auditorias". O jeito canônico não é markdown à mão — é **derivar da fonte única** (regra fonte-única [ADR 0256]). O `PAINEL-SISTEMA.md` (máquina matriz gerada por `system-map.mjs`) já existia mas **não tinha seção de gates**. Ao fechar essa lacuna, **exercitar** o sistema revelou 3 defeitos que estavam invisíveis — cada um virou um fix ancorado num padrão canônico existente. Resultado: 4 PRs, `protection-drift` **🟢 ok** em todos os eixos, e a máquina matriz agora **realmente se mantém sozinha**.

## A cadeia (4 PRs, todos merged)

| PR | O quê | Como foi descoberto |
|---|---|---|
| [#4163] | `system-map.mjs` ganha `measureGates()` + seção `## Auditorias & Gates` (25 required + censo dos 99 workflows por classe). Poda a entrada morta `cowork-inbox.yml` do registry. | O pedido original. Revisão pré-merge pegou subcontagem (ignorava o required de **ruleset** "Governance Gate") → corrigido pra contar `classic + ruleset` como o `protection-drift`. |
| [#4167] | Incorpora ao `required-checks-baseline.json` o 24º required `Ancora de design nao-shell` (promovido no vivo em #3972 · ADR 0327/0336, nunca aterrissado no baseline). | O painel expôs baseline (23) ≠ vivo (24). O `protection-drift` confirmou o 🟡 empiricamente. |
| [#4177] | O job `refresh` do `system-map.yml` passa a **abrir auto-PR** em vez de `git push` direto. | **Disparar o cron/dispatch** pra corrigir o painel stale → os runs falharam com `GH013`. |
| [#4178] | Mapeia `distiller_freshness` no `WATCHDOG_SOURCES` do `protection-drift.mjs` (aponta pra `sdd-scorecard.yml`, o agregador que a computa). | Rodar o `protection-drift` expôs o último watchdog órfão (🟡). |

Colisão de sessão paralela resolvida: o #4169 (COMECE-AQUI, outra sessão Wagner) tocava o mesmo `system-map.mjs`; integrou a `measureGates` limpo ao rebasear. O #4160 (frescor-da-órfã, outra sessão) toca o mesmo `protection-drift.mjs` em região diferente — complementar, sem conflito. Não toquei branch de sessão paralela (pegadinha catalogada).

## Lição de infra perene — commit-back direto em `main` é rejeitado (GH013)

**O que:** qualquer workflow que faça `git push` direto em `main` (branch protegido: 24 required classic + 1 ruleset + `enforce_admins`) é **rejeitado** com:
```
remote: error: GH013: Repository rule violations found for refs/heads/main.
remote: - N of N required status checks are expected.
```
O bot do Action não tem bypass. Um `[skip ci]` no commit **não** ajuda — a regra exige os checks *expected*, e o push nem passa.

**Padrão canônico (imitar · [ADR 0011]):** o job abre um **PR** via `peter-evans/create-pull-request@v7` + **`COWORK_BOT_PAT`** (não `GITHUB_TOKEN` — PR aberto pelo token default **não dispara CI**, então ficaria preso nos required), + `gh pr merge --squash --auto --delete-branch`, branch `chore/` efêmera. Já resolvido assim em `sdd-scorecard-publish.yml` e `shipped-log-cron.yml` (~jun/2026, mesmo GH013). O `system-map.yml` era o único gerador que ficou pra trás com push direto — por isso a "máquina que mantém" nunca mantinha (o cron falhava silencioso; o painel só atualizava quando um PR humano tocava as fontes).

## Método que pagou — exercitar > confiar

Nenhum dos 3 defeitos aparecia lendo o código; todos apareceram ao **rodar a máquina**: o painel expôs o drift do baseline, disparar o cron expôs o GH013, rodar o `protection-drift` expôs o watchdog órfão. Cada fix ancorou num padrão já existente (baseline `promocoes`, o auto-PR do `sdd-scorecard-publish`, o mapa `WATCHDOG_SOURCES`) — zero invenção.

## Estado final

- `PAINEL-SISTEMA.md` documenta os 25 required + 99 workflows, derivado das fontes, auto-mantido.
- `protection-drift` **🟢 ok**: required batem com o vivo, 7/7 sinais de frescor verdes.
- Máquina matriz se mantém sozinha via auto-PR no cron 07:30 BRT.

**Pendência (sessão paralela, não desta):** o #4160 segue aberto; ao mergear, conferir que o `protection-drift` continua 🟢.
