---
date: "2026-06-13"
topic: "US-GOV-018 Frente A — fix do harness de DB do nightly (3 sub-causas): A.1 mariadb-client (imagem+harness), A.2 FK-off só-no-nightly no TestCase, A.3 refutada (coberta por A.1). Meta: floor 1514 → centenas."
authors: [W, C]
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
prs: []
---

# US-GOV-018 Frente A — consertar o harness de DB do nightly full-suite

> **Origem:** "abre a aba na Frente A (maior valor, lane livre, eu fico fora)" → Frente A = harness de verificação (P0 Fase 2b). Brief canônico: `memory/requisitos/Governance/SPEC.md#US-GOV-018` (retest adversarial por reprodução byte-a-byte, 3 skeptics). Diagnose+spec via workflow de 3 threads read-only sobre os runs CT 100 `20260613-003042` e `20260613-020001`.
> **Número honesto (do SPEC):** floor determinístico **1514** (interseção test-a-test de 2 runs MySQL code-equivalentes); banda 1514–2197 (o eixo que oscila é ERROR → ruído infra de DB, confirma a causa). NÃO é "schema incompleto" (o dump tem as 364 tabelas) — é harness.

## As 3 sub-causas (medidas) e o que esta PR faz

### A.1 — imagem sem cliente mysql [~850 falhas, confiança alta]
A imagem `oimpresso/mcp` (FROM `php:8.4-fpm-alpine`) tem a extensão **`pdo_mysql`** mas **não o binário CLI `mysql`/`mariadb`**. Quando um teste com `RefreshDatabase`/`migrate:fresh` roda "Loading stored database schemas", o Laravel invoca o CLI `mysql` pra reaplicar `database/schema/mysql-schema.sql` → **`mysql: not found`** (72× no run `20260613-020001`) → o fresh dropa as 364 tabelas do baseline e **não recarrega** → cascata `Base table or view not found` (`business` 173×, `activity_log` 72×, `users` 42×).
**Fix (duplo):** (a) `mariadb-client` no `docker/oimpresso-mcp/Dockerfile` (durável — exige rebuild+deploy da imagem via `bootstrap-ct100.sh`); (b) `apk add mariadb-client` no `docker run` do pest em `ct100-fullsuite.sh` (fallback imediato, no-op se a imagem já tiver). Prova isolada: `command -v mysql` BEFORE=none → AFTER=`/usr/bin/mysql`. Risco zero (client read-only ~172 KB; o MCP usa `pdo_mysql`, não o binário).

### A.2 — teardown sem FK-off [508 "Cannot drop", confiança alta]
Os 508 `drop table if exists` que estouram **errno 3730** ("Cannot drop ... referenced by a foreign key constraint") vêm de **`Schema::dropIfExists()` em beforeEach/setUp de ~210 testes era-sqlite** — NÃO do `dropAllTables` em batch do migrate:fresh (que já faz FK-off). Stack confirma o último frame ser o próprio teste (ex `SlashLembrarTest.php:43` dropa `messages` mas nunca a filha `whatsapp_jana_correcoes` que tem FK→`messages`). A base `Tests\TestCase` usa só `CreatesApplication+WithSeededTenant` (sem RefreshDatabase, sem FK-off); em DB MySQL **persistente** a tabela-filha sobrevive e o drop da pai falha. **A.1 NÃO resolve A.2** (mecanismo independente).
**Fix:** `Tests\TestCase::setUp()` faz `SET SESSION FOREIGN_KEY_CHECKS=0` **só quando `getenv('FULLSUITE_FK_OFF')==='1'`** (env setado no passo 6 do harness) e só em driver mysql/mariadb. Restaura o comportamento sqlite histórico (esses 210 testes já rodavam sem FK), **escopado ao nightly** — CI/local NÃO setam a flag, então FK segue **ON** lá e nenhum bug de FK fica mascarado nos gates required. (O agente propôs FK-off global no TestCase; escopei ao nightly via env pra proteger os gates de CI — divergência consciente, mais segura/reversível.)

### A.3 — migrations PSR-4-broken [REFUTADA como contribuinte do floor]
As ~232 migrations classe-nomeada que o composer pula ("does not comply with psr-4 ... Skipping") são **100% pré-dump** (cutoff `2026_06_04`): já estão materializadas no `mysql-schema.sql` **e** registradas como rodadas no `INSERT INTO migrations` (820 rows). O `migrate --force` do harness nunca tenta rodá-las (roda só as 13 pós-dump, **todas classe-anônima**). O "table/column not found" mid-run é efeito da A.1 (teardown sem reload), **não** do skip. **Converter as 232 move o floor em 0 e é risco alto** (1 PR = 1 intent + cada `down()` é chance de typo). Portanto A.3 = **no-op** nesta PR; a dívida latente (uma futura migration nomeada pós-dump quebraria o migrate de verdade) vira backlog separado de higiene, não bloqueia.

## Validação
Re-rodar o nightly após A.1+A.2 e medir o novo floor (interseção de ≥2 runs com seed fixo). **NÃO redisparar enquanto houver run com flock ativo** (run `20260613-074432` estava vivo). Esperado: floor cai de 1514 pra a casa das centenas; o resíduo é Frente B (#2636, `config_json`, mergeada), Frente C (testes era-sqlite, sub-onda) e ~385 ExpectationFailed + ~105 app-bugs (dívida real, fora do harness).

## Notas
- Esta PR **subsume #2638** (que fazia só o `apk` do A.1 no harness) — #2638 fechado em favor desta.
- Foundation-ratchet advisory está vermelho por **baseline drift pré-existente** (`n_refresh_database 69→71` por outros PRs em main); esta PR não adiciona RefreshDatabase.

**Segurança/PII:** diagnose READ-ONLY no CT 100 (SSH key-based); zero PII extraída (só SQLSTATE, nomes de tabela/coluna, FQCN, contagens). Repo público.
