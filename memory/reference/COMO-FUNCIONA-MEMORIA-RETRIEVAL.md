---
name: Como funciona a memória do oimpresso (retrieval)
description: Regra de ouro e arquitetura de como o conhecimento do oimpresso é armazenado e LOCALIZADO — busca indexada (Meilisearch + Scout) com decay por tempo/status, NÃO navegação de pasta. Doc anti-erro pra humanos e agentes IA. Inclui gaps de indexação a otimizar.
type: reference
authority: canonical
---

# Como funciona a memória do oimpresso — e como achar as coisas

> **Pra quem é:** todo agente IA (Claude/Cursor) e toda pessoa do time.
> **Por que existe:** evitar o erro recorrente de tratar a árvore de pastas `memory/` como se fosse a forma de achar conhecimento. **Não é.** Tudo aqui foi verificado em código + git + teste (2026-05-28).

## 🥇 Regra de ouro

> **A memória é INDEXADA e localizada por BUSCA. A pasta é só onde o arquivo mora — não é como você acha. Nunca mova/delete pra "arrumar" — append-only.**

- ✅ **CERTO:** perguntar às tools de busca do MCP (`decisions-search`, `memoria-search`, `brief-fetch`).
- ❌ **ERRADO:** `grep`/`glob`/`ls` em `memory/` procurando "por assunto".
- ❌ **PROIBIDO:** mover, deletar, renomear ou "reorganizar" arquivos de memória.

### Dois anti-padrões reais (sessão 2026-05-28 — erros cometidos de verdade)

1. **Navegar pasta e "consolidar movendo".** Um agente recebeu "consolide a memória" e deletou/moveu arquivos. Errado de premissa: ninguém navega pasta; o decay já tira o velho do topo.
2. **Afirmar com `git show --stat` num clone shallow.** O mesmo agente viu "14k arquivos / 3M linhas" num commit e gritou "estrago". Era artefato: em **clone shallow (grafted)**, um commit sem pai alcançável é diffado contra a árvore vazia → reporta o repo inteiro como "adicionado". **Sempre cheque `git rev-parse --is-shallow-repository` e os parents antes de confiar em `--stat`.** O diff real eram 2 arquivos.

## Arquitetura real: 2 índices Meilisearch + Scout

```
  FONTE (git, append-only)          INGESTÃO                    ÍNDICE                         BUSCA
  memory/**/*.md  ──── mcp:sync-memory (a cada 5min) ────▶  mcp_memory_documents  ──── decisions-search (FULLTEXT)
  (ADRs, reference,     + freshness reindex (04:30 BRT)      (Scout Searchable)          kb-answer (hybrid)
   sessions, specs…)                                                                     [NÃO passa pelo time-decay]

  fatos de negócio ──── lembrar() / copiloto:seed-adrs ──▶  copiloto_memoria_facts ──── memoria-search
                                                            (MemoriaFato)                hybrid + HyDE + RRF
                                                                                         + applyTimeDecay + reranker
```

| Índice | Recebe | Busca por | Time-decay? |
|---|---|---|---|
| **`mcp_memory_documents`** | TODO markdown canon (sync 5min, Scout) | `decisions-search` (MySQL FULLTEXT), `kb-answer` (hybrid) | ❌ só freshness pipeline |
| **`copiloto_memoria_facts`** (`MemoriaFato`) | fatos de negócio + ADRs seedados | `memoria-search` (hybrid + HyDE + RRF + reranker) | ✅ `applyTimeDecay` |

## Como LOCALIZAR (a forma certa)

| Quero achar… | Tool MCP | Não use |
|---|---|---|
| Decisão / ADR | `decisions-search "query"` | grep em `memory/decisions/` |
| Fato de negócio / cliente | `memoria-search "query"` | navegar pastas |
| Estado atual (cycle/tasks/HITL) | `brief-fetch` | ler vários `.md` |

Query semântica acha por **significado**, não por nome de arquivo, e já filtra por relevância/status.

## Os pesos (onde cada um aplica)

