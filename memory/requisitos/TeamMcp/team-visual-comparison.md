---
slug: teammcp-team-visual-comparison
title: "TeamMcp — Comparativo visual da tela Team (painel MCP)"
type: visual-comparison
module: TeamMcp
status: approved
approved_by: wagner
approved_at: 2026-06-16
date: 2026-06-16
canon_reference: forja-cowork (forja-mcp.jsx — painel MCP; ref expirada, ver nota)
blade_source: "N/A — tela já é Inertia (re-skin DS v6 conservador)"
inertia_target: resources/js/Pages/team-mcp/Team/Index.tsx
pr_branch: feat/forja-pr4-team
---

# TeamMcp — Comparativo visual · tela **Team** (painel MCP)

> **F1.5 do MWART V4** · PR-4 da onda **Forja**. Pré-aprovado pelo padrão Forja ([W] "pode seguir" 2026-06-16). **Tela Tier 0** (governança de tokens MCP — ADR 0081/0057/0093). Já tem [charter vivo](../../../../resources/js/Pages/team-mcp/Team/Index.charter.md).

## Escopo escolhido (e o que ficou pra PR-4b)

A tela já é **madura** (reveal-once, AlertDialog destrutivo, drill-down de tokens por dev, multi-tenant via backend). Por ser **Tier 0** (raw token, revoke, quota), o PR-4 faz um **re-skin DS v6 conservador que preserva 100% da lógica de credencial** — não reescreve fluxo de token.

**Deferido pra PR-4b** (é build de feature, não re-skin, e precisa de fonte nova + ref Cowork):
- "Contrato recurso×ação" (matriz de tools) — vive em `Admin/ToolsController`/`Admin/TeamScopesController` (telas irmãs; visual delas é escopo do PR-6).
- Auditoria `mcp_audit_log` read-only inline + Identity Mesh (`mcp_actors`) — novas leituras de backend.
- `forja-mcp.jsx` (ref do painel MCP) **expirou**; pra replicar o layout do painel fielmente, regenerar a URL Cowork.

## O que o PR-4 faz (re-skin DS v6, lógica intacta)

| Mudança | Antes | Depois |
|---|---|---|
| `tokenStatus()` cores | `bg-yellow-100`/`bg-green-100` cru | `bg-warning-soft text-warning-fg` / `bg-success/15 text-success-fg` |
| `quotaBadge()` cores | `bg-red/orange/yellow/green-100` cru | tokens destructive/warning/success |
| Botão "N ativos" | `bg-green-100 text-green-800` cru | `bg-success/15 text-success-fg` |
| Checkbox "bloquear" (QuotaForm) | `<input type="checkbox">` nativo | `<Checkbox>` DS (ds/no-native-checkbox) |
| Export CSV | `prompt()` nativo (viola anti-hook do charter) | `<Dialog>` com date-range (G-DESIGN-07) |
| Breadcrumb / título | "Copiloto / Team Admin" | "Equipe / Time" |

## Preservado 100% (Tier 0 — NÃO tocar)

- `gerarToken`/`doGerarToken` (reveal-once raw 1×), `gerarDxt`/`doGerarDxt` (blob download), revoke por token, quota POST, `listTokens` drill-down, `exportCsv`.
- AlertDialog destrutivo (todas as confirmações), modal de token raw (COPIE AGORA), multi-tenant scope (backend `listTokens`/`revokeToken` filtram business_id), Inertia::defer (team + stats_globais).
- Nenhum token raw logado/persistido/exposto além do reveal-once existente.

## 15 dimensões (resumo — re-skin conservador)

| # | Dimensão | Decisão |
|---|---|---|
| 1 Layout | PageHeader + KPIs (defer) + tabela team + dialogs — mantido |
| 2 Hierarquia | ação primária por linha (+DXT/+Token); export no header |
| 3 Densidade | tabela densa mantida |
| 4 Iconografia | lucide (BarChart3/Package/Trash2…) — mantido |
| 5 Estados | loading defer (skeleton), empty drill-down, disabled em token inativo — mantido |
| 6 Atalhos | — (tela admin de formulário; ⌘K global) |
| 7 Persistência | nenhuma local (read+ações) |
| 8 Shared | PageHeader/KpiGrid/KpiCard/Dialog/AlertDialog/Checkbox |
| 9 Tipografia num | `font-mono` em custo/IP — mantido |
| 10 Espaçamento | mantido |
| 11 **Cores** | **paleta crua → tokens semânticos** (única mudança visual real) |
| 12 Microinterações | hover row, dialogs — mantido |
| 13 Ref aprovada | Forja Cowork (forja-mcp.jsx — expirada; re-skin conservador não depende dela) |
| 14 Benchmark | Stripe API keys / Vercel tokens (reveal-once, drill, revoke) |
| 15 Persona | Wagner superadmin — onboarding/offboarding seguro de credencial |

## Decisões [W] (pré-aprovado)

1. **Re-skin conservador** (preserva lógica Tier 0); contrato/audit/Identity Mesh → **PR-4b**.
2. Trocar `prompt()` por Dialog (G-DESIGN-07, fecha violação do charter).
3. Native checkbox → `Checkbox` DS.
4. Breadcrumb "Equipe / Time".

## Gates antes do F3
- [x] Padrão Forja aprovado ([W] "pode seguir").
- [x] Charter já existe (Team/Index.charter.md).
- [ ] CI: typecheck + eslint/lint-baseline + conformance + foundation. **Smoke obrigatório** dos fluxos de token (gerar/revoke/quota/CSV/DXT) pós-merge — Tier 0.

---
**Status:** `approved` — implementado no PR `feat/forja-pr4-team` (re-skin conservador; PR-4b pendente pro painel completo).
