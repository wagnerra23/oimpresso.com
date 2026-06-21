---
topic: "Decisões T4 (bi-temporal Jana) e T5 (hash-chain audit log) da Onda 1 IA OS — pré-ADR"
name: 2026-06-20-t4-t5-decisoes-tier0
description: Decisoes arquiteturais T4 (bi-temporal Jana) e T5 (hash-chain audit log) da Onda 1 IA OS — Wagner delegou ("faca o melhor"); pre-ADR para ratificacao
type: session
date: "2026-06-20"
related_adrs: [0074-temporal-validity-bi-temporal-time-travel, 0084-triggers-mysql-imutabilidade-mcp-audit-log, 0092-tabela-rename-copiloto-para-jana, 0093-multi-tenant-isolation-tier-0, 0062-separacao-runtime-hostinger-ct100]
---

# T4 / T5 — Decisoes Tier-0 (pre-ADR, para ratificacao do Wagner)

> Contexto: na sessao 2026-06-20 Wagner pediu pra fechar as pendencias da Onda 1 e, sobre os 2 itens Tier-0, disse "eu nao sei as respostas, faca o melhor". Este doc registra as DECISOES que eu (Claude) tomei como delegado + o plano. Como sao Tier-0 (audit imutavel + memoria multi-tenant), o **codigo so deve ser escrito apos a decisao virar ADR ratificada** — append-only, governanca da casa. Os blueprints completos vieram da reconferencia adversarial (workflow wznwj303g).

## Risco transversal que vale pros DOIS (resolver primeiro)

**NAO existe job `PHP / Pest (Jana)` no CI.** O `modules-pest.yml` (matrix) nao inclui `Jana`, e o `ci.yml` (Pest Unit) e allowlist sem os testes novos. Logo, qualquer teste Pest de T4/T5 roda **verde no local e invisivel no CI** — exatamente o anti-padrao "a suite mente". **Pre-requisito de ambos:** adicionar `Jana` ao matrix do `modules-pest.yml` (lane MySQL, pra exercitar os triggers) OU ancorar os testes na allowlist do `ci.yml`. Sem isso, nao mergear nenhum dos dois.

---

## T5 — Hash-chain tamper-evident no `mcp_audit_log`

**DECISAO (minha, delegada): cadeia GLOBAL unica.**

**Por que global e nao por-business:** `mcp_audit_log` e tabela de **governanca cross-tenant** (`business_id` NULLABLE; CLI/superadmin gravam `biz=null`). O valor da tamper-evidencia num audit forense e detectar **qualquer** adulteracao — inclusive exclusao de linha cross-tenant. Cadeia por-business e mais simples (nao precisa furar o global scope), mas **nao detecta** delecao cross-tenant — derrota o proposito. Entao: cadeia global.

**Custo da decisao (mitigar no codigo):** ler o `hash_anterior` (ultima linha N-1) exige `withoutGlobalScopes()` com comentario `// SUPERADMIN: cadeia global de auditoria, ver ADR T5` — senao o global scope (ADR 0093) filtra por tenant e **quebra a cadeia entre business**.

**Plano (segue o padrao JA PROVADO de `Modules/Ponto/Services/MarcacaoService.php`):**
1. ADR nova (status `proposed`): semantica global + ordenacao por `id` + payload canonico + tratamento de linhas legadas (`hash=null`).
2. Migration idempotente: add `hash` CHAR(64) null + `hash_anterior` CHAR(64) null em `mcp_audit_log`. **Nunca** backfillar linhas antigas por UPDATE — bate no trigger `trg_mcp_audit_log_no_update` (SIGNAL 45000, ADR 0084). Legadas ficam `hash=null`.
3. Writer central `McpAuditLog::registrar()` (descobrir o ponto de escrita real): dentro de `DB::transaction` + `lockForUpdate` no SELECT do N-1 → **mitiga a corrida** (dois INSERTs concorrentes lendo o mesmo N-1 e bifurcando a cadeia).
4. `AuditChainService` copia o algoritmo do Ponto (`payloadCanonico` a prova de campo faltando = string vazia; `hash('sha256', hash_anterior . payload)`); `verificarIntegridade()` percorre e detecta quebra, tolerando a transicao legado(null)→novo.
5. **Failsafe:** o `try/catch` best-effort dos call-sites do audit NAO pode engolir excecao do calc de hash silenciosamente (audit sumir sem rastro). Calc tolerante a campo faltando.
6. Teste Pest (biz=1, ADR 0101): cadeia integra; adulteracao de 1 payload → `verificarIntegridade` acusa; INSERT concorrente nao bifurca; legado null nao quebra. **+ wire Jana no CI** (risco transversal acima).

