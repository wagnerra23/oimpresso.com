# EvolutionAgent — Especificação

> Status: **spec-ready** (sem código ainda)
> Data: 2026-04-26
> Owner: Wagner
> Meta vinculada: [`memory/11-metas-negocio.md`](../../11-metas-negocio.md) — R$ 5mi/ano
> Posicionamento: ferramenta meta — ajuda Claude Code a propor o **próximo PR de maior ROI** dentro do oimpresso

## 1. Visão

Agente que mantém memória persistente do repo + ranqueia oportunidades por ROI estimado + roda evals automatizados em CI. **Claude Code permanece como interface primária** (Wagner conversa com CC); EvolutionAgent é backend que CC consulta via Artisan.

## 2. Por que existe

- Hoje cada sessão CC re-lê `memory/` inteiro → caro em tokens.
- Decisões de roadmap são manuais; sem ranking objetivo.
- Sem regressão automatizada no que o agente sugere.
- Wagner adaptado ao Claude Code; trocar UX = perda.

## 3. Não-objetivos

- ❌ Substituir Claude Code como interface.
- ❌ Tornar-se módulo SaaS pra cliente final (esse é Copiloto).
- ❌ Mutar código autonomamente em `main` na Fase 1.
- ❌ Indexar `vendor/`, `node_modules/`, builds.
- ❌ Sentry agora (overkill pré-uso real).

## 4. ROI por componente

Cada decisão tem ROI estimado. Detalhe nos ADRs.

| Componente | Custo | Benefício | ROI estimado | ADR |
|---|---|---|---|---|
| Vizra ADK (base) | 1 dia setup | ~3 semanas evitadas (memory+eval+tracing) | **~15×** | [arq/0001](adr/arq/0001-vizra-adk-como-base.md) |
| Prism PHP + Claude default | 0 (incluso) | Multi-LLM gratuito; modelos certos por tarefa | **~10×** | [tech/0001](adr/tech/0001-prism-php-claude-padrao.md) |
| Sonnet 4.6 padrão / Opus 4.7 escalation / Haiku 4.5 extração | ~$15/mês | 1/5 do custo de Opus em tudo | **~5×** | [tech/0001](adr/tech/0001-prism-php-claude-padrao.md) |
| Sub-agents domain-sliced (4) | +4h modelagem | ~7× menos tokens/query (carrega só fatia) | **~7×** | [arq/0004](adr/arq/0004-sub-agents-domain-sliced.md) |
| Vector memory no MySQL atual | 0 setup extra | Backup unificado; sem infra nova | **~3×** | [arq/0003](adr/arq/0003-memoria-no-mysql-atual.md) |
| Embeddings Voyage-3-lite (PT-BR) | ~$0.50/mês | ~30% melhor recall em PT-BR vs OpenAI ada | **~2-3×** | [tech/0001](adr/tech/0001-prism-php-claude-padrao.md) |
| Eval LLM-as-Judge + Pest em CI | 2h setup | 1 regressão evitada/mês = 1 dia | **~4×** | [tech/0002](adr/tech/0002-eval-llm-as-judge-em-ci.md) |
| 3 tiers de autonomia (read → comment → PR-draft) | 1 dia/tier | ~30min/semana de chores autônomos | **~25h/ano** | [tech/0003](adr/tech/0003-tres-tiers-de-autonomia.md) |
| Tracing Vizra + Telescope local | 30min | Debug barato; Sentry quando virar prod | **~6×** vs Sentry agora | — |
| CC permanece UX primária (híbrido) | 0 | Mantém adaptação do Wagner; Vizra invisível | **~12×** vs MCP custom | [arq/0002](adr/arq/0002-cc-permanece-ux-primaria.md) |

## 5. Arquitetura

