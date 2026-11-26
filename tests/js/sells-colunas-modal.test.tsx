/**
 * Janela do botão "Colunas" da lista de vendas: desmarcar e subir uma coluna chegam à escolha
 * gravada; as fixas não podem ser desmarcadas. Regra pura em sells-colunas-lista.test.ts.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import ColunasListaModal from '@/Pages/Sells/_components/ColunasListaModal';
import type { ColumnId } from '@/Pages/Sells/_components/SellsTabelaUnificada';

const ATIVAS: ColumnId[] = ['check', 'invoice', 'date', 'client', 'total'];

function abrir(onAtivasChange = vi.fn(), onRestaurar = vi.fn()) {
  render(
    <ColunasListaModal
      aberto
      onFechar={() => undefined}
      visao="operacional"
      ativas={ATIVAS}
      comissao={false}
      onAtivasChange={onAtivasChange}
      onRestaurar={onRestaurar}
    />,
  );
  return { onAtivasChange, onRestaurar };
}

describe('Lista de vendas · janela Colunas', () => {
  it('desmarcar Data tira a coluna da lista', () => {
    const { onAtivasChange } = abrir();
    fireEvent.click(screen.getByRole('checkbox', { name: 'Mostrar Data' }));
    expect(onAtivasChange).toHaveBeenCalledWith(['check', 'invoice', 'client', 'total']);
  });

  it('subir Total troca de lugar com Cliente', () => {
    const { onAtivasChange } = abrir();
    fireEvent.click(screen.getByRole('button', { name: 'Subir Total' }));
    expect(onAtivasChange).toHaveBeenCalledWith(['check', 'invoice', 'date', 'total', 'client']);
  });

  it('Venda é fixa: caixinha desabilitada e sem setas', () => {
    abrir();
    expect(screen.getByRole('checkbox', { name: 'Mostrar Venda' }).hasAttribute('disabled')).toBe(true);
    expect(screen.queryByRole('button', { name: 'Subir Venda' })).toBeNull();
  });

  it('coluna fora da lista aparece para ligar; Comissão não, sem comissão na empresa', () => {
    const { onAtivasChange } = abrir();
    fireEvent.click(screen.getByRole('checkbox', { name: 'Mostrar Status' }));
    expect(onAtivasChange).toHaveBeenCalledWith([...ATIVAS, 'status']);
    expect(screen.queryByRole('checkbox', { name: 'Mostrar Comissão' })).toBeNull();
  });

  it('Restaurar padrão avisa a lista', () => {
    const { onRestaurar } = abrir();
    fireEvent.click(screen.getByRole('button', { name: 'Restaurar padrão' }));
    expect(onRestaurar).toHaveBeenCalled();
  });
});
