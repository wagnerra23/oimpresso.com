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
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Clock,
  CornerDownLeft,
  CreditCard,
  Download,
  Edit,
  Eye,
  Keyboard,
  Loader2,
  MoreVertical,
  Phone,
  Plus,
  Search,
  Star,
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
// Wave C-FE — 5 tabs cadastrais com autosave on blur (ADR 0179).
import IdentificacaoTab from './_drawer/IdentificacaoTab';
import ContatoTab from './_drawer/ContatoTab';
import EnderecoTab from './_drawer/EnderecoTab';
import ComercialTab from './_drawer/ComercialTab';
import ClassificacaoTab from './_drawer/ClassificacaoTab';
// Wave D/E/F — OSs wrapper, IA 4 cards, Auditoria timeline LGPD (ADR 0179).
import OssTab from './_drawer/OssTab';
import IATab from './_drawer/IATab';
import AuditoriaTab from './_drawer/AuditoriaTab';
// Wave G — listagem turbinada (avatar HSL hash + Pills semânticos).
import { Avatar as ClienteAvatar } from '@/Components/clientes/Avatar';
import { ActiveChip } from '@/Components/clientes/ActiveChip';
import { TipoPill, TagChip, FrescorPill, SaldoCell } from '@/Components/clientes/Pills';

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
  cpf_cnpj_masked?: string | null;
  contact_id: string | null;
  mobile: string | null;
  total_os: number;
  os_abertas: number;
  os_atrasadas: number;
  valor_aberto: number;
  status: 'late' | 'active' | 'idle';
  last_os_at: string | null;
  // Wave C — campos cadastrais opcionais propagados pelo ContactController::index
  // payload defer customers (Wave G expandirá full coverage). Cada Tab usa
  // fallback ?? '' / null nos campos missing.
  // Z-2.1: created_at pra subtitle drawer "cadastrado há Xd".
  created_at?: string | null;
  tipo?: 'PF' | 'PJ' | null;
  fantasia?: string | null;
  ie?: string | null;
  rg?: string | null;
  nascimento?: string | null;
  contato?: string | null;
  cargo?: string | null;
  tel?: string | null;
  tel2?: string | null;
  landline?: string | null;
  email?: string | null;
  site?: string | null;
  canal?: 'whatsapp' | 'email' | 'telefone' | 'presencial' | null;
  cep?: string | null;
  endereco?: string | null;
  address_line_1?: string | null;
  numero?: string | null;
  complemento?: string | null;
  bairro?: string | null;
  cidade?: string | null;
  city?: string | null;
  uf?: string | null;
  state?: string | null;
  limite_credito?: number | null;
  prazo_padrao_dias?: number | null;
  tabela_preco_padrao?: string | null;
  pgto_padrao?: string | null;
  obs_comercial?: string | null;
  segmento?: string | null;
  tags?: string[] | null;
  vip?: boolean | null;
  // Wave G — listagem turbinada (ADR 0179). Server-side em ContactController::buildClienteIndexCustomers.
  avatar_hash_seed?: string | null;     // string p/ HSL determinístico — default = name
  saldo_devedor?: number | null;        // valor_aberto - balance (positivo = cliente nos deve)
  last_purchase_at?: string | null;     // ISO; FrescorPill calcula 4 estados client-side
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

// ─── Wave G — Constantes filtros + hook useFavoritos ─────────────────────────

const TIPO_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'PF', label: 'Pessoa física' },
  { value: 'PJ', label: 'Pessoa jurídica' },
];

const UF_OPTIONS = [
  'AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
  'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO',
];

const TAG_OPTIONS = [
  'varejo','atacado','corporativo','evento','parceiro','agência','governo','vip','reincidente',
];

const STALE_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '15', label: '15 dias' },
  { value: '30', label: '30 dias' },
  { value: '90', label: '90 dias' },
  { value: '180', label: '6 meses' },
  { value: '365', label: '1 ano' },
];

const SALDO_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'devedor', label: 'Em débito' },
  { value: 'zerado', label: 'Sem saldo' },
];

const FAVORITOS_STORAGE_KEY = 'oimpresso.cliente.favoritos';

