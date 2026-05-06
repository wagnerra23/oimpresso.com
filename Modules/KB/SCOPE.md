---
module: KB
purpose: "Knowledge Base — biblioteca compartilhada de ADRs, sessions, runbooks, specs, comparativos. Browse/search/graph sobre `mcp_memory_documents`. Split do Copiloto pra desacoplar chat IA de browsing canônico."
contains:
  - "KbController — listagem e detalhe"
  - "MemoriaController (a migrar de Copiloto Fase 3.7) — admin de mcp_memory_documents (filtros type/module/PII, soft-delete LGPD double-confirm, history)"
  - "FontesController (a migrar de Copiloto Fase 3.7) — knowledge sources"
  - "GraphController (a migrar de ADS Fase 3.7) — knowledge graph (relationships entre ADRs/sessions/skills)"
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
drift_alerts:
  - controller: "(esperando migração)"
    pertence_a: "Modules/Copiloto/Http/Controllers/MemoriaController.php"
    motivo: "MemoriaController hoje vive em Copiloto mas conceitualmente é KB"
    eta_migracao: "Fase 3.7"
  - controller: "(esperando migração)"
    pertence_a: "Modules/Copiloto/Http/Controllers/FontesController.php"
    motivo: "FontesController também é KB"
    eta_migracao: "Fase 3.7"
  - controller: "(esperando migração)"
    pertence_a: "Modules/ADS/Http/Controllers/Admin/GraphController.php"
    motivo: "Knowledge graph pertence ao KB"
    eta_migracao: "Fase 3.7"
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

## Drift atual

3 controllers que **deveriam** estar aqui mas estão em Copiloto/ADS. Migração em Fase 3.7.

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. 3 controllers pendentes de migração.
