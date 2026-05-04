# Pesquisa exaustiva Wagner sobre IA/memória (29-abr-2026)

Wagner mandou ZIP `files.zip` com 6 ADRs (001-006) + README cobrindo o módulo IA do **WR2/UltimatePOS** (sistema separado mas mesmo stack Laravel). Análise de aproveitamento p/ oimpresso.

## O que importou pro oimpresso (ADRs 0048-0050 criados)

| Wagner ADR | oimpresso ADR | Aproveitamento |
|---|---|---|
| 001 — Vizra ADK rejeitada | **0048** | 100% — Vizra quebrou no L13, COP-015 cancelada, `laravel/ai` consolidado |
| 002 — 6 camadas memória | **0049** | Conceito + gate Recall@3>0.80 antes de evoluir camada |
| 003 — MySQL+Meilisearch (sem pgvector/Mem0) | **0036 estendida** | Benchmark BM25+vetor=95.2% LongMemEval (supera Mem0 93.4%, Zep 71.2%) + 5 triggers concretos |
| 004 — 8 métricas + memory_metrics | **0050** | 100% — tabela + comando `copiloto:metrics:apurar` + 5 tasks MEM-MET-1..5 |
| 005 — Assistente WR2 (3 camadas) | NÃO importado | Produto separado (WhatsApp+browser); fora do escopo Copiloto oimpresso |
| 006 — MCP server (em aberto) | Backlog P3 | Decisão Wagner sobre WR2; `laravel/mcp` é caminho potencial futuro |

## 8 métricas obrigatórias (ADR 0050)

Recall@3>0.80 · Precision@3>0.60 · MRR>0.7 · Latência p95<2s · Tokens/interação<3K · Memory bloat>0.6 · Contradições<2% · Cross-tenant=0

## Triggers concretos pra abandonar Meilisearch hybrid (ADR 0036 estendida)

Latência p99>200ms sustentado · vol>50M vetores · curadoria de prompt >1d/sem por trimestre · raciocínio temporal forte virar requisito · cliente pagante #10

## O que NÃO importei e por quê

- **ADR-005 Wagner** — específico WR2 (chat WhatsApp+Playwright+computer use). Copiloto oimpresso é chat interno do ERP — escopo diferente.
- **ADR-006 Wagner** — MCP server depende de decisão dele para o WR2. Pra oimpresso vira P3 futuro (Copiloto expor tools p/ Claude Desktop).
- **manual-copiloto.md** — plano migração AppShell→Cockpit. Já contemplado em UI-001 do TASKS.md (Cycle 02+).
