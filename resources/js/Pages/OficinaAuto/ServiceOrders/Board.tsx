// @memcofre tela=/oficina-auto/ordens-servico/board module=OficinaAuto
// Board (Kanban) das OS de MECÂNICA — fluxo real do carro (caminhão entra pra
// manutenção/troca de peça). Port do protótipo Cowork "oficina-page.jsx" (o wow da
// homologação da Kamila), confirmado por [W] 2026-06-02.
//
// NÃO confundir com ProducaoOficina (caçamba — vertical legado/equívoco ADR 0194).
// Colunas data-driven pelas etapas reais do FSM `oficina_mecanica_os`:
//   Recepção → Diagnóstico → Aguardando aprovação → Aguardando peças →
//   Em execução → Pronto p/ retirar.
//
// REUSA o canon (estender, não recriar — §10.4):
//   - KanbanDndProvider + DragConfirmDialog (drag dnd-kit + confirmação FSM)
//   - ServiceOrderRichSheet (drawer rico polimórfico, embute FsmActionPanel + DviPhotoGrid)
//   - MercosulPlate (via ServiceOrderKanbanCard)
//
// Drag entre colunas dispara FSM via ServiceOrderFsmActionController@execute
// (ExecuteStageActionService) — NUNCA UPDATE direto em current_stage_id (canon GUARD).
//
// Modificações [W]-aceitas: foto real no card · contador DVI x/y checklist ·
// densidade compacta @container @1280 · "N OS" + distinção aguardando-peças × aprovação.
//
// Lição PR #717 — useMemo/useCallback nos handlers descendentes.
//
// FIX layout [CC] 2026-06-10 (reportado por [W] com screenshot — board cortado):
//   1. Removido `-m-6` do root: AppShellV2 `.main-body` NÃO tem p-6 (é flex column
//      sem padding — cockpit.css linha ~180). A margem negativa criava overflow
//      INALCANÇÁVEL à esquerda/topo do scroll container → header/KPIs/1ª coluna
//      permanentemente cortados embaixo da sidebar.
//   2. Removido `min-h-[calc(100vh-3rem)]`: assumia topbar de 3rem, mas
//      hideTopbar default = true desde 2026-05-17. Substituído por `flex-1`.
//   3. Grid do quadro: `grid-cols-6` (1fr puro, min-content estoura a viewport)
//      → `repeat(n, minmax(228px, 1fr))` + wrapper com overflow-x-auto.
//      Espelha o canon do protótipo Cowork (.prod-kanban.ofc-5 em
//      oficina-page.css: repeat(5, minmax(228px, 1fr)) + overflow auto):
//      o quadro rola HORIZONTALMENTE POR DENTRO, o shell nunca estoura.
//
// ONDA 1 toolbar [CC] 2026-06-10 — paridade com o protótipo Cowork homologado:
//   - KPIs clicáveis como filtro client-side (canon D-05) + chip "limpar filtro";
//   - menu Visão (.ofc-adjust): Foco Etapa/Box/Mecânico (re-pivot client-side,
//     drag desliga fora do foco Etapa) + Densidade compacto/padrão/detalhe,
//     persistidos em localStorage. Pressão ficou FORA desta onda;
//   - busca com botão limpar (×) + atalho "/";
//   - atalhos de teclado (canon D-07): N nova OS · / busca · setas navegam ·
//     Enter abre drawer · Esc fecha (ignorados enquanto digita);
//   - "Imprimir fila" no header (ghost) — printOficinaFila.ts via mecanismo
//     canônico printHtmlDocument (port do oficina-print.js);
//   - coluna Em execução com ocupação "x/y boxes" no header.
//   Onda 2 (PR separado): views Grade e Fila no toggle.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Plus, Search, LayoutGrid, List as ListIcon, Printer, SlidersHorizontal, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Segmented } from '@/Components/ui/segmented';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { printOficinaFila, type FilaPrintRow } from '@/Lib/printOficinaFila';
import KanbanDndProvider from '@/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider';
import DragConfirmDialog, {
  type PendingTransition,
} from '@/Pages/OficinaAuto/ProducaoOficina/_components/DragConfirmDialog';
import ServiceOrderRichSheet from '@/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet';
import ServiceOrderKanbanColumn from './_components/board/ServiceOrderKanbanColumn';
import type { BoardDensity, ServiceOrderCardData } from './_components/board/ServiceOrderKanbanCard';
import { kpiTone, type KpiTone } from './_components/board/boardTone';

