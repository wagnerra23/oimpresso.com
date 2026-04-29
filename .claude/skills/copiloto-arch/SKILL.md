---
name: copiloto-arch
description: Use ao trabalhar em Modules/Copiloto/ ou ao tocar memória/IA do projeto. Carrega arquitetura canônica do Copiloto (ADRs 0035-0053): laravel/ai SDK, MeilisearchDriver hybrid, ContextoNegocio com 3 ângulos faturamento, OTel GenAI, MCP server governança, tabela copiloto_memoria_metricas. Substitui leitura repetida de 18+ ADRs.
---

# Arquitetura Copiloto (ADRs 0035-0053)

## Stack atual em produção (29-abr-2026)

| Camada | Componente | Estado |
|---|---|---|
| A | `laravel/ai ^0.6.3` + `config/ai.php` (gpt-4o-mini) | ✅ ativo |
| B | `LaravelAiSdkDriver` + 4 Agents (~~Vizra rejeitado ADR 0048~~) | ✅ |
| C Hot | `SqlDriver` (conversas DB) | ✅ |
| C Cold | `MeilisearchDriver` hybrid (`semanticRatio: 0.7`) | ✅ MEM-HOT-1 |
| Working | `ContextoNegocio` 3 ângulos (bruto/líquido/caixa) | ✅ MEM-FAT-1 |
| Embedder | OpenAI text-embedding-3-small | ✅ |
| Métricas | `copiloto_memoria_metricas` 14 colunas (8 obrigatórias + 3 RAGAS) | ✅ MEM-MET-1+2 |
| Telemetria | log channel `otel-gen-ai` 12 atributos `gen_ai.*` | ✅ MEM-OTEL-1 |
| Scheduler | cron 23:55 `copiloto:metrics:apurar --business=all` | ✅ MEM-MET-3 |
| **MCP server** | `mcp.oimpresso.com` (CT 100 Proxmox) | 🚧 em construção MEM-MCP-1 |

## Decisões canônicas (resumo)

- **ADR 0035** — Stack canônica IA (laravel/ai + MemoriaContrato)
- **ADR 0036** — Meilisearch first; benchmark 95.2% LongMemEval supera Mem0/Zep; 5 triggers concretos pra reavaliar
- **ADR 0037** — Roadmap memória (Tier 7+)
- **ADR 0046** — Gap ChatCopilotoAgent (resolvido via Caminho A — ADR 0047)
- **ADR 0047** — Wagner solo + sprint memória priorizado
- **ADR 0048** — `laravel/ai` consolidado; **Vizra ADK rejeitada** (quebrou L13)
- **ADR 0049** — 6 camadas memória (Working/ConvHist/Episodic/Semantic/Procedural/Reflective) + gate Recall@3>0.80
- **ADR 0050** — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`
- **ADR 0051** — Schema próprio + adapter + OTel GenAI (`gen_ai.*` semantic conventions)
- **ADR 0052** — `ContextoNegocio` expor múltiplos ângulos (não 1 número)
- **ADR 0053** — MCP server da empresa: governança como produto (em construção)

## Arquivos-chave

```
Modules/Copiloto/
├── Services/
│   ├── Ai/LaravelAiSdkDriver.php        # canônico, fallback OpenAiDirectDriver
│   ├── Memoria/MeilisearchDriver.php    # hybrid embedder via Scout callback
│   ├── ContextSnapshotService.php       # ContextoNegocio 3 ângulos (MEM-FAT-1)
│   └── Metricas/MetricasApurador.php    # 8 métricas obrigatórias (ADR 0050)
├── Ai/Agents/
│   ├── ChatCopilotoAgent.php            # recebe ?ContextoNegocio (MEM-HOT-2)
│   ├── BriefingAgent.php
│   ├── SugestoesMetasAgent.php
│   └── ExtrairFatosAgent.php
├── Entities/
│   ├── Conversa.php · Mensagem.php · Sugestao.php
│   ├── Meta.php · MetaPeriodo.php · MetaApuracao.php · MetaFonte.php
│   ├── CopilotoMemoriaFato.php (Searchable + SoftDeletes)
│   ├── MemoriaMetrica.php (8 obrigatórias + 3 RAGAS)
│   └── Mcp/                             # 9 entidades governança MCP
└── Console/Commands/
    ├── ApurarMetricasCommand.php        # cron 23:55 daily
    └── McpSyncMemoryCommand.php         # sync git→DB (ADR 0053)
```

## Padrões obrigatórios

### Multi-tenant (skill `multi-tenant-patterns`)
- Toda Entity tem `business_id`; ScopeByBusiness aplicado em `booted()`
- `ContextoNegocio` recebido por business; nunca cross-tenant
- Recall Meilisearch filtra `business_id = X AND user_id = Y`

### LGPD
- `CopilotoMemoriaFato` usa SoftDeletes — `esquecer()` é opt-out
- Sanitizar CPF/CNPJ via `LaravelAiSdkDriver::mascararDocumentos()` antes da LLM
- Audit log `mcp_audit_log` retenção mínima 1 ano (ADR 0053)
- PII redactor automático no sync git→DB (`IndexarMemoryGitParaDb`)

### Append-only (memória + métricas)
- `copiloto_memoria_facts.valid_until` setado = superseded (não DELETE)
- `mcp_audit_log` é IMUTÁVEL — só INSERT
- `copiloto_memoria_metricas` upsert idempotente via unique (apurado_em, business_id)

### Test pattern (skill `pest-test-pattern`)
- **NÃO usar `RefreshDatabase`** — migrations core UltimatePOS quebram em SQLite
- Usar `beforeEach` com `Schema::create` da tabela alvo
- Em testes que tocam DB, BC-compat com cast `date:Y-m-d` explícito (não `date` genérico)

## Métricas em prod (snapshot 29-abr)

| Métrica | Valor |
|---|---|
| Suite Copiloto | 81 passed, 3 skipped |
| `memoria_recall_chars` (era 0) | 190 chars em chat real Larissa |
| Token economy ContextoNegocio | 270 tokens (3 ângulos faturamento) |
| Baseline `copiloto_memoria_metricas` | 3 linhas em prod (plataforma + biz=1 + biz=4) |
| OTel GenAI events | 12 atributos `gen_ai.*` por chamada |

## Trabalho atual (CURRENT)

**Modo solo Wagner (ADR 0047)** + sprint MCP server (ADR 0053):
- A1: 8 dias MCP server CT 100 Proxmox
- A2 paralelo: 3 dias Skills (este e outras)
