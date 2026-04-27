# ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica

**Status:** ✅ Aceita — **VERDADE CANÔNICA do projeto** — ⚠️ ordem dos sprints **revisada por ADR 0036** (Meilisearch first, Mem0 último)
**Data decisão:** 2026-04-26 (sessão 17, fim do dia)
**Autor:** Wagner (dono/operador) — *"quero sim, e grave isso como verdade canônica. acho que isso vai ser o melhor ROI"*
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Relacionado e consolida:**
- [ADR 0026 — Posicionamento "ERP gráfico com IA"](0026-posicionamento-erp-grafico-com-ia.md)
- [ADR 0027 — Gestão de memória do projeto](0027-gestao-memoria-roles-claros.md)
- [ADR 0031 — `MemoriaContrato` + Mem0RestDriver default](0031-memoriacontrato-mem0-default.md)
- [ADR 0032 — Vizra ADK (camada B)](0032-vizra-adk-prism-php-orquestracao.md) — sprint 1 já revisado por 0034
- [ADR 0033 — Vector store: Meilisearch fallback do Mem0](0033-vector-store-meilisearch-pgvector-mem0.md)
- [ADR 0034 — Laravel AI ecosystem 2026](0034-laravel-ai-sdk-oficial-boost-mcp.md)

---

## Contexto

Em 4 ADRs (0031, 0032, 0033, 0034) lavrados na sessão 16-17 de 2026-04-26, formalizamos progressivamente uma stack-alvo pra IA do oimpresso:

- ADR 0031: interface `MemoriaContrato` PHP + `Mem0RestDriver` default
- ADR 0032: Vizra ADK como camada B (orquestração)
- ADR 0033: Meilisearch como driver alternativo da `MemoriaContrato`; `pgvector` rejeitado por exigir Postgres
- ADR 0034: Laravel AI SDK oficial (`laravel/ai`, fev/2026) como camada A — supersedes Prism PHP em sprint 1

Esses ADRs foram apresentados no comparativo Capterra ([memory/comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](../comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md)) com score 11/11 no checklist do template.

**Wagner aprovou explicitamente em 2026-04-26 (sessão 17):** *"quero sim, e grave isso como verdade canônica. acho que isso vai ser o melhor ROI"*. Pediu também revisão de todas as memórias com a justificativa.

Este ADR consolida e declara a decisão como **canônica** do projeto.

## Decisão (canônica)

**Stack-alvo definitiva pra IA do Copiloto:**

| Camada | Pacote canônico | ADR | Status |
|---|---|---|---|
| **A — Wrapper LLM** | `laravel/ai` (Laravel AI SDK oficial, fev/2026) | 0034 | Sprint 1 em execução |
| **B — Framework de agente** | `vizra/vizra-adk` | 0032 | Sprints 2-3 |
| **C — Memória especializada** | `MemoriaContrato` + `Mem0RestDriver` (default) | 0031 | Sprints 4-5 |
| **C — Memória fallback** | `MeilisearchDriver` (self-hosted) | 0033 | Sprint 8-10 condicional |
| **C — Memória dev/CI** | `NullMemoriaDriver` (fixtures) | 0031 | Sprint 4 |
| **Tooling DEV** | `laravel/boost` (`--dev`) + `laravel/mcp` (futuro) | 0034 | Paralelo sprint 1 |
| **Search non-Copiloto** | Scout database driver default | 0033 | Já é assim |

**Fallbacks documentados (não default):**
- Camada A fallback: Prism PHP — usado se SDK oficial perder feature crítica
- Camada B alternativa: LarAgent / Neuron AI / construir direto sobre `laravel/ai` agents — reavaliar em sprint 2
- Camada C alternativas: Zep (temporal validity), Letta (long-running) — reavaliar quando feature exigir

**Rejeitados explicitamente:**
- `openai-php/laravel` — abandonado, causa do GAP 1 atual; remover quando sprint 1 entregar
- `pgvector` (Postgres) — exige migração de DB, custo > benefício hoje
- LangGraph/Letta self-hosted (Python) — exige container Python, inviável em Hostinger compartilhada
- Vercel AI SDK (Node) — mesmo problema do Python
- Algolia — paid-only escala cara
- Construir camada de memória do zero em PHP nativo — Tier 3-4 só, reinventa roda

## Justificativa (ROI)

Wagner: *"acho que isso vai ser o melhor ROI"*. Math que sustenta:

| Caminho | Sprints | Tier final | Custo recorrente | ROI estimado |
|---|---|---|---|---|
| Caminho A (canônico) | 7 | 6-7 LongMemEval | $25-300/mês Mem0 | **Alto** — destrava Copiloto comercializável + dashboard/eval |
| Construir do zero | 12-15 | 3-4 | $0/mês | Baixo — reinventa roda, sem dashboard |
| Aceitar Tier 1 | 0 | 1 | $0/mês | **Negativo** — contradiz ADR 0026 ("ERP com IA") |
| Stack Python | inviável | n/a | n/a | **Inviável** — Hostinger compartilhada |

