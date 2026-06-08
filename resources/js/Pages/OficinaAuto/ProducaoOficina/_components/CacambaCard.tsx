// Card individual de caçamba no Kanban Produção · Oficina — V3 PIXEL-PERFECT.
// Espelha 1:1 protótipo Cowork canon `prototipo-ui/prototipos/producao-oficina/visual-source.html`
// adaptado pra caçambas (vocabulário m³ + locação + recolhimento, não revisão veicular).
//
// Estrutura V3 (12 fixes do canon):
//  1. Borda superior colorida por coluna (slate/blue/rose/violet/emerald)
//  2. OS# esquerda + capacidade badge (m³ box) direita
//  3. Layout horizontal: [Plate Mercosul] · "Caçamba 5m³" + sub "vehicle_number · cliente"
//  4. Observação MAIS LEGÍVEL (text-[12px] not italic, leading-snug)
//  5. Valor R$ canto superior direito (junto OS#) quando ativa
//  6. Progress bar fina embaixo do sintoma (tempo decorrido / prazo)
//  7. Avatar mecânico + nome em LINHA SEPARADA embaixo
//  8. ETA + prazo embaixo separados ("há 5 dias · 5 diárias" + "vence dd/mm")
//  9. Banners coloridos status (Atrasada amber / Aguardando rose / Pronta verde)
// 10. Botões de ação por estado (Iniciar/Recolher/Concluir/Entregar)
// 11. Coluna Pronta com "✓ disponível" + concluído + valor + botão Entregar
// 12. Border highlighted colorida quando URGENTE (varia por coluna)
//
// Lição PR #717 — useMemo/useCallback nos handlers descendentes pra evitar re-render loop
// quando hierarquia profunda (Index → Coluna → Card × N).

import { memo, type CSSProperties, type MouseEvent } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import {
  Clock,
  MapPin,
  ArrowRight,
  CheckCircle2,
  Wrench,
} from 'lucide-react';
import MercosulPlate from './MercosulPlate';

export type CacambaStatus = 'disponivel' | 'locada' | 'aguardando' | 'manutencao' | 'pronta';

export interface CacambaCardData {
  id: number;
  plate: string;
  vehicle_number: string | null;
  capacity_m3: number | null;
  current_status: string;
  is_overdue: boolean;
  current_rental_id: number | null;
  os_number: number | null;
  rental_created_at: string | null;
  rental_notes: string | null;
  cliente_nome: string | null;
  delivery_address: string | null;
  entered_at: string | null;
  expected_return: string | null;
  dias_locacao: number | null;
  daily_rate: number | null;
  valor_receber: number | null;
  atendente_nome: string | null;
  atendente_iniciais: string | null;
  // Eixo de recurso (modelo reparo) — box (texto livre) + mecânico atribuído.
  box_label?: string | null;
  mecanico_nome?: string | null;
  mecanico_iniciais?: string | null;
}

interface Props {
  cacamba: CacambaCardData;
  variant: CacambaStatus;
  onClick: (cacamba: CacambaCardData) => void;
  /**
   * D-02 — "avançar etapa direto no card". Quando presente, o botão de ação dos
   * estados de avanço (Iniciar/Recolher/Concluir/Entregar) dispara a MESMA porta do
   * arrasto (gate-guardada · resolveDragMapping no Index) em vez de só abrir o drawer.
   * "Acompanhar" (locada) segue abrindo o drawer (é ver, não avançar).
   */
  onAdvance?: (cacamba: CacambaCardData) => void;
  /**
   * D-07 — card em foco via teclado (setas no Index). Pinta o anel pra o usuário
   * ver onde está; Enter abre o drawer (tratado no Index).
   */
  isFocused?: boolean;
}

/**
 * D-04 — tom de SLA por prazo (função pura, sem efeito colateral).
 * `destructive` = atrasada · `warning` = vence hoje/amanhã · `null` = calmo (sem borda).
 * Só para estados ativos (locada/aguardando) — pátio/pronta não têm prazo em curso.
 */
