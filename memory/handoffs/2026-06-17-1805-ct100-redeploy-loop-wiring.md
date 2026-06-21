---
date: "2026-06-17"
time: "1805 BRT"
slug: "ct100-redeploy-loop-wiring"
tldr: "Follow-up do handoff fase0 (1659). Pos-pergunta [W] 'isso e grave?': investigacao adversarial achou + corrigi 2 incidentes — artisan Hostinger (deploy sem composer dump-autoload) e GRAVE: servidor MCP CT 100 ~19d stale (loop inalcancavel pelo endpoint). Redeployei CT 100 pro main (reset --hard + classmap via composer:2), seedei scope, grant RBAC, bindei token ao actor claude-code-wagner-laptop. handoff-pending provado end-to-end. Falta so HANDOFF_SECRET [W] pra ligar a ingestao."
decided_by: [W]
cycle: "CYCLE-08"
prs: []
us: []
next_steps:
  - "RESTA [W]: HANDOFF_SECRET no .env do Hostinger (openssl rand -hex 32) + secret do pipeline Cowork -> liga a INGESTAO (sem ele handoff:ingest aborta; o lado da LEITURA ja funciona). Opcional: rotacionar o token (baixa urgencia) + GITHUB_API_TOKEN no .env do CT 100 (stale_warning)."
  - "JA FEITO: CT 100 redeployado pro main (da31d185e) + classmap + healthy; scope seedado; grant RBAC; token id=23 bound ao actor claude-code-wagner-laptop; handoff-pending provado end-to-end no mcp.oimpresso.com."
  - "Chips: task_08140da5 (pipeline main->CT100 + sentinela de drift — causa-raiz); task_1fb6282b (deploy.yml composer dump-autoload). TeamMcp -4 ja resolvido pelo #2914."
---

> Continuação do [[handoff-loop-zero-paste-fase0]] (2026-06-17 16:59) — aquele entregou a Fase 0 código; este cobre o **wiring de prod + 2 incidentes** que surgiram quando [W] perguntou "isso é grave?".

## Contexto

Fase 0 mergeada (PRs #2904/2905/2906/2908). Ao tentar ligar o loop, o smoke contra `mcp.oimpresso.com` voltou `Unauthorized` citando `copiloto.mcp.use` (não `jana.mcp.use` do main) — sinal de que o **endpoint não tinha o código novo**. [W] pediu avaliação adversarial ("isso é grave? o que recomenda?").

## O que aconteceu (2 incidentes + redeploy + wiring)

- **Incidente 1 (Hostinger):** merges deployaram mas o `deploy.yml` **não roda `composer dump-autoload`** → classmap autoritativo não resolvia `HandoffStaleAlertCommand` → **`php artisan` quebrava no boot** (cron/console down; web OK pq `commands()` só em `runningInConsole()`). Corrigido: `composer dump-autoload -o`. Chip `task_1fb6282b`.
- **Incidente 2 (GRAVE — CT 100):** o servidor MCP (`mcp.oimpresso.com` = container Docker `oimpresso-mcp`) rodava **imagem de 29/mai (~19d stale)**; código bind-mountado (`/opt/oimpresso-mcp/code`) em `0aac033d5` com história **divergente/reescrita** (root commits diferentes — provável purge de credenciais) + 16 SPEC.md dirty. Gate ainda `copiloto.mcp.use`; **zero tools de handoff registradas no endpoint**. Causa-raiz: `deploy.yml` é Hostinger-only, **sem pipeline `main`→CT100 nem sentinela**. Chip `task_08140da5`.
- **Redeploy controlado** (autorizado [W], reversível): `git stash` (16 SPECs) → `git reset --hard origin/main` (→ `da31d185e`; `.env`/stashes preservados; rollback `0aac033d5`) → classmap regenerado via container **`composer:2` descartável** (composer não existe no host/imagem) → `docker compose up -d --force-recreate mcp` → **healthy, octane up, zero erro de classe**. **Gate-flip provado:** endpoint passou a responder `jana.mcp.use`.
- **Wiring de prod:** `McpScopesSeeder` (cria `jana.mcp.handoff.ack`, DB compartilhada Hostinger); grant `jana.mcp.use`+`handoff.ack` ao user do token (`user_id=2` = conta pessoal Wagner); **bind do token `id=23` ao actor `claude-code-wagner-laptop`** (L2 ai_agent — audit limpo, **sem gerar token novo**). **`handoff-pending` provado end-to-end** → `{handoffs:[], meta:{count:0, head_sha:null, hint}}`, `isError:false`.

## Estado final do loop

| Camada | Estado |
|---|---|
| Fase 0 código (4 PRs + scope-guard) no main | ✅ |
| CT 100 no main atual (`da31d185e`) + healthy | ✅ |
| `handoff-pending` provado no `mcp.oimpresso.com` | ✅ |
| RBAC + token bound ao actor | ✅ |
| **Ingestão** (`handoff:ingest`) | ⏳ falta `HANDOFF_SECRET` [W] |

## Lições novas

- **"merged no main" ≠ "vivo"** quando o endpoint é runtime separado sem pipeline — **smoke no ENDPOINT é obrigatório**, não confiar no merge-verde.
- O **mesmo landmine de autoload** (deploy sem `composer dump-autoload`) mordeu **dois runtimes** (Hostinger + CT 100).
- Checkout de prod com história reescrita/divergente → **`reset --hard origin/main`** (não `pull`), recuperável via reflog.
- O servidor MCP que o time usa diário estava **~19d stale silenciosamente** — dados frescos (DB compartilhada) mascaravam o código velho. Daí a sentinela de drift (chip).
- Append-only mordeu de volta: tentei "atualizar" o handoff 1659 já mergeado (#2911) → gate bloquearia; correto é **handoff novo** (este). ([[handoff-loop-zero-paste-fase0]])

## Pointers

- [[handoff-loop-zero-paste-fase0]] — handoff irmão (Fase 0 código).
- ADR 0283 (`memory/decisions/0283-handoff-loop-zero-paste.md`) — a lei.
- ADR 0062 (Hostinger ≠ CT 100 runtime) — base do gap de deploy.
- Clone de trabalho: `D:/oimpresso-teammcp`. Container MCP: `oimpresso-mcp` em `tailscale ssh root@ct100-mcp`.
