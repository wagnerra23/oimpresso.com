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

---

## Medição 2026-11-24 (L6) — substitui a de 2026-09-18 [CC]

Mede as **4 telas** com fonte no `governance-page.jsx` (Dashboard, Policies, Audit, DriftAlerts), cada uma contra a sua vista. Esta seção traz o método e o canário, que valem para as quatro; o veredito de cada tela está no GAP-SPEC dela:

| Tela | Dono do veredito | Veredito |
|---|---|---|
| `governance/Dashboard` | [`governance-dashboard-gap.md`](../Governance/governance-dashboard-gap.md) | DIVERGE — 2 reais, 1 decidida |
| `governance/Policies` | [`governance-policies-gap.md`](../Governance/governance-policies-gap.md) | IGUAL nas dimensões medidas |
| `governance/Audit` | [`governance-audit-gap.md`](../Governance/governance-audit-gap.md) | DIVERGE — 5 reais, 1 não conclusivo |
| `governance/DriftAlerts` | [`governance-drift-alerts-gap.md`](../Governance/governance-drift-alerts-gap.md) | IGUAL nas dimensões medidas |

Custos, QualidadeIa e DsRollout: **não medidos** — não têm âncora de protótipo (`ancora.mjs` não resolve fonte para elas).

### Método

- **Pré-requisitos.** L3 (PageHeader nas telas: #11217, #11228) e L5 (protótipo: #11194, #11226, #11244) mergeados antes da medição.
- **Fonte.** `prototipo-ui/cowork/Wagner/governance-page.jsx` sha256 `3b1b79c13065…` e `governance-telas.jsx` `c82e0d6bf8a3…`, último commit `34b12a7869` (2026-11-19). Âncora: `node scripts/design/ancora.mjs governance/<Tela>` resolve as 4 pelo `bundle_source`.
- **Vista certa.** O `app.jsx` abre cada vista por um token próprio (`governance`, `gov-politicas`, `gov-auditoria`, `gov-drift`). Sem token por tela o lote caía na rota do grupo e media a vista painel para as quatro — foi o erro da medida de 2026-09-18 (4 `design.json` com o mesmo sha). O token e os papéis por lado ficaram em `governance/design/targets/roles/governance--*.json`; com eles, `design-diff-lote.mjs --dry` passa a aceitar as 4 telas (antes: "rota do GRUPO"). Identidade da vista: D0 pelo `governance.contract.json` no Dashboard (OK); nas outras três, pelo `h1` e pelo `data-screen-label` gravados no `design.json`. Os 4 `design.json` têm sha distintos.
- **Mesma sonda.** `node scripts/design/design-diff.mjs --probe`, sha256 `358fd0f0…`. No protótipo, lida do arquivo por http local; em produção, carregada por upload de arquivo e conferida por sha256 antes de rodar.
- **Mesmo tema e viewport.** Claro nos dois (protótipo com `__setTweak('theme','light')`; produção sem classe `dark`). 1280×900 nos dois, em iframe.
- **Produção.** `oimpresso.com`, `business_id=1` (lido dos props da página), sem diálogo aberto nem sobreposição fixa (`__meta`).
- **Texto de produção redigido.** O `assinatura` do `prod.json` guarda só texto de UI (título, rótulos, cabeçalhos, e no Dashboard as cópias do contrato presentes); a tabela de auditoria tem dado de produção e ficou fora. O sha do texto original vai em `__meta.assinaturaRedigida`. Cada `prod.json` foi conferido por sha256 contra o snapshot da página antes de gravar.

### Canário

Na vista auditoria do protótipo, forcei o `h1` a 30px/700, removi o último KPI e tirei a fonte mono da coluna Ferramenta, e comparei contra o `design.json` sem alteração. Esperado e obtido: `kpi.count 2 × 3`, `título 30px × 22px`, `título 700 × 600` e `col3.mono` — as quatro, e nada além. Controle (o mesmo `design.json` contra ele mesmo): 0 DIVERGE. A sonda discrimina.

### Divergências reais (não consertadas aqui)

1. Dashboard — data da próxima revisão sai em ISO (`2026-08-05`) no card de KPI; o protótipo usa dd/mm/aaaa.
2. Dashboard — seção MCP sem 9 das 22 cópias do contrato (a confirmar: parte pode ser o `Select` de período).
3. Audit — produção não marca a linha com erro (0 de 200; o protótipo marca 16 de 200).
4. Audit — colunas User e Endpoint sem cor própria.
5. Audit — colunas Tool/Resource e Duração sem fonte mono.
