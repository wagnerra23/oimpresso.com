// W1-B3 Cliente/Index — listagem de clientes Inertia/React (MWART F3).
// Refs:
//   - ADR 0104 (MWART canônico) · ADR 0107 (visual gate) · ADR 0149 (pattern reuse)
//   - ADR 0093 (multi-tenant Tier 0) · ADR 0110 (Cockpit V2)
//   - Blueprint Cowork: prototipo-ui/prototipos/clientes/cowork-app.jsx
//   - Reuse pattern de Pages/Sells/Index.tsx (gold-standard W1-A)
// Backend: ContactController::index() — Inertia::render dual via config('mwart.cliente_index.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  Briefcase,
  Car,
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
  Layers,
  List,
  Loader2,
  MoreVertical,
  Paperclip,
  Phone,
  Plus,
  Search,
  Sparkles,
  Star,
  Trash2,
  Truck,
  Upload,
  UserCheck,
  Users,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
// Wave C-FE — 5 tabs cadastrais com autosave on blur (ADR 0179).
import IdentificacaoTab from './_drawer/IdentificacaoTab';
import ContatoTab from './_drawer/ContatoTab';
import EnderecoTab from './_drawer/EnderecoTab';
import ComercialTab, { type PriceGroupOption } from './_drawer/ComercialTab';
import ClassificacaoTab from './_drawer/ClassificacaoTab';
// Wave D/E/F — OSs wrapper, IA 4 cards, Auditoria timeline LGPD (ADR 0179).
import OssTab, { type OssSubTabKey } from './_drawer/OssTab';
import IATab from './_drawer/IATab';
// Wagner 2026-05-27 iteracao 2: Placas promovido pra tab principal (botao header).
import PlacasMainTab from './_drawer/PlacasMainTab';
// Wave G — listagem turbinada (avatar HSL hash + Pills semânticos).
import { Avatar as ClienteAvatar } from './_components/Avatar';
import { ActiveChip } from './_components/ActiveChip';
import { TipoPill, TagChip, FrescorPill, SaldoCell } from './_components/Pills';
// PTDP Onda 2 — KPI strip clicável (5 cards-filtro · substitui 4 KpiCard estáticos).
import {
  KpiStripClickable,
  type KpiCardDef,
  type KpiKey,
} from './_components/KpiStripClickable';

interface ClienteKpis {
  total: number;
  com_os_aberta: number;
  com_atraso: number;
  // Onda 3 — counts reais server-side (antes estimados client-side sobre a página).
  vips: number;
  sem_compra_90d: number;
  novos_mes: number;
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
  // Tabela de preço REAL (FK customer_groups). Substitui o fake tabela_preco_padrao.
  customer_group_id?: number | null;
  tabela_preco_padrao?: string | null;
  pgto_padrao?: string | null;
  // Mensagem exibida como alerta ao vendedor no POS (migrado do Delphi).
  mensagem_venda?: string | null;
  obs_comercial?: string | null;
  segmento?: string | null;
  tags?: string[] | null;
  vip?: boolean | null;
  // Wave G — listagem turbinada (ADR 0179). Server-side em ContactController::buildClienteIndexCustomers.
  avatar_hash_seed?: string | null;     // string p/ HSL determinístico — default = name
  saldo_devedor?: number | null;        // valor_aberto - balance (positivo = cliente nos deve)
  last_purchase_at?: string | null;     // ISO; FrescorPill calcula 4 estados client-side
  // ADR 0188 Onda 4 — flags multi-papel (Drawer seção "Papéis").
  is_customer?: boolean | null;
  is_supplier?: boolean | null;
  is_employee?: boolean | null;
  is_representative?: boolean | null;
  // ADR 0195 Bucket A — flag `bloqueado` (separada de `status` enum derivado).
  // Sells/Financeiro consultam pra impedir checkout/cobrança mesmo quando ativo.
  bloqueado?: boolean | null;
  // Consumidor/fornecedor padrão (walk-in). destroy() protege server-side;
  // front esconde "Excluir" pra ele. Wagner 2026-06-08.
  is_default?: boolean | null;
  // Wagner 2026-05-27 iteracao 2 — contador veiculos pro botao header drawer.
  // Lazy: count so existe se modulo OficinaAuto instalado (gate hasTable).
  vehicles_count?: number | null;
  // Wagner 2026-06-01 — contador de anexos (document-notes com media) pro chip
  // "📎 N anexos" no header do drawer. Count server-side em ContactController.
  documents_count?: number | null;
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

/** ADR 0188 + ADR 0246 — Slot 2 PT-01 multi-type. 6 valores aceitos. Default `'customer'`. */
export type ContactRoleType = 'customer' | 'supplier' | 'employee' | 'representative' | 'other' | 'all';

/**
 * ADR 0188 — Slot 2 PT-01 ModuleTopNav (sub-tabs ghost).
 *
 * Title H1 + label "Novo X" + count noun mudam por papel. `all` é leitura agregada
 * (sem CTA "Novo") · papéis específicos têm CTA pra criar com pre-fill correto.
 *
 * Pegadinha: `?type=X` no `<a href>` casa com `Route::get('/cliente')` que merge
 * em `request()->query` antes de chamar `ContactController::index()` (routes/web.php).
 */
const SLOT2_TABS: Array<{
  key: ContactRoleType;
  label: string;        // nome completo (mobile + screen reader title)
  shortLabel: string;   // ADR 0189 v3.1: nome encurtado p/ desktop md+
  href: string;
  Icon: typeof Users;
}> = [
  { key: 'all',            label: 'Todos',          shortLabel: 'Todos',    href: '/cliente?type=all',            Icon: List },
  { key: 'customer',       label: 'Clientes',       shortLabel: 'Clientes', href: '/cliente?type=customer',       Icon: Users },
  { key: 'supplier',       label: 'Fornecedores',   shortLabel: 'Fornec.',  href: '/cliente?type=supplier',       Icon: Truck },
  { key: 'employee',       label: 'Funcionários',   shortLabel: 'Equipe',   href: '/cliente?type=employee',       Icon: Briefcase },
  { key: 'representative', label: 'Representantes', shortLabel: 'Repr.',    href: '/cliente?type=representative', Icon: UserCheck },
  // ADR 0246 (2026-06-03) — categoria "Outros" pra cadastros sem CPF/CNPJ
  // obrigatório (prospects, leads, contatos avulsos, migração legacy WR Comercial).
  { key: 'other',          label: 'Outros',         shortLabel: 'Outros',   href: '/cliente?type=other',          Icon: Layers },
];

const ROLE_TITLE: Record<ContactRoleType, { title: string; singular: string; collective: string }> = {
  customer:       { title: 'Clientes',       singular: 'cliente',       collective: 'cadastrados' },
  supplier:       { title: 'Fornecedores',   singular: 'fornecedor',    collective: 'cadastrados' },
  employee:       { title: 'Funcionários',   singular: 'funcionário',   collective: 'cadastrados' },
  representative: { title: 'Representantes', singular: 'representante', collective: 'cadastrados' },
  // ADR 0246 — singular "outros" minúsculo (botão "+ Novo outros" mostra label canon).
  other:          { title: 'Outros',         singular: 'outros',        collective: 'cadastrados' },
  all:            { title: 'Contatos',       singular: 'contato',       collective: 'cadastros'   },
};

export interface ClienteIndexPageProps {
  /** ADR 0188 — papel ativo (vindo do `?type=X` URL · backend valida whitelist). */
  activeType?: ContactRoleType;
  kpis: ClienteKpis;
  /**
   * ADR 0189 v3.1 + LEARNINGS AP18 (2026-05-25): counter por papel canon do
   * subnav Zona C. Backend devolve via deferred prop — `undefined` enquanto
   * carrega. NUNCA recalcular via `rows.filter(r => r.type === X)` — rows
   * traz só tipo ativo server-side filtered → outros retornariam 0 (bug).
   */
  tab_counts?: Record<ContactRoleType, number>;
  customers: {
    data: ClienteRow[];
    meta: ListMeta;
  };
  permissions: {
    create: boolean;
    view: boolean;
    import: boolean;
    /** customer.delete || supplier.delete — gate do "Excluir" no menu ⋮. */
    delete: boolean;
  };
  /**
   * Tabelas de preço REAIS do business (customer_groups id+name). Server-side
   * em ContactController::index (scope business_id). Alimenta o dropdown de
   * tabela de preço no drawer ComercialTab — substitui o enum fake hardcoded.
   */
  priceGroups?: PriceGroupOption[];
  /** Wagner 2026-05-27 — gate sub-tab "Placas" do OssTab drawer. true se modulo
   *  OficinaAuto ativo no business. Default false (biz=4 Larissa vestuario). */
  oficinaauto_enabled?: boolean;
}

type StatusFilter = '' | 'late' | 'active' | 'idle';
type SortKey = 'recent' | 'name' | 'total_os' | 'valor_aberto' | 'last_os_at';
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
  late: 'bg-destructive-soft text-destructive-fg border-destructive/20',
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