> 🚨 **Princípio (Wagner, 2026-05-28): decair é pra MEMÓRIA, não pra DECISÃO.**
> - **Memória** (`session`, `handoff`, fato de negócio) → perde relevância com o **tempo**. Time-decay faz sentido.
> - **Decisão** (`adr`, `spec`) → **NÃO decai por tempo**. Vale até ser **substituída** (supersede) ou marcada **não-usada** (status). ADR Tier 0/fundacional **nunca** decai — sempre no topo.
> - O `half_life: adr=365` que existe no config é **conceitualmente errado** e **não deve ser ativado pra decisões**. Decisão é governada por `status` + `supersede`, jamais por relógio.

**Time-decay** (`MeilisearchDriver::applyTimeDecay`, caminho `memoria-search`) — config `jana.time_decay`, aplica-se **só a memória**:
```
score = base × ( (1 − 0.4) + 0.4 × 0.5^(idade/half_life) ) × status_multiplier
```
- `temporal_weight = 0.4`. Half-life de memória: `session=30d · handoff=14d · default=180d`.
- `status_multiplier`: `accepted=1.2 · proposed=1.0 · historical=0.5 · superseded=0.3` (válido pra ambos — mede uso, não idade).
- Validado: `Modules/Jana/Tests/Feature/Memoria/TimeDecayTest.php` (11 casos).

**Freshness pipeline** (`jana:freshness-check`, sobre `mcp_memory_documents`): FRESH(≤1d)/WARM(<7d)/STALE(<30d)/CRITICAL(≥30d) + detecta drift git↔DB + reindex.

**Filtro de lifecycle** (`decisions-search`): por padrão só `accepted` + `accepted-historical`; superseded/deprecated saem do resultado sem ser apagados.

## Append-only — nunca mover, nunca editar

- ADR aceita JAMAIS é editada → nova com `supersedes: [N]`.
- `MemoriaFato::atualizar()` marca o antigo com `valid_until` e cria novo.
- `_INDEX-LIFECYCLE.md` é a única forma de "mover" estado de ADR (1 linha no índice).

## ⚠️ Gaps de indexação (auditoria 2026-05-28)

> Atenção: o "P0 — ligar o time-decay nas ADRs corrigindo chaves" que uma versão anterior deste doc listava **era um erro** e foi removido. Ligar decay em decisão é a cagada a evitar (ver Lição 2). Os gaps reais são:

1. **Config trata decisão como memória (erro conceitual a corrigir — NÃO "ligar").** `jana.time_decay` define `half_life: adr=365 / spec=180`. Mas **decisão não decai por tempo**. Se o decay for um dia ativado de fato, deve cobrir **só memória** (`session`/`handoff`/fatos); `adr`/`spec` ficam de fora — governados por `status` + `supersede`. O conserto certo é *remover adr/spec do decay temporal*, não "consertar as chaves pra ligar".
2. **Cobertura de coleta incompleta (gap real).** `IndexarMemoryGitParaDb::coletarArquivos` usa `glob()` não-recursivo + lista fixa → `memory/handoffs/`, `reference/` (subpastas), `_DesignSystem/`, `sprints/`, `decisions/proposals/` ficam **fora do índice**. Esses são docs/memória que deveriam ser buscáveis e não são.
3. **`decisions-search` usa FULLTEXT sem decay temporal — e isso está CERTO.** Decisão não decai por tempo; não é gap.

## Referências

- **Código:** `Modules/Jana/Services/Memoria/MeilisearchDriver.php` · `Modules/Jana/Config/config.php` (§time_decay, §freshness) · `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` (coleta) · `Modules/Jana/Console/Commands/{SeedAdrs,McpSyncMemory,FreshnessCheck}Command.php` · `Modules/Jana/Entities/Mcp/McpMemoryDocument.php`
- **Teste:** `Modules/Jana/Tests/Feature/Memoria/TimeDecayTest.php`
- **ADRs:** 0035 (stack IA) · 0036 (Meilisearch first) · 0054 (HyDE/reranker) · 0061 (zero auto-mem) · 0131 (3 tiers) · 0195 (decay adaptativo de feedback)
