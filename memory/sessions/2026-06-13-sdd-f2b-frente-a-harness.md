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

### A.1 — reload do dump quebrado no migrate:fresh [confiança alta — fix em 3 partes]
A imagem `oimpresso/mcp` (FROM `php:8.4-fpm-alpine`) tem **`pdo_mysql`** mas **não o binário CLI `mysql`/`mariadb`**. Quando um teste com `RefreshDatabase`/`migrate:fresh` roda "Loading stored database schemas", o Laravel invoca o CLI `mysql` pra reaplicar `database/schema/mysql-schema.sql` → **`mysql: not found`** (72× no run `20260613-020001`) → o fresh dropa as 364 tabelas e **não recarrega** → `Base table or view not found` (variável 529–688 entre runs).

⚠️ **O par adversarial (ADR 0276) REFUTOU a v1 (só instalar o client):** com `/usr/bin/mysql` presente, o comando exato que o Laravel emite (`mysql --user --password --host --port --database < dump`, **sem flag de ssl**) falha com **`ERROR 2026 Certificate verification failure`** — o mariadb-client verifica TLS por default contra `mysql-workers`. O dump segue sem recarregar; o erro só muda de "not found" pra "TLS cert". (O PDO dos testes conecta porque `sslmode=prefer` cai em não-verificado; a CLI verifica.)

**Fix (3 partes):** (1) `mariadb-client` no `docker/oimpresso-mcp/Dockerfile` (durável — exige rebuild via `bootstrap-ct100.sh`); (2) `apk add mariadb-client` no `docker run` do pest (fallback imediato); (3) **`/etc/my.cnf.d/zz-fullsuite-no-ssl-verify.cnf` com `ssl-verify-server-cert=0`** escrito no container do pest, pra o `mysql < dump` conectar. Provado isolado no CT100: bare load → `ERROR 2026`; com o `.cnf` → `ok`. Só no container efêmero do nightly (não entra na imagem de prod).

### A.2 — teardown sem FK-off [mecanismo correto; quantificação corrigida pelo adversário]
`Schema::dropIfExists()` em beforeEach/setUp de ~210 testes era-sqlite estoura **errno 3730** ("Cannot drop ... referenced by FK") — NÃO o `dropAllTables` em batch do migrate:fresh (que já faz FK-off). Stack confirma o último frame ser o próprio teste (ex `SlashLembrarTest.php:43` dropa `messages` mas nunca a filha `whatsapp_jana_correcoes`). A base `Tests\TestCase` usa só `CreatesApplication+WithSeededTenant` (sem rollback, sem FK-off); em MySQL **persistente** a filha sobrevive e o drop da pai falha.

**Fix:** `Tests\TestCase::setUp()` faz `SET SESSION FOREIGN_KEY_CHECKS=0` **só quando `getenv('FULLSUITE_FK_OFF')==='1'`** (env do passo 6) e só em mysql/mariadb. **Escopado ao nightly** — CI/local não setam a flag, FK segue **ON** (não mascara FK nos gates required). _O agente propôs FK-off global; escopei ao nightly — divergência consciente, mais segura._

**Correções do par adversarial (ADR 0276) — verdict `partial`, mecanismo OK mas a alegação estava inflada:**
- **"508" → 254 testes.** 508 são menções raw (o junit grava a msg 2×: atributo + CDATA); são **254 `<testcase>` com erro 3730**.
- **Não é determinístico:** 3730 são `<error>` (eixo ERROR), que oscila por run (003042=254, 020001=**0**, 074432=68) → vive na **banda 1514–2197, não no floor de 1514**. A.2 corta **ruído de ERROR**, não o piso.
- **A.1 e A.2 são ACOPLADOS, não independentes** (corrige o que esta sessão dizia antes): quando A.1 envenena o schema primeiro, parte dos 254 vira `Base table not found` em vez de 3730 (run 020001: 0× 3730 mas 973 base-table). O delta isolado de A.2 só é mensurável **com A.1 já corrigido**.
- O mecanismo (FK-off no setUp pros testes que bindam `Tests\TestCase`) está **correto e validado em probe isolado** (todos os 55 ofensores reais bindam `Tests\TestCase`; nenhum módulo tem TestCase próprio; nenhum dropa em conexão nomeada).

### A.3 — migrations PSR-4-broken [REFUTADA — adversário confirmou, verdict `survives`]
As migrations classe-nomeada que o composer pula ("does not comply with psr-4 ... Skipping") são **100% pré-dump** (cutoff `2026_06_04`): já estão no `mysql-schema.sql` **e** registradas como rodadas no `INSERT INTO migrations` (820 rows, max id=849). O `migrate --force` nunca tenta rodá-las; roda só as pendentes, **todas classe-anônima**. O "table/column not found" mid-run é efeito da A.1, **não** do skip. **A.3 move o floor em 0** → no-op; dívida latente (futura nomeada pós-dump) vira backlog.
O par adversarial confirmou byte-level (correções cosméticas: são **266** classe-nomeadas no repo, todas registradas no INSERT; **0** nomeadas > cutoff; as 15–20 pendentes são todas `return new class`). Veredito: **sobrevive**.

## Validação (pendente — nenhum run exercitou o fix ainda)
**Nenhum dos 3 runs (003042/020001/074432) tem A.1/A.2** — o clone do CT 100 está em `5d56bbd2` (pré-merge) e a imagem deployada ainda não tem o binário. **O floor 1514 é o baseline do estado QUEBRADO.** A redução é **predição até medir**: re-rodar o nightly após A.1+A.2 (interseção de ≥2 runs com seed fixo). **NÃO redisparar com flock ativo.** A.1 ataca o `Base table not found` (variável 529–688) + os 72 `mysql: not found`; A.2 corta ~254 erros 3730 (ruído de ERROR, banda 1514–2197). Resíduo fora do harness: Frente B (#2636 ✅), Frente C (era-sqlite) e ~385 ExpectationFailed + ~105 app-bugs.

## Par adversarial (ADR 0276 · ledger)
Workflow `wqompm50q` (3 refutadores, sessão fresca, reprodução read-only no CT 100):
- **A.1 → REFUTADO → corrigido.** Só instalar o client trocava `mysql: not found` por `ERROR 2026 TLS cert`. Fix v2 adicionou o `.cnf` de ssl-verify-off (provado: bare load TLS-fail → ok). _Sem o adversário, a Frente A teria mergeado sem recarregar o dump._
- **A.2 → PARCIAL → claim corrigido.** Mecanismo OK; números (508→254), determinismo (é ruído de ERROR, não floor) e independência (A.1 preempta A.2) reescritos acima.
- **A.3 → SOBREVIVE.** No-op confirmado byte-level.

## Notas
- Esta PR **subsume #2638** (que fazia só o `apk` do A.1) — #2638 fechado em favor desta.
- Foundation-ratchet advisory vermelho = **baseline drift pré-existente** (`n_refresh_database` +2 de outros PRs); esta PR não adiciona uso real (comentário reescrito pra não citar o token).

**Segurança/PII:** diagnose READ-ONLY no CT 100 (SSH key-based); zero PII extraída (só SQLSTATE, nomes de tabela/coluna, FQCN, contagens). Repo público.
