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
//
// ONDA 1.5 paridade total [CC] 2026-06-10 (pedido [W] "era para ser assim" — gap
// vs protótipo Cowork) — fecha o que a checklist da Onda 1 não enumerou:
//   - KPIs com SUBLINHA + set do protótipo: Recepção · Em diagnóstico · Aguardando
//     peças · Em execução · Urgentes · Valor em curso (faturamento previsto = soma
//     do valor das OS não-terminais). 5 filtráveis + valor só-leitura;
//   - ABAS de box/elevador (.prod-equip-filters) substituem os dropdowns Box/Mecânico
//     — filtro client-side instantâneo com contador por box ("Todos N | Box 1 (n)…");
//   - card RICO: km de entrada · barra de progresso (% DVI decidido) · linha "últ."
//     (última transição FSM auditada, dado real do sale_stage_history) · BOTÃO de
//     ação primária por etapa (Triagem→/Enviar orçamento→/Peças chegaram→/Concluir→/
//     Entregar→). O botão é a 2ª PORTA do MESMO ExecuteStageActionService do drag
//     (reusa pending + DragConfirmDialog) — aguardando_aprovacao abre o drawer
//     (2 saídas críticas + gate), pronto_retirada→entregue é TERMINAL (o drag não
//     faz terminal — Non-Goal charter; o botão faz, charter emendado v3).
//   Sem dado fake (gate no-mock-in-prod): ETA-diag / "Encomendado: peça chega X" /
//   "Pago" do protótipo NÃO têm campo real → omitidos (documentado no charter/PR).
//
// ONDA 2 + polish toolbar [CC] 2026-06-11 (pedido [W] "fecha paridade Cowork"):
//   1. Toggle + Visão MIGRARAM do header pra BARRA DA BUSCA (canon .ofc-view-toolbar):
//      [busca + contador] | [toggle] | [Visão]. Header fica só [Imprimir fila] [Nova OS].
//   2. Toggle vira Quadro · Lista · Grade · Fila. Quadro/Grade são in-page (estado
//      `view` persistido em localStorage oficinaBoard.view); Lista/Fila NAVEGAM pra
//      Index (Fila = ?view=fila — página já existe, NÃO duplica).
//   3. GRADE (BoardGrade): varredura client-side veículo × etapa — linha = OS
//      (MercosulPlate + modelo + cliente), colunas = etapas do payload `columns`,
//      marca na célula da etapa atual (tom da coluna + glifo semântico gradeGlyph),
//      legenda data-driven. Respeita busca + KPI-filtro + aba de box (cardVisible);
//      independe do foco (Box/Mecânico é pivot só do Quadro). Sem heurística fake.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Plus, Search, LayoutGrid, List as ListIcon, Table2, ListOrdered, Printer, SlidersHorizontal, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Segmented } from '@/Components/ui/segmented';
import { Inline } from '@/Components/layout';
import { printOficinaFila, type FilaPrintRow } from '@/Lib/printOficinaFila';
import MercosulPlate from '@/Components/shared/MercosulPlate';
import KanbanDndProvider from '@/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider';
import DragConfirmDialog, {
  type PendingTransition,
} from '@/Pages/OficinaAuto/ProducaoOficina/_components/DragConfirmDialog';
import ServiceOrderRichSheet, { ServiceOrderRichBody } from '@/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet';
import ServiceOrderKanbanColumn from './_components/board/ServiceOrderKanbanColumn';
import BoardKpiCard from './_components/board/BoardKpiCard';
import type { BoardDensity, ServiceOrderCardData } from './_components/board/ServiceOrderKanbanCard';
import { toneForColor, gradeGlyph } from './_components/board/boardTone';

// Info de etapa por card (do stageByCardId) — compartilhada pelas views Lista/Fila.
type StageInfo = { key: string; name: string; color: string | null; index: number };
type StageInfoMap = Map<number, StageInfo>;

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
  recepcao: number;
  em_diagnostico: number;
  aguardando_aprovacao: number;
  aguardando_pecas: number;
  em_execucao: number;
  pronto_retirada: number;
  atrasadas: number;
  valor_em_curso: number;
  boxes_total: number;
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

/** KPIs filtráveis → predicado (stage do card ou urgência). 'valor' não filtra. */
type KpiFilterKey = 'recepcao' | 'diagnostico' | 'pecas' | 'execucao' | 'urgentes';

const KPI_FILTER_STAGE: Record<Exclude<KpiFilterKey, 'urgentes'>, string> = {
  recepcao: 'recepcao',
  diagnostico: 'em_diagnostico',
  pecas: 'aguardando_pecas',
  execucao: 'em_execucao',
};

const KPI_FILTER_LABEL: Record<KpiFilterKey, string> = {
  recepcao: 'Recepção',
  diagnostico: 'Em diagnóstico',
  pecas: 'Aguardando peças',
  execucao: 'Em execução',
  urgentes: 'Urgentes',
};

const formatBRL = (value: number): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(value);

type BoardFoco = 'etapa' | 'box' | 'mecanico';

