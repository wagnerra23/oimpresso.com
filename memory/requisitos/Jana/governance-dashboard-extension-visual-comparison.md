---
id: requisitos-jana-governance-dashboard-extension-visual-comparison
slug: jana-governance-dashboard-extension-visual-comparison
title: "Governance — Extensão Cockpit Saúde do Ecossistema (governance/Dashboard.tsx)"
type: visual-comparison
module: Governance
status: approved
date: 2026-05-09
approved_by: wagner
approved_at: 2026-05-09
canon_reference: resources/js/Pages/governance/Dashboard.tsx (charter live, Cockpit Pattern V2 ADR 0110)
blade_source: n/a (greenfield extension de Page já live)
inertia_target: resources/js/Pages/governance/Dashboard.tsx
charter_target: resources/js/Pages/governance/Dashboard.charter.md (bump v1 → v2)
---

# Visual comparison — Extensão `/governance` Dashboard com Cockpit Saúde

## Contexto

Pivot da decisão original do epic Cockpit Saúde (US-COPI-095): ao invés de criar `/copiloto/admin/health` separada, **estendemos `/governance` Dashboard** que já é o cockpit de saúde gold-standard do projeto (charter `live`, Cockpit Pattern V2 canon, em prod desde 2026-05-08).

Princípio que motivou pivot: Constituição V2 §5 SoC brutal + ADR 0105 (cliente como sinal — sem cliente pedindo separação, default é unificar).

Esta extensão adiciona 3 fontes de saúde do ecossistema **que não estão no painel atual**: queue Horizon, custo IA Brain B 24h, e narrativas hourly do Brain A narrador (US-COPI-099 / PR #339).

## Adições propostas (não-rebuild — extensão incremental)

### 1. KpiGrid ganha 2ª fileira "Saúde do ecossistema"

```
─────────────────────────────────────────────────────────────────────
H2 "Constituição"   (sobressai a fileira existente, sem outras mudanças)
─────────────────────────────────────────────────────────────────────
[ADRs pend.] [Policies] [Skill apr.] [Actors] [Audit 24h] [Compliance]   ← KpiGrid cols=6 (mantém)

─────────────────────────────────────────────────────────────────────
H2 "Saúde do ecossistema"
─────────────────────────────────────────────────────────────────────
[Failed jobs 24h]  [Custo IA 24h]  [Última narrativa]                    ← KpiGrid cols=3 (novo)
```

### 2. Linha de 2 cards (ADRs + Audit) vira **3 cards**

```
[ADRs pendentes]  [Audit Highlights 24h]  [Narrativas Brain A 24h]      ← grid 3-col desktop, stack mobile
```

Card "Narrativas Brain A 24h" segue padrão visual idêntico ao Audit Highlights atual: bullet com badge severity (rose=critical, amber=warning, blue=info) + timestamp HH:MM + texto truncado a 80 chars. Dropea pro último 5.

### 3. NÃO mexe

- `<PageHeader>` "Governança" + ActionGate badge — mantém
- Quick actions card — mantém
- Documentos canônicos card — mantém

## 15 dimensões — só nas adições (canon V2 herda o resto)

### A. Estrutura

| # | Dimensão | Decisão |
|---|---|---|
| 1 | **Layout** | KpiGrid existente (cols=6) ganha h2 "Constituição" acima · novo h2 "Saúde do ecossistema" + KpiGrid cols=3 abaixo · grid-cols-1 lg:grid-cols-3 pra 3 cards (ADRs+Audit+Narrativas) |
| 2 | **Hierarquia visual** | h2 com `text-sm font-semibold uppercase tracking-widest text-zinc-500` (label de seção, igual canon Cockpit V2). Mantém PageHeader como h1 dominante |
| 3 | **Densidade** | KpiGrid `gap-3` herdado do componente shared. h2 com `mt-4 mb-2` |
| 4 | **Iconografia** | lucide via `<Icon>` shared (mesmo do KpiCard atual). 3 novos ícones: `activity` (failed jobs), `dollar-sign` (custo IA), `message-circle-warning` (última narrativa) |
| 5 | **Estados visuais** | Failed jobs: tone warning se >0, danger se >100 · Custo IA: tone info default, warning se >5 BRL · Narrativas: tone do KpiCard espelha severity (critical=danger, warning=warning, info=default) |
| 6 | **Atalhos teclado** | N/A — read-only (mesmo que page atual) |
| 7 | **Persistência** | N/A |
| 8 | **Componentes shared** | `<KpiGrid>`, `<KpiCard>`, `<Card>`, `<Badge>`, `<EmptyState>` — todos canon ADR 0110 já em uso |

### B. Estado da arte

| # | Dimensão | Decisão |
|---|---|---|
| 9 | **Tipografia numérica** | KpiCard.value default é `text-2xl font-semibold` — herdado. Custo IA exibe `R$ [redacted Tier 0]` (number_format BR), failed jobs como inteiro, narrativa exibe severity como label do card |
| 10 | **Espaçamento numérico** | Container `space-y-4` existente herda. Novo h2 com `mt-4 mb-2`. Cards mantêm `p-4` |
| 11 | **Cores semânticas warm** | tone variants do KpiCard (`bg-emerald-500/5`, `bg-amber-500/5`, `bg-destructive/5`, `bg-blue-500/5`) — canon ADR 0110, sem mudança |
| 12 | **Microinterações** | KpiCard com hover do tone (mantém shadow-sm + transition-colors). Sem novas animações |
| 13 | **Referência visual aprovada** | `/governance` Dashboard live em prod (charter status: live, last_validated 2026-05-08) — referência aprovada implícita por já estar em produção como gold-standard |
| 14 | **Benchmarks externos** | dashboard tipo: Vercel project overview · Linear cycle review. Nossa Page já segue pattern Vercel-like (KpiGrid + lista lateral). Adições mantêm o pattern |
| 15 | **Persona priorização** | Wagner superadmin 1280px+ — vê tudo de uma vez. KpiGrid cols=6 + cols=3 = 9 KPIs visíveis sem scroll horizontal em 1280. Mobile (Wagner em viagem): stack vertical |

## Outputs Claude Design plugin (anexar quando rodar)

> Pra esta extensão de Page já live, sub-skills `design:design-critique`, `design:design-system`, `design:design-handoff`, `design:ux-copy`, `design:accessibility-review` ficam pra **F3 pós-impl** (skill V4 §17-21) — execução em screenshot real.

## Decisões pendentes

Nenhuma. Wagner aprovou layout em chat 2026-05-09 ("sim" pós proposta de 2 fileiras separadas com headers).

## Refs

- [Charter atual](../../../resources/js/Pages/governance/Dashboard.charter.md) (status: live, charter_version 1 → 2 com este PR)
- [PR #339](https://github.com/wagnerra23/oimpresso.com/pull/339) — HealthNarratorService (US-COPI-099) cria tabela `jana_health_narratives` que esta extensão lê
- [PR #312](https://github.com/wagnerra23/oimpresso.com/pull/312) — Setup Horizon (US-COPI-096) — independente desta extensão (failed_jobs já existe sem Horizon publicado)
- [SPEC US-COPI-095/098](SPEC.md) — epic + sub-stories (esta extensão fecha boa parte do escopo da US-COPI-098 sem precisar tela nova)
- [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit Pattern V2 canon
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — gate F1.5 mwart-comparative

## Status

`approved` (Wagner aprovou em chat 2026-05-09). Liberado pra implementação F2-F3.
