// JanaKpiCard — RÉPLICA do `.jc-kpi` da âncora, sob ADR 0388 ("réplica primeiro").
//
// Âncora: `node prototipo-ui/ancora.mjs Jana/Index` -> `prototipo-ui/cowork/jana-merge.jsx`
// §`data.kpis.map` -> `KPICard` (markup em `chat-jana.jsx`, estilo em `chat-jana.css`
// §`── KPIs ──`). Re-localize com `grep -n "jc-kpi" prototipo-ui/cowork/chat-jana.css`.
//
// POR QUE UM COMPONENTE NOVO, e não um ajuste no `KpiCard` shared: o shared serve 37
// telas com a anatomia PT-04 (caixa de ícone 36×36, r12, p16, label sans). A anatomia da
// âncora é OUTRA — label mono coladinho no ícone de 15px, r8, p12/14, altura 98. Mudar o
// shared imporia a forma da Jana ao resto do ERP; a ADR 0388 §D-1 diz que onde há âncora a
// aparência é a do protótipo, e a 0385 diz que "diferente não é erro". Réplica local.
//
// ATENCAO: ESTE COMPONENTE É DE APARÊNCIA. Ele não decide dado, rota nem permissão — ADR 0388 §D-5.
//
// ── Mapa medido âncora -> token de produção (nenhuma cor crua) ────────────────────────
//   .jc-kpi          background --surface · border --border · radius --r-2 (8px)
//                    padding 12px 14px 14px · gap 3px · min-width 0
//                 ->  bg-card · border-border · rounded-lg (--radius-lg = .5rem = 8px)
//                    pt-3 px-3.5 pb-3.5 · gap-[3px] · min-w-0
//   .jc-kpi-h        700 10px/1 mono · uppercase · ls .06em · --text-3 · mb 4px
//                 ->  font-mono text-[10px] font-bold leading-none uppercase
//                    tracking-[0.06em] text-muted-foreground mb-1
//   .jc-kpi-ic       15×15 · stroke --text-3            -> size={15} text-muted-foreground
//   .jc-kpi-v        700 --fs-7/1 sans · ls -.02em · --text · tabular
//                 ->  text-[length:var(--fs-7)] font-bold leading-none tracking-[-0.02em]
//                    text-foreground tabular-nums
//   .jc-kpi-v.red    --neg                              -> text-destructive
//   .jc-kpi-d        11px --text-3 (.down --neg · .up --pos)
//                 ->  text-[11px] text-muted-foreground (text-destructive · text-success)
//   .jc-kpi.emph     background --neg-soft · border --neg 22%
//                 ->  bg-destructive-soft border-destructive/20
//   .emph .jc-kpi-ic --neg          -> text-destructive
//   .emph .jc-kpi-v  --fs-8 (28px)  -> text-[length:var(--fs-8)]
//   .emph .jc-kpi-h/-d  --text      -> text-foreground
//
// DOIS EIXOS SEPARADOS, como a âncora — e é o conserto da errata registrada no
// `KpiCard.tsx` (§"O EIXO pode estar errado"), que os fundira num `tone` só:
//   `emphasis`  <- `emphasize:true`             -> fundo + borda + valor 28px (NÃO cor do valor)
//   `valueTone` <- `deltaCls === "red big"`     -> cor do VALOR, e só ela
// No dataset da âncora os dois caem no mesmo card (N=1), por isso eram indistinguíveis.
// Aqui ficam declaráveis em separado; quem passa os dois é o `JanaCockpit`.
//
// As divergências de token que sobraram estão contadas em
// `memory/requisitos/Jana/INCONSISTENCIAS-replica.md` (ADR 0388 §D-2) — não aqui, porque
// comentário que afirma estado apodrece (§5 2026-08-17).

import { Icon } from '@/Components/Icon';
import { cn } from '@/Lib/utils';

export interface JanaKpiCardProps {
  label: string;
  value: string;
  /** Nome Lucide — o `.jc-kpi-ic` da âncora, 15px, INLINE à direita do rótulo. */
  icon?: string;
  /** `.jc-kpi-d` sem classe de direção — o `sub` da âncora. */
  description?: string | null;
  /** `.jc-kpi-d.down/.up` — percentual; a âncora escreve o `%` no texto. */
  delta?: { value: number; label?: string } | null;
  /** `.jc-kpi.emph` — fundo tintado + borda + valor no degrau `--fs-8`. */
  emphasis?: boolean;
  /** `.jc-kpi-v.red` — só a cor do valor. */
  valueTone?: 'default' | 'negative';
  /** Drill. O clicável é o WRAPPER, como o `.jm-an-hit` da âncora — o card fica DIV. */
  onClick?: () => void;
}

export default function JanaKpiCard({
  label,
  value,
  icon,
  description,
  delta,
  emphasis = false,
  valueTone = 'default',
  onClick,
}: JanaKpiCardProps) {
  const card = (
    <div
      data-slot="jana-kpi"
      data-emphasis={emphasis ? 'true' : undefined}
      className={cn(
        'flex h-full min-w-0 flex-col gap-[3px] rounded-lg border pt-3 pr-3.5 pb-3.5 pl-3.5',
        emphasis ? 'border-destructive/20 bg-destructive-soft' : 'border-border bg-card',
      )}
    >
      <div
        className={cn(
          'mb-1 flex items-center justify-between gap-2 font-mono text-[10px] leading-none font-bold tracking-[0.06em] uppercase',
          emphasis ? 'text-foreground' : 'text-muted-foreground',
        )}
      >
        {/* `min-w-0 break-words`, nunca `truncate`: o rótulo é copy de contrato e tem de
            aparecer inteiro (mesma razão medida no `KpiCard.tsx` em 2026-08-24). */}
        <span className="min-w-0 break-words">{label}</span>
        {icon && (
          <Icon
            name={icon}
            size={15}
            className={cn('shrink-0', emphasis ? 'text-destructive' : 'text-muted-foreground')}
          />
        )}
      </div>

      <b
        className={cn(
          'leading-none font-bold tracking-[-0.02em] tabular-nums',
          emphasis ? 'text-[length:var(--fs-8)]' : 'text-[length:var(--fs-7)]',
          valueTone === 'negative' ? 'text-destructive' : 'text-foreground',
        )}
      >
        {value}
      </b>

      {delta && (
        <small
          className={cn(
            'text-[11px]',
            delta.value < 0 ? 'text-destructive' : delta.value > 0 ? 'text-success' : 'text-muted-foreground',
          )}
        >
          {delta.value > 0 ? '+' : ''}
          {delta.value}%{delta.label ? ` ${delta.label}` : ''}
        </small>
      )}

      {description && (
        <small className={cn('text-[11px]', emphasis ? 'text-foreground' : 'text-muted-foreground')}>
          {description}
        </small>
      )}
    </div>
  );

  if (!onClick) return card;

  // O card em si segue DIV (é o que a sonda compara com o `.jc-kpi` da âncora); quem
  // recebe o clique é o wrapper. A âncora usa `div role="button" tabIndex={0}` — aqui é
  // `<button>` de verdade, que entrega a mesma affordance sem o teclado à mão.
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={`Ver origem de ${label}`}
      className="min-w-0 rounded-lg text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
      {card}
    </button>
  );
}
