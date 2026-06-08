// @memcofre tela=/oficina-auto/producao-oficina module=OficinaAuto
// Produção · Oficina — Kanban estado dos veículos em produção.
//
// Convergência F3 caçamba → modelo (A) reparo automotivo (camada VISÍVEL apenas).
// Gabarito de design: protótipo Cowork `oficina-page.{jsx,css}` (modelo (A), nota [W] 9.5).
// 5 colunas reparo: Recepção/Diagnóstico/Aguardando peças/Em execução/Pronto p/ retirar.
// + 6 KPIs (Recepção · Em diagnóstico · Aguardando peças · Em execução · Urgentes · Valor)
// + drawer próprio ServiceOrderRichSheet polimórfico (TRAVADO — não tocar além de vocab header).
//
// ⛔ Trava dura (Martinho biz=164 LIVE prod, ADR 0194): as KEYS FSM canon
// (`disponivel`/`locada`/`aguardando`/`manutencao`/`pronta`) e o DB `cacamba_locacao`
// PERMANECEM intactos. A convergência troca só APRESENTAÇÃO (label/vocab/KPIs/filtro);
// migração de seeder/controller/DB é Tier 0 → ADR própria. Aqui: zero backend.
//
// Composição via primitivos de layout (ADR 0253): header/KPIs/filtro recompostos em
// Box/Stack/Inline/Grid/Text — sem `flex`/`grid` solto novo, sem `.css` de tela.
//
// Refs:
//   - ADR 0137 (OficinaAuto qualificada) — amendado por 0194
//   - ADR 0194 (correção domínio Martinho — mecânica pesada não locação; dívida F3)
//   - ADR 0143 (FSM Pipeline LIVE prod biz=1)
//   - ADR 0110 (Cockpit V2 — AppShellV2 obrigatório)
//   - ADR 0253 (primitivos de layout)
//   - PR #717 lição: useMemo/useCallback nos handlers descendentes (re-render loop)
//   - Charter: Index.charter.md (modelo (A) reparo decidido por [W] 2026-06-02; drawer travado)
// Visual comparison: memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import { toast } from 'sonner';
import { Plus, Printer, Search, LayoutGrid, List as ListIcon, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Box, Stack, Inline, Grid, Text } from '@/Components/layout';
import CacambaKanbanColumn from './_components/CacambaKanbanColumn';
import type { CacambaCardData, CacambaStatus } from './_components/CacambaCard';
import ServiceOrderRichSheet from './_components/ServiceOrderRichSheet';
import KanbanDndProvider from './_components/KanbanDndProvider';
import DragConfirmDialog, {
  type PendingTransition,
} from './_components/DragConfirmDialog';

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

// Convergência F3 (modelo (A) reparo): só o LABEL visível muda — as keys/status FSM
// canon permanecem (compat DB cacamba_locacao LIVE prod biz=164, ADR 0194). Mapa:
//   disponivel→Recepção · locada→Diagnóstico · aguardando→Aguardando peças ·
//   manutencao→Em execução · pronta→Pronto p/ retirar.
const COLUMNS: Array<{ key: keyof KanbanGroups; status: CacambaStatus; label: string }> = [
  { key: 'disponivel',  status: 'disponivel',  label: 'Recepção' },
  { key: 'locada',      status: 'locada',      label: 'Diagnóstico' },
  { key: 'aguardando',  status: 'aguardando',  label: 'Aguardando peças' },
  { key: 'manutencao',  status: 'manutencao',  label: 'Em execução' },
  { key: 'pronta',      status: 'pronta',      label: 'Pronto p/ retirar' },
];

// D-05 — chave de filtro por KPI. As 5 etapas mapeiam pra coluna FSM; `urgentes`
// filtra os atrasados (is_overdue) em todas as colunas. `valor` não filtra.
type KpiFilterKey = 'recepcao' | 'diagnostico' | 'pecas' | 'execucao' | 'urgentes';

const KPI_FILTER_TO_STATUS: Record<
  Exclude<KpiFilterKey, 'urgentes'>,
  CacambaStatus
> = {
  recepcao: 'disponivel',
  diagnostico: 'locada',
  pecas: 'aguardando',
  execucao: 'manutencao',
};