**Risco:** ALTO (audit forense Tier-0). Bug aqui degrada a GARANTIA de integridade — mas nao corrompe os dados de audit em si (so os metadados de cadeia, e detectavel). Postura: **PR pra review do Wagner, sem auto-merge.**

---

## T4 — Bi-temporal event-time da memoria Jana (ADR 0074)

**DECISAO (minha, delegada): ACEITAR o ADR 0074 (flip `proposto`→`accepted`) e shipar fatiado.** O ganho e real (Zep/Graphiti: +18.5% acc em "knowledge updates", onde LLMs caem ~30%), o desenho ja existe, e e append-only (baixo risco de corrupcao).

**Pegadinhas que mandam no plano (do adversario):**
- **Tabela:** ADR 0074 e a migration original dizem `copiloto_memoria_facts`, mas **ADR 0092 renomeou pra `jana_memoria_facts`** (com VIEW legacy). `ALTER` na VIEW falha — confirmar `Schema::hasTable('jana_memoria_facts')` primeiro.
- **Scout/Meilisearch:** `shouldBeSearchable()` so indexa `valid_until=NULL`. Time-travel busca fatos **superseded** (fora do index) → `buscarHistorico()` **DEVE usar Eloquent/SQL direto, nunca Scout** (senao volta vazio).
- **Multi-tenant (ADR 0093):** `buscarHistorico()` e a deteccao automatica filtram `business_id` (+`user_id`); a tool `MemoriaHistoricaTool` replica a checagem cross-tenant superadmin de `MemoriaSearchTool.php`.
- **Custo LLM:** deteccao Haiku = 1 chamada extra por fato → **nasce atras de flag `config('jana.memoria.supersede_detection.enabled', false)` DEFAULT OFF** (flag OFF = job byte-identico ao legado). Confirmar `ANTHROPIC_API_KEY` no worker da fila `copiloto-memoria`.
- **Append-only:** `atualizar()` ja marca `valid_until` + cria novo — **NAO** converter pra UPDATE in-place. `supersedes_id` so torna o link explicito. Migration nao dropa dados; sem backfill retroativo (nao-decisao deliberada do ADR).
- **Indices MySQL <=64 char:** nomear explicitamente (`jmf_event_validity_idx`, `jmf_supersedes_idx`).

**Plano fatiado (PRs <=300 linhas):**
1. Flip ADR 0074 `proposto`→`accepted` (Wagner ratifica) + confirmar tabela/key/worker.
2. PR1: migration (`event_valid_from`/`event_valid_until`/`supersedes_id`) + entity casts + `atualizar()` grava `supersedes_id` + teste schema/cadeia.
3. PR2: tool MCP `memoria-historica` (time-travel `as_of`) + `buscarHistorico()` Eloquent + teste.
4. PR3: deteccao automatica de update temporal (agent Haiku) atras da flag OFF + job + teste. **Liga so em homolog.**

**Risco:** MEDIO. Postura: **PRs pra review, sem auto-merge** (Tier-0-adjacente: memoria multi-tenant + custo LLM novo + flip de ADR).

---

## Recomendacao de sequencia

1. Wagner ratifica as 2 decisoes (este doc → 2 ADRs `proposed`).
2. Fechar a lacuna de CI (Jana no Pest matrix) — **bloqueante** dos dois.
3. T5 (menor, ~2h, padrao provado) primeiro; depois T4 fatiado em 3 PRs.
4. Nenhum auto-merge — todo PR Tier-0 passa pelo seu olho.
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
