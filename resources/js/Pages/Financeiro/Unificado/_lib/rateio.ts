// Rateio do título em centros de custo (US-FIN-075) — a MESMA conta do
// Modules/Financeiro/Services/RateioService.php, para a soma mostrada no drawer
// bater com a gravada. Tudo em inteiros: centavos e décimos-milésimos de ponto
// percentual. Piso + maior resto no modo %; no modo valor a soma tem de fechar.

export type RateioModo = 'percentual' | 'valor';

export interface RateioLinha {
  centro_custo_id: number;
  percentual: string;
  valor: string;
}

export interface RateioResultado {
  ok: boolean;
  /** centavos por linha (NaN = entrada inválida) */
  valores: number[];
  /** décimos-milésimos de % por linha */
  pcts: number[];
  /** quanto falta para fechar (positivo) ou passou (negativo), na unidade do modo */
  falta: number;
}

const CEM = 1_000_000;

/** "1.234,56" / "1234.56" → inteiro na escala pedida, metade para cima. NaN se inválido. */
export function paraInteiro(valor: string | number, casas: number): number {
  let s = String(valor ?? '').trim();
  if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
  const m = /^(-?)(\d*)(?:\.(\d*))?$/.exec(s);
  if (!m || (m[2] === '' && (m[3] ?? '') === '')) return NaN;
  const frac = ((m[3] ?? '') + '0'.repeat(casas + 1)).slice(0, casas + 1);
  let n = parseInt((m[2] || '0') + frac.slice(0, casas), 10);
  if (Number(frac[casas]) >= 5) n += 1;
  return m[1] === '-' ? -n : n;
}

export function deInteiro(n: number, casas: number): string {
  const neg = n < 0;
  const s = String(Math.abs(n)).padStart(casas + 1, '0');
  return `${neg ? '-' : ''}${s.slice(0, -casas)}.${s.slice(-casas)}`;
}

export function distribuir(totalCentavos: number, modo: RateioModo, linhas: RateioLinha[]): RateioResultado {
  if (modo === 'percentual') {
    const pcts = linhas.map((l) => paraInteiro(l.percentual, 4));
    const soma = pcts.reduce((a, b) => a + (Number.isNaN(b) ? 0 : b), 0);
    const valores = pcts.map((p) => (Number.isNaN(p) ? NaN : Math.floor((totalCentavos * p) / CEM)));
    if (soma === CEM && pcts.every((p) => p > 0)) {
      const restos = pcts.map((p, i) => totalCentavos * p - valores[i] * CEM);
      let sobra = totalCentavos - valores.reduce((a, b) => a + b, 0);
      const ordem = restos.map((_, i) => i).sort((a, b) => restos[b] - restos[a] || a - b);
      for (const i of ordem) {
        if (sobra <= 0) break;
        valores[i] += 1;
        sobra -= 1;
      }
    }
    const ok = soma === CEM && pcts.every((p) => p > 0) && valores.every((v) => v > 0);
    return { ok, valores, pcts, falta: CEM - soma };
  }

  const valores = linhas.map((l) => paraInteiro(l.valor, 2));
  const soma = valores.reduce((a, b) => a + (Number.isNaN(b) ? 0 : b), 0);
  const pcts = valores.map((v) => (v > 0 && totalCentavos > 0 ? Math.round((v * CEM) / totalCentavos) : 0));
  return { ok: soma === totalCentavos && valores.every((v) => v > 0), valores, pcts, falta: totalCentavos - soma };
}

/** Divide igual entre as linhas; a última fecha o total. */
export function dividirIgual(totalCentavos: number, modo: RateioModo, linhas: RateioLinha[]): RateioLinha[] {
  const n = linhas.length;
  if (n === 0) return linhas;
  if (modo === 'percentual') {
    const base = Math.floor(CEM / n);
    return linhas.map((l, i) => ({ ...l, percentual: deInteiro(i === n - 1 ? CEM - base * (n - 1) : base, 4) }));
  }
  const base = Math.floor(totalCentavos / n);
  return linhas.map((l, i) => ({ ...l, valor: deInteiro(i === n - 1 ? totalCentavos - base * (n - 1) : base, 2) }));
}
