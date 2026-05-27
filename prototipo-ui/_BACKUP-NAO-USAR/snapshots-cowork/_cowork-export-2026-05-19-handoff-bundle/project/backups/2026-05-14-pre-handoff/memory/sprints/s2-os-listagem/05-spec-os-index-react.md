# Spec — Pages/Os/Index.tsx

> Sprint 2 · MWART · React/Inertia

## Localização

`resources/js/Pages/Os/Index.tsx`

## Layout

```
AppShell
├── PageHeader (titulo "Ordens de Serviço", botão "Nova OS", contadores por status)
├── PageFilters (busca, multi-status chips, cliente, responsável, período, prioridade)
├── BulkBar (visível quando 1+ selecionada: "X selecionadas | Mudar etapa | Mudar responsável | Arquivar")
├── DataTable (denso, sticky header, row hover, click → /os/{id})
└── Pagination (preserva query string)
```

## Props

```ts
type OsRow = {
  id: number;
  numero: string;                    // "OS-2026-04321"
  descricao: string;
  status: 'briefing'|'arte'|'aprovacao'|'producao'|'acabamento'|'expedicao'|'entregue'|'arquivada';
  status_label: string;
  status_color: string;              // tailwind token: "amber-500", "blue-500", etc
  prioridade: 'baixa'|'media'|'alta'|'urgente';
  cliente: { id: number; nome: string };
  responsavel: { id: number; name: string };
  prazo_entrega: string | null;      // ISO
  prazo_humano: string | null;       // "em 3 dias"
  atrasada: boolean;
  valor: number;
  valor_formatado: string;
  created_at: string;
};

type Props = {
  os: {
    data: OsRow[];
    links: PaginationLinks;
    meta: PaginationMeta;
  };
  filtros: FiltrosState;
  meta: {
    totais_por_status: Record<OsStatus, number>;
    clientes_options: Array<{ id: number; nome: string }>;
    responsaveis_options: Array<{ id: number; name: string }>;
  };
  permissions: {
    create: boolean;
    bulk_update: boolean;
    archive: boolean;
  };
};
```

## Componentes shared usados (já existem no projeto)

- `<PageHeader>` — título + ações primárias
- `<PageFilters>` — wrapper com chips + reset
- `<DataTable>` — tabela densa com sort, sticky header, row selection
- `<KpiCard>` — contadores por status no header
- `<StatusBadge>` — chip colorido com label
- `<EmptyState>` — quando 0 resultados

## Estado local

```tsx
const [selected, setSelected] = useState<number[]>([]);
const [bulkAction, setBulkAction] = useState<BulkAction | null>(null);

// Filtros: usar Inertia.reload() preservando state, sem useState local
function applyFilter(patch: Partial<FiltrosState>) {
  router.get(route('officeimpresso.os.index'), { ...filtros, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    only: ['os', 'filtros', 'meta'],
  });
}
```

## DataTable — colunas

| col | label | sortable | width | render |
|---|---|---|---|---|
| checkbox | — | não | 32 | seleção bulk |
| numero | OS | sim | 110 | `<Link>` para `/os/{id}`, mono |
| status | Etapa | sim | 130 | `<StatusBadge>` |
| descricao | Descrição | não | flex | truncate, title=full |
| cliente_nome | Cliente | sim | 180 | nome + tooltip |
| responsavel | Resp. | não | 100 | avatar + iniciais |
| prazo_entrega | Prazo | sim | 120 | data + chip "atrasada" se vencida |
| valor | Valor | sim | 110 | R$ formatado, alinhado direita |
| acoes | — | não | 48 | menu kebab |

## Header — KPIs por status

Linha de cards clicáveis que aplicam filtro de status:

```
[ Briefing 12 ] [ Arte 8 ] [ Aprovação 5 ] [ Produção 23 ] [ Acabamento 6 ] [ Expedição 3 ] [ Entregue 142 ]
```

Click em card → `applyFilter({ status: [card.status] })`. Card ativo tem ring/border.

## Filtros

- **Busca:** input com debounce 300ms, ícone search, atalho `/`
- **Status:** chips multi-select inline (mesmos do KPI header, mas como toggle)
- **Cliente:** combobox com search remoto (Headless UI Combobox + debounced search Inertia)
- **Responsável:** combobox simples (lista vem nas props)
- **Período:** date range picker (de / até)
- **Prioridade:** chips multi-select
- **Toggles:** "Apenas minhas" | "Incluir arquivadas"
- **Botão "Limpar filtros"** quando 1+ filtro ativo
- **Botão "Salvar visão"** (Sprint 3+, deixar disabled)

## Bulk bar

Aparece sticky no topo quando `selected.length > 0`:

```
[ X selecionadas ] [ Mudar etapa ▾ ] [ Mudar responsável ▾ ] [ Arquivar ] [ × Limpar ]
```

Cada ação abre modal de confirmação. Submit:

```tsx
router.post(route('officeimpresso.os.bulk'), {
  ids: selected,
  action: 'mudar_status',
  value: 'producao',
}, {
  onSuccess: () => setSelected([]),
});
```

## Atalhos de teclado

- `/` — foca busca
- `n` — nova OS (se permission)
- `j`/`k` — navega linhas
- `enter` — abre OS selecionada
- `x` — toggle seleção da linha atual
- `esc` — limpa seleção / fecha modal

Implementar via `useHotkeys` (já no projeto) ou hook custom `useOsShortcuts()`.

## Estados especiais

- **Loading:** skeleton da tabela (8 rows) durante Inertia partial reload
- **Empty (sem filtros):** `<EmptyState>` com CTA "Criar primeira OS"
- **Empty (com filtros):** `<EmptyState>` com CTA "Limpar filtros"
- **Erro:** toast via `flash.error` shared prop

## Persistência

Filtros vivem **na URL** (Inertia query string). Nada em localStorage — bookmarkar uma view = compartilhar URL.

Exceção: `per_page` e ordem de colunas (preferência pessoal) → `localStorage['os.index.prefs']`.

## Acessibilidade

- Tabela usa `<table>` semântico (não divs)
- `aria-sort` nas colunas sortáveis
- `aria-live="polite"` no contador de seleção
- Foco volta pra linha origem ao fechar modal bulk
- Contraste AA em todos badges de status (validar `status_color` no design system)

## Testes

- `tests/js/Pages/Os/Index.test.tsx` (Vitest + Testing Library)
  - renderiza N rows
  - click em status card aplica filtro
  - bulk bar aparece ao selecionar
  - atalho `/` foca busca

- E2E (Cypress) — cenário golden:
  1. login
  2. abre /os
  3. filtra status=arte
  4. seleciona 3 rows
  5. bulk → mudar etapa → producao
  6. confirma toast e atualização da lista

## Tamanho do arquivo

Alvo: < 400 linhas. Se exceder, extrair:
- `OsTable.tsx` (DataTable wrapper)
- `OsFilters.tsx` (filtros)
- `OsBulkBar.tsx` (bulk)
- `useOsShortcuts.ts`

`Index.tsx` fica como composição.

## Tweaks (não escopo desta sprint)

Deixar comentário `// TODO MWART-S3: tweak densidade Skim/Briefing` perto do DataTable. Não implementar.
