---
title: "Memória do MCP (mcp_memory_documents*) sai do MySQL Hostinger para MariaDB no CT 100 — desacopla o storage da memória do ERP"
status: proposed
date: "2026-06-21"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0060-tudo-rede-interna-proxmox-bye-hostinger
  - 0058-reverb-substituido-por-centrifugo-frankenphp
origem: "Incidente 2026-06-21: mcp_memory_documents_history inflou pra 4.97 GB, estourou a cota de disco do MySQL Hostinger e o provedor AUTO-REVOGOU INSERT/UPDATE → toda a escrita do ERP morreu por ~horas. Wagner: 'se possível levar para CT 100, lá deveria ser o lugar dela.'"
---

# Memória do MCP → MariaDB no CT 100

> **Status: PROPOSTA — gated.** Não executar o cutover sem aprovação do Wagner + acesso Tailscale ao CT 100. Toca o recall/memória vivo (Tier 0).

## Contexto

As tabelas `mcp_memory_documents` (docs canônicos, ~1.042 linhas) e `mcp_memory_documents_history` (histórico bitemporal) são **dados do MCP server** — que roda no **CT 100** (Proxmox, FrankenPHP/Octane/Centrifugo/Meilisearch · [ADR 0058](../0058-reverb-substituido-por-centrifugo-frankenphp.md)). Mas hoje elas vivem fisicamente no **MySQL do Hostinger** (`u906587222_oimpresso`), junto com o ERP — o MCP server se conecta **remoto** a esse banco (`docker/oimpresso-mcp/.env`: `DB_HOST=srv1818.hstgr.io`).