  // Fix 2026-05-26 — search server-side via router.reload. Antes filtrava
  // `rows` em memória (página paginada do backend) → busca só achava entre
  // os 25-50 da página atual. Agora `q` vai pro Controller, bate LIKE em
  // name/tax_number/mobile/contact_id/fantasia (ADR 0093 multi-tenant scope
  // já no Builder). Debounce 300ms já existente acima evita request por
  // keystroke. Skip primeiro render (mount) pra não disparar reload vazio.
  const isSearchMounted = useRef(false);
  useEffect(() => {
    if (!isSearchMounted.current) {
      isSearchMounted.current = true;
      return;
    }
    router.reload({
      only: ['customers'],
      data: { q: search || undefined, page: 1, per_page: perPageRef.current, sort: sortRef.current.key, dir: sortRef.current.dir },
      preserveScroll: true,
      preserveState: true,
    });
  }, [search]);

  const [page, setPage] = useState(1);
  // Default 50 = default do backend (ContactController per_page). Antes era 25 e
  // o placar mostrava "/ N" do servidor (50) divergindo do dropdown — bug Wagner.
  const [perPage, setPerPage] = useState(50);
  // Lê o perPage atual dentro do effect de busca sem virar dep (evita reload duplo
  // ao trocar página + mantém o tamanho de página ao buscar).
  const perPageRef = useRef(perPage);
  perPageRef.current = perPage;
  // Default 'recent' (id desc) — job-aligned, sem lixo alfabético de símbolo no topo.
  const [sortKey, setSortKey] = useState<SortKey>('recent');
  const [sortDir, setSortDir] = useState<SortDir>('desc');
  // Lê o sort atual nos reloads sem virar dep (igual perPageRef).
  const sortRef = useRef({ key: sortKey, dir: sortDir });
  sortRef.current = { key: sortKey, dir: sortDir };
  const [openContactId, setOpenContactId] = useState<number | null>(null);
  // 2026-06-08 (Wagner "arrumar botões da contacts") — aba-alvo do drawer ao abrir.
  // Consolidação ADR 0179: o menu ⋮ da linha não manda mais pra Blade legacy
  // (/contacts/{id}, /contacts/{id}/edit, /contacts/ledger). Tudo abre o drawer
  // 760 — "Editar" cai na Identificação, "Extrato" cai em Operações›Extrato.
  const [openTab, setOpenTab] = useState<DrawerTab>('identificacao');
  // Entrada única do drawer: garante reset da aba-alvo em cada abertura (sem
  // herdar a aba da última ação — ex: abrir via Extrato e depois clicar a linha).
  const openDrawer = useCallback((id: number, tab: DrawerTab = 'identificacao') => {
    setOpenTab(tab);
    setOpenContactId(id);
  }, []);

