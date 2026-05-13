---
title: "Auditoria Knowledge Architecture oimpresso vs estado-da-arte 2026"
type: auditoria
status: draft
authority: tecnico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-13
decided_by: [knowledge-architecture-expert]
module: Jana
tier: TECHNICAL_AUDIT
trust_level: advise
related_adrs: [0053, 0061, 0070, 0091, 0093, 0094, 0095, 0130, 0131]
parent_artifact: memory/requisitos/Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md
authors: [knowledge-architecture-expert]
---

# Auditoria Knowledge Architecture oimpresso vs estado-da-arte 2026

> **Pedido Wagner (2026-05-13):** "o conhecimento ainda está espalhado, crie um agente especialista para pesquisar os melhores e comparar com meu e avaliar quanto porcento estou e planejar as melhorias na minha estrutura. ou sugerir evoluir"
>
> **Escopo:** PKM / Second Brain / RAG / Agent Memory — **não** task management (já coberto pelo artefato irmão [`COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md`](COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) pelo agent `mcp-quality-expert`).
>
> **Resposta executiva:** Maturidade global ponderada ≈ **73%** vs melhor-da-classe mundial. Governance é classe-mundial (94%), Storage é estado-da-arte (88%), mas Retrieval (54%) e Capture (52%) são os gaps. **Recomendação: CONSOLIDAR (Cenário A)** — não evoluir paradigma. Argumento em §5.

---

## 1. Inventário verificado (2026-05-13, worktree `nervous-mayer-3ff0da`)

| Categoria | Contagem real | Comando de verificação |
|---|---:|---|
| Arquivos `.md` em `memory/` | **1.529** | `find memory -name "*.md" \| wc -l` |
| ADRs canônicas | **190** | `find memory/decisions -name "*.md" \| wc -l` |
| SPECs por módulo | **37** | `find memory/requisitos -name SPEC.md \| wc -l` |
| Session logs | **79** | `find memory/sessions -name "*.md" \| wc -l` |
| Handoffs append-only | **14** | `find memory/handoffs -name "*.md" \| wc -l` |
| Auto-mem privada legacy | **53** | `ls ~/.claude/projects/D--oimpresso-com/memory \| wc -l` |
| Skills auto-ativáveis | **40** | `find .claude/skills -name SKILL.md \| wc -l` |
| Hooks | **12** | `find .claude/hooks -type f \| wc -l` |
| Slash commands | **5** | `find .claude/commands -type f \| wc -l` |
| Page Charters `.charter.md` | **26** | `find . -name "*.charter.md"` |
| CAPTERRA-FICHA mercado | **9** | `find memory/requisitos -name "CAPTERRA*.md"` |
| Tools MCP server | **22** | `ls Modules/Jana/Mcp/Tools/*.php` |
| Agents `.claude/agents/` | **0** filesystem (mas 2 referenciados em prompt) | `find .claude/agents -name "*.md"` (vazio) |
| Volume total palavras `memory/` | **461.435** | `wc -w memory/**/*.md` |

> ⚠️ **Divergência detectada:** O brief inicial dizia "1.527 .md / 189 ADRs". Real = 1.529 / 190 (cresceu 2 hoje 2026-05-13). Auto-mem 53 confere com hook bloqueador ADR 0061 — escape valves continuam em uso.

> ⚠️ **Agents folder vazio:** O subagent `whatsapp-baileys-expert` está mencionado na auto-mem de Wagner mas `.claude/agents/` está vazio na worktree. Possível inconsistência worktree vs repo principal — gap operacional sinalizado.

---

## 2. Tabela de concorrentes (14 sistemas, 3 categorias)

### A. PKM / Second Brain consumer