const KPI_FILTER_KEYS: readonly KpiFilterKey[] = [
  'recepcao',
  'diagnostico',
  'pecas',
  'execucao',
  'urgentes',
];

const isKpiFilterKey = (v: string): v is KpiFilterKey =>
  (KPI_FILTER_KEYS as readonly string[]).includes(v);

const formatBRLCompact = (value: number) =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));

// ─── Drag-drop mapping FSM ───────────────────────────────────────────────────
//
// Mapping FROM-coluna → TO-coluna → action FSM (cacamba_locacao seeder).
// Bloqueia transições inválidas com mensagem orientação.
//
// Ver memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
// Ver Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php

type MappingResult =
  | {
      kind: 'allowed';
      actionKey: string;
      actionLabel: string;
      isCritical: boolean;
      title: string;
      description: string;
    }
  | { kind: 'blocked'; reason: string; tone: 'info' | 'warning' }
  | { kind: 'redirect_sells'; reason: string };

function resolveDragMapping(
  from: CacambaStatus,
  to: CacambaStatus,
  cacamba: CacambaCardData,
): MappingResult {
  // Mesma coluna — defensivo (KanbanDndProvider já filtra)
  if (from === to) {
    return { kind: 'blocked', reason: 'Mesma coluna', tone: 'info' };
  }

  const plate = cacamba.plate ?? 'veículo';
  const cliente = cacamba.cliente_nome ?? 'cliente';

  // disponivel → locada: precisa criar OS, não pode inline V1
  if (from === 'disponivel' && to === 'locada') {
    return {
      kind: 'redirect_sells',
      reason:
        'Pra abrir OS use "Criar OS" no menu Ordens de Serviço — V1 não cria OS inline via drag.',
    };
  }

  // locada → aguardando: transição automática (overdue calc), não manual
  if (from === 'locada' && to === 'aguardando') {
    return {
      kind: 'blocked',
      reason:
        'Aguardando peça é calculado automático quando passa do prazo — não move manual.',
      tone: 'info',
    };
  }

  // locada → manutencao: enviar_manutencao (action FSM crítica — Vehicle stage)
  if (from === 'locada' && to === 'manutencao') {
    return {
      kind: 'allowed',
      actionKey: 'enviar_manutencao',
      actionLabel: 'Enviar pra manutenção',
      isCritical: true,
      title: 'Enviar pra manutenção?',
      description: `Veículo ${plate} vai sair do serviço atual e ir pra bancada de manutenção. Confirma?`,
    };
  }

  // aguardando → manutencao: enviar_manutencao
  if (from === 'aguardando' && to === 'manutencao') {
    return {
      kind: 'allowed',
      actionKey: 'enviar_manutencao',
      actionLabel: 'Enviar pra manutenção',
      isCritical: true,
      title: 'Enviar veículo pra manutenção?',
      description: `Veículo ${plate} de ${cliente} vai ser movido pra bancada de manutenção.`,
    };
  }

  // aguardando → disponivel: action 'recolher' (encerra OS sem manutenção adicional)
  if (from === 'aguardando' && to === 'disponivel') {
    const dias = cacamba.dias_locacao ?? 0;
    const valor = cacamba.valor_receber ?? 0;
    return {
      kind: 'allowed',
      actionKey: 'recolher',
      actionLabel: 'Encerrar OS',
      isCritical: false,
      title: 'Confirmar encerramento da OS?',
      description: `Encerrar OS do veículo ${plate} de ${cliente}. Dias em serviço: ${dias} · Valor: ${formatBRLCompact(valor)}.`,
    };
  }

  // manutencao → disponivel: voltar_disponivel (action FSM Vehicle)
  if (from === 'manutencao' && to === 'disponivel') {
    return {
      kind: 'allowed',
      actionKey: 'voltar_disponivel',
      actionLabel: 'Liberar pra próximo serviço',
      isCritical: false,
      title: 'Manutenção finalizada?',
      description: `Veículo ${plate} volta pro pátio disponível pra próximo serviço.`,
    };
  }

  // manutencao → pronta: concluir (ServiceOrder manut ativa)
  if (from === 'manutencao' && to === 'pronta') {
    return {
      kind: 'allowed',
      actionKey: 'concluir',
      actionLabel: 'Concluir serviço',
      isCritical: true,
      title: 'Finalizar manutenção?',
      description: `Veículo ${plate} fica pronto pra entrega ao cliente (concluído oficina).`,
    };
  }

  // pronta → disponivel: voltar_disponivel
  if (from === 'pronta' && to === 'disponivel') {
    return {
      kind: 'allowed',
      actionKey: 'voltar_disponivel',
      actionLabel: 'Voltar pro pátio',
      isCritical: false,
      title: 'Veículo volta ao pátio?',
      description: `Veículo ${plate} fica disponível pra próximo serviço.`,
    };
  }

  // Tudo o resto — bloqueia
  return {
    kind: 'blocked',
    reason: `Transição não permitida (${from} → ${to})`,
    tone: 'warning',
  };
}