// Tela unificada "Oficina Auto" (pedido [W] 2026-06-11 — no demo são ABAS, não
// páginas): as 4 views (Quadro · Lista · Grade · Fila) são TODAS in-page sobre o
// MESMO payload `columns`, com KPIs + abas de box + toolbar COMPARTILHADOS (1
// componente de cada — zero duplicação). Lista/Fila deixaram de navegar pro Index
// (que foi aposentado); derivam dos cards via `visibleCards`. View persistida no
// localStorage + refletida em ?view= (shareable). Esta tela é servida tanto em
// /ordens-servico quanto em /ordens-servico/board (mesmo componente).
type BoardView = 'quadro' | 'lista' | 'grade' | 'fila';

const FOCO_STORAGE_KEY = 'oficinaBoard.foco';
const DENSIDADE_STORAGE_KEY = 'oficinaBoard.densidade';
const VIEW_STORAGE_KEY = 'oficinaBoard.view';

const BOARD_VIEWS: readonly BoardView[] = ['quadro', 'lista', 'grade', 'fila'];

function initialBoardView(): BoardView {
  if (typeof window !== 'undefined') {
    const fromUrl = new URLSearchParams(window.location.search).get('view');
    if (fromUrl && (BOARD_VIEWS as readonly string[]).includes(fromUrl)) return fromUrl as BoardView;
    const stored = window.localStorage.getItem(VIEW_STORAGE_KEY);
    if (stored && (BOARD_VIEWS as readonly string[]).includes(stored)) return stored as BoardView;
  }
  return 'quadro';
}

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

// ─── Ação primária do card (botão inline — Onda 1.5 paridade Cowork) ──────────
// Cada etapa tem um botão de avanço no card (Triagem→/Enviar orçamento→/Peças
// chegaram→/Concluir→/Entregar→). Vai pelo MESMO ExecuteStageActionService do
// drag (reusa pending + DragConfirmDialog) — não viola o anti-hook do charter
// (que proíbe UPDATE direto, não uma 2ª porta pelo serviço). Diferenças vs drag:
//   - aguardando_aprovacao tem 2 saídas críticas (pedir peças × executar) +
//     gate de cobrança → o botão ABRE O DRAWER (FsmActionPanel decide), não força;
//   - pronto_retirada → entregue é TERMINAL: o drag não faz terminal (Non-Goal do
//     charter), mas o BOTÃO faz (decisão [W] 2026-06-10 · charter emendado v3).
interface CardAction {
  label: string;
  drawer?: boolean;
  move?: AllowedMove & { toColumn: string };
}

const CARD_PRIMARY_ACTION: Record<string, CardAction> = {
  recepcao: {
    label: 'Triagem',
    move: {
      toColumn: 'em_diagnostico', actionKey: 'iniciar_diagnostico', actionLabel: 'Iniciar diagnóstico', isCritical: false,
      title: 'Iniciar diagnóstico?', description: 'O veículo entra em diagnóstico técnico.',
    },
  },
  em_diagnostico: {
    label: 'Enviar orçamento',
    move: {
      toColumn: 'aguardando_aprovacao', actionKey: 'enviar_orcamento', actionLabel: 'Enviar orçamento', isCritical: false,
      title: 'Enviar orçamento pra aprovação?', description: 'A OS vai aguardar o OK do cliente sobre o orçamento.',
    },
  },
  // 2 saídas críticas (pedir peças × executar) + gate de cobrança → o botão ABRE
  // o drawer (FsmActionPanel decide), em vez de forçar uma transição.
  aguardando_aprovacao: { label: 'Aprovação', drawer: true },
  aguardando_pecas: {
    label: 'Peças chegaram',
    move: {
      toColumn: 'em_execucao', actionKey: 'pecas_chegaram', actionLabel: 'Peças chegaram', isCritical: false,
      title: 'Peças chegaram?', description: 'Confirma a chegada das peças e inicia a execução do serviço.',
    },
  },
  em_execucao: {
    label: 'Concluir',
    move: {
      toColumn: 'pronto_retirada', actionKey: 'concluir_servico', actionLabel: 'Concluir serviço', isCritical: true,
      title: 'Concluir o serviço?', description: 'Marca a OS como pronta pro cliente retirar.',
    },
  },
  pronto_retirada: {
    label: 'Entregar',
    move: {
      toColumn: 'entregue', actionKey: 'entregar', actionLabel: 'Entregar ao cliente', isCritical: false,
      title: 'Entregar ao cliente?', description: 'Marca a OS como entregue (etapa final) — sai do quadro.',
    },
  },
};

// ─── Component ─────────────────────────────────────────────────────────────

