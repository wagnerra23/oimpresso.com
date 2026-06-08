# Spec — Pages/Repair/Index.tsx

> Sprint 2 · MWART · React/Inertia v3 + Tailwind 4

## Localização

`resources/js/Pages/Repair/Index.tsx`

## Layout (Persistent Layout — preference_persistent_layouts)

Página NÃO envolve em `<AppShellV2>` no JSX. Usa o pattern static `Component.layout`:

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import type { ReactNode } from 'react';

function RepairIndex({ ... }: Props) {
  return (
    <>
      <PageHeader ... />
      <PageFilters ... />
      <BulkBar ... />
      <DataTable ... />
      <Pagination ... />
    </>
  );
}

RepairIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default RepairIndex;
```

## Estrutura visual

```
AppShellV2 (sidebar + topnav já vêm prontos)
├── PageHeader
│   ├── título "Ordens de Serviço"
│   ├── breadcrumb / back
│   ├── KPI strip: contadores por status_type (em-progresso / concluído / atrasado)
│   └── botão "Nova OS" (se permissions.create)
├── PageFilters (chips + drawer pra filtros avançados)
├── BulkBar (sticky, visível quando selected.size > 0)
├── DataTable (denso, sticky header, row click → /repair/repair/{id})
└── Pagination (preserva query string)
```

## Props (espelham RepairListResource + meta + permissions)

```ts
type RepairRow = {
  id: number;
  invoice_no: string;
  transaction_date: string | null;     // ISO
  repair_due_date: string | null;
  repair_due_human: string | null;     // "em 3 dias"
  is_overdue: boolean;
  serial_no: string | null;
  defects: string | null;
  final_total: number;
  final_total_formatted: string;       // "R$ 250,00"
  payment_status: 'paid' | 'partial' | 'due' | string;
  contact: { id: number; name: string } | null;
  service_staff: { id: number; name: string } | null;
  location: { id: number; name: string };
  status: { id: number; name: string; color: string | null };
  warranty_name: string | null;
  device_model_name: string | null;
};

type SharedProps = {
  auth: {
    user: { id: number; first_name: string; last_name: string; email: string };
    business_id: number;
    permissions: string[];
  };
  business: { id: number; name: string; currency: string };
  flash: { success?: string; error?: string };
};

type Props = {
  repairs: {
    data: RepairRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta: { current_page: number; from: number; to: number; total: number; per_page: number };
  };
  filters: FiltersState;
  meta: {
    totals_by_status: Record<string, number>;
    repair_statuses: Array<{ id: number; name: string; color: string | null; status_type?: string }>;
    locations: Array<{ id: number; name: string }>;
    service_staff: Array<{ id: number; name: string }>;
    business_currency: string;
    business_timezone: string;
  };
  permissions: {
    create: boolean;
    update: boolean;
    delete: boolean;
    status_update: boolean;
    view_all: boolean;
  };
};

type FiltersState = {
  q: string | null;
  repair_status_id: number[];
  contact_id: number | null;
  location_id: number | null;
  service_staff_id: number | null;
  start_date: string | null;
  end_date: string | null;
  due_start: string | null;
  due_end: string | null;
  sort: string;
  dir: 'asc' | 'desc';
  per_page: 25 | 50 | 100;
};
```

## Componentes shared (já no projeto, reusar)

- `<PageHeader>` — título + ações primárias
- `<PageFilters>` — wrapper com chips + drawer
- `<DataTable>` — tabela densa com sort, sticky header, seleção
- `<KpiCard>` — contadores
- `<StatusBadge>` — chip colorido (já consumindo `repair_statuses.color`)
- `<EmptyState>` — quando 0 resultados
- `<ContactCombobox>` — combobox com search remoto via Inertia partial reload
- `<DateRangePicker>` — date picker pt-BR (formato dd/mm/yyyy)

Se algum desses ainda não existir em `resources/js/Components/shared/`, cria neste PR (re-uso futuro garantido).

## Estado local + filtros via URL

Filtros vivem **na URL** (Inertia query string). Nada em localStorage — bookmarkar uma view = compartilhar URL. Exceção: `per_page` pode persistir em `localStorage['repair.index.per_page']`.

```tsx
import { router } from '@inertiajs/react';

const [selected, setSelected] = useState<Set<number>>(new Set());

function applyFilter(patch: Partial<FiltersState>) {
  router.get(
    route('repair.index'),
    { ...filters, ...patch, page: 1 },           // reset paginação ao filtrar
    {
      preserveState: true,
      preserveScroll: true,
      only: ['repairs', 'filters', 'meta'],      // partial reload
      replace: true,                              // não polui history
    }
  );
}

