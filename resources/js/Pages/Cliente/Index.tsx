// W1-B3 Cliente/Index — listagem de clientes Inertia/React (MWART F3).
// Refs:
//   - ADR 0104 (MWART canônico) · ADR 0107 (visual gate) · ADR 0149 (pattern reuse)
//   - ADR 0093 (multi-tenant Tier 0) · ADR 0110 (Cockpit V2)
//   - Blueprint Cowork: prototipo-ui/prototipos/clientes/cowork-app.jsx
//   - Reuse pattern de Pages/Sells/Index.tsx (gold-standard W1-A)
// Backend: ContactController::index() — Inertia::render dual via config('mwart.cliente_index.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Clock,
  CornerDownLeft,
  CreditCard,
  Edit,
  Eye,
  Keyboard,
  Layers,
  Loader2,
  MoreVertical,
  Phone,
  Plus,
  Search,
  Trash2,
  Upload,
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
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';

interface ClienteKpis {
  total: number;
  com_os_aberta: number;
  com_atraso: number;
  valor_total_aberto: number;
}

interface ClienteRow {
  id: number;
  name: string;
  // PII mascarado server-side — nunca plain digits no client.
  tax_number_masked: string | null;
  contact_id: string | null;
  mobile: string | null;
  total_os: number;
  os_abertas: number;
  os_atrasadas: number;
  valor_aberto: number;
  status: 'late' | 'active' | 'idle';
  last_os_at: string | null;
}

interface ListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  sort: SortKey;
  dir: SortDir;
}

export interface ClienteIndexPageProps {
  kpis: ClienteKpis;
  customers: {
    data: ClienteRow[];
    meta: ListMeta;
  };
  permissions: {
    create: boolean;
    view: boolean;
    import: boolean;
  };
}

type StatusFilter = '' | 'late' | 'active' | 'idle';
type SortKey = 'name' | 'total_os' | 'valor_aberto' | 'last_os_at';
type SortDir = 'asc' | 'desc';

const STATUS_FILTER_STORAGE_KEY = 'oimpresso.cliente.lastStatus';
const STATUS_FILTER_VALUES = ['', 'late', 'active', 'idle'] as const;
const PER_PAGE_OPTIONS = [25, 50, 100];

const STATUS_LABEL: Record<string, string> = {
  late: 'Atrasado',
  active: 'Ativo',
  idle: 'Sem OS',
};

const STATUS_STYLE: Record<string, string> = {
  late: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300',
  active: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300',
  idle: 'bg-stone-50 text-stone-700 border-stone-200 dark:bg-stone-950/40 dark:text-stone-300',
};

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(d);
};

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

