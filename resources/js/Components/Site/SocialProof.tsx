/**
 * Banda de prova social. Logos placeholder até Wagner enviar a lista real.
 * Stats qualitativos pra evitar comprometer com o número exato (56 businesses).
 */
const PLACEHOLDER_LOGOS = ['Cliente A', 'Cliente B', 'Cliente C', 'Cliente D', 'Cliente E'];

const STATS = [
  { value: 'Dezenas', label: 'de negócios brasileiros' },
  { value: '+10 anos', label: 'no mercado' },
  { value: '24/7', label: 'sempre disponível' },
];

export default function SocialProof() {
  return (
    <section className="border-b border-border bg-muted/20 py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p className="text-center text-xs font-medium uppercase tracking-wider text-muted-foreground">
          Empresas brasileiras que escolheram o oimpresso
        </p>
        <div className="mt-6 grid grid-cols-2 items-center gap-8 sm:grid-cols-3 lg:grid-cols-5">
          {PLACEHOLDER_LOGOS.map((logo) => (
            <div
              key={logo}
              className="flex h-10 items-center justify-center rounded-md border border-dashed border-border/80 text-xs font-medium text-muted-foreground/60"
              aria-label={`logo ${logo} (placeholder)`}
            >
              {logo}
            </div>
          ))}
        </div>

        <div className="mt-12 grid grid-cols-1 gap-6 border-t border-border pt-10 sm:grid-cols-3">
          {STATS.map((stat) => (
            <div key={stat.label} className="text-center">
              <div className="text-3xl font-bold text-foreground">{stat.value}</div>
              <div className="mt-1 text-sm text-muted-foreground">{stat.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
