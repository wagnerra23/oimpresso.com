// Valor do resgate de pontos de fidelidade (US-SELL-079) — a mesma conta de
// App\Services\Sells\ResgatePontosFidelidade, para a tela e o servidor baterem.

/** pontos × valor do ponto → "0.00", meio para cima, sem ponto flutuante. */
export function valorDoResgate(pontos: number, valorDoPonto: string): string {
  const [inteiro, fracao = ''] = valorDoPonto.trim().split('.');
  const decimilesimos = Number(inteiro) * 10000 + Number((fracao + '0000').slice(0, 4));
  const centavos = Math.floor((Math.trunc(pontos) * decimilesimos + 50) / 100);
  return `${Math.floor(centavos / 100)}.${String(centavos % 100).padStart(2, '0')}`;
}