/**
 * Wave G — Hook Star pessoal localStorage.
 *
 * Per-USER per-BROWSER (Q1 wave-G decisão: client-only, sem sync server).
 * Coluna `favorito_users` JSON em Contact existe (Wave B migration) mas é
 * reservada pra futura sync server-side opcional. Por ora, localStorage cobre
 * 100% da UX da Larissa biz=4 ROTA LIVRE (1 usuário só).
 *
 * Edge: SSR / localStorage indisponível → Set vazio (não quebra render).
 */
function useFavoritos(): { favs: Set<number>; toggle: (id: number) => void; isFav: (id: number) => boolean } {
  const [favs, setFavs] = useState<Set<number>>(() => {
    if (typeof window === 'undefined') return new Set();
    try {
      const raw = window.localStorage.getItem(FAVORITOS_STORAGE_KEY);
      if (!raw) return new Set();
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return new Set();
      return new Set(parsed.filter((x) => typeof x === 'number'));
    } catch {
      return new Set();
    }
  });

  const toggle = useCallback((id: number) => {
    setFavs((cur) => {
      const next = new Set(cur);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      try {
        window.localStorage.setItem(FAVORITOS_STORAGE_KEY, JSON.stringify([...next]));
      } catch (_) { /* localStorage indisponível — silencia */ }
      return next;
    });
  }, []);

  const isFav = useCallback((id: number) => favs.has(id), [favs]);

  return { favs, toggle, isFav };
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

  // Wave G — 5 filtros adicionais (Status legacy já tratado em statusFilter).
  // Tipo PF/PJ · UF (27) · Tags (multi) · Sem compra há (5 ranges) · Saldo (devedor/zerado).
  const [tipoFilter, setTipoFilter] = useState<string>('');
  const [ufFilter, setUfFilter] = useState<string>('');
  const [tagsFilter, setTagsFilter] = useState<string[]>([]);
  const [staleFilter, setStaleFilter] = useState<string>('');
  const [saldoFilter, setSaldoFilter] = useState<string>('');

  // Wave G — Star pessoal localStorage (per-user per-browser).
  const { toggle: toggleFav, isFav } = useFavoritos();

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
  }, [statusFilter, search, sortKey, sortDir, perPage, tipoFilter, ufFilter, tagsFilter, staleFilter, saldoFilter]);

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
    // Wave G — filtros novos. Cada um é AND independente (espelha protótipo Cowork).
    if (tipoFilter) r = r.filter((x) => x.tipo === tipoFilter);
    if (ufFilter) r = r.filter((x) => (x.uf ?? x.state ?? '') === ufFilter);
    if (tagsFilter.length > 0) {
      // Multi-select: cliente deve ter TODAS as tags marcadas (AND, não OR).
      // Espelha protótipo Cowork (clientes-table.jsx: `fTags.every(t => c.tags.includes(t))`).
      r = r.filter((x) => {
        const xt = x.tags ?? [];
        return tagsFilter.every((t) => xt.includes(t));
      });
    }
    if (staleFilter) {
      const days = parseInt(staleFilter, 10);
      if (!Number.isNaN(days)) {
        const cutoff = Date.now() - days * 86400000;
        r = r.filter((x) => {
          // "Sem compra há X dias" — last_purchase_at deve ser anterior ao cutoff
          // OU não existir (cliente sem histórico = inclui no filtro stale).
          if (!x.last_purchase_at) return true;
          const t = new Date(x.last_purchase_at).getTime();
          if (Number.isNaN(t)) return true;
          return t < cutoff;
        });
      }
    }
    if (saldoFilter === 'devedor') {
      r = r.filter((x) => (x.saldo_devedor ?? x.valor_aberto ?? 0) > 0);
    } else if (saldoFilter === 'zerado') {
      r = r.filter((x) => {
        const s = x.saldo_devedor ?? x.valor_aberto ?? 0;
        return s <= 0;
      });
    }
    return r;
  }, [rows, statusFilter, search, tipoFilter, ufFilter, tagsFilter, staleFilter, saldoFilter]);

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

  // Wave G — pills array removido (substituído por 6 FilterDropdown — ver nav acima).
  // KPIs/contadores ficam no header (subtítulo inline) + count "X de Y" ao lado dos filtros.

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">Clientes</h1>
              {/* Wave G — subtítulo verbose substituído por contador inline (paridade Cowork). */}
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed tabular-nums">
                {(kpis?.total ?? 0).toLocaleString('pt-BR')} cadastrados
                {' · '}
                {(kpis?.com_os_aberta ?? 0).toLocaleString('pt-BR')} ativos
                {(kpis?.com_atraso ?? 0) > 0 && (
                  <>
                    {' · '}
                    <span className="text-rose-700 dark:text-rose-400">
                      {(kpis.com_atraso).toLocaleString('pt-BR')} com saldo
                    </span>
                  </>
                )}
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
              {/* Wave G — Exportar CSV (server-side stream, BOM UTF-8 Excel-BR). */}
              {props.permissions.view && (
                <Button asChild variant="outline">
                  <a href="/cliente/export" title="Baixar CSV de clientes (UTF-8)">
                    <Download className="mr-1.5 h-4 w-4" />
                    Exportar
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

          {/* Wave G — 6 dropdowns substituem 4 pills radio (paridade Cowork blueprint).
              Pills antigas conservam-se aria-label="Filtro de status" como semântica.
              Cada FilterDropdown reseta page=1 via useEffect [filtros, perPage]. */}
          <nav className="flex items-center gap-2 mt-6 flex-wrap" aria-label="Filtros de cliente">
            <FilterDropdown
              label="Status"
              value={statusFilter}
              onChange={(v) => setStatusFilter(v as StatusFilter)}
              options={[
                { value: 'active', label: 'Ativo (com OS aberta)' },
                { value: 'late', label: 'Atrasado' },
                { value: 'idle', label: 'Sem OS' },
              ]}
            />
            <FilterDropdown
              label="Tipo"
              value={tipoFilter}
              onChange={setTipoFilter}
              options={TIPO_OPTIONS}
            />
            <FilterDropdown
              label="UF"
              value={ufFilter}
              onChange={setUfFilter}
              options={UF_OPTIONS.map((u) => ({ value: u, label: u }))}
            />
            <FilterDropdown
              label="Tags"
              value={tagsFilter}
              onChange={(v) => setTagsFilter(v as string[])}
              options={TAG_OPTIONS.map((t) => ({ value: t, label: t }))}
              multi
            />
            <FilterDropdown
              label="Sem compra há"
              value={staleFilter}
              onChange={setStaleFilter}
              options={STALE_OPTIONS}
            />
            <FilterDropdown
              label="Saldo"
              value={saldoFilter}
              onChange={setSaldoFilter}
              options={SALDO_OPTIONS}
            />
            {/* Contagem inline "X de Y clientes" — Wave G UX paridade Cowork. */}
            <span className="text-xs text-muted-foreground tabular-nums ml-1">
              {filteredRows.length === rows.length
                ? `${rows.length.toLocaleString('pt-BR')} cliente${rows.length !== 1 ? 's' : ''}`
                : `${filteredRows.length.toLocaleString('pt-BR')} de ${rows.length.toLocaleString('pt-BR')} clientes`}
            </span>
          </nav>

          {/* Z-2.1 — ActiveChips removíveis (alinhado ao protótipo Cowork). */}
          {(tipoFilter || statusFilter || ufFilter || tagsFilter.length > 0 || staleFilter || saldoFilter) && (
            <div className="flex flex-wrap items-center gap-1.5 mt-3" aria-label="Filtros ativos">
              {tipoFilter && (
                <ActiveChip label="Tipo" value={tipoFilter} onRemove={() => setTipoFilter('')} />
              )}
              {statusFilter && (
                <ActiveChip label="Status" value={statusFilter} onRemove={() => setStatusFilter('')} />
              )}
              {ufFilter && (
                <ActiveChip label="UF" value={ufFilter} onRemove={() => setUfFilter('')} />
              )}
              {tagsFilter.length > 0 && (
                <ActiveChip label="Tags" value={tagsFilter} onRemove={() => setTagsFilter([])} />
              )}
              {staleFilter && (
                <ActiveChip label="Sem compra há" value={staleFilter} onRemove={() => setStaleFilter('')} />
              )}
              {saldoFilter && (
                <ActiveChip
                  label="Saldo"
                  value={saldoFilter}
                  variant={saldoFilter === 'devedor' ? 'danger' : 'default'}
                  onRemove={() => setSaldoFilter('')}
                />
              )}
              <button
                type="button"
                onClick={() => {
                  setTipoFilter('');
                  setStatusFilter('');
                  setUfFilter('');
                  setTagsFilter([]);
                  setStaleFilter('');
                  setSaldoFilter('');
                }}
                className="text-[10px] text-muted-foreground underline-offset-2 hover:underline hover:text-foreground ml-1"
              >
                limpar tudo
              </button>
            </div>
          )}
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
              {/* Wave G — Tabela turbinada (paridade Cowork blueprint score 9,4/10).
                  Colunas: Avatar HSL · Cliente+sub · Tipo · Documento · Cidade/UF ·
                  Frescor · Saldo (vermelho devedor) · OS · Tags+Star · Ações. */}
              <table className="w-full text-sm">
                <thead className="bg-muted/50">
                  <tr className="border-b border-border">
                    <Th className="w-10">&nbsp;</Th>
                    <SortableTh sortKey="name" current={sortKey} dir={sortDir} onSort={handleSort}>Cliente</SortableTh>
                    <Th className="w-14">Tipo</Th>
                    <Th className="w-32">Documento</Th>
                    <Th className="w-28">Cidade/UF</Th>
                    <SortableTh sortKey="last_os_at" current={sortKey} dir={sortDir} onSort={handleSort} className="w-36">Frescor</SortableTh>
                    <SortableTh sortKey="valor_aberto" current={sortKey} dir={sortDir} onSort={handleSort} align="right" className="w-28">Saldo</SortableTh>
                    <SortableTh sortKey="total_os" current={sortKey} dir={sortDir} onSort={handleSort} align="right" className="w-14">OS</SortableTh>
                    <Th>Tags</Th>
                    <Th className="w-10">&nbsp;</Th>
                    <Th className="w-10 text-right pr-4">&nbsp;</Th>
                  </tr>
                </thead>
                <tbody>
                  {filteredRows.length === 0 ? (
                    <tr>
                      <td colSpan={11} className="text-center py-12 text-muted-foreground text-xs">
                        Nenhum cliente encontrado nesse filtro.
                      </td>
                    </tr>
                  ) : (
                    filteredRows.map((row, idx) => {
                      const isOpen = openContactId === row.id;
                      const isFocused = focusedIndex === idx;
                      const tags = row.tags ?? [];
                      const tagsVisible = tags.slice(0, 2);
                      const tagsExtra = Math.max(0, tags.length - 2);
                      const isFavorito = isFav(row.id);
                      // Saldo p/ exibir: prefere saldo_devedor (Wave G computed) e cai p/ valor_aberto legacy.
                      const saldoExibir = row.saldo_devedor ?? row.valor_aberto ?? 0;
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
                          <td className="px-4 py-2.5">
                            {/* Wave G — Avatar HSL hash determinístico (Components/clientes/Avatar.tsx). */}
                            <ClienteAvatar
                              name={row.name}
                              size={32}
                              seed={row.avatar_hash_seed ?? row.name}
                            />
                          </td>
                          <td className="px-4 py-2.5">
                            <div className="text-foreground font-medium leading-tight flex items-center gap-1.5">
                              {row.name}
                              {row.vip && (
                                <span
                                  className="inline-flex items-center rounded bg-yellow-100 px-1 py-0 text-[9px] font-semibold text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300"
                                  title="Cliente VIP"
                                >
                                  VIP
                                </span>
                              )}
                            </div>
                            {/* Sub-nome: fantasia (PJ) ou telefone (contato rápido). */}
                            {(row.fantasia || row.mobile) && (
                              <div className="text-[11px] text-muted-foreground/70 leading-tight mt-0.5 flex items-center gap-1">
                                {row.fantasia ? (
                                  <span className="truncate">{row.fantasia}</span>
                                ) : (
                                  <>
                                    <Phone size={9} className="opacity-60" />
                                    <span className="tabular-nums">{row.mobile}</span>
                                  </>
                                )}
                              </div>
                            )}
                          </td>
                          <td className="px-4 py-2.5">
                            <TipoPill tipo={row.tipo ?? null} />
                          </td>
                          <td className="px-4 py-2.5">
                            <span className="text-xs text-muted-foreground tabular-nums">
                              {row.tax_number_masked ?? '—'}
                            </span>
                          </td>
                          <td className="px-4 py-2.5">
                            {(row.cidade ?? row.city) ? (
                              <span className="text-xs text-foreground">
                                {row.cidade ?? row.city}
                                {(row.uf ?? row.state) && (
                                  <span className="text-muted-foreground">/{row.uf ?? row.state}</span>
                                )}
                              </span>
                            ) : (
                              <span className="text-xs text-muted-foreground/50">—</span>
                            )}
                          </td>
                          <td className="px-4 py-2.5">
                            <FrescorPill lastPurchaseAt={row.last_purchase_at ?? row.last_os_at ?? null} />
                          </td>
                          <td className="px-4 py-2.5 text-right">
                            <SaldoCell valor={saldoExibir} />
                          </td>
                          <td className="px-4 py-2.5 text-right tabular-nums text-foreground">{row.total_os}</td>
                          <td className="px-4 py-2.5">
                            {tags.length === 0 ? (
                              <span className="text-xs text-muted-foreground/50">—</span>
                            ) : (
                              <div className="flex items-center gap-1 flex-wrap">
                                {tagsVisible.map((t) => (
                                  <TagChip key={t} tag={t} />
                                ))}
                                {tagsExtra > 0 && (
                                  <span
                                    className="text-[10px] text-muted-foreground tabular-nums"
                                    title={tags.slice(2).join(', ')}
                                  >
                                    +{tagsExtra}
                                  </span>
                                )}
                              </div>
                            )}
                          </td>
                          {/* Star pessoal localStorage — toggle não abre drawer. */}
                          <td
                            className="px-2 py-2.5"
                            onClick={(e) => e.stopPropagation()}
                          >
                            <button
                              type="button"
                              onClick={() => toggleFav(row.id)}
                              aria-label={isFavorito ? 'Remover dos favoritos' : 'Marcar como favorito'}
                              aria-pressed={isFavorito}
                              title={isFavorito ? 'Favorito (clique pra remover)' : 'Marcar como favorito'}
                              className={
                                'inline-flex h-7 w-7 items-center justify-center rounded transition-colors ' +
                                (isFavorito
                                  ? 'text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                                  : 'text-muted-foreground/40 hover:text-amber-500 hover:bg-muted')
                              }
                            >
                              <Star
                                size={14}
                                fill={isFavorito ? 'currentColor' : 'none'}
                                strokeWidth={2}
                              />
                            </button>
                          </td>
                          <td className="px-2 py-2.5 text-right pr-4" onClick={(e) => e.stopPropagation()}>
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

// ─── Wave G — FilterDropdown ──────────────────────────────────────────────────
// Componente filtro dropdown com suporte single/multi-select + Limpar option.
// Espelha protótipo Cowork (clientes-listagem.jsx::FilterDropdown).
// Mouse-out fecha popover; tecla Esc fecha; click fora fecha.

interface FilterDropdownOption {
  value: string;
  label: string;
}

interface FilterDropdownProps {
  label: string;
  value: string | string[];
  options: FilterDropdownOption[];
  onChange: (v: string | string[]) => void;
  multi?: boolean;
}

function FilterDropdown({ label, value, options, onChange, multi = false }: FilterDropdownProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const arrayValue = multi ? (Array.isArray(value) ? value : []) : [];
  const singleValue = !multi ? (typeof value === 'string' ? value : '') : '';
  const hasSel = multi ? arrayValue.length > 0 : !!singleValue;

  // Label exibido no botão.
  const displayLabel = (() => {
    if (multi) {
      if (arrayValue.length === 0) return label;
      if (arrayValue.length === 1) return `${label}: ${arrayValue[0]}`;
      return `${label} (${arrayValue.length})`;
    }
    if (!singleValue) return label;
    const opt = options.find((o) => o.value === singleValue);
    return opt ? `${label}: ${opt.label}` : `${label}: ${singleValue}`;
  })();

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className={
          'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ' +
          (hasSel
            ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
            : 'border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground')
        }
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        <span>{displayLabel}</span>
        <ChevronDown size={11} className="opacity-70" />
      </button>
      {open && (
        <div
          className="absolute z-50 top-full mt-1 left-0 min-w-[180px] max-h-[320px] overflow-y-auto rounded-md border border-border bg-background shadow-lg p-1"
          role="listbox"
        >
          {!multi && singleValue && (
            <button
              type="button"
              onClick={() => {
                onChange('');
                setOpen(false);
              }}
              className="w-full text-left rounded px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted border-b border-border mb-0.5"
            >
              Limpar
            </button>
          )}
          {multi && arrayValue.length > 0 && (
            <button
              type="button"
              onClick={() => onChange([])}
              className="w-full text-left rounded px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted border-b border-border mb-0.5"
            >
              Limpar tudo
            </button>
          )}
          {options.map((opt) => {
            const isOn = multi ? arrayValue.includes(opt.value) : singleValue === opt.value;
            return (
              <button
                key={opt.value}
                type="button"
                role="option"
                aria-selected={isOn}
                onClick={() => {
                  if (multi) {
                    onChange(isOn ? arrayValue.filter((x) => x !== opt.value) : [...arrayValue, opt.value]);
                  } else {
                    onChange(isOn ? '' : opt.value);
                    setOpen(false);
                  }
                }}
                className={
                  'w-full text-left rounded px-2 py-1.5 text-xs flex items-center gap-2 transition-colors ' +
                  (isOn
                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                    : 'text-foreground hover:bg-muted')
                }
              >
                {multi && (
                  <span
                    className={
                      'inline-flex h-3.5 w-3.5 items-center justify-center rounded border ' +
                      (isOn
                        ? 'border-blue-500 bg-blue-500 text-white'
                        : 'border-border bg-background')
                    }
                  >
                    {isOn && <Check size={9} strokeWidth={3} />}
                  </span>
                )}
                <span className="flex-1">{opt.label}</span>
                {!multi && isOn && <Check size={11} className="text-blue-600" />}
              </button>
            );
          })}
        </div>
      )}
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

// ─── Wave B (ADR 0179) ─────────────────────────────────────────────────────
// ClienteSheet: drawer 480 → 760 + 8 tabs cadastrais (Identificacao, Contato,
// Endereco, Comercial, Classificacao, OSs, IA, Auditoria).
//
// Tabs cadastrais (1-5): importadas de `./_drawer/*Tab.tsx` (Agent C-FE Wave C
// produz). Placeholder enquanto Wave C nao mergeia.
// Tabs operacionais (6 OSs, 7 IA, 8 Auditoria): placeholders pra Wave D/E/F.
//
// Header rico (Cowork blueprint score 9,4/10):
//   - Avatar grande (TODO Wave G: HSL hash deterministico)
//   - Toggle PF/PJ + nome + "cadastrado ha Xd"
//   - Badge Ativo/Inativo (status semantico Cockpit V2)
//   - Botoes "Imprimir ficha" (window.print) + "Falar com Copiloto -> /ia/chat"
//
// KB-9.75 atalhos preservados (linhas 174-280, 892+) -- ClienteSheet nao
// toca em key handlers globais; o Sheet Radix ja faz Esc-to-close nativo.

type DrawerTab =
  | 'identificacao'
  | 'contato'
  | 'endereco'
  | 'comercial'
  | 'classificacao'
  | 'oss'
  | 'ia'
  | 'auditoria';

const DRAWER_TABS: Array<{ key: DrawerTab; label: string }> = [
  { key: 'identificacao', label: 'Identificação' },
  { key: 'contato',       label: 'Contato' },
  { key: 'endereco',      label: 'Endereço' },
  { key: 'comercial',     label: 'Comercial' },
  { key: 'classificacao', label: 'Classificação' },
  { key: 'oss',           label: 'OSs' },
  { key: 'ia',            label: 'IA' },
  { key: 'auditoria',     label: 'Auditoria' },
];

function relativeDate(iso: string | null | undefined): string {
  // "cadastrado ha Xd" -- usa created_at do contact. Fallback "—" se ausente.
  // Wave G troca por relDate util (Lib/relDate.ts).
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  const diffMs = Date.now() - d.getTime();
  const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  if (days < 1) return 'hoje';
  if (days < 30) return `há ${days}d`;
  const months = Math.floor(days / 30);
  if (months < 12) return `há ${months}m`;
  const years = Math.floor(months / 12);
  return `há ${years}a`;
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
  const [activeTab, setActiveTab] = useState<DrawerTab>('identificacao');
  // Toggle PF/PJ -- estado local. Wave C persiste via autosave on blur PATCH.
  // Placeholder no payload ClienteRow nao tem `tipo` ainda (Migration Wave B
  // adiciona; ContactController nao pro frontend ainda nesta wave).
  const [tipo, setTipo] = useState<'PF' | 'PJ'>('PJ');

  // Reset tab ao abrir contact diferente. Drawer abre default em "Identificação".
  useEffect(() => {
    if (open && contactId) {
      setActiveTab('identificacao');
    }
  }, [open, contactId]);

  // Z-2.1: Atalho 1-8 troca tab quando drawer aberto. Alinhado ao protótipo Cowork.
  // Pulado quando user está digitando em input/textarea (form fields cadastrais).
  useEffect(() => {
    if (!open) return;
    const TAB_ORDER: DrawerTab[] = [
      'identificacao', 'contato', 'endereco', 'comercial',
      'classificacao', 'oss', 'ia', 'auditoria',
    ];
    const onKey = (e: KeyboardEvent) => {
      const target = e.target;
      if (target instanceof HTMLElement) {
        const tag = target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (target.isContentEditable) return;
      }
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      const n = parseInt(e.key, 10);
      if (!Number.isNaN(n) && n >= 1 && n <= 8) {
        e.preventDefault();
        setActiveTab(TAB_ORDER[n - 1]);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        // 760px conforme ADR 0179 + Cowork blueprint. Larissa biz=4 1280x1024:
        // 760 + AppShellV2 sidebar 240 + main padding ~= 1024px (cabe sem
        // scroll horizontal). Pest Wave B asserta `w-[760px]` no HTML.
        className="w-[760px] sm:max-w-[760px] p-0 flex flex-col"
      >
        {/* ─── Header rico ─────────────────────────────────────────────── */}
        <SheetHeader className="border-b border-border px-6 py-4 space-y-3">
          <div className="flex items-start gap-4">
            {/* Z-2.1: drawer header avatar 40px round gradient oklch (alinhado ao protótipo Cowork). */}
            <ClienteAvatar
              name={contact?.name ?? '?'}
              seed={contact?.avatar_hash_seed ?? String(contact?.id ?? 0)}
              size={40}
              shape="circle"
            />

            <div className="flex-1 min-w-0">
              {/* Toggle PF/PJ -- radio group simples. Wave C planta PATCH on blur. */}
              <div
                className="inline-flex items-center gap-1 rounded-md bg-muted/60 p-0.5 text-xs"
                role="radiogroup"
                aria-label="Tipo de pessoa"
              >
                {(['PF', 'PJ'] as const).map((t) => (
                  <button
                    key={t}
                    type="button"
                    role="radio"
                    aria-checked={tipo === t}
                    onClick={() => setTipo(t)}
                    className={
                      'rounded px-2 py-0.5 transition-colors ' +
                      (tipo === t
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground')
                    }
                  >
                    {t === 'PF' ? 'Pessoa física' : 'Pessoa jurídica'}
                  </button>
                ))}
              </div>

              <SheetTitle className="text-lg font-semibold leading-tight mt-1.5">
                {contact?.name ?? 'Cliente'}
              </SheetTitle>

              <SheetDescription className="mt-0.5 text-xs">
                {tipo === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física'}
                {' · cadastrado '}
                {/* Z-2.1: ContactController::index payload inclui created_at. */}
                {relativeDate(contact?.created_at ?? null)}
              </SheetDescription>
            </div>

            {/* Badge status -- "Ativo" se contact.status !== 'idle'; placeholder.
                Wave G adiciona Inativo/Bloqueado via segmentStatus campo novo. */}
            <span
              className={
                'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ' +
                (contact?.status === 'idle'
                  ? 'bg-stone-50 text-stone-700 border-stone-200 dark:bg-stone-950/40 dark:text-stone-300'
                  : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300')
              }
            >
              {contact?.status === 'idle' ? 'Sem OS' : 'Ativo'}
            </span>
          </div>

          {/* Botoes acao -- Imprimir + Copiloto. */}
          <div className="flex items-center gap-2 pt-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                // Placeholder Wave B -- window.print() abre dialogo print do navegador.
                // Wave G implementa CSS @media print + layout dedicado.
                if (typeof window !== 'undefined') window.print();
              }}
              className="text-xs h-7"
            >
              Imprimir ficha
            </Button>
            <Button asChild size="sm" className="text-xs h-7">
              <a
                href={contact ? `/ia/chat?context=cliente:${contact.id}` : '#'}
                title="Abre o Copiloto (Jana) com contexto deste cliente"
              >
                Falar com Copiloto →
              </a>
            </Button>
          </div>
        </SheetHeader>

        {/* ─── 8 Tabs (pattern Show.tsx canon -- border-b-2 + overflow-x-auto) */}
        <nav
          className="border-b border-border px-6 flex gap-1 overflow-x-auto"
          role="tablist"
          aria-label="Tabs do cliente"
        >
          {DRAWER_TABS.map((t) => {
            const isActive = activeTab === t.key;
            return (
              <button
                key={t.key}
                type="button"
                role="tab"
                aria-selected={isActive}
                onClick={() => setActiveTab(t.key)}
                className={
                  'inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px whitespace-nowrap ' +
                  (isActive
                    ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground')
                }
              >
                {t.label}
              </button>
            );
          })}
        </nav>

        {/* ─── Tab content scrollable ──────────────────────────────────── */}
        <div
          role="tabpanel"
          className="flex-1 overflow-y-auto px-6 py-5"
          data-testid="cliente-drawer-tabpanel"
        >
          {contact && (
            <>
              {/* Tabs cadastrais 1-5 — Wave C-FE plugadas com autosave on blur. */}
              {activeTab === 'identificacao' && (
                <IdentificacaoTab contact={contact} />
              )}
              {activeTab === 'contato' && (
                <ContatoTab contact={contact} />
              )}
              {activeTab === 'endereco' && (
                <EnderecoTab contact={contact} />
              )}
              {activeTab === 'comercial' && (
                <ComercialTab contact={contact} />
              )}
              {activeTab === 'classificacao' && (
                <ClassificacaoTab contact={contact} />
              )}
              {activeTab === 'oss' && (
                <OssTab contact={{ id: contact.id, name: contact.name }} />
              )}
              {activeTab === 'ia' && (
                <IATab contact={{ id: contact.id, name: contact.name }} />
              )}
              {activeTab === 'auditoria' && (
                <AuditoriaTab contact={{ id: contact.id }} />
              )}
            </>
          )}
        </div>

        {/* ─── Footer ──────────────────────────────────────────────────── */}
        <div className="border-t border-border px-6 py-3 flex items-center justify-between">
          <div className="text-xs text-muted-foreground">
            {/* Placeholder pendencias -- Wave G calcula contagem real. */}
            <span className="inline-flex items-center gap-1.5">
              <AlertTriangle size={12} className="text-amber-500" />
              1 pendência
            </span>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => onOpenChange(false)}
              className="text-xs h-7"
            >
              Cancelar
            </Button>
            <Button
              size="sm"
              onClick={() => {
                // Placeholder Wave B -- Wave C dispara PATCH autosave on blur.
                // router.reload only:['contact'] refresca payload sem rerender total.
                // TODO Wave C: substituir por toast "Salvo" + reload partial.
                onOpenChange(false);
              }}
              className="text-xs h-7"
            >
              Salvar
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

/**
 * Placeholder visual para tabs Wave C/D/E/F nao implementadas ainda.
 * Wave C/D/E/F substituem por componente real importado de `./_drawer/*Tab`.
 */
function DrawerTabPlaceholder({
  title,
  body,
  wave,
}: {
  title: string;
  body: string;
  wave: 'C' | 'D' | 'E' | 'F';
}) {
  return (
    <div className="rounded-lg border border-dashed border-border bg-muted/20 p-6">
      <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
        Wave {wave} pendente
      </div>
      <h3 className="text-base font-semibold text-foreground mt-1.5">{title}</h3>
      <p className="text-sm text-muted-foreground mt-2 leading-relaxed">{body}</p>
    </div>
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
