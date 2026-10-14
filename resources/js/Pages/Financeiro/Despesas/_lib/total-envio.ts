// O total que a Nova despesa manda no POST (US-MWART-018 · UC-DESPES-06).
// 2 casas e vírgula decimal: o `num_uf` do servidor lê "." como milhar quando há 3+ dígitos depois
// dele (incidente 2026-06-05), então a tela nunca manda float cru.
export function totalParaEnvio(v: number): string {
  return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',');
}