function avatarInitial(name: string): string {
  const parts = name.trim().split(/\s+/);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

export default function ClienteIndex(props: ClienteIndexPageProps) {
  const [statusFilter, setStatusFilter] = useState<StatusFilter>(() => readStoredStatusFilter());
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sortKey, setSortKey] = useState<SortKey>('name');
  const [sortDir, setSortDir] = useState<SortDir>('asc');
  const [openContactId, setOpenContactId] = useState<number | null>(null);

  // KB-9.75 N2/N3/P2 — Slice A (Wagner approved 2026-05-21):
  // ⌘K (palette) · ? (cheat-sheet) · J/K (row nav) · Enter (open) · / (focus search)
  const searchInputRef = useRef<HTMLInputElement>(null);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [cheatOpen, setCheatOpen] = useState(false);
  const [focusedIndex, setFocusedIndex] = useState<number | null>(null);

  useEffect(() => {
    try {
      window.localStorage.setItem(STATUS_FILTER_STORAGE_KEY, statusFilter);
    } catch (_) { /* localStorage indisponível */ }
  }, [statusFilter]);

  useEffect(() => {
    setPage(1);
  }, [statusFilter, search, sortKey, sortDir, perPage]);

  const rows = props.customers?.data ?? [];
  const meta = props.customers?.meta ?? null;
  const kpis = props.kpis;

  const filteredRows = useMemo(() => {
    let r = rows;
    if (statusFilter) r = r.filter((x) => x.status === statusFilter);
    if (search) {
      const q = search.toLowerCase();
      r = r.filter((x) =>
        x.name.toLowerCase().includes(q) ||
        (x.tax_number_masked ?? '').toLowerCase().includes(q) ||
        (x.mobile ?? '').includes(q),
      );
    }
    return r;
  }, [rows, statusFilter, search]);

  // KB-9.75 Slice A — reset row focus when the result set shrinks/changes.
  useEffect(() => {
    setFocusedIndex(null);
  }, [search, statusFilter, sortKey, sortDir, page]);

  // KB-9.75 Slice A — global keydown listener (Tier A skill "Charter Anti-pattern: no sessionStorage")
  // Gates: skip when typing in inputs/textareas and when any modal is open (palette/cheat have own handlers).
  useEffect(() => {
    const isTypingTarget = (target: EventTarget | null): boolean => {
      if (!(target instanceof HTMLElement)) return false;
      const tag = target.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
      if (target.isContentEditable) return true;
      return false;
    };

    const onKey = (e: KeyboardEvent) => {
      // ⌘K / Ctrl+K — global trigger, even inside inputs (matches Slack/Linear/GitHub).
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPaletteOpen(true);
        return;
      }

      // Suspend all other shortcuts while a modal is open OR while typing in form fields.
      if (paletteOpen || cheatOpen) return;
      if (isTypingTarget(e.target)) return;

      if (e.key === '?') {
        e.preventDefault();
        setCheatOpen(true);
        return;
      }

      if (e.key === '/') {
        e.preventDefault();
        searchInputRef.current?.focus();
        searchInputRef.current?.select();
        return;
      }

      if (e.key === 'j' || e.key === 'J') {
        if (filteredRows.length === 0) return;
        e.preventDefault();
        setFocusedIndex((i) => (i === null ? 0 : Math.min(i + 1, filteredRows.length - 1)));
        return;
      }

      if (e.key === 'k' || e.key === 'K') {
        if (filteredRows.length === 0) return;
        e.preventDefault();
        setFocusedIndex((i) => (i === null ? filteredRows.length - 1 : Math.max(i - 1, 0)));
        return;
      }

      if (e.key === 'Enter' && focusedIndex !== null) {
        e.preventDefault();
        const row = filteredRows[focusedIndex];
        if (row) setOpenContactId(row.id);
        return;
      }

      if (e.key === 'Escape' && focusedIndex !== null) {
        setFocusedIndex(null);
        return;
      }
    };

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [paletteOpen, cheatOpen, filteredRows, focusedIndex]);

  const handleSort = useCallback((key: SortKey) => {
    setSortKey((prevKey) => {
      if (key === prevKey) return prevKey;
      return key;
    });
    setSortDir((prevDir) => {
      if (key === sortKey) {
        return prevDir === 'asc' ? 'desc' : 'asc';
      }
      return key === 'last_os_at' || key === 'valor_aberto' ? 'desc' : 'asc';
    });
  }, [sortKey]);

  const pills: Array<{ key: StatusFilter; label: string; icon: typeof Layers; count: number; danger?: boolean }> = [
    { key: '',       label: 'Todos',      icon: Layers,        count: kpis?.total ?? 0 },
    { key: 'active', label: 'Ativos',     icon: Users,         count: kpis?.com_os_aberta ?? 0 },
    { key: 'late',   label: 'Atrasados',  icon: AlertTriangle, count: kpis?.com_atraso ?? 0, danger: true },
    { key: 'idle',   label: 'Sem OS',     icon: Clock,         count: Math.max(0, (kpis?.total ?? 0) - (kpis?.com_os_aberta ?? 0)) },
  ];

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">Clientes</h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                Lista de clientes com KPIs de relacionamento e drawer de detalhes ao clicar.
              </p>
            </div>
            <div className="flex-shrink-0 flex items-center gap-2">
              {props.permissions.import && (
                <Button asChild variant="outline">
                  <a href="/contacts/import">
                    <Upload className="mr-1.5 h-4 w-4" />
                    Importar
                  </a>
                </Button>
              )}
              {props.permissions.create && (
                <Button asChild>
                  <a href="/contacts/create?type=customer">
                    <Plus className="mr-1.5 h-4 w-4" />
                    Novo cliente
                  </a>
                </Button>
              )}
            </div>
          </div>

          <Deferred data="kpis" fallback={<KpiSkeleton />}>
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
              <KpiCard label="Total" value={kpis?.total ?? 0} icon={Users} />
              <KpiCard label="Com OS aberta" value={kpis?.com_os_aberta ?? 0} icon={Clock} />
              <KpiCard label="Com atraso" value={kpis?.com_atraso ?? 0} icon={AlertTriangle} danger={(kpis?.com_atraso ?? 0) > 0} />
              <KpiCard label="Valor aberto" valueBRL={kpis?.valor_total_aberto ?? 0} icon={CreditCard} />
            </div>
          </Deferred>

          <nav className="flex items-center gap-2 mt-6 flex-wrap" aria-label="Filtro de status">
            {pills.map((pill) => {
              const isActive = statusFilter === pill.key;
              const Icon = pill.icon;
              const danger = pill.danger && pill.count > 0;
              return (
                <button
                  key={pill.key || 'all'}
                  type="button"
                  onClick={() => setStatusFilter(pill.key)}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    (isActive
                      ? danger
                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200'
                        : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                      : danger
                        ? 'bg-rose-50/60 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/30 dark:text-rose-300'
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
                        (isActive
                          ? danger
                            ? 'bg-rose-200/80 dark:bg-rose-900/60'
                            : 'bg-blue-100 dark:bg-blue-900/60'
                          : 'bg-background')
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
        <div className="mb-4 flex items-center gap-2">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              ref={searchInputRef}
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar por nome, CNPJ/CPF, telefone… (/ pra focar · ⌘K busca global)"
              className="pl-9 pr-9 h-9"
              aria-label="Buscar cliente"
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
        </div>

        <Deferred data="customers" fallback={<TableSkeleton />}>
          <div className="rounded-lg border border-border bg-background overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50">
                  <tr className="border-b border-border">
                    <Th className="w-8">&nbsp;</Th>
                    <SortableTh sortKey="name" current={sortKey} dir={sortDir} onSort={handleSort}>Cliente</SortableTh>
                    <Th>Contato</Th>
                    <SortableTh sortKey="total_os" current={sortKey} dir={sortDir} onSort={handleSort} align="right" className="w-20">OS</SortableTh>
                    <Th className="w-20 text-right">Abertas</Th>
                    <SortableTh sortKey="valor_aberto" current={sortKey} dir={sortDir} onSort={handleSort} align="right" className="w-32">Valor aberto</SortableTh>
                    <Th className="w-24">Status</Th>
                    <SortableTh sortKey="last_os_at" current={sortKey} dir={sortDir} onSort={handleSort} className="w-24">Última OS</SortableTh>
                    <Th className="w-12 text-right pr-4">&nbsp;</Th>
                  </tr>
                </thead>
                <tbody>
                  {filteredRows.length === 0 ? (
                    <tr>
                      <td colSpan={9} className="text-center py-12 text-muted-foreground text-xs">
                        Nenhum cliente encontrado nesse filtro.
                      </td>
                    </tr>
                  ) : (
                    filteredRows.map((row, idx) => {
                      const isOpen = openContactId === row.id;
                      const isFocused = focusedIndex === idx;
                      return (
                        <tr
                          key={row.id}
                          className={
                            'border-b border-border cursor-pointer transition-colors ' +
                            (isOpen
                              ? 'bg-blue-50/60 dark:bg-blue-950/30'
                              : isFocused
                                ? 'bg-blue-50/30 dark:bg-blue-950/20 ring-2 ring-inset ring-blue-300 dark:ring-blue-700'
                                : 'hover:bg-muted/40')
                          }
                          onClick={() => {
                            setFocusedIndex(idx);
                            setOpenContactId(row.id);
                          }}
                        >
                          <td className="px-4 py-3">
                            <Avatar initial={avatarInitial(row.name)} />
                          </td>
                          <td className="px-4 py-3">
                            <div className="text-foreground font-medium leading-tight">{row.name}</div>
                            {row.tax_number_masked && (
                              <div className="text-xs text-muted-foreground tabular-nums leading-tight mt-0.5">
                                {row.tax_number_masked}
                              </div>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            {row.mobile ? (
                              <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Phone size={11} />
                                {row.mobile}
                              </span>
                            ) : (
                              <span className="text-xs text-muted-foreground/50">—</span>
                            )}
                          </td>
                          <td className="px-4 py-3 text-right tabular-nums text-foreground">{row.total_os}</td>
                          <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">{row.os_abertas}</td>
                          <td className="px-4 py-3 text-right tabular-nums text-foreground">{formatBRL(row.valor_aberto)}</td>
                          <td className="px-4 py-3">
                            <StatusBadge status={row.status} />
                          </td>
                          <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">{formatDate(row.last_os_at)}</td>
                          <td className="px-2 py-3 text-right pr-4" onClick={(e) => e.stopPropagation()}>
                            <ActionsMenu row={row} onView={() => setOpenContactId(row.id)} />
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
            <Pagination meta={meta} perPage={perPage} onPageChange={setPage} onPerPageChange={setPerPage} />
          )}
        </Deferred>
      </div>

      <ClienteSheet
        contactId={openContactId}
        open={openContactId !== null}
        rows={rows}
        onOpenChange={(open) => {
          if (!open) setOpenContactId(null);
        }}
      />

      {/* KB-9.75 Slice A — Command Palette + Cheat-sheet + FAB. State efêmero, sem persistência. */}
      {paletteOpen && (
        <CommandPalette
          rows={rows}
          onClose={() => setPaletteOpen(false)}
          onSelectContact={(id) => {
            setPaletteOpen(false);
            setOpenContactId(id);
          }}
          onClearFilters={() => {
            setStatusFilter('');
            setSearchInput('');
            setSearch('');
            setFocusedIndex(null);
          }}
        />
      )}
      {cheatOpen && <CheatSheet onClose={() => setCheatOpen(false)} />}
      <button
        type="button"
        onClick={() => setCheatOpen(true)}
        aria-label="Atalhos de teclado"
        title="Atalhos de teclado (?)"
        className="fixed bottom-4 right-4 z-40 inline-flex items-center gap-1.5 rounded-full border border-border bg-background px-3 py-1.5 text-xs text-muted-foreground shadow-sm hover:bg-muted hover:text-foreground transition-colors"
      >
        <Keyboard size={14} />
        <Kbd>?</Kbd>
      </button>
    </div>
  );
}

ClienteIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

function KpiSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="rounded-xl border border-border bg-background p-5 shadow-sm h-28 animate-pulse" />
      ))}
    </div>
  );
}

function TableSkeleton() {
  return (
    <div className="rounded-lg border border-border bg-background overflow-hidden">
      <div className="p-8 text-center text-muted-foreground text-xs">
        <Loader2 className="inline-block h-4 w-4 animate-spin mr-2" />
        Carregando clientes…
      </div>
    </div>
  );
}

function KpiCard({
  label,
  value,
  valueBRL,
  icon: Icon,
  danger,
}: {
  label: string;
  value?: number;
  valueBRL?: number;
  icon: typeof Users;
  danger?: boolean;
}) {
  const display = valueBRL !== undefined ? formatBRL(valueBRL) : (value ?? 0).toLocaleString('pt-BR');
  return (
    <div
      className={
        'rounded-xl border p-5 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div
        className={
          'text-[11px] font-semibold uppercase tracking-widest ' +
          (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div className="flex items-end justify-between mt-3">
        <div
          className={
            'text-3xl font-semibold tabular-nums ' +
            (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')
          }
        >
          {display}
        </div>
        <Icon
          size={24}
          className={danger ? 'text-rose-400' : 'text-muted-foreground/60'}
          strokeWidth={1.5}
        />
      </div>
    </div>
  );
}

function Avatar({ initial }: { initial: string }) {
  return (
    <div
      className="h-8 w-8 rounded-md bg-gradient-to-br from-stone-100 to-stone-200 dark:from-stone-800 dark:to-stone-700 flex items-center justify-center text-xs font-semibold text-stone-700 dark:text-stone-200"
      aria-hidden="true"
    >
      {initial}
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
  align = 'left',
}: {
  children: ReactNode;
  sortKey: SortKey;
  current: SortKey;
  dir: SortDir;
  onSort: (k: SortKey) => void;
  className?: string;
  align?: 'left' | 'right';
}) {
  const active = current === sortKey;
  const Icon = !active ? ArrowUpDown : dir === 'asc' ? ArrowUp : ArrowDown;
  return (
    <th
      className={
        (align === 'right' ? 'text-right ' : 'text-left ') +
        'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
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

function StatusBadge({ status }: { status: ClienteRow['status'] }) {
  const cls = STATUS_STYLE[status] ?? 'bg-muted text-muted-foreground border-border';
  const label = STATUS_LABEL[status] ?? status;
  return (
    <span className={'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' + cls}>
      {label}
    </span>
  );
}

function ActionsMenu({ row, onView }: { row: ClienteRow; onView: () => void }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-label="Ações do cliente"
          onClick={(e) => e.stopPropagation()}
        >
          <MoreVertical size={16} />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuItem onClick={onView}>
          <Eye size={14} className="mr-2" />
          Ver detalhes
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <a href={`/contacts/${row.id}`}>
            <Eye size={14} className="mr-2" />
            Página completa
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <a href={`/contacts/${row.id}/edit`}>
            <Edit size={14} className="mr-2" />
            Editar
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <a href={`/contacts/ledger?contact_id=${row.id}`}>
            <CreditCard size={14} className="mr-2" />
            Extrato
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem
          className="text-rose-600 focus:bg-rose-50 focus:text-rose-700 dark:text-rose-400 dark:focus:bg-rose-950/40"
          disabled
          title="Use rota legacy /contacts/duplicates pra deletar/mesclar (Non-Goal Charter)."
        >
          <Trash2 size={14} className="mr-2" />
          Excluir
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function ClienteSheet({
  contactId,
  open,
  rows,
  onOpenChange,
}: {
  contactId: number | null;
  open: boolean;
  rows: ClienteRow[];
  onOpenChange: (open: boolean) => void;
}) {
  const contact = useMemo(() => rows.find((r) => r.id === contactId) ?? null, [rows, contactId]);
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-[480px] sm:max-w-[480px]">
        <SheetHeader>
          <SheetTitle>{contact?.name ?? 'Cliente'}</SheetTitle>
          <SheetDescription>
            {contact?.tax_number_masked ?? 'Documento não informado'}
          </SheetDescription>
        </SheetHeader>
        {contact && (
          <div className="mt-6 space-y-5">
            <section className="grid grid-cols-2 gap-3">
              <SheetKpi label="Total OS" value={contact.total_os.toString()} />
              <SheetKpi label="Em aberto" value={contact.os_abertas.toString()} />
              <SheetKpi label="Atrasadas" value={contact.os_atrasadas.toString()} danger={contact.os_atrasadas > 0} />
              <SheetKpi label="Valor" value={formatBRL(contact.valor_aberto)} />
            </section>
            <section className="space-y-2">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contato</h4>
              <dl className="space-y-1 text-sm">
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">Telefone</dt>
                  <dd className="text-foreground">{contact.mobile ?? '—'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">Última OS</dt>
                  <dd className="text-foreground tabular-nums">{formatDate(contact.last_os_at)}</dd>
                </div>
              </dl>
            </section>
            <section className="flex gap-2">
              <Button asChild variant="outline" className="flex-1">
                <a href={`/contacts/${contact.id}`}>Página completa</a>
              </Button>
              <Button asChild className="flex-1">
                <a href={`/contacts/${contact.id}/edit`}>Editar</a>
              </Button>
            </section>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

function SheetKpi({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
  return (
    <div className={'rounded-lg border p-3 ' + (danger ? 'border-rose-200 bg-rose-50/40 dark:border-rose-900/40 dark:bg-rose-950/30' : 'border-border bg-background')}>
      <div className={'text-[10px] font-semibold uppercase tracking-widest ' + (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')}>
        {label}
      </div>
      <div className={'text-lg font-semibold tabular-nums mt-1 ' + (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')}>
        {value}
      </div>
    </div>
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
          ? 'Nenhum cliente'
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

// ─── KB-9.75 Slice A subcomponents ──────────────────────────────────────────
// Ref: prototipo-ui/prototipos/clientes/clientes-975.jsx (CommandPalette · CheatSheet)
// Charter: Pages/Cliente/Index.charter.md (Slice A não introduz Non-Goal nem Anti-hook).

type PaletteAction = {
  id: string;
  label: string;
  icon: typeof Plus;
  href?: string;
  action?: () => void;
};

function CommandPalette({
  rows,
  onClose,
  onSelectContact,
  onClearFilters,
}: {
  rows: ClienteRow[];
  onClose: () => void;
  onSelectContact: (id: number) => void;
  onClearFilters: () => void;
}) {
  const [query, setQuery] = useState('');
  const [selectedIdx, setSelectedIdx] = useState(0);

  const results = useMemo(() => {
    if (!query.trim()) return rows.slice(0, 8);
    const q = query.toLowerCase();
    return rows
      .filter(
        (r) =>
          r.name.toLowerCase().includes(q) ||
          (r.tax_number_masked ?? '').toLowerCase().includes(q) ||
          (r.mobile ?? '').includes(q),
      )
      .slice(0, 8);
  }, [rows, query]);

  const actions: PaletteAction[] = [
    { id: 'novo', label: 'Novo cliente', icon: Plus, href: '/contacts/create?type=customer' },
    { id: 'importar', label: 'Importar clientes', icon: Upload, href: '/contacts/import' },
    { id: 'clear', label: 'Limpar filtros desta página', icon: X, action: onClearFilters },
  ];

  // Combined navigable items: rows first, then actions. Index is absolute.
  const totalItems = results.length + actions.length;

  useEffect(() => {
    setSelectedIdx(0);
  }, [query]);

  const activate = useCallback(
    (absIdx: number) => {
      if (absIdx < results.length) {
        const r = results[absIdx];
        if (r) onSelectContact(r.id);
        return;
      }
      const a = actions[absIdx - results.length];
      if (!a) return;
      if (a.href) {
        window.location.href = a.href;
      } else if (a.action) {
        a.action();
        onClose();
      }
    },
    [results, actions, onSelectContact, onClose],
  );

  const onInputKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIdx((i) => Math.min(i + 1, Math.max(totalItems - 1, 0)));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIdx((i) => Math.max(i - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      activate(selectedIdx);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      onClose();
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center pt-24"
      role="dialog"
      aria-modal="true"
      aria-label="Busca rápida"
    >
      <div className="absolute inset-0 bg-black/40" onClick={onClose} aria-hidden="true" />
      <div className="relative w-full max-w-2xl rounded-xl border border-border bg-background shadow-2xl mx-4">
        <div className="flex items-center gap-2 border-b border-border px-4 py-3">
          <Search size={16} className="text-muted-foreground" aria-hidden="true" />
          <input
            type="text"
            autoFocus
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={onInputKey}
            placeholder="Buscar cliente por nome, CNPJ/CPF, telefone — ou escolha uma ação…"
            className="flex-1 bg-transparent text-sm text-foreground placeholder:text-muted-foreground outline-none"
            aria-label="Termo de busca"
          />
          <Kbd>Esc</Kbd>
        </div>

        <div className="max-h-[60vh] overflow-y-auto p-2">
          {results.length > 0 && (
            <>
              <div className="px-2 pt-1 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                Clientes {query.trim() ? `(${results.length})` : '(recentes)'}
              </div>
              {results.map((r, idx) => {
                const sel = selectedIdx === idx;
                return (
                  <button
                    key={r.id}
                    type="button"
                    onClick={() => activate(idx)}
                    onMouseEnter={() => setSelectedIdx(idx)}
                    className={
                      'w-full text-left flex items-center gap-3 rounded-md px-3 py-2 transition-colors ' +
                      (sel ? 'bg-muted text-foreground' : 'hover:bg-muted/50')
                    }
                    aria-selected={sel}
                  >
                    <Avatar initial={avatarInitial(r.name)} />
                    <div className="flex-1 min-w-0">
                      <div className="text-sm text-foreground truncate">{r.name}</div>
                      <div className="text-[11px] text-muted-foreground tabular-nums truncate">
                        {r.tax_number_masked ?? '—'}
                        {r.mobile ? ` · ${r.mobile}` : ''}
                      </div>
                    </div>
                    <StatusBadge status={r.status} />
                  </button>
                );
              })}
            </>
          )}

          {query.trim() && results.length === 0 && (
            <div className="px-3 py-6 text-center text-xs text-muted-foreground">
              Sem clientes pra <span className="font-medium text-foreground">"{query}"</span>.
              Tente outro termo ou crie um novo cliente abaixo.
            </div>
          )}

          <div className="mt-2 pt-2 border-t border-border">
            <div className="px-2 pt-1 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
              Ações
            </div>
            {actions.map((a, idx) => {
              const absIdx = results.length + idx;
              const sel = selectedIdx === absIdx;
              const Icon = a.icon;
              const cls =
                'w-full text-left flex items-center gap-3 rounded-md px-3 py-2 transition-colors ' +
                (sel ? 'bg-muted text-foreground' : 'hover:bg-muted/50 text-muted-foreground');
              if (a.href) {
                return (
                  <a key={a.id} href={a.href} onMouseEnter={() => setSelectedIdx(absIdx)} className={cls} aria-selected={sel}>
                    <Icon size={14} />
                    <span className="text-sm">{a.label}</span>
                  </a>
                );
              }
              return (
                <button
                  key={a.id}
                  type="button"
                  onClick={() => activate(absIdx)}
                  onMouseEnter={() => setSelectedIdx(absIdx)}
                  className={cls}
                  aria-selected={sel}
                >
                  <Icon size={14} />
                  <span className="text-sm">{a.label}</span>
                </button>
              );
            })}
          </div>
        </div>

        <div className="flex items-center gap-3 border-t border-border px-4 py-2 text-[10px] text-muted-foreground">
          <span className="inline-flex items-center gap-1">
            <Kbd>↑</Kbd>
            <Kbd>↓</Kbd> navegar
          </span>
          <span className="inline-flex items-center gap-1">
            <Kbd>
              <CornerDownLeft size={10} />
            </Kbd>{' '}
            abrir
          </span>
          <span className="inline-flex items-center gap-1">
            <Kbd>Esc</Kbd> fechar
          </span>
        </div>
      </div>
    </div>
  );
}

function CheatSheet({ onClose }: { onClose: () => void }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const items: Array<{ keys: ReactNode[]; label: string }> = [
    { keys: [<Kbd key="meta">⌘</Kbd>, <Kbd key="k">K</Kbd>], label: 'Busca rápida (Command Palette)' },
    { keys: [<Kbd key="slash">/</Kbd>], label: 'Focar busca da página' },
    { keys: [<Kbd key="j">J</Kbd>], label: 'Próxima linha' },
    { keys: [<Kbd key="k2">K</Kbd>], label: 'Linha anterior' },
    { keys: [<Kbd key="enter">Enter</Kbd>], label: 'Abrir cliente focado' },
    { keys: [<Kbd key="esc">Esc</Kbd>], label: 'Fechar modal ou cancelar foco' },
    { keys: [<Kbd key="q">?</Kbd>], label: 'Mostrar atalhos (este painel)' },
  ];

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      role="dialog"
      aria-modal="true"
      aria-label="Atalhos de teclado"
    >
      <div className="absolute inset-0 bg-black/40" onClick={onClose} aria-hidden="true" />
      <div className="relative w-full max-w-md rounded-xl border border-border bg-background shadow-2xl mx-4">
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <div className="flex items-center gap-2">
            <Keyboard size={16} className="text-muted-foreground" />
            <h2 className="text-sm font-semibold text-foreground">Atalhos de teclado</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Fechar painel de atalhos"
            className="text-muted-foreground hover:text-foreground"
          >
            <X size={16} />
          </button>
        </div>
        <ul className="p-2 space-y-0.5">
          {items.map((it) => (
            <li
              key={it.label}
              className="flex items-center justify-between gap-3 rounded-md px-3 py-2 hover:bg-muted/40"
            >
              <span className="text-sm text-foreground">{it.label}</span>
              <span className="flex items-center gap-1">{it.keys}</span>
            </li>
          ))}
        </ul>
        <div className="border-t border-border px-4 py-2 text-[10px] text-muted-foreground">
          Atalhos pausam quando você está digitando em um campo. <Kbd>⌘K</Kbd> sempre funciona.
        </div>
      </div>
    </div>
  );
}

function Kbd({ children }: { children: ReactNode }) {
  return (
    <kbd className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded border border-border bg-muted px-1.5 text-[10px] font-medium text-muted-foreground tabular-nums">
      {children}
    </kbd>
  );
}
