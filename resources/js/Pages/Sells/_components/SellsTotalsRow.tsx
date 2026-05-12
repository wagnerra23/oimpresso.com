// US-SELL-017 — Totalizador rodapé compartilhado entre Lista e Grade Avançada.
// Refs: ADR 0136 (Sells: Lista vs Grade Avançada toggle).
//
// Backend (`/sells-list-json`) devolve `totals` calculados sobre o filtro inteiro
// (não só página corrente) — ver SellController@inertiaList §US-SELL-017.

interface SellsTotals {
  count: number;
  sum_final_total: number;
  sum_total_paid: number;
  sum_due: number;
}

interface SellsTotalsRowProps {
  totals: SellsTotals | null;
  loading?: boolean;
  /** Quando true, renderiza versão compacta single-line (Lista mode opt-in). */
  compact?: boolean;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatCount = (n: number) =>
  new Intl.NumberFormat('pt-BR').format(n);

export default function SellsTotalsRow({ totals, loading = false, compact = false }: SellsTotalsRowProps) {
  if (!totals && !loading) return null;

  if (compact) {
    return (
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2 text-xs text-muted-foreground border border-border rounded-md bg-muted/30">
        {loading || !totals ? (
          <span className="italic">Calculando totais…</span>
        ) : (
          <>
            <span>
              Qtd: <strong className="font-semibold tabular-nums text-foreground">{formatCount(totals.count)}</strong> vendas
            </span>
            <span aria-hidden="true">·</span>
            <span>
              Total: <strong className="font-semibold tabular-nums text-foreground">{formatBRL(totals.sum_final_total)}</strong>
            </span>
            <span aria-hidden="true">·</span>
            <span>
              Pago: <strong className="font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{formatBRL(totals.sum_total_paid)}</strong>
            </span>
            <span aria-hidden="true">·</span>
            <span>
              A receber: <strong className="font-semibold tabular-nums text-amber-700 dark:text-amber-300">{formatBRL(totals.sum_due)}</strong>
            </span>
          </>
        )}
      </div>
    );
  }

  // Versão sticky-bottom pra Grade Avançada (4 colunas semantic).
  return (
    <div
      className="sticky bottom-0 z-10 border-t border-border bg-muted/95 backdrop-blur-sm px-4 py-2.5 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs"
      role="contentinfo"
      aria-label="Totais do filtro"
    >
      {loading || !totals ? (
        <div className="col-span-full text-center italic text-muted-foreground">
          Calculando totais…
        </div>
      ) : (
        <>
          <TotalCell label="Qtd" value={formatCount(totals.count)} suffix="vendas" />
          <TotalCell label="Total" value={formatBRL(totals.sum_final_total)} />
          <TotalCell label="Pago" value={formatBRL(totals.sum_total_paid)} accent="emerald" />
          <TotalCell label="A receber" value={formatBRL(totals.sum_due)} accent="amber" />
        </>
      )}
    </div>
  );
}

function TotalCell({
  label,
  value,
  suffix,
  accent,
}: {
  label: string;
  value: string;
  suffix?: string;
  accent?: 'emerald' | 'amber';
}) {
  const accentClass =
    accent === 'emerald'
      ? 'text-emerald-700 dark:text-emerald-300'
      : accent === 'amber'
        ? 'text-amber-700 dark:text-amber-300'
        : 'text-foreground';
  return (
    <div className="flex flex-col">
      <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
        {label}
      </span>
      <span className={'text-sm font-semibold tabular-nums ' + accentClass}>
        {value}
        {suffix && <span className="ml-1 text-[11px] font-normal text-muted-foreground">{suffix}</span>}
      </span>
    </div>
  );
}

export type { SellsTotals };
