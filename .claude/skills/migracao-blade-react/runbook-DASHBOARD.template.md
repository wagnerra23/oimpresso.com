# RUNBOOK — Migração tela DASHBOARD (`{{MODULE}}/{{TELA}}`)

> Template do tipo **DASHBOARD** (KPIs + drill-down). Use quando o Blade legacy é `dashboard.blade.php` ou cockpit.
> Substituir `{{PLACEHOLDERS}}` antes de salvar em `memory/requisitos/{{MODULE}}/RUNBOOK-{{TELA_KEBAB}}.md`.
> Skill: [migracao-blade-react](../../.claude/skills/migracao-blade-react/SKILL.md) · ADR: [0141](../decisions/0141-skill-migracao-blade-react.md)

---

## 1. Identificação

- **Módulo:** {{MODULE}}
- **Tela:** {{TELA}}
- **Tipo:** DASHBOARD
- **Blade legacy:** `{{BLADE_PATH}}`
- **Controller:** `{{CONTROLLER_PATH}}@index`
- **Rota legada:** `GET {{ROUTE_URI}}` (name: `{{ROUTE_NAME}}`)
- **Mockup Cowork:** `prototipo-ui/prototipos/{{MODULE_KEBAB}}/visual-source.html`
- **Pages destino:** `resources/js/Pages/{{MODULE}}/{{TELA}}/Dashboard.tsx`

## 2. Snapshot paridade

### 2.1 KPIs (cards principais)

| KPI | Fonte | Cálculo | Tone | Drill-down (tab destino) |
|-----|-------|---------|------|---------------------------|
| {{KPI_1}} | sum({{TABELA}}.{{FIELD}}) | período filtro | success | tab `received` |
| {{KPI_2}} | count({{TABELA}}) | período | default | tab `open` |
| {{KPI_3}} | sum({{TABELA}}.atrasado) | hoje | destructive | tab `late` |

### 2.2 Filtros (sticky topo)

- **Período:** select (hoje, 7d, mês atual, customizado)
- **Conta:** select multi
- **Categoria:** select multi
- **Busca:** input livre

### 2.3 Gráficos / Widgets adicionais (opcional)

| Widget | Tipo | Fonte | Drill-down |
|--------|------|-------|------------|
| {{WIDGET_1}} | bar chart | aggregate by month | clique → tabela mês |
| {{WIDGET_2}} | line chart | tempo série | hover → tooltip |

### 2.4 Tabela detalhe (abaixo dos KPIs)

Mesma estrutura de tela LIST — ver `runbook-LIST.template.md` §2.

### 2.5 Permissões

- `{{PERM_VIEW}}` — ver dashboard
- `{{PERM_EXPORT}}` — botão exportar CSV/PDF

### 2.6 Hooks DataController multi-tenant

- Todos os agregados scoped por `business_id`
- Job assíncrono (se houver) recebe `$businessId` explícito (`session()` não funciona em fila)

## 3. Shared components obrigatórios

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import DataTable from '@/Components/shared/DataTable';
import { Card, CardContent } from '@/Components/ui/card';
```

| Componente | Obrigatório? | Por quê |
|------------|:---:|---------|
| `AppShellV2` | ✅ | Layout canônico |
| `PageHeader` | ✅ | Título + período + ação |
| `KpiGrid` + `KpiCard` | ✅ | KPIs com drill-down via `onClick` |
| `DataTable` | ✅ se tem tabela detalhe | Reutiliza LIST |
| Sticky filters Card | ✅ | `top-14 z-10` para não perder ao rolar |

## 4. Esqueleto TSX

```tsx
import { type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Card, CardContent } from '@/Components/ui/card';

interface Kpis {
  {{KPI_1}}: { valor: number; qtd?: number };
  {{KPI_2}}: { valor: number; qtd?: number };
  {{KPI_3}}: { valor: number; qtd?: number };
}