// D-02 — coluna-alvo do "avançar direto pelo card" por estado de origem (o botão do
// card dispara a MESMA porta gate-guardada do arrasto · resolveDragMapping). `locada`
// = "Acompanhar" (ver, não avançar) → tratado no card via onClick, fica null aqui.
const NEXT_COLUMN_FOR: Record<CacambaStatus, CacambaStatus | null> = {
  disponivel: 'locada',     // Iniciar locação → redirect criar OS
  locada:     null,         // Acompanhar → abre drawer (não avança)
  aguardando: 'disponivel', // Recolher → encerrar OS
  manutencao: 'pronta',     // Concluir → pronto p/ entrega
  pronta:     'disponivel', // Entregar → volta ao pátio
};

// ─── Component ───────────────────────────────────────────────────────────────

export default function ProducaoOficinaIndex({ kanban, kpis, filters }: Props) {
  const [searchInput, setSearchInput] = useState(filters.q ?? '');
  const searchInputRef = useRef<HTMLInputElement>(null);

  // D-06 — preferência por-usuário Tier-0-scoped (multi-tenant, ADR 0093): a chave
  // do localStorage carrega o business_id (sessão, via shared props) pra NÃO vazar
  // o filtro entre tenants num mesmo browser.
  const page = usePage();
  const bizId =
    (page.props as { auth?: { user?: { business_id?: number } } })?.auth?.user
      ?.business_id ?? 0;
  const kpiFilterStorageKey = `oimpresso.b${bizId}.oficina.kpiFilter`;

  // D-05 — filtro por KPI (toggle). D-07 — card em foco por teclado.
  const [kpiFilter, setKpiFilter] = useState<KpiFilterKey | null>(null);
  const [focusedId, setFocusedId] = useState<number | null>(null);

  // D-06 — hidrata o último filtro escolhido (client-only; SSR não tem localStorage).
  useEffect(() => {
    try {
      const saved = window.localStorage.getItem(kpiFilterStorageKey);
      if (saved && isKpiFilterKey(saved)) setKpiFilter(saved);
    } catch {
      /* localStorage indisponível (modo privado) — ignora */
    }
  }, [kpiFilterStorageKey]);

  // D-06 — persiste a escolha entre sessões.
  useEffect(() => {
    try {
      if (kpiFilter) window.localStorage.setItem(kpiFilterStorageKey, kpiFilter);
      else window.localStorage.removeItem(kpiFilterStorageKey);
    } catch {
      /* ignora */
    }
  }, [kpiFilter, kpiFilterStorageKey]);

  const toggleKpiFilter = useCallback((key: KpiFilterKey) => {
    setKpiFilter((cur) => (cur === key ? null : key));
    setFocusedId(null);
  }, []);

  // Debounce 300ms — evita visit por keystroke
  useEffect(() => {
    if (searchInput === (filters.q ?? '')) return;
    const t = setTimeout(() => {
      router.get(
        '/oficina-auto/producao-oficina',
        { q: searchInput || undefined },
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

  // ─── Drag-drop state + handlers ─────────────────────────────────────────
  const [pendingTransition, setPendingTransition] =
    useState<PendingTransition | null>(null);
  const [transitionLoading, setTransitionLoading] = useState(false);

  const handleDragMove = useCallback(
    (
      cacambaId: number,
      fromColumn: CacambaStatus,
      toColumn: CacambaStatus,
      cacamba: CacambaCardData,
    ) => {
      const result = resolveDragMapping(fromColumn, toColumn, cacamba);

      if (result.kind === 'redirect_sells') {
        toast.info(result.reason, {
          action: {
            label: 'Criar OS',
            onClick: () => router.visit('/oficina-auto/ordens-servico/create'),
          },
          duration: 6000,
        });
        return;
      }

      if (result.kind === 'blocked') {
        if (result.tone === 'warning') {
          toast.warning(result.reason);
        } else {
          toast.info(result.reason);
        }
        // D-01 — "gate falha → o drawer abre já no que falta": em vez de só toast,
        // abre o documento da OS pra o usuário ver o estado (quando há OS ativa).
        if (cacamba.current_rental_id) {
          setOpenOsId(cacamba.current_rental_id);
        }
        return;
      }

      // Allowed — abre dialog de confirmação
      setPendingTransition({
        cacambaId,
        rentalId: cacamba.current_rental_id,
        fromColumn,
        toColumn,
        actionKey: result.actionKey,
        actionLabel: result.actionLabel,
        isCritical: result.isCritical,
        title: result.title,
        description: result.description,
        plate: cacamba.plate,
        cliente_nome: cacamba.cliente_nome,
        valor_receber: cacamba.valor_receber,
        dias_locacao: cacamba.dias_locacao,
      });
    },
    [],
  );

  // D-01 — veredito preditivo síncrono pra pintar as colunas durante o arrasto.
  // Reusa resolveDragMapping (mesma máquina do drop real) — verde se avança, âmbar se barra.
  const evaluateDrop = useCallback(
    (from: CacambaStatus, to: CacambaStatus, cacamba: CacambaCardData) => {
      const r = resolveDragMapping(from, to, cacamba);
      if (r.kind === 'allowed') return r.isCritical ? 'confirm' : 'advance';
      return 'blocked'; // redirect_sells + blocked: não avança soltando aqui
    },
    [],
  );

  // D-02 — "avançar etapa direto no card": o botão dispara a MESMA porta do arrasto
  // (handleDragMove → resolveDragMapping → confirm/toast). Touch-friendly (tablet do mecânico).
  const handleCardAdvance = useCallback(
    (cacamba: CacambaCardData, from: CacambaStatus) => {
      const to = NEXT_COLUMN_FOR[from];
      if (!to) {
        // Estado sem avanço definido (ex.: locada "Acompanhar") — abre o drawer.
        handleCardClick(cacamba);
        return;
      }
      handleDragMove(cacamba.id, from, to, cacamba);
    },
    [handleDragMove, handleCardClick],
  );

  const handleConfirmTransition = useCallback(async () => {
    if (!pendingTransition) return;

    // Sem rental_id, não conseguimos disparar action via ServiceOrderFsmActionController
    // (endpoint canônico precisa do {order} model bind). V2 pode adicionar Vehicle FSM endpoint.
    if (pendingTransition.rentalId == null) {
      toast.warning(
        'Veículo sem OS ativa — abra o card pra iniciar pipeline FSM antes.',
      );
      setPendingTransition(null);
      return;
    }

    setTransitionLoading(true);
    try {
      const csrf = (
        document.querySelector(
          'meta[name="csrf-token"]',
        ) as HTMLMetaElement | null
      )?.content;

      const res = await fetch(
        `/oficina-auto/service-orders/${pendingTransition.rentalId}/fsm/execute`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
          },
          credentials: 'same-origin',
          body: JSON.stringify({ action_key: pendingTransition.actionKey }),
        },
      );

      const json = await res.json().catch(() => ({}));

      if (!res.ok) {
        toast.error(json?.error ?? `Falha HTTP ${res.status}`);
        setTransitionLoading(false);
        return;
      }

      toast.success(`Transição aplicada: ${pendingTransition.actionLabel}`);
      setPendingTransition(null);
      // Refresh kanban + kpis (server-side recalcula colunas)
      router.reload({
        only: ['kanban', 'kpis'],
        preserveScroll: true,
        preserveState: true,
      });
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : 'Erro ao executar transição',
      );
    } finally {
      setTransitionLoading(false);
    }
  }, [pendingTransition]);

  const handleCancelTransition = useCallback(() => {
    if (!transitionLoading) setPendingTransition(null);
  }, [transitionLoading]);

  // Memoiza 5 cards arrays — só re-render se conteúdo mudar (lição PR #717)
  const columnsData = useMemo(
    () => COLUMNS.map((col) => ({
      ...col,
      cards: kanban[col.key] ?? [],
    })),
    [kanban]
  );

  // 6 KPI cards — modelo (A) reparo (espelha `oficina-page.jsx` → totals).
  // Labels reparo; valores continuam vindo das mesmas keys FSM canon (compat Martinho).
  // Tons por token: urgente=destructive · valor=success (resto default).
  const kpiCards = useMemo(
    () => [
      {
        id: 'recepcao',
        label: 'Recepção',
        value: String(kpis.disponivel),
        sub: 'aguardando triagem',
        tone: 'default' as const,
      },
      {
        id: 'diagnostico',
        label: 'Em diagnóstico',
        value: String(kpis.locada),
        sub: 'em análise técnica',
        tone: 'default' as const,
      },
      {
        id: 'pecas',
        label: 'Aguardando peças',
        value: String(kpis.aguardando_recolhimento),
        sub: 'peça ou aprovação',
        tone: 'warning' as const,
      },
      {
        id: 'execucao',
        label: 'Em execução',
        value: String(kpis.manutencao),
        sub: 'serviço em andamento',
        tone: 'default' as const,
      },
      {
        id: 'urgentes',
        label: 'Urgentes',
        value: String(kpis.atrasadas),
        sub: 'prazo crítico',
        tone: 'destructive' as const,
      },
      {
        id: 'valor',
        label: 'Valor em curso',
        value: formatBRLCompact(kpis.valor_em_curso),
        sub: 'faturamento previsto',
        tone: 'success' as const,
      },
    ],
    [kpis]
  );

  // KPI inline filter bar (espelha "8 veículos · 1 atrasada · 1 aguarda peça")
  const kpiSummary = useMemo(() => {
    const parts: string[] = [
      `${kpis.total} ${kpis.total === 1 ? 'veículo' : 'veículos'}`,
    ];
    if (kpis.atrasadas > 0) {
      parts.push(`${kpis.atrasadas} ${kpis.atrasadas === 1 ? 'atrasada' : 'atrasadas'}`);
    }
    if (kpis.aguardando_recolhimento > 0) {
      parts.push(`${kpis.aguardando_recolhimento} aguardando peças`);
    }
    return parts;
  }, [kpis]);

  // D-05 — aplica o filtro de KPI ao quadro (client-side, alimenta o kanban).
  // Etapa → mantém só a coluna correspondente; `urgentes` → só os atrasados.
  const filteredColumnsData = useMemo(() => {
    if (!kpiFilter) return columnsData;
    if (kpiFilter === 'urgentes') {
      return columnsData.map((col) => ({
        ...col,
        cards: col.cards.filter((c) => c.is_overdue),
      }));
    }
    const targetStatus = KPI_FILTER_TO_STATUS[kpiFilter];
    return columnsData.map((col) => ({
      ...col,
      cards: col.status === targetStatus ? col.cards : [],
    }));
  }, [columnsData, kpiFilter]);

  // D-07 — lista linear dos cards visíveis pra navegação por setas.
  const flatCards = useMemo(
    () => filteredColumnsData.flatMap((col) => col.cards),
    [filteredColumnsData],
  );

  // D-07 — atalhos de teclado (Larissa é teclado-first): N nova OS · / busca ·
  // setas navegam o card em foco · Enter abre o drawer · Esc fecha.
  // Ignora quando o foco está num input/textarea/select (não sequestra digitação).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const tag = (target?.tagName ?? '').toLowerCase();
      const typing =
        tag === 'input' ||
        tag === 'textarea' ||
        tag === 'select' ||
        Boolean(target?.isContentEditable);

      if (e.key === 'Escape') {
        setOpenOsId(null);
        return;
      }
      if (typing) return;

      if (e.key === '/') {
        e.preventDefault();
        searchInputRef.current?.focus();
        return;
      }
      if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        router.visit('/oficina-auto/ordens-servico/create');
        return;
      }
      if (flatCards.length === 0) return;

      if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
        e.preventDefault();
        setFocusedId((id) => {
          const i = flatCards.findIndex((c) => c.id === id);
          const next = flatCards[Math.min(flatCards.length - 1, i + 1)];
          return next ? next.id : id;
        });
      } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
        e.preventDefault();
        setFocusedId((id) => {
          const i = flatCards.findIndex((c) => c.id === id);
          const prev = flatCards[i <= 0 ? 0 : i - 1];
          return prev ? prev.id : id;
        });
      } else if (e.key === 'Enter' && focusedId != null) {
        const c = flatCards.find((x) => x.id === focusedId);
        if (c) handleCardClick(c);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [flatCards, focusedId, handleCardClick]);

  return (
    <>
      <Head title="Oficina Auto" />
      <Box bg="muted" className="-m-6 min-h-[calc(100vh-3rem)]">
        {/* ─── Topbar header — h1 + sub + ações (primitivos ADR 0253) ─── */}
        <Box asChild bg="card" px={6} py={4} className="border-b border-border">
          <header>
            <Inline justify="between" align="start" wrap gap={4}>
              <Stack gap={1} className="min-w-0">
                <Text as="h1" size="lg" weight="semibold">
                  Oficina Auto
                </Text>
                <Text as="p" size="xs" tone="muted">
                  Recepção, diagnóstico, peças, execução e entrega de veículos
                </Text>
              </Stack>
              <Inline gap={2} wrap>
                {/* Toggle Kanban|Lista — Lista navega pra /veiculos por enquanto */}
                <div
                  className="inline-flex rounded border border-border bg-card overflow-hidden"
                  role="group"
                  aria-label="Visualização"
                >
                  <button
                    className="px-2.5 py-1 text-xs font-medium bg-foreground text-background inline-flex items-center gap-1"
                    disabled
                    aria-pressed="true"
                  >
                    <LayoutGrid size={12} />
                    Kanban
                  </button>
                  <Link
                    href="/oficina-auto/veiculos"
                    className="px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted inline-flex items-center gap-1"
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
                  <Link href="/oficina-auto/ordens-servico/create">
                    <Plus className="mr-1.5 h-4 w-4" />
                    Nova OS
                  </Link>
                </Button>
              </Inline>
            </Inline>
          </header>
        </Box>

        {/* ─── 6 KPI cards — Grid auto-fit reflowa 1280↔1440 sem media-query (ADR 0253).
             D-05: as 5 etapas são clicáveis (toggle) e filtram o quadro; ativo ganha
             anel, os outros esmaecem. "Valor em curso" não filtra. ─── */}
        <Box bg="card" px={6} py={4} className="border-b border-border">
          <Grid min="sm" gap={3}>
            {kpiCards.map(({ id, ...kpi }) => {
              const filterKey = isKpiFilterKey(id) ? id : undefined;
              const isActive = filterKey != null && kpiFilter === filterKey;
              const isDimmed =
                kpiFilter != null && filterKey != null && !isActive;
              return (
                <KpiCard
                  key={id}
                  {...kpi}
                  active={isActive}
                  dimmed={isDimmed}
                  onToggle={filterKey ? () => toggleKpiFilter(filterKey) : undefined}
                />
              );
            })}
          </Grid>
        </Box>

        {/* ─── Filter bar sticky — busca + KPI inline ─── */}
        <Box bg="card" px={6} py={3} className="border-b border-border sticky top-0 z-10">
          <Inline gap={6} wrap className="w-full">
            <Inline gap={2} className="flex-1 min-w-[240px] max-w-md">
              <Search size={14} className="text-muted-foreground/60 flex-shrink-0" />
              <Input
                ref={searchInputRef}
                type="search"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Buscar OS, veículo ou cliente… (/ foca)"
                className="h-8 border-border"
                aria-label="Buscar OS, veículo ou cliente"
              />
            </Inline>

            {/* D-05 — chip pra limpar o filtro de KPI ativo */}
            {kpiFilter && (
              <button
                type="button"
                onClick={() => setKpiFilter(null)}
                className="inline-flex items-center gap-1 h-8 px-2.5 rounded text-xs font-medium text-muted-foreground border border-border hover:bg-muted transition-colors flex-shrink-0"
              >
                <X size={12} />
                Limpar filtro
              </button>
            )}

            {/* KPI inline (à direita) */}
            <Inline gap={0} className="ml-auto" aria-live="polite">
              {kpiSummary.map((part, i) => (
                <span key={part}>
                  {i > 0 && <span className="mx-1.5 text-muted-foreground/60">·</span>}
                  <Text
                    as="span"
                    size="sm"
                    weight="medium"
                    tone={
                      part.includes('atrasada')
                        ? 'destructive'
                        : part.includes('aguardando')
                          ? 'warning'
                          : 'default'
                    }
                  >
                    {part}
                  </Text>
                </span>
              ))}
            </Inline>
          </Inline>
        </Box>

        {/* ─── Kanban 5 colunas (drag-drop entre colunas) ─── */}
        <Box p={6}>
          <KanbanDndProvider onMove={handleDragMove} evaluateDrop={evaluateDrop}>
            <Grid cols={5} gap={4}>
              {filteredColumnsData.map((col) => (
                <CacambaKanbanColumn
                  key={col.key}
                  status={col.status}
                  label={col.label}
                  cards={col.cards}
                  onCardClick={handleCardClick}
                  onCardAdvance={handleCardAdvance}
                  focusedId={focusedId}
                />
              ))}
            </Grid>
          </KanbanDndProvider>
        </Box>
      </Box>

      {/* Drawer rico polimórfico (locação CNAE 4581 + manutenção CNAE 4520) — Wave 2.2 US-OFICINA-027
          embute FsmActionPanel reusado · seção PEÇAS & MÃO DE OBRA consome data.items[] */}
      <ServiceOrderRichSheet
        serviceOrderId={openOsId}
        open={openOsId !== null}
        onOpenChange={handleSheetOpenChange}
        onOrderChanged={handleOrderChanged}
      />

      {/* Dialog de confirmação pra transição FSM via drag-drop */}
      <DragConfirmDialog
        pending={pendingTransition}
        loading={transitionLoading}
        onConfirm={handleConfirmTransition}
        onCancel={handleCancelTransition}
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
  tone: 'default' | 'warning' | 'destructive' | 'success';
  /** D-05 — quando presente, o card é clicável (filtra o quadro) e vira `<button>`. */
  onToggle?: () => void;
  active?: boolean;
  dimmed?: boolean;
}

