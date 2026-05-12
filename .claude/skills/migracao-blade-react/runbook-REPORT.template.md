# RUNBOOK — Migração tela REPORT (`{{MODULE}}/{{TELA}}`)

> Template do tipo **REPORT** (relatório com filtros + agregação + export). Use quando o Blade legacy é `report/*.blade.php`.
> Substituir `{{PLACEHOLDERS}}` antes de salvar em `memory/requisitos/{{MODULE}}/RUNBOOK-{{TELA_KEBAB}}.md`.
> Skill: [migracao-blade-react](../../.claude/skills/migracao-blade-react/SKILL.md) · ADR: [0141](../decisions/0141-skill-migracao-blade-react.md)

---

## 1. Identificação

- **Módulo:** {{MODULE}}
- **Relatório:** {{TELA}} (kebab: {{TELA_KEBAB}})
- **Tipo:** REPORT
- **Blade legacy:** `{{BLADE_PATH}}`
- **Controller:** `{{CONTROLLER_PATH}}@{{ACTION}}`
- **Rota legada:** `GET {{ROUTE_URI}}` (name: `{{ROUTE_NAME}}`)
- **Export PDF Blade:** `{{BLADE_PATH_PDF}}` (se aplicável)
- **Mockup Cowork:** `prototipo-ui/prototipos/{{MODULE_KEBAB}}/visual-source.html`
- **Pages destino:** `resources/js/Pages/{{MODULE}}/Relatorios/{{TELA}}.tsx`

## 2. Snapshot paridade

### 2.1 Filtros do relatório

| Filtro | Tipo | Required | Default | Observação |
|--------|------|----------|---------|------------|
| Data inicial | date | ✅ | primeiro dia mês | — |
| Data final | date | ✅ | hoje | — |
| {{FILTRO_2}} | select | 🟡 | todas | scope multi |
| {{FILTRO_3}} | select | 🟡 | todos | scope multi |
| Agrupamento | select | ✅ | dia | dia/semana/mês/categoria |

### 2.2 Colunas do relatório

| Coluna | Origem | Formatação | Sortable |
|--------|--------|------------|----------|
| {{COL_1}} | {{COL_1_FIELD}} | data PT-BR | ☑ |
| {{COL_2}} | {{COL_2_FIELD}} | currency BRL | ☑ |
| {{COL_3}} | sum/count/avg | — | ☐ |

### 2.3 Agregações (totais no rodapé)

- **Total:** sum({{FIELD}})
- **Média:** avg({{FIELD}})
- **Contagem:** count(*)
- **{{KPI_CUSTOM}}:** {{FORMULA_CUSTOM}}

### 2.4 Exports

| Formato | Endpoint | Permissão |
|---------|----------|-----------|
| CSV | `GET {{ROUTE_URI}}/export-csv` | `{{PERM_EXPORT}}` |
| PDF | `GET {{ROUTE_URI}}/export-pdf` | `{{PERM_EXPORT}}` |
| Excel | `GET {{ROUTE_URI}}/export-xlsx` | `{{PERM_EXPORT}}` |

### 2.5 Permissões

- `{{PERM_VIEW}}` — ver relatório
- `{{PERM_EXPORT}}` — exportar
- `{{PERM_DRILLDOWN}}` — clicar em linha pra detalhe (opcional)

### 2.6 Hooks DataController multi-tenant

- TODAS as queries scoped por `business_id`
- Export pode usar Job assíncrono — `$businessId` explícito no constructor

