// Lançamento V3: a sigla real do cadastro (`M2`) tem de abrir as medidas e compor a área.
// Antes, só `m²` literal era dimensional — produto em `M2` faturava 1 fixo, sem altura × largura.
import { describe, expect, it } from 'vitest';
import { areaUnitaria, quantidadeFaturada, unidadeDeMedida } from '../../resources/js/Pages/Sells/_components/v3/calculo-item';

describe('unidadeDeMedida', () => {
  it.each([
    ['M2', 'm²'], ['m2', 'm²'], ['M²', 'm²'], ['m²', 'm²'], [' M2 ', 'm²'],
    ['M3', 'm³'], ['m³', 'm³'], ['M', 'm'], ['m', 'm'],
  ])('%s → %s', (de, para) => expect(unidadeDeMedida(de)).toBe(para));

  it('sigla não dimensional volta como veio', () => {
    expect(unidadeDeMedida('UN')).toBe('UN');
    expect(unidadeDeMedida('milheiro')).toBe('milheiro');
    expect(unidadeDeMedida('h')).toBe('h');
  });
});

describe('areaUnitaria com a sigla do cadastro', () => {
  it('M2: 2 peças de 1,20 × 0,80 = 1,92 (mesmo resultado de m²)', () => {
    const area = areaUnitaria('M2', 1.2, 0.8, 0);
    expect(area).toBeCloseTo(0.96, 10);
    expect(quantidadeFaturada(true, 2, area, 0)).toBe(1.92);
    expect(areaUnitaria('m²', 1.2, 0.8, 0)).toBeCloseTo(area, 10);
  });
  it('M3 usa a espessura; UN não compõe área', () => {
    expect(areaUnitaria('M3', 1, 0.5, 0.02)).toBeCloseTo(0.01, 10);
    expect(areaUnitaria('UN', 1.2, 0.8, 0)).toBe(1);
  });
});
