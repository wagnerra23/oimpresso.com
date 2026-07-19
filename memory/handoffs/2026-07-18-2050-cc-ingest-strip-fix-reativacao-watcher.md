---
date: "2026-07-18"
time: "20:50 BRT"
slug: cc-ingest-strip-fix-reativacao-watcher
tldr: "Pipe MEM-CC-1 estava morto por 2 causas: watcher nunca virou daemon (parou 30/abr) E o endpoint /api/cc/ingest stripava TODOS os campos (17.686 rows skeleton). Fix #4503 (live-provado) + reativação fresh-only do watcher + enrichment `model` #4517 + alarme anti-SPOF #4519. Crivo adversarial corrigiu 4 erros do meu diagnóstico inicial."
prs: [4503, 4517, 4519]
decided_by: [W]
next_steps:
  - "Confirmar que o deploy #4517 aplicou a coluna `model` no Hostinger (deploy estava pending no fechamento)"
  - "Restaurar router Traefik de mcp.oimpresso.com→oimpresso-mcp no CT100 (borda pública 404, app saudável internamente)"
  - "Consertar o passo OPcache-reset flaky do deploy (falha ~metade dos deploys; ADR 0269)"
  - "Puxar main no repo LOCAL do [W] pra o watcher passar a enviar `model` (até lá model=null)"
---

## Estado MCP no momento

⚠️ **MCP indisponível a sessão inteira** — e a causa foi DIAGNOSTICADA aqui: a **borda pública de `mcp.oimpresso.com` retorna 404 em tudo** (`/api/mcp/health`, root = 404 do Go), enquanto o container `oimpresso-mcp` serve **200 internamente** (commit `fa74cba87`). É o **Traefik** (dono do :443) sem router pro container — labels sumiram. Fallback: filesystem + `gh` + `git` + `tailscale ssh root@ct100-mcp` (também com blips 502 transitórios no fim).

## O que aconteceu

Task spawnada: investigar por que o pipe **MEM-CC-1** (`cc-search`/`whats-active`) servia dado de ~2,5 meses atrás. Diagnóstico inicial **de leitura** → submetido a **crivo adversarial** (3 céticos contexto-zero) que corrigiu **4 erros meus** (todos re-verificados firsthand depois):

1. **Olhei o watcher errado** — não é o `.cc-watcher/` da skill; é o **commitado** `scripts/cc-watcher/index.js` (aponta pra **Hostinger**, não mcp.oimpresso.com; default `--once`).
2. **"Servidor está ok" estava certo mas pelo motivo errado** — os 404 são de `mcp.oimpresso.com`; o alvo REAL do watcher é `oimpresso.com` (Hostinger), **saudável** (401 sem token = rota viva).
3. **Histórico pobre ≠ "cliente antigo"** — é bug VIVO no endpoint: `CcIngestController::ingest` usava o **retorno de `$request->validate()`** e, com `excludeUnvalidatedArrayKeys=true` (Laravel 9+), o `messages` volta **stripado só com {uuid,type}**. **Provado na DB prod:** 17.686 rows, `content_text`/`content_json`/`tokens_*` **TODOS NULL** → `cc-search` FULLTEXT **nunca teve o que buscar. MEM-CC-1 nunca funcionou.**
4. **Re-backfill NÃO recupera** — dedup por `msg_uuid` pula (sem UPDATE-path) + endpoint re-strippa + os `.jsonl` de abril **rotacionaram** (0 arquivos de abril; mais antigo 2026-06-17).

**Freeze do watcher** confirmado firsthand: DB `min_ts=11:33:26 → max_ts=11:34:54` (janela de **88s**, 30/abr) = **backfill one-shot**, nunca instalado como serviço (zero task/processo/pasta).

## Artefatos gerados

| PR | O quê | Estado |
|---|---|---|
| **[#4503](https://github.com/wagnerra23/oimpresso.com/pull/4503)** | Fix strip: lê `$request->input('messages')` cru (whitelist real = `create([...])`) + regressão era-sqlite `CcIngestPersistsFieldsTest` + registro na lane sqlite | **MERGED + deployed + LIVE-provado** (smoke autenticado: row com content+tokens) |
| **[#4517](https://github.com/wagnerra23/oimpresso.com/pull/4517)** | Enrichment `model` (G5 pricing): migration coluna `model` + fillable + controller/service + watcher `parseMessage` envia `row.message.model` | **MERGED**; deploy c/ migration **pending** no fechamento |
| **[#4519](https://github.com/wagnerra23/oimpresso.com/pull/4519)** | `IngestLivenessChecker` no `governance:audit` — alarma quando há hosts mas fresh=0 (reusa `IngestLivenessService`, não duplica; blind≠dead) + teste era-sqlite | **MERGED** |

**Reativação (fresh-only, aprovada [W]):** seed do checkpoint (444 arquivos já-vistos, sem o 1,3 GB) → `--once`: **441 skipped, 404 msgs frescas, 0 erros** → daemon `--watch` armado + **launcher no Startup** `oimpresso-cc-watcher.vbs` (persistência no logon; Task Scheduler deu Acesso Negado no contexto não-elevado).

## Persistência

- **Git:** 3 PRs merged em main (#4503/#4517/#4519). Watcher/seed = operacional local (scripts/cc-watcher commitado; `.cc-watcher-state.json` + Startup VBS = máquina do [W], gitignored-realm).
- **MCP:** indisponível (borda 404) — este handoff propaga quando o webhook GitHub→MCP voltar.
- **BRIEFING:** TeamMcp merece update (MEM-CC-1 saiu de "nunca funcionou" → "vivo") — **follow-up** (não feito p/ economia + evitar fricção memory-gate).

## Próximos passos pra retomar

```bash
# 1. Confirmar coluna model no Hostinger (deploy #4517):
tailscale ssh root@ct100-mcp "docker exec -i oimpresso-mcp php artisan tinker" <<'PHP'
fwrite(STDOUT, json_encode(['has_model'=>Schema::hasColumn('mcp_cc_messages','model')])."\n");
PHP
# 2. Traefik mcp.oimpresso.com (borda pública 404) — infra CT100.
```

## Lições catalogadas

- **O crivo adversarial pegou 4 erros de diagnóstico-de-leitura** — o §5 (2026-07-15 "achado de leitura ≠ fato") funcionou: só apresentei conclusão DEPOIS de varrer + verificar firsthand (DB prod, curl, git). Não é lição §5 nova (a disciplina foi seguida), é a prova de que ela morde.
- **2 gotchas de teste era-sqlite** (CI pegou, não local): (a) teste novo **precisa** entrar em `.github/ci-sqlite-pest.list` senão dá `markTestSkipped` nas lanes MySQL e **não roda** (falsa cobertura); (b) tabela sintética de model com `SoftDeletes` **precisa de `deleted_at`** (senão `updateOrCreate` 500a). PHPStan pegou `?? 0` redundante em `array{fresh:int,...}`.
- **`--data "$BODY"` com `\\` shell-mangla** o JSON → 422 "session obrigatório"; usar `--data-binary @file`.

## Pointers detalhados

- Diagnóstico + evidências: corpos dos PRs #4503/#4517/#4519.
- Session log narrativo: [`memory/sessions/2026-07-18-cc-ingest-strip-reativacao.md`](../sessions/2026-07-18-cc-ingest-strip-reativacao.md).
- Watcher canon: `scripts/cc-watcher/index.js` · skill `oimpresso-cc-watcher-setup`.
- Heartbeat/liveness: ADR 0278 (B-LIVE-HB) · `Modules/TeamMcp/Services/IngestLivenessService.php`.
