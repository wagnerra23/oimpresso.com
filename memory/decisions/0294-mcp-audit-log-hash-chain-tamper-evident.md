---
slug: 0294-mcp-audit-log-hash-chain-tamper-evident
number: 294
title: "mcp_audit_log tamper-evident por hash-chain SHA-256 (cadeia global) — transplante do padrao Ponto, emenda 0084"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-20"
module: jana
tags: [jana, mcp, audit, tamper-evidence, hash-chain, forense, lgpd, tier-0]
supersedes: []
superseded_by: []
related:
  - 0084-triggers-mysql-imutabilidade-mcp-audit-log
  - 0093-multi-tenant-isolation-tier-0
  - 0053-mcp-server-governanca-como-produto
---

# ADR 0294 — `mcp_audit_log` tamper-evident por hash-chain (cadeia global)

> Origem: auditoria do IA OS 2026-06-20 (gap #10 — "audit log append-only mas NAO tamper-evident"). Decisao delegada por Wagner ("faca o melhor") e ratificada verbalmente ("vai") em 2026-06-20; este PR e a aceitacao formal.

## Contexto

`mcp_audit_log` (ADR 0053) e o registro forense de toda chamada MCP. A ADR 0084 ja garante **append-only** via triggers MySQL (`trg_mcp_audit_log_no_update`/`no_delete` → `SIGNAL 45000`). Mas append-only **detecta UPDATE/DELETE no banco — nao detecta adulteracao por quem tem acesso a desligar/recriar o trigger, restaurar de backup adulterado, ou editar fora do MySQL**. Falta a camada de **tamper-evidence**: provar que a sequencia de linhas nao foi alterada.

O projeto JA TEM esse padrao provado em `Modules/Ponto/Services/MarcacaoService` (Portaria MTP 671/2021): cada linha grava `hash = H(payload_canonico | hash_anterior)`, formando uma cadeia; `verificarIntegridade()` recalcula e detecta qualquer divergencia. T5 transplanta esse padrao para o audit log — **reuso, nao invencao**.

## Decisao

1. **Cadeia GLOBAL unica** (nao por-business). `mcp_audit_log` e tabela de **governanca cross-tenant** (`business_id` NULLABLE — CLI/superadmin gravam `biz=null`). O valor forense da tamper-evidencia e detectar **qualquer** adulteracao, **inclusive exclusao de linha cross-tenant**. Cadeia por-business seria mais simples (sem furar o global scope) mas **nao detectaria** delecao cross-tenant — derrota o proposito. Por isso: cadeia global, ordenada por `id` (ordem de insercao).
   - **Custo aceito:** ler o `hash_anterior` (ultima linha) exige `withoutGlobalScopes()` comentado `SUPERADMIN` — senao o global scope (ADR 0093) filtra por tenant e **quebra a cadeia entre business**. Esta e a unica excecao ao scope, restrita e documentada.

2. **Migration so ADITIVA** (`hash`, `hash_anterior` CHAR(64) nullable). **NUNCA** backfill de linhas legadas por UPDATE — bateria no trigger 0084. Linhas pre-0294 ficam `hash=null`; o verificador **tolera o prefixo legado** e ancora a cadeia na primeira linha com hash.

3. **Escrita pela factory unica `McpAuditLog::registrar()`** (todos os 4 call sites ja passam por ela): dentro de `DB::transaction` + `lockForUpdate` no SELECT do N-1 → **mitiga a corrida** (dois INSERTs concorrentes lendo o mesmo N-1 e bifurcando a cadeia).

4. **FAILSAFE absoluto:** os call sites do audit sao `try/catch` best-effort. Se o calc de hash estourasse, o audit **sumiria sem rastro**. Por isso `AuditChainService::hash()`/`payloadCanonico()` **nunca lancam** (campo faltando → `''`), igual ao `payloadCanonico` do Ponto.

5. **Payload canonico forense:** so os campos que definem a IDENTIDADE do evento (`request_id`, `user_id`, `business_id`, `ts`, `endpoint`, `tool_or_resource`, `status`, `error_code`, `mcp_token_id`, `hash_anterior`). Custo/tokens/duration ficam de fora (volateis, nao-forenses).

## Consequencias

- **+** Adulteracao de payload, exclusao de linha ou recriacao do log passam a ser **detectaveis** por `AuditChainService::verificarIntegridade()`.
- **+** Reuso de padrao provado (Ponto) — risco de design baixo.
- **−** Serializa parcialmente os INSERTs do audit (lock na ultima linha). Aceitavel: audit nao e hot-path e correcao forense > throughput.
- **−** Uma excecao `withoutGlobalScopes` documentada (cadeia global). Mitigado por comentario `SUPERADMIN` + este ADR.
- **Lacuna de CI fechada junto:** nao existia job `Pest (Jana)` no CI → teste ficaria verde-local-invisivel ("a suite mente"). Este trabalho adiciona `Jana` ao `modules-pest.yml`. O teste do hash-chain e **logica pura** (sem DB) → roda no CI SQLite; o trigger MySQL real e exercitado no CT 100.

## Alternativas consideradas

- **Cadeia por-business:** rejeitada — nao detecta exclusao cross-tenant (proposito forense).
- **Assinatura assimetrica / Merkle / WORM externo:** overkill pro estagio; hash-chain ja cobre o gap. Reavaliar se houver requisito de prova perante terceiro.
- **Backfill das linhas legadas:** impossivel sem violar o trigger append-only (0084). Prefixo legado tolerado e a escolha correta.

## Implementacao

- Migration `2026_06_20_000001_add_hash_chain_to_mcp_audit_log` (aditiva, idempotente).
- `Modules/Jana/Services/Mcp/AuditChainService` (payloadCanonico/hash/verificarCadeia puros + verificarIntegridade DB).
- `McpAuditLog::registrar()` passa a encadear dentro de transacao+lock.
- `Modules/Jana/Tests/Unit/AuditChainServiceTest` (logica pura, roda no CI).
- `modules-pest.yml`: `Jana` no matrix + paths.