export default function ServiceOrdersBoard({ columns, kpis, process_seeded, filters, filterOptions }: Props) {
  const [searchInput, setSearchInput] = useState(filters.q ?? '');
  const searchRef = useRef<HTMLInputElement | null>(null);

  // D-05 — filtro por KPI (client-side, clicar de novo limpa)
  const [kpiFilter, setKpiFilter] = useState<KpiFilterKey | null>(null);
  // Abas de box (client-side, paridade Cowork .prod-equip-filters) — null = todos
  const [boxFilter, setBoxFilter] = useState<string | null>(null);
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

  // View in-page (Quadro · Lista · Grade · Fila), persistida por operador +
  // refletida em ?view= (shareable). Tela unificada — todas as 4 derivam de `columns`.
  const [view, setViewState] = useState<BoardView>(initialBoardView);
  const setView = useCallback((v: BoardView) => {
    setViewState(v);
    setFocusedId(null);
    try { window.localStorage.setItem(VIEW_STORAGE_KEY, v); } catch { /* storage cheio/bloqueado — preferência só não persiste */ }
    if (typeof window !== 'undefined') {
      const url = new URL(window.location.href);
      url.searchParams.set('view', v);
      window.history.replaceState(window.history.state, '', url.toString());
    }
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
      // D-14: partial reload — só re-busca o que muda com filtro (ref PR #3889).
      // process_seeded/filterOptions são por business, não mudam com q/mecânico/box.
      router.get('/oficina-auto/ordens-servico/board', next, {
        preserveState: true, preserveScroll: true, replace: true,
        only: ['columns', 'kpis', 'filters'],
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

  // KPIs do protótipo Cowork: label + valor + sublinha descritiva. 5 filtráveis +
  // "Valor em curso" (faturamento previsto · só leitura). Onda 1.5.
  const kpiCards = useMemo(() => [
    { id: 'recepcao', label: 'Recepção', value: String(kpis.recepcao), sub: 'veículos aguardando triagem', tone: 'default' as const, filterKey: 'recepcao' as const },
    { id: 'diagnostico', label: 'Em diagnóstico', value: String(kpis.em_diagnostico), sub: `${kpis.boxes_total} ${kpis.boxes_total === 1 ? 'box/elevador' : 'boxes/elevadores'}`, tone: 'blue' as const, filterKey: 'diagnostico' as const },
    { id: 'pecas', label: 'Aguardando peças', value: String(kpis.aguardando_pecas), sub: `${kpis.aguardando_aprovacao} ${kpis.aguardando_aprovacao === 1 ? 'aguarda' : 'aguardam'} OK do cliente`, tone: 'violet' as const, filterKey: 'pecas' as const },
    { id: 'execucao', label: 'Em execução', value: String(kpis.em_execucao), sub: 'boxes ocupados agora', tone: 'indigo' as const, filterKey: 'execucao' as const },
    { id: 'urgentes', label: 'Urgentes', value: String(kpis.atrasadas), sub: 'prazo crítico', tone: 'rose' as const, filterKey: 'urgentes' as const },
    { id: 'valor', label: 'Valor em curso', value: formatBRL(kpis.valor_em_curso), sub: 'faturamento previsto', tone: 'emerald' as const, filterKey: null },
  ], [kpis]);

  // D-05 — predicado do KPI ativo sobre (card, etapa). Client-side: o payload do
  // board já está no browser; filtrar não round-tripa.
  const cardMatchesKpi = useCallback((card: ServiceOrderCardData, stageKey: string): boolean => {
    if (!kpiFilter) return true;
    if (kpiFilter === 'urgentes') return card.is_overdue;
    return stageKey === KPI_FILTER_STAGE[kpiFilter];
  }, [kpiFilter]);

  // Predicado combinado: KPI + aba de box (ambos client-side).
  const cardVisible = useCallback((card: ServiceOrderCardData, stageKey: string): boolean => {
    if (!cardMatchesKpi(card, stageKey)) return false;
    if (boxFilter !== null && card.box !== boxFilter) return false;
    return true;
  }, [cardMatchesKpi, boxFilter]);

  // Contagem de OS por box (abas .prod-equip-filters) — sobre TODOS os cards
  // (pré-aba, pós-KPI? não: pré-tudo, pra a aba mostrar o universo). Espelha o
  // protótipo: "Todos os boxes N | Box 1 (n) …".
  const boxCounts = useMemo(() => {
    const counts = new Map<string, number>();
    columns.forEach((col) => col.cards.forEach((c) => {
      if (c.box) counts.set(c.box, (counts.get(c.box) ?? 0) + 1);
    }));
    return counts;
  }, [columns]);

  // Etapa de origem de cada card (sobrevive ao pivot Box/Mecânico — usada no
  // filtro por KPI de etapa, na folha "Imprimir fila" e nas views Lista/Fila).
  const stageByCardId = useMemo(() => {
    const m = new Map<number, { key: string; name: string; color: string | null; index: number }>();
    columns.forEach((col, index) => col.cards.forEach((c) => m.set(c.id, { key: col.key, name: col.name, color: col.color, index })));
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
        cards: col.cards.filter((c) => cardVisible(c, col.key)),
        emphasis: col.key === 'aguardando_aprovacao' ? 'aprovacao' as const : col.key === 'aguardando_pecas' ? 'pecas' as const : null,
        // Capacidade da oficina (header da coluna Em execução): ocupação REAL,
        // por isso usa col.cards (pré-filtro KPI) — y = boxes cadastrados.
        capacity: col.key === 'em_execucao' && filterOptions.boxes.length > 0
          ? `${col.cards.length}/${filterOptions.boxes.length} boxes`
          : null,
      }));
    }

    const visiveis = columns.flatMap((col) => col.cards.filter((c) => cardVisible(c, col.key)));
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
  }, [columns, foco, cardVisible, filterOptions.boxes, filterOptions.mecanicos]);

  // Botão de ação primária do card (paridade Cowork) — resolve a ação pela ETAPA
  // REAL do card (via stageByCardId, sobrevive ao pivot Box/Mecânico). Reusa o
  // mesmo confirm/execute do drag (setPending) ou abre o drawer (aguardando_aprovacao).
  const handleCardAction = useCallback((card: ServiceOrderCardData) => {
    const stage = stageByCardId.get(card.id)?.key;
    if (!stage) return;
    const action = CARD_PRIMARY_ACTION[stage];
    if (!action) return;
    if (action.drawer || !action.move) {
      setOpenOsId(card.id);
      return;
    }
    const move = action.move;
    setPending({
      subjectId: card.id,
      fromColumn: stage,
      toColumn: move.toColumn,
      actionKey: move.actionKey,
      actionLabel: move.actionLabel,
      isCritical: move.isCritical,
      title: move.title,
      description: move.description,
      plate: card.plate ?? undefined,
      cliente_nome: card.cliente_nome,
      subjectLabel: 'Veículo',
    });
  }, [stageByCardId]);

  // Ordem visível dos cards (coluna a coluna) — navegação por setas + contador "N OS"
  const visibleCards = useMemo(() => displayColumns.flatMap((c) => c.cards), [displayColumns]);
  const visibleCount = visibleCards.length;

  // Onda 2 — linhas da Grade (veículo × etapa). Cada OS vira uma linha; a marca cai
  // na coluna da etapa REAL (col.key), respeitando busca + KPI-filtro + aba de box
  // (cardVisible). Independe do foco — Box/Mecânico é pivot só do Quadro; a Grade é
  // sempre por etapa. Colunas = as etapas FSM do payload `columns` (data-driven).
  const gradeRows = useMemo(() => {
    const rows: Array<{ card: ServiceOrderCardData; stageKey: string }> = [];
    columns.forEach((col) => col.cards.forEach((c) => {
      if (cardVisible(c, col.key)) rows.push({ card: c, stageKey: col.key });
    }));
    return rows;
  }, [columns, cardVisible]);

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
    if (boxFilter) filtroParts.push(`box ${boxFilter}`);
    if (foco !== 'etapa') filtroParts.push(`foco ${foco === 'box' ? 'Box' : 'Mecânico'}`);
    toast.info('Preparando impressão da fila…');
    printOficinaFila(rows, { filtro: filtroParts.length ? filtroParts.join(' · ') : null })
      .catch((e: unknown) => toast.error(e instanceof Error ? e.message : 'Falha ao imprimir a fila'));
  }, [visibleCards, stageByCardId, filters.q, kpiFilter, boxFilter, foco]);

  // FIX [CC] 2026-06-10: colunas com largura mínima utilizável (canon do protótipo
  // .prod-kanban: repeat(n, minmax(228px, 1fr))) — inline style em vez de classe
  // Tailwind literal porque o nº de colunas é data-driven. O wrapper rola no eixo X.
  const boardGridStyle = useMemo(() => ({
    gridTemplateColumns: `repeat(${Math.max(displayColumns.length, 1)}, minmax(228px, 1fr))`,
  }), [displayColumns.length]);

  return (
    <>
      <Head title="Oficina Auto · Ordens de Serviço" />
      {/* Coluna flex que PREENCHE o .main-body (height 100vh - topbar, overflow-hidden
          no shell): chrome (header/KPIs/abas/toolbar) FIXO e só o conteúdo (4 views)
          rola por dentro. Sem isso, o .main-body rolava inteiro e os KPIs/toolbar
          sumiam ao rolar — "a tela corta" ([W] 2026-06-11, sintoma sistêmico do shell). */}
      <div className="flex-1 min-h-0 flex flex-col bg-muted/40 @container/board">
        {/* Topbar */}
        <header className="bg-white border-b border-border px-6 py-4 flex items-start justify-between gap-4 flex-wrap">
          <div className="min-w-0">
            <h1 className="text-lg font-semibold text-foreground">Oficina Auto</h1>
            <p className="text-xs text-muted-foreground mt-0.5">Recepção, diagnóstico, peças, execução e entrega de veículos</p>
          </div>
          {/* Header só com as ações de página (toggle de views + Visão migraram pra
              barra da busca — canon .ofc-view-toolbar · pedido [W] 2026-06-11). */}
          <div className="flex items-center gap-2 flex-shrink-0 flex-wrap">
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
              <BoardKpiCard
                key={id}
                {...kpi}
                active={filterKey !== null && kpiFilter === filterKey}
                dimmed={kpiFilter !== null && filterKey !== null && kpiFilter !== filterKey}
                onClick={filterKey !== null ? () => kpiClick(filterKey) : undefined}
              />
            ))}
          </div>
        </div>

        {/* Abas de box/elevador (paridade Cowork .prod-equip-filters) — filtro
            client-side instantâneo. "Todos os boxes" + cada box com contador. */}
        {filterOptions.boxes.length > 0 && (
          <Inline gap={1} className="bg-white border-b border-border px-6 py-2 gap-1.5 overflow-x-auto" role="group" aria-label="Filtrar por box">
            <button
              type="button"
              onClick={() => setBoxFilter(null)}
              aria-pressed={boxFilter === null}
              className={
                'inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-2.5 py-1 border whitespace-nowrap transition-colors '
                + (boxFilter === null ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-border hover:bg-muted')
              }
              data-testid="board-box-tab-all"
            >
              Todos os boxes
              <span className={'tabular-nums rounded px-1 ' + (boxFilter === null ? 'bg-white/20' : 'bg-muted')}>{kpis.total}</span>
            </button>
            {filterOptions.boxes.map((b) => {
              const active = boxFilter === b;
              return (
                <button
                  key={b}
                  type="button"
                  onClick={() => setBoxFilter(active ? null : b)}
                  aria-pressed={active}
                  className={
                    'inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-2.5 py-1 border whitespace-nowrap transition-colors '
                    + (active ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-border hover:bg-muted')
                  }
                  data-testid={`board-box-tab-${b}`}
                >
                  {b}
                  <span className={'tabular-nums rounded px-1 ' + (active ? 'bg-white/20' : 'bg-muted')}>{boxCounts.get(b) ?? 0}</span>
                </button>
              );
            })}
          </Inline>
        )}

        {/* Barra de views (canon .ofc-view-toolbar) — [busca + contador] | [toggle
            Quadro·Lista·Grade·Fila] | [Visão]. Toggle e Visão migraram do header pra
            cá ([W] 2026-06-11). O contador "N OS · N atrasadas" fica à direita da busca. */}
        <Inline wrap gap={3} className="bg-white border-b border-border px-6 py-2.5 sticky top-0 z-10">
          {/* Grupo busca (flex-1): input + limpar + chip KPI + contador à direita */}
          <Inline gap={2} className="flex-1 min-w-[240px]">
            <Search size={14} className="text-muted-foreground flex-shrink-0" />
            <div className="relative flex-1 max-w-md">
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

            {/* D-05 — chip do KPI-filtro ativo (clicar limpa) */}
            {kpiFilter && (
              <button
                type="button"
                className="inline-flex items-center gap-1 text-[11px] font-medium text-primary bg-primary/10 border border-primary/30 rounded-full px-2 py-0.5 hover:bg-primary/15 whitespace-nowrap"
                onClick={() => kpiClick(kpiFilter)}
                data-testid="board-kpi-clear"
              >
                <X size={10} /> limpar filtro: {KPI_FILTER_LABEL[kpiFilter]}
              </button>
            )}

            <span className="ml-auto pl-2 text-sm text-muted-foreground whitespace-nowrap" aria-live="polite">
              <span className="font-medium text-foreground tabular-nums">{visibleCount} OS</span>
              {kpiFilter && (<span className="ml-1 text-xs">de {kpis.total}</span>)}
              {kpis.atrasadas > 0 && (<><span className="mx-1.5 text-muted-foreground">·</span><span className="font-medium text-destructive tabular-nums">{kpis.atrasadas} atrasada{kpis.atrasadas === 1 ? '' : 's'}</span></>)}
            </span>
          </Inline>

          {/* Toggle de views (canon .prod-view-toggle): Quadro · Lista · Grade · Fila —
              TODAS in-page (tela unificada, pedido [W] 2026-06-11). Trocam só o miolo
              sobre o mesmo payload `columns`; KPIs/abas/toolbar continuam acima. */}
          <div className="inline-flex flex-shrink-0 rounded border border-border bg-white overflow-hidden" role="group" aria-label="Visualização">
            {([
              { v: 'quadro', label: 'Quadro', Icon: LayoutGrid },
              { v: 'lista', label: 'Lista', Icon: ListIcon },
              { v: 'grade', label: 'Grade', Icon: Table2 },
              { v: 'fila', label: 'Fila', Icon: ListOrdered },
            ] as const).map(({ v, label, Icon }, i) => (
              <button
                key={v}
                type="button"
                onClick={() => setView(v)}
                aria-pressed={view === v}
                className={
                  'px-2.5 py-1 text-xs font-medium inline-flex items-center gap-1 transition-colors '
                  + (i > 0 ? 'border-l border-border ' : '')
                  + (view === v ? 'bg-primary text-white' : 'text-foreground hover:bg-muted')
                }
                data-testid={`board-view-${v}`}
              >
                <Icon size={12} /> {label}
              </button>
            ))}
          </div>

          {/* Menu Visão (canon .ofc-adjust do protótipo) — foco + densidade.
              Pressão ficou FORA desta onda. */}
          <Popover>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className={'flex-shrink-0 ' + (foco !== 'etapa' || densidade !== 'padrao' ? 'border-primary text-primary' : '')}
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
              {foco === 'etapa' ? (
                view === 'grade' ? (
                  <p className="text-[11px] text-muted-foreground leading-snug">
                    A Grade é sempre por etapa (veículo × etapa) — Foco e Densidade valem no Quadro.
                  </p>
                ) : null
              ) : (
                <p className="text-[11px] text-muted-foreground leading-snug">
                  No foco {foco === 'box' ? 'Box' : 'Mecânico'} o arraste fica desligado — etapas mudam no foco Etapa ou pelo drawer da OS.
                </p>
              )}
            </PopoverContent>
          </Popover>
        </Inline>

        {/* Área de conteúdo — flex-1 min-h-0 BOUNDA a altura (chrome acima fica fixo);
            cada view preenche (h-full) e rola POR DENTRO. overflow-hidden aqui evita
            o scroll duplo (shell × view). */}
        <div className="flex-1 min-h-0 overflow-hidden">
        {!process_seeded ? (
          <div className="p-6 h-full overflow-y-auto">
            <EmptyProcessState />
          </div>
        ) : view === 'grade' ? (
          <BoardGrade columns={columns} rows={gradeRows} onRowClick={handleCardClick} />
        ) : view === 'lista' ? (
          <BoardLista rows={visibleCards} stageByCardId={stageByCardId} onRowClick={handleCardClick} />
        ) : view === 'fila' ? (
          <BoardFila rows={visibleCards} stageByCardId={stageByCardId} onOpenFull={handleCardClick} onOrderChanged={reloadBoard} />
        ) : (
          /* Quadro — h-full + overflow-auto: o kanban rola por dentro (X e Y), chrome fixo. */
          <div className="p-6 h-full overflow-auto">
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
                    // Botão de ação só no foco Etapa (coluna = etapa FSM real). No
                    // pivot Box/Mecânico a coluna mistura etapas → sem rótulo único.
                    primaryActionLabel={foco === 'etapa' ? (CARD_PRIMARY_ACTION[col.key]?.label ?? null) : null}
                    onCardAction={handleCardAction}
                    onCardClick={handleCardClick}
                  />
                ))}
              </div>
            </KanbanDndProvider>
          </div>
        )}
        </div>{/* /área de conteúdo */}
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
// KpiCard extraído pra ./_components/board/BoardKpiCard (2026-06-11) — a Index
// passou a usar o MESMO canon de KPI clicável; um único componente pros dois.