// KpiCard — composto nos primitivos (ADR 0253). Superfície neutra (card branco);
// o destaque vem do `tone` semântico no valor, não de fundo tingido feito à mão.
// D-05: quando `onToggle` existe, é um `<button>` real (a11y: teclado nativo +
// aria-pressed), NUNCA um `div role="button"`.
function KpiCard({ label, value, sub, tone, onToggle, active = false, dimmed = false }: KpiCardProps) {
  const body = (
    <Stack gap={0}>
      <Text
        as="span"
        size="xs"
        weight="semibold"
        tone="muted"
        className="uppercase tracking-wider"
      >
        {label}
      </Text>
      <Text as="span" size="4xl" weight="bold" numeric="tabular" tone={tone}>
        {value}
      </Text>
      <Text as="span" size="xs" tone="muted">
        {sub}
      </Text>
    </Stack>
  );

  if (!onToggle) {
    return (
      <Box bg="card" border rounded="lg" p={3}>
        {body}
      </Box>
    );
  }

  return (
    <Box
      asChild
      bg="card"
      border
      rounded="lg"
      p={3}
      className={
        'w-full text-left cursor-pointer transition-colors hover:border-primary/40 ' +
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ' +
        (active ? 'ring-1 ring-primary border-primary/50 ' : '') +
        (dimmed ? 'opacity-50 ' : '')
      }
    >
      <button type="button" onClick={onToggle} aria-pressed={active}>
        {body}
      </button>
    </Box>
  );
}
