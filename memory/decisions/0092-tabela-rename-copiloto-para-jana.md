---
slug: 0092-tabela-rename-copiloto-para-jana
number: 92
title: "Tabela rename copiloto_* → jana_* (PR-9 da Fase 3.7 — renumerada de 0090 pra 0092 por conflito monotônico ADR 0028)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_at: 2026-05-06
decided_by: [W]
module: copiloto
quarter: 2026-Q2
supersedes: []
supersedes_partially: ["0088-module-rename-php-only"]
related: ["0084", "0087", "0088"]
authors: [wagner, claude]
---

# ADR 0092 — Tabela rename `copiloto_*` → `jana_*` (PR-9 da Fase 3.7 — renumerada de 0090 pra 0092 por conflito monotônico ADR 0028)

## Contexto

ADR 0088 decidiu **rename PHP-only** dos módulos Copiloto/PontoWr2/MemCofre, deixando 7 dimensões da fachada legacy (URLs, permissions, config, log, Pages React, lang, **DB**) como opt-in evolution. PR-9 era a dimensão DB — marcada *"alta dor, default manter legacy"*.

Em 2026-05-06 Wagner invocou PR-9 explicitamente (*"mude tu para manter o contexto correto inclusive tabelas"*) com 4 decisões:

- **(a)** Renomear classes Eloquent que carregam "Copiloto" no nome também (ex: `CopilotoMemoriaFato` → `MemoriaFato`)
- **(b)** Manter `CREATE VIEW copiloto_* AS SELECT * FROM jana_*` por 30 dias (fallback ad-hoc)
- **(c)** Aceita janela de ~30s downtime no deploy Hostinger
- **(d)** Executa agora (não adia pós-CYCLE-01)

Levantamento confirmou:

| Item | Quantidade |
|---|---|
| Tabelas a renomear | 13 |
| Foreign keys internas Jana→Jana | 6 (intra-módulo, preservadas pelo `RENAME TABLE`) |
| Models Eloquent c/ `protected $table = 'copiloto_*'` | 11 |
| Classe Eloquent c/ "Copiloto" no nome | 1 (`CopilotoMemoriaFato`) |
| `DB::table('copiloto_*')` em services/commands/controllers/tools | ~30 calls |
| Tests Pest com `DB::table('copiloto_*')` ou `Schema::create('copiloto_*')` | 6 arquivos |
| Triggers MySQL append-only afetados | **0** (semântica é app-level via `SoftDeletes` + `valid_until`) |

Triggers reais existem em `mcp_audit_log`, `ponto_marcacoes`, `licenca_log` — nenhuma é `copiloto_*`. Risco eliminado.

## Decisão

**Renomear 13 tabelas DB** `copiloto_*` → `jana_*` num **PR único big-bang**, com:

1. 1 migration nova `2026_05_06_*_rename_copiloto_tables_to_jana.php` que executa:
   - 13× `Schema::rename('copiloto_xxx', 'jana_xxx')` (metadata-only no InnoDB; FKs preservadas automaticamente)
   - 13× `CREATE OR REPLACE VIEW copiloto_xxx AS SELECT * FROM jana_xxx` (fallback ad-hoc 30 dias)
   - `down()` inverso (DROP VIEWS + RENAME inverso)
2. **1 rename de classe Eloquent**: `CopilotoMemoriaFato` → `MemoriaFato` (renomeia arquivo + classe + namespace usages + Scout `searchableAs()`)
3. **10 atualizações de `protected $table`** nos demais Models (sem renomear classe — não tinham "Copiloto" no nome)
4. **Bulk-replace** `'copiloto_'` → `'jana_'` em ~30 calls `DB::table('copiloto_*')` espalhadas por services/commands/controllers/tools/tests/seeders
5. **Migrations originais NÃO tocadas** (append-only histórico) — continuam criando `copiloto_*`; nova migration RENAME executa em sequência
6. **Drop view legacy planejado para 2026-06-05** (30 dias) via ADR sub-decisão futura ou comando `php artisan jana:drop-legacy-views`

## Justificativa

**Por que big-bang num PR.** As 13 tabelas têm FKs intra-módulo (Jana → Jana) — fragmentar quebra integridade referencial mid-flight. `RENAME TABLE` no MySQL InnoDB é metadata-only (instantâneo, FKs preservadas). Logo, downtime esperado ≈ tempo de `php artisan migrate` + `optimize:clear` no Hostinger ≈ 30s.

