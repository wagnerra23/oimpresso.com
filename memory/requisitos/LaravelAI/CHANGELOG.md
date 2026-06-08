# Changelog — LaravelAI

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) + SemVer.

## [Unreleased]

### Planejado (Onda 1 — POC Knowledge Graph básico)

- Schema `kg_entities`, `kg_relations` (sem embedding ainda)
- Seeder a partir de Spatie roles+permissions (1 business)
- Endpoint GET `/api/laravel-ai/graph/nodes` + `/edges` paginado
- Permissões Spatie (`laravel-ai.graph.view`)
- Multi-tenant scope tests (TECH-0002)

### Planejado (Onda 2 — Vector store + RAG)

- Coluna `embedding VECTOR(1536)` em `kg_entities` (pgvector)
- `EmbeddingService` com OpenAI provider (TECH-0001)
- Sync ADRs filesystem via cron + hash (TECH-0003)
- Endpoint GET `/api/laravel-ai/rag/search?q=...` (US-AI-002)

### Planejado (Onda 3 — AgentService + LLM)

- `AgentService` que orquestra Graph + RAG + Audit
- Provider OpenAI + fallback Anthropic
- Circuit breaker (R-AI-010)
- PII masking (R-AI-004)
- Quota enforcement (R-AI-003)
- Endpoint POST `/api/laravel-ai/chat` (US-AI-001)
- Audit log `ai_query_log` (R-AI-008)

### Planejado (Onda 4 — Visualização React Flow)

- Página `/laravel-ai/graph` (UI-0001)
- Filtros, expand, click handlers
- Custom nodes shadcn/ui themed
- Dark mode + responsivo

### Planejado (Onda 5 — Chat IA contextual)

- Componente `<AiContextualChat />` flutuante (US-AI-005)
- Auto-injeção de contexto (rota, user, business)
- Memória curta de sessão (10 mensagens)
- Streaming respostas via SSE

### Planejado (Onda 6 — Multi-modal + ML)

- Análise preditiva (churn, NFe rejection probability)
- Visualização gerada por IA (gráficos como resposta)
- Voice input (futuro distante)

### Planejado (Onda 7 — Custom embeddings tenant)

- Modelo embedding fine-tuned por tenant Enterprise
- Dataset interno proprietário
- Rolar batch re-embed quando model atualiza

## [0.0.0] - 2026-04-24

### Added

- Spec promovida de `_Ideias/LaravelAI/` (status `idea`) para `requisitos/LaravelAI/` (`spec-ready`)
- Estrutura completa: README + SPEC + ARCHITECTURE + GLOSSARY + 8 ADRs (arq/0001-0003 + tech/0001-0003 + ui/0001-0002)
- Frase de posicionamento e revenue model: add-on Pro R$ 199 / Enterprise R$ 599 (subscription puro)
- Decisão estratégica: estender MemCofre (não duplicar)
- Origem rastreada: conversa Claude mobile (`_Ideias/LaravelAI/evidencias/conversa-claude-2026-04-mobile.md`)
