---
module: KB
purpose: "Knowledge Base — biblioteca compartilhada de ADRs, sessions, runbooks, specs, comparativos. Browse/search/graph sobre `mcp_memory_documents`. Split do Copiloto pra desacoplar chat IA de browsing canônico."
contains:
  - "KbController — listagem e detalhe"
  - "MemoriaController — tela LGPD 'O Copiloto lembra de você' (US-COPI-MEM-012); URL /copiloto/memoria mantida"
  - "FontesController — config de data source da meta (driver sql/php/http); URL /copiloto/metas/{id}/fonte mantida"
  - "Admin/GraphController — knowledge graph (relationships entre ADRs/sessions/skills); URL /ads/admin/graph mantida"
  - "DataController + InstallController (boilerplate)"
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
  - mcp_memory_documents (sync git → DB via webhook)
  - mcp_memory_documents_history
drift_alerts: []
  # Fase 3.7 PR-1 (2026-05-06): 3 controllers absorvidos do Copiloto/ADS.
  # MemoriaController + FontesController vieram do Copiloto.
  # Admin/GraphController veio do ADS.
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