ROI quantitativo aproximado:
- **Custo:** 7 sprints (~9-10 semanas) + $25-300/mês × meses até atingir tração
- **Retorno:** Copiloto vendável → ticket Tier 1A (R$ 199-599/mês) × N clientes. ADR 0022 mira R$5mi/ano = R$417k/mês de MRR.
- **Break-even:** se Copiloto trouxer 1 cliente Tier 1A em 6 meses, paga Mem0 e justifica investimento. Se trouxer 50 clientes (caminho da meta), ROI é exponencial.

**Diferenciais vs concorrentes verticais (Mubisys/Zênite/Calcgraf/Calcme/Visua):**
- Nenhum tem Copiloto IA-first do business
- Nenhum tem MemCofre (cofre de memórias)
- Nenhum tem dashboard com traces visuais de agente
- Stack Laravel-nativa permite Wagner solo cobrir todo o ciclo (dev, ops, suporte)

## Implementação imediata

**Sprint 1 (em execução nesta sessão 17, branch `feat/copiloto-laravel-ai-sdk-sprint1`):**
1. `composer require laravel/ai` no main worktree
2. `composer require laravel/boost --dev` em paralelo
3. Deletar stub atual `Modules/Copiloto/Services/Ai/LaravelAiDriver.php` (era stub do módulo LaravelAI interno, não do SDK oficial)
4. Criar `Modules/Copiloto/Services/Ai/LaravelAiSdkDriver.php` implementando `AiAdapter`
5. Criar `Modules/Copiloto/Ai/Agents/`:
   - `BriefingAgent` — gera briefing inicial da conversa
   - `SugestoesMetasAgent` — devolve propostas estruturadas via `HasStructuredOutput`
   - `ChatCopilotoAgent` — `Conversational` interface, recebe histórico via `messages()`
6. Atualizar `CopilotoServiceProvider::register()` pra binding do driver
7. Atualizar `Modules/Copiloto/Config/config.php`: adicionar opção `'laravel_ai_sdk'` em `ai_adapter`
8. Smoke test Pest: 3 testes (briefing, sugestoes, chat) com `COPILOTO_AI_DRY_RUN=true`
9. PR aberto pra Wagner revisar antes de mergear em `6.7-bootstrap`

## Consequências

✅ **Verdade canônica do projeto** — qualquer agente novo (Cursor, Claude, contratado futuro) tem stack pra seguir sem inventar.
✅ Roadmap 7 sprints concreto com responsável por sprint.
✅ ADRs 0031-0034 ficam materializados como decisões fundadoras.
✅ Wagner aprovou ROI explicitamente — bloqueia futuros pivots sem ADR de revisão.
✅ Fallbacks documentados pra cada camada — antifragilidade contra abandono de upstream.
⚠️ Sprint 1 toca produção — exige PR + revisão Wagner antes de mergear em `6.7-bootstrap`.
⚠️ Custo Mem0 recorrente vai aparecer no orçamento — documentar em finanças quando ativar (sprint 4-5).
⚠️ Vizra ADK e Laravel AI SDK são novos (2026) — bug surface emergente. Sprints 1 e 2 incluem smoke tests reais.

## Alternativas consideradas (e rejeitadas)

Já documentadas em ADRs 0031-0034 com motivos. Resumo:
- **Caminho de menor custo curto prazo** (aceitar Tier 1 / Caminho D) → rejeitado: contradiz ADR 0026.
- **Caminho de máxima soberania** (construir tudo do zero) → rejeitado: 12-15 sprints sem dashboard/eval.
- **Stacks Python state-of-the-art** (LangGraph/Letta/etc) → rejeitado: container Python inviável em Hostinger.
- **Rebuild com pgvector + PostgreSQL** → rejeitado: migração DB é projeto separado, sem retorno claro hoje.

## Sources

ADRs internos: 0026, 0027, 0031, 0032, 0033, 0034.

Comparativos:
- [stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](../comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md) (7 players, 48 features, score 11/11)
- [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](../comparativos/copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md) (foco Camada C)

Externas: ver ADR 0034 (Sources) — Laravel AI SDK, Boost, MCP, Vizra ADK, Mem0, Meilisearch, todos referenciados com URL literal.

## Compromisso

Wagner em 2026-04-26 declarou: *"melhor ROI"*. Próximo agente que considerar pivotar dessa stack **deve abrir ADR de revisão** explicando:
1. Que ROI ficou comprometido (com números)
2. Qual alternativa concreta substituirá
3. Plano de migração

Sem ADR de revisão, agente segue Caminho A.