```
┌─ Wagner ↔ Claude Code (CC) ──────────────────────────┐
│                                                       │
│  .claude/agents/evolucao.md   (subagent CC nativo)    │
│  ├─ tools que invoco via Bash:                        │
│  │   php artisan evolution:query "<pergunta>"         │
│  │   php artisan evolution:rank --escopo=<dom>        │
│  │   php artisan evolution:eval                       │
│  │   php artisan evolution:index                      │
│  └─ retorno texto pra CC, CC sintetiza pro Wagner     │
└──────────────────┬───────────────────────────────────┘
                   │ shells out
                   ▼
┌─ Laravel app · Vizra ADK ────────────────────────────┐
│                                                       │
│  EvolutionAgent (router/triage — Sonnet 4.6)         │
│   ├─ FinanceiroAgent     ← memory/requisitos/Financeiro/ + ADRs arq/* │
│   ├─ PontoAgent          ← memory/requisitos/PontoWr2/                │
│   ├─ CmsAgent            ← memory/requisitos/Cms/ + comparativos/     │
│   └─ CopilotoAgent       ← memory/requisitos/Copiloto/                │
│                                                       │
│  Tools (compartilhadas):                              │
│   • ListAdrs(escopo)        • RankByRoi(opts[])       │
│   • PestRun(filter)         • RouteList(filter)       │
│   • ModelSchema(model)      • GitDiffStat(branch)     │
│   • MemoryQuery(q, top_k=5) • EvalGoldenSet()         │
│                                                       │
│  Persistência (MySQL atual):                          │
│   • vizra_agents, vizra_messages, vizra_traces        │
│   • vizra_memory_chunks (+ embedding via Voyage-3)    │
│   • vizra_evaluations, vizra_eval_runs                │
└──────────────────────────────────────────────────────┘
                   │
                   ▼
       GH Actions (eval headless, sem CC online)
```

## 6. Memória — estratégia 3 tiers

| Tier | Conteúdo | Carregamento | Budget |
|---|---|---|---|
| **Hot** | Metas R$5mi + ADR 0026 + branch atual + módulo em foco | Sempre no system prompt | ~1k tokens |
| **Warm** | ADRs/SPECs do escopo via RAG (top-5 chunks semânticos) | Por query, via `MemoryQuery` | ~3-5k tokens |
| **Cold** | Código, schema DB, git log | Só sob demanda via tool específica | sem cap (mas pago) |

Indexação: `php artisan evolution:index` percorre `memory/`, gera embeddings Voyage-3-lite, grava em `vizra_memory_chunks`. Re-indexa só arquivos com `mtime` mais novo que último run. Custo esperado: ~$0.50 inicial, ~$0.05/semana.

## 7. User stories

> Convenção do ID: `US-EVOL-NNN`

### US-EVOL-001 · Indexar `memory/` em vetor

**Como** desenvolvedor (Wagner ou CC) **quero** rodar `php artisan evolution:index` **para** alimentar a memória vetorial com o conteúdo de `memory/`.

**DoD:**
- [ ] Comando lê todo `memory/**/*.md` (ignora `_arquivo/`, `memory_backup/`)
- [ ] Chunking por header (H2/H3) com overlap de 200 tokens
- [ ] Embeddings via Voyage-3-lite (config `vizra.embeddings.provider`)
- [ ] Idempotente: re-rodar não duplica chunks (hash do conteúdo)
- [ ] Re-indexa só arquivos com `mtime` mais recente que `last_indexed_at`
- [ ] Teste Pest cobrindo: arquivo novo, arquivo modificado, arquivo deletado
- [ ] Output: `Indexed N files (M chunks, K skipped, $X.XX cost)`

### US-EVOL-002 · Consultar memória via Artisan (chamado pelo CC)

**Como** Claude Code **quero** rodar `php artisan evolution:query "<pergunta>"` **para** receber top-5 chunks relevantes em vez de re-ler `memory/` inteiro.

**DoD:**
- [ ] CLI aceita `--top=5` (default), `--escopo=<dom>` (filtra por sub-agent)
- [ ] Retorna JSON: `[{file, heading, content, score, tokens}]`
- [ ] Latência <2s pra top-5
- [ ] Teste Pest com fixture de 10 chunks: query "ROI Financeiro" retorna chunk Financeiro/SPEC.md no top-3
- [ ] Custo por query <$0.001 (só embedding da query, não geração)

