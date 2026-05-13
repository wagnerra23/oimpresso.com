// @memcofre tela=/oficina-auto/producao-oficina module=OficinaAuto
// Produção · Oficina — Kanban estado das caçambas (V2 RICA — espelha visual-source.html canônico).
// Espelha 1:1 protótipo Cowork rico:
//   prototipo-ui/prototipos/producao-oficina/visual-source.html (1213 linhas — fonte canônica visual)
// Adaptado pra caçambas (5 colunas: Disponível/Locada/Aguardando recolhimento/
// Em manutenção/Pronta entrega) com 6 KPIs ricos + drawer próprio CacambaProducaoSheet.
//
// Refs:
//   - ADR 0137 (OficinaAuto qualificada)
//   - ADR 0143 (FSM Pipeline LIVE prod biz=1)
//   - ADR 0110 (Cockpit V2 — AppShellV2 obrigatório)
//   - PR #717 lição: useMemo/useCallback nos handlers descendentes (re-render loop)
//   - ServiceOrderSheet existing (PR #729) — NÃO usado aqui (drawer próprio CacambaProducaoSheet
//     embute ServiceOrderFsmActionPanel)
// Visual comparison: memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md (V2)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { Plus, Printer, Search, LayoutGrid, List as ListIcon } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import CacambaKanbanColumn from './_components/CacambaKanbanColumn';
import type { CacambaCardData, CacambaStatus } from './_components/CacambaCard';
import CacambaProducaoSheet from './_components/CacambaProducaoSheet';

// ─── Types ───────────────────────────────────────────────────────────────────

interface KanbanGroups {
  disponivel: CacambaCardData[];
  locada: CacambaCardData[];
  aguardando: CacambaCardData[];
  manutencao: CacambaCardData[];
  pronta: CacambaCardData[];
}

interface Kpis {
  total: number;
  disponivel: number;
  locada: number;
  aguardando_recolhimento: number;
  manutencao: number;
  atrasadas: number;
  valor_em_curso: number;
}

type CapacidadeFilter = 'all' | '3' | '5' | '7';

interface Props {
  kanban: KanbanGroups;
  kpis: Kpis;
  filters: {
    capacidade: CapacidadeFilter;
    q: string;
  };
}

// ─── Constants ───────────────────────────────────────────────────────────────

const COLUMNS: Array<{ key: keyof KanbanGroups; status: CacambaStatus; label: string }> = [
  { key: 'disponivel',  status: 'disponivel',  label: 'Disponível' },
  { key: 'locada',      status: 'locada',      label: 'Locada' },
  { key: 'aguardando',  status: 'aguardando',  label: 'Aguardando recolhimento' },
  { key: 'manutencao',  status: 'manutencao',  label: 'Em manutenção' },
  { key: 'pronta',      status: 'pronta',      label: 'Pronta entrega' },
];

const CAPACIDADES: Array<{ key: CapacidadeFilter; label: string }> = [
  { key: 'all', label: 'Todas' },
  { key: '3',   label: '3m³' },
  { key: '5',   label: '5m³' },
  { key: '7',   label: '7m³' },
];

const formatBRLCompact = (value: number) =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));

// ─── Component ───────────────────────────────────────────────────────────────

