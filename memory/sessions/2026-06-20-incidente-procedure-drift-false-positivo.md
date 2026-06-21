# Incidente — `procedure_drift = DRIFT` é falso positivo (backticks do SHOW CREATE)

- **Data:** 2026-06-20 (achado 22:55 BRT via `php artisan jana:health-check` em prod Hostinger)
- **Refs:** US-COPI-092 · [Modules/Jana/Console/Commands/HealthCheckCommand.php](../../Modules/Jana/Console/Commands/HealthCheckCommand.php) `::checkProcedureDrift` (Check 6)
- **Tier 0:** banco de PRODUÇÃO. Investigação 100% READ-ONLY (nenhum ALTER/DROP/CREATE PROCEDURE).

## Sintoma

`jana:health-check` Check 6 reportou `procedure_drift = DRIFT` ("ALERTA: refresh_brief_inputs_cache divergiu da migration"). Interpretação esperada: ou edição manual da procedure em prod, ou migration não aplicada.

## Diagnóstico (root cause): FALSO POSITIVO no próprio check

A procedure **deployed em prod é byte-a-byte idêntica** à migration canônica `database/migrations/2026_05_07_120000_fix_brief_aggregator_in_flight_adrs_activity.php`. **Não há drift real** — a migration foi aplicada e o corpo bate.

O DRIFT vem de um bug no `normalize()`:
- `SHOW CREATE PROCEDURE` do MySQL devolve `CREATE DEFINER=\`…\`@\`…\` PROCEDURE \`refresh_brief_inputs_cache\`()` — nome da rotina **entre backticks**.
- A migration declara `CREATE PROCEDURE refresh_brief_inputs_cache()` — nome **sem backticks**.
- `normalize()` remove o `DEFINER=…@…` mas **deixa os backticks**. Resultado: `create procedure \`refresh_brief_inputs_cache\`()` ≠ `create procedure refresh_brief_inputs_cache()` → divergem no **char 17** (o backtick), e em mais nada.

Backtick é só quoting de identificador (nunca é drift semântico). O mesmo bug está no snapshot test `Modules/Jana/Tests/Feature/Smoke/ProcedureDriftSnapshotTest.php::normalizeProcSql` — só não pegou porque o teste é `markTestSkipped` em SQLite/CI; o caminho MySQL só roda em prod/staging.

### Prova (probe READ-ONLY em prod, replicando `checkProcedureDrift`)

```
=== CHECK REPLICA (current normalize) ===
CHECK_RESULT=DRIFT
CANON_LEN=3723 DEPLOY_LEN=3725          # diff = exatamente 2 chars = os 2 backticks
CHECK_FIRST_DIFF_AT=17                  # char 17 = `refresh_brief_inputs_cache`
CHECK_CANON_HEAD=create procedure refresh_brief_inputs_cache() begin declare v_active_cycle json; ...
CHECK_DEPLOY_HEAD=create procedure `refresh_brief_inputs_cache`() begin declare v_active_cycle json; ...
=== FIXED REPLICA (also strip backticks) ===
FIXED_RESULT=OK
FIXED_CANON_LEN=3721 FIXED_DEPLOY_LEN=3721
FIXED_FIRST_DIFF_AT=3721 (of min 3721) # zero divergência: corpos idênticos
```

## Reconciliação (a correta)

**NÃO** é migration de procedure (a procedure já está certa — rodar DDL em prod seria errado e desnecessário). O fix é **corrigir o `normalize()`**: tirar o DEFINER **primeiro** (a regex se ancora em backticks) e **depois** remover todos os backticks, dos dois lados. Aplicado em:
- `HealthCheckCommand.php::checkProcedureDrift`
- `ProcedureDriftSnapshotTest.php::normalizeProcSql`

Com o fix, drift volta a significar drift de verdade (mudança de corpo via DDL fora de migration).

## Achado secundário (incidente prod ATIVO, separado)

Durante a repro, `php artisan jana:health-check` (e **qualquer** comando artisan) está **fatalando no boot** em prod:

```
Illuminate\Contracts\Container\BindingResolutionException
Target class [Modules\Jana\Console\Commands\McpTasksOrphansCommand] does not exist.
```

Evidência (read-only):
- Arquivo `Modules/Jana/Console/Commands/McpTasksOrphansCommand.php` **existe em disco** (deploy Jun 21 02:03 UTC ≈ 23:03 BRT — logo após a run das 22:55).
- `grep -c McpTasksOrphansCommand vendor/composer/autoload_classmap.php` = **0** → classmap otimizado/authoritative **stale**.
- Registrado em `Modules/Jana/Providers/JanaServiceProvider.php` (incidente US-RB-052, hoje) → o provider tenta resolver a classe no boot e estoura.

Padrão idêntico ao incidente "prod 500 = classmap stale pós-deploy" (`git reset --hard` sem `composer dump-autoload`). **Impacto:** o cron diário `jana:health-check` (06:00 BRT) está quebrado até reconciliar o autoloader. **Remediação (precisa aprovação Wagner — Tier 0 prod):**

```bash
# após warm-up curl 5×, via receita SSH canônica (memory/reference/hostinger.md)
ssh … "cd domains/oimpresso.com/public_html && /usr/local/bin/composer dump-autoload -o 2>&1 | tail -5"
```

(`dump-autoload` regenera só o autoloader, sem mexer em pacotes — baixo risco; mesmo assim é mudança em prod → escala pro Wagner por publication-policy.)
