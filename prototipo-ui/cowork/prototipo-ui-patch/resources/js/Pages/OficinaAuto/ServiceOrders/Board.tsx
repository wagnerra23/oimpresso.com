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

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Plus, Search, LayoutGrid, List as ListIcon } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import KanbanDndProvider from '@/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider';
import DragConfirmDialog, {
  type PendingTransition,
} from '@/Pages/OficinaAuto/ProducaoOficina/_components/DragConfirmDialog';
import ServiceOrderRichSheet from '@/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet';
import ServiceOrderKanbanColumn from './_components/board/ServiceOrderKanbanColumn';
import type { ServiceOrderCardData } from './_components/board/ServiceOrderKanbanCard';
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
    { id: 'total', label: 'OS no quadro', value: String(kpis.total), tone: 'default' as const },
    { id: 'aprovacao', label: 'Aguardando aprovação', value: String(kpis.aguardando_aprovacao), tone: 'amber' as const },
    { id: 'pecas', label: 'Aguardando peças', value: String(kpis.aguardando_pecas), tone: 'violet' as const },
    { id: 'execucao', label: 'Em execução', value: String(kpis.em_execucao), tone: 'default' as const },
    { id: 'pronto', label: 'Pronto p/ retirar', value: String(kpis.pronto_retirada), tone: 'emerald' as const },
    { id: 'atrasadas', label: 'Atrasadas', value: String(kpis.atrasadas), tone: 'rose' as const },
  ], [kpis]);

  // FIX [CC] 2026-06-10: colunas com largura mínima utilizável (canon do protótipo
  // .prod-kanban: repeat(n, minmax(228px, 1fr))) — inline style em vez de classe
  // Tailwind literal porque o nº de colunas é data-driven. O wrapper rola no eixo X.
  const boardGridStyle = useMemo(() => ({
    gridTemplateColumns: `repeat(${Math.max(columns.length, 1)}, minmax(228px, 1fr))`,
  }), [columns.length]);

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
            <Button asChild size="sm">
              <Link href="/oficina-auto/ordens-servico/create">
                <Plus className="mr-1.5 h-4 w-4" /> Nova OS
              </Link>
            </Button>
          </div>
        </header>

        {/* KPIs compactos — densidade @container (@1280 expande colunas) */}
        <div className="bg-white border-b border-border px-6 py-3">
          <div className={'grid grid-cols-2 @[700px]/board:grid-cols-3 @[1100px]/board:grid-cols-6 gap-2'}>
            {kpiCards.map(({ id, ...kpi }) => <KpiCard key={id} {...kpi} />)}
          </div>
        </div>

        {/* Filtro busca + selects compactos Mecânico/Box */}
        <div className="bg-white border-b border-border px-6 py-2.5 flex items-center gap-3 sticky top-0 z-10 flex-wrap">
          <div className="flex items-center gap-2 flex-1 min-w-[240px] max-w-md">
            <Search size={14} className="text-muted-foreground flex-shrink-0" />
            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar OS, placa ou cliente…"
              className="h-8 border-border"
              aria-label="Buscar OS, placa ou cliente"
            />
          </div>

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
            <span className="font-medium text-foreground">{kpis.total} {kpis.total === 1 ? 'OS' : 'OS'}</span>
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
                {columns.map((col) => (
                  <ServiceOrderKanbanColumn
                    key={col.key}
                    stageKey={col.key}
                    name={col.name}
                    color={col.color}
                    cards={col.cards}
                    emphasis={col.key === 'aguardando_aprovacao' ? 'aprovacao' : col.key === 'aguardando_pecas' ? 'pecas' : null}
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

interface KpiCardProps { label: string; value: string; tone: KpiTone; }

function KpiCard({ label, value, tone }: KpiCardProps) {
  const t = kpiTone(tone);
  return (
    <div className={`rounded-lg border px-3 py-2 flex flex-col gap-0.5 ${t.wrapper}`}>
      <span className={`text-[10px] font-semibold uppercase tracking-wider truncate ${t.label}`}>{label}</span>
      <span className={`text-xl @[1100px]/board:text-2xl font-bold tabular-nums ${t.value}`}>{value}</span>
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