function slaTone(
  card: CacambaCardData,
  variant: CacambaStatus,
): 'destructive' | 'warning' | null {
  if (variant !== 'locada' && variant !== 'aguardando') return null;
  if (card.is_overdue) return 'destructive';
  if (card.expected_return) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = new Date(card.expected_return + 'T00:00:00');
    if (!Number.isNaN(due.getTime())) {
      const diffDays = Math.round((due.getTime() - today.getTime()) / 86_400_000);
      if (diffDays <= 1) return 'warning';
    }
  }
  return null;
}

const SLA_LEFT_BORDER: Record<'destructive' | 'warning', string> = {
  destructive: 'border-l-2 border-l-destructive',
  warning: 'border-l-2 border-l-warning',
};

const formatBRL = (value: number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value ?? 0));

/** Borda topo colorida por coluna — espelha .prod-col-{slate,blue,rose,violet,emerald}. */
const TOP_BORDER_COLOR: Record<CacambaStatus, string> = {
  disponivel: 'border-t-[var(--stage-slate)]',
  locada:     'border-t-[var(--stage-blue)]',
  aguardando: 'border-t-[var(--stage-rose)]',
  manutencao: 'border-t-[var(--stage-violet)]',
  pronta:     'border-t-[var(--stage-emerald)]',
};

/** Cor da progress bar por etapa. */
const PROGRESS_BAR_COLOR: Record<CacambaStatus, string> = {
  disponivel: 'bg-[var(--stage-slate)]',
  locada:     'bg-[var(--stage-blue)]',
  aguardando: 'bg-[var(--stage-rose)]',
  manutencao: 'bg-[var(--stage-violet)]',
  pronta:     'bg-[var(--stage-emerald)]',
};

/** Cor do BG da progress track (mais clara). */
const PROGRESS_TRACK_COLOR: Record<CacambaStatus, string> = {
  disponivel: 'bg-[var(--stage-slate)]/15',
  locada:     'bg-[var(--stage-blue)]/15',
  aguardando: 'bg-[var(--stage-rose)]/15',
  manutencao: 'bg-[var(--stage-violet)]/15',
  pronta:     'bg-[var(--stage-emerald)]/15',
};

/** "vence dd/mm" derivado de expected_return — espelha .ofc-eta-row "prazo Sex 17h". */
function formatVence(iso: string | null): string | null {
  if (!iso) return null;
  // expected_return é date (sem hora), parse como local
  const parts = iso.split('-');
  if (parts.length < 3) return null;
  const dd = parts[2].padStart(2, '0');
  const mm = parts[1].padStart(2, '0');
  return `vence ${dd}/${mm}`;
}

function relativeDays(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  const diffMs = Date.now() - d.getTime();
  const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  if (days === 0) return 'hoje';
  if (days === 1) return 'há 1 dia';
  if (days < 30) return `há ${days} dias`;
  const months = Math.floor(days / 30);
  return months === 1 ? 'há 1 mês' : `há ${months} meses`;
}

function truncate(s: string | null, max = 38): string {
  if (!s) return '—';
  return s.length > max ? s.slice(0, max - 1) + '…' : s;
}

/** Progress relativo: dias decorridos / total previsto (entered_at → expected_return). */
function computeProgressPct(entered: string | null, expected: string | null): number {
  if (!entered || !expected) return 0;
  const start = new Date(entered).getTime();
  const end = new Date(expected + 'T23:59:59').getTime();
  if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return 0;
  const now = Date.now();
  const total = end - start;
  const elapsed = now - start;
  const pct = Math.max(0, Math.min(100, Math.floor((elapsed / total) * 100)));
  return pct;
}