// ─── Types ───────────────────────────────────────────────────────────────────

interface BoardColumn {
  key: string;
  name: string;
  color: string | null;
  cards: ServiceOrderCardData[];
  count: number;
}

interface BoardKpis {
  total: number;
  aguardando_aprovacao: number;
  aguardando_pecas: number;
  em_execucao: number;
  pronto_retirada: number;
  atrasadas: number;
}

interface MecanicoOption {
  id: number;
  nome: string;
}

interface Props {
  columns: BoardColumn[];
  kpis: BoardKpis;
  process_seeded: boolean;
  filters: { q: string; mecanico: number | null; box: string | null };
  filterOptions: { boxes: string[]; mecanicos: MecanicoOption[] };
}

// ─── Onda 1 paridade Cowork (canon D-05/D-07 + menu Visão) ───────────────────
// KPI clicável filtra cards client-side (sem round-trip); menu Visão re-pivota
// as colunas por Box/Mecânico e controla densidade; atalhos de teclado pra
// Larissa (teclado-first). Pressão ficou FORA desta onda (decisão [W]).

/** KPIs filtráveis → predicado (stage do card ou atraso). 'total' não filtra. */
type KpiFilterKey = 'aprovacao' | 'pecas' | 'execucao' | 'pronto' | 'atrasadas';

const KPI_FILTER_STAGE: Record<Exclude<KpiFilterKey, 'atrasadas'>, string> = {
  aprovacao: 'aguardando_aprovacao',
  pecas: 'aguardando_pecas',
  execucao: 'em_execucao',
  pronto: 'pronto_retirada',
};

const KPI_FILTER_LABEL: Record<KpiFilterKey, string> = {
  aprovacao: 'Aguardando aprovação',
  pecas: 'Aguardando peças',
  execucao: 'Em execução',
  pronto: 'Pronto p/ retirar',
  atrasadas: 'Atrasadas',
};

type BoardFoco = 'etapa' | 'box' | 'mecanico';

const FOCO_STORAGE_KEY = 'oficinaBoard.foco';
const DENSIDADE_STORAGE_KEY = 'oficinaBoard.densidade';

/** Coluna exibida no quadro — etapa FSM (foco Etapa) ou pivot Box/Mecânico. */
interface DisplayColumn {
  key: string;
  name: string;
  color: string | null;
  cards: ServiceOrderCardData[];
  emphasis: 'aprovacao' | 'pecas' | null;
  capacity: string | null;
}

// ─── Drag mapping FSM (espelha oficina_mecanica_os do OficinaAutoFsmSeeder) ────
// Mantido em sincronia com o seeder (mesma estratégia do Kanban de caçamba).
// Backward/transições não-listadas são bloqueadas com toast.

interface AllowedMove {
  actionKey: string;
  actionLabel: string;
  isCritical: boolean;
  title: string;
  description: string;
}

const STAGE_TRANSITIONS: Record<string, Record<string, AllowedMove>> = {
  recepcao: {
    em_diagnostico: {
      actionKey: 'iniciar_diagnostico', actionLabel: 'Iniciar diagnóstico', isCritical: false,
      title: 'Iniciar diagnóstico?', description: 'O veículo entra em diagnóstico técnico.',
    },
  },
  em_diagnostico: {
    aguardando_aprovacao: {
      actionKey: 'enviar_orcamento', actionLabel: 'Enviar orçamento', isCritical: false,
      title: 'Enviar orçamento pra aprovação?', description: 'A OS vai aguardar o OK do cliente sobre o orçamento.',
    },
  },
  aguardando_aprovacao: {
    aguardando_pecas: {
      actionKey: 'aprovar_pedir_pecas', actionLabel: 'Aprovar e pedir peças', isCritical: true,
      title: 'Cliente aprovou o orçamento?', description: 'Registra aprovação e move pra aguardar a chegada das peças.',
    },
    em_execucao: {
      actionKey: 'aprovar_executar', actionLabel: 'Aprovar e executar', isCritical: true,
      title: 'Cliente aprovou — já executar?', description: 'Aprova o orçamento e inicia a execução (peças em estoque).',
    },
  },
  aguardando_pecas: {
    em_execucao: {
      actionKey: 'pecas_chegaram', actionLabel: 'Peças chegaram', isCritical: false,
      title: 'Peças chegaram?', description: 'Confirma a chegada das peças e inicia a execução do serviço.',
    },
  },
  em_execucao: {
    pronto_retirada: {
      actionKey: 'concluir_servico', actionLabel: 'Concluir serviço', isCritical: true,
      title: 'Concluir o serviço?', description: 'Marca a OS como pronta pro cliente retirar.',
    },
  },
};