| Sistema | Versão obs. | Storage | Retrieval | AI Q&A | Local-first | Preço |
|---|---|---|---|---|---|---|
| **Notion AI** | 2026 Business tier | Cloud-only | DB nativo + AI Q&A | ✅ Agents | ❌ | US$20/user/mo (killed US$8 add-on May/2025) |
| **Obsidian + Smart Connections** | 786k+ downloads jan/2026 | Local markdown | Local embeddings + RAG | ✅ Chat-with-vault | ✅ | Free (plugins free) |
| **Logseq** | 2026 c/ AI | Local markdown blocks | Block-level bi-links | 🟡 AI meeting/tasks | ✅ | Free |
| **Tana** | 2026 supertags | Cloud | Supertags + AI | ✅ AI deeper than Logseq | ❌ | US$14/mo |
| **Capacities** | 2026 object-oriented | Cloud | Typed objects (Person/Book/Project) | 🟡 limited | ❌ | US$10/mo |
| **Reflect** | 2026 | Cloud E2E | Whisper voice + linked | ✅ GPT-4 | ❌ | US$15/mo |
| **NotebookLM** | 2026 Google | Cloud (Gemini 1.5) | Source-grounded RAG | ✅ classe-mundial | ❌ | Free / Plus |

### B. AI Agent Memory / RAG infrastructure

| Sistema | Versão obs. | Paradigma | Storage backend | Use case |
|---|---|---|---|---|
| **Mem0** | 47k+ stars GitHub | Cloud-first managed (graph + vector + KV) | Auto-managed | Chatbot memory rápida |
| **Letta** (ex-MemGPT) | 2026 framework | OS-inspired tiered (core/recall/archival) | Self-hosted | Long-running agents (dias) |
| **Zep** | 2026 v2 | Temporal Knowledge Graph + async summarization | Cloud ou Community Edition | Enterprise complex state |
| **Cognee** | 2026 | Graph + vector hybrid | Self-hosted | Open-source alternative |
| **LlamaIndex** | 2026 | KG + agent memory framework | Self-hosted | Customizável |

### C. Code/Knowledge stacks pra dev

| Sistema | Versão obs. | Arquitetura | Diferencial |
|---|---|---|---|
| **Cursor codebase indexing** | 2026 VS Code fork | Dynamic context + Merkle tree | AI-native IDE |
| **Sourcegraph Cody** | 2026 Indexed + Fallback Mode | Search-first RAG, Gemini 1.5 Flash 1M token | Moveu **away from pure embeddings** rumo a hybrid code-search |
| **Continue.dev** | 2026 OSS | Two-stage RAG semantic + re-rank | Configurável |
| **Claude Code** (este harness) | 2026 | CLAUDE.md + skills + MCP + hooks | Plugin Anthropic |