/** Botão de ação por estado — texto + leve hint. Click stops propagation. */
function actionLabelFor(variant: CacambaStatus): string | null {
  switch (variant) {
    case 'disponivel': return 'Iniciar locação';
    case 'locada':     return 'Acompanhar';
    case 'aguardando': return 'Recolher';
    case 'manutencao': return 'Concluir';
    case 'pronta':     return 'Entregar';
    default:           return null;
  }
}

function CacambaCardImpl({ cacamba, variant, onClick, onAdvance, isFocused = false }: Props) {
  // D-02 — estados de avanço usam a porta gate-guardada (onAdvance); "Acompanhar"
  // (locada) é ver, não avançar → segue abrindo o drawer (onClick).
  const canAdvance = onAdvance != null && variant !== 'locada';
  const triggerAction = (e: MouseEvent) => {
    e.stopPropagation();
    if (canAdvance) {
      onAdvance!(cacamba);
    } else {
      onClick(cacamba);
    }
  };
  // Drag handle — distance:8 no PointerSensor (KanbanDndProvider) evita drag acidental
  // em onClick "abrir drawer". Card inteiro é o drag handle (UX padrão Kanban).
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `cacamba-${cacamba.id}`,
    data: {
      cacambaId: cacamba.id,
      currentColumn: variant,
      cacamba,
    },
  });

  const dragStyle: CSSProperties = transform
    ? { transform: CSS.Translate.toString(transform) }
    : {};

  const isAprovacao = variant === 'aguardando';
  const isPronta = variant === 'pronta';
  const isAtiva = variant === 'locada' || variant === 'aguardando';
  const hasRentalContext = cacamba.cliente_nome != null || cacamba.delivery_address != null;

  // Borda highlighted variando por coluna quando urgente — fix #12
  const cardBaseBorder =
    isAprovacao
      ? 'border-destructive/40 hover:border-destructive'
      : 'border-border hover:border-muted-foreground/40';

  const cardBg = isAprovacao
    ? 'bg-warning/10'
    : isPronta
      ? 'bg-[var(--stage-emerald)]/8'
      : 'bg-white';

  const opacityClass = isPronta ? 'opacity-95' : '';

  // D-04 — borda-esquerda por SLA (token semântico). D-07 — anel de foco por teclado.
  const sla = slaTone(cacamba, variant);
  const slaBorder = sla ? SLA_LEFT_BORDER[sla] : '';
  const focusRing = isFocused && !isDragging ? 'ring-2 ring-primary ring-offset-1' : '';

  const osLabel = cacamba.os_number ? `OS #${cacamba.os_number}` : null;
  const venceLabel = formatVence(cacamba.expected_return);
  const progressPct = computeProgressPct(cacamba.entered_at, cacamba.expected_return);
  const progressColor = isAprovacao ? 'bg-[var(--stage-rose)]' : PROGRESS_BAR_COLOR[variant];
  const trackColor = isAprovacao ? 'bg-[var(--stage-rose)]/15' : PROGRESS_TRACK_COLOR[variant];
  const actionLabel = actionLabelFor(variant);

  // Valor superior direito apenas quando ativa (espelha canon: valor no topo)
  const showValorTop = isAtiva && cacamba.valor_receber != null && cacamba.valor_receber > 0;

  // Subtítulo horizontal: "Caçamba {N}m³ · vehicle_number · cliente" (linha única)
  const cacambaTitle = cacamba.capacity_m3 != null
    ? `Caçamba ${Number(cacamba.capacity_m3)}m³`
    : 'Caçamba';

  return (
    <article
      ref={setNodeRef}
      style={dragStyle}
      {...attributes}
      {...listeners}
      className={`${cardBg} ${opacityClass} ${slaBorder} ${focusRing} relative rounded border border-t-2 ${cardBaseBorder} ${TOP_BORDER_COLOR[variant]} p-3 transition-colors ${
        isDragging
          ? 'opacity-50 cursor-grabbing ring-2 ring-primary/60 ring-offset-1'
          : 'cursor-grab active:cursor-grabbing hover:shadow-sm'
      }`}
      onClick={(e) => {
        // Bloqueia click durante drag (PointerSensor distance:8 já filtra,
        // mas defensivo extra). Não interfere em Enter/Space teclado.
        if (isDragging) {
          e.preventDefault();
          return;
        }
        onClick(cacamba);
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          // Space é também o "grab" do KeyboardSensor — só Enter abre drawer
          if (e.key === 'Enter') {
            e.preventDefault();
            onClick(cacamba);
          }
        }
      }}
      role="button"
      tabIndex={0}
      aria-label={`Caçamba ${cacamba.vehicle_number ?? cacamba.plate}${cacamba.cliente_nome ? ` — ${cacamba.cliente_nome}` : ''}`}
      aria-roledescription="Card arrastável — use Space pra agarrar e setas pra mover"
    >
      {/* ─── Linha 1: OS# esquerda · capacidade badge + valor direita ─── */}
      <div className="flex items-center justify-between mb-2 gap-2">
        <span className="font-mono text-[11px] text-muted-foreground font-medium">
          {osLabel ?? <span className="text-muted-foreground/60 italic">sem OS</span>}
        </span>
        <div className="flex items-center gap-1.5 flex-shrink-0">
          {/* Eixo de recurso (modelo reparo): box atribuído à OS. */}
          {cacamba.box_label ? (
            <span
              className="inline-flex items-center gap-1 text-[10.5px] px-1.5 py-0.5 rounded font-medium border bg-primary/5 text-primary border-primary/20 whitespace-nowrap"
              title={`Box: ${cacamba.box_label}`}
            >
              <Wrench size={9} />
              {cacamba.box_label}
            </span>
          ) : null}
          {showValorTop && (
            <span
              className={
                'text-[11px] font-semibold tabular-nums whitespace-nowrap ' +
                (isAprovacao ? 'text-destructive' : 'text-success')
              }
              title="Valor a receber"
            >
              {formatBRL(cacamba.valor_receber)}
            </span>
          )}
          {cacamba.capacity_m3 != null && !isPronta ? (
            <span
              className={
                'text-[10.5px] px-1.5 py-0.5 rounded font-mono font-medium border whitespace-nowrap ' +
                (variant === 'locada'
                  ? 'bg-[var(--stage-blue)]/10 text-[var(--stage-blue)] border-[var(--stage-blue)]/30'
                  : variant === 'aguardando'
                    ? 'bg-[var(--stage-rose)]/10 text-[var(--stage-rose)] border-[var(--stage-rose)]/30'
                    : variant === 'manutencao'
                      ? 'bg-[var(--stage-violet)]/10 text-[var(--stage-violet)] border-[var(--stage-violet)]/30'
                      : 'bg-muted text-muted-foreground border-border')
              }
              title={`Capacidade ${Number(cacamba.capacity_m3)}m³`}
            >
              {Number(cacamba.capacity_m3)}m³
            </span>
          ) : null}
          {isPronta && (
            <span className="inline-flex items-center gap-1 text-[11px] text-success font-medium">
              <CheckCircle2 size={12} />
              disponível
            </span>
          )}
        </div>
      </div>

      {/* ─── Linha 2: layout horizontal — Placa + título "Caçamba Nm³" + sub "vehicle_number · cliente" — fix #3 ─── */}
      <div className="flex items-center gap-2 mb-2">
        <MercosulPlate plate={cacamba.plate} size="sm" />
        <div className="flex flex-col gap-0 min-w-0 flex-1">
          <span className="text-[12.5px] font-medium text-foreground truncate" title={cacambaTitle}>
            {cacambaTitle}
          </span>
          <span className="text-[10.5px] text-muted-foreground truncate font-mono tabular-nums">
            {cacamba.vehicle_number && cacamba.vehicle_number !== cacamba.plate
              ? cacamba.vehicle_number
              : cacamba.plate}
            {cacamba.cliente_nome ? <> · <span className="font-sans not-italic text-foreground">{truncate(cacamba.cliente_nome, 24)}</span></> : null}
          </span>
        </div>
      </div>

      {/* ─── Linha 3: endereço delivery (com pin icon) ─── */}
      {cacamba.delivery_address ? (
        <div className="flex items-start gap-1 text-[11px] text-muted-foreground mb-1.5">
          <MapPin size={10} className="mt-0.5 flex-shrink-0 text-muted-foreground/60" />
          <span className="truncate" title={cacamba.delivery_address}>
            {truncate(cacamba.delivery_address, 40)}
          </span>
        </div>
      ) : null}

      {/* ─── Linha 4: observação LEGÍVEL (NÃO italic) — fix #4 espelha .ofc-symptom ─── */}
      {cacamba.rental_notes ? (
        <p
          className="text-[12px] text-foreground leading-snug mb-2 line-clamp-2"
          title={cacamba.rental_notes}
        >
          {cacamba.rental_notes}
        </p>
      ) : !hasRentalContext && variant === 'manutencao' ? (
        <p className="text-[12px] text-muted-foreground leading-snug mb-2">
          Caçamba em diagnóstico
          {cacamba.entered_at ? <span className="text-muted-foreground/60"> · {relativeDays(cacamba.entered_at)}</span> : null}
        </p>
      ) : !hasRentalContext && variant === 'disponivel' ? (
        <p className="text-[12px] text-muted-foreground leading-snug mb-2 italic">
          No pátio · pronta pra locar
        </p>
      ) : null}

      {/* ─── Linha 5: progress bar fina prazo — fix #6 espelha .prod-progress ─── */}
      {isAtiva && progressPct > 0 ? (
        <div
          className={`h-1 rounded ${trackColor} my-2 overflow-hidden`}
          role="progressbar"
          aria-valuenow={progressPct}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={`Tempo decorrido ${progressPct}%`}
        >
          <div
            className={`h-full ${progressColor} rounded transition-all`}
            style={{ width: `${Math.min(progressPct, 100)}%` }}
          />
        </div>
      ) : null}

      {/* ─── Linha 6: avatar atendente + nome em LINHA SEPARADA — fix #7 espelha MechAv ─── */}
      {isAtiva && (cacamba.atendente_nome || cacamba.atendente_iniciais) ? (
        <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-1.5">
          <span
            className="inline-flex items-center justify-center w-[18px] h-[18px] rounded-full bg-muted text-foreground text-[9px] font-semibold flex-shrink-0"
            title={cacamba.atendente_nome ?? ''}
            aria-label={`Atendente ${cacamba.atendente_nome ?? ''}`}
          >
            {cacamba.atendente_iniciais ?? '?'}
          </span>
          <span className="truncate">{cacamba.atendente_nome ?? '—'}</span>
        </div>
      ) : isAtiva ? (
        <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground/60 italic mt-1.5">
          <Wrench size={11} className="text-muted-foreground/60" />
          sem atendente
        </div>
      ) : null}

      {/* ─── Linha 7: ETA "há N dias · N diárias" esquerda + "vence dd/mm" direita — fix #8 espelha .ofc-eta-row ─── */}
      {isAtiva && cacamba.dias_locacao != null ? (
        <div className="flex items-center justify-between text-[10.5px] text-muted-foreground mt-1.5 tabular-nums">
          <span>
            <span className={isAprovacao ? 'font-semibold text-destructive' : 'font-medium text-foreground'}>
              há {cacamba.dias_locacao} {cacamba.dias_locacao === 1 ? 'dia' : 'dias'}
            </span>
            <span className="text-muted-foreground/60 mx-1">·</span>
            <span>
              {cacamba.dias_locacao} {cacamba.dias_locacao === 1 ? 'diária' : 'diárias'}
            </span>
          </span>
          {venceLabel && (
            <span className={isAprovacao ? 'font-semibold text-destructive' : 'text-muted-foreground'}>
              {venceLabel}
            </span>
          )}
        </div>
      ) : null}

      {/* ─── Banner status colorido — fix #9 espelha .ofc-parts/.ofc-approval ─── */}
      {isAprovacao && (
        <div className="mt-2 px-2 py-1.5 bg-destructive/10 border border-destructive/30 rounded text-[11px] text-destructive flex items-start gap-1.5">
          <span className="inline-block w-1.5 h-1.5 rounded-full bg-destructive mt-1 flex-shrink-0 animate-pulse" />
          <div className="flex-1 leading-snug">
            <b className="font-semibold">Atrasada</b> · cobrar cliente · agendar recolhimento imediato
          </div>
        </div>
      )}
      {variant === 'manutencao' && (
        <div className="mt-2 px-2 py-1.5 bg-[var(--stage-violet)]/10 border border-[var(--stage-violet)]/30 rounded text-[11px] text-[var(--stage-violet)] flex items-start gap-1.5">
          <span className="inline-block w-1.5 h-1.5 rounded-full bg-[var(--stage-violet)] mt-1 flex-shrink-0" />
          <div className="flex-1 leading-snug">
            <b className="font-semibold">Em oficina</b> · diagnóstico em andamento
          </div>
        </div>
      )}
      {variant === 'pronta' && (
        <div className="mt-2 px-2 py-1.5 bg-success/10 border border-success/30 rounded text-[11px] text-success flex items-start gap-1.5">
          <span className="inline-block w-1.5 h-1.5 rounded-full bg-success mt-1 flex-shrink-0" />
          <div className="flex-1 leading-snug">
            <b className="font-semibold">Caçamba pronta</b> · agendar entrega
            {cacamba.entered_at ? <span className="text-success"> · concluído {relativeDays(cacamba.entered_at)}</span> : null}
          </div>
        </div>
      )}

      {/* ─── Rodapé Pronta: valor + botão Entregar — fix #11 espelha CardPronto ─── */}
      {variant === 'pronta' && (
        <div className="mt-2 pt-2 border-t border-success/20 flex items-center justify-between gap-2">
          <span className="text-[11px] text-muted-foreground">
            <Clock size={10} className="inline mr-1 text-muted-foreground/60" />
            no pátio
          </span>
          {cacamba.valor_receber != null && cacamba.valor_receber > 0 ? (
            <span className="font-semibold text-[11px] tabular-nums text-success whitespace-nowrap">
              {formatBRL(cacamba.valor_receber)}
            </span>
          ) : null}
          <button
            type="button"
            className="text-[10.5px] px-2 py-1 bg-foreground text-background rounded font-medium hover:bg-foreground/90 inline-flex items-center gap-1 transition-colors"
            onClick={triggerAction}
            aria-label="Entregar caçamba"
          >
            Entregar
            <ArrowRight size={10} />
          </button>
        </div>
      )}

      {/* ─── Botão de ação por estado (quando NÃO Pronta) — fix #10 ─── */}
      {variant !== 'pronta' && actionLabel && (
        <div className="mt-2 pt-2 border-t border-border flex items-center justify-end">
          <button
            type="button"
            className={
              'text-[10.5px] px-2 py-1 rounded font-medium inline-flex items-center gap-1 transition-colors ' +
              (isAprovacao
                ? 'bg-destructive text-white hover:bg-destructive/90'
                : variant === 'disponivel'
                  ? 'bg-muted text-foreground hover:bg-muted/80 border border-border'
                  : 'bg-foreground text-background hover:bg-foreground/90')
            }
            onClick={triggerAction}
            aria-label={actionLabel}
          >
            {actionLabel}
            <ArrowRight size={10} />
          </button>
        </div>
      )}
    </article>
  );
}

// memo — re-render só quando props efetivos mudam (lição PR #717)
export default memo(CacambaCardImpl);