function clearFilters() {
  router.get(route('repair.index'), {}, {
    preserveState: false,
    preserveScroll: false,
  });
}
```

## DataTable — colunas

| col | label | sortable | width | render |
|---|---|---|---|---|
| checkbox | — | não | 32 | seleção bulk (se permissions.status_update) |
| invoice_no | OS | sim | 130 | `<Link>` → `/repair/repair/{id}`, mono |
| status | Status | sim | 140 | `<StatusBadge color={row.status.color}>` |
| contact_name | Cliente | sim | 200 | nome + tooltip se truncar |
| device_model | Aparelho | não | 160 | `device_model_name` ou `—` |
| serial_no | Série | não | 120 | mono, copy-on-click |
| service_staff | Resp. | não | 120 | iniciais + tooltip |
| location | Local | não | 130 | só se business tem >1 location |
| transaction_date | Aberta em | sim | 110 | data dd/mm |
| repair_due_date | Prazo | sim | 130 | data + chip "atrasada" se `is_overdue` |
| final_total | Total | sim | 120 | `final_total_formatted`, alinhado direita |
| payment_status | Pgto | não | 80 | mini-badge: pago/parcial/dever |
| acoes | — | não | 40 | menu kebab |

Colunas `location` e `device_model` são collapsible em viewport < 1280px (Wagner: monitor cliente ROTA LIVRE = 1280px, memória `cliente_rotalivre`).

## KPI strip no header

Cards clicáveis que aplicam filtro de status_type:

```
[ Em andamento N ]  [ Atrasadas N ]  [ Aguardando peça N ]  [ Concluídas N ]
```

`status_type` vem de `repair_statuses.status_type` (UltimatePOS já tem essa coluna). Card ativo tem ring; click → `applyFilter({ repair_status_id: [...ids do tipo] })`.

## Filtros (drawer "Filtros avançados")

- **Busca:** input com debounce 300ms; placeholder "Nº OS, cliente ou nº de série"; atalho `/`
- **Status:** chips multi-select (lista `meta.repair_statuses`)
- **Cliente:** `<ContactCombobox>` com search remoto (Headless UI Combobox + debounced router.get)
- **Local:** select (escondido se business só tem 1 location)
- **Responsável:** select com `meta.service_staff`
- **Período (abertura):** date range
- **Período (prazo):** date range
- **Toggle "Apenas minhas":** preset que aplica `service_staff_id = auth.user.id`
- Botão "Limpar filtros" quando 1+ filtro ativo
- Botão "Salvar visão" → desabilitado, comentário `// TODO MWART-S3`

## Bulk bar (sticky topo quando selected.size > 0)

```
[ N selecionadas ] [ Mudar status ▾ ] [ Mudar responsável ▾ ] [ × Limpar ]
```

Cada ação abre modal de confirmação. Submit:

```tsx
router.post(
  route('repair.update-repair-status-bulk'),  // endpoint a confirmar com Blade existente
  { ids: [...selected], status_id: nextStatus },
  {
    onSuccess: () => setSelected(new Set()),
    preserveScroll: true,
  }
);
```

Se endpoint bulk não existir hoje no Blade legacy, **NÃO criar nesta sprint** — escopo Sprint 2.5. Mostrar bulk bar com botões disabled + tooltip "disponível em breve".

## Atalhos de teclado

Reusar `useHotkeys` (já no projeto, vem do AppShellV2):

- `/` — foca busca
- `n` — Nova OS (se permissions.create)
- `j`/`k` — navega linhas (memória de Cockpit, DESIGN.md)
- `enter` — abre OS focada
- `x` — toggle seleção da linha focada
- `esc` — limpa seleção / fecha drawer
- `?` — abre cheatsheet de atalhos

## Estados especiais

- **Loading:** skeleton da tabela (8 rows shimmer) durante partial reload Inertia
- **Empty (sem filtros):** `<EmptyState>` com CTA "Criar primeira OS"
- **Empty (com filtros):** `<EmptyState>` com CTA "Limpar filtros"
- **Erro:** toast via `flash.error` shared prop

## i18n

Todas strings via `__('repair::lang.…')` no servidor (PageHeader title, KPI labels, status names já vêm do banco). No React, apenas micro-copy de UI (botões, placeholders) — usar a infra i18n do AppShellV2 (memória `feedback_topnav_i18n_pattern`).

## Acessibilidade

- Tabela usa `<table>` semântico, não divs
- `aria-sort` nas colunas sortáveis
- `aria-live="polite"` no contador de seleção
- Foco volta pra linha origem ao fechar modal
- Contraste AA em todos badges (validar `repair_statuses.color` no design system; se hex < AA, override pra paleta tokens)
- Texto pt-BR (Wagner: nunca inglês — CLAUDE.md §4)

## Persistência de UI

| Item | Onde | Justificativa |
|---|---|---|
| Filtros | URL (querystring) | bookmarkable, compartilhável |
| `per_page` | localStorage | preferência pessoal |
| Densidade tabela | localStorage | preferência pessoal |
| Ordem colunas | **NÃO persistir Sprint 2** | escopo Sprint 3+ |
| Coluna oculta | **NÃO persistir Sprint 2** | escopo Sprint 3+ |

## Testes — `tests/js/Pages/Repair/Index.test.tsx`

Vitest + Testing Library:

- [ ] renderiza N rows do mock
- [ ] click em KPI card aplica filtro
- [ ] bulk bar aparece ao selecionar
- [ ] atalho `/` foca busca
- [ ] atalho `j`/`k` move linha focada
- [ ] sem permission `repair.create` → botão "Nova OS" não aparece

E2E Cypress (golden path):

1. login como user com `repair.view`
2. abre `/repair/repair`
3. filtra status=em-andamento
4. seleciona 3 rows
5. bulk → mudar status → concluído
6. confirma toast e atualização da lista

## Tamanho do arquivo

Alvo: < 400 linhas. Se exceder, extrair em mesmo PR:

- `RepairIndexTable.tsx`
- `RepairIndexFilters.tsx`
- `RepairIndexBulkBar.tsx`
- `useRepairShortcuts.ts`

`Index.tsx` fica como composição (Persistent Layout no rodapé).

## Não inventar UX nova nesta sprint

MWART = port 1:1. Cor de status, ícones, ordem dos campos, terminologia — tudo igual ao Blade. Mudanças de produto vêm em sprint dedicada com aprovação Wagner.

Comentários `// TODO MWART-S3:` permitidos pra ideias surgidas durante o port — vão pra backlog, não pro PR.