### US-EVOL-003 · Ranquear oportunidades por ROI

**Como** Wagner (via CC) **quero** rodar `php artisan evolution:rank --escopo=Financeiro` **para** ver top-3 próximos passos com fonte citada.

**DoD:**
- [ ] EvolutionAgent recebe escopo, delega ao sub-agent certo
- [ ] Sub-agent usa tools `ListAdrs`, `MemoryQuery`, `RankByRoi`
- [ ] Output: 3 itens com `{titulo, impacto, esforco, risco, roi_estimado, fontes:[arquivo:linha]}`
- [ ] Tracing salvo em `vizra_traces` (debug via dashboard se precisar)
- [ ] Resposta determinística com `seed=42` em `temperature=0` pra eval
- [ ] Teste Pest: dado fixture de 5 ADRs, ranking top-1 esperado bate (assert via LLM-as-Judge)

### US-EVOL-004 · Eval golden set

**Como** CI **quero** rodar `php artisan evolution:eval` **para** validar que o agente não regrediu.

**DoD:**
- [ ] 5 perguntas-ouro em `tests/Eval/golden.yml` (escopo: Financeiro, Ponto, Cms, Copiloto, geral)
- [ ] LLM-as-Judge avalia cada resposta em [accuracy, citations_correct, tokens_used, cost]
- [ ] Output: relatório markdown + score 0-100
- [ ] Salva baseline em `memory/evolution/baseline.json` (commitado)
- [ ] Falha CI se score cair >5% vs baseline anterior
- [ ] GH Actions roda em PR que toca `app/Services/Evolution/**` ou `Modules/EvolutionAgent/**`

### US-EVOL-005 · Subagent CC `evolucao.md`

**Como** Wagner **quero** invocar `evolucao` no Claude Code **para** que CC use as ferramentas certas (Vizra) sem eu precisar lembrar dos comandos.

**DoD:**
- [ ] Arquivo `.claude/agents/evolucao.md` com YAML frontmatter
- [ ] Descrição: "Use quando Wagner pedir próximo passo, ROI, ou quiser entender estado de um módulo"
- [ ] Tools listadas: Bash (com allowlist `php artisan evolution:*`), Read, Grep, Glob
- [ ] System prompt instrui: sempre chamar `evolution:query` antes de responder, nunca re-ler `memory/` direto
- [ ] Teste manual: 3 perguntas reais; eu (CC) uso o subagent corretamente

### US-EVOL-006 · Tier-2 autonomia: comentar PR

**Como** Wagner **quero** que o agente **comente** em PRs abertos no GH apontando dívida técnica detectada **para** triagem mais rápida.

**DoD:**
- [ ] GH Action `evolution-pr-comment.yml` roda em `pull_request: opened`
- [ ] Tool `OpenPrComment(pr_number, body)` via `gh api`
- [ ] Comenta só se score de relevância >0.7
- [ ] Teste em PR-draft (não em PR real até aceitar)
- [ ] Toggle `EVOLUTION_PR_COMMENT_ENABLED=false` por padrão

### US-EVOL-007 · Tier-3 autonomia: PR-draft autônomo

**Como** Wagner **quero** que o agente **abra PR-draft** pra renomes, dead code e doc stale **para** ganhar 30min/semana.

**DoD:**
- [ ] Cron diário `evolution:propose --auto-pr`
- [ ] Allowlist de mudanças: rename de variável, remove `// TODO removed`, fix link quebrado em `.md`
- [ ] Sempre PR-draft (nunca direto em main)
- [ ] Diff <50 linhas; senão, vira issue
- [ ] Pest verde antes de abrir PR (run local pelo agente)
- [ ] Toggle `EVOLUTION_AUTO_PR_ENABLED=false` por padrão

## 8. Comandos Artisan

