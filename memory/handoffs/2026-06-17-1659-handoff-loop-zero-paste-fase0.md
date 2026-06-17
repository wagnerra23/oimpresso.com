---
date: "2026-06-17"
time: "1659 BRT"
slug: "handoff-loop-zero-paste-fase0"
tldr: "Fase 0 do ADR 0283 (loop handoff zero-paste) mergeada: #2904 cowork_handoffs+handoff:ingest HMAC, #2905 tools handoff-pending/-ack+GitMainResolver+scope, #2906 handoff:stale-alert cron, #2908 scope-guard files_json. Adversario [AH] A1-A8 fechados; SEM auto-merge (0283). Prod: scope jana.mcp.handoff.ack seedado + incidente artisan (deploy sem composer dump-autoload) achado/corrigido. Falta wiring [W]: HANDOFF_SECRET + token ator-Code."
decided_by: [W]
cycle: "CYCLE-08"
prs: [2904, 2905, 2906, 2908]
us: []
next_steps:
  - "Wiring [W] pra LIGAR o loop: (1) HANDOFF_SECRET no .env do Hostinger (openssl rand -hex 32) + no secret do pipeline de export do Cowork — chave NUNCA passa pelo Code (0283); (2) givePermissionTo('jana.mcp.use','jana.mcp.handoff.ack') no user do ator-Code + emitir token raw (UI /team-mcp/team) e configurar no MCP client do Code."
  - "Follow-ups flagados (chips): deploy.yml sem composer dump-autoload (quebra artisan com classe nova — incidente hoje); investigar se o -4 do TeamMcp (79->75) esconde gap real D2/D6 vs so diluicao."
  - "Norte (pos-Fase-0, 0283): oraculo de CONTEUDO (dsih-gate ja existe + smoke cross-tenant) ANTES de reabrir auto-merge. scope-guard files_json ja existe mas NAO e required — [W] promove quando ligar auto-merge."
---

## Estado MCP no momento

- **Cycle:** CYCLE-08 (Receita — Onda A), 11d restantes, 61% decorrido. Este trabalho é **off-cycle** (infra de governança do loop Cowork↔Code) — coerente com o drift do brief (commits 7d não tocam tasks do cycle).
- **my-work:** 30 tasks (4 review, 8 blocked, 18 todo) — nenhuma tocada nesta sessão.
- **HANDOFF_SECRET em prod:** `nao` (Wagner seta — autoridade soberana 0283).

## O que aconteceu

Acionamento "zero-toque" colado por [W]: implementar o loop de handoff via MCP (specs Cowork por curl). **Course-correction grande logo de cara:** as specs apontavam `Modules/TeamMcp`/`McpTokenIssuer`/`brief-fetch` como "repo separado" — investiguei e **tudo vive no próprio `wagnerra23/oimpresso.com`** (o alarme "não está no repo" veio do checkout órfão `frosty-greider` com object-DB corrompido; `git ls-tree origin/main` mentia). Trabalhei num **clone limpo** `D:/oimpresso-teammcp`.

**Headline da divergência:** o bloco Cowork pedia **PR-3 auto-merge**, mas o **ADR 0283 (teu, mesmo dia) BLOQUEIA auto-merge** — humano-no-merge é estrutural pra `.tsx` multi-tenant (2 adversários acharam XSS vivo passando nos 17 checks). [W] escolheu **Fase 0** (ingest+pending+ack+scope-guard, 1-clique humano). Implementei a Fase 0, não o auto-merge.

Onde a spec divergiu do `main`, o **main venceu**: MySQL (não Postgres DDL), Tools `Laravel\Mcp\Server\Tool` registradas no `OimpressoMcpServer` (não controller HTTP + `routes/api.php`), audit via `Modules\Jana\McpAuditLog`, token GitHub em `config/services.php`, "channel ops" → notificação per-user `due_soon`.

