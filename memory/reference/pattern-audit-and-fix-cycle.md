# Pattern Audit-and-Fix Cycle — meta-padrão canônico

> **Tipo:** reference canônico (git canon, ADR 0061)
> **Status:** validado · ativado via `/audit-and-fix <tema>`
> **Origem:** sessão `nervous-mayer-3ff0da` 2026-05-13 (19 PRs, 70% → 91% maturidade em 6h)
> **Generalização:** reusável pra qualquer tema com benchmark externo + áreas isoláveis

## Por que existe

Wagner observou: "*é sempre o mesmo ciclo. Evolua.*" — depois de 4 ondas de auditorias na mesma sessão (mcp-quality, knowledge-architecture, session-handoff, gap-analysis-91-100) + 16 agents implementadores em paralelo, o pattern ficou claro. Esta doc canoniza o ciclo pra ele virar capacidade permanente do sistema, não esforço improvisado.

## O ciclo (4 fases)

```
┌─────────────────────────────────────────────────────────────────┐
│  Fase 1 — RESEARCH (1 agent)                                    │
│  audit-research-expert pesquisa + compara + nota %              │
│  → Artefato canônico em memory/requisitos/Jana/AUDITORIA-*.md   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Fase 2 — HITL APROVAÇÃO (Wagner)                               │
│  Decide se prossegue Fase 3 (default) ou aborta (--research-only)│
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Fase 3 — IMPLEMENT (N agents paralelos)                        │
│  N × audit-implement-expert (cada um 1 gap, áreas isoladas)     │
│  → Código + Pest + RUNBOOK por gap                              │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Fase 4 — CONSOLIDATE (parent, sem agents)                      │
│  N PRs separados (1 por gap), splittar shared files manualmente │
│  Rebase pattern se conflito                                     │
└─────────────────────────────────────────────────────────────────┘
```

## Validação empírica

| Onda | Tema | Score inicial | Score final | PRs | Agents | Tempo |
|---|---|---:|---:|---:|---:|---:|
| 1 | `mcp-sync` (bugs MCP) | 62% | 72% | 5 | 4 | 1h |
| 2 | `knowledge-architecture` | 73% | 85% | 6 | 5 | 1.5h |
| 3 | `session-handoff` + multi | 74% | 92% | 6 | 6 | 2h |
| 4 (curso) | `reranker`+`langfuse`+`charters` | 91% | ~95% | 3 | 3 | 1h+ |
| **Total** | — | **70%** | **~91%** | **20** | **18** | **~6h** |

## Pré-requisitos pra ativar `/audit-and-fix <tema>`

1. **Tema com benchmark externo** — existe estado-da-arte pesquisável (Linear/Jira/Notion/Cohere/Langfuse/etc)
2. **Áreas de implementação isoláveis** — diferentes Modules/, Services/, Tools/, etc — não monolito único
3. **Worktree limpa** — sem `git status` pendente (evita conflitos com PRs paralelos)
4. **Tools MCP conectadas**
5. **Princípios duros ADR 0094** disponíveis pra restrições Tier 0 nos prompts

## 8 lições aplicadas em cada ciclo

(Vide [aprendizados-onda1-2-3-2026-05-13.md](aprendizados-onda1-2-3-2026-05-13.md) detalhado.)

1. **`ls -la` no reporte é IRREVOGÁVEL** (anti-alucinação Write)
2. **Áreas isoladas obrigatórias** entre N agents paralelos
3. **Splittar Provider/Server/Kernel manualmente** (`git add -p` não funciona em script)
4. **Rebase pattern proven** quando 2º PR conflita com 1º mergeado
5. **Multi-tenant Tier 0 IRREVOGÁVEL** em CADA prompt
6. **PT-BR** em prompts + comentários
7. **Tools MCP com mock mode** (sustentabilidade Pest)
8. **Custo IA tracking** por feature

## Quando NÃO usar `/audit-and-fix`

- **Tema vago** ("melhorar sistema") sem dimensions mensuráveis
- **Sem benchmark externo** (área proprietária sem competidor público)
- **Implementação monolítica** sem áreas isoláveis pra N agents
- **Bug urgente prod** (use fluxo hotfix direto, não ciclo de auditoria)
- **Wagner já decidiu o que fazer** — pula Fase 1, vai direto pra implementação

## Métrica de saturação (parar de subir)

Vide [GAP-ANALYSIS-91-100-2026-05-13.md](../requisitos/Jana/GAP-ANALYSIS-91-100-2026-05-13.md) §Métrica de saturação:

> Parar quando custo marginal > 2.5 d/pp **E** sem Langfuse instrumentado. Teto pragmático **97-98%** (não 100%). Onda 6 custa 13.5 d/pp em capacidades sem dor real pra time de 5.

## Capacidades já adquiridas (não reauditar sem novo trigger)

Pós-sessão 2026-05-13, o oimpresso tem:

- **3 auditorias canônicas** (MCP/PM, Knowledge, Handoff) com nota %
- **2 ferramentas IA-eval** (RAGAS gate manual + Langfuse em curso)
- **5 capacidades retrieval** (RRF default + LLM driver + BGE em curso + backlinks sweep + reranker pipeline)
- **5 capacidades handoff** (append-only + summarized + diff + sessions/_INDEX + handoff curto piloto)
- **4 ferramentas knowledge** (kb-answer + INDEX navegável + weekly digest + auto-mem migration)
- **3 surpresas mundo-classe** (Constituição v2 + Brief 6×/dia + whats-active)
- **Pattern reusável** `/audit-and-fix` (este doc)

## Referências

- **Slash command:** [.claude/commands/audit-and-fix.md](../../.claude/commands/audit-and-fix.md)
- **Agent research:** [.claude/agents/audit-research-expert.md](../../.claude/agents/audit-research-expert.md)
- **Agent implement:** [.claude/agents/audit-implement-expert.md](../../.claude/agents/audit-implement-expert.md)
- **Lições:** [aprendizados-onda1-2-3-2026-05-13.md](aprendizados-onda1-2-3-2026-05-13.md)
- **Auditorias:** `memory/requisitos/Jana/AUDITORIA-*-2026-05-13.md` + `COMPARATIVO-*-2026-05-13.md` + `GAP-ANALYSIS-91-100-2026-05-13.md`
- **Princípios duros:** [ADR 0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
