# RUNBOOK — Migração tela LIST (`{{MODULE}}/{{TELA}}`)

> Template do tipo **LIST** (listagem com tabela). Use quando o Blade legacy é `index.blade.php` com DataTable Yajra.
> Substituir `{{PLACEHOLDERS}}` antes de salvar em `memory/requisitos/{{MODULE}}/RUNBOOK-{{TELA_KEBAB}}.md`.
> Skill: [migracao-blade-react](../../.claude/skills/migracao-blade-react/SKILL.md) · ADR: [0141](../decisions/0141-skill-migracao-blade-react.md)

---

## 1. Identificação

- **Módulo:** {{MODULE}}
- **Tela:** {{TELA}} (kebab: {{TELA_KEBAB}})
- **Tipo:** LIST
- **Blade legacy:** `{{BLADE_PATH}}`
- **Controller atual:** `{{CONTROLLER_PATH}}@{{ACTION}}`
- **Rota legada:** `{{ROUTE_METHOD}} {{ROUTE_URI}}` (name: `{{ROUTE_NAME}}`)
- **Mockup Cowork:** `prototipo-ui/prototipos/{{MODULE_KEBAB}}/visual-source.html`
- **Pages destino:** `resources/js/Pages/{{MODULE}}/{{TELA}}/Index.tsx`

## 2. Snapshot paridade (do `mwart-inventory/{{MODULE_KEBAB}}/{{TELA_KEBAB}}.snapshot.md`)

### 2.1 Colunas DataTable (Yajra)

| # | Coluna | Origem (model.field) | Renderização | Sortable | Searchable |
|---|--------|----------------------|--------------|----------|------------|
| 1 | {{COL_1}} | {{COL_1_FIELD}} | {{COL_1_RENDER}} | ☐ | ☐ |
| 2 | ... | ... | ... | ☐ | ☐ |

### 2.2 Filtros (selects + date ranges + buscas)

- {{FILTRO_1}}: `<select>` opções `{{FILTRO_1_OPCOES}}`, default `{{FILTRO_1_DEFAULT}}`
- {{FILTRO_2}}: `<input type="date">` range
- {{FILTRO_3}}: busca livre (debounced 400ms)

### 2.3 Botões de ação

| Ação | Quando | Permissão | Confirmação | Endpoint |
|------|--------|-----------|-------------|----------|
| {{ACAO_1}} | inline na linha | `{{PERM_1}}` | ☐ | `{{ENDPOINT_1}}` |
| {{ACAO_2}} | bulk (≥1 selecionado) | `{{PERM_2}}` | ☐ | `{{ENDPOINT_2}}` |

### 2.4 Permissões

- `{{PERM_VIEW}}` — ver lista
- `{{PERM_CREATE}}` — botão "+ Novo"
- `{{PERM_EDIT}}` — ação editar inline
- `{{PERM_DELETE}}` — ação excluir / bulk delete

### 2.5 Eventos disparados

- `{{EVENT_1}}` (em qual ação)
- `{{EVENT_2}}` (idem)

### 2.6 Hooks DataController multi-tenant

- Scope `business_id` aplicado em: {{SCOPE_FIELDS}}
- Eager load: {{EAGER_LOAD}}

