// Consulta de vendas — totais do filtro abaixo da lista (US-SELL-017).
//
// Cobre UC-SIDX-06 (resources/js/Pages/Sells/Index.casos.md): a barra mostra os totais que o
// servidor calculou para o filtro inteiro, e não mostra número quando um filtro que roda só no
// navegador deixaria o total diferente das linhas na tela.
//
// Origem: pesquisa de concorrentes 2026-10-20 — o servidor já devolvia `totals` desde a US-SELL-017
// e a tela nunca os exibia.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml). Cada lane Vitest lista os
// próprios specs: a lista viva é a de lá.
// Comando: npx vitest run tests/js/sells-index-totais.test.tsx

import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import SellsTotalBar from '@/Pages/Sells/_components/SellsTotalBar';
import {
  motivoSemTotais,
  type FiltrosDaTela,
  type TotaisDoFiltro,
} from '@/Pages/Sells/_components/totaisDoFiltro';

afterEach(cleanup);

// Números de propósito "quebrados": se a barra somasse por conta própria ou arredondasse, o texto
// não bateria com o que o servidor mandou.
const DO_SERVIDOR: TotaisDoFiltro = {
  count: 37,
  sum_final_total: 18432.57,
  sum_total_paid: 11020.1,
  sum_due: 7412.47,
};

const SEM_FILTRO_LOCAL: FiltrosDaTela = {
  pill: 'todas',
  savedViewId: 'todas',
  savedViewLabel: 'Todas',
  visaoOrigem: '',
};

const brl = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

describe('UC-SIDX-06 · totais do filtro na consulta de vendas', () => {
  it('UC-SIDX-06 mostra quantidade, pago, a receber e total exatamente como o servidor mandou', () => {
    const { container } = render(
      <SellsTotalBar totais={DO_SERVIDOR} filtros={SEM_FILTRO_LOCAL} carregando={false} />,
    );
    const barra = container.querySelector('[aria-label="Totais do filtro"]');
    expect(barra).not.toBeNull();
    const texto = barra!.textContent ?? '';
    expect(texto).toContain('37 vendas');
    expect(texto).toContain(`Pago${brl(11020.1)}`);
    expect(texto).toContain(`A receber${brl(7412.47)}`);
    expect(texto).toContain(`Total do filtro${brl(18432.57)}`);
    // Controle: o valor formatado de fato tem centavos — a asserção acima não passaria por acaso.
    expect(brl(18432.57)).toMatch(/18\.432,57/);
  });

  it('UC-SIDX-06 vale também com as pílulas Paga e Pendente, que o servidor filtra', () => {
    for (const pill of ['paga', 'pendente']) {
      cleanup();
      const { container } = render(
        <SellsTotalBar totais={DO_SERVIDOR} filtros={{ ...SEM_FILTRO_LOCAL, pill }} carregando={false} />,
      );
      expect(container.querySelector('[aria-label="Totais do filtro"]')).not.toBeNull();
    }
  });

  it('UC-SIDX-06 com visão salva, pílula Faturada/Cancelada ou origem, não mostra número e diz por quê', () => {
    const casos: FiltrosDaTela[] = [
      { ...SEM_FILTRO_LOCAL, savedViewId: 'atrasadas', savedViewLabel: 'Atrasadas' },
      { ...SEM_FILTRO_LOCAL, pill: 'faturada' },
      { ...SEM_FILTRO_LOCAL, pill: 'cancelada' },
      { ...SEM_FILTRO_LOCAL, visaoOrigem: 'oficina' },
    ];
    for (const filtros of casos) {
      cleanup();
      const { container } = render(
        <SellsTotalBar totais={DO_SERVIDOR} filtros={filtros} carregando={false} />,
      );
      expect(container.querySelector('[aria-label="Totais do filtro"]')).toBeNull();
      const nota = container.querySelector('[role="note"]');
      expect(nota).not.toBeNull();
      expect(nota!.textContent).toBe(motivoSemTotais(filtros));
      expect(container.textContent ?? '').not.toContain('R$');
    }
  });

  it('UC-SIDX-06 em Rascunho, carregando ou sem vendas, a barra não aparece', () => {
    const vazios = [
      render(<SellsTotalBar totais={DO_SERVIDOR} filtros={{ ...SEM_FILTRO_LOCAL, pill: 'rascunho' }} carregando={false} />),
      render(<SellsTotalBar totais={DO_SERVIDOR} filtros={SEM_FILTRO_LOCAL} carregando />),
      render(<SellsTotalBar totais={{ ...DO_SERVIDOR, count: 0 }} filtros={SEM_FILTRO_LOCAL} carregando={false} />),
      render(<SellsTotalBar totais={null} filtros={SEM_FILTRO_LOCAL} carregando={false} />),
    ];
    for (const { container } of vazios) {
      expect(container.innerHTML).toBe('');
    }
  });

  it('UC-SIDX-06 sem filtro local não há motivo para esconder os totais', () => {
    expect(motivoSemTotais(SEM_FILTRO_LOCAL)).toBeNull();
    expect(motivoSemTotais({ ...SEM_FILTRO_LOCAL, pill: 'pendente' })).toBeNull();
  });
});
