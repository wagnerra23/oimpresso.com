---
module: KB
purpose: "Knowledge Base — biblioteca compartilhada de ADRs, sessions, runbooks, specs, comparativos. Browse/search/graph sobre `mcp_memory_documents`. Split do Copiloto pra desacoplar chat IA de browsing canônico."
contains:
  - "KbController — listagem e detalhe (legacy V0 — KB browser dos docs MCP)"
  - "MemoriaController — tela LGPD 'O Copiloto lembra de você' (US-COPI-MEM-012); URL /copiloto/memoria mantida"
  - "FontesController — config de data source da meta (driver sql/php/http); URL /copiloto/metas/{id}/fonte mantida"
  - "Admin/GraphController — knowledge graph ADS (relationships entre Skills/Meta-skills/Tools/Policy/Memory); URL /ads/admin/graph mantida"
  - "DataController + InstallController (boilerplate)"
  - "KbNodeController — CRUD kb_nodes (artigos + bridge canônico) — ONDA 1 ADR 0149"
  - "KbEdgeController — CRUD kb_edges manuais (auto-derivados via KbEdgeAutoDeriverJob) — ONDA 1 ADR 0149"
  - "KbPathController — CRUD trilhas de aprendizado (kb_paths + kb_path_steps) — ONDA 1 ADR 0149"
  - "KbDecisionTreeController — CRUD troubleshooters (kb_decision_trees + kb_decision_tree_steps) — ONDA 1 ADR 0149"
  - "KbCommentController — comments inline ancorados em block_idx (kb_comments) — ONDA 1 ADR 0149"
  - "KbFavoriteController — favoritos pessoais por user (kb_favorites) — ONDA 1 ADR 0149"
  - "KbVersionController — histórico de versões com diff (kb_node_versions append-only) — ONDA 1 ADR 0149"
  - "KbAiController — IA RAG endpoints /kb/ai/{ask,summarize,suggest-meta} (delega Modules/Jana/Ai/Agents/KbAnswerAgent) — ONDA 4 ADR 0149"
not_contains:
  - "Chat IA (Jana) → Modules/Copiloto"
  - "MCP server admin (tokens, webhooks) → Modules/TeamMcp"
  - "Skills governance → Modules/ADS"
  - "System Rules Spec (regras pra IA programar) → Modules/MemCofre (futuro SRS)"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "Audit log → Modules/TeamMcp + Modules/Governance"
trust_required: L2
owner: wagner
permission_prefix: kb.*
charter_adr: 0080
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
url_prefixes:
  - /kb/*
  - /copiloto/admin/memoria/* (legacy — vai virar /kb/memoria/* na Fase 3.7 com 301 redirect)
db_tables_owned:
  - mcp_memory_documents (sync git → DB via webhook — bridge read-only fotografia git)
  - mcp_memory_documents_history
  # ONDA 1 ADR 0149 (2026-05-15) — 11 tabelas novas do KB Unificado como Grafo de Conhecimento:
  - kb_nodes (artigos editáveis + bridge canônico)
  - kb_edges (arestas tipadas: next-in-path/fix-of/supersedes/charter-of/references-data/ai-related/cross-link/related-by-tag)
  - kb_categories
  - kb_subcategories
  - kb_paths
  - kb_path_steps
  - kb_decision_trees
  - kb_decision_tree_steps
  - kb_node_versions (append-only)
  - kb_favorites
  - kb_comments
  - kb_bridge_state
drift_alerts: []
  # Fase 3.7 PR-1 (2026-05-06): 3 controllers absorvidos do Copiloto/ADS.
  # MemoriaController + FontesController vieram do Copiloto.
  # Admin/GraphController veio do ADS.
  # ONDA 1 ADR 0149 (2026-05-15): 8 controllers novos (KbNode/Edge/Path/DecisionTree/Comment/Favorite/Version/Ai)
  # + 11 tabelas kb_*. KB re-escopado como módulo IA central do oimpresso.
---

# Modules/KB — Knowledge Base

## Missão

Browser canônico de **conhecimento estruturado** do oimpresso: ADRs, sessions, specs, comparativos, runbooks. UI de governança Wagner (`/copiloto/admin/memoria` → vai virar `/kb/memoria`). 352 docs sincronizados via webhook GitHub → `mcp_memory_documents`.

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Wagner busca ADR sobre X | L1 | search hybrid em `mcp_memory_documents` |
| Wagner abre detalhe de session | L1 | preview markdown render + git_sha→GitHub |
| Wagner soft-delete doc LGPD | L1 | double-confirm + audit log |
| Push em `memory/*` | webhook | sync DB cache (≤60s) |

## Quando NÃO é tocado

- ❌ Conversar com IA → Modules/Copiloto (Jana)
- ❌ Editar SKILL.md → Modules/ADS
- ❌ Editar regra imutável de programação → Modules/MemCofre (SRS)
- ❌ Browse de tarefas → Modules/ProjectMgmt

## Drift resolvido (Fase 3.7 PR-1, 2026-05-06)

Os 3 controllers absorvidos: MemoriaController + FontesController (do Copiloto), Admin/GraphController (do ADS). URLs mantidas — só namespace mudou.

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. 3 controllers pendentes de migração.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: 3 controllers absorvidos. drift_alerts vazio.