| Comando | Uso |
|---|---|
| `evolution:install` | Cria tabelas + seeds + .env keys (Voyage, Anthropic) |
| `evolution:index [--rebuild]` | Indexa `memory/` em vector store |
| `evolution:query "<q>" [--top=5] [--escopo=X]` | Consulta memória, retorna JSON |
| `evolution:rank [--escopo=X] [--top=3]` | Ranqueia oportunidades por ROI |
| `evolution:eval [--baseline] [--update-baseline]` | Roda golden set; falha se regrediu |
| `evolution:propose [--auto-pr]` | Cron job — propõe próximo passo |
| `evolution:trace <id>` | Mostra trace de uma execução (debug) |

## 9. Métricas de sucesso (gates por fase)

| Fase | Gate | Métrica |
|---|---|---|
| 1 (read-only) | Top-1 do agente aceito por Wagner em ≥3/5 testes manuais | manual |
| 2 (eval) | tokens/query reduz ≥3× **e** accuracy ≥80% | `evolution:eval` |
| 3 (autonomia) | ≥60% dos PRs do agente mergeados em 1 semana | GH metrics |
| Geral | Tempo poupado/semana ≥2h após 30 dias | Wagner self-report |

## 10. Cronograma faseado

| Fase | Duração | Bloqueadores | Saída |
|---|---|---|---|
| 0 | até L13 fechar em 6.7-bootstrap | Wagner faz merge | branch limpa |
| 1 | 1 dia | Fase 0 | Vizra instalado + 1 agente + 3 tools + index |
| 2 | 1 dia | Fase 1 verde | golden set + eval em CI + 4 sub-agents |
| 3 | 2 dias | Fase 2 verde + Wagner aprova | tier-2 + tier-3 autonomia |

## 11. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Vizra ADK pacote jovem (~v1.x), pode ter breaking changes | Pin de versão; revisar changelog antes de bump |
| Voyage-3-lite custar mais que esperado | Cap de gasto via env `VOYAGE_MONTHLY_CAP=5`; alarme em 80% |
| Agente sugere besteira convincente | Tier-1 read-only; Wagner sempre revisa |
| Memory/ não cobre tudo (sem código) | Tool `ModelSchema`, `RouteList` pra Tier cold |
| LLM-as-Judge tendencioso (mesmo modelo julgando) | Judge usa Opus 4.7; agente usa Sonnet 4.6 (modelo diferente) |
| Tabelas `vizra_*` colidirem com algo | Prefixo já é único; verificar antes de install |
| Wagner perder paciência se Fase 1 demorar | Cap rígido de 1 dia; se passar, paro e calibro |

## 12. Glossário rápido

- **EvolutionAgent**: agente raiz, faz triage por escopo
- **Sub-agent**: especialista por domínio (FinanceiroAgent, etc.)
- **Memory chunk**: pedaço de markdown indexado (header H2/H3 + conteúdo + embedding)
- **Golden set**: 5 perguntas-fixtures pra eval
- **LLM-as-Judge**: avaliador automático (Opus 4.7) que pontua respostas

## 13. Links

- [ADR ARQ-0001 — Vizra ADK como base](adr/arq/0001-vizra-adk-como-base.md)
- [ADR ARQ-0002 — Claude Code permanece UX primária](adr/arq/0002-cc-permanece-ux-primaria.md)
- [ADR ARQ-0003 — Memória vetorial no MySQL atual](adr/arq/0003-memoria-no-mysql-atual.md)
- [ADR ARQ-0004 — Sub-agents fatiados por domínio](adr/arq/0004-sub-agents-domain-sliced.md)
- [ADR TECH-0001 — Prism PHP + Claude default + tier por tarefa](adr/tech/0001-prism-php-claude-padrao.md)
- [ADR TECH-0002 — Eval LLM-as-Judge em CI](adr/tech/0002-eval-llm-as-judge-em-ci.md)
- [ADR TECH-0003 — 3 tiers de autonomia](adr/tech/0003-tres-tiers-de-autonomia.md)
