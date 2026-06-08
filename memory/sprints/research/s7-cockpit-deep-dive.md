---
name: S7 Deep Dive — ADR poda + Cockpit /governance/oimpresso
description: Pesquisa estado-da-arte 2026 pra Sprint 7. ADR como append-only com 6-stage lifecycle, dashboard React 19 + shadcn/ui + react-grid-layout, real-time observability cards.
type: project
created: 2026-05-06
related_sprint: S7
sources_count: 2
---

# S7 — ADR poda + Cockpit (deep-dive)

> **Objetivo da pesquisa:** validar nossa estratégia de poda (92→≤30) + design de Cockpit
> contra estado-da-arte 2026.

---

## Achado #1 — ADR lifecycle 6 estados, NÃO archive direto

[Pogopaule — ADR Lifecycle](https://github.com/pogopaule/architecture_decision_record/blob/master/adr_lifecycle.md), [AWS Prescriptive Guidance — ADR Process](https://docs.aws.amazon.com/prescriptive-guidance/latest/architectural-decision-records/adr-process.html):

> "Architecture Decision Records follow five lifecycle stages: **Initiating → Researching → Evaluating → Implementing → Maintaining → Sunsetting**."

[Martin Fowler — ADR](https://martinfowler.com/bliki/ArchitectureDecisionRecord.html):

> "ADRs have statuses including 'proposed' while under discussion, 'accepted' once active, and 'superseded' once replaced."

### Implicação no plano S7

Plano original: 4 decisões — KEEP CANON / MERGE / ARCHIVE / DELETE.

🟡 **Refinar com lifecycle 6 estados:**

| Decisão atual | Estado lifecycle | Ação concreta |
|---|---|---|
| KEEP CANON | `accepted` (em uso) | manter em `memory/decisions/` raiz |
| MERGE INTO X | `superseded by X` | criar nova ADR, marcar antiga como superseded, manter ambas |
| ARCHIVE | `sunsetting` ou `superseded` | mover pra `memory/decisions/_archive/YYYY/` |
| DELETE | **NUNCA** | ADR é append-only, não deleta |

🔴 **Decisão "DELETE" do plano original VIOLA o princípio append-only.** Trocar por "ARCHIVE com motivo: 'irrelevante histórico'".

### Schema canônico de status (frontmatter)

```yaml
---
adr_id: 0023
status: accepted  # proposed | accepted | superseded | deprecated | sunsetting
superseded_by: ~  # ou número de ADR nova
sunsetting_reason: ~  # se status=sunsetting
last_reviewed: 2026-05-06
review_due: 2027-05-06  # 12 meses
---
```

🟢 **Triagem das 92 ADRs deve produzir uma DECISÃO de status pra cada uma**, não apenas KEEP/ARCHIVE. Isso preserva história.

---

## Achado #2 — ADR pode "drift" do propósito original

[InfoQ — Has Your ADR Lost Its Purpose?](https://www.infoq.com/articles/architectural-decision-record-purpose/):

> "Lacking a clear definition of what is architectural, ADRs can drift from their original purpose. **When bloated with every decision a team makes, architectural decisions can't be easily seen amidst everything else.**"

### Implicação no plano S7

Critério explícito pra triagem (a ser confirmado por Wagner):

| Pergunta | Se SIM → ADR | Se NÃO → re-classificar |
|---|---|---|
| Decisão custosa de reverter? | ADR | virou guideline/runbook |
| Afeta múltiplos módulos? | ADR | comentário no PR é suficiente |
| Declara invariante de longo prazo? | ADR | nota informal |
| Foi resultado de incidente real? | ADR | history irrelevante |

🟡 **Adicionar coluna "passa critério ADR?" na tabela de triagem.** ADRs que não passam viram ou:
- Runbook em `memory/operational/` (se procedural)
- Charter (se contrato de página/feature)
- Apenas histórico em `_archive/`

### Estimativa do trabalho de poda

Triagem 92 ADRs × ~5 min/ADR = ~8h Wagner direto + ~2h Sonnet rascunhando categorias.

🔴 **Wagner sozinho é gargalo.** Recomendação: Sonnet pré-classifica todas 92 com proposta + grep de refs ativas; Wagner aprova bloco a bloco (10 ADRs × 10 min = ~100 min, não 8h).

---

## Achado #3 — Cockpit React 2026: shadcn/ui + react-grid-layout

[Use DataBrain — React Dashboard 2026 Guide (React 19 + Vite + shadcn/ui)](https://www.usedatabrain.com/how-to/create-react-dashboard), [Untitled UI — 19 Best React Dashboards](https://www.untitledui.com/blog/react-dashboards):

> "Dashboard layouts can use **react-grid-layout** for drag-and-drop resizable widgets. Metric cards built using shadcn/ui components in a grid layout (e.g., `md:grid-cols-2 lg:grid-cols-4`)."

### Stack proposta para Cockpit

✅ **Já compatível com nosso stack atual:**

```
React 19          ← já usado
Inertia v3        ← já usado
Tailwind 4        ← já usado
shadcn/ui         ← já usado (auto-mem confirma)
react-grid-layout ← ADICIONAR (npm i react-grid-layout)
```

Layout proposto:

```tsx
// resources/js/Pages/Governance/Cockpit.tsx
import GridLayout from 'react-grid-layout';

const layout = [
  { i: 'brief',          x: 0, y: 0, w: 6, h: 8 },   // Brief (markdown)
  { i: 'design-health',  x: 6, y: 0, w: 6, h: 4 },   // Design tokens drift
  { i: 'migration-board',x: 0, y: 8, w: 12, h: 6 },  // Kanban LEGACY→DELETED
  { i: 'prs',            x: 0, y: 14, w: 6, h: 4 },  // PRs aguardando
  { i: 'charters',       x: 6, y: 14, w: 6, h: 4 },  // Charters health
  { i: 'locks',          x: 0, y: 18, w: 4, h: 4 },  // Locks ativos
  { i: 'visual-regress', x: 4, y: 18, w: 4, h: 4 },  // Visual regression feed
  { i: 'ads-metrics',    x: 8, y: 18, w: 4, h: 4 },  // ADS metrics
];
```

### Implicação no plano S7

🟢 **Stack sem surpresa.** Adicionar `react-grid-layout` é a única dependência nova.

---

## Achado #4 — Real-time dashboard precisa client-side windowing

[Makers' Den — ReactJS Real-Time Analytics](https://makersden.io/blog/reactjs-dev-for-real-time-analytics-dashboards):

> "Building React dashboards that stream live data smoothly requires **client-side windowing, backpressure control, and browser observability**."

### Aplicação ao Cockpit

Plano original: polling 30s × 8 painéis. Risco: se um painel ficar lento, trava UI.

🟡 **Refinamento:**

```typescript
// resources/js/hooks/useCockpitPanel.ts
function useCockpitPanel<T>(toolName: string, intervalMs: number = 30_000) {
  const [data, setData] = useState<T | null>(null);
  const [stale, setStale] = useState(false);

  useEffect(() => {
    let mounted = true;
    const fetch = async () => {
      try {
        // Inertia partial reload (só este painel)
        router.reload({
          only: [toolName],
          onSuccess: (page) => mounted && setData(page.props[toolName]),
          onError: () => mounted && setStale(true)
        });
      } catch (e) {
        if (mounted) setStale(true);
      }
    };

    fetch();
    const interval = setInterval(fetch, intervalMs);
    return () => { mounted = false; clearInterval(interval); };
  }, [toolName, intervalMs]);

  return { data, stale };
}
```

Cada painel é independente. Se `migration-state-list` falha, só esse painel fica stale (mostra `⚠️ atualizado há 2min`); resto do Cockpit continua atualizando.

### Implicação no plano S7

✅ **Adicionar `useCockpitPanel` hook** ao plano. Detalhe técnico mas crítico pra UX.

Real-time com Centrifugo (S5 INFRA-RT-1) seria upgrade futuro — push em vez de poll. Não precisa pro MVP S7.

---

## Achado #5 — Cockpit precisa "explainability per decision"

Cruzando com S5 deep-dive (TRiSM pillar Explainability):

> "Para cada decisão Brain B, mostrar 'por que' — input + output + reasoning."

### Aplicação ao painel "ADS metrics" do Cockpit

Em vez de só métricas agregadas, adicionar drill-down:

```
[Painel ADS Metrics]
┌──────────────────────────────────────────┐
│ % auto-aprovado: 47%  ✅                  │
│ Custo Brain B mês: $87  📊               │
│ Escalações: 12  📋                        │
│                                           │
│ [Últimas 5 decisões] (clicável)           │
│ ┌─────────────────────────────────────┐   │
│ │ MWART-002 · DESIGN tier 2 · BRAIN_B │   │
│ │ Verdict: approved · 1.2k tokens     │   │
│ │ [Ver reasoning]                     │   │
│ └─────────────────────────────────────┘   │
│ ...                                       │
└──────────────────────────────────────────┘
```

Click em "Ver reasoning" abre Sheet com:
- Input completo (charter + diff + screenshot)
- Output Brain B (verdict + reasoning text)
- Custo da decisão
- Audit log entry

### Implicação no plano S7

🟡 **Adicionar painel #9 "Decision drill-down"** OU integrar no painel #8 (ADS metrics) com clickable rows.

---

## Recomendações pro plano S7 (revisões)

### O que manter

- 8 painéis canônicos
- ADR poda 92 → ≤30
- Tools MCP novas (`migration-state-list`, `design-locks-active`)
- Stack React 19 + Inertia v3 + Tailwind 4 + shadcn/ui

### O que mudar

| Item | Plano original | Revisão pós deep-dive |
|---|---|---|
| Decisão na triagem | KEEP/MERGE/ARCHIVE/DELETE | **proposed/accepted/superseded/deprecated/sunsetting** (5 estados lifecycle) |
| DELETE | permitido | **❌ ABOLIDO — ADR append-only** |
| Polling | 30s monolítico | **partial reload por painel + stale indicator** |
| Drill-down | só agregados | **clickable row em ADS metrics → Sheet com reasoning** |
| Stack dashboard | React+Tailwind genérico | **+ react-grid-layout (drag-and-drop)** |
| Triagem 92 ADRs | Wagner aprova bloco a bloco | **Sonnet pré-classifica + grep refs; Wagner aprova ~10×10min em vez de 8h** |

### O que adicionar

- [ ] Hook `useCockpitPanel` pra independência de cada painel
- [ ] Frontmatter de ADR ganha `status, superseded_by, sunsetting_reason, last_reviewed, review_due`
- [ ] Sheet de drill-down em ADS metrics
- [ ] Auto-bump panel "Charters apodrecendo" via campo `last_verified` (charters >90d sem revisão)
- [ ] Auto-bump panel "Runbooks desatualizados" via `last_tested` (do S6)

### Estimativa revisada

Plano original: 6–8 dias.
Pós deep-dive: **7–10 dias** (drill-down + hook independente + react-grid-layout add).

---

## Sources

- [Pogopaule — ADR Lifecycle](https://github.com/pogopaule/architecture_decision_record/blob/master/adr_lifecycle.md)
- [AWS Prescriptive Guidance — ADR Process](https://docs.aws.amazon.com/prescriptive-guidance/latest/architectural-decision-records/adr-process.html)
- [Martin Fowler — ADR](https://martinfowler.com/bliki/ArchitectureDecisionRecord.html)
- [InfoQ — Has Your ADR Lost Its Purpose?](https://www.infoq.com/articles/architectural-decision-record-purpose/)
- [Use DataBrain — React Dashboard 2026 Guide](https://www.usedatabrain.com/how-to/create-react-dashboard)
- [Untitled UI — 19 Best React Dashboards](https://www.untitledui.com/blog/react-dashboards)
- [Makers' Den — ReactJS Real-Time Analytics](https://makersden.io/blog/reactjs-dev-for-real-time-analytics-dashboards)
- [Joel Parker Henderson — ADR Examples Repo](https://github.com/joelparkerhenderson/architecture-decision-record)
