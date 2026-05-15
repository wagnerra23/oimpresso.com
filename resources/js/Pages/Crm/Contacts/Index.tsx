// US-CRM-CONT-001 — Lista de Contatos (Clientes/Fornecedores) Inertia/React.
// Migração Blade legacy `contact/index.blade.php` (DataTables + jQuery + Select2)
// para Cockpit Pattern V2 (ADR 0110). Canary: Filha Martinho (biz=164) + Dani.
//
// Pain-point #1 reunião 2026-05-13: velocidade pra abrir cadastro/prospecção.
// Estado-da-arte 2026 Linear/Notion/Stripe — denso, atalhos teclado, busca instant.
//
// Refs: ADR 0104 (processo MWART), ADR 0110 (Cockpit V2), ADR 0093 (multi-tenant Tier 0),
//        ADR 0141 (skill migracao-blade-react), Pages/Sells/Index.tsx (gold-standard).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Edit,
  Eye,
  Layers,
  Loader2,
  Mail,
  MoreVertical,
  Phone,
  Plus,
  Power,
  Search,
  Trash2,
  Users,
  X,
} from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

// ── Types ────────────────────────────────────────────────────────────────────

interface ContactKpis {
  total: number;
  active: number;
  inactive: number;
}

interface ContactRow {
  id: number;
  type: 'customer' | 'supplier' | 'both' | string;
  contact_type: 'individual' | 'business' | string | null;
  contact_id: string | null;
  name: string;
  supplier_business_name: string | null;
  tax_number: string | null;
  mobile: string | null;
  email: string | null;
  city: string | null;
  state: string | null;
  contact_status: 'active' | 'inactive' | string;
  is_default: boolean;
  created_at: string | null;
}

type StatusFilter = '' | 'active' | 'inactive';
type ContactType = 'customer' | 'supplier';
type SortKey = 'name' | 'mobile' | 'tax_number' | 'created_at';
type SortDir = 'asc' | 'desc';

interface ListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface ContactsIndexPageProps {
  type: ContactType;
  kpis: ContactKpis;
  permissions: {
    create: boolean;
    update: boolean;
    delete: boolean;
    view: boolean;
  };
}

const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const STATUS_FILTER_STORAGE_KEY = 'oimpresso.crm.contacts.lastStatus';
const STATUS_FILTER_VALUES = ['', 'active', 'inactive'] as const;
const SORT_KEY_STORAGE_KEY = 'oimpresso.crm.contacts.sortKey';
const SORT_DIR_STORAGE_KEY = 'oimpresso.crm.contacts.sortDir';

// ── Formatters ───────────────────────────────────────────────────────────────

/**
 * Format CPF (11 dig) or CNPJ (14 dig). Other lengths → raw. Display only;
 * banco mantém raw em `contacts.tax_number`.
 */
function formatTaxNumber(raw: string | null): string {
  if (!raw) return '—';
  const digits = raw.replace(/\D/g, '');
  if (digits.length === 11) {
    return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  }
  if (digits.length === 14) {
    return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  }
  return raw;
}

/**
 * Format telefone BR: 10 dig = (DD) NNNN-NNNN, 11 dig = (DD) NNNNN-NNNN.
 */
function formatPhone(raw: string | null): string {
  if (!raw) return '—';
  const d = raw.replace(/\D/g, '');
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return raw;
}

