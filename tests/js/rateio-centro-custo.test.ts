// US-FIN-075 — a conta do drawer (Unificado/_lib/rateio.ts) tem de dar os MESMOS
// números do RateioService (Modules/Financeiro/Tests/Feature/RateioCentroCustoContratoTest.php).
// Segundo caminho da regra mestre de valor: frontend × backend com os mesmos casos.
import { describe, expect, it } from 'vitest';
import { distribuir, dividirIgual, paraInteiro } from '@/Pages/Financeiro/Unificado/_lib/rateio';

const linha = (id: number, percentual = '', valor = '') => ({ centro_custo_id: id, percentual, valor });

describe('rateio do título (US-FIN-075)', () => {
  it('modo % reparte 1.000,01 com piso + maior resto, igual ao backend', () => {
    const r = distribuir(100001, 'percentual', [linha(1, '33.3333'), linha(2, '33,3333'), linha(3, '33.3334')]);
    expect(r.ok).toBe(true);
    expect(r.valores).toEqual([33334, 33333, 33334]);
    expect(r.valores.reduce((a, b) => a + b, 0)).toBe(100001);
  });

  it('modo valor deriva o % e exige fechar o total', () => {
    const r = distribuir(30000, 'valor', [linha(1, '', '100,00'), linha(2, '', '100'), linha(3, '', '100.00')]);
    expect(r.ok).toBe(true);
    expect(r.pcts).toEqual([333333, 333333, 333333]);
    expect(distribuir(10000, 'valor', [linha(1, '', '50'), linha(2, '', '49,99')]).falta).toBe(1);
  });

  it('recusa soma diferente de 100% e mostra quanto falta', () => {
    const r = distribuir(10000, 'percentual', [linha(1, '60'), linha(2, '39.99')]);
    expect(r.ok).toBe(false);
    expect(r.falta).toBe(100);
  });

  it('dividir igual fecha 100% e o total', () => {
    const pct = dividirIgual(100001, 'percentual', [linha(1), linha(2), linha(3)]);
    expect(pct.map((l) => paraInteiro(l.percentual, 4)).reduce((a, b) => a + b, 0)).toBe(1_000_000);
    const val = dividirIgual(100001, 'valor', [linha(1), linha(2), linha(3)]);
    expect(val.map((l) => l.valor)).toEqual(['333.33', '333.33', '333.35']);
  });

  it('lê número em pt-BR e em ponto, arredondando metade para cima', () => {
    expect(paraInteiro('1.234,565', 2)).toBe(123457);
    expect(paraInteiro('1000.0100', 2)).toBe(100001);
    expect(Number.isNaN(paraInteiro('abc', 2))).toBe(true);
  });
});