## 3. Shared components obrigatórios (Tier 0 visual)

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import PageFilters from '@/Components/shared/PageFilters';
import DataTable from '@/Components/shared/DataTable';
import BulkActionBar from '@/Components/shared/BulkActionBar';
import EmptyState from '@/Components/shared/EmptyState';
import StatusBadge from '@/Components/shared/StatusBadge';
```

| Componente | Obrigatório? | Por quê |
|------------|:---:|---------|
| `AppShellV2` | ✅ | Layout canônico — sidebar + topnav + breadcrumb |
| `PageHeader` | ✅ | Título + descrição + action (botão "+ Novo") |
| `PageFilters` | ✅ se tem filtros | Padroniza dropdowns/date range |
| `DataTable` | ✅ | Substitui Yajra DataTable — paginação + sort + search |
| `BulkActionBar` | ✅ se tem bulk | Aparece quando ≥1 selecionado |
| `EmptyState` | ✅ | Quando lista vazia (com CTA) |
| `StatusBadge` | ✅ se tem status | Substitui `<span class="label-*">` Blade |

## 4. Esqueleto TSX (preencher após STEP 2 da skill)

```tsx
import { type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
// + shared components conforme seção 3

interface {{TELA}}Row {
  id: number;
  {{COL_1_FIELD}}: string;
  // ... espelhando 2.1
}

interface Props {
  rows: {{TELA}}Row[];
  filters: { {{FILTROS_TS}} };
  permissions: { create: boolean; edit: boolean; delete: boolean };
}

function {{MODULE}}{{TELA}}Index({ rows, filters, permissions }: Props) {
  return (
    <>
      <PageHeader
        title="{{TITLE}}"
        description="{{DESCRIPTION}}"
        action={permissions.create && (
          <Button onClick={() => router.visit('{{ROUTE_CREATE}}')}>+ Novo</Button>
        )}
      />
      {/* filtros sticky */}
      {/* DataTable ou EmptyState */}
    </>
  );
}

{{MODULE}}{{TELA}}Index.layout = (page: ReactNode) => (
  <AppShellV2
    title="{{TITLE}}"
    breadcrumbItems={[
      { label: '{{MODULE_LABEL}}', href: '{{MODULE_HOME}}' },
      { label: '{{TELA_LABEL}}' },
    ]}
  >{page}</AppShellV2>
);

export default {{MODULE}}{{TELA}}Index;
```

## 5. Adaptação Controller (delta mínimo — Tier 0 obrigatório)

```php
// ANTES (Blade legacy):
public function index(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $items = {{MODEL}}::where('business_id', $business_id)
        ->{{SCOPES}}
        ->get();
    return view('{{MODULE_KEBAB}}::{{TELA_KEBAB}}.index', compact('items'));
}

// DEPOIS (Inertia — apenas troca view→render, MANTENDO scope):
public function index(Request $request)
{
    $business_id = $request->session()->get('user.business_id');  // ← MANTÉM
    $items = {{MODEL}}::where('business_id', $business_id)        // ← MANTÉM
        ->{{SCOPES}}
        ->get();
    return Inertia::render('{{MODULE}}/{{TELA}}/Index', [
        'rows' => $items,
        'filters' => $request->only(['{{FILTRO_KEYS}}']),
        'permissions' => [
            'create' => auth()->user()->can('{{PERM_CREATE}}'),
            'edit'   => auth()->user()->can('{{PERM_EDIT}}'),
            'delete' => auth()->user()->can('{{PERM_DELETE}}'),
        ],
    ]);
}
```

**⛔ PROIBIDO neste Step:**
- Remover `business_id` scope
- Remover `auth()->user()->can()` checks
- Reescrever query (manter scopes existentes)
- Inventar Service novo

## 6. Pest tests (STEP 5)

```php
test('{{MODULE_KEBAB}} {{TELA_KEBAB}} lists rows for biz=1', function () {
    // happy path biz=1 — ADR 0101
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} hides rows from other tenant (biz=99)', function () {
    // cross-tenant guard — Tier 0
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} respects permission {{PERM_CREATE}}', function () {
    // user sem permissão NÃO vê botão "+ Novo"
});

// + 1 teste por filtro, 1 por ação bulk
```

## 7. Checklist paridade (STEP 6 — não fecha PR sem)

- [ ] Todas as colunas do Blade aparecem na TSX
- [ ] Todos os filtros do Blade funcionam na TSX
- [ ] Todas as ações inline preservadas
- [ ] Todas as ações bulk preservadas
- [ ] `business_id` scope intacto (grep `withoutGlobalScopes` retorna nada novo)
- [ ] Permissões intactas
- [ ] Events::dispatch intactos
- [ ] Screenshot Blade vs TSX aprovado por Wagner (STEP 4)
- [ ] Pest biz=1 + biz=99 passando
- [ ] Smoke local `http://oimpresso.test/{{ROUTE_URI}}` validado
- [ ] PR ≤300 LOC, conventional commit, `Refs: ADR-0141`

## 8. Cutover (F5 mwart-process)

1. Aviso prévio cliente ROTA LIVRE (biz=4) ≥48h antes
2. Flag `useV2{{TELA}}=true` em `pos_settings`
3. Monitor 7d (canary) — logs Laravel + Centrifugo
4. Após 30d sem regressão: remover `{{BLADE_PATH}}` e rota legada

---

**Refs:**
- ADR mãe: [0141](../decisions/0141-skill-migracao-blade-react.md)
- Processo MWART: [0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Tier 0: [0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
- Visual gate: [0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
