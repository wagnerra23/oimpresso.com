---
date: "2026-06-21"
hour: "01:30 BRT"
topic: "Incidente prod: GRANT INSERT/UPDATE revogado do usuário de DB (Hostinger) — 17 checks verdes com toda gravação morta; write-canary adicionado ao health-check"
authors: [W, C]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0061-conhecimento-canonico-git-mcp-zero-automem
outcomes:
  - "Causa-raiz: cota de disco do DB estourada (6180/6144 MB) → Hostinger auto-revogou INSERT/UPDATE"
  - "Estouro por mcp_memory_documents_history (4.97 GB, over-versioning); dropada (git é canônico) → ALL PRIVILEGES auto-restaurado; DB 5788→816 MB"
  - "Guards (PR #3125): db_write_canary + db_storage_quota (duros) no jana:health-check"
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

## Causa-raiz REAL (atualizado 2026-06-21)

O grant não foi "revogado por alguém" — foi **automático**. O DB bateu a **cota de disco
do plano**: `6180 / 6144 MB` (100%+). Quando isso acontece, o **Hostinger auto-revoga
INSERT/UPDATE/CREATE/INDEX** (deixa SELECT/DELETE/DROP pra dar como liberar espaço) e
**re-restaura sozinho** assim que volta a ficar abaixo da cota.

O que estourou a cota: **`mcp_memory_documents_history` = 4.97 GB** (296.586 linhas para
1.042 docs atuais ≈ 285 versões/doc), inflada por over-versioning do sync git→MCP (grava
snapshot a cada webhook). Sozinha era 85% do banco. Cada linha tem `git_sha` + `content_md`
→ **redundante com o git**, fonte canônica das memórias ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).

## Resolução (2026-06-21 ~09h BRT)

1. `DROP TABLE _bkp_fin_titulos_20260602` (backup datado do resync num_uf, ~80 MB).
2. `SHOW CREATE TABLE` salvo → `DROP TABLE mcp_memory_documents_history` (0.2s, libera
   ~4.97 GB). DB: **5788 → 816 MB**.
3. Hostinger **auto-restaurou `ALL PRIVILEGES`** no ato (confirma a causa-raiz).
4. Tabela recriada **vazia** (DDL idêntico + FK `ON DELETE CASCADE`).
5. `jana:profile-distill --only-stale` → **76 ok / 0 falha**; health-check **`ok: true`**
   (profile_distiller_drift e spec_id_drift zerados).

## Guards adicionados (PR #3125)

- **`db_write_canary`** (DURO) — INSERT de prova em tabela dedicada (`jana_health_write_canary`),
  transação sempre revertida (não persiste linha, não toca negócio). Pega o SINTOMA
  (escrita morta) no cron 06:00. Predicado puro `isWriteDenied()` (1142/command denied).
- **`db_storage_quota`** (DURO) — alerta quando o DB passa de `jana.db_quota_warn_pct`
  (90%) da cota (`jana.db_quota_mb`, default 6144). Pega a CAUSA **antes** do provedor
  cortar. Predicado puro `dbQuotaExceeded()`. Ambos com bite-tests.

## Fase 2 — mover memória do MCP pro CT 100 (planejado)

Arquiteturalmente os `mcp_memory_documents*` são dados do MCP server (CT 100, [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)),
hoje no MySQL do Hostinger só por conexão remota — foi esse acoplamento que deixou o bloat
da memória derrubar o ERP. Plano:
1. Subir DB no CT 100 (storage do Proxmox, sem a cota apertada de shared hosting).
2. Repointar a config de memória do MCP pro DB do CT 100.
3. ~~Corrigir o over-versioning~~ → **NÃO há bug.** O sync (`IndexarMemoryGitParaDb`) já
   deduplica por `content_md`, e `McpMemoryDocument::snapshotEAtualizar()` só grava em
   mudança real. Era **falta de teto**, não escrita espúria. (verificado no código)
4. Retenção (N versões/doc OU ≤90d) — **FEITO** ([PR #3130](https://github.com/wagnerra23/oimpresso.com/pull/3130)): comando
   `jana:memory-history-prune` (diário 03:20 BRT, keep 20 + janela 90d, driver-agnóstico).

Refill atual é lento (~170 MB/semana → **meses** até reincomodar), então não é urgente.

## Update (fechamento da sessão — 2026-06-21 ~11:30 BRT)

- A `mcp_memory_documents_history` refez ~120 MB / 8.400 linhas após o drop da manhã;
  **re-truncada → 0** (DB voltou a ~816 MB). O `jana:memory-history-prune` (#3130) impede
  reincidência. (O screenshot 6180/6144 que o [W] mandou era anterior ao drop da manhã.)
- **Fase 2 mover pro CT 100** virou **proposta ADR gated** ([PR #3131](https://github.com/wagnerra23/oimpresso.com/pull/3131),
  `memory/decisions/proposals/2026-06-21-mcp-memory-store-ct100.md`): opção (a) conexão
  `memory_ct100` recomendada; runbook de cutover; emenda ao 0062; **aguarda decisão [W]**.

## Pendências

- **Fase 2 CT 100** = decisão do Wagner na proposta #3131 (não-urgente; teto #3130 já segura).
- **Headroom durável**: a conta tem 14 bancos; o oimpresso é só 937 MB do total — os ~5 GB
  restantes estão em **bancos legados** que meu user SQL não enxerga (perfex/wr2/crm/…).
  Limpar os defuntos no hPanel é o ganho real de espaço (decisão [W] — dados de outros sistemas).
- `spec_id_drift` zerou sozinho pós-fix (parser #3124 + sync voltando); monitorar.
  (US-RECURRINGBILLING-001 segue falso-positivo conhecido em `RecurringBilling/SPEC.md`.)