export default function ProducaoOficinaIndex({ kanban, kpis, filters }: Props) {
  const [searchInput, setSearchInput] = useState(filters.q ?? '');

  // Debounce 300ms — evita visit por keystroke
  useEffect(() => {
    if (searchInput === (filters.q ?? '')) return;
    const t = setTimeout(() => {
      router.get(
        '/oficina-auto/producao-oficina',
        {
          q: searchInput || undefined,
          capacidade: filters.capacidade === 'all' ? undefined : filters.capacidade,
        },
        { preserveState: true, preserveScroll: true, replace: true },
      );
    }, 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  // Drawer caçamba — abre OS atual (rental ativa) ao clicar card
  const [openOsId, setOpenOsId] = useState<number | null>(null);

  const handleCardClick = useCallback((c: CacambaCardData) => {
    if (c.current_rental_id) {
      setOpenOsId(c.current_rental_id);
      return;
    }
    // Sem locação ativa (manut./disponível) — fallback show vehicle
    router.visit(`/oficina-auto/veiculos/${c.id}`);
  }, []);

  const handleSheetOpenChange = useCallback((open: boolean) => {
    if (!open) setOpenOsId(null);
  }, []);

  const handleOrderChanged = useCallback(() => {
    // Refresh kanban + kpis após transição FSM
    router.reload({ only: ['kanban', 'kpis'], preserveScroll: true, preserveState: true });
  }, []);

  // Memoiza 5 cards arrays — só re-render se conteúdo mudar (lição PR #717)
  const columnsData = useMemo(
    () => COLUMNS.map((col) => ({
      ...col,
      cards: kanban[col.key] ?? [],
    })),
    [kanban]
  );

  // 6 KPI cards (espelha visual-source.html linha de 6 cards horizontais)
  const kpiCards = useMemo(
    () => [
      {
        key: 'total',
        label: 'Total',
        value: String(kpis.total),
        sub: `${kpis.total === 1 ? 'caçamba no estoque' : 'caçambas no estoque'}`,
        tone: 'default' as const,
      },
      {
        key: 'locada',
        label: 'Locadas',
        value: String(kpis.locada),
        sub: 'em campo no momento',
        tone: 'default' as const,
      },
      {
        key: 'aguardando',
        label: 'Aguardando',
        value: String(kpis.aguardando_recolhimento),
        sub: 'recolhimento',
        tone: 'amber' as const,
      },
      {
        key: 'manutencao',
        label: 'Em manutenção',
        value: String(kpis.manutencao),
        sub: 'oficina',
        tone: 'default' as const,
      },
      {
        key: 'atrasadas',
        label: 'Atrasadas',
        value: String(kpis.atrasadas),
        sub: 'prazo crítico',
        tone: 'rose' as const,
      },
      {
        key: 'valor',
        label: 'Valor em curso',
        value: formatBRLCompact(kpis.valor_em_curso),
        sub: 'faturamento previsto',
        tone: 'emerald' as const,
      },
    ],
    [kpis]
  );

  // KPI inline filter bar (espelha "8 caçambas · 1 atrasada · 1 aguarda recolhimento")
  const kpiSummary = useMemo(() => {
    const parts: string[] = [
      `${kpis.total} ${kpis.total === 1 ? 'caçamba' : 'caçambas'}`,
    ];
    if (kpis.atrasadas > 0) {
      parts.push(`${kpis.atrasadas} ${kpis.atrasadas === 1 ? 'atrasada' : 'atrasadas'}`);
    }
    if (kpis.aguardando_recolhimento > 0) {
      parts.push(`${kpis.aguardando_recolhimento} aguardando recolhimento`);
    }
    return parts;
  }, [kpis]);

  return (
    <>
      <Head title="Produção · Oficina — Caçambas" />
      <div className="-m-6 bg-slate-50 min-h-[calc(100vh-3rem)]">
        {/* ─── Topbar header — h1 + sub + ações ─── */}
        <header className="bg-white border-b border-slate-200 px-6 py-4 flex items-start justify-between gap-4 flex-wrap">
          <div className="min-w-0">
            <h1 className="text-lg font-semibold text-slate-900">
              Produção · Oficina
            </h1>
            <p className="text-xs text-slate-500 mt-0.5">
              Locação, recolhimento, manutenção e entrega de caçambas
            </p>
          </div>
          <div className="flex items-center gap-2 flex-shrink-0 flex-wrap">
            {/* Toggle Kanban|Lista — Lista navega pra /veiculos por enquanto */}
            <div
              className="inline-flex rounded border border-slate-200 bg-white overflow-hidden"
              role="group"
              aria-label="Visualização"
            >
              <button
                className="px-2.5 py-1 text-xs font-medium bg-slate-900 text-white inline-flex items-center gap-1"
                disabled
                aria-pressed="true"
              >
                <LayoutGrid size={12} />
                Kanban
              </button>
              <Link
                href="/oficina-auto/veiculos"
                className="px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1"
              >
                <ListIcon size={12} />
                Lista
              </Link>
            </div>
            <Button variant="ghost" size="sm" disabled title="Imprimir fila — V2">
              <Printer className="mr-1.5 h-4 w-4" />
              Imprimir fila
            </Button>
            <Button asChild size="sm">
              <Link href="/oficina-auto/veiculos/create">
                <Plus className="mr-1.5 h-4 w-4" />
                Nova caçamba
              </Link>
            </Button>
          </div>
        </header>

        {/* ─── 6 KPI cards (grid-cols-6 — espelha visual-source.html) ─── */}
        <div className="bg-white border-b border-slate-200 px-6 py-4">
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            {kpiCards.map((kpi) => (
              <KpiCard key={kpi.key} {...kpi} />
            ))}
          </div>
        </div>

        {/* ─── Filter bar sticky — pills capacidade + busca + KPI inline ─── */}
        <div className="bg-white border-b border-slate-200 px-6 py-3 flex items-center gap-6 sticky top-0 z-10 flex-wrap">
          <div className="flex items-center gap-2">
            <span className="text-xs uppercase tracking-wide text-slate-500 font-medium">
              Capacidade
            </span>
            <div className="flex gap-1" role="group" aria-label="Filtro por capacidade">
              {CAPACIDADES.map((cap) => {
                const isActive = filters.capacidade === cap.key;
                return (
                  <Link
                    key={cap.key}
                    href={`/oficina-auto/producao-oficina?${new URLSearchParams({
                      ...(cap.key !== 'all' ? { capacidade: cap.key } : {}),
                      ...(searchInput ? { q: searchInput } : {}),
                    }).toString()}`}
                    preserveScroll
                    preserveState
                    className={
                      'px-2.5 py-1 text-sm rounded transition-colors ' +
                      (isActive
                        ? 'bg-slate-900 text-white'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200')
                    }
                    aria-pressed={isActive}
                  >
                    {cap.label}
                  </Link>
                );
              })}
            </div>
          </div>

          <div className="w-px h-6 bg-slate-200" />

          <div className="flex items-center gap-2 flex-1 min-w-[240px] max-w-md">
            <Search size={14} className="text-slate-400 flex-shrink-0" />
            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar caçamba ou cliente…"
              className="h-8 border-slate-200"
              aria-label="Buscar caçamba ou cliente"
            />
          </div>

          {/* KPI inline (à direita) */}
          <div className="ml-auto text-sm text-slate-500" aria-live="polite">
            {kpiSummary.map((part, i) => (
              <span key={part}>
                {i > 0 && <span className="mx-1.5 text-slate-300">·</span>}
                <span
                  className={
                    part.includes('atrasada')
                      ? 'font-medium text-rose-700'
                      : part.includes('aguardando')
                        ? 'font-medium text-amber-700'
                        : 'font-medium text-slate-900'
                  }
                >
                  {part}
                </span>
              </span>
            ))}
          </div>
        </div>

        {/* ─── Kanban 5 colunas ─── */}
        <main className="p-6">
          <div className="grid grid-cols-5 gap-4">
            {columnsData.map((col) => (
              <CacambaKanbanColumn
                key={col.key}
                status={col.status}
                label={col.label}
                cards={col.cards}
                onCardClick={handleCardClick}
              />
            ))}
          </div>
        </main>
      </div>

      {/* Drawer rico Caçamba — específico desta tela (embute FsmActionPanel reusado) */}
      <CacambaProducaoSheet
        serviceOrderId={openOsId}
        open={openOsId !== null}
        onOpenChange={handleSheetOpenChange}
        onOrderChanged={handleOrderChanged}
      />
    </>
  );
}

ProducaoOficinaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

interface KpiCardProps {
  label: string;
  value: string;
  sub: string;
  tone: 'default' | 'amber' | 'rose' | 'emerald';
}

function KpiCard({ label, value, sub, tone }: KpiCardProps) {
  const toneClasses = {
    default: {
      wrapper: 'bg-white border-slate-200',
      label: 'text-slate-500',
      value: 'text-slate-900',
      sub: 'text-slate-400',
    },
    amber: {
      wrapper: 'bg-amber-50 border-amber-200',
      label: 'text-amber-700',
      value: 'text-amber-900',
      sub: 'text-amber-600',
    },
    rose: {
      wrapper: 'bg-rose-50 border-rose-200',
      label: 'text-rose-700',
      value: 'text-rose-900',
      sub: 'text-rose-600',
    },
    emerald: {
      wrapper: 'bg-emerald-50 border-emerald-200',
      label: 'text-emerald-700',
      value: 'text-emerald-900',
      sub: 'text-emerald-600',
    },
  }[tone];

  return (
    <div
      className={`rounded-lg border p-3 flex flex-col gap-0.5 ${toneClasses.wrapper}`}
    >
      <span
        className={`text-[10px] font-semibold uppercase tracking-wider ${toneClasses.label}`}
      >
        {label}
      </span>
      <span className={`text-2xl font-bold tabular-nums ${toneClasses.value}`}>
        {value}
      </span>
      <span className={`text-[11px] ${toneClasses.sub}`}>{sub}</span>
    </div>
  );
}
