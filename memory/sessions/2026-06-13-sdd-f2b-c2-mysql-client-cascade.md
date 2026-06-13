---
date: "2026-06-13"
topic: "Re-triage pós-C1 do nightly CT 100 (run 20260613-020001): C1 funcionou (roda em MySQL real) mas a suíte segue ~1.636 falhando, NÃO os ~150-300 que a triage Q2 previu. Causa-raiz nova C2: o container do pest não tem cliente mysql, então RefreshDatabase/migrate:fresh falha o reload do dump (72× `mysql: not found`) e envenena o schema em cascata. Débito real de teste ≈ 700-900, não ~150-300."
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0062-separacao-runtime-hostinger-ct100"]
prs: []
---

# Re-triage pós-C1 — a cascata de schema (C2) que a triage Q2 não viu

> **Origem:** "abre a aba na Frente A (maior valor, lane livre, eu fico fora)" (Wagner 2026-06-13). Frente A = FV/verificação (P0 do audit, pior tema 47/100). Recon read-only sobre o nightly CT 100 + dataset autoritativo extraído do `junit.xml` do run **20260613-020001** (sha `9f48c424`, 02:00 BRT, **pós-C1 #2632**).
> **Descoberta que reframe a Fase 2b de novo:** o C1 (#2632) está correto e **pegou** — o run roda em MySQL real, as falhas sqlite (`no such table`/`near MODIFY`) sumiram. Mas o número **mal mexeu** (1.521 → 1.636). A previsão da triage Q2 ("C1 derruba pra ~150-300") está **refutada pelos dados**. O grosso do vermelho é uma **segunda** falha de harness, não débito de teste.

## 1. Estado medido (run pós-C1, autoritativo via junit.xml)

**Run:** `20260613-020001` · sha `9f48c424` · 02:00 BRT · **MySQL real** (13 stamps `Connection: mysql`, zero sqlite).
**Totais:** 10.478 testcases · **passed 7.086 · failed 385 · errors 1.251 · skipped 1.756** → **1.636 falhando.**

Comparado ao pré-C1 (`50db892`: 1.521 falhando) → C1 trocou o eixo da falha (sqlite→MySQL) mas **não reduziu** o volume.

### Split por tipo de exceção (1.636)

| Tipo | n | % | Natureza |
|---|---:|---:|---|
| `QueryException` | 849 | 52% | DB: tabela/coluna/constraint |
| `ExpectationFailedException` | 382 | 23% | asserts reais (stale test OU bug) |
| `ErrorException` | 103 | 6% | PHP undefined/warning |
| `BindingResolutionException` | 81 | 5% | `session`/`db.schema`/`Cache\Factory` não resolvem |
| `ProcessFailedException` | 72 | 4% | testes que dão shell-out |
| `Error` / `TypeError` / `CommandNotFound`… | ~150 | 9% | código/registro |

### QueryException (849) aberto

- **463 `Base table or view not found`** — top: `business` **173**, `activity_log` 72, `users` 42, `arquivos_audit_log` 22, `nfe_*` ~33, `vestuario_settings` 14, `service_orders` 10, `mcp_actors` 9…
- **116 foreign key** · **113 `Unknown column`** (top: `business_id` 39, `updated_at` 12, `numero` 11, `phone_e164` 7, vários `deleted_at`).

## 2. Causa-raiz C2 (provada) — schema envenenado pelo migrate:fresh sem cliente mysql

`business`/`users`/`activity_log` **estão** no dump (`database/schema/mysql-schema.sql`, 364 `CREATE TABLE`) e **existem** após o setup (7.086 testes passam). Sumirem **no meio da suíte** só fecha de um jeito:

1. Testes com **`RefreshDatabase`** disparam `migrate:fresh`, que roda **"Loading stored database schemas"** — e isso invoca o **CLI `mysql`** pra recarregar o dump.
2. A imagem `oimpresso/mcp` **não tem** o client mysql/mariadb (`sh: mysql: not found` confirmado via `docker run … mysql --version`). **Mesma limitação que o passo 4 do harness já contornava no setup** — mas o migrate:fresh disparado **por teste** não tinha contorno.
3. **Prova:** `72× "mysql: not found"` no `pest-out.txt` do run. Cada um = um `migrate:fresh` que **dropou** as 364 tabelas do baseline e **não recarregou** → cascata `Base table not found` no resto da suíte.
4. Corroboração independente: o `tests/Pest.php` do módulo **RecurringBilling** já tem band-aid criando `activity_log`+`contacts` na mão num `beforeEach` — sintoma localizado do **mesmo** bug sistêmico.

→ **O nightly pós-C1 mede ruído de harness, não débito de teste.** Estimativa: C2 zera o grosso dos 463 table-not-found + boa parte dos 116 FK / 113 unknown-column / 81 binding (`db.schema`/`session` derivam do fresh quebrado) ≈ **~600-700 falhas**.

## 3. Fix C2 (esta PR — `sdd/f2b-c2-mysql-client-pest`)

`scripts/tests/ct100-fullsuite.sh` passo [6]: o `docker run` do pest passa a instalar `mariadb-client` (`apk add`, provê `/usr/bin/mysql`) no container descartável **antes** do `exec php … pest` — rede docker-host e creds `DB_*` já anexadas. RefreshDatabase/migrate:fresh volta a recarregar o dump em vez de envenenar. RUNBOOK ganha linha de troubleshooting C2. **Não toca `phpunit.xml` global** (sqlite segue correto no CI rápido Unit). Pura mudança de harness (sem dev, sem prod, ADR 0062).

## 4. Número real corrigido (a mensagem pro time)

Removido o ruído C1 (sqlite) **e** C2 (schema poisoning), o que sobra é débito **real**:

- ~**382** `ExpectationFailedException` (asserts) — mistura de **stale tests** (quarentena Q-A/Q-B da triage Q2: snapshot UI Sells superseded, canon-source DS) e **bugs de produto** confirmados (§3 da triage Q2: `GLOB_BRACE`, `ads:health`, `$i_`/NullDriver, `strpos`, **FSM fail-secure**).
- ~**150** erros de código (`Error`/`TypeError`/`ErrorException`/`CommandNotFound` — ex `governance:detect-drift` 9, `whatsapp:health-probe-channels` 6, `whatsapp:reconnect-and-import` 5, `ads:health` 2: comandos não registrados ou shell-out falho).
- residual DB que não for C2.

**Débito de teste real ≈ 700-900, NÃO ~150-300.** Quarentena dos 178 sozinha **não** leva a suíte ao verde — é ~5× maior do que a triage otimista estimou.

## 5. Próximos passos (ordem, pós-merge desta PR)

1. **Deploy + re-run** do nightly com C2 (prova + 1º número limpo de harness). Esperado: 1.636 → casa dos **700-900**.
2. **Re-triar os sobreviventes** (382 ExpectationFailed + ~150 código) separando **stale→quarentena** de **bug→dev**. Só aqui o batch Q-A/Q-B da triage Q2 faz sentido.
3. **CommandNotFound** (`governance:detect-drift`, `whatsapp:*`): checar registro no ServiceProvider (mesmo padrão do bug `ads:health`).
4. Burn-down por módulo (B1/B2…) **sobre o número limpo**, não sobre o ruído.

> ⚠️ **Implicação pro scorecard (ADR 0275):** `full_suite_pass_rate` não deve declarar baseline antes do run pós-C2 — o 020001 ainda é regime sujo de harness. O 1º baseline honesto é o run pós-merge desta PR.

**Segurança/PII:** READ-ONLY no CT 100 (SSH key-based). Nenhum CPF/CNPJ/email/nome extraído — só estrutura (SQLSTATE, nomes de tabela/coluna, FQCN, contagens). Dataset local em `run/fv-triage/` (gitignored).