4 PRs, cada um CI-verde, **[W] mergeou um a um** (Fase 0 = clique dele). PHPStan precisou de 3 fixes pequenos (env baseline; `request()->header()` porque `Laravel\Mcp\Request` não tem `header()`; `@property created_at` + `is_array` redundante). module-grades virou advisory −4 no TeamMcp (reconciliado pra 75 — o #2907 reconciliou em paralelo; resolvi o conflito tomando o do main).

**Incidente prod achado+corrigido:** os merges deployaram, mas o `deploy.yml` NÃO roda `composer dump-autoload` → classmap autoritativo do prod não resolvia `HandoffStaleAlertCommand` → **`php artisan` quebrava no boot** (cron/console down; web OK porque `commands()` só em `runningInConsole()`). Corrigi via `composer dump-autoload -o --no-scripts` + `optimize:clear` (artisan voltou). Depois **seedei `jana.mcp.handoff.ack`** em prod (mecânico, sem segredo).

## Artefatos gerados

- **PR-1 #2904** (`d6d29932`): migration `cowork_handoffs` (MySQL, cross-tenant) + entity `CoworkHandoff` + `HandoffIngestCommand` (HMAC `hash_equals`, append-only) + `config teammcp.handoff_secret` + 6 Pest GUARD.
- **PR-2 #2905** (`555d7977`): `HandoffPendingTool` (stale/conflict guard A4/A5, body-cap 32k A8) + `HandoffAckTool` (scope A7 via `AuthorizesMcpMutation`, gate-verde A3, idempotência, sem `Cache::flush` A2) + `GitMainResolver` (GitHub API) + scope `jana.mcp.handoff.ack` + 9 Pest GUARD.
- **PR-4 #2906** (`23519d3f`): `HandoffStaleAlertCommand` (pending>3d → inbox `due_soon` ops) + schedule daily 08:30 + 4 Pest GUARD.
- **PR-5 #2908** (`2206e22e`): `bin/check-handoff-scope.php` (files_json, `--self-test` controle-negativo) + `handoff-scope-guard.yml` + censo `gates-registry.json`.

## Persistência

- **git:** 4 PRs mergeados no `main` + este handoff (PR docs). Webhook GitHub→MCP propaga ~2min.
- **MCP:** scope `jana.mcp.handoff.ack` seedado no DB de prod (37 scopes total).
- **prod:** `cowork_handoffs` table LIVE; artisan recuperado.

## Próximos passos pra retomar

Wiring [W] (ver `next_steps` no frontmatter) → loop LIGA. Depois: o **norte** do 0283 (oráculo de conteúdo) antes de auto-merge. 2 chips de follow-up abertos (deploy dump-autoload; TeamMcp −4).

## Lições catalogadas

- **L:** checkout órfão com object-DB corrompido faz `git ls-tree origin/main` MENTIR (omite paths). Sintoma: "arquivo não existe no main" sendo que existe. Cura: clone limpo + `gh api` autoritativo. (Reforça [[licao-no-checkout-worktree-mass-delete]].)
- **L:** `deploy.yml` (git pull) sem `composer dump-autoload` quebra `artisan` em prod quando PR adiciona classe em `Modules/` referenciada em boot. Web sobrevive (`runningInConsole`), cron não. Cura no pipeline (chip).
- **L:** quando a spec contradiz a ADR ratificada do MESMO dia, a ADR vence — perguntar a [W] qual artefato é a lei (bloco Cowork agressivo × 0283 conservador).

## Pointers detalhados

- ADR 0283 (`memory/decisions/0283-handoff-loop-zero-paste.md`) — a lei da Fase 0.
- Relatório de divergência completo (local, gitignored): `D:/oimpresso-teammcp/_handoff_spec/DIVERGENCE-REPORT.md`.
- Clone de trabalho: `D:/oimpresso-teammcp` (shallow main; `_handoff_spec/` tem as specs Cowork baixadas).