function readStoredStatusFilter(): StatusFilter {
  if (typeof window === 'undefined') return '';
  try {
    const params = new URLSearchParams(window.location.search);
    const fromUrl = params.get('status');
    if (fromUrl !== null && (STATUS_FILTER_VALUES as readonly string[]).includes(fromUrl)) {
      return fromUrl as StatusFilter;
    }
  } catch (_) { /* SSR */ }
  try {
    const v = window.localStorage.getItem(STATUS_FILTER_STORAGE_KEY);
    if (v !== null && (STATUS_FILTER_VALUES as readonly string[]).includes(v)) {
      return v as StatusFilter;
    }
  } catch (_) { /* localStorage indisponível */ }
  return '';
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ContactsIndex(props: ContactsIndexPageProps) {
  const { type, kpis, permissions } = props;

  const [statusFilter, setStatusFilter] = useState<StatusFilter>(() => readStoredStatusFilter());
  const [rows, setRows] = useState<ContactRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState<ListMeta | null>(null);

  // Busca livre — nome / CPF-CNPJ / telefone / email. Debounce 300ms.
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Paginação + ordenação.
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sortKey, setSortKey] = useState<SortKey>(() => {
    if (typeof window === 'undefined') return 'name';
    try {
      const v = window.localStorage.getItem(SORT_KEY_STORAGE_KEY);
      if (v === 'name' || v === 'mobile' || v === 'tax_number' || v === 'created_at') return v;
    } catch (_) { /* */ }
    return 'name';
  });
  const [sortDir, setSortDir] = useState<SortDir>(() => {
    if (typeof window === 'undefined') return 'asc';
    try {
      const v = window.localStorage.getItem(SORT_DIR_STORAGE_KEY);
      if (v === 'asc' || v === 'desc') return v;
    } catch (_) { /* */ }
    return 'asc';
  });

  // Persiste status filter.
  useEffect(() => {
    try {
      window.localStorage.setItem(STATUS_FILTER_STORAGE_KEY, statusFilter);
    } catch (_) { /* */ }
  }, [statusFilter]);

  useEffect(() => {
    try {
      window.localStorage.setItem(SORT_KEY_STORAGE_KEY, sortKey);
      window.localStorage.setItem(SORT_DIR_STORAGE_KEY, sortDir);
    } catch (_) { /* */ }
  }, [sortKey, sortDir]);

  // Reset página quando filter/search/sort/per_page muda.
  useEffect(() => {
    setPage(1);
  }, [statusFilter, search, sortKey, sortDir, perPage, type]);

  // Fetch
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const params = new URLSearchParams();
    params.set('type', type);
    if (statusFilter) params.set('status', statusFilter);
    if (search) params.set('q', search);
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    fetch(`/contacts/list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        if (cancelled) return;
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
      })
      .catch(() => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [type, statusFilter, search, page, perPage, sortKey, sortDir]);

  const refetch = useCallback(() => {
    const params = new URLSearchParams();
    params.set('type', type);
    if (statusFilter) params.set('status', statusFilter);
    if (search) params.set('q', search);
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    fetch(`/contacts/list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
      });
  }, [type, statusFilter, search, page, perPage, sortKey, sortDir]);

  const handleSort = useCallback((key: SortKey) => {
    setSortKey((prevKey) => {
      if (key === prevKey) return prevKey;
      return key;
    });
    setSortDir((prevDir) => {
      if (key === sortKey) {
        return prevDir === 'asc' ? 'desc' : 'asc';
      }
      return key === 'created_at' ? 'desc' : 'asc';
    });
  }, [sortKey]);

  // Pills canon Cockpit V2 — 3 statuses (Todos / Ativos / Inativos)
  const pills: Array<{ key: StatusFilter; label: string; icon: typeof Layers; count: number }> = [
    { key: '',         label: 'Todos',    icon: Layers,       count: kpis.total },
    { key: 'active',   label: 'Ativos',   icon: CheckCircle2, count: kpis.active },
    { key: 'inactive', label: 'Inativos', icon: Power,        count: kpis.inactive },
  ];

  const typeLabel = type === 'supplier' ? 'Fornecedores' : 'Clientes';
  const newButtonLabel = type === 'supplier' ? 'Novo fornecedor' : 'Novo cliente';
  const createHref = `/contacts/create?type=${type}`;

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      {/* Header Cockpit canon — h1 + subtitle + KPIs + filter pills */}
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">{typeLabel}</h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                {type === 'supplier'
                  ? 'Fornecedores cadastrados — busque por nome, CNPJ, telefone ou email.'
                  : 'Clientes cadastrados — busque rápido por nome, CPF/CNPJ, telefone ou email.'}
              </p>
            </div>
            <div className="flex-shrink-0 flex items-center gap-3">
              {permissions.create && (
                <Button asChild>
                  {/* anchor force native nav: controller dual-mode pode retornar Blade
                      em biz sem flag ativada — Inertia <Link> quebraria. Acompanha pattern Sells/Index. */}
                  <a href={createHref}>
                    <Plus className="mr-1.5 h-4 w-4" />
                    {newButtonLabel}
                  </a>
                </Button>
              )}
            </div>
          </div>

          {/* 3 KPIs cards */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <KpiCard label="Total" value={kpis.total} icon={Users} />
            <KpiCard label="Ativos" value={kpis.active} icon={CheckCircle2} />
            <KpiCard label="Inativos" value={kpis.inactive} icon={Power} muted />
          </div>

          {/* Filter pills */}
          <nav className="flex items-center gap-2 mt-6 flex-wrap" aria-label="Filtro de status do contato">
            {pills.map((pill) => {
              const isActive = statusFilter === pill.key;
              const Icon = pill.icon;
              return (
                <button
                  key={pill.key || 'all'}
                  type="button"
                  onClick={() => setStatusFilter(pill.key)}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    (isActive
                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                      : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                  }
                  aria-current={isActive ? 'true' : undefined}
                >
                  <Icon size={13} />
                  {pill.label}
                  {pill.count > 0 && (
                    <span
                      className={
                        'ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums ' +
                        (isActive ? 'bg-blue-100 dark:bg-blue-900/60' : 'bg-background')
                      }
                    >
                      {pill.count}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-7xl">
        {/* Busca livre */}
        <div className="mb-4 flex items-center gap-2">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar por nome, CPF/CNPJ, telefone ou email…"
              className="pl-9 pr-9 h-9"
              aria-label="Buscar contato"
              autoFocus
            />
            {searchInput && (
              <button
                type="button"
                onClick={() => setSearchInput('')}
                aria-label="Limpar busca"
                className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
          {loading && search && (
            <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
          )}
        </div>

        {/* Tabela */}
        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="border-b border-border">
                  <SortableTh sortKey="name" current={sortKey} dir={sortDir} onSort={handleSort}>Nome</SortableTh>
                  <SortableTh sortKey="tax_number" current={sortKey} dir={sortDir} onSort={handleSort} className="w-44">CPF/CNPJ</SortableTh>
                  <SortableTh sortKey="mobile" current={sortKey} dir={sortDir} onSort={handleSort} className="w-44">Telefone</SortableTh>
                  <Th className="w-24">Tipo</Th>
                  <Th className="w-24">Status</Th>
                  <Th className="w-12 text-right pr-4">&nbsp;</Th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={6} className="text-center py-12 text-muted-foreground text-xs">
                      Carregando…
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center py-12 text-muted-foreground text-xs">
                      {search
                        ? `Nenhum ${type === 'supplier' ? 'fornecedor' : 'cliente'} encontrado pra "${search}".`
                        : `Nenhum ${type === 'supplier' ? 'fornecedor' : 'cliente'} cadastrado ainda.`}
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => {
                    const isInactive = row.contact_status === 'inactive';
                    const displayName = row.supplier_business_name
                      ? row.supplier_business_name
                      : row.name;
                    const secondaryName = row.supplier_business_name && row.name !== row.supplier_business_name
                      ? row.name
                      : null;
                    return (
                      <tr
                        key={row.id}
                        className={
                          'border-b border-border cursor-pointer transition-colors hover:bg-muted/40 ' +
                          (isInactive ? 'opacity-60' : '')
                        }
                        onClick={() => { window.location.href = `/contacts/${row.id}`; }}
                      >
                        <td className="px-4 py-3">
                          <div className="text-foreground font-medium leading-tight">
                            {displayName}
                            {row.contact_id && (
                              <span className="ml-2 text-xs text-muted-foreground font-normal">#{row.contact_id}</span>
                            )}
                          </div>
                          {secondaryName && (
                            <div className="text-xs text-muted-foreground leading-tight mt-0.5">{secondaryName}</div>
                          )}
                        </td>
                        <td className="px-4 py-3 text-xs tabular-nums text-muted-foreground">
                          {formatTaxNumber(row.tax_number)}
                        </td>
                        <td className="px-4 py-3">
                          <div className="text-xs text-foreground tabular-nums inline-flex items-center gap-1">
                            {row.mobile ? <Phone size={11} className="text-muted-foreground/60" /> : null}
                            {formatPhone(row.mobile)}
                          </div>
                          {row.email && (
                            <div className="text-[11px] text-muted-foreground leading-tight mt-0.5 inline-flex items-center gap-1">
                              <Mail size={10} />
                              {row.email}
                            </div>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <TypeBadge type={row.type} />
                        </td>
                        <td className="px-4 py-3">
                          <StatusBadge status={row.contact_status} />
                        </td>
                        <td className="px-2 py-3 text-right pr-4" onClick={(e) => e.stopPropagation()}>
                          <ActionsMenu
                            row={row}
                            permissions={permissions}
                            onChange={refetch}
                          />
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>

        {meta && meta.total > 0 && (
          <Pagination
            meta={meta}
            perPage={perPage}
            onPageChange={setPage}
            onPerPageChange={setPerPage}
          />
        )}
      </div>
    </div>
  );
}

ContactsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

function KpiCard({
  label,
  value,
  icon: Icon,
  muted,
}: {
  label: string;
  value: number;
  icon: typeof Users;
  muted?: boolean;
}) {
  return (
    <div className="rounded-xl border border-border bg-background p-5 shadow-sm">
      <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
        {label}
      </div>
      <div className="flex items-end justify-between mt-3">
        <div className={'text-4xl font-semibold tabular-nums ' + (muted ? 'text-muted-foreground' : 'text-foreground')}>
          {value.toLocaleString('pt-BR')}
        </div>
        <Icon
          size={28}
          className={muted ? 'text-muted-foreground/40' : 'text-muted-foreground/60'}
          strokeWidth={1.5}
        />
      </div>
    </div>
  );
}

function Th({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      {children}
    </th>
  );
}

function SortableTh({
  children,
  sortKey,
  current,
  dir,
  onSort,
  className = '',
}: {
  children: ReactNode;
  sortKey: SortKey;
  current: SortKey;
  dir: SortDir;
  onSort: (k: SortKey) => void;
  className?: string;
}) {
  const active = current === sortKey;
  const Icon = !active ? ArrowUpDown : dir === 'asc' ? ArrowUp : ArrowDown;
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      <button
        type="button"
        onClick={() => onSort(sortKey)}
        className={
          'inline-flex items-center gap-1 transition-colors hover:text-foreground ' +
          (active ? 'text-foreground' : '')
        }
        aria-sort={active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none'}
      >
        {children}
        <Icon size={12} className={active ? '' : 'opacity-40'} />
      </button>
    </th>
  );
}

function TypeBadge({ type }: { type: string }) {
  const map: Record<string, { label: string; cls: string }> = {
    customer: { label: 'Cliente', cls: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300' },
    supplier: { label: 'Fornecedor', cls: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300' },
    both: { label: 'Ambos', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' },
  };
  const entry = map[type] ?? { label: type, cls: 'bg-muted text-muted-foreground border-border' };
  return (
    <span className={'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' + entry.cls}>
      {entry.label}
    </span>
  );
}

function StatusBadge({ status }: { status: string }) {
  if (status === 'inactive') {
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
        <Power size={11} />
        Inativo
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300">
      <CheckCircle2 size={11} />
      Ativo
    </span>
  );
}

function ActionsMenu({
  row,
  permissions,
  onChange,
}: {
  row: ContactRow;
  permissions: ContactsIndexPageProps['permissions'];
  onChange: () => void;
}) {
  const handleDelete = async () => {
    if (row.is_default) {
      alert('Este contato padrão não pode ser excluído.');
      return;
    }
    const label = row.supplier_business_name ?? row.name;
    if (!confirm(`Excluir o contato "${label}"? Essa ação não pode ser desfeita.`)) return;
    try {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const csrf = meta?.getAttribute('content') ?? '';
      const res = await fetch(`/contacts/${row.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        const json = await res.json().catch(() => null);
        alert(json?.msg ?? 'Falha ao excluir contato.');
        return;
      }
      const json = await res.json().catch(() => null);
      if (json && json.success === false) {
        alert(json.msg ?? 'Falha ao excluir contato.');
        return;
      }
      onChange();
    } catch (e) {
      alert('Erro ao excluir: ' + String((e as Error)?.message || e));
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-label="Ações do contato"
          onClick={(e) => e.stopPropagation()}
        >
          <MoreVertical size={16} />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-52">
        {permissions.view && (
          <DropdownMenuItem asChild>
            <a href={`/contacts/${row.id}`}>
              <Eye size={14} className="mr-2" />
              Ver detalhes
            </a>
          </DropdownMenuItem>
        )}
        {permissions.update && (
          <DropdownMenuItem asChild>
            <a href={`/contacts/${row.id}/edit`}>
              <Edit size={14} className="mr-2" />
              Editar
            </a>
          </DropdownMenuItem>
        )}
        {permissions.update && (
          <DropdownMenuItem asChild>
            <a href={`/contacts/update-status/${row.id}`}>
              <Power size={14} className="mr-2" />
              {row.contact_status === 'active' ? 'Desativar' : 'Ativar'}
            </a>
          </DropdownMenuItem>
        )}
        {permissions.delete && !row.is_default && (
          <DropdownMenuItem
            onClick={handleDelete}
            className="text-rose-600 focus:bg-rose-50 focus:text-rose-700 dark:text-rose-400 dark:focus:bg-rose-950/40"
          >
            <Trash2 size={14} className="mr-2" />
            Excluir
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function Pagination({
  meta,
  perPage,
  onPageChange,
  onPerPageChange,
}: {
  meta: ListMeta;
  perPage: number;
  onPageChange: (p: number) => void;
  onPerPageChange: (n: number) => void;
}) {
  const { current_page, last_page, total, from, to } = meta;
  const canPrev = current_page > 1;
  const canNext = current_page < last_page;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 mt-3 px-1">
      <div className="text-xs text-muted-foreground">
        {total === 0
          ? 'Nenhum contato'
          : `Mostrando ${from ?? 0}–${to ?? 0} de ${total.toLocaleString('pt-BR')}`}
      </div>
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>Por página</span>
          <select
            value={perPage}
            onChange={(e) => onPerPageChange(Number(e.target.value))}
            className="h-7 rounded border border-border bg-background px-2 text-xs text-foreground"
            aria-label="Itens por página"
          >
            {PER_PAGE_OPTIONS.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-1">
          <PageBtn onClick={() => onPageChange(1)} disabled={!canPrev} aria-label="Primeira página">
            <ChevronsLeft size={14} />
          </PageBtn>
          <PageBtn onClick={() => onPageChange(current_page - 1)} disabled={!canPrev} aria-label="Página anterior">
            <ChevronLeft size={14} />
          </PageBtn>
          <span className="px-2 text-xs tabular-nums text-foreground">
            {current_page} <span className="text-muted-foreground">/ {last_page}</span>
          </span>
          <PageBtn onClick={() => onPageChange(current_page + 1)} disabled={!canNext} aria-label="Próxima página">
            <ChevronRight size={14} />
          </PageBtn>
          <PageBtn onClick={() => onPageChange(last_page)} disabled={!canNext} aria-label="Última página">
            <ChevronsRight size={14} />
          </PageBtn>
        </div>
      </div>
    </div>
  );
}

function PageBtn({
  children,
  onClick,
  disabled,
  ...rest
}: React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      {...rest}
      className="inline-flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-background"
    >
      {children}
    </button>
  );
}
