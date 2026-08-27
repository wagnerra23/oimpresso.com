/**
 * Resumo das parcelas no fechamento — a PONTE entre gerar e ver.
 *
 * @covers-us UC-V369 UC-V370
 *
 * Por que RENDER e não domínio: `parcelas-dominio.test.ts` prova em 15 casos que
 * `ratear` divide o total sem perder centavo — e passava inteiro enquanto a tela
 * principal não mostrava parcela nenhuma. O operador gerava 2x, o drawer fechava,
 * e o único vestígio era o rótulo do botão virar `Parcelas (2)…`. Domínio certo,
 * tela muda: exatamente o buraco que o `colunas-grid.test.tsx` documenta no grid.
 *
 * O componente é isolado (não é Page), então nada de mock de Inertia/shell aqui —
 * renderiza direto.
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import ResumoParcelas, { PARCELAS_VISIVEIS } from '@/Pages/Sells/_components/v3/ResumoParcelas';
import { RECEBIDA, type Parcela } from '@/Pages/Sells/_components/v3/parcelas-dominio';

/** Uma parcela plausível — só o que o resumo lê é que importa. */
function parcela(over: Partial<Parcela> & { k: number; num: number; de: number }): Parcela {
  return {
    valor: '557,70',
    venc: '2026-08-27',
    pgto: null,
    tipo: 'PIX',
    lanc: 'A RECEBER',
    plano: '1.1.1 — Caixa',
    conta: '1 — Caixa financeiro',
    doc: '',
    resp: '',
    hist: '',
    ...over,
  };
}

const duas: Parcela[] = [
  parcela({ k: 1, num: 1, de: 2, venc: '2026-08-27' }),
  parcela({ k: 2, num: 2, de: 2, venc: '2026-09-26' }),
];

describe('UC-V369 · gerar parcelas aparece no fechamento', () => {
  it('sem parcela nenhuma o bloco não existe — não é caixa vazia', () => {
    const { container } = render(<ResumoParcelas parcelas={[]} onEditar={() => {}} />);
    expect(container.firstChild).toBeNull();
  });

  it('mostra quantidade, tipo e cada parcela com número, vencimento e valor', () => {
    render(<ResumoParcelas parcelas={duas} onEditar={() => {}} />);

    expect(screen.getByText('2x')).toBeTruthy();
    expect(screen.getByText('PIX')).toBeTruthy();

    const linhas = screen.getAllByRole('listitem');
    expect(linhas).toHaveLength(2);

    // A âncora mostra `num/de · venc · valor` — as três coisas que respondem
    // "quantas, pra quando e de quanto" sem reabrir o drawer.
    expect(within(linhas[0]!).getByText('1/2')).toBeTruthy();
    expect(within(linhas[0]!).getByText('27/08/2026')).toBeTruthy();
    expect(within(linhas[0]!).getByText('557,70')).toBeTruthy();

    expect(within(linhas[1]!).getByText('2/2')).toBeTruthy();
    expect(within(linhas[1]!).getByText('26/09/2026')).toBeTruthy();
  });

  it('"Editar parcelas" é botão real e devolve o controle ao drawer', async () => {
    const onEditar = vi.fn();
    render(<ResumoParcelas parcelas={duas} onEditar={onEditar} />);

    const botao = screen.getByRole('button', { name: 'Editar parcelas' });
    botao.click();

    expect(onEditar).toHaveBeenCalledTimes(1);
  });
});

describe('UC-V370 · baixa e excedente são declarados, não escondidos', () => {
  it('parcela com baixa vem marcada; a que ainda não entrou, não', () => {
    const comBaixa: Parcela[] = [
      parcela({ k: 1, num: 1, de: 2, lanc: RECEBIDA }),
      parcela({ k: 2, num: 2, de: 2 }),
    ];
    render(<ResumoParcelas parcelas={comBaixa} onEditar={() => {}} />);

    const linhas = screen.getAllByRole('listitem');
    expect(within(linhas[0]!).getByLabelText('recebida')).toBeTruthy();
    expect(within(linhas[1]!).queryByLabelText('recebida')).toBeNull();
  });

  it('acima do corte, o resto é CONTADO em vez de sumir', () => {
    const seis: Parcela[] = Array.from({ length: 6 }, (_, i) =>
      parcela({ k: i + 1, num: i + 1, de: 6 }),
    );
    render(<ResumoParcelas parcelas={seis} onEditar={() => {}} />);

    expect(screen.getAllByRole('listitem')).toHaveLength(PARCELAS_VISIVEIS);
    // O excedente precisa aparecer: 6 parcelas mostrando 4 sem dizer nada seria
    // a mesma cegueira que este componente existe pra fechar, só que menor.
    expect(screen.getByText(`+${6 - PARCELAS_VISIVEIS} parcelas`)).toBeTruthy();
  });

  it('excedente de UMA fala no singular', () => {
    const cinco: Parcela[] = Array.from({ length: 5 }, (_, i) =>
      parcela({ k: i + 1, num: i + 1, de: 5 }),
    );
    render(<ResumoParcelas parcelas={cinco} onEditar={() => {}} />);

    expect(screen.getByText('+1 parcela')).toBeTruthy();
  });
});
