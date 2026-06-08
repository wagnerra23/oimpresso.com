/**
 * Mockup placeholder do dashboard. Wireframe estilizado, sem dados reais.
 * PR2: substituir por screenshot do app rodando em produção.
 */
export default function DashboardMockup() {
  return (
    <div className="relative w-full max-w-xl">
      <div className="rounded-2xl border border-border bg-card shadow-2xl">
        {/* Window chrome */}
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <div className="flex gap-1.5">
            <span className="h-2.5 w-2.5 rounded-full bg-destructive/60" />
            <span className="h-2.5 w-2.5 rounded-full bg-yellow-400/70" />
            <span className="h-2.5 w-2.5 rounded-full bg-green-500/70" />
          </div>
          <div className="rounded-md bg-muted px-3 py-1 text-[10px] font-mono text-muted-foreground">
            oimpresso.com/home
          </div>
          <div className="w-12" />
        </div>

        {/* Body */}
        <div className="space-y-4 p-5">
          {/* Title row */}
          <div className="flex items-center justify-between">
            <div>
              <div className="h-3 w-32 rounded bg-foreground/80" />
              <div className="mt-2 h-2 w-44 rounded bg-muted-foreground/30" />
            </div>
            <div className="h-7 w-20 rounded-md bg-primary/90" />
          </div>

          {/* KPI cards */}
          <div className="grid grid-cols-3 gap-3">
            {[
              { label: 'Vendas hoje', value: 'R$ 12.480' },
              { label: 'NF-e emitidas', value: '38' },
              { label: 'A receber', value: 'R$ 84.210' },
            ].map((kpi) => (
              <div key={kpi.label} className="rounded-lg border border-border bg-background p-3">
                <div className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                  {kpi.label}
                </div>
                <div className="mt-1 text-sm font-bold text-foreground">{kpi.value}</div>
                <div className="mt-2 h-1.5 w-full rounded-full bg-muted">
                  <div className="h-full w-3/4 rounded-full bg-primary/80" />
                </div>
              </div>
            ))}
          </div>

          {/* Chart area */}
          <div className="rounded-lg border border-border bg-background p-4">
            <div className="mb-3 flex items-center justify-between">
              <div className="h-2.5 w-24 rounded bg-foreground/70" />
              <div className="flex gap-1.5">
                <span className="h-2 w-2 rounded-full bg-primary" />
                <span className="h-2 w-2 rounded-full bg-muted-foreground/40" />
              </div>
            </div>
            <svg viewBox="0 0 320 100" className="h-24 w-full" aria-hidden>
              <defs>
                <linearGradient id="mockupArea" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="hsl(221.2 83.2% 53.3%)" stopOpacity="0.35" />
                  <stop offset="100%" stopColor="hsl(221.2 83.2% 53.3%)" stopOpacity="0" />
                </linearGradient>
              </defs>
              <path
                d="M0,75 L40,60 L80,70 L120,45 L160,55 L200,30 L240,35 L280,18 L320,25 L320,100 L0,100 Z"
                fill="url(#mockupArea)"
              />
              <path
                d="M0,75 L40,60 L80,70 L120,45 L160,55 L200,30 L240,35 L280,18 L320,25"
                fill="none"
                stroke="hsl(221.2 83.2% 53.3%)"
                strokeWidth="2"
              />
            </svg>
          </div>

          {/* List rows */}
          <div className="space-y-2">
            {[1, 2, 3].map((i) => (
              <div
                key={i}
                className="flex items-center justify-between rounded-md border border-border bg-background px-3 py-2"
              >
                <div className="flex items-center gap-3">
                  <div className="h-7 w-7 rounded bg-primary/15" />
                  <div>
                    <div className="h-2 w-24 rounded bg-foreground/70" />
                    <div className="mt-1.5 h-1.5 w-16 rounded bg-muted-foreground/30" />
                  </div>
                </div>
                <div className="h-2 w-14 rounded bg-foreground/70" />
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Floating mini-card pra dar profundidade */}
      <div className="absolute -bottom-6 -left-6 hidden rounded-xl border border-border bg-card px-4 py-3 shadow-xl sm:block">
        <div className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
          Boletos hoje
        </div>
        <div className="mt-1 flex items-baseline gap-2">
          <span className="text-lg font-bold text-foreground">R$ 9.840</span>
          <span className="rounded-full bg-green-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-green-600">
            +12%
          </span>
        </div>
      </div>
    </div>
  );
}
