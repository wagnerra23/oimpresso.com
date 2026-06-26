# Incidente 2026-06-26 — ROTA LIVRE não salva produto/venda (Hostinger revogou INSERT)

**Severidade:** Tier 0 (cliente piloto biz=4, 99% do volume, write-blocked por horas).
**Reincidência:** 2ª vez (a 1ª foi 2026-06-21). PR de prevenção: [#3374](https://github.com/wagnerra23/oimpresso.com/pull/3374).

## Sintoma reportado
Guilherme (ROTA LIVRE) via Maiara: cadastro de produto (novo + duplicar) e venda/rascunho **pararam de salvar** "desde hoje" — toast genérico "Algo deu errado, tente novamente". Também a lista de produtos travando ("Em processando…"). Print confirmou prod (`oimpresso.com`, biz=4, 25/06 ~14:50).

## Diagnóstico (via SSH prod, read-only)
1. Código de salvar produto/venda **não mudou em 14 dias** → não era regressão de fluxo.
2. `laravel.log` (873 MB, sem rotação) cheio de `SQLSTATE 1142: INSERT command denied to user 'u906587222_oimpresso'`.
3. `SHOW GRANTS`: usuário **sem `INSERT`/`UPDATE`/`CREATE`** (só SELECT/DELETE/ALTER/DROP). Todo INSERT/UPDATE caía no `DB::rollBack` + catch genérico (`messages.something_went_wrong`).
4. **Causa:** banco a ~6 GB estourou a cota do plano → Hostinger **auto-revogou a escrita**. `mcp_memory_documents_history` = **5,2 GB / 374.550 linhas**, 86% do banco, **todas criadas 21-25/06** (≈264 versões/doc) — burst da maratona de governança/SDD (dezenas de merges/dia × docs quentes; cada mudança grava snapshot do `content_md` inteiro, sem teto).

## Correção imediata (prod) — Wagner autorizou
`TRUNCATE mcp_memory_documents_history` (usuário tinha `DROP`): **6.006 MB → 834 MB**. A Hostinger **re-concedeu `ALL PRIVILEGES` automaticamente** ao cair abaixo da cota (Wagner não precisou de hPanel). Teste de escrita real (CREATE+INSERT+DROP) OK. `/login` 200. Sem perda de dado de negócio (git é canônico — ADR 0061).

## Por que reincidiu (o bug)
`jana:memory-history-prune` (PR #3130, pós-21/06) só deletava o que reprovava em **AMBAS** as defesas: fora de `--days=90` **E** além do top-N. O burst nasceu todo dentro de 90d → poda não deletava nada. **A janela temporal era o buraco.**

## Prevenção — PR #3374 (defesa em profundidade)
- **Poda → teto duro por doc** (idade ignorada) + de 6 em 6h (era diária).
- **Camada 1 — teto no WRITE:** `McpMemoryDocumentHistory::podarExcedentePorDoc()` dentro de `snapshotEAtualizar()` → bounda a tabela em `docs × 20` (~400 MB) **independente do cron**. Esta é a garantia matemática.
- Testes Pest sqlite pros dois.
- **Camadas 3/4 já existiam** (`checkDbWriteCanary` + `checkDbStorageQuota` + `isWriteDenied`, pós-21/06) e estão **verdes** em prod pós-reclaim. A detecção existia; faltou **ação a tempo** sobre o alerta de cota (90% de 6144 → email no fim de semana, ninguém agiu antes do 100%).

## Estrutural (raiz) — proposta reforçada, gated
[`memory/decisions/proposals/2026-06-21-mcp-memory-store-ct100.md`](../decisions/proposals/2026-06-21-mcp-memory-store-ct100.md) (mover `mcp_memory_documents*` pro MariaDB CT 100) — atualizei com a reincidência como 2ª prova (N=2) de que só teto não fecha a raiz: enquanto a memória do MCP dividir a cota de 6 GB do DB do ERP, qualquer crescimento ameaça a venda. **Aguarda decisão do Wagner** (Opção a/b).

## Follow-ups
- **Wagner decide** a proposta CT 100 (estrutural).
- **Camada 2** (não versionar docs gerados — shipped-log/índices/scorecards): com Camada 1 a tabela já está bounded, então é otimização, não segurança. Backlog.
- **Camada 4 cosmético:** catch do produto/venda mostrar mensagem específica em `1142` (manutenção/somente-leitura) em vez do genérico. Baixa prio (Camada 1 evita o gatilho).
- **`laravel.log` sem rotação** (873 MB) — configurar `LOG_CHANNEL=daily` ou logrotate. Backlog infra.

## Estado MCP no momento do fechamento
Brief MCP indisponível na sessão (fallback SessionStart). Diagnóstico e fix feitos direto contra prod via SSH (warm-up + flags canônicos de `memory/reference/hostinger.md`).