// ─── Grade (veículo × etapa · Onda 2) ────────────────────────────────────────
// View de varredura client-side: cada linha é uma OS (placa + modelo + cliente),
// cada coluna é uma etapa FSM (data-driven do payload `columns`); a marca cai na
// célula da etapa ATUAL da OS. Tom da marca = tom da coluna (toneForColor), glifo
// semântico por etapa (gradeGlyph). Port Tailwind do canon .ofc-grade do protótipo
// Cowork. Sem dado fake — heurística sintoma×serviço do protótipo NÃO entra (gate
// no-mock-in-prod): a Grade espelha a etapa real, não inventa cobertura de serviço.
interface BoardGradeProps {
  columns: BoardColumn[];
  rows: Array<{ card: ServiceOrderCardData; stageKey: string }>;
  onRowClick: (card: ServiceOrderCardData) => void;
}

function BoardGrade({ columns, rows, onRowClick }: BoardGradeProps) {
  const headCls =
    'bg-muted text-[9.5px] font-semibold uppercase tracking-[0.05em] text-muted-foreground border-b border-r border-border align-bottom';
  return (
    <div className="p-6 h-full overflow-auto">
      <table className="w-full border-separate border-spacing-0 text-[11.5px] bg-white border border-border rounded-lg overflow-hidden">
        <thead>
          <tr>
            <th className={`${headCls} sticky left-0 z-[2] text-left w-[240px] px-3.5 py-2.5`}>
              Veículo / Serviço
            </th>
            {columns.map((col) => (
              <th key={col.key} className={`${headCls} px-1.5 py-2.5 text-center whitespace-nowrap last:border-r-0`}>
                {col.name}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map(({ card, stageKey }) => (
            <tr
              key={card.id}
              onClick={() => onRowClick(card)}
              className="group cursor-pointer"
            >
              <td className="sticky left-0 z-[1] bg-white group-hover:bg-muted/60 px-3.5 py-2.5 border-b border-r border-border flex items-center gap-2.5 font-medium">
                {card.plate ? (
                  <MercosulPlate plate={card.plate} size="sm" />
                ) : (
                  <span className="text-[10px] text-muted-foreground italic flex-shrink-0">sem placa</span>
                )}
                <div className="min-w-0 flex-1">
                  <span className="block text-[11.5px] font-semibold leading-tight truncate text-foreground">
                    {card.vehicle_type ?? 'Veículo'}
                  </span>
                  <span className="block text-[10px] text-muted-foreground truncate">
                    {card.cliente_nome ?? '—'} · <span className="font-mono">OS #{card.id}</span>
                  </span>
                </div>
              </td>
              {columns.map((col) => (
                <td
                  key={col.key}
                  className="h-10 p-0 text-center border-b border-r border-border last:border-r-0 group-hover:bg-muted/40"
                >
                  {col.key === stageKey ? (
                    <span
                      className={`inline-grid place-items-center w-[22px] h-[22px] rounded-[5px] text-xs font-bold font-mono ${toneForColor(col.color).badge}`}
                      title={col.name}
                    >
                      {gradeGlyph(col.key)}
                    </span>
                  ) : null}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>

      {rows.length === 0 && (
        <p className="text-center text-sm text-muted-foreground py-8">
          Nenhuma OS no filtro atual.
        </p>
      )}

      {/* Legenda data-driven — uma entrada por etapa do board (mesmo glifo/tom da célula) */}
      <div className="mt-3.5 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border border-border bg-muted/40 px-3.5 py-2.5 text-[11px] text-muted-foreground">
        {columns.map((col) => (
          <span key={col.key} className="inline-flex items-center gap-1.5">
            <span className={`inline-grid place-items-center w-[18px] h-[18px] rounded-[5px] text-[10.5px] font-bold font-mono ${toneForColor(col.color).badge}`}>
              {gradeGlyph(col.key)}
            </span>
            {col.name}
          </span>
        ))}
        <span className="ml-auto italic text-muted-foreground/80">clique no veículo abre a OS</span>
      </div>
    </div>
  );
}

// ─── Lista (tabela rica · tela unificada) ────────────────────────────────────
// View tabela sobre os MESMOS cards do Quadro (paridade protótipo Cowork): OS ·
// PLACA Mercosul · VEÍCULO+km · CLIENTE · ETAPA (dot+nome) · BOX · MECÂNICO ·
// PRAZO · VALOR. Respeita busca + KPI-filtro + aba de box (recebe `visibleCards`).
interface BoardListaProps {
  rows: ServiceOrderCardData[];
  stageByCardId: StageInfoMap;
  onRowClick: (card: ServiceOrderCardData) => void;
}

const fmtFilaDate = (iso: string | null): string => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
};

const fmtBRL2 = (n: number): string =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function BoardLista({ rows, stageByCardId, onRowClick }: BoardListaProps) {
  return (
    <div className="p-6 h-full overflow-auto">
      <table className="w-full text-sm bg-white border border-border rounded-lg overflow-hidden">
        <thead className="border-b bg-muted/50 text-[11px] uppercase tracking-wide text-muted-foreground">
          <tr>
            <th className="px-3 py-2.5 text-left font-semibold">OS</th>
            <th className="px-3 py-2.5 text-left font-semibold">Placa</th>
            <th className="px-3 py-2.5 text-left font-semibold">Veículo</th>
            <th className="px-3 py-2.5 text-left font-semibold">Cliente</th>
            <th className="px-3 py-2.5 text-left font-semibold">Etapa</th>
            <th className="px-3 py-2.5 text-left font-semibold">Box</th>
            <th className="px-3 py-2.5 text-left font-semibold">Mecânico</th>
            <th className="px-3 py-2.5 text-left font-semibold">Prazo</th>
            <th className="px-3 py-2.5 text-right font-semibold">Valor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((c) => {
            const stage = stageByCardId.get(c.id);
            const tone = toneForColor(stage?.color);
            return (
              <tr
                key={c.id}
                onClick={() => onRowClick(c)}
                className={
                  'border-b last:border-0 cursor-pointer transition-colors '
                  + (c.is_overdue ? 'bg-destructive/5 hover:bg-destructive/10' : 'hover:bg-muted/30')
                }
              >
                <td className="px-3 py-2.5 font-mono font-semibold whitespace-nowrap">{c.number}</td>
                <td className="px-3 py-2">
                  {c.plate ? <MercosulPlate plate={c.plate} size="sm" /> : <span className="text-xs text-muted-foreground italic">sem placa</span>}
                </td>
                <td className="px-3 py-2.5">
                  <span className="font-medium text-foreground">{c.vehicle_type ?? '—'}</span>
                  {c.km != null && <span className="ml-1.5 text-[11px] text-muted-foreground tabular-nums">{c.km.toLocaleString('pt-BR')} km</span>}
                </td>
                <td className="px-3 py-2.5">{c.cliente_nome ?? <span className="text-muted-foreground">—</span>}</td>
                <td className="px-3 py-2.5 whitespace-nowrap">
                  {stage ? (
                    <span className="inline-flex items-center gap-1.5 text-xs">
                      <span className={'inline-block h-2 w-2 rounded-full ' + tone.dot} />
                      {stage.name}
                    </span>
                  ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                  )}
                </td>
                <td className="px-3 py-2.5 text-xs">{c.box ?? <span className="text-muted-foreground">—</span>}</td>
                <td className="px-3 py-2.5 text-xs">{c.mechanic_name ?? <span className="text-muted-foreground">—</span>}</td>
                <td className={'px-3 py-2.5 text-xs tabular-nums whitespace-nowrap ' + (c.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground')}>
                  {fmtFilaDate(c.expected_completion)}{c.is_overdue && ' ⚠'}
                </td>
                <td className={'px-3 py-2.5 text-right tabular-nums whitespace-nowrap ' + (c.valor > 0 ? 'text-foreground font-medium' : 'text-muted-foreground')}>
                  {c.valor > 0 ? fmtBRL2(c.valor) : '—'}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
      {rows.length === 0 && (
        <p className="text-center text-sm text-muted-foreground py-8">Nenhuma OS no filtro atual.</p>
      )}
    </div>
  );
}

// ─── Fila (master-detail · tela unificada) ───────────────────────────────────
// Lista persistente (esq) + detalhe inline (centro) + rail Apps Vinculados (dir),
// sobre os MESMOS cards. Detalhe RICO INLINE (Onda 2 · [W] 2026-06-11): o centro
// renderiza o ServiceOrderRichBody — o MESMO corpo do drawer (DVI/fotos/peças/
// checklist/pipeline/timeline), editável inline. "Abrir OS completa" no rail abre
// o drawer focado. onOrderChanged recarrega o board após transição FSM no inline.
interface BoardFilaProps {
  rows: ServiceOrderCardData[];
  stageByCardId: StageInfoMap;
  onOpenFull: (card: ServiceOrderCardData) => void;
  onOrderChanged: () => void;
}

function BoardFila({ rows, stageByCardId, onOpenFull, onOrderChanged }: BoardFilaProps) {
  const [selectedId, setSelectedId] = useState<number | null>(rows[0]?.id ?? null);
  const selected = useMemo(() => rows.find((c) => c.id === selectedId) ?? rows[0] ?? null, [rows, selectedId]);

  if (rows.length === 0) {
    return (
      <div className="p-6">
        <div className="rounded-lg border bg-card px-4 py-10 text-center text-sm text-muted-foreground">
          Nenhuma OS no filtro atual.
        </div>
      </div>
    );
  }

  const urgentes = rows.filter((c) => c.is_overdue);
  const demais = rows.filter((c) => !c.is_overdue);

  const FilaItem = ({ c }: { c: ServiceOrderCardData }) => {
    const stage = stageByCardId.get(c.id);
    const tone = toneForColor(stage?.color);
    const active = selected?.id === c.id;
    return (
      <button
        type="button"
        onClick={() => setSelectedId(c.id)}
        className={
          'w-full rounded-md border px-3 py-2.5 text-left transition-colors '
          + (active ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/30' : 'border-border bg-card hover:bg-muted/40')
          + (c.is_overdue ? ' border-l-2 border-l-destructive' : '')
        }
      >
        <div className="flex items-center justify-between gap-2">
          <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
            <span className={'inline-block h-1.5 w-1.5 rounded-full ' + tone.dot} />
            {stage?.name ?? '—'}
          </span>
          <span className={'text-[11px] tabular-nums ' + (c.is_overdue ? 'font-medium text-destructive' : 'text-muted-foreground')}>
            {fmtFilaDate(c.expected_completion)}
          </span>
        </div>
        <div className="mt-1.5 truncate text-sm font-medium text-foreground">
          {c.vehicle_type ?? 'Veículo'}{c.plate ? <span className="ml-1 font-mono text-xs text-muted-foreground">· {c.plate}</span> : null}
        </div>
        <div className="truncate text-xs text-muted-foreground">{c.number} · {c.cliente_nome ?? '—'}</div>
      </button>
    );
  };

  const Group = ({ title, items }: { title: string; items: ServiceOrderCardData[] }) =>
    items.length === 0 ? null : (
      <div className="space-y-1.5">
        <div className="flex items-center gap-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
          {title}
          <span className="rounded-full bg-muted px-1.5 text-[10px] tabular-nums">{items.length}</span>
        </div>
        {items.map((c) => <FilaItem key={c.id} c={c} />)}
      </div>
    );

  const sel = selected;
  const selStage = sel ? stageByCardId.get(sel.id) : undefined;

  return (
    // h-full: a Fila PREENCHE a área de conteúdo (chrome fixo acima); cada coluna
    // (lista · detalhe rico · rail) rola por dentro. Sem max-h-[72vh] fixo — fluido.
    <div className="p-6 h-full min-h-0">
      <div className="grid h-full min-h-0 grid-cols-1 gap-3 md:grid-cols-[300px_minmax(0,1fr)] xl:grid-cols-[300px_minmax(0,1fr)_280px]">
        {/* Lista (esquerda) */}
        <div className="flex min-h-0 flex-col rounded-lg border bg-muted/20">
          <div className="flex items-center justify-between border-b px-3 py-2.5">
            <b className="text-sm text-foreground">Ordens de serviço</b>
            <span className="text-xs text-muted-foreground">{rows.length} na fila</span>
          </div>
          <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-2.5">
            <Group title="Urgentes" items={urgentes} />
            <Group title="Demais" items={demais} />
          </div>
        </div>

        {/* Detalhe inline (centro) — MESMO corpo rico do drawer (ServiceOrderRichBody):
            DVI semáforo · Fotos & Laudo · Peças & mão-de-obra · Checklist de etapa ·
            Pipeline FSM · Linha do tempo. Zero duplicação (Onda 2 · [W] 2026-06-11).
            key={sel.id} re-seed o estado ao trocar de OS na fila. */}
        {sel ? (
          <div className="flex min-h-0 flex-col rounded-lg border bg-card overflow-hidden">
            <ServiceOrderRichBody
              key={sel.id}
              serviceOrderId={sel.id}
              enabled
              onOrderChanged={onOrderChanged}
            />
          </div>
        ) : (
          <div className="flex items-center justify-center rounded-lg border bg-card text-sm text-muted-foreground">
            Selecione uma OS na fila.
          </div>
        )}

        {/* Rail Apps Vinculados (direita) */}
        {sel && (
          <aside className="hidden min-h-0 flex-col gap-3 xl:flex">
            <div className="px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Apps vinculados</div>
            <div className="rounded-lg border bg-card p-3">
              <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
                <span className="inline-flex items-center gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-primary">OS</span>
                <b>{sel.number}</b>
              </div>
              <dl className="space-y-1 text-xs">
                <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Etapa</dt><dd className="truncate text-right text-foreground">{selStage?.name ?? '—'}</dd></div>
                <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Prazo</dt><dd className={'tabular-nums ' + (sel.is_overdue ? 'font-medium text-destructive' : 'text-foreground')}>{fmtFilaDate(sel.expected_completion)}</dd></div>
                <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Valor</dt><dd className="font-mono tabular-nums text-foreground">{sel.valor > 0 ? fmtBRL2(sel.valor) : '—'}</dd></div>
              </dl>
              <button
                type="button"
                onClick={() => onOpenFull(sel)}
                className="mt-2.5 inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-border bg-background px-2 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
              >
                Abrir OS completa
              </button>
            </div>
            <div className="rounded-lg border bg-card p-3">
              <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
                <span className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-muted-foreground">Cliente</span>
                <b className="truncate">{sel.cliente_nome ?? '—'}</b>
              </div>
              <p className="text-xs leading-relaxed text-muted-foreground">
                Histórico de contato e disparo de WhatsApp ainda não disponíveis nesta tela.
              </p>
            </div>
          </aside>
        )}
      </div>
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