  // 2026-06-08 (Wagner "exclusão de contato pela tela") — soft delete via
  // DELETE /contacts/{id} (ContactController::destroy). O backend é a fonte de
  // verdade das travas: escopo business_id (Tier 0 ADR 0093), bloqueia se há
  // qualquer transação/venda/OS, protege is_default e grava ActivityLog
  // (LGPD). Aqui só confirmamos e tratamos o JSON {success,msg}.
  const [deleteTarget, setDeleteTarget] = useState<ClienteRow | null>(null);
  const [deleting, setDeleting] = useState(false);
  const confirmDelete = useCallback(async () => {
    if (!deleteTarget || deleting) return;
    setDeleting(true);
    try {
      const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const r = await fetch(`/contacts/${deleteTarget.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const json = await r.json().catch(() => ({ success: false }));
      if (r.ok && json?.success) {
        toast.success(json.msg || 'Contato excluído.');
        // Se o drawer do excluído estiver aberto, fecha. Recarrega a lista.
        if (openContactId === deleteTarget.id) setOpenContactId(null);
        setDeleteTarget(null);
        router.reload({ only: ['customers', 'kpis', 'tab_counts'], preserveScroll: true });
      } else {
        // Caso típico: contato com vendas/OS — backend devolve success:false +
        // msg "você não pode excluir este contato". Mantém o dialog aberto.
        toast.error(json?.msg || 'Não foi possível excluir este contato.');
      }
    } catch {
      toast.error('Falha de rede ao excluir. Verifique a conexão e tente de novo.');
    } finally {
      setDeleting(false);
    }
  }, [deleteTarget, deleting, openContactId]);

  // ADR 0179 evolução 2026-05-25 — "Novo cliente" via drawer 760 modo 'novo'.
  // State declarado aqui; callback (que depende de activeType) está DEPOIS
  // da declaração de activeType pra evitar Temporal Dead Zone (TDZ).
  const [creatingDraft, setCreatingDraft] = useState(false);
  // Draft placeholder otimista — alimenta ClienteSheet com row sintética pro
  // drawer renderizar tabs ANTES do router.reload trazer a row real do backend.
  // Sem isso, ClienteSheet faz rows.find(id) que retorna null pra draft recém
  // criado fora da página paginada da listagem → tabs vazias.
  const [draftContact, setDraftContact] = useState<ClienteRow | null>(null);

  // Wave G — 5 filtros adicionais (Status legacy já tratado em statusFilter).
  // Tipo PF/PJ · UF (27) · Tags (multi) · Sem compra há (5 ranges) · Saldo (devedor/zerado).
  const [tipoFilter, setTipoFilter] = useState<string>('');
  const [ufFilter, setUfFilter] = useState<string>('');
  const [tagsFilter, setTagsFilter] = useState<string[]>([]);
  const [staleFilter, setStaleFilter] = useState<string>('');
  const [saldoFilter, setSaldoFilter] = useState<string>('');

  // PTDP Onda 2 — KPI strip clicável (5 cards-filtro).
  // Filtros adicionais (não cobertos pelos 6 FilterDropdown): novos do mês.
  const [activeKpiKey, setActiveKpiKey] = useState<KpiKey | null>(null);
  const [recentMonthFilter, setRecentMonthFilter] = useState(false);

  // PTDP Onda 2 — handler aplicar/desaplicar KPI card.
  const applyKpiCard = useCallback((card: KpiCardDef | null) => {
    if (!card) {
      setActiveKpiKey(null);
      setStatusFilter('');
      setTagsFilter([]);
      setStaleFilter('');
      setSaldoFilter('');
      setRecentMonthFilter(false);
      return;
    }
    setActiveKpiKey(card.key);
    setStatusFilter((card.filters.statusFilter ?? '') as StatusFilter);
    setTagsFilter(card.filters.tagsFilter ?? []);
    setStaleFilter(card.filters.staleFilter ?? '');
    setSaldoFilter(card.filters.saldoFilter ?? '');
    setRecentMonthFilter(card.filters.recentMonthFilter ?? false);
  }, []);

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
  }, [statusFilter, search, sortKey, sortDir, perPage, tipoFilter, ufFilter, tagsFilter, staleFilter, saldoFilter, recentMonthFilter]);

  const rows = props.customers?.data ?? [];
  const meta = props.customers?.meta ?? null;
  const kpis = props.kpis;
  // ADR 0188 — Slot 2 PT-01 multi-type. Backend valida whitelist + default 'customer'.
  const activeType: ContactRoleType = props.activeType ?? 'customer';

  // ADR 0179 evolução 2026-05-25 — "Novo cliente" via drawer 760 modo 'novo'.
  // POST /cliente/draft cria placeholder vazio + retorna id, drawer abre sobre ele,
  // autosave on blur preenche conforme user digita. Substitui /contacts/create Blade
  // (que tinha bugs Inertia handler + validation errors invisíveis na Blade).
  // Declarado AQUI (após activeType) pra evitar Temporal Dead Zone — hotfix TDZ.
  const createNewContactDraft = useCallback(async () => {
    if (creatingDraft) return;
    setCreatingDraft(true);
    try {
      const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const draftType = activeType === 'fornecedor' ? 'supplier' : 'customer';
      const r = await fetch('/cliente/draft', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ type: draftType }),
      });
      if (!r.ok) {
         
        console.error('[ClienteIndex] POST /cliente/draft falhou', r.status);
        alert('Não foi possível criar novo cliente. Tente de novo ou contate suporte.');
        return;
      }
      const json = await r.json();
      const newId = Number(json?.id ?? 0);
      if (newId > 0) {
        // Hotfix 2026-05-25 v2 — bug Wagner reportou "endereço não traz, tab vazia".
        // Solução: passar contact SINTÉTICO direto pro ClienteSheet via state
        // `draftContact`. Drawer renderiza tabs IMEDIATAMENTE sem esperar reload.
        // Quando user digitar e autosave persistir, router.reload({only:['rows']})
        // (já dentro do IdentificacaoTab/EnderecoTab) substitui o sintético pelo real.
        const placeholderContact: ClienteRow = {
          id: newId,
          name: '',
          tax_number_masked: null,
          contact_id: null,
          mobile: null,
          total_os: 0,
          os_abertas: 0,
          os_atrasadas: 0,
          valor_aberto: 0,
          status: 'idle',
          last_os_at: null,
          avatar_hash_seed: '',
          cidade: null,
          uf: null,
          saldo_devedor: 0,
          last_purchase_at: null,
          created_at: new Date().toISOString(),
          tipo: null,
          fantasia: null,
          tags: [],
          segmento: null,
          vip: false,
        } as unknown as ClienteRow;
        setDraftContact(placeholderContact);
        setOpenTab('identificacao');
        setOpenContactId(newId);

        // Em paralelo: reload customers do backend pra sincronizar listagem com o
        // novo draft (aparece "Cliente" no topo alfabético, name vazio). Não bloqueia
        // a abertura do drawer.
        router.reload({
          only: ['customers'],
          preserveScroll: true,
          preserveState: true,
        });
      }
    } catch (err) {
       
      console.error('[ClienteIndex] POST /cliente/draft network', err);
      alert('Falha de rede ao criar cliente. Verifique sua conexão e tente de novo.');
    } finally {
      setCreatingDraft(false);
    }
  }, [activeType, creatingDraft]);

  const filteredRows = useMemo(() => {
    let r = rows;
    if (statusFilter) r = r.filter((x) => x.status === statusFilter);
    // Fix 2026-05-26 — busca por nome/tax_number/mobile MIGROU pro backend
    // (useEffect [search] acima dispara router.reload com `q`). Aqui rows
    // já vem filtrado server-side; filtro client-side seria duplicado e
    // amputaria resultados que casam o `q` SQL mas não o regex JS (acentos,
    // case). Filtros locais abaixo continuam (tipo/UF/tags/stale/saldo).
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
    // PTDP Onda 2 — KPI "Novos este mês" · client-side via created_at.
    if (recentMonthFilter) {
      const monthStart = new Date();
      monthStart.setDate(1);
      monthStart.setHours(0, 0, 0, 0);
      const startTs = monthStart.getTime();
      r = r.filter((x) => {
        if (!x.created_at) return false;
        const t = new Date(x.created_at).getTime();
        return !Number.isNaN(t) && t >= startTs;
      });
    }
    return r;
  }, [rows, statusFilter, tipoFilter, ufFilter, tagsFilter, staleFilter, saldoFilter, recentMonthFilter]);

  // Onda 3 (2026-06-12) — os 5 counts dos KPI cards agora vêm TODOS reais do backend
  // (`kpis.*`, scoped business_id). Removido o estimado client-side sobre as 50 rows da
  // página (que mostrava "número sem prova": VIPs/Sem90/Novos da amostra, não do negócio).

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
        if (row) openDrawer(row.id);
        return;
      }

      if (e.key === 'Escape' && focusedIndex !== null) {
        setFocusedIndex(null);
        return;
      }
    };

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [paletteOpen, cheatOpen, filteredRows, focusedIndex, openDrawer]);

  // Sort server-side (bug Wagner): antes só mexia state e NÃO re-buscava (cabeçalho
  // morto, igual paginação). Agora manda sort/dir pro backend (whitelist) e reseta
  // pra página 1. name=asc default; demais (recent/total_os/valor_aberto/last_os_at)=desc.
  const handleSort = useCallback((key: SortKey) => {
    const newDir: SortDir = key === sortKey
      ? (sortDir === 'asc' ? 'desc' : 'asc')
      : (key === 'name' ? 'asc' : 'desc');
    setSortKey(key);
    setSortDir(newDir);
    setPage(1);
    router.reload({
      only: ['customers'],
      data: { q: search || undefined, page: 1, per_page: perPageRef.current, sort: key, dir: newDir },
    });
  }, [sortKey, sortDir, search]);

  // Wave G — pills array removido (substituído por 6 FilterDropdown — ver nav acima).
  // KPIs/contadores ficam no header (subtítulo inline) + count "X de Y" ao lado dos filtros.

  // PageHeader canon v3.1 (ADR 0189): primary roxo medio + 3 blocos fechados.
  // Tokens inline (skel componente <PageHeader> ainda nao codificado em Wave 1).
  const PRIMARY_HUE = 295;
  const primaryBg   = `oklch(0.55 0.15 ${PRIMARY_HUE})`;
  const primaryDk   = `oklch(0.45 0.15 ${PRIMARY_HUE})`;
  const primarySoft = `oklch(0.96 0.03 ${PRIMARY_HUE})`;
  const primaryTxt  = `oklch(0.35 0.15 ${PRIMARY_HUE})`;

  // ADR 0189 v3.1 + LEARNINGS AP18 (2026-05-25): counter por tab vem do
  // backend via `props.tab_counts` (deferred). NUNCA via `rows.filter`
  // porque rows traz só tipo ativo (server-side filtered) → outros
  // tipos retornariam 0 (bug detectado em prod 2026-05-25).
  // Fallback {0,0,0,0,0} enquanto deferred carrega.
  const tabCounts: Record<ContactRoleType, number> = props.tab_counts ?? {
    all:            kpis?.total ?? 0,
    customer:       0,
    supplier:       0,
    employee:       0,
    representative: 0,
  };

  return (
    /* canon v3.4 (Wagner 2026-05-25): bg-page-cream substitui bg-slate-50 — fundo cream
       warm hue 90 espelha `.cockpit` /sells canon Cowork. Cria afinidade visual com cards
       brancos (mesma família temperatura) sem competir com filtros coloridos.
       Token `--color-page-cream` definido em resources/css/inertia.css @theme block. */
    <div className="flex-1 bg-page-cream py-4">
     {/* Fluid layout — Wagner 2026-05-25: tabela contatos usa 100% da largura disponível
         (pattern Linear/Notion/Stripe Dashboard em listas). `w-full px-6` (24px respiro
         lateral) + `py-4` (16px respiro topo+rodapé) — Wagner pediu "respiro nas laterais
         e em cima".
         canon v3.8 (Wagner 2026-05-25): space-y-3 (12px) → space-y-4 (16px) — gaps maiores
         entre blocos (Header→KPI→Toolbar→Tabela) pra cada um respirar melhor. */}
     <div className="w-full px-6 space-y-2">
      {/* ───── BLOCO 1 · HEADER TRANSPARENTE + border-b warm (canon v3.4 polish · 2026-05-25) ─────
          Wagner pediu remover `bg-background border rounded-t-lg` pra header herdar
          o cream `--color-page-cream` do parent — espelha `/sells` canon Cowork exato.
          v3.4 polish: adicionado `border-b` warm `oklch(0.93 0.004 90)` pra criar linha
          divisora visual entre BLOCO 1 e BLOCO 2 (espelha `.vd-toolbar` border-bottom
          em /sells). Mesmo hue 90 da familia cream — afinidade visual com fundo. */}
      <header
        className="border-b overflow-visible"
        role="banner"
        style={{ borderBottomColor: 'oklch(0.93 0.004 90)' }}
      >
        {/* Wagner 2026-05-25: BLOCO 1 header padding canon Vendas (referência /sells):
            pt-6 px-6 pb-3.5 (24px topo+lateral · 14px rodapé). Bottom menor pra underline
            da tab ativa casar com border-bottom do card via -mb-px (pattern canon). */}
        <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
          {/* ZONA L · identidade */}
          <div className="flex-1 min-w-0">
            {/* H1 canon v3.2 (LEARNINGS Decisao #3 · 2026-05-25): 22px font-weight 700 —
                peso espelhando /sells canon Cowork. v3.1 anterior era 16px/600 (compact
                demais pra entidade principal da pagina). */}
            <h1 className="text-[22px] font-bold tracking-tight text-foreground leading-snug">
              {ROLE_TITLE[activeType].title}
            </h1>
            <p className="text-xs text-muted-foreground mt-0.5 tabular-nums">
              {(kpis?.total ?? 0).toLocaleString('pt-BR')} {ROLE_TITLE[activeType].collective}
              {' · '}
              {(kpis?.com_os_aberta ?? 0).toLocaleString('pt-BR')} ativos
              {(kpis?.com_atraso ?? 0) > 0 && (
                <>
                  {' · '}
                  <strong className="text-destructive font-medium">
                    {(kpis.com_atraso).toLocaleString('pt-BR')} com saldo
                  </strong>
                </>
              )}
            </p>
          </div>

          {/* ZONA C · subnav inline (md+) com tabs abreviadas + counter */}
          <nav
            className="hidden md:flex items-center gap-0 shrink-0 self-stretch ml-2"
            aria-label="Tipo de contato"
          >
            {SLOT2_TABS.map(({ key, label, shortLabel, href, Icon }) => {
              const isActive = activeType === key;
              return (
                <a
                  key={key}
                  href={href}
                  aria-current={isActive ? 'page' : undefined}
                  aria-label={`Filtrar por ${label}`}
                  title={label}
                  className={
                    'group inline-flex items-center gap-1.5 px-3 h-9 text-[12.5px] border-b-2 -mb-px transition-colors ' +
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 ' +
                    (isActive
                      ? 'text-foreground font-medium'
                      : 'border-transparent text-muted-foreground font-normal hover:text-foreground')
                  }
                  style={isActive ? { borderBottomColor: primaryBg } : undefined}
                >
                  {/* Ícone canon LEARNINGS Decisão #2: size 16 + stroke 1.75 + vector-effect + shrink-0 (anti-borrão Lucide DPR=1) */}
                  <Icon
                    className="h-4 w-4 shrink-0"
                    aria-hidden="true"
                    strokeWidth={1.75}
                    style={{ vectorEffect: 'non-scaling-stroke' }}
                  />
                  <span>{shortLabel}</span>
                  {/* Wagner 2026-05-25: counter REMOVIDO da tab — duplicava info do KPI strip abaixo.
                      Contador continua sendo computado backend via props.tab_counts (defer paralelo
                      sem custo extra) — fica disponível pra future re-add ou outras telas. */}
                </a>
              );
            })}
          </nav>

          {/* ZONA R · actions (apenas ⋮ + primary roxo) */}
          <div className="flex-shrink-0 flex items-center gap-1.5">
            {(props.permissions.import || props.permissions.view) && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  {/* AP17 LEARNINGS: ghost puro (border-0) · Decisão #2 ícones: size 16 stroke 1.75 vector-effect */}
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Mais ações"
                    title="Importar / Exportar / Grupos"
                    className="h-8 w-8 border-0"
                  >
                    <MoreVertical
                      className="h-4 w-4 shrink-0"
                      strokeWidth={1.75}
                      style={{ vectorEffect: 'non-scaling-stroke' }}
                    />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="cw-dropdown-content w-56">
                  {/* SEÇÃO DADOS — ADR 0189 v3.1 SPEC §4.5 */}
                  {(props.permissions.import || props.permissions.view) && (
                    <>
                      <div className="px-2 pt-2 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Dados
                      </div>
                      {props.permissions.import && (
                        <DropdownMenuItem asChild>
                          <a href="/contacts/import">
                            <Upload className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} style={{ vectorEffect: 'non-scaling-stroke' }} />
                            Importar
                          </a>
                        </DropdownMenuItem>
                      )}
                      {props.permissions.view && (
                        <DropdownMenuItem asChild>
                          <a href="/cliente/export" title="Baixar CSV de contatos (UTF-8)">
                            <Download className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} style={{ vectorEffect: 'non-scaling-stroke' }} />
                            Exportar CSV
                          </a>
                        </DropdownMenuItem>
                      )}
                      <DropdownMenuSeparator />
                    </>
                  )}
                  {/* SEÇÃO CONFIGURAÇÃO */}
                  <div className="px-2 pt-1 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                    Configuração
                  </div>
                  <DropdownMenuItem asChild>
                    <a href="/customer-group" title="Cadastrar grupos/tipos de cliente (VIP, atacado, varejo, etc)">
                      <Layers className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} style={{ vectorEffect: 'non-scaling-stroke' }} />
                      Grupos de clientes
                    </a>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
            {/* Primary roxo universal (ADR 0190) — `all` não tem CTA pq é leitura agregada.
                ADR 0190 supersede pattern hue-per-grupo · usa PageHeaderPrimary canon. */}
            {props.permissions.create && activeType !== 'all' && (
              <PageHeaderPrimary
                label={`Novo ${ROLE_TITLE[activeType].singular}`}
                onClick={createNewContactDraft}
                disabled={creatingDraft}
              />
            )}
          </div>
        </div>

        {/* Mobile fallback: tabs em 2ª linha quando <md (no md+ ficam inline acima).
            canon v3.4: removido `border-t border-border` — consistência com header transparente. */}
        <nav
          className="md:hidden flex items-center gap-0 overflow-x-auto px-4"
          aria-label="Tipo de contato (mobile)"
        >
          {SLOT2_TABS.map(({ key, label, shortLabel, href, Icon }) => {
            const isActive = activeType === key;
            return (
              <a
                key={key}
                href={href}
                aria-current={isActive ? 'page' : undefined}
                aria-label={`Filtrar por ${label}`}
                title={label}
                className={
                  'group inline-flex items-center gap-1.5 px-3 h-11 text-[12.5px] border-b-2 -mb-px shrink-0 transition-colors ' +
                  (isActive
                    ? 'text-foreground font-medium'
                    : 'border-transparent text-muted-foreground font-normal')
                }
                style={isActive ? { borderBottomColor: primaryBg } : undefined}
              >
                <Icon
                  className="h-4 w-4 shrink-0"
                  aria-hidden="true"
                  strokeWidth={1.75}
                  style={{ vectorEffect: 'non-scaling-stroke' }}
                />
                <span>{shortLabel}</span>
                {/* Wagner 2026-05-25: counter removido — duplicava KPI strip */}
              </a>
            );
          })}
        </nav>
      </header>

      {/* ───── BLOCO 2 · KPI STRIP FLUTUANTE (sem moldura · 5 cards autossuficientes) ─────
          Wagner 2026-05-25: removido wrapper externo `bg-background border rounded-lg` pra
          eliminar "duplo fundo" (wrapper branco com 5 cards branco internos cada um já com
          border + rounded próprios). Pattern Linear/Notion · cards flutuam direto · respiro
          lateral vem do parent `<div w-full px-6>`. Toolbar de busca/filtros foi pra header
          do BLOCO 3 (header do card da tabela, separada por border-b). */}
      <Deferred data="kpis" fallback={<KpiSkeleton />}>
        <KpiStripClickable
          ativos={kpis?.com_os_aberta ?? 0}
          comSaldo={kpis?.com_atraso ?? 0}
          vips={kpis?.vips ?? 0}
          sem90={kpis?.sem_compra_90d ?? 0}
          novos={kpis?.novos_mes ?? 0}
          activeKey={activeKpiKey}
          onApply={applyKpiCard}
        />
      </Deferred>

      {/* ───── BLOCO 3 · TOOLBAR transparente + LISTA card warm (canon v3.5 · 2026-05-25) ─────
          Wagner sessao polish #4: toolbar transparente herda cream do parent (espelha
          /sells `.vd-tabs-row` bg `rgba(0,0,0,0)`). Busca AGORA a direita (ml-auto),
          filtros a esquerda. Linha divisora warm `oklch(0.93 0.004 90)` igual a linha
          entre BLOCO 1 e BLOCO 2 (consistencia visual canon v3.4). */}
      <div className="overflow-visible">
        <div
          className="px-4 py-3 border-b"
          style={{ borderBottomColor: 'oklch(0.93 0.004 90)' }}
        >
          <div className="flex items-center gap-3 flex-wrap" aria-label="Filtros e busca de contato">
            {/* Filtros combobox a esquerda — 6 FilterDropdown.
                Cada FilterDropdown reseta page=1 via useEffect [filtros, perPage]. */}
            <nav
              className="flex items-center gap-2 flex-wrap"
              aria-label="Filtros de contato"
            >
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
              {/* Contagem inline "X de Y" — Wave G UX paridade Cowork. */}
              <span className="text-xs text-muted-foreground tabular-nums ml-1">
                {filteredRows.length === rows.length
                  ? `${rows.length.toLocaleString('pt-BR')} ${rows.length !== 1 ? 'registros' : 'registro'}`
                  : `${filteredRows.length.toLocaleString('pt-BR')} de ${rows.length.toLocaleString('pt-BR')}`}
              </span>
            </nav>

            {/* Busca a direita (Wagner v3.5): `ml-auto` empurra pra direita extrema.
                border warm `oklch(0.9 0.004 90)` (afinidade familia cream). Placeholder
                simplificado: tira meta-info "(/ pra focar · ⌘K global)" — atalhos no cheat-sheet.
                Espaço pros ícones (lupa + X) via `.cw-input-search` — NÃO `pl-9/pr-9` tailwind,
                que são ignorados pelo `.cw-input` unlayered no Tailwind v4 (ver cowork-fields.css). */}
            <div className="relative ml-auto min-w-[200px] max-w-md w-full sm:w-auto">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
              <Input
                ref={searchInputRef}
                type="search"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Buscar nome, CNPJ/CPF, contato, telefone, cidade..."
                className="cw-input-icon-left cw-input-icon-right"
                style={{ borderColor: 'oklch(0.9 0.004 90)' }}
                aria-label="Buscar contato"
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

        <Deferred data="customers" fallback={<TableSkeleton />}>
          {/* canon v3.6 (Wagner 2026-05-25): tabela FLAT — `rounded-lg` removido pra
              parar com aparencia de card. Lista usa borders retas igual /sells.
              canon v3.7 (Wagner 2026-05-25): `mt-1` (4px) gap entre toolbar e tabela
              pra evitar borders coladas.
              canon v3.9 (Wagner 2026-05-25 polish #5): `mt-0` — lista COLADA na
              linha separadora da toolbar (Wagner: "remova o espaço entre lista
              e a linha, cole nela"). 0px gap · borders compartilham · cleaner. */}
          <div className="border border-border bg-background overflow-hidden">
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
                    <Th className="w-44">Documento</Th>
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
                              ? 'bg-primary/10'
                              : isFocused
                                ? 'bg-primary/5 ring-2 ring-inset ring-primary/40'
                                : 'hover:bg-muted/40')
                          }
                          onClick={() => {
                            setFocusedIndex(idx);
                            openDrawer(row.id);
                          }}
                        >
                          <td className="px-4 py-2.5">
                            {/* Wave G — Avatar HSL hash determinístico (Pages/Cliente/_components/Avatar.tsx). */}
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
                            {/* Sub-nome: fantasia (PJ) ou telefone. Suprime quando a fantasia
                                == razão social (legado preenche fantasia = nome → duplicava
                                a identidade na lista, desperdiçando a linha. Bug Wagner). */}
                            {(() => {
                              const f = row.fantasia?.trim();
                              const fant = f && f.toLowerCase() !== row.name.trim().toLowerCase() ? f : null;
                              if (!fant && !row.mobile) return null;
                              return (
                                <div className="text-[11px] text-muted-foreground/70 leading-tight mt-0.5 flex items-center gap-1">
                                  {fant ? (
                                    <span className="truncate">{fant}</span>
                                  ) : (
                                    <>
                                      <Phone size={9} className="opacity-60" />
                                      <span className="tabular-nums">{row.mobile}</span>
                                    </>
                                  )}
                                </div>
                              );
                            })()}
                          </td>
                          <td className="px-4 py-2.5">
                            <TipoPill tipo={row.tipo ?? null} />
                          </td>
                          <td className="px-4 py-2.5">
                            <span className="text-xs text-muted-foreground tabular-nums whitespace-nowrap">
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
                            <ActionsMenu
                              row={row}
                              canDelete={props.permissions.delete}
                              onView={() => openDrawer(row.id)}
                              onEdit={() => openDrawer(row.id, 'identificacao')}
                              onExtrato={() => openDrawer(row.id, 'operacoes')}
                              onDelete={() => setDeleteTarget(row)}
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
              onShowShortcuts={() => setCheatOpen(true)}
              onPageChange={(p) => {
                // Antes só atualizava o state (não buscava) → setas mortas. Agora
                // manda page pro servidor (paginate() lê da query) — bug Wagner.
                setPage(p);
                router.reload({
                  only: ['customers'],
                  data: { q: search || undefined, page: p, per_page: perPage, sort: sortRef.current.key, dir: sortRef.current.dir },
                });
              }}
              onPerPageChange={(n) => {
                setPerPage(n);
                setPage(1);
                router.reload({
                  only: ['customers', 'kpis', 'tab_counts'],
                  data: { q: search || undefined, page: 1, per_page: n, sort: sortRef.current.key, dir: sortRef.current.dir },
                });
              }}
            />
          )}
        </Deferred>
      </div>

      <ClienteSheet
        contactId={openContactId}
        open={openContactId !== null}
        initialTab={openTab}
        rows={rows}
        draftContact={draftContact}
        priceGroups={props.priceGroups ?? []}
        oficinaAutoEnabled={props.oficinaauto_enabled ?? false}
        onOpenChange={(open) => {
          if (!open) setDraftContact(null);
          if (!open) setOpenContactId(null);
        }}
        onContactUpdated={(patched) => {
          // Fix 2026-05-25 — atualiza draftContact com campos PATCHados (endereço,
          // contato, name, fantasia). EnderecoTab re-renderiza no próximo ciclo.
          setDraftContact((prev) => {
            if (!prev || prev.id !== openContactId) return prev;
            return { ...prev, ...patched } as ClienteRow;
          });
        }}
      />

      {/* KB-9.75 Slice A — Command Palette + Cheat-sheet + FAB. State efêmero, sem persistência. */}
      {paletteOpen && (
        <CommandPalette
          rows={rows}
          onClose={() => setPaletteOpen(false)}
          onSelectContact={(id) => {
            setPaletteOpen(false);
            openDrawer(id);
          }}
          onClearFilters={() => {
            setStatusFilter('');
            setSearchInput('');
            setSearch('');
            setFocusedIndex(null);
          }}
          onCreateDraft={createNewContactDraft}
        />
      )}
      {cheatOpen && <CheatSheet onClose={() => setCheatOpen(false)} />}

      {/* 2026-06-08 — confirmação de exclusão (ação destrutiva → AlertDialog,
          nunca delete num clique só). Soft delete server-side; reversível. */}
      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(o) => { if (!o && !deleting) setDeleteTarget(null); }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir contato?</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteTarget ? (
                <>
                  <strong>{deleteTarget.name || 'Este contato'}</strong> será excluído.
                  Contatos com venda, compra ou OS lançada não podem ser excluídos —
                  nesse caso o sistema avisa e nada é apagado.
                </>
              ) : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={(e) => { e.preventDefault(); confirmDelete(); }}
              disabled={deleting}
              className="bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-600"
            >
              {deleting ? 'Excluindo…' : 'Excluir'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
     </div>
    </div>
  );
}

ClienteIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

function KpiSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="rounded-lg border border-border bg-background p-5 shadow-sm h-28 animate-pulse" />
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
        // canon v3.6 (Wagner 2026-05-25): FILTRO INATIVO ghost/flat — `border-transparent
        // bg-transparent` (zero peso visual, herda fundo cream do toolbar). FILTRO ATIVO
        // mantido como antes — border-blue + bg-blue + text-blue (estado claro de selecionado).
        className={
          'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ' +
          (hasSel
            ? 'border-primary/40 bg-primary/10 text-primary'
            : 'border-transparent bg-transparent text-muted-foreground hover:bg-muted/50 hover:text-foreground')
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
                    ? 'bg-primary/10 text-primary'
                    : 'text-foreground hover:bg-muted')
                }
              >
                {multi && (
                  <span
                    className={
                      'inline-flex h-3.5 w-3.5 items-center justify-center rounded border ' +
                      (isOn
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background')
                    }
                  >
                    {isOn && <Check size={9} strokeWidth={3} />}
                  </span>
                )}
                <span className="flex-1">{opt.label}</span>
                {!multi && isOn && <Check size={11} className="text-primary" />}
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
        'rounded-lg border p-5 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div
        className={
          'text-[11px] font-semibold uppercase tracking-widest ' +
          (danger ? 'text-destructive' : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div className="flex items-end justify-between mt-3">
        <div
          className={
            'text-3xl font-semibold tabular-nums ' +
            (danger ? 'text-destructive' : 'text-foreground')
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

// 2026-06-08 (Wagner "arrumar botões da contacts" + "exclusão de contato") —
// menu ⋮ consolidado no drawer 760 (ADR 0179: drawer substitui página/edit
// full-page Blade legacy). "Editar"/"Extrato" abrem o drawer na aba certa (não
// vão mais pra /contacts/{id}/edit · /contacts/ledger). "Excluir" voltou
// funcional (soft delete via DELETE /contacts/{id}) — gated por canDelete
// (customer/supplier.delete) e escondido pro is_default (walk-in). A trava de
// "tem venda/OS → não exclui" é server-side (destroy()).
function ActionsMenu({
  row,
  canDelete,
  onView,
  onEdit,
  onExtrato,
  onDelete,
}: {
  row: ClienteRow;
  canDelete: boolean;
  onView: () => void;
  onEdit: () => void;
  onExtrato: () => void;
  onDelete: () => void;
}) {
  const showDelete = canDelete && !row.is_default;
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-label={`Ações de ${row.name || 'contato'}`}
          onClick={(e) => e.stopPropagation()}
        >
          <MoreVertical size={16} />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="cw-dropdown-content w-48">
        <DropdownMenuItem onClick={onView}>
          <Eye size={14} className="mr-2" />
          Ver detalhes
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onEdit}>
          <Edit size={14} className="mr-2" />
          Editar
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onExtrato}>
          <CreditCard size={14} className="mr-2" />
          Extrato
        </DropdownMenuItem>
        {showDelete && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={onDelete}
              className="text-destructive focus:bg-destructive-soft focus:text-destructive-fg"
            >
              <Trash2 size={14} className="mr-2" />
              Excluir
            </DropdownMenuItem>
          </>
        )}
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
  | 'operacoes'
  | 'placas'
  | 'ia';

// Wagner 2026-05-27 iteracao 2 (Proposta F): 6 tabs principais focadas em
// CADASTRO/OPERACOES editaveis. Tabs read-only (placas/ia) viraram BOTOES NO
// HEADER do drawer (acesso 1-clique sem poluir tab bar). Rename oss→operacoes
// (semantica clara: OSs ficavam dentro mas agora so coisas REAIS de OS —
// Extrato/Vendas/Pagamentos/Documentos/Pessoas/Assinaturas/Pontos/Auditoria).
// Wagner 2026-06-13: Auditoria saiu do chip e virou sub-aba de Operações
// ("chips e abas são a mesma coisa — integrar").
const DRAWER_TABS: Array<{ key: DrawerTab; label: string }> = [
  { key: 'identificacao', label: 'Identificação' },
  { key: 'contato',       label: 'Contato' },
  { key: 'endereco',      label: 'Endereço' },
  { key: 'comercial',     label: 'Comercial' },
  { key: 'classificacao', label: 'Classificação' },
  { key: 'operacoes',     label: 'Operações' },
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
  initialTab = 'identificacao',
  rows,
  draftContact,
  priceGroups = [],
  oficinaAutoEnabled = false,
  onOpenChange,
  onContactUpdated,
}: {
  contactId: number | null;
  open: boolean;
  /** Aba em que o drawer abre. Menu ⋮ da linha usa pra "Editar" (identificacao)
   *  e "Extrato" (operacoes›ledger). Default identificacao (clique na linha). */
  initialTab?: DrawerTab;
  rows: ClienteRow[];
  /** Hotfix 2026-05-25 — modo 'novo cliente' passa contact placeholder direto
   * via state Index.tsx pra não depender do `rows.find()` (que falha porque
   * draft recém-criado pode estar fora da página paginada da listagem). */
  draftContact?: ClienteRow | null;
  /** Tabelas de preço REAIS (customer_groups) pro dropdown do ComercialTab. */
  priceGroups?: PriceGroupOption[];
  /** Wagner 2026-05-27 — gate sub-tab "Placas" do OssTab. */
  oficinaAutoEnabled?: boolean;
  onOpenChange: (open: boolean) => void;
  /** Fix 2026-05-25 — IdentificacaoTab chama isso após PATCH endereço/contato
   * sucesso pra atualizar draftContact no Index.tsx DIRETO (sem router.reload
   * que pode não popular paginação). EnderecoTab re-renderiza com campos novos. */
  onContactUpdated?: (patched: Record<string, unknown>) => void;
}) {
  const contact = useMemo(() => {
    // Prioridade: draftContact (modo novo) → rows.find (modo edit).
    if (draftContact && draftContact.id === contactId) return draftContact;
    return rows.find((r) => r.id === contactId) ?? null;
  }, [rows, contactId, draftContact]);
  const [activeTab, setActiveTab] = useState<DrawerTab>('identificacao');
  // Toggle PF/PJ -- estado local. Wave C persiste via autosave on blur PATCH.
  // Placeholder no payload ClienteRow nao tem `tipo` ainda (Migration Wave B
  // adiciona; ContactController nao pro frontend ainda nesta wave).
  const [tipo, setTipo] = useState<'PF' | 'PJ'>('PJ');
  // Fix Wagner 2026-05-27 — version counter pra forçar remount do EnderecoTab
  // SÓ quando CNPJ lookup explicitamente persistir endereço (sobrescrita autorizada
  // pelo user). Tabs cadastrais agora usam useEffect deps `[contact.id]` apenas
  // (preserva state local quando user troca de aba). Sem isso, pós CNPJ lookup
  // o EnderecoTab continuaria mostrando state local antigo. Incrementar key
  // descarta state local e re-monta com novo `contact.zip_code/address_line_1/...`.
  const [enderecoVersion, setEnderecoVersion] = useState(0);
  // Wagner 2026-06-01 — sub-aba ativa da tab Operações (OssTab). Controlada aqui
  // pra o chip header "📎 N anexos" cair direto em Documentos. Reset p/ 'ledger'
  // quando entra na tab Operações pelo caminho normal (tab bar).
  const [opsSubTab, setOpsSubTab] = useState<OssSubTabKey>('ledger');
  // Wagner 2026-06-01 — contagem VIVA de anexos pro chip "📎 N anexos". null =
  // usa o valor estático do payload (documents_count); quando o painel Documentos
  // carrega/sobe/exclui, o OssTab reporta o número real e o chip passa a refletir.
  const [liveAnexosCount, setLiveAnexosCount] = useState<number | null>(null);

  // Reset tab ao abrir contact diferente. Abre na aba pedida pelo chamador
  // (default "Identificação"; menu ⋮ pede "operacoes" pra Extrato). Entrar em
  // Operações já cai na sub-aba Extrato ('ledger').
  useEffect(() => {
    if (open && contactId) {
      setActiveTab(initialTab);
      if (initialTab === 'operacoes') setOpsSubTab('ledger');
      setLiveAnexosCount(null); // novo contact → count do payload até o painel carregar
    }
  }, [open, contactId, initialTab]);

  // Z-2.1: Atalho 1-8 troca tab quando drawer aberto. Alinhado ao protótipo Cowork.
  // Pulado quando user está digitando em input/textarea (form fields cadastrais).
  useEffect(() => {
    if (!open) return;
    const TAB_ORDER: DrawerTab[] = [
      'identificacao', 'contato', 'endereco', 'comercial',
      'classificacao', 'operacoes', 'ia', 'placas',
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
        // cw-sheet (ADR UI-0015): bg --cw-bg sutil — destaca seções brancas dentro
        className="cw-sheet w-[760px] sm:max-w-[760px] p-0 flex flex-col"
        // Fix Wagner 2026-05-27 — ESC dentro do drawer (ex: dispensar erro
        // "CEP não encontrado" após Buscar CEP, ou limpar focus de um input)
        // fechava o Sheet inteiro perdendo todo o trabalho não-salvo. Drawer
        // tem botão X visível (top-right) + click no overlay pra fechar — ESC
        // é foot-gun. Padrão Notion/Linear: ESC NUNCA fecha drawer de edição.
        onEscapeKeyDown={(e) => e.preventDefault()}
      >
        {/* ─── Header rico ─────────────────────────────────────────────── */}
        <SheetHeader className="cw-sheet-header px-6 py-4 space-y-3">
          <div className="flex items-start gap-4">
            {/* Z-2.1: drawer header avatar 40px round gradient oklch (alinhado ao protótipo Cowork). */}
            <ClienteAvatar
              name={contact?.name ?? '?'}
              seed={contact?.avatar_hash_seed ?? String(contact?.id ?? 0)}
              size={40}
              shape="circle"
            />

            <div className="flex-1 min-w-0">
              {/* Wagner UX: toggle PF/PJ duplicado removido (toggle REAL fica na
                  IdentificacaoTab com autosave). Aqui só identidade informativa. */}
              <SheetTitle className="text-lg font-semibold leading-tight truncate">
                {contact?.name ?? 'Cliente'}
              </SheetTitle>

              {/* Wagner 2026-06-12 — badge status (Sem OS/Ativo) movido pra ESTA linha
                  de identidade. Antes ficava no canto direito ENCAVALADO com o X de
                  fechar do Sheet; agora o topo-direito tem só o X. Inline-flex (não
                  cria flex container novo) + cor tokenizada (era stone/emerald cru). */}
              <SheetDescription className="mt-0.5 text-xs">
                {tipo === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física'}
                {' · cadastrado '}
                {/* Z-2.1: ContactController::index payload inclui created_at. */}
                {relativeDate(contact?.created_at ?? null)}
                <span
                  className={
                    'ml-1.5 inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium align-middle ' +
                    (contact?.status === 'idle'
                      ? 'bg-muted text-muted-foreground border-border'
                      : 'bg-success/10 text-success border-success/30')
                  }
                >
                  {contact?.status === 'idle' ? 'Sem OS' : 'Ativo'}
                </span>
              </SheetDescription>
            </div>
          </div>

          {/* Botoes acao -- Imprimir + Copiloto. ADR UI-0015 (cowork canon). */}
          <div className="flex items-center gap-2 pt-1">
            <Button
              variant="cowork-ghost"
              onClick={() => {
                // Placeholder Wave B -- window.print() abre dialogo print do navegador.
                // Wave G implementa CSS @media print + layout dedicado.
                if (typeof window !== 'undefined') window.print();
              }}
            >
              Imprimir ficha
            </Button>
            <Button asChild variant="cowork-primary">
              <a
                href={contact ? `/ia/chat?context=cliente:${contact.id}` : '#'}
                title="Abre o Copiloto (Jana) com contexto deste cliente"
              >
                Falar com Copiloto →
              </a>
            </Button>
          </div>

          {/* Wagner 2026-05-27 iteracao 2 (Proposta F) -- Botoes header pra
              entidades secundarias: Placas (gate OficinaAuto, contador) + IA.
              Acesso 1-clique sem poluir tab bar (6 tabs principais focadas em
              cadastro/operacoes editaveis). Auditoria saiu daqui 2026-06-13 →
              virou sub-aba de Operações (rail). */}
          <div className="flex items-center gap-1 pt-1 flex-wrap" role="toolbar" aria-label="Atalhos do drawer">
            {oficinaAutoEnabled && (
              <button
                type="button"
                onClick={() => setActiveTab('placas')}
                className={
                  'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-medium transition-colors ' +
                  (activeTab === 'placas'
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-input bg-background text-muted-foreground hover:text-foreground hover:bg-muted/40')
                }
                aria-pressed={activeTab === 'placas'}
                title="Ver veiculos cadastrados pra este cliente"
              >
                <Car size={11} aria-hidden />
                <span>{contact?.vehicles_count ?? 0}</span>
                <span>placas</span>
              </button>
            )}
            {/* Wagner 2026-06-01 — chip "anexos" ao lado de placas: acesso 1-clique
                aos documentos (abre Operações → Documentos). Universal (sem gate):
                anexos valem pra todo cliente, não só OficinaAuto. */}
            <button
              type="button"
              onClick={() => {
                setOpsSubTab('documents');
                setActiveTab('operacoes');
              }}
              className={
                'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-medium transition-colors ' +
                (activeTab === 'operacoes' && opsSubTab === 'documents'
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-input bg-background text-muted-foreground hover:text-foreground hover:bg-muted/40')
              }
              aria-pressed={activeTab === 'operacoes' && opsSubTab === 'documents'}
              title="Ver anexos do cliente (comprovantes, contratos, fotos)"
            >
              <Paperclip size={11} aria-hidden />
              <span>{liveAnexosCount ?? contact?.documents_count ?? 0}</span>
              <span>anexos</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('ia')}
              className={
                'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-medium transition-colors ' +
                (activeTab === 'ia'
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-input bg-background text-muted-foreground hover:text-foreground hover:bg-muted/40')
              }
              aria-pressed={activeTab === 'ia'}
              title="Cards de inteligencia (perfil/risco)"
            >
              <Sparkles size={11} aria-hidden />
              <span>IA</span>
            </button>
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
                onClick={() => {
                  setActiveTab(t.key);
                  // Entrar em Operações pela tab bar começa no Extrato (não herda
                  // 'documents' deixado por um clique anterior no chip de anexos).
                  if (t.key === 'operacoes') setOpsSubTab('ledger');
                }}
                className={
                  'inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px whitespace-nowrap ' +
                  (isActive
                    ? 'border-primary text-primary'
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
              {/* Tabs cadastrais 1-5 — Wagner 2026-05-27: MOUNTED SEMPRE (hidden via
                  CSS quando inativo) pra preservar state local + debounces pendentes
                  quando user troca de aba antes do autosave 800ms disparar. Padrão
                  Notion/Linear. Render-condicional anterior desmontava o componente,
                  matando state + cleanup clearTimeout matando debounces → user digita
                  CEP/número/etc e troca pra aba "Contato" em <800ms → valor sumia.
                  Tabs read-only (placas/ia) seguem condicionais — desmontar
                  é OK pra elas (consultas pesadas, sem state user-editável).
                  Auditoria agora é sub-aba de Operações (desmonta junto do OssTab). */}
              <div hidden={activeTab !== 'identificacao'}>
                <IdentificacaoTab
                  contact={contact}
                  onCnpjEnderecoPersisted={() => {
                    router.reload({ only: ['rows'], preserveScroll: true, preserveState: true });
                    // Força remount do EnderecoTab pra sincronizar com novos campos
                    // da BrasilAPI (CEP/logradouro/bairro/cidade/UF). Sobrescrita
                    // autorizada (user clicou "Buscar CNPJ").
                    setEnderecoVersion((v) => v + 1);
                  }}
                  onContactUpdated={onContactUpdated}
                />
              </div>
              <div hidden={activeTab !== 'contato'}>
                <ContatoTab contact={contact} />
              </div>
              <div hidden={activeTab !== 'endereco'}>
                <EnderecoTab
                  key={enderecoVersion}
                  contact={contact}
                  onContactUpdated={onContactUpdated}
                />
              </div>
              <div hidden={activeTab !== 'comercial'}>
                <ComercialTab contact={contact} priceGroups={priceGroups} />
              </div>
              <div hidden={activeTab !== 'classificacao'}>
                <ClassificacaoTab contact={contact} />
              </div>
              {activeTab === 'operacoes' && (
                <OssTab
                  contact={{ id: contact.id, name: contact.name }}
                  activeSubTab={opsSubTab}
                  onSubTabChange={setOpsSubTab}
                  /* Wagner 2026-06-01 — habilita anexar/excluir/notas no drawer.
                     Legado concede view/create/delete de documentos a quem vê o
                     contato (DocumentAndNoteController::__getPermission p/ App\Contact). */
                  permissions={{ upload: true, delete_document: true, edit_note: true }}
                  /* chip "📎 N anexos" reflete a contagem viva após load/upload/delete */
                  onDocumentsCountChange={setLiveAnexosCount}
                />
              )}
              {activeTab === 'placas' && oficinaAutoEnabled && (
                <PlacasMainTab contactId={contact.id} />
              )}
              {activeTab === 'ia' && (
                <IATab contact={{ id: contact.id, name: contact.name }} />
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
              variant="cowork-ghost"
              onClick={() => onOpenChange(false)}
            >
              Cancelar
            </Button>
            <Button
              variant="cowork-primary"
              onClick={() => {
                // Placeholder Wave B -- Wave C dispara PATCH autosave on blur.
                // router.reload only:['contact'] refresca payload sem rerender total.
                // TODO Wave C: substituir por toast "Salvo" + reload partial.
                onOpenChange(false);
              }}
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
      <div className={'text-[10px] font-semibold uppercase tracking-widest ' + (danger ? 'text-destructive' : 'text-muted-foreground')}>
        {label}
      </div>
      <div className={'text-lg font-semibold tabular-nums mt-1 ' + (danger ? 'text-destructive' : 'text-foreground')}>
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
  onShowShortcuts,
}: {
  meta: ListMeta;
  perPage: number;
  onPageChange: (p: number) => void;
  onPerPageChange: (n: number) => void;
  /** Abre o cheat-sheet de atalhos — encaixado no rodapé (antes era FAB flutuante). */
  onShowShortcuts: () => void;
}) {
  const { current_page, last_page, total, from, to } = meta;
  const canPrev = current_page > 1;
  const canNext = current_page < last_page;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 mt-3 px-1">
      <div className="text-xs text-muted-foreground">
        {/* Atalhos de teclado — discreto no canto do rodapé (Wagner 2026-06-15:
            "não gostei dele flutuante, deve ficar mais discreto, só encaixar").
            Sem wrapper flex extra (ratchet de layout · ADR 0253): o botão já é
            inline-flex e o texto flui inline ao lado. */}
        <button
          type="button"
          onClick={onShowShortcuts}
          aria-label="Atalhos de teclado"
          title="Atalhos de teclado (?)"
          className="inline-flex items-center gap-1.5 align-middle -ml-1 mr-1.5 rounded-md px-1.5 py-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
        >
          <Keyboard size={14} />
          <Kbd>?</Kbd>
        </button>
        {total === 0
          ? 'Nenhum cliente'
          : `Mostrando ${from ?? 0}–${to ?? 0} de ${total.toLocaleString('pt-BR')}`}
      </div>
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>Por página</span>
          <Select value={String(perPage)} onValueChange={(v) => onPerPageChange(Number(v))}>
            <SelectTrigger variant="shadcn" size="sm" className="h-7 w-fit text-xs" aria-label="Itens por página">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PER_PAGE_OPTIONS.map((n) => (
                <SelectItem key={n} value={String(n)}>
                  {n}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
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
  onCreateDraft,
}: {
  rows: ClienteRow[];
  onClose: () => void;
  onSelectContact: (id: number) => void;
  onClearFilters: () => void;
  onCreateDraft: () => void;
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
    { id: 'novo', label: 'Novo cliente', icon: Plus, action: () => { onClose(); onCreateDraft(); } },
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
      <div className="relative w-full max-w-2xl rounded-lg border border-border bg-background shadow-2xl mx-4">
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
      <div className="relative w-full max-w-md rounded-lg border border-border bg-background shadow-2xl mx-4">
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
