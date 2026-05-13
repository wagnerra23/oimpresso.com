// @memcofre tela=/oficina-auto/producao-oficina module=OficinaAuto
// Produção · Oficina — Kanban estado das caçambas (demo Martinho 13/maio 2026 10h).
// Espelha 1:1 protótipo Cowork canônico:
//   prototipo-ui/prototipos/producao-oficina/F1.html
// Adaptado pra caçambas (5 colunas: Disponível/Locada/Aguardando recolhimento/
// Em manutenção/Pronta entrega).
//
// Refs:
//   - ADR 0137 (OficinaAuto qualificada)
//   - ADR 0143 (FSM Pipeline LIVE prod biz=1)
//   - ADR 0110 (Cockpit V2 — AppShellV2 obrigatório)
//   - PR #717 lição: useMemo/useCallback nos handlers descendentes (re-render loop)
//   - ServiceOrderSheet existing (PR #729) — drawer reusado pra detalhe FSM
// Visual comparison: memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { Plus, Search } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import CacambaKanbanColumn from './_components/CacambaKanbanColumn';
import type { CacambaCardData, CacambaStatus } from './_components/CacambaCard';
import ServiceOrderSheet from '../ServiceOrders/_components/ServiceOrderSheet';

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
  atrasadas: number;
  aguardando_recolhimento: number;
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

  // Drawer ServiceOrder — clicar card abre OS atual (se houver locação)
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

  // KPI inline filter bar (espelha F1.html: "17 OS · 3 aguardando aprovação")
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

  // Memoiza 5 cards arrays — só re-render se conteúdo mudar (lição PR #717)
  const columnsData = useMemo(
    () => COLUMNS.map((col) => ({
      ...col,
      cards: kanban[col.key] ?? [],
    })),
    [kanban]
  );

  return (
    <>
      <Head title="Produção · Oficina — Caçambas" />
      <div className="-m-6 bg-slate-50 min-h-[calc(100vh-3rem)]">
        {/* Topbar interno (resumo + ação) — F1 mostra "Hoje · data · Cliente" */}
        <header className="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
          <div className="flex items-center gap-3 min-w-0">
            <h1 className="text-base font-semibold text-slate-900 truncate">
              Produção · Oficina
            </h1>
            <span className="text-xs text-slate-400 whitespace-nowrap">
              Kanban estado das caçambas
            </span>
          </div>
          <div className="flex items-center gap-2 flex-shrink-0">
            <Button asChild variant="outline" size="sm">
              <Link href="/oficina-auto/veiculos">Ver lista completa</Link>
            </Button>
            <Button asChild size="sm">
              <Link href="/oficina-auto/veiculos/create">
                <Plus className="mr-1.5 h-4 w-4" />
                Nova caçamba
              </Link>
            </Button>
          </div>
        </header>

        {/* Filter bar sticky — pills capacidade + busca + KPI inline */}
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
                    part.includes('atrasada') || part.includes('aguardando')
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

        {/* Kanban 5 colunas */}
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

      {/* Drawer ServiceOrder — abre ao clicar caçamba locada (reusa Wave 7+1 PR #729) */}
      <ServiceOrderSheet
        serviceOrderId={openOsId}
        open={openOsId !== null}
        onOpenChange={handleSheetOpenChange}
        onOrderChanged={handleOrderChanged}
      />
    </>
  );
}

ProducaoOficinaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
