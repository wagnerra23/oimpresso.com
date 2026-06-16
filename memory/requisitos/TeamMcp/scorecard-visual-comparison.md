---
slug: teammcp-scorecard-visual-comparison
title: "TeamMcp — Comparativo visual da tela Scorecard (Saúde)"
type: visual-comparison
module: TeamMcp
status: approved
approved_by: wagner
approved_at: 2026-06-16
date: 2026-06-16
canon_reference: forja-cowork (forja-page.jsx — view .fj-saude/.fj-metric/.fj-gate-health)
blade_source: "N/A — Page nova (rota existia sem componente)"
inertia_target: resources/js/Pages/team-mcp/Scorecard/Index.tsx
pr_branch: feat/forja-pr3-scorecard
---

# TeamMcp — Comparativo visual · tela **Scorecard** (Saúde)

> **F1.5 do MWART V4** · PR-3 da onda **Forja**. Pré-aprovado pelo padrão Forja ([W] "pode seguir" 2026-06-16).

## Premissa corrigida vs prompt

O prompt tratava PR-3 como **re-skin**. A realidade: `ScorecardController@index` renderiza `team-mcp/Scorecard/Index` mas **a Page nunca existiu** no repo → a rota `/team-mcp/scorecard` está **quebrada** hoje. Logo PR-3 = **criar** a Page (não re-skin). Backend (`ScorecardBuilderService` Facts+Checks) já existe e fica intacto.

## Contexto

Padrão **Facts + Checks** (ADR 0091): Facts = números sem juízo (tokens_ativos, calls_7d, cost_7d_brl, users_ativos_7d, top_tools_7d, flags de presença de tabela); Checks = `{name, ok, detail}` (schema mcp_tokens/audit_log, brief recente, tokens sem orphan, custo médio sanity). `meta` eager (generated_at/period_days/pattern/source). Tudo via `Inertia::defer`.

## Sobre o **sparkline** (decisão §3)

A Forja `.fj-saude` mostra sparklines por métrica. **O builder não expõe série temporal** — só pontos atuais. Renderizar sparkline exigiria fabricar série (fantasma) OU adicionar uma nova métrica (`buildTrends`), o que contraria o escopo do prompt "**sem inventar métrica — só Facts+Checks atuais**". → **Sparkline deferido.** Se quiser, abro PR-3b com um `buildTrends()` real (buckets diários de mcp_audit_log) — aí o sparkline vira dado honesto.

## 15 dimensões (Forja · Decisão DS v6)

| # | Dimensão | Forja `.fj-saude` | Decisão DS v6 |
|---|---|---|---|
| 1 | Layout | grid de métricas + gate-health | PageHeader › Semáforo geral › Facts (KpiGrid) › Top tools › Checks (lista) |
| 2 | Hierarquia | métricas + gates | banner semáforo no topo (resposta "tá verde?") → Facts → Checks |
| 3 | Densidade | cards metric | KpiCard padrão + lista de checks py-2.5 |
| 4 | Iconografia | dot + spark | lucide `CheckCircle2`/`AlertCircle`/`RefreshCw`/`heart-pulse` |
| 5 | Estados | metric ok/warn/bad | loading skeleton (defer) · empty top-tools · aviso tabela ausente |
| 6 | Atalhos | — | `R` recarrega (+ botão Atualizar) |
| 7 | Persistência | — | nenhuma (read-only, sem estado de UI persistente) |
| 8 | Shared | custom | **PageHeader + KpiGrid + KpiCard** reusados; checks list module-local |
| 9 | Tipografia num | spark + valor | KPI value (KpiCard) `tabular-nums`; contagens mono |
| 10 | Espaçamento | grid gap | KpiGrid cols=4; seções com `mt-6` |
| 11 | Cores | hue ok/warn/bad | **success/warning/destructive** tokens (dot+texto, soft bg); zero cru |
| 12 | Microinterações | — | hover linhas; banner colorido por estado |
| 13 | Ref aprovada | Forja Cowork | ✅ |
| 14 | Benchmark | Vercel/Datadog health, Linear insights | painel de saúde Facts+Checks |
| 15 | Persona | Wagner superadmin | (1) "tá tudo verde?" em 2s, (2) números reais sob demanda, (3) sem ruído |

## Decisões [W] (pré-aprovado)

1. **PR-3 = criar** a Page (não re-skin) — rota quebrada.
2. **Sem sparkline** (sem série real; evita fantasma / nova métrica). PR-3b opcional com trends reais.
3. Frontend-only — `ScorecardController`/`ScorecardBuilderService` intactos (preserva contrato Pest Wave 23).
4. Breadcrumb "Equipe / Saúde".

## Gates antes do F3
- [x] Padrão Forja aprovado ([W] "pode seguir").
- [x] Charter `Index.charter.md` ao lado.
- [ ] CI: typecheck + eslint/lint-baseline + conformance + foundation + a rota renderiza (smoke).

---
**Status:** `approved` — implementado no PR `feat/forja-pr3-scorecard`.
