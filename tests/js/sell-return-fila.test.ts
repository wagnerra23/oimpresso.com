// UC-SRCRE-09 — várias vendas do mesmo cliente numa devolução: a escolha (SellReturn/Create)
// monta a fila e o registro (SellReturn/Add) a lê para abrir a próxima venda depois de salvar.
import { describe, expect, it } from 'vitest';
import { lerFila, urlDoRegistro } from '@/Pages/SellReturn/_components/devolucaoFila';

describe('UC-SRCRE-09 fila de vendas da devolução', () => {
  it('uma venda abre o registro sem fila', () => {
    expect(urlDoRegistro([135])).toBe('/sell-return/add/135');
  });

  it('várias vendas abrem a primeira e levam as demais na fila, na ordem escolhida', () => {
    expect(urlDoRegistro([135, 136, 120])).toBe('/sell-return/add/135?fila=136,120');
  });

  it('o registro lê a fila e ignora o que não é id', () => {
    expect(lerFila('?fila=136,120')).toEqual([136, 120]);
    expect(lerFila('?fila=136,abc,-2,0,1.5,120')).toEqual([136, 120]);
    expect(lerFila('')).toEqual([]);
  });

  it('a fila percorre todas as vendas até esvaziar', () => {
    const visitadas: string[] = [];
    let url = urlDoRegistro([1, 2, 3]);
    for (;;) {
      visitadas.push(url);
      const fila = lerFila(url.includes('?') ? url.slice(url.indexOf('?')) : '');
      if (!fila.length) break;
      url = urlDoRegistro(fila);
    }
    expect(visitadas).toEqual(['/sell-return/add/1?fila=2,3', '/sell-return/add/2?fila=3', '/sell-return/add/3']);
  });
});
