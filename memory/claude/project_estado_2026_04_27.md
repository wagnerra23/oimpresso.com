---
name: Estado do projeto Copiloto em 2026-04-27 (consolidação pra retomar amanhã)
description: Sumário consolidado das sessões 14-19 do Copiloto. 5 PRs mergeados, 11 ADRs canônicos, 6 comparativos Capterra. Tier 5-6 LongMemEval estimado. Pendente validar Larissa.
type: project
originSessionId: dazzling-lichterman-e59b61
---

Estado em 2026-04-27 fim de noite (sessão 19 da thread `dazzling-lichterman-e59b61` que já dura desde 2026-04-26).

## 5 PRs mergeados em `6.7-bootstrap` desta thread

- **PR #24** — sprint 1: `laravel/ai` SDK oficial + 3 Agents + `LaravelAiSdkDriver`. Stub `LaravelAiDriver.php` deletado.
- **PR #25** — sprint 4: `MemoriaContrato` + `MeilisearchDriver` + `NullDriver` + horizon/telescope/pail.
- **PR #26** — sprint 5: bridge memória↔chat dual-layer (recall síncrono + extração async via Horizon queue `copiloto-memoria`).
- **PR #27** — sprint 6: tela `/copiloto/memoria` LGPD US-COPI-MEM-012 (`MemoriaController` + `Pages/Copiloto/Memoria.tsx`).
- **PR #28** — MemCofre score boost: 3 pages Copiloto com `@memcofre` block → 14 pages total em `docs_pages`.
- **PR #29** — ENTERPRISE.md (12 seções).

## 11 ADRs canônicos (memory/decisions/)

- 0026 posicionamento "ERP gráfico com IA"
- 0027 gestão de memória (papéis canônicos)
- 0028 ADRs numeração monotônica
- 0030 credenciais nunca em git
- 0031 `MemoriaContrato` (revisado por 0036)
- 0032 Vizra ADK + Prism (sprint 1 revisado por 0034)
- 0033 vector store backend (revisado por 0036)
- 0034 Laravel AI ecosystem 2026
- **0035 Stack canônica IA (verdade — Wagner *"melhor ROI"*)**
- **0036 Replanejamento Meilisearch first, Mem0 último (economiza R$1.500-18k/ano)**
- **0037 Roadmap evolução Tier 5-6 → Tier 7-9 LongMemEval**

## 6 comparativos Capterra (memory/comparativos/)

- _TEMPLATE_ v1.0
- oimpresso vs concorrentes (verticais BR)
- sistemas_memoria_oimpresso (camada A, 9 sistemas dev)
- copiloto_runtime_memory_vs_mem0_langgraph_letta_zep (camada C, 5 frameworks)
- stack_agente_php_vizra_prism_mem0 (stack completa A+B+C, 7 players)
- **revisao_caminho_2026_04_27** (auditoria pós-sprint 6, 5 caminhos — recomenda validar Larissa)
- **claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27** (7 MCP servers vs nossa stack — vácuo vertical BR)

## Stack-alvo (verdade canônica)

- A — `laravel/ai ^0.6.3` ✅ instalado e mergeado
- B — `LaravelAiSdkDriver` + 4 Agents (Briefing/Sugestoes/Chat/ExtrairFatos). Vizra ADK aguarda L13 upstream.
- C — `MemoriaContrato` ✅ default `MeilisearchDriver` (Mem0 sprint 8+ condicional, 5 triggers)
- Tooling — Boost + MCP + Scout + Horizon + Telescope + Pail ✅
- Bridge — Hot Path (`recallMemoria()`) + Cold Path (`ExtrairFatosDaConversaJob` async) ✅

## Estado prod Hostinger

- ✅ Sprints 1+4 deployados (composer install + migrate copiloto_memoria_facts rodaram)
- ✅ Meilisearch v1.10.3 daemon RODANDO (PID 632084, /health: available)
- ✅ `memcofre:sync-pages` rodou (14 pages em `docs_pages`)
- 🟡 PRs #26/#27/#29 código mergeado mas deploy SSH pendente
- 🟡 Embedder Meilisearch NÃO configurado (recall sem semantic ainda)
- 🟡 `OPENAI_API_KEY` NÃO setada no `.env` (Copiloto em dry_run)

## Pendente pra retomar amanhã

🔴 **BLOQUEANTE: validar com Larissa do ROTA LIVRE** (1-2h call testando `/copiloto`):
- Pergunta sobre meta atual
- Conversa >15 turnos
- Corrige fato em `/copiloto/memoria`

🟡 4 cenários de Sprint 7 dependendo do feedback dela:
| Larissa diz | Sprint 7 | ADR base |
|---|---|---|
| "Lembrou minha meta" | A — RAGAS evaluation | 0037 |
| "Preciso PricingFpv/CT-e" | Pivot ADR 0026 | 0026 |
| "Não entendi" | MCP server pro Claude Desktop | 0036 + comparativo 2026-04-27 |
| Silêncio 30d | Pivot comercial | 0026 |

🟡 Operacional independente da Larissa:
1. Deploy SSH dos PRs #26/#27/#29
2. Configurar embedder Meilisearch via curl POST settings/embedders
3. Setar OPENAI_API_KEY + COPILOTO_AI_DRY_RUN=false no .env Hostinger

## Como aplicar

- Quando Wagner perguntar "estado do Copiloto" → este arquivo
- Quando ele perguntar "o que falta" → seção "Pendente pra retomar"
- Quando ele perguntar "quais decisões já estão tomadas" → 11 ADRs canônicos
- Sempre verificar `git log origin/6.7-bootstrap -10` antes de afirmar deploy aconteceu (Hostinger pode ter dropado)

## Refs internas

- `memory/08-handoff.md` (resumo "começa aqui" do projeto)
- `memory/sessions/2026-04-27-sprints-5-6-mcp-claude-desktop-revisao.md` (session log completo)
- `memory/CHANGELOG.md` (Keep-a-Changelog format)
- `memory/requisitos/Copiloto/ENTERPRISE.md` (overview enterprise)