**Por que view legacy 30 dias.** Custo zero (view = metadata MySQL). Fallback gratuito pra Wagner/Eliana fazerem queries SQL ad-hoc com nomes antigos durante a transição. Drop é trivial (1 comando) quando confiança operacional consolidar.

**Por que renomear `CopilotoMemoriaFato` (única classe afetada).** As outras 10 classes (`Meta`, `Conversa`, `Mensagem`, etc.) já têm nomes neutros — só `protected $table` muda. Manter `CopilotoMemoriaFato` na nova arquitetura `Modules\Jana\` seria dissonância nominal residual.

**Por que NÃO migrations originais reescritas.** Migrations são histórico append-only. Reescrever quebraria checksum em ambientes que já rodaram (todos). `RENAME` em migration nova é o pattern correto.

**Por que NÃO dual-write transitório.** Overkill — 13 tabelas, todas pequenas-a-médias (maior provavelmente `copiloto_mensagens` com algumas dezenas de milhares). RENAME é instantâneo. Dual-write adiciona ~12-20h trabalho + risco de divergência sem benefício real.

**Por que view legacy não inclui INSERT/UPDATE.** Views MySQL com `SELECT *` de tabela única são updatable por default — mas explicitamente NÃO queremos write-through legacy. Aplicação só escreve em `jana_*`. View é read-only de fato (qualquer escrita externa em `copiloto_*` cai num erro de unique/integridade na tabela renomeada). Aceitável.

## Cascade Review (cumprindo §10.4)

| Camada | Auditada | Resultado | Ação |
|---|---|---|---|
| L4 Compliance (LGPD) | ✅ sim | `SoftDeletes` em `jana_memoria_facts` preserva semântica `esquecer()` | OK |
| L5 Module Charter | ✅ sim | SCOPE.md Jana bumped (mention rename DB no histórico) | aplicar |
| L7 Audit | ✅ sim | Migration nova é append-only no histórico; FKs preservadas pelo `RENAME TABLE` | OK |
| Plano canônico | ✅ sim | MODULE-DRIFT-MIGRATION-PLAN v1.2→1.3 com erratum §4 (PR-9 executado, falta PR-3..8) | aplicar |
| Triggers MySQL | ✅ sim | 0 triggers em tabelas afetadas; `mcp_audit_log`/`ponto_marcacoes`/`licenca_log` intocadas | OK |
| Foreign Keys | ✅ sim | 6 FKs intra-Jana preservadas automaticamente pelo `RENAME TABLE` (InnoDB) | OK |
| Scout / Meilisearch index | ⚠️ parcial | Index name muda de `copiloto_memoria_facts` → `jana_memoria_facts`; histórico fica orfão até reimport | seguir com `php artisan scout:import` pós-deploy |
| Tests Pest | ✅ sim | 6 arquivos tocados (Schema::create + DB::table); RefreshDatabase roda nova migration | aplicar |
| ADR 0088 | ✅ sim | Status mudará pra "Superseded by 0090 §DB" | aplicar |
| GUARDA `bin/check-scope.php` | ✅ sim | Não rastreia tabelas — só módulo/owner; passa intacto | OK |
| Auto-load PSR-4 | ✅ sim | Rename só de 1 classe (`CopilotoMemoriaFato`) — `composer dump-autoload` post-deploy garante | aplicar |
| Webhook GitHub / watchers | ✅ sim | URL intocada; webhook só lê markdown — DB rename não afeta | OK |

## Consequências

**Positivas:**

- **Backend semanticamente coerente** — `Modules\Jana\Entities\MemoriaFato` opera em `jana_memoria_facts`. IDE search por "Copiloto" não retorna mais false-positives na Jana.
- **View legacy 30d permite rollback simples** — qualquer código terceiro (script SQL, BI ad-hoc) que assuma `copiloto_*` continua lendo até 2026-06-05.
- **Padrão reusável pra próximas migrations** — quando Wagner decidir mover URLs (`/copiloto/*` → `/jana/*`) ou permissions, mesma estratégia "código novo + view/redirect legacy 30d" aplicável.

**Negativas / Trade-offs:**

- **Janela ~30s downtime** durante deploy Hostinger — tempo entre `php artisan migrate` (RENAME executado) e `optimize:clear` (autoload + view cache). Requests no intervalo retornam 500. Aceitável (Wagner aprovou).
- **Meilisearch index órfão** — `copiloto_memoria_facts` no Meilisearch fica sem dono após deploy; `php artisan scout:import "Modules\Jana\Entities\MemoriaFato"` reimporta no nome novo. Manual obrigatório pós-deploy.
- **Drop view 2026-06-05 não está agendado em scheduler** — depende de Wagner abrir comando manual ou ADR sub-decisão. Risco de "view virar permanente" se esquecer.

**Riscos mitigados:**

- FKs internas Jana→Jana preservadas pelo `RENAME TABLE` InnoDB (MySQL semantica garantida desde 5.5.46).
- Triggers append-only não afetadas (não há trigger em tabelas Jana).
- ROTA LIVRE biz=4 (5993 clientes) intocado — só prefixo de tabela, dados preservados byte-a-byte.
- Tests Pest CI continuam verdes — RefreshDatabase roda nova migration; setUp tests com `Schema::create` atualizados pra `jana_*`.

## Implementação

✅ **Executado em PR-9 da Fase 3.7 (commit a definir):**

1. Migration `2026_05_06_HHMMSS_rename_copiloto_tables_to_jana.php` (RENAME + VIEWs)
2. `Modules/Jana/Entities/CopilotoMemoriaFato.php` → `MemoriaFato.php` (rename arquivo + classe)
3. 11 Models: `protected $table` atualizado pra `jana_*`
4. `searchableAs()` no `MemoriaFato` retorna `'jana_memoria_facts'`
5. ~30 `DB::table('copiloto_*')` substituídos por `DB::table('jana_*')` em:
   - `Modules/Jana/Services/{ContextSnapshotService,CustosService,ProfileDistiller,HitTrackerService,GabaritoEvaluator,MetricasApurador,SinteseSemanalService}.php`
   - `Modules/Jana/Console/Commands/{ApurarMetricas,AvaliarGabarito,BackfillFatos,CacheStats,CleanupMemoria,SeedAdrs}.php`
   - `Modules/Jana/Http/Controllers/Admin/QualidadeController.php`
   - `Modules/Jana/Mcp/Tools/MemoriaSearchTool.php`
   - `Modules/Jana/Listeners/NotificarDesvioListener.php`
   - `Modules/Jana/Database/Seeders/MemoriaGabaritoSeeder.php`
   - `tests/Feature/Modules/Copiloto/{TenancyLeak,SemanticCacheService,MetricasApurador,ApuracaoIdempotencia,BridgeMemoriaChat,MemoriaMetrica}Test.php`
   - `Modules/Jana/Tests/Feature/Admin/CustosControllerTest.php`
6. SCOPE.md Jana bumped + plano canônico v1.2→1.3
7. ADR 0088 status atualizado pra "Superseded by 0090 §DB"
8. RUNBOOK-MEMORIA-SEMANAL.md atualizado

**Pós-deploy Hostinger / CT 100:**

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
git pull origin main
composer dump-autoload
php artisan migrate --no-interaction
php artisan scout:import "Modules\Jana\Entities\MemoriaFato"
php artisan optimize:clear
```

## Plano de Rollback

Se algo quebrar pós-merge:

```bash
php artisan migrate:rollback --step=1
# down() executa: DROP VIEW copiloto_*; RENAME jana_xxx → copiloto_xxx
git revert <commit-hash>  # reverte código também
composer dump-autoload
php artisan optimize:clear
```

Tempo estimado de rollback: ~45s (RENAME é instantâneo, view drop também).

## Quando reabrir

- Drop das views legacy (planejado 2026-06-05 ou quando confiança operacional permitir)
- Algum descobrir queries SQL bruto fora deste levantamento usando `copiloto_*` direto

## Referências

- [ADR 0088 — Module rename PHP-only](0088-module-rename-php-only.md) (predecessor)
- [ADR 0084 — Triggers MySQL append-only](0084-triggers-mysql-append-only-mcp-audit.md)
- [ADR 0087 — Drift resolution sem mover URL](0087-drift-resolution-sem-mover-url.md)
- [MODULE-DRIFT-MIGRATION-PLAN v1.3](../governance/MODULE-DRIFT-MIGRATION-PLAN.md) §4
- [Session log 2026-05-06 PR-9](../sessions/2026-05-06-pr-9-tabela-rename-copiloto-jana.md)
