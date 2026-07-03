---
date: "2026-07-03"
time: "14:19 BRT"
slug: onda-produto-capterra-inventario-spec
tldr: "Onda standalone Produto (Passos 1+2): CAPTERRA-FICHA capacidade 61/100 + INVENTARIO (✅6/🟡11/❌1) + SPEC novo (G-04) com 7 US. PR #3729 merged; 7 US-PROD materializadas no mcp_tasks via cron sync. Cliente já estava pronto (parallel) — não dupliquei."
prs: [3729]
decided_by: [W]
related_adrs: [0089-capterra-driven-module-evolution, 0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente]
next_steps: ["Executar US-PROD-020 (casos.md + revisar SPEC) — pré-req que desbloqueia 021-026", "Considerar próximo módulo da fila (Compras já tem sessão paralela ativa)"]
---

# Handoff — Onda Produto (Passos 1+2 do programa de ondas)

## TL;DR

Onda standalone Produto: **Passo 1** FICHA de capacidade **61/100** (a `module-grade 71` de UX esconde kardex de fachada, multiplicador de preço oco, 8 telas draft/0 live, sem SPEC, zero prova de valor) + **Passo 2** INVENTARIO (✅6/🟡11/❌1) + **SPEC novo** (G-04) com 7 US. PR #3729 merged; os 7 US-PROD-020..026 materializaram no `mcp_tasks` via `mcp:tasks:sync`. Cliente já estava pronto (parallel) — não dupliquei. Próximo: US-PROD-020 (pré-req).

## Estado MCP no momento

- **cycles-active:** timeout (MCP) — não capturado; off-cycle (onda é standalone, `parent_plan=programa-ondas`).
- **my-work (@wagner):** 30 tasks ativas; **US-PROD-020/021 já aparecem em TODO** (materializadas nesta sessão).
- **tasks-list Produto:** 7 US (US-PROD-020..026), todas `todo`, blocked_by US-PROD-020.
- **decisions-search:** nenhuma ADR nova criada nesta sessão.

## O que aconteceu

Rodei o adversário de mercado (`capterra-senior`, Opus) sobre o módulo **Produto** e apliquei o ciclo-padrão de ondas: Passo 1 (FICHA capacidade **61/100**) + Passo 2 (INVENTARIO + criação do **SPEC que faltava**, gap G-04). Achado central da §8: a `module-grade 71` (UX das telas) esconde a capacidade real — kardex de fachada (StockHistory 47 só linka Blade), multiplicador de preço oco (`mult=1.00`), 8 telas draft/0 live, sem SPEC, zero prova de valor. Wagner aprovou o batch ("ok pode fazer") com 2 refinamentos (custo médio = SPIKE primeiro; React = finalizar+promover draft→live) e mandou mergear.

`tasks-create` não registra módulo core novo (Produto não está em `mcp_jira_projects`), então as 7 US foram **escritas no SPEC** — o caminho canônico: `mcp:tasks:sync` (cron 10min + webhook) parseia o SPEC e materializa em `mcp_tasks`. **Confirmado pós-deploy.**

## Artefatos gerados

- `memory/requisitos/Produto/CAPTERRA-FICHA.md` (232 linhas) — capacidade
- `memory/requisitos/Produto/CAPTERRA-INVENTARIO.md` (66 linhas) — buckets + 7 tasks
- `memory/requisitos/Produto/SPEC.md` (novo) — capacidades em prose (§2) + 7 US backlog
- `memory/sessions/2026-07-03-onda-produto-passos-1-2.md` — session log
- Todos mergeados via **PR #3729** (`6c08d97`), CI 70/70 verde.

## Persistência

- **git:** #3729 merged em `main`.
- **MCP:** 7 US-PROD no `mcp_tasks` (via `mcp:tasks:sync`); docs no `mcp_memory_documents` (webhook).
- **BRIEFING:** Produto/BRIEFING.md já existe; não editei (é read-only research, sem mudança de capacidade real).

## Próximos passos pra retomar

`tasks-detail task_id=US-PROD-020` → executar (casos.md + revisar SPEC). É o pré-req que desbloqueia US-PROD-021..026. Ordem sugerida depois: US-PROD-021 (kardex real) → US-PROD-022 (multiplicador, ⚠️Tier0).

## Lições catalogadas

- **Criar SPEC novo é pesadamente gated.** 3 gates mordem: (1) ghost-ratchet casa o literal `Modules/<Mod>` mesmo em negação → nunca escrever o token pra um módulo core que não tem pasta; (2) anchor-lint v1 rejeita âncora com class-name/glob/parenthetical (vira path morto) + exige DoD+teste em US "done"; (3) doneness rejeita status≠âncora. Fix limpo: capacidade pronta = prose; US nova = `todo` sem âncora (zona-cinza advisory). Validar `anchor-lint --check-entry --check-covers` + `doneness-lint --check` localmente ANTES do push.
- **Verificar duplicação antes de spawnar** salvou retrabalho: Cliente já estava 100% pronto (parallel session #3732/#3742).

## Pointers detalhados

- Session log (narrativa completa + percalços CI): [2026-07-03-onda-produto-passos-1-2.md](../sessions/2026-07-03-onda-produto-passos-1-2.md)
- Pipeline SPEC→MCP: `Modules/Jana/Console/Commands/McpTasksSyncCommand.php` + `app/Console/Kernel.php:797` (cron) + `SyncMemoryWebhookController` (US-TR-004).
