# 2026-05-06 — PR-9 Fase 3.7: rename DB `copiloto_*` → `jana_*`

## Contexto

Sessão de continuação após `/continuar`. Wagner respondeu "2" (executar PR-9 do ADR 0088) com 4 decisões via letras (a/c/d + ok pra view legacy):

- **(a)** Renomear classes Eloquent que carregam "Jana" no nome também
- **(b)** Manter `CREATE VIEW copiloto_*` por 30 dias (drop 2026-06-05)
- **(c)** Aceita janela ~30s downtime no deploy Hostinger
- **(d)** Executa agora (não adia pós-CYCLE-01)

## O que foi feito

### Documentação canônica

- ✅ Criada [ADR 0092](../decisions/0092-tabela-rename-copiloto-para-jana.md) — supersede §DB do ADR 0088
- ✅ ADR 0088 frontmatter: `superseded_by_section.db: "0092-tabela-rename-copiloto-para-jana"`
- ✅ MODULE-DRIFT-MIGRATION-PLAN bumped v1.2 → 1.3 com erratum §4
- ✅ SCOPE.md Jana bumped v1.2.0 → 1.3.0 (`db_tables_owned` + `db_tables_legacy_views`)
- ✅ RUNBOOK-MEMORIA-SEMANAL atualizado
- ✅ ENTERPRISE.md atualizado
- ✅ skill `copiloto-arch/SKILL.md` atualizada
- ✅ comando `.claude/commands/sync-mem.md` atualizado

### Código (47 arquivos modificados, 2 novos)

**Migration nova:**
- `Modules/Jana/Database/Migrations/2026_05_06_120000_rename_copiloto_tables_to_jana.php`
  - 13× `Schema::rename('copiloto_*', 'jana_*')` (idempotente; só renomeia se origem existe e destino não)
  - 13× `CREATE OR REPLACE VIEW copiloto_* AS SELECT * FROM jana_*` (MySQL only — gated por `isMysql()`)
  - `down()` inverso: drop views + rename inverso
  - FKs intra-Jana preservadas pelo InnoDB automaticamente

**Eloquent rename:**
- `Modules/Jana/Entities/JanaMemoriaFato.php` → `Modules/Jana/Entities/MemoriaFato.php` (`git mv` preservou history)
- Classe `JanaMemoriaFato` → `MemoriaFato`
- `protected $table = 'jana_memoria_facts'`
- `searchableAs(): 'jana_memoria_facts'`

**11 Models com `protected $table` atualizado** (`copiloto_*` → `jana_*`):
Sugestao, MetaPeriodo, MetaFonte, MetaApuracao, Meta, Mensagem, MemoriaMetrica, MemoriaGabarito, Conversa, CacheSemantico — além do MemoriaFato acima.

**~30 calls `DB::table('copiloto_*')` → `DB::table('jana_*')`** em:
- Services: ContextSnapshotService, CustosService, MetricasApurador, GabaritoEvaluator, SinteseSemanalService, ProfileDistiller, HitTrackerService, MeilisearchDriver
- Commands: ApurarMetricas, AvaliarGabarito, BackfillFatos, CacheStats, CleanupMemoria, SeedAdrs
- Controllers: QualidadeController
- Mcp Tools: MemoriaSearchTool
- Listeners: NotificarDesvioListener
- Seeders: MemoriaGabaritoSeeder
- Tests: 8 arquivos `tests/Feature/Modules/Jana/*` + 1 `Modules/Jana/Tests/Feature/Admin/CustosControllerTest.php`

**Config:**
- `Modules/Jana/Config/config.php` — default index Meilisearch `copiloto_memoria_facts` → `jana_memoria_facts`
- `Modules/Jana/Contracts/MemoriaPersistida.php` — comentário atualizado

### O que NÃO foi tocado (intencional)

- **Migrations originais `2026_04_*`** — append-only; criam `copiloto_*` na ordem cronológica (renomeadas pela nova migration)
- **`Modules/Jana/Http/Controllers/DataController.php` `copiloto_module`** — chave de menu/install, faz parte da fachada legacy mantida pelo ADR 0088
- **ADRs históricas** (0033, 0047, 0074, 0090, 0088 antiga) que mencionam `JanaMemoriaFato` — registros append-only
- **session logs históricos** (`2026-04-28-*`)
- **`memory/CHANGELOG.md`** — registro append-only
- **`tests/eval/golden-questions.yaml`** — tem `copiloto_custo_diario_business` (não está na lista das 13)

## Pendências antes de commitar

🔴 **Validação local não executada** (PHP não disponível no sandbox Claude). Wagner precisa rodar:

```bash
cd D:\oimpresso.com  # ou worktree atual
php bin/check-scope.php          # GUARDA — espera 0 drift / 29 módulos
./vendor/bin/pest tests/Feature/Modules/Jana/ --no-coverage  # SQLite in-memory
```

Caso suite Pest passe, pode commitar.

## Pós-deploy (Hostinger)

Após merge na main:

```bash
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     git pull origin main && \
     composer dump-autoload && \
     php artisan migrate --no-interaction && \
     php artisan scout:import "Modules\\Jana\\Entities\\MemoriaFato" && \
     php artisan optimize:clear'
```

Smoke prod (esperado HTTP 200):
- `/copiloto/chat`
- `/copiloto/admin/qualidade`
- `/copiloto/admin/custos`

## Plano de rollback

```bash
php artisan migrate:rollback --step=1
git revert <commit-hash>
composer dump-autoload && php artisan optimize:clear
```

Tempo estimado: ~45s (RENAME instantâneo no InnoDB).

## Drop view legacy

Planejado **2026-06-05** (30 dias). ADR sub-decisão futura ou comando `php artisan jana:drop-legacy-views`.

## Arquivos tocados (resumo `git status`)

- 47 modificados + 2 novos (ADR 0092 + migration)
- 1 rename git (JanaMemoriaFato → MemoriaFato)

## Estatísticas bulk-replace (PowerShell)

28 files processados, 27 OK, 1 NOOP (BridgeMemoriaChatTest sem ocorrências).