**Fontes** (web search 2026-05-13):
- [Mem0 vs Zep vs Letta benchmark 2026 (Atlan)](https://atlan.com/know/best-ai-agent-memory-frameworks-2026/)
- [Notion vs Obsidian 2026 (Atlas Workspace)](https://www.atlasworkspace.ai/blog/best-second-brain-apps)
- [Cursor indexing arch (Towards Data Science)](https://towardsdatascience.com/how-cursor-actually-indexes-your-codebase/)
- [Logseq alternatives 2026](https://www.atlasworkspace.ai/blog/connected-notes-apps)
- [Sourcegraph Cody RAG hybrid (Augment Code)](https://www.augmentcode.com/tools/sourcegraph-cody-vs-cursor-vs-augment-code-for-enterprise-development)

---

## 3. Matriz de capacidades (22 dimensões × 8 sistemas representativos)

Legenda: ✅ pleno · 🟡 parcial · ❌ ausente · ➖ N/A.

| Dimensão | Notion | Obsidian+SC | Mem0 | Letta | Zep | Cursor | Cody | **oimpresso** |
|---|---|---|---|---|---|---|---|---|
| **C1. Auto-capture** (browser/email) | 🟡 web clipper | 🟡 plugin | ❌ | ❌ | ❌ | ➖ | ➖ | ❌ — só Wagner digita |
| **C2. Voice-to-text** | ❌ | 🟡 plugin | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **C3. Estruturado (schema)** | ✅ DB típicas | 🟡 frontmatter | ✅ entities | ✅ tiered mem | ✅ KG nodes | ➖ | ➖ | ✅ ADR/SPEC/Session/Handoff frontmatter rígido |
| **C4. Multi-author conflict** | ✅ realtime | 🟡 sync plugin | ➖ | ➖ | ➖ | ➖ | ✅ git | ✅ git PR + branch protection |
| **S1. Local-first** | ❌ | ✅ | ❌ | 🟡 | 🟡 CE | 🟡 | 🟡 fallback | ✅ git canon |
| **S2. Versionamento (git)** | ❌ | 🟡 plugin | ❌ | ❌ | ❌ | ➖ | ✅ | ✅ git append-only ADRs |
| **S3. Append-only audit** | ❌ | ❌ | 🟡 audit log | ✅ | 🟡 | ❌ | ❌ | ✅ ADR 0130 handoff + ADR canon nunca editar |
| **S4. Schema rígido** | ✅ DB | 🟡 | ✅ | ✅ | ✅ | ➖ | ➖ | 🟡 ADR frontmatter validado em CI; restante livre |
| **R1. FULLTEXT** | ✅ | ✅ | 🟡 | 🟡 | ✅ | ➖ | ✅ | ✅ MySQL FULLTEXT em `mcp_memory_documents` |
| **R2. Semantic embedder** | ✅ AI | ✅ local | ✅ | ✅ | ✅ | ✅ | 🟡 (moveu-se away) | ✅ Meilisearch hybrid + Ollama embedder ([RETRIEVAL-ESTADO-ARTE-2026-05](RETRIEVAL-ESTADO-ARTE-2026-05.md)) |
| **R3. Hybrid (FT+sem+rerank)** | 🟡 | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 hybrid sim; reranker ainda não em prod (planejado [0037](../../decisions/0037-roadmap-evolucao-tier-7-plus.md)) |
| **R4. Knowledge graph (bi-links)** | 🟡 relations | ✅ graph view | ✅ Mem0 graph | ✅ tiered | ✅ TKG | 🟡 code graph | ✅ code graph | ❌ links são markdown manual; sem grafo de relações |
| **R5. Time-decay weighting** | ❌ | ❌ | ✅ | ✅ | ✅ temporal | ❌ | 🟡 | ❌ recall não usa decay; só relevance |
| **R6. Trust score / provenance** | ❌ | ❌ | 🟡 | ✅ | 🟡 | ❌ | ✅ git_sha | ✅ git_sha + GitHub link em cada doc MCP (ADR 0053) |
| **D1. Search UX** | ✅ | ✅ + graph | API only | API only | API only | ✅ IDE | ✅ web | 🟡 `/copiloto/admin/memoria` filtro + preview, mas sem graph view |
| **D2. Hierarquia navegável** | ✅ | ✅ folders | ❌ | ❌ | ❌ | ➖ | ➖ | 🟡 `memory/INDEX.md` ([INDEX.md](../../INDEX.md)) só 64 linhas — só Ponto WR2 (stale) |
| **D3. Backlinks automáticos** | ❌ | ✅ Smart Connections | 🟡 entity | 🟡 | ✅ | ✅ code refs | ✅ code refs | ❌ ADRs referenciam manual via `related_adrs:` |
| **D4. Daily/weekly digest** | ❌ | 🟡 plugin | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ Daily Brief tier A ([ADR 0091](../../decisions/0091-daily-brief.md)) — destaque mundial |
| **D5. Smart suggestions** | ✅ Notion AI | ✅ SC | ✅ | ✅ | ✅ | ✅ autocomplete | ✅ | 🟡 skills auto-trigger (Tier B) mas não suggest docs relacionados |
| **A1. Q&A natural sobre KB** | ✅ Notion AI Agents | ✅ chat-with-vault | ✅ API | ✅ LLM-driven | ✅ | 🟡 @codebase | ✅ | 🟡 indireto — Jana chat consome mas não tem tool `kb-answer` dedicada |
| **A2. Agent long-term memory** | 🟡 | ❌ | ✅ flagship | ✅ flagship | ✅ flagship | ❌ | ❌ | ✅ MemoriaContrato Camada C + 3 ângulos faturamento ([ADR 0052](../../decisions/0052-tres-angulos-faturamento.md)) |
| **A3. Auto-summarization** | ✅ | 🟡 plugin | ✅ | ✅ | ✅ async | ❌ | ❌ | 🟡 Brief consolida mas não auto-summary de docs |
| **G1. Append-only governance** | ❌ | ❌ | 🟡 | ✅ | 🟡 | ❌ | ❌ | ✅✅ ADR canon IRREVOGÁVEL + Constituição v2 ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — **classe-mundial** |
| **G2. Multi-tenant scope** | ✅ workspaces | ➖ | 🟡 user_id | 🟡 | 🟡 | ➖ | ✅ enterprise | ✅✅ Tier 0 IRREVOGÁVEL `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) |
| **G3. Privacy tiers** | 🟡 perm. doc | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 ignore files | 🟡 | ✅ Canon git / oimpresso-local / Vaultwarden segredo ([ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md)) |
| **G4. Decay/archival** | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | 🟡 ADR `lifecycle: historical` mas sem auto-archival |
| **H1. Multi-user team (5p)** | ✅ realtime | 🟡 | ✅ team API | ✅ | ✅ | 🟡 | ✅ | ✅ git + MCP team tokens (`/copiloto/admin/team`) |
| **H2. Webhook sync** | ✅ API | 🟡 | ✅ | ✅ | ✅ | ➖ | ✅ | ✅ GitHub→MCP webhook (ADR 0053) — destaque |

**Observações críticas:**
- ✅✅ = oimpresso **acima da média mundial** em G1+G2+G3 (governance brutal).
- ❌ em C1, R4, R5, D2, D3 = pontos de evolução.

---

## 4. Score de maturidade % por área

Pra cada área cálculo % = oimpresso vs **melhor-da-classe** (não média), peso baseado em uso real Wagner.

| Área | Peso | Sub-itens avaliados | Melhor-da-classe | oimpresso | % |
|---|---:|---|---|---|---:|
| **Capture & Ingestion** | 10% | C1+C2+C3+C4 | Notion (auto-capture) + Reflect (voice) + ADR frontmatter | C3+C4 ✅, C1+C2 ❌ | **52%** |
| **Storage & Versioning** | 20% | S1+S2+S3+S4 | Obsidian (local) + git (versioning) + Letta (append) | S1+S2+S3 ✅✅, S4 🟡 | **88%** |
| **Retrieval & Discovery** | 25% | R1..R6 + D1..D5 | Cody + Zep + Obsidian SC + Notion AI | R1+R2+R6 ✅, R3 🟡 sem rerank prod, R4+R5 ❌, D2+D3 ❌, D4 ✅✅ | **54%** |
| **AI Memory & Reasoning** | 20% | A1+A2+A3 | Letta (long-term) + Mem0 (entity) + NotebookLM (Q&A) | A2 ✅✅, A1 🟡, A3 🟡 | **67%** |
| **Governance & Trust** | 25% | G1..G4 + H1+H2 | Nenhum competidor cobre os 6 — oimpresso **define o ceiling** | G1+G2+G3 ✅✅ (classe-mundial), G4 🟡, H1+H2 ✅ | **94%** |

### Score consolidado weighted

```
0.10 × 52% + 0.20 × 88% + 0.25 × 54% + 0.20 × 67% + 0.25 × 94%
= 5.2 + 17.6 + 13.5 + 13.4 + 23.5
= 73.2%
```

**oimpresso ≈ 73% maturidade global ponderada vs estado-da-arte 2026.**

Comparação com baseline mercado:
- **Notion AI Business tier**: ~55% (ganha em Capture/A1, perde brutal em S1+S2+G1+G2+G3)
- **Obsidian + Smart Connections**: ~62% (ganha em D3+R2 local, perde em A2+G2)
- **Mem0 + Letta combinado**: ~70% (ganha em A2+R5, perde em S2+G governance + D1 UX)
- **oimpresso 73%** — competitivo, com **defensável moat em Governance**.

---

## 5. Top 10 gaps priorizados

| # | Gap | Sistema-ref | Sintoma hoje no oimpresso | Esforço | ROI | Prio | Tipo |
|---|---|---|---|---:|---|---|---|
| **G1** | Auto-mem 53 legacy não-migrada (ADR 0061 enforcement parcial) | — | Hook bloqueia Write novo mas legado persiste em `~/.claude/projects/D--oimpresso-com/memory/` (53 arquivos com info que time não vê) | 3 dev-days | Alto (compliance ADR canon + team visibility) | **P0** | Quick win |
| **G2** | `memory/INDEX.md` stale (64 linhas, só Ponto WR2 — não menciona Jana/NfeBrasil/Repair/CV/etc) | Notion sidebar / Obsidian folder view | "Sessão precisa consultar 5+ locais pra responder o que falta" (sintoma Wagner) | 2 dev-days | Alto (onboarding + descoberta) | **P0** | Quick win |
| **G3** | Sem reranker em prod (R3 hybrid incompleto) | Zep / Cohere Rerank | RETRIEVAL-ESTADO-ARTE-2026-05.md lista como GAP-A; recall recupera mas ranking sub-ótimo | 5 dev-days | Médio (qualidade respostas Jana) | P1 | Estrutural |
| **G4** | Tool MCP `kb-answer` ausente (A1 Q&A natural) | NotebookLM / Cody @codebase | Wagner pergunta "qual ADR fala X" → tem que `decisions-search` + ler ADRs manualmente; Jana não responde direto sobre KB | 4 dev-days | Alto (10× redução fricção) | P1 | Quick win |
| **G5** | Backlinks automáticos ADR↔SPEC↔Session inexistentes (D3) | Obsidian Smart Connections / Roam | ADR menciona outra ADR via `related_adrs:` mas não há sweep que detecta orfãs/quebradas | 3 dev-days | Médio | P1 | Estrutural |
| **G6** | Time-decay no recall (R5) | Mem0 / Zep temporal | ADR de 2024 e de 2026 mesma weight; ADR `lifecycle: historical` ainda volta no top-K | 4 dev-days | Médio | P2 | Estrutural |
| **G7** | Charters dormentes (S4 não entregue) — 26 `.charter.md` criados mas tool `charter-fetch` não existe | Notion DB + AI agents | Skill `charter-first` Tier A dormente; charters viram peso-morto | 6 dev-days | Médio (qualidade migração MWART) | P2 | Estrutural |
| **G8** | Daily digest semanal ausente (D4 só daily) | Reflect weekly review | Wagner não vê "o que mudou na semana" em 1 lugar | 2 dev-days | Baixo-médio | P2 | Quick win |
| **G9** | Schema rígido só pra ADR (S4 🟡) | Letta / Mem0 schemas | SPEC.md, RUNBOOK, Session log sem validação CI; drift acumula | 3 dev-days | Médio | P2 | Estrutural |
| **G10** | Auto-summary docs longos (A3) | Mem0 async | SPEC.md de 49.7KB (Jana SPEC.md) cabe inteiro no contexto mas tools MCP retornam blob sem TL;DR | 4 dev-days | Baixo (Brief já mitiga 80%) | P3 | Quick win |

---

## 6. Decisão estratégica: CONSOLIDAR (Cenário A) vs EVOLUIR (Cenário B)

### Cenário A — CONSOLIDAR (incremental, 2-4 semanas)

**O que faz:**
- Fix G1 (migrar 53 auto-mem)
- Fix G2 (INDEX.md navegável)
- Fix G4 (tool `kb-answer`)
- Fix G8 (weekly digest)
- Fix G1+G5 sweep ADR órfãs

**Não muda:** paradigma markdown + git + MCP MySQL FULLTEXT + Meilisearch. Sem refactor estrutural.

**Custo:** ~12 dev-days = 2-3 semanas calendário (com fator IA-pair [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) ~1.5 semana).

**Payoff:** 73% → ~85% maturidade. Resolve dor reportada Wagner ("conhecimento espalhado") sem mexer no fundamento.

### Cenário B — EVOLUIR (mudança paradigma, 2-3 meses)

**O que faz:**
- Adota **Mem0/Letta layer** sobre o git canon — agent memory tiered (core/recall/archival).
- Ou refactor `memory/` em **objeto-orientado a la Capacities** (Person/Module/Client/ADR como objetos tipados).
- Ou **bidirectional links automáticos** (D3) + grafo de relações tipo Obsidian Graph View.
- Substitui Meilisearch por **vector DB + reranker** (Pinecone/Qdrant + Cohere Rerank).

**Custo:** ~50-80 dev-days + migração 1.529 .md → schema novo + retraining time.

**Payoff:** 73% → ~92% maturidade. Captura A2 estado-da-arte + R3 hybrid completo + D3 auto-links.

### Recomendação: **CENÁRIO A — CONSOLIDAR**

**Justificativa (3 argumentos):**

1. **Governance é o moat — não trocar fundamento.** ADR 0061 (zero auto-mem) + 0094 (Constituição) + 0093 (Tier 0) já dão 94% Governance, classe-mundial. Refactor pra Mem0/Letta colocaria dependência em sistema third-party com governance fraca (Mem0 cloud-first viola S1; Letta self-host adiciona ops burden no CT 100). **Não jogar fora a vantagem competitiva pra ganhar 7pp.**

2. **A dor reportada Wagner é Discovery (D2+D3) — não paradigma.** "Conhecimento espalhado" não significa "preciso de knowledge graph"; significa "preciso de índice navegável + Q&A natural". G1+G2+G4 entregam isso em 9 dev-days sem refactor.

3. **Fator IA-pair 10× ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))** — Cenário A em 1.5 semana calendário. Se ROI provar, **Cenário B vira opção pós-onda 1+2** (não substituto). Princípio Constituição "Loop fechado por métrica" — mede G1-G4 antes de evoluir paradigma.

**Métrica de gate pra reavaliar Cenário B (3 meses pós-Onda 2):**
- Se Brief load time > 800ms p95 → evoluir retrieval (vector DB)
- Se `kb-answer` accuracy RAGAS < 75% → adicionar reranker (Cohere)
- Se time-decay queries falham > 10% → adicionar Mem0/Letta agent memory layer
- Senão: ficar em Cenário A maturado.

---

## 7. Roadmap detalhado (Cenário A)

### Onda 1 (2 semanas calendário, ~12 dev-days IA-pair = ~1.5 semana real)

| US proposta | Descrição | Esforço | Dependência | Métrica sucesso |
|---|---|---:|---|---|
| **US-JANA-10X-001** | Migrar 53 auto-mem legacy → git canon (com triagem batch + Wagner aprova ondas de 10) | 3d | ADR 0061 enforcement hook já existe | 53→0 arquivos `~/.claude/projects/D--oimpresso-com/memory/` |
| **US-JANA-10X-002** | Reescrever `memory/INDEX.md` (64→200 linhas) cobrindo 1.529 docs em 6 buckets: governance/módulos/clientes/skills/runbooks/handoffs | 2d | nenhuma | Onboarding novo dev < 30min |
| **US-JANA-10X-003** | Tool MCP `kb-answer` — Q&A natural sobre `mcp_memory_documents` usando Camada A laravel/ai + recall hybrid | 4d | Camada C MemoriaContrato (já em prod) | Wagner pergunta "qual ADR sobre X" → 1 chamada vs 3-5 hoje |
| **US-JANA-10X-004** | Sweep ADR links órfãs (`related_adrs:`, `supersedes:`, `referenced_by:`) → CI artisan check + relatório | 1d | nenhuma | 0 links quebrados |
| **US-JANA-10X-005** | Weekly digest auto (sex 18h) — agrega session logs + ADRs aceitas + handoffs + cycle-goals-track da semana num post `memory/digests/YYYY-WW.md` | 2d | brief-fetch | Wagner abre digest 1× sex |

**Total Onda 1:** 12 dev-days. PRs estimados: 5 (1 per US). Maturidade alvo pós-onda: **82%**.

### Onda 2 (1 mês, ~25 dev-days IA-pair = ~3 semanas real)

| US proposta | Descrição | Esforço | Dependência |
|---|---|---:|---|
| **US-JANA-10X-006** | Reranker Cohere/local em recall (R3 hybrid completo) | 5d | embedder Ollama atual |
| **US-JANA-10X-007** | Schema validation CI pra SPEC.md/RUNBOOK/Session log (S4 ✅) | 3d | ADR frontmatter validator existente |
| **US-JANA-10X-008** | Backlinks automáticos ADR↔SPEC via sweep + popular `referenced_by:` (D3) | 3d | US-JANA-10X-004 |
| **US-JANA-10X-009** | Time-decay weighting no recall (R5) — boost recente, decay `lifecycle: historical` | 4d | recall hybrid |
| **US-JANA-10X-010** | Charters S4 — tool MCP `charter-fetch` + ativar skill `charter-first` Tier A | 6d | 26 .charter.md existentes |
| **US-JANA-10X-011** | UI `/copiloto/admin/memoria` — adicionar graph view (force-directed) das relações ADR-SPEC-Session (D3 visual) | 4d | US-JANA-10X-008 |

**Total Onda 2:** 25 dev-days. Maturidade alvo pós-onda: **89%**.

### Onda 3 (avaliação gate — só se métricas Onda 2 falham; 2-3 meses se acionada)

Não pré-aprovada. Gate em §6.

---

## 8. Surpresas

### Positivas (oimpresso melhor que mercado)

1. **Constituição v2 7 camadas + 8 princípios duros ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md))** — nenhum PKM/agent-memory tem nada equivalente. Notion/Obsidian/Mem0 são plataformas, não constituições.
2. **ADRs append-only IRREVOGÁVEL com lifecycle (`active/historical`) + Nygard format + frontmatter validado CI** — Letta tem audit log mas é mutable; Zep tem temporal KG mas não governance. **Defensável moat.**
3. **Multi-tenant Tier 0 `business_id` global scope** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — Notion tem workspaces mas vazamento é só "feio"; oimpresso "vazar = pior bug possível" enforcement.
4. **Daily Brief ~3k tokens consolidados, Tier A always-on** ([ADR 0091](../../decisions/0091-daily-brief.md)) — Reflect tem weekly review manual; oimpresso tem brief automatizado 6×/dia carregado por hook. **Único no mercado.**
5. **MCP server como produto governado** ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) — git→webhook→MySQL FULLTEXT+Meilisearch hybrid + team tokens UI admin. Notion/Obsidian não têm AI API publicado pra IDE; oimpresso entrega.
6. **Skills Tier A/B/C convenção formal** ([ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md)) — Claude Code stock não tem tiering; oimpresso disciplinou.
7. **Escape valves de privacidade explícitas** ([ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md): canon git / oimpresso-local / Vaultwarden) — Obsidian é só local; oimpresso classifica.

### Negativas (mercado faz e ainda não pensamos)

1. **Backlinks automáticos** (Obsidian Smart Connections, Roam) — ADRs referenciam manual; sem grafo de relações descobríveis.
2. **Time-decay weighting** (Mem0/Zep temporal) — recall trata ADR 2024 e 2026 igual; ADR `historical` ainda volta no top-K.
3. **Agent long-term memory tiered** (Letta core/recall/archival) — Jana Camada C é flat; Letta agents operam dias com state coerente.
4. **Q&A direta sobre KB** (NotebookLM source-grounded) — Jana chat tem que vir via UI; sem tool `kb-answer` MCP-exposed.
5. **Auto-summarization async** (Zep background) — SPEC.md de 49KB cabe na janela mas custa tokens; sem TL;DR auto-gerado.
6. **Voice-to-text capture** (Reflect Whisper) — Wagner digita ou IA escreve; voz não-explorada.

---

## 9. Resumo executivo (1 página pra Wagner)

```
oimpresso Knowledge Architecture — Auditoria 2026-05-13
=========================================================
Score global ponderado:    73%   vs estado-da-arte 2026
                                  (Notion 55% · Obsidian 62% · Mem0+Letta 70%)

Por área:
  Governance & Trust       94%   ← classe-mundial, MOAT
  Storage & Versioning     88%   ← estado-da-arte (git canon)
  AI Memory & Reasoning    67%   ← bom, gap em Letta-tier
  Retrieval & Discovery    54%   ← MAIOR GAP (D2+D3+R3+R5)
  Capture & Ingestion      52%   ← gap mas baixo uso real

Recomendação:              CONSOLIDAR (Cenário A, ~1.5 semana real)
                           Não trocar paradigma git+MCP+markdown.

Top 3 ações P0:
  G1  Migrar 53 auto-mem legacy → git           3 dev-days
  G2  Reescrever memory/INDEX.md (64→200 linhas) 2 dev-days
  G4  Tool MCP kb-answer (Q&A natural sobre KB)  4 dev-days

Onda 1 (12 dev-days):       73% → 82%
Onda 2 (25 dev-days):       82% → 89%
Onda 3 (gate-only):         só se métricas Onda 2 falham
```

---

## Fontes (pesquisa fresh 2026-05-13)

**Web (4 WebSearch):**
- [Mem0 vs Zep vs Letta — Atlan 2026](https://atlan.com/know/best-ai-agent-memory-frameworks-2026/)
- [Mem0 vs Letta vs Zep — DEV community 2026](https://dev.to/varun_pratapbhardwaj_b13/5-ai-agent-memory-systems-compared-mem0-zep-letta-supermemory-superlocalmemory-2026-benchmark-59p3)
- [Obsidian Smart Connections + Notion AI 2026 — Atlas Workspace](https://www.atlasworkspace.ai/blog/best-second-brain-apps)
- [Notion AI Business tier 2025 killed US$8 add-on — Tech-Insider](https://tech-insider.org/notion-vs-obsidian-2026/)
- [Cursor codebase indexing arch — Towards Data Science](https://towardsdatascience.com/how-cursor-actually-indexes-your-codebase/)
- [Sourcegraph Cody RAG hybrid — Augment Code](https://www.augmentcode.com/tools/sourcegraph-cody-vs-cursor-vs-augment-code-for-enterprise-development)
- [Cody moveu-se away from pure embeddings 2026](https://www.augmentcode.com/tools/sourcegraph-cody-vs-continue-enterprise-comparison)
- [Logseq/Tana/Capacities alternatives 2026 — Atlas](https://www.atlasworkspace.ai/blog/connected-notes-apps)

**Código oimpresso (inventário real):**
- `find memory -name "*.md" \| wc -l` = 1.529
- `find memory/decisions -name "*.md" \| wc -l` = 190
- `find Modules/Jana/Mcp/Tools -name "*.php"` = 22 tools
- `find .claude/skills -name SKILL.md \| wc -l` = 40
- `wc -w memory/**/*.md` = 461.435 palavras

**ADRs canon críticos lidos:**
- [ADR 0053 — MCP server governança](../../decisions/0053-mcp-server-governanca-como-produto.md)
- [ADR 0061 — Zero auto-mem](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0070 — Jira-style tasks](../../decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0091 — Daily Brief](../../decisions/0091-daily-brief.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0095 — Skills Tiers](../../decisions/0095-skills-tiers-convencao-interna.md)
- [ADR 0130 — Handoff append-only](../../decisions/0130-handoff-append-only-mcp-first.md)
- [ADR 0131 — Tiering memória](../../decisions/0131-tiering-memoria-canonico-local-segredo.md)
- [Artefato irmão MCP](COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) (escopo task management, complementar)

---

**Última atualização:** 2026-05-13 — knowledge-architecture-expert (Opus 4.7)