Esse **acoplamento físico** foi a causa do incidente 2026-06-21: o bloat da memória (5 GB) estourou a cota de disco compartilhada e o Hostinger revogou a escrita do **ERP** (não da memória). A Fase 2/1 já pôs um teto preventivo na tabela (`jana:memory-history-prune`, PR #3130); esta proposta resolve a **raiz arquitetural**: tirar a memória do MCP da cota do ERP.

## Decisão proposta

Mover `mcp_memory_documents` + `mcp_memory_documents_history` (como **par atômico**, pra preservar a FK CASCADE intra-servidor) para um **MariaDB 11 dedicado no CT 100**, via uma **conexão Laravel `memory_ct100`** (Opção **a**). Os Models de memória passam a usar essa conexão.

> ⚠️ **Emenda explícita ao [ADR 0062](../0062-separacao-runtime-hostinger-ct100.md):** hoje o 0062 diz "CT 100 não escreve no DB do app; o app não escreve no DB do CT". Esta decisão **inverte** isso para um caso específico governado — o app Hostinger passa a escrever num DB do CT 100. A proposta autoriza essa exceção.

## Por que a memória do MCP pertence ao CT 100

- O MCP server (recall, `decisions-search`, `kb-answer`, sync) roda no CT 100.
- Meilisearch (índice da memória) já está no CT 100.
- O storage do Proxmox não tem a cota apertada de 6 GB do shared hosting.
- Desacopla de vez: bloat de memória nunca mais ameaça a escrita do ERP.
- Alinha com a direção do [ADR 0060](../0060-tudo-rede-interna-proxmox-bye-hostinger.md) (peso pesado no CT 100).

## Alternativas consideradas

- **(b) MCP dono com DB próprio + app lê só via API HTTP** — destino arquitetural mais limpo (alinha com o MCP-como-produto, ADR 0053), mas é refactor grande: hoje o app Hostinger lê `mcp_memory_documents` direto via Eloquent em vários pontos (`McpServerHealthReader`, KB bridge, reconcilers). Trocar tudo por HTTP + criar fallback de disponibilidade do MCP (princípio duro 8) é muito mais trabalho. **Adiada**, com gatilho: quando o app sair do Hostinger (Opção A do 0060) ou o MCP virar produto multi-cliente.
- **Só purga agressiva no Hostinger** — não resolve a causa (acoplamento físico); só adia o próximo estouro. É o que o PR #3130 já faz como defesa em profundidade, não como solução da raiz.
- **Mover só `_history` (a maior)** — quebra a FK CASCADE cross-server. Rejeitada.

## Pegadinhas verificadas no código

- **FK `history.document_id → documents.id` (ON DELETE CASCADE)** sobrevive **só se as duas tabelas ficarem na mesma conexão/servidor** (InnoDB FK é intra-servidor). Por isso migram como par atômico.
- **FK cross-table que ninguém espera:** `kb_nodes.source_doc_id → mcp_memory_documents.id` (`fk_kb_nodes_source_doc`, ON DELETE SET NULL). `kb_nodes` é tabela do **ERP** e **não move**. Essa FK física **tem de ser dropada** e rebaixada a FK lógica (precedente: `mcp_task_memory_links` já é lógica).
- **Prod é MariaDB 11.8, não MySQL** (`docker/oimpresso-staging/seed-from-prod.sh` usa `mariadb-dump`). O DB de memória no CT 100 deve ser **MariaDB 11.x** pra casar collation (utf8mb4_unicode_ci) + os CHECK `json_valid()`. Usar `mariadb-dump`, não `mysqldump`.
- **CT 100 ainda NÃO tem MySQL/MariaDB** — é o que esta migração precisa provisionar (o `oimpresso-staging-db` já provou que o CT 100 roda MariaDB 11).

## RUNBOOK de cutover (gated)

Legenda: 🔴 = exige Tailscale + Wagner (CT 100/prod). 🟢 = local/CI.

**Pré-condição:** o reclaim de bloat (drop+recreate) já ocorreu — as tabelas estão pequenas.

**Fase A — Preparação (sem downtime, reversível)**
1. 🟢 Extrair o DDL canônico das 2 tabelas das migrations `2026_04_29_100008/100009_*` + ALTERs.
2. 🔴 Provisionar **MariaDB 11** no CT 100 (`oimpresso-memory-db`, volume dedicado, só LAN/Tailscale, backup diário + snapshot Proxmox).
3. 🔴 Criar schema vazio das 2 tabelas no CT 100 **com** a FK history→documents e **sem** a FK kb_nodes.

**Fase B — Carga inicial**
4. 🔴 `mariadb-dump --single-transaction --skip-lock-tables --no-tablespaces u906587222_oimpresso mcp_memory_documents mcp_memory_documents_history` (documents ANTES de history, pela FK).
5. 🔴 Importar no CT 100; validar contagem + `MAX(updated_at)` + FULLTEXT index presente.

**Fase C — Repoint (o switch)**
6. 🟢 **PR de código** (no-op até virar env): conexão `memory_ct100` em `config/database.php` (env `MEMORY_DB_*`, default = `mysql` antigo); `protected $connection = 'memory_ct100'` nos 2 Models + nos `DB::table('mcp_memory_documents'...)` (ex. `McpServerHealthReader`). Pest verde.
7. 🔴 Migration idempotente que **dropa `fk_kb_nodes_source_doc`** no Hostinger (vira coluna lógica; `down()` recria).
8. 🔴 Janela de corte (baixo tráfego, ex domingo 02-04h BRT): pausar o cron de sync, re-dump delta → import, virar `MEMORY_DB_*` pro CT 100 no `.env` do **app Hostinger** E do **container MCP** (`docker/oimpresso-mcp/.env`), `config:cache` + `--force-recreate` do MCP.

**Fase D — Verificação (gates de aceite)**
9. 🔴 **Recall (Tier 0):** `decisions-search` / `kb-answer` / `sessions-recent` em `mcp.oimpresso.com` = baseline. `jana:health-check` verde.
10. 🔴 **Meilisearch:** 1 query híbrida (`buscarHybrid` lê Eloquent na nova conexão).
11. 🔴 **Webhook de sync:** push de teste em `main` → `IndexarMemoryGitParaDb` escreve no CT 100, history grava (FK CASCADE viva).
12. 🔴 **KB bridge:** `KbBridgeFromMcpJob` ainda casa `kb_nodes.source_doc_id` (join lógico).
13. 🔴 **ERP (a dor original):** folga de cota no Hostinger + escrita de venda OK (smoke ROTA LIVRE biz=4).

**Rollback:** até o passo 8 nada é destrutivo (env aponta pro `mysql` antigo). Pós-8: reverter `MEMORY_DB_*` + `config:cache`. **NÃO dropar** as tabelas originais do Hostinger no cutover — mantê-las read-only ≥7d como rede de segurança; dropar só após canário verde, em PR separado. Webhook é idempotente (UPSERT por slug) → re-sync reconcilia delta perdido.

## Riscos (Tier 0 em destaque)

1. 🔴 **Recall depende dessas tabelas** — import incompleto/conexão errada = chat "esquece" o canon. Mitigar: baseline pré/pós + janela curta.
2. 🔴 **Cross-server FK** — par atômico pras 2 tabelas; dropar `fk_kb_nodes_source_doc` (senão `kb_nodes` no Hostinger fica com FK órfã → erro de INSERT/migrate no ERP).
3. 🔴 **Webhook `/api/mcp/sync-memory` roda no app Hostinger** → passa a escrever cross-network no CT 100 (latência + ponto de falha novo). Validar timeout/retry.
4. 🔴 **Latência app↔CT100** em toda leitura/escrita de memória pelo app (hoje é Eloquent local). `McpServerHealthReader` tem cache 60s; sync e KB bridge não — medir antes de aprovar.
5. **Drift de collation MySQL↔MariaDB** (pegadinha já catalogada no 0060) — MariaDB 11 + `mariadb-dump` + conferir CHECK `json_valid()`.

## Consequências

- **+** ERP recupera/garante folga de cota; memória do MCP escala sem ameaçar a venda; alinha com 0053/0060.
- **−** latência app→CT100; novo ponto de falha de rede no sync; o app ainda é dono lógico (migrations + sync de lá) — desacoplamento total só na Opção (b).
- **Reversível** por env até o cutover; tabelas originais preservadas ≥7d.
- **Review triggers:** app sair do Hostinger (Opção A 0060) → reavaliar (b); MCP virar multi-cliente; latência de sync inaceitável.

## Decisão do Wagner

- [ ] Aprovo a Opção (a) + a emenda ao 0062 → vira ADR numerada (`number` + `decided_at`) e o runbook entra em execução.
- [ ] Prefiro a Opção (b) (refactor maior, desacoplamento total).
- [ ] Adiar — o teto preventivo (PR #3130) basta por ora.
