// Consulta de vendas — período com que a lista abre.
//
// Cobre UC-SIDX-09 (resources/js/Pages/Sells/Index.casos.md). Decisão [W] 2026-11-25, item 08 da
// conferência lista × protótipo: na primeira vez a lista abre em "Hoje"; depois vale a última
// escolha salva. `presetInicial` é o que Index.tsx usa para o período e para as datas iniciais.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/sells-index-periodo-inicial.test.ts

import { describe, it, expect } from 'vitest';
import { computePresetRange, presetInicial } from '@/Pages/Sells/_components/SellsDateFilter';

describe('UC-SIDX-09 · Período inicial da lista de vendas', () => {
  it('UC-SIDX-09 primeira visita (nada salvo) abre em Hoje', () => {
    expect(presetInicial('')).toBe('day');
  });

  it('UC-SIDX-09 Hoje cobre o dia inteiro de hoje', () => {
    const agora = new Date(2026, 10, 26, 15, 30);
    expect(computePresetRange('day', agora)).toEqual({ dateFrom: '2026-11-26', dateTo: '2026-11-26' });
  });

  it('UC-SIDX-09 respeita a última escolha salva', () => {
    expect(presetInicial('month')).toBe('month');
    expect(presetInicial('week')).toBe('week');
    expect(presetInicial('custom')).toBe('custom');
  });

  it('UC-SIDX-09 quem limpou o filtro (todas) continua em todas', () => {
    expect(presetInicial('all')).toBe('all');
  });

  it('UC-SIDX-09 valor salvo desconhecido conta como primeira visita', () => {
    expect(presetInicial('ontem')).toBe('day');
  });
});
