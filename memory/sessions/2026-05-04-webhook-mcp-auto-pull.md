---
title: Webhook MCP sync agora puxa git automaticamente + scheduler 5min como rede
date: 2026-05-04
type: session
authors: [W, Claude]
related_adrs: [0053]
related_prs: [87]
---

# Sessão 2026-05-04 — Webhook MCP sync auto-pull + scheduler 5min

## Sintoma

Wagner reportou que ADR 0069 + 2 session logs novos (sprint9-retrieval-diagnostico, auditoria-regras-concorrentes-adr0069) não apareciam via tools MCP, mesmo com tudo merged em `main` e `git push` feito. `decisions-fetch 0069-...` retornava 404; `tasks-current` retornava CURRENT.md desatualizado (versão pré-ADR-0069, ainda dizendo "Backlog completo: TASKS.md").

## Diagnóstico

Webhook GitHub→`https://oimpresso.com/api/mcp/sync-memory` chega 200 OK em ~2.5s a cada push. **Cada push gera 2 deliveries** (1 OK + 1 status_code=0 / duration 10s — investigar separadamente). Mas o problema não era o webhook em si:

1. `SyncMemoryWebhookController::handle()` chamava `new IndexarMemoryGitParaDb(repoBasePath: base_path(), ...)` direto, indexando o **filesystem da Hostinger**.
2. Filesystem da Hostinger estava em `03b70d642` (12:02 BRT) enquanto `origin/main` estava em `8ddd2bcfd` — **21 commits / 3h de drift**.
3. Sem cron `mcp:sync-memory`, sem `git pull` automático no Hostinger, sem CI/CD funcionando (`.github/workflows/quick-sync.yml` quebrada). Único caminho era SSH manual.

Confirmado que ADR 0067 (10:54), 0068 (11:29) e session ragas-baseline-infra (10:03) **estavam** no MCP — porque no momento do push deles, alguém ainda tinha SSHd manualmente. A partir das 12:02 ninguém deployou e a sincronização parou.

## Fix imediato

```bash
ssh ...hostinger... 'cd ... && git pull --ff-only origin main && php artisan optimize:clear && php artisan mcp:sync-memory --reason=manual'
# Concluído: 389 indexados (3 novos, 4 atualizados), 0 removidos, 5 redactions PII
```

ADR 0069 ficou acessível via `decisions-fetch` em seguida.

## Fix permanente (PR [#87](https://github.com/wagnerra23/oimpresso.com/pull/87))

### 1. Webhook agora puxa git antes de indexar

`SyncMemoryWebhookController` chama `sincronizarComOrigin()` antes de `IndexarMemoryGitParaDb`:

- `git fetch origin main` + `git reset --hard origin/main`
- Resposta JSON ganha bloco `git: { pulled, head, reason? }`

### 2. Detecta deploy manual necessário

Se o push tocar arquivos que exigem `composer install` / `npm run build` / `migrate`, o reset é **pulado** com `pulled: false, reason: needs_manual_deploy` — o sync ainda roda sobre o filesystem atual, mas não desnuda prod. Padrões cobertos:

```
composer.lock, composer.json,
package.json, package-lock.json, bun.lockb,
vite.config.{js,ts},
database/migrations/, Modules/*/Database/Migrations/,
public/build/
```

### 3. Scheduler 5min como rede de proteção

```php
$schedule->command('mcp:sync-memory --reason=cron')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->environments(['live'])
    ->appendOutputTo(storage_path('logs/mcp-cron.log'));
```

Cobre: webhook do GitHub falhou (timeout/network), ou alguém fez `git pull` manual e esqueceu o sync. Idempotente — só re-indexa sha mudados.

## Validação

Após merge e SSH manual de deploy desta versão (penúltima vez), trigger manual do webhook retornou:

```json
{
  "ok": true,
  "git": { "pulled": true, "head": "b4d14443e" },
  "stats": { "indexados": 389, "atualizados": 0, "novos": 0, "removidos": 0, "redactions": 5 },
  "tasks_sync": null
}
```

Próximo push (este mesmo session log) deve aparecer no MCP em <60s **sem nenhum SSH manual** — esse é o teste e2e final.

## Pendências relacionadas

- **2 deliveries por push** (1 OK + 1 timeout 10s) — investigar se é GitHub retry ou bug local
- **`.github/workflows/quick-sync.yml` quebrada** — não bloqueia mais (webhook + scheduler cobrem o gap), mas seria bom diagnosticar
- **Política de cron** — adicionar cron mestre do scheduler em INFRA.md se ainda não documentado (Hostinger via hPanel)

## Arquivos tocados

- `Modules/Jana/Http/Controllers/Mcp/SyncMemoryWebhookController.php` — pull-safe + paths perigosos
- `Modules/Jana/Tests/Feature/TaskRegistry/SyncMemoryWebhookTasksTest.php` — 4 testes novos
- `app/Console/Kernel.php` — schedule mcp:sync-memory 5min