interface Props {
  kpis: Kpis;
  filters: { periodo: string; conta: string; categoria: string };
  rows: {{TELA}}Row[];
  periodLabel: string;
  businessName: string;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

function {{MODULE}}{{TELA}}Dashboard({ kpis, filters, rows, periodLabel, businessName }: Props) {
  const aplicar = (patch: Partial<typeof filters>) => {
    router.get('{{ROUTE_URI}}', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
    });
  };

  return (
    <>
      <PageHeader
        title="{{TITLE}}"
        description={businessName ? `${periodLabel} · ${businessName}` : periodLabel}
        action={<Button onClick={() => router.visit('{{ROUTE_NOVO}}')}>+ Novo</Button>}
      />

      <KpiGrid cols={3} className="mt-4">
        <KpiCard
          icon="wallet"
          tone="success"
          label="{{KPI_1_LABEL}}"
          value={brl(kpis.{{KPI_1}}.valor)}
          description={`${kpis.{{KPI_1}}.qtd ?? 0} itens`}
          onClick={() => aplicar({ /* drill-down */ })}
        />
        {/* ... KpiCard pra cada KPI */}
      </KpiGrid>

      {/* filtros sticky */}
      <Card className="mt-6 sticky top-14 z-10">
        <CardContent className="p-3 flex flex-wrap items-center gap-2">
          {/* selects periodo/conta/categoria */}
        </CardContent>
      </Card>

      {/* tabela detalhe — reusa pattern LIST */}
    </>
  );
}

{{MODULE}}{{TELA}}Dashboard.layout = (page: ReactNode) => (
  <AppShellV2
    title="{{TITLE}}"
    breadcrumbItems={[{ label: '{{MODULE_LABEL}}', href: '{{MODULE_HOME}}' }, { label: 'Dashboard' }]}
  >{page}</AppShellV2>
);

export default {{MODULE}}{{TELA}}Dashboard;
```

## 5. Adaptação Controller

```php
public function index(Request $request) {
    $business_id = $request->session()->get('user.business_id');  // ← Tier 0

    $kpis = [
        '{{KPI_1}}' => [
            'valor' => {{MODEL}}::where('business_id', $business_id)->{{FILTROS_PERIODO}}->sum('{{FIELD}}'),
            'qtd'   => {{MODEL}}::where('business_id', $business_id)->{{FILTROS_PERIODO}}->count(),
        ],
        // ... espelhar 2.1
    ];

    $rows = {{MODEL}}::where('business_id', $business_id)
        ->{{SCOPES_FILTROS}}
        ->get();

    return Inertia::render('{{MODULE}}/{{TELA}}/Dashboard', [
        'kpis' => $kpis,
        'rows' => $rows,
        'filters' => $request->only(['periodo', 'conta', 'categoria', 'busca']),
        'periodLabel' => Carbon::parse(...)->isoFormat('MMMM YYYY'),  // PT-BR
        'businessName' => session('business.name'),
    ]);
}
```

## 6. Pest tests

```php
test('{{MODULE_KEBAB}} {{TELA_KEBAB}} dashboard renders KPIs for biz=1', function () {
    // happy path — KPIs com valores reais
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} dashboard does NOT include data from other tenant', function () {
    // cross-tenant biz=99 — KPIs zeram
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} dashboard respects period filter', function () {
    // filtros aplicam
});
```

## 7. Checklist paridade

- [ ] Todos os KPIs do Blade aparecem
- [ ] Drill-down funciona (click no KPI filtra tabela)
- [ ] Filtros sticky preservados
- [ ] Período em PT-BR (`Carbon::isoFormat`)
- [ ] `businessName` do session (não hardcoded — fix #355/#358)
- [ ] `business_id` scope em TODOS os agregados (KPIs + tabela)
- [ ] Empty state quando KPIs zerados
- [ ] Screenshot aprovado por Wagner
- [ ] Pest biz=1 + biz=99 + filtros passando
- [ ] PR ≤300 LOC, `Refs: ADR-0141`

---

**Refs:** [0141](../decisions/0141-skill-migracao-blade-react.md) · [0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [0093](../decisions/0093-multi-tenant-isolation-tier-0.md) · [0110](../decisions/0110-cockpit-pattern-v2-design.md)
