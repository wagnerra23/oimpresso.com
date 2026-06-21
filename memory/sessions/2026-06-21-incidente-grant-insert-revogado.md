---
date: "2026-06-21"
hour: "01:30 BRT"
topic: "Incidente prod: GRANT INSERT/UPDATE revogado do usuário de DB (Hostinger) — 17 checks verdes com toda gravação morta; write-canary adicionado ao health-check"
authors: [W, C]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
outcomes:
  - "Causa-raiz: usuário de DB de prod sem privilégio INSERT/UPDATE (MySQL 1142)"
  - "Lacuna de detecção fechada: check db_write_canary (duro) no jana:health-check"
---

# Incidente — GRANT INSERT/UPDATE revogado em prod (2026-06-21)

## Resumo

Durante uma auditoria de saúde de rotina (`php artisan jana:health-check` ao vivo em
prod via SSH), o gate falhou (exit 1) por `profile_distiller_drift = 3`. Ao investigar,
descobri que o distiller falha em **76/76 businesses** — e a causa-raiz **não é o
distiller**: o usuário de DB de produção **`u906587222_oimpresso` perdeu os privilégios
`INSERT` e `UPDATE`** (e `CREATE`/`INDEX`). Toda gravação do app web no Hostinger falha
com MySQL **1142** (`SQLSTATE[42000] ... command denied`), mascarado como "Syntax error
or access violation".

## Linha do tempo (BRT)

- **20/jun ~22:45–22:50** — últimas escritas bem-sucedidas (`activity_log` 22:50,
  `mcp_dual_brain_decisions` 22:45). Onset do bloqueio.
- **20/jun 23:00** — primeiras negações no `laravel.log`.
- **21/jun 01:03** — `jana:health-check` ao vivo: exit 1, mas só `profile_distiller_drift`
  acende (sintoma), nada aponta pra escrita.
- **21/jun ~01:1x** — diagnóstico: `SHOW GRANTS` confirma ausência de INSERT/UPDATE;
  `mcp_dual_brain_decisions` acumulou **~8.377** negações; ~8.9k no total em ~2h.

## Evidência

```
GRANT SELECT, DELETE, DROP, REFERENCES, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES,
      EXECUTE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER,
      ... ON `u906587222_oimpresso`.* TO `u906587222_oimpresso`@`localhost`
```

Sem `INSERT`, `UPDATE`, `CREATE`, `INDEX`. O grant é **database-wide** → atinge **todas**
as tabelas uniformemente. Tabelas que ainda recebem escrita (`mcp_briefs` 23:02,
`mcp_audit_log` 02:32) vêm de **outra conexão/usuário** (MCP server CT 100, runtime
separado — [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).

Tabelas com negação registrada (amostra): `mcp_dual_brain_decisions` (8377), `jobs` (66 —
**fila não enfileira**), `mcp_audit_log`/`error_groups` (131/131), `channel_health_snapshots`
(60), `essentials_attendances` (13 — **ponto**), `woocommerce_sync_logs` (12), `channels`,
`mcp_inbox`, `mcp_brief_inputs_cache`.

## Por que passou despercebido (o buraco real)

Os 17 checks do `jana:health-check` são **SELECTs** (multi-tenant, PII, drift) ou
**degradam gracioso** quando uma tabela falta. **Nenhum** provava **privilégio de
escrita**. Resultado: tudo verde com prod meio-morto — o anti-padrão **"a suíte mente"**
(mesma classe da auditoria de sentinelas de 20/jun: monitor verde com o fluxo morto).

## Correção

1. **Grant (P0 — humano, hPanel):** o usuário não tem `GRANT OPTION` e não há root
   MySQL no shared hosting; **só dá pra restaurar no hPanel Hostinger** (MySQL Databases
   → privilégios de `u906587222_oimpresso` → ALL, ou ao menos INSERT/UPDATE/CREATE/INDEX),
   ou via ticket. Tudo a jusante (distiller, ledger ADS) **auto-cura** depois.
2. **Guard (este PR):** check **`db_write_canary`** (DURO) no `jana:health-check` —
   INSERT de prova numa tabela dedicada (`jana_health_write_canary`) dentro de uma
   transação **sempre revertida** (não persiste linha, não toca tabela de negócio).
   Escrita morta passa a **derrubar o exit code + ALERT do cron 06:00**. Predicado puro
   `isWriteDenied()` (1142/command denied) + bite-test. **Ordem:** o fix do grant vem
   ANTES do deploy deste guard (a própria migration precisa de `CREATE`, que o mesmo
   grant revoga).

## Pendências

- Reconciliar 3 IDs em `spec_id_drift` (US-WA-002/010/045 — título DB ≠ SPEC.md). **Bloqueado**
  pelo grant (é `UPDATE mcp_tasks`) e precisa do MCP conectado. (US-RECURRINGBILLING-001 é
  falso-positivo conhecido — `RecurringBilling/SPEC.md` linha 12 documenta o ID legado.)
- Investigar **o que** revogou o grant ~22:50 (ação no hPanel? reset Hostinger? deploy?).