## 3. Shared components obrigatórios

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
```

| Componente | Obrigatório? | Por quê |
|------------|:---:|---------|
| `AppShellV2` | ✅ | Layout canônico |
| `PageHeader` | ✅ | Título + período + botões export |
| `Card` (filtros) | ✅ | Agrupa date range + filtros |
| `DataTable` | ✅ | Resultados com sort + paginação |
| Date range picker | ✅ | shadcn `<Calendar>` ou nativo |
| Footer com totais | ✅ | `<tfoot>` ou Card abaixo |

## 4. Esqueleto TSX

```tsx
import { type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

interface ReportRow {
  {{ROW_FIELDS_TS}}
}

interface Aggregates {
  total: number;
  media: number;
  contagem: number;
}

interface Props {
  rows: ReportRow[];
  aggregates: Aggregates;
  filters: { data_ini: string; data_fim: string; {{FILTROS_TS}}; agrupamento: string };
  selectOptions: { {{SELECT_OPTIONS_TS}} };
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

function {{MODULE}}{{TELA}}Report({ rows, aggregates, filters, selectOptions }: Props) {
  const aplicar = (patch: Partial<typeof filters>) => {
    router.get('{{ROUTE_URI}}', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
    });
  };

  return (
    <>
      <PageHeader
        title="{{TITLE}}"
        description={`${filters.data_ini} a ${filters.data_fim}`}
        action={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => window.location.href = '{{ROUTE_URI}}/export-csv?' + new URLSearchParams(filters as any)}>
              CSV
            </Button>
            <Button variant="outline" onClick={() => window.location.href = '{{ROUTE_URI}}/export-pdf?' + new URLSearchParams(filters as any)}>
              PDF
            </Button>
          </div>
        }
      />

      <Card className="mt-4">
        <CardContent className="p-3 flex flex-wrap items-end gap-3">
          {/* date range + filtros + agrupamento */}
        </CardContent>
      </Card>

      <Card className="mt-3">
        <CardContent className="p-0">
          {/* DataTable */}
        </CardContent>
      </Card>

      {/* Totais */}
      <Card className="mt-3">
        <CardContent className="p-4 grid grid-cols-3 gap-4 text-center">
          <div>
            <div className="text-sm text-stone-500">Total</div>
            <div className="text-xl font-semibold tabular-nums">{brl(aggregates.total)}</div>
          </div>
          <div>
            <div className="text-sm text-stone-500">Média</div>
            <div className="text-xl font-semibold tabular-nums">{brl(aggregates.media)}</div>
          </div>
          <div>
            <div className="text-sm text-stone-500">Contagem</div>
            <div className="text-xl font-semibold tabular-nums">{aggregates.contagem}</div>
          </div>
        </CardContent>
      </Card>
    </>
  );
}

{{MODULE}}{{TELA}}Report.layout = (page: ReactNode) => (
  <AppShellV2
    title="{{TITLE}}"
    breadcrumbItems={[
      { label: '{{MODULE_LABEL}}', href: '{{MODULE_HOME}}' },
      { label: 'Relatórios', href: '{{ROUTE_RELATORIOS}}' },
      { label: '{{TELA_LABEL}}' },
    ]}
  >{page}</AppShellV2>
);

export default {{MODULE}}{{TELA}}Report;
```

## 5. Adaptação Controller

```php
public function {{ACTION}}(Request $request) {
    $business_id = $request->session()->get('user.business_id');  // ← Tier 0
    $data_ini = $request->input('data_ini', now()->startOfMonth()->toDateString());
    $data_fim = $request->input('data_fim', now()->toDateString());

    $rows = {{MODEL}}::where('business_id', $business_id)
        ->whereBetween('{{DATE_FIELD}}', [$data_ini, $data_fim])
        ->{{SCOPES_FILTROS}}
        ->orderBy('{{DATE_FIELD}}')
        ->get();

    $aggregates = [
        'total'     => $rows->sum('{{FIELD}}'),
        'media'     => $rows->avg('{{FIELD}}') ?? 0,
        'contagem'  => $rows->count(),
    ];

    return Inertia::render('{{MODULE}}/Relatorios/{{TELA}}', [
        'rows' => $rows,
        'aggregates' => $aggregates,
        'filters' => compact('data_ini', 'data_fim') + $request->only(['{{FILTRO_KEYS}}']),
        'selectOptions' => [/* ... */],
    ]);
}

// exportCsv mantém lógica atual (apenas troca formato output)
public function exportCsv(Request $request) {
    // mesma query, retorna StreamedResponse CSV
}
```

## 6. Pest tests

```php
test('{{MODULE_KEBAB}} {{TELA_KEBAB}} report renders for biz=1', function () {
    // happy path com período
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} report scopes to biz=1 (not 99)', function () {
    // cross-tenant — rows de biz=99 NÃO aparecem
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} report aggregates sum/avg/count correctly', function () {
    // valores agregados batem
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} report exports CSV with same data', function () {
    // export = render → mesma data
});
```

## 7. Checklist paridade

- [ ] Filtros do Blade preservados (date range + scopes)
- [ ] Colunas idênticas
- [ ] Agregações idênticas (total/média/contagem/etc)
- [ ] Export CSV funcionando
- [ ] Export PDF funcionando (se aplicável)
- [ ] `business_id` scope em query principal + agregados + exports
- [ ] Datas em PT-BR
- [ ] Currency em BRL
- [ ] Sort por coluna funcional
- [ ] Screenshot aprovado por Wagner
- [ ] Pest biz=1 + biz=99 + agregados + export passando
- [ ] PR ≤300 LOC, `Refs: ADR-0141`

---

**Refs:** [0141](../decisions/0141-skill-migracao-blade-react.md) · [0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
