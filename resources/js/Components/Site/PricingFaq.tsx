const FAQ = [
  {
    q: 'Posso testar antes de pagar?',
    a: 'Pode. O plano Profissional tem 14 dias grátis sem cartão de crédito. O Essencial tem versão demo.',
  },
  {
    q: 'Tem fidelidade ou multa pra cancelar?',
    a: 'Não. Cancela quando quiser. Se for no plano anual, devolvemos proporcional ao tempo não usado.',
  },
  {
    q: 'Posso emitir NF-e em todos os planos?',
    a: 'Sim. NF-e, NFC-e e NFS-e estão disponíveis desde o Essencial. CT-e e MDF-e a partir do Profissional.',
  },
  {
    q: 'O que acontece com meus dados se eu cancelar?',
    a: 'Você consegue exportar tudo (vendas, estoque, clientes, fiscal) em CSV/XML antes de cancelar. Mantemos backup por 90 dias após cancelamento, depois deletamos.',
  },
  {
    q: 'Vocês ajudam na migração de outro sistema?',
    a: 'Sim. No Profissional fazemos a importação dos cadastros (produtos, clientes, fornecedores). No Enterprise temos onboarding guiado completo.',
  },
  {
    q: 'O suporte é em português? Que horários?',
    a: 'Sim, sempre em português. Chat e e-mail das 8h às 22h, todos os dias. No Enterprise tem WhatsApp dedicado e SLA garantido.',
  },
];

export default function PricingFaq() {
  return (
    <section className="py-16 sm:py-20">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div className="text-center">
          <span className="text-xs font-semibold uppercase tracking-wider text-primary">
            Antes de você perguntar
          </span>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Dúvidas frequentes
          </h2>
        </div>

        <dl className="mt-12 space-y-3">
          {FAQ.map((item) => (
            <details
              key={item.q}
              className="group rounded-xl border border-border bg-card p-5 transition-colors open:border-primary/40 open:bg-primary/[0.02]"
            >
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-left">
                <dt className="text-sm font-semibold text-foreground sm:text-base">{item.q}</dt>
                <span
                  aria-hidden
                  className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-border bg-background transition-transform group-open:rotate-45"
                >
                  <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" fill="currentColor">
                    <path d="M11 5h2v14h-2z M5 11h14v2H5z" />
                  </svg>
                </span>
              </summary>
              <dd className="mt-3 text-sm leading-relaxed text-muted-foreground sm:text-base">
                {item.a}
              </dd>
            </details>
          ))}
        </dl>
      </div>
    </section>
  );
}
