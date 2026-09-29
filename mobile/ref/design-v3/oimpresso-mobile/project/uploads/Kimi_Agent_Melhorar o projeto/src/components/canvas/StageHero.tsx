export function StageHero() {
  return (
    <header className="text-center pt-14 px-6">
      <h1 className="text-[28px] font-semibold tracking-[-0.02em] text-[#f5f5f5]">
        Oimpresso ERP — Mobile
      </h1>
      <p className="mt-2 mx-auto max-w-[540px] text-sm leading-relaxed" style={{ color: 'oklch(0.72 0.01 240)' }}>
        Cliente nativo iOS + Android sobre o mesmo backend tRPC do web. Os dois aparatos compartilham o mesmo estado — alternar empresa ou tema atualiza ambos. Toque nos tabs e itens para navegar.
      </p>
      <span className="inline-flex items-center gap-2 mt-3.5 px-3 py-1.5 rounded-full text-[11px] font-mono border"
        style={{ background: '#2c2c2e', borderColor: '#38383A', color: '#8E8E93' }}>
        <span className="w-1.5 h-1.5 rounded-full bg-[#30D158]" style={{ animation: 'pulse-dot 2s ease infinite' }} />
        Protótipo interativo · ambos lados sincronizados
      </span>
    </header>
  );
}