// ─── Component ─────────────────────────────────────────────────────────────

export default function ServiceOrdersBoard({ columns, kpis, process_seeded, filters, filterOptions }: Props) {
  const [searchInput, setSearchInput] = useState(filters.q ?? '');
  const searchRef = useRef<HTMLInputElement | null>(null);

  // D-05 — filtro por KPI (client-side, clicar de novo limpa)
  const [kpiFilter, setKpiFilter] = useState<KpiFilterKey | null>(null);
  // D-07 — card focado pela navegação por setas (anel visível)
  const [focusedId, setFocusedId] = useState<number | null>(null);

  // Menu Visão — foco (pivot das colunas) + densidade, persistidos por operador
  const [foco, setFocoState] = useState<BoardFoco>(() => {
    const v = typeof window !== 'undefined' ? window.localStorage.getItem(FOCO_STORAGE_KEY) : null;
    return v === 'box' || v === 'mecanico' ? v : 'etapa';
  });
  const [densidade, setDensidadeState] = useState<BoardDensity>(() => {
    const v = typeof window !== 'undefined' ? window.localStorage.getItem(DENSIDADE_STORAGE_KEY) : null;
    return v === 'compacto' || v === 'detalhe' ? v : 'padrao';
  });
  const setFoco = useCallback((v: BoardFoco) => {
    setFocoState(v);
    setFocusedId(null);
    try { window.localStorage.setItem(FOCO_STORAGE_KEY, v); } catch { /* storage cheio/bloqueado — preferência só não persiste */ }
  }, []);
  const setDensidade = useCallback((v: BoardDensity) => {
    setDensidadeState(v);
    try { window.localStorage.setItem(DENSIDADE_STORAGE_KEY, v); } catch { /* idem */ }
  }, []);

  const kpiClick = useCallback((key: KpiFilterKey) => {
    setKpiFilter((f) => (f === key ? null : key));
    setFocusedId(null);
  }, []);

  // Navega pro board mesclando os filtros atuais com o patch (query — canon charter).
  // Limpa chaves vazias pra não poluir a URL ('' / null / undefined caem fora).
  const applyBoardFilter = useCallback(
    (patch: { q?: string; mecanico?: number | null; box?: string | null }) => {
      const next: Record<string, string | number> = {};
      const q = patch.q ?? filters.q ?? '';
      const mecanico = patch.mecanico !== undefined ? patch.mecanico : filters.mecanico;
      const box = patch.box !== undefined ? patch.box : filters.box;
      if (q) next.q = q;
      if (mecanico) next.mecanico = mecanico;
      if (box) next.box = box;
      router.get('/oficina-auto/ordens-servico/board', next, {
        preserveState: true, preserveScroll: true, replace: true,
      });
    },
    [filters.q, filters.mecanico, filters.box],
  );

  useEffect(() => {
    if (searchInput === (filters.q ?? '')) return;
    const t = setTimeout(() => {
      applyBoardFilter({ q: searchInput });
    }, 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  // Drawer rico (reusado)
  const [openOsId, setOpenOsId] = useState<number | null>(null);
  const handleCardClick = useCallback((c: ServiceOrderCardData) => setOpenOsId(c.id), []);
  const handleSheetOpenChange = useCallback((open: boolean) => { if (!open) setOpenOsId(null); }, []);
  const reloadBoard = useCallback(() => {
    router.reload({ only: ['columns', 'kpis'] });
  }, []);

  // Drag → confirmação → FSM execute
  const [pending, setPending] = useState<PendingTransition | null>(null);
  const [loading, setLoading] = useState(false);

  const handleDragMove = useCallback(
    (orderId: number, from: string, to: string, card: ServiceOrderCardData) => {
      if (!card.in_pipeline) {
        toast.warning('OS sem pipeline iniciado — abra a OS pra iniciar antes de mover.');
        return;
      }
      const move = STAGE_TRANSITIONS[from]?.[to];
      if (!move) {
        toast.warning(`Transição não permitida (${from} → ${to}).`);
        return;
      }
      setPending({
        subjectId: orderId, // o endpoint /fsm/execute usa o id da OS
        fromColumn: from,
        toColumn: to,
        actionKey: move.actionKey,
        actionLabel: move.actionLabel,
        isCritical: move.isCritical,
        title: move.title,
        description: move.description,
        plate: card.plate ?? undefined,
        cliente_nome: card.cliente_nome,
        subjectLabel: 'Veículo',
      });
    },
    [],
  );

  const handleConfirm = useCallback(async () => {
    if (!pending) return;
    setLoading(true);
    try {
      const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content;
      const res = await fetch(`/oficina-auto/service-orders/${pending.subjectId}/fsm/execute`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ action_key: pending.actionKey }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        toast.error(json?.error ?? `Falha HTTP ${res.status}`);
        setLoading(false);
        return;
      }
      toast.success(`Etapa aplicada: ${pending.actionLabel}`);
      setPending(null);
      reloadBoard();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Erro ao executar transição');
    } finally {
      setLoading(false);
    }
  }, [pending, reloadBoard]);

  const handleCancel = useCallback(() => { if (!loading) setPending(null); }, [loading]);

  // Preview do drag (sem a palavra "Caçamba")
  const renderPreview = useCallback((c: ServiceOrderCardData) => (
    <div className="bg-white border-2 border-primary rounded shadow-lg p-3 max-w-[260px] cursor-grabbing rotate-2 opacity-95" role="presentation" aria-hidden>
      <div className="flex items-center gap-2">
        {c.plate ? (
          <span className="font-mono text-[11px] bg-primary text-white px-1.5 py-0.5 rounded">{c.plate}</span>
        ) : null}
        <div className="flex flex-col min-w-0">
          <span className="text-[12.5px] font-medium text-foreground truncate">{c.number}</span>
          {c.cliente_nome ? <span className="text-[10.5px] text-muted-foreground truncate">{c.cliente_nome}</span> : null}
        </div>
      </div>
    </div>
  ), []);

  const kpiCards = useMemo(() => [
    { id: 'total', label: 'OS no quadro', value: String(kpis.total), tone: 'default' as const, filterKey: null },
    { id: 'aprovacao', label: 'Aguardando aprovação', value: String(kpis.aguardando_aprovacao), tone: 'amber' as const, filterKey: 'aprovacao' as const },
    { id: 'pecas', label: 'Aguardando peças', value: String(kpis.aguardando_pecas), tone: 'violet' as const, filterKey: 'pecas' as const },
    { id: 'execucao', label: 'Em execução', value: String(kpis.em_execucao), tone: 'default' as const, filterKey: 'execucao' as const },
    { id: 'pronto', label: 'Pronto p/ retirar', value: String(kpis.pronto_retirada), tone: 'emerald' as const, filterKey: 'pronto' as const },
    { id: 'atrasadas', label: 'Atrasadas', value: String(kpis.atrasadas), tone: 'rose' as const, filterKey: 'atrasadas' as const },
  ], [kpis]);

  // D-05 — predicado do KPI ativo sobre (card, etapa). Client-side: o payload do
  // board já está no browser; filtrar não round-tripa.
  const cardMatchesKpi = useCallback((card: ServiceOrderCardData, stageKey: string): boolean => {
    if (!kpiFilter) return true;
    if (kpiFilter === 'atrasadas') return card.is_overdue;
    return stageKey === KPI_FILTER_STAGE[kpiFilter];
  }, [kpiFilter]);

  // Etapa de origem de cada card (sobrevive ao pivot Box/Mecânico — usada no
  // filtro por KPI de etapa e na folha "Imprimir fila").
  const stageByCardId = useMemo(() => {
    const m = new Map<number, { key: string; name: string; index: number }>();
    columns.forEach((col, index) => col.cards.forEach((c) => m.set(c.id, { key: col.key, name: col.name, index })));
    return m;
  }, [columns]);

  // Colunas exibidas — foco Etapa usa as colunas FSM; Box/Mecânico re-pivota
  // client-side (cards já carregam box + mechanic_id do BoardController).
  const displayColumns = useMemo<DisplayColumn[]>(() => {
    if (foco === 'etapa') {
      return columns.map((col) => ({
        key: col.key,
        name: col.name,
        color: col.color,
        cards: col.cards.filter((c) => cardMatchesKpi(c, col.key)),
        emphasis: col.key === 'aguardando_aprovacao' ? 'aprovacao' as const : col.key === 'aguardando_pecas' ? 'pecas' as const : null,
        // Capacidade da oficina (header da coluna Em execução): ocupação REAL,
        // por isso usa col.cards (pré-filtro KPI) — y = boxes cadastrados.
        capacity: col.key === 'em_execucao' && filterOptions.boxes.length > 0
          ? `${col.cards.length}/${filterOptions.boxes.length} boxes`
          : null,
      }));
    }

    const visiveis = columns.flatMap((col) => col.cards.filter((c) => cardMatchesKpi(c, col.key)));
    const pivotCol = (key: string, name: string, cards: ServiceOrderCardData[]): DisplayColumn => ({
      key, name, color: null, cards, emphasis: null, capacity: null,
    });

    if (foco === 'box') {
      return [
        ...filterOptions.boxes.map((b) => pivotCol(`box-${b}`, b, visiveis.filter((c) => c.box === b))),
        pivotCol('box-none', 'Sem box', visiveis.filter((c) => !c.box)),
      ];
    }

    return [
      ...filterOptions.mecanicos.map((m) => pivotCol(`mec-${m.id}`, m.nome, visiveis.filter((c) => c.mechanic_id === m.id))),
      pivotCol('mec-none', 'Sem mecânico', visiveis.filter((c) => c.mechanic_id === null)),
    ];
  }, [columns, foco, cardMatchesKpi, filterOptions.boxes, filterOptions.mecanicos]);

  // Ordem visível dos cards (coluna a coluna) — navegação por setas + contador "N OS"
  const visibleCards = useMemo(() => displayColumns.flatMap((c) => c.cards), [displayColumns]);
  const visibleCount = visibleCards.length;

  // D-07 — atalhos de teclado (Larissa é teclado-first): N nova OS · / busca ·
  // setas navegam cards · Enter abre o drawer · Esc limpa o foco (Radix já fecha
  // drawer/popover no Esc). Ignora quando digitando em input/select/textarea.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      // dnd-kit KeyboardSensor usa as setas durante o drag — não atropelar.
      if (e.defaultPrevented) return;
      const target = e.target as HTMLElement | null;
      const tag = (target?.tagName ?? '').toLowerCase();
      const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || Boolean(target?.isContentEditable);
      if (e.key === 'Escape') { setFocusedId(null); return; }
      if (typing) return;
      if (e.key === '/') { e.preventDefault(); searchRef.current?.focus(); return; }
      if (e.key === 'n' || e.key === 'N') { e.preventDefault(); router.visit('/oficina-auto/ordens-servico/create'); return; }
      if (e.key === 'Enter') {
        if (focusedId !== null && visibleCards.some((c) => c.id === focusedId)) {
          e.preventDefault();
          setOpenOsId(focusedId);
        }
        return;
      }
      if (e.key === 'ArrowDown' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
        if (!visibleCards.length) return;
        e.preventDefault();
        const dir = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : -1;
        setFocusedId((prev) => {
          const i = visibleCards.findIndex((c) => c.id === prev);
          const next = i === -1
            ? visibleCards[0]
            : visibleCards[Math.min(visibleCards.length - 1, Math.max(0, i + dir))];
          return next ? next.id : prev;
        });
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [visibleCards, focusedId]);

  // Imprimir fila — folha A4 com as OS visíveis (busca + KPI + selects aplicados)
  const handlePrintFila = useCallback(() => {
    const rows: FilaPrintRow[] = visibleCards.map((c) => {
      const stage = stageByCardId.get(c.id);
      const prazo = c.expected_completion ? new Date(c.expected_completion) : null;
      return {
        number: c.number,
        etapa: stage?.name ?? '—',
        etapaIndex: stage?.index ?? 99,
        vehicle: c.vehicle_type,
        plate: c.plate,
        cliente: c.cliente_nome,
        mecanico: c.mechanic_name,
        box: c.box,
        prazo: prazo && !Number.isNaN(prazo.getTime())
          ? prazo.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
          : null,
        valor: c.valor,
        atrasada: c.is_overdue,
      };
    });
    const filtroParts: string[] = [];
    if (filters.q) filtroParts.push(`busca "${filters.q}"`);
    if (kpiFilter) filtroParts.push(KPI_FILTER_LABEL[kpiFilter]);
    if (filters.mecanico) {
      const m = filterOptions.mecanicos.find((x) => x.id === filters.mecanico);
      if (m) filtroParts.push(`mecânico ${m.nome}`);
    }
    if (filters.box) filtroParts.push(`box ${filters.box}`);
    toast.info('Preparando impressão da fila…');
    printOficinaFila(rows, { filtro: filtroParts.length ? filtroParts.join(' · ') : null })
      .catch((e: unknown) => toast.error(e instanceof Error ? e.message : 'Falha ao imprimir a fila'));
  }, [visibleCards, stageByCardId, filters.q, filters.mecanico, filters.box, kpiFilter, filterOptions.mecanicos]);

  // FIX [CC] 2026-06-10: colunas com largura mínima utilizável (canon do protótipo
  // .prod-kanban: repeat(n, minmax(228px, 1fr))) — inline style em vez de classe
  // Tailwind literal porque o nº de colunas é data-driven. O wrapper rola no eixo X.
  const boardGridStyle = useMemo(() => ({
    gridTemplateColumns: `repeat(${Math.max(displayColumns.length, 1)}, minmax(228px, 1fr))`,
  }), [displayColumns.length]);

  return (
    <>
      <Head title="Oficina · Quadro de OS" />
      <div className="flex-1 bg-muted/40 @container/board">
        {/* Topbar */}
        <header className="bg-white border-b border-border px-6 py-4 flex items-start justify-between gap-4 flex-wrap">
          <div className="min-w-0">
            <h1 className="text-lg font-semibold text-foreground">Oficina · Quadro de OS</h1>
            <p className="text-xs text-muted-foreground mt-0.5">Fluxo de reparo do veículo — da recepção à retirada</p>
          </div>
          <div className="flex items-center gap-2 flex-shrink-0 flex-wrap">
            <div className="inline-flex rounded border border-border bg-white overflow-hidden" role="group" aria-label="Visualização">
              <button className="px-2.5 py-1 text-xs font-medium bg-primary text-white inline-flex items-center gap-1" disabled aria-pressed="true">
                <LayoutGrid size={12} /> Quadro
              </button>
              <Link href="/oficina-auto/ordens-servico" className="px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted inline-flex items-center gap-1">
                <ListIcon size={12} /> Lista
              </Link>
            </div>

            {/* Menu Visão (canon .ofc-adjust do protótipo) — foco + densidade.
                Pressão ficou FORA desta onda. */}
            <Popover>
              <PopoverTrigger asChild>
                <Button
                  variant="outline"
                  size="sm"
                  className={foco !== 'etapa' || densidade !== 'padrao' ? 'border-primary text-primary' : undefined}
                  title="Ajustar visão (foco · densidade)"
                  data-testid="board-visao-trigger"
                >
                  <SlidersHorizontal className="mr-1.5 h-3.5 w-3.5" /> Visão
                </Button>
              </PopoverTrigger>
              <PopoverContent align="end" className="w-72 space-y-3" role="menu" aria-label="Ajustar visão">
                <div className="space-y-1.5">
                  <span className="block text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Foco</span>
                  <Segmented
                    value={foco}
                    onValueChange={(v) => setFoco(v as BoardFoco)}
                    options={[
                      { value: 'etapa', label: 'Etapa' },
                      { value: 'box', label: 'Box', disabled: filterOptions.boxes.length === 0 },
                      { value: 'mecanico', label: 'Mecânico', disabled: filterOptions.mecanicos.length === 0 },
                    ]}
                    aria-label="Foco das colunas"
                  />
                </div>
                <div className="space-y-1.5">
                  <span className="block text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Densidade</span>
                  <Segmented
                    value={densidade}
                    onValueChange={(v) => setDensidade(v as BoardDensity)}
                    options={[
                      { value: 'compacto', label: 'Compacto' },
                      { value: 'padrao', label: 'Padrão' },
                      { value: 'detalhe', label: 'Detalhe' },
                    ]}
                    aria-label="Densidade dos cards"
                  />
                </div>
                {foco !== 'etapa' && (
                  <p className="text-[11px] text-muted-foreground leading-snug">
                    No foco {foco === 'box' ? 'Box' : 'Mecânico'} o arraste fica desligado — etapas mudam no foco Etapa ou pelo drawer da OS.
                  </p>
                )}
              </PopoverContent>
            </Popover>

            <Button variant="ghost" size="sm" onClick={handlePrintFila} data-testid="board-print-fila">
              <Printer className="mr-1.5 h-3.5 w-3.5" /> Imprimir fila
            </Button>
            <Button asChild size="sm">
              <Link href="/oficina-auto/ordens-servico/create">
                <Plus className="mr-1.5 h-4 w-4" /> Nova OS
              </Link>
            </Button>
          </div>
        </header>

        {/* KPIs compactos — densidade @container (@1280 expande colunas).
            D-05: KPI clicável filtra os cards client-side (clicar de novo limpa). */}
        <div className="bg-white border-b border-border px-6 py-3">
          <div className={'grid grid-cols-2 @[700px]/board:grid-cols-3 @[1100px]/board:grid-cols-6 gap-2'}>
            {kpiCards.map(({ id, filterKey, ...kpi }) => (
              <KpiCard
                key={id}
                {...kpi}
                active={filterKey !== null && kpiFilter === filterKey}
                dimmed={kpiFilter !== null && filterKey !== null && kpiFilter !== filterKey}
                onClick={filterKey !== null ? () => kpiClick(filterKey) : undefined}
              />
            ))}
          </div>
        </div>

        {/* Filtro busca + selects compactos Mecânico/Box */}
        <div className="bg-white border-b border-border px-6 py-2.5 flex items-center gap-3 sticky top-0 z-10 flex-wrap">
          <div className="flex items-center gap-2 flex-1 min-w-[240px] max-w-md">
            <Search size={14} className="text-muted-foreground flex-shrink-0" />
            <div className="relative flex-1">
              <Input
                ref={searchRef}
                type="search"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Buscar OS, placa ou cliente…  ( / )"
                className="h-8 border-border pr-7"
                aria-label="Buscar OS, placa ou cliente (atalho /)"
                aria-keyshortcuts="/"
              />
              {searchInput !== '' && (
                <button
                  type="button"
                  className="absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted"
                  onClick={() => setSearchInput('')}
                  aria-label="Limpar busca"
                  data-testid="board-search-clear"
                >
                  <X size={12} />
                </button>
              )}
            </div>
          </div>

          {/* D-05 — chip do KPI-filtro ativo (clicar limpa) */}
          {kpiFilter && (
            <button
              type="button"
              className="inline-flex items-center gap-1 text-[11px] font-medium text-primary bg-primary/10 border border-primary/30 rounded-full px-2 py-0.5 hover:bg-primary/15"
              onClick={() => kpiClick(kpiFilter)}
              data-testid="board-kpi-clear"
            >
              <X size={10} /> limpar filtro: {KPI_FILTER_LABEL[kpiFilter]}
            </button>
          )}

          {filterOptions.mecanicos.length > 0 && (
            <Select
              value={filters.mecanico ? String(filters.mecanico) : 'all'}
              onValueChange={(v) => applyBoardFilter({ mecanico: v === 'all' ? null : Number(v) })}
            >
              <SelectTrigger className="h-8 w-auto gap-1.5 text-xs" aria-label="Filtrar por mecânico">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Mecânico: todos</SelectItem>
                {filterOptions.mecanicos.map((m) => (
                  <SelectItem key={m.id} value={String(m.id)}>{m.nome}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}

          {filterOptions.boxes.length > 0 && (
            <Select
              value={filters.box ?? 'all'}
              onValueChange={(v) => applyBoardFilter({ box: v === 'all' ? null : v })}
            >
              <SelectTrigger className="h-8 w-auto gap-1.5 text-xs" aria-label="Filtrar por box">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Box: todos</SelectItem>
                {filterOptions.boxes.map((b) => (
                  <SelectItem key={b} value={b}>{b}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}

          <span className="ml-auto text-sm text-muted-foreground" aria-live="polite">
            <span className="font-medium text-foreground">{visibleCount} OS</span>
            {kpiFilter && (<span className="ml-1 text-xs">de {kpis.total}</span>)}
            {kpis.atrasadas > 0 && (<><span className="mx-1.5 text-muted-foreground">·</span><span className="font-medium text-destructive">{kpis.atrasadas} atrasada{kpis.atrasadas === 1 ? '' : 's'}</span></>)}
          </span>
        </div>

        {/* Quadro — overflow-x-auto: o kanban rola por dentro, o shell nunca estoura
            (espelha .prod-kanban do protótipo: overflow auto + minmax(228px, 1fr)) */}
        <div className="p-6 overflow-x-auto">
          {!process_seeded ? (
            <EmptyProcessState />
          ) : (
            <KanbanDndProvider<ServiceOrderCardData, string> onMove={handleDragMove} renderPreview={renderPreview}>
              <div className="grid gap-4 items-start" style={boardGridStyle}>
                {displayColumns.map((col) => (
                  <ServiceOrderKanbanColumn
                    key={col.key}
                    stageKey={col.key}
                    name={col.name}
                    color={col.color}
                    cards={col.cards}
                    emphasis={col.emphasis}
                    capacity={col.capacity}
                    density={densidade}
                    focusedId={focusedId}
                    dragDisabled={foco !== 'etapa'}
                    onCardClick={handleCardClick}
                  />
                ))}
              </div>
            </KanbanDndProvider>
          )}
        </div>
      </div>

      {/* Drawer rico reusado (embute FsmActionPanel + DviPhotoGrid) */}
      <ServiceOrderRichSheet
        serviceOrderId={openOsId}
        open={openOsId !== null}
        onOpenChange={handleSheetOpenChange}
        onOrderChanged={reloadBoard}
      />

      {/* Confirmação da transição FSM via drag */}
      <DragConfirmDialog pending={pending} loading={loading} onConfirm={handleConfirm} onCancel={handleCancel} />
    </>
  );
}

ServiceOrdersBoard.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

interface KpiCardProps {
  label: string;
  value: string;
  tone: KpiTone;
  /** D-05 — KPI ativo como filtro (anel primary + aria-pressed) */
  active?: boolean;
  /** D-05 — outro KPI está filtrando (esmaece este) */
  dimmed?: boolean;
  /** presente = KPI filtrável (vira role=button); ausente = só leitura (ex.: total) */
  onClick?: () => void;
}

function KpiCard({ label, value, tone, active = false, dimmed = false, onClick }: KpiCardProps) {
  const t = kpiTone(tone);

  const inner = (
    <>
      <span className={`text-[10px] font-semibold uppercase tracking-wider truncate ${t.label}`}>{label}</span>
      <span className={`text-xl @[1100px]/board:text-2xl font-bold tabular-nums ${t.value}`}>{value}</span>
    </>
  );

  // KPI filtrável é <button> de verdade (a11y nativa: foco, Enter/Space, aria-pressed)
  if (onClick !== undefined) {
    return (
      <button
        type="button"
        className={
          `rounded-lg border px-3 py-2 flex flex-col gap-0.5 text-left w-full cursor-pointer select-none transition-all hover:shadow-sm ${t.wrapper}`
          + (active ? ' ring-2 ring-primary ring-offset-1' : '')
          + (dimmed ? ' opacity-50' : '')
        }
        aria-pressed={active}
        title={active ? 'Clique pra limpar o filtro' : `Filtrar o quadro: ${label}`}
        onClick={onClick}
      >
        {inner}
      </button>
    );
  }

  return (
    <div className={`rounded-lg border px-3 py-2 flex flex-col gap-0.5 ${t.wrapper}`}>
      {inner}
    </div>
  );
}

function EmptyProcessState() {
  return (
    <div className="rounded-lg border border-dashed border-border bg-white p-10 text-center max-w-lg mx-auto">
      <p className="text-sm font-medium text-foreground">Quadro ainda não configurado</p>
      <p className="text-xs text-muted-foreground mt-1 leading-relaxed">
        O processo FSM <code className="font-mono">oficina_mecanica_os</code> não está cadastrado pra este negócio.
        Rode o seeder <code className="font-mono">OficinaAutoFsmSeeder</code> pra ativar as etapas do quadro.
      </p>
    </div>
  );
}
