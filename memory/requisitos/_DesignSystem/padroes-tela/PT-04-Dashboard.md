---
pattern_id: PT-04
nome: Dashboard
camada: 3-padroes-tela
status: draft
versao: 0.1
created: 2026-05-30
parent_adr: UI-0013
applied_in:
  - Pages/governance/Dashboard.tsx
---

# PT-04 · Dashboard — padrão canônico de tela-painel

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md#camada-1--fundacoes) + [Shell](../README.md#camada-2--shell) e nunca contradiz. Módulo só configura os slots (KPIs, painéis, períodos), **não** muda a estrutura.
>
> ⚠️ **status: draft.** Golden eleito por código real (`governance/Dashboard`). **A barra "≥2 dashboards convergirem" já foi ATINGIDA** (verificado 2026-07-11): `Admin/GovernanceV4`, `governance/DsRollout`, `kb/Graph` e `team-mcp/Scorecard` convergem em `KpiGrid`+`KpiCard`+`PageHeader`. Único gate restante = **aprovação de screenshot do Wagner** (F1.5 · [ADR 0107](../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).

## Quando aplicar

Tela cujo **propósito primário é leitura agregada** (KPIs gigantes + painéis de resumo/gráfico/lista-top-N + filtro de período), sem ser a lista paginável de uma entidade. Ex: visão geral de OS, painel de governança, metas, saúde do ecossistema.

Não aplicar pra: lista paginável de entidades → [PT-01 Lista](PT-01-Lista.md); form/drawer de cadastro → PT-02; detalhe full-page → PT-03.

## Golden eleito · `governance/Dashboard.tsx`

[`resources/js/Pages/governance/Dashboard.tsx`](../../../../resources/js/Pages/governance/Dashboard.tsx) · controller [`Modules/Governance/Http/Controllers/DashboardController.php`](../../../../Modules/Governance/Http/Controllers/DashboardController.php)

**Por que esta:**
- **Único candidato que usa os shared `@/Components/shared`** canônicos de dashboard: `KpiGrid` (`:11`) + `KpiCard` (`:12`) + `PageHeader` (`:10`) + `EmptyState` (`:13`). Os outros hand-rolam.
- **Hierarquia de leitura clara em 2 tiers de KPI** com sub-cabeçalho seccionador (`:129` "Constituição" cols=6 · `:180` "Saúde do ecossistema" cols=3) — exatamente o que um painel precisa: o olho desce por grupos.
- **Tom semântico em vez de cor crua** via `tone` do KpiCard (`failedJobsTone` `:73`, `custoIaTone` `:80`, `severityTone` `:87`) — verde/âmbar/vermelho derivados do dado, não literais.
- **Painéis de resumo em `<Card>`** com drill-down (`:216-324`: ADRs pendentes / Audit highlights / Narrativas) + `EmptyState` em cada um (`:231`, `:265`, `:306`).
- `tabular-nums` nos valores via KpiCard (`KpiCard.tsx:127`), PT-BR em todo label, `pt-BR` locale em datas/moeda (`:199`, `:243`).

**Por que descartei os outros:**
- **`Financeiro/Advisor/Dashboard.tsx`** (anti-golden parcial) — não usa nenhum shared: `os-btn primary/ghost` cru (`:51,93,100`), `<div className="rounded-md border bg-white">` hand-rolado (`:67,76`), `bg-slate-50`/`bg-white` literais (`:38,39`) em vez de tokens, sem `KpiCard`/`PageHeader`/`AppShellV2`. É um portal isolado, não painel canônico. **Financeiro/Unificado** já marcado piloto como "ilha CSS" (bundle paralelo 8.663 LOC) — anti-golden absoluto, nem entra.
- **`Repair/Dashboard/Index.tsx`** — usa `KpiCard` + `PageHeader` certo, mas é **raso demais pra golden**: só 2 KPIs, painéis são `SimpleListCard` hand-rolada (`:105`), tem `FIXME US-REPAIR-DASH-1` aberto (`:34`), sem tiers nem tom semântico. Bom aluno, não professor.
- **`Jana/Dashboard.tsx`** — rico, mas **contaminado por mocks e CSS escopado paralelo**: `JanaKpiStrip` é placeholder que "não consulta DB" (`:190-191`), wrapper `.sells-cowork` puxa tokens `.vd-insights-*` de bundle escopado (`:276`), gradientes `from-violet-600 via-fuchsia-500` (`:294`) e `bg-gradient-to-br` (`:239`). Viola "sem bundle CSS paralelo" e "sem gradiente bluish-purple". Desqualifica.

## Anatomia · 5 slots fixos

```
┌─────────────────────────────────────────────────────────────┐
│ 1 · PageHeader     ícone · título · descrição · período/badge │ ← sticky
├─────────────────────────────────────────────────────────────┤
│ 2 · KPI Tier(s)    <KpiGrid cols=N> de <KpiCard> gigantes     │
│                    sub-h2 uppercase seccionando cada tier     │
├─────────────────────────────────────────────────────────────┤
│ 3 · Painéis        grid de <Card> resumo/top-N/gráfico        │
│                    cada um com título + drill-down + Empty     │
├─────────────────────────────────────────────────────────────┤
│ 4 · Quick actions  (opcional) grid de atalhos navegação       │
├─────────────────────────────────────────────────────────────┤
│ 5 · Estados        loading skeleton · vazio · erro            │ ← transversal
└─────────────────────────────────────────────────────────────┘
```

## 8 regras binárias (sim/não) — ancoradas em linha real

| # | Regra (pergunta sim/não) | Evidência na golden |
|---|---|---|
| **R1** | **KPIs gigantes vêm de `<KpiGrid cols=N>` + `<KpiCard>` shared (NÃO `<div>` hand-rolado)?** value `text-2xl/4xl font-semibold tabular-nums`, label `text-[11px] uppercase tracking-widest`. | `Dashboard.tsx:133,184` (KpiGrid) · `:134` (KpiCard) · `KpiCard.tsx:106-111,117,127` |
| **R2** | **Tom do KPI é semântico via `tone` derivado do dado (NÃO cor crua `bg-red-N`)?** `default/success/warning/danger/info`. | `:73` `failedJobsTone` · `:80` `custoIaTone` · `:166` `tone={... > 0 ? 'warning' : 'success'}` · `KpiCard.tsx:33-39` |
| **R3** | **Há hierarquia de leitura: tiers de KPI seccionados por sub-`<h2>` uppercase (NÃO um amontoado plano)?** | `:129-131` "Constituição" cols=6 · `:180-182` "Saúde do ecossistema" cols=3 |
| **R4** | **Painéis de resumo/gráfico/top-N usam `<Card>` shadcn (NÃO `<div className="rounded-md border bg-white">`)?** | `:218,252,293` `<Card><CardContent>` — contraste: Advisor `:67,76` hand-rola |
| **R5** | **Todo painel tem estado vazio explícito via `<EmptyState>` (NÃO some/quebra quando dado=0)?** | `:231` · `:265` · `:306` (3 painéis, 3 EmptyState) |
| **R6** | **Header é `<PageHeader>` shared (sticky, ícone+título+descrição), com filtro de período/contexto no slot de ação?** | `:119-127` (PageHeader + `<Badge>` ActionGate no slot) · `PageHeader.tsx:46` |
| **R7** | **Números/valores/moeda usam `tabular-nums` + locale `pt-BR` (NÃO toFixed cru sem locale)?** | `KpiCard.tsx:127` (tabular-nums) · `:199` `toLocaleString('pt-BR')` · `:243` `toLocaleDateString('pt-BR')` |
| **R8** | **Cabe em 1280px e o container é `max-w-7xl` com `space-y-4` entre blocos (sem scroll horizontal)?** | `:118` `mx-auto max-w-7xl p-6 space-y-4` · `KpiGrid.tsx` grids responsivos `lg:grid-cols-N` |

**Placar:** 8/8 = canon. 6-7 = 1 round de ajuste. <6 = volta pro Claude Design.

> A golden marca **8/8 nas regras estruturais**, mas ver §Drift abaixo — ela tem débitos que você corrige ao copiar.

## §Nunca

- ❌ Hand-rolar KPI com `<div className="rounded-md border bg-white">` — usa `<KpiGrid>` + `<KpiCard>` (anti-padrão vivo no Advisor `:76`)
- ❌ Cor crua em valor/tom (`bg-red-500`, `bg-slate-50`, `text-blue-700` literal) — tom vem de `tone` semântico ou token; azul de marca migra pra `primary` roxo ([INDEX §0 R2](../INDEX-DESIGN-MEMORIAS.md))
- ❌ **Bundle CSS paralelo** escopado (`.sells-cowork`, `.vd-insights-*`, `cowork-<mod>-bundle.css`) pra estilizar dashboard — é o pecado do Financeiro/Unificado (ilha CSS 8.663 LOC) e contamina o Jana (`:276`)
- ❌ Gradiente bluish-purple decorativo (`bg-gradient-to-br from-violet via-fuchsia`) em card de KPI/painel (Jana `:239,294`) — proibição visual [PRE-MERGE-UI](../PRE-MERGE-UI.md)
- ❌ KPI mock que "não consulta DB" apresentado como real (Jana `JanaKpiStrip` `:190`) — M-AP-2/M-AP-5 ([INDEX §3a](../INDEX-DESIGN-MEMORIAS.md))
- ❌ Painel sem estado vazio — todo `<Card>` de lista/top-N cobre `length === 0` com `<EmptyState>`
- ❌ `rounded-xl+` em superfície de painel · emoji em UI produtiva (a golden usa 📋📊🤖 nos títulos — **débito**, ver §Drift) · label não-PT-BR
- ❌ Modal full-screen pra drill-down — drill-down é `<Link>` pra rota (golden `:225,259,300`) ou drawer/Sheet

## Estados obrigatórios

1. **Cheio** — KPIs com valor, painéis com N linhas
2. **Vazio/sem dado** — cada painel com `<EmptyState>` contextual (golden `:231,265,306`); KPI mostra `—` quando null (golden `:189,196,207`)
3. **Loading skeleton** — `<Deferred data="..." fallback={<KpiSkeleton/>}>` enquanto `Inertia::defer` resolve (ver §Drift — golden hoje é eager)
4. **Erro de fetch** — toast + retry

## Drift conhecido (corrija ao copiar — não herde)

- ⚠️ **`Inertia::defer` foi REVERTIDO** no controller (`DashboardController.php:47-50`, "Wave W7 #953 — Pages não tinham `<Deferred>` wrapper, kpis undefined crashava"). Hoje é **eager**. Ao copiar pra dashboard novo: wrappe os payloads pesados (`buildKpisPayload`, `buildAuditHighlightsPayload`, queries 24h) em `Inertia::defer(fn () => ...)` **e** o frontend em `<Deferred fallback={skeleton}>` juntos — skill [`inertia-defer-default`](../../../../.claude/skills/inertia-defer-default/SKILL.md). Eager aqui é débito, não modelo.
- ⚠️ **Emoji nos títulos de painel** (`:223` 📋, `:256` 📊, `:297` 🤖, `:335-353` ⚙️📊🚨📋 nos atalhos) viola "sem emoji em UI produtiva" ([PRE-MERGE-UI](../PRE-MERGE-UI.md)). Troca por ícone lucide via `<Icon name>` ao replicar.
- ⚠️ **`KpiCard` raiz usa `rounded-xl`** (`KpiCard.tsx:30`) — mesmo drift da GOLDEN-REFERENCE §4. Canon é `rounded-lg`; está no shared, então herda — não piora, mas não cite como exemplo de raio.
- ⚠️ **`bg-blue-50 text-blue-700`** em `severityBadgeClass` info (`:98`) e `tone="info"` (`KpiCard.tsx:38,62`) — azul semântico **sobrevive hoje** ([INDEX §4 nuance R2](../INDEX-DESIGN-MEMORIAS.md)); azul de **marca** (link/foco) migra pra `primary` roxo.

## Aplicado em (estado real)

| Página | R1 KPI shared | R2 tom | R3 tiers | R4 Card | R5 Empty | R6 Header | defer | Nota |
|---|---|---|---|---|---|---|---|---|
| `governance/Dashboard.tsx` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | eager (drift) | **golden** |
| `Repair/Dashboard/Index.tsx` | ✓ | — | — | parcial | parcial | ✓ | — | bom aluno |
| `Jana/Dashboard.tsx` | parcial (mock) | parcial | — | ✓ | ✓ | ✓ | — | mock+bundle |
| `Financeiro/Advisor/Dashboard.tsx` | ❌ | ❌ | — | ❌ (hand-roll) | parcial | ❌ | — | anti-golden |

**Métrica adoção PT-04 (2026-05-30):** 1/4 dashboards atinge canon estrutural. Próximo passo: portar `Repair/Dashboard` pros 2 tiers de KpiGrid + tom semântico (já tem base shared), e desbundlar o Jana.

## Referências

- **ADR-mãe**: [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Tipografia KPI canon**: [ADR 0110 Cockpit Pattern V2](../../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) (`KpiCard.tsx:104-117`)
- **Golden form irmão**: [GOLDEN-REFERENCE.md](../../../../prototipo-ui/GOLDEN-REFERENCE.md) (`Sells/Create`)
- **Índice de design**: [INDEX-DESIGN-MEMORIAS.md](../INDEX-DESIGN-MEMORIAS.md) (regra de ouro + negativo)
- **Defer**: [RUNBOOK-inertia-defer-pattern.md](../RUNBOOK-inertia-defer-pattern.md)
- **Anti-golden catalogado**: Financeiro/Unificado (ilha CSS bundle paralelo) · [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)

## Versão

**v0.1** · 2026-05-30 · primeira formalização (draft). Golden eleito por código real (`governance/Dashboard`).
**v0.2** · 2026-07-11 · re-âncora no `origin/main`. Barra "≥2 dashboards" atingida (4 convergem); charter do golden já `live`; `pt-conformance` verde (4 declarações PT-04).
**Bump v1.0 (→ live)** quando Wagner aprovar o screenshot do golden `governance/Dashboard` (F1.5). Convergência já satisfeita.
