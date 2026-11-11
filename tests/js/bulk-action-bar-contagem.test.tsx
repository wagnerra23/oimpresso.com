import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import BulkActionBar from '@/Components/shared/BulkActionBar';

// A barra de selecionados mostra quantos estão marcados, também quando a tela passa o próprio texto.
// Antes o label substituía a frase inteira e a consulta de produto mostrava "produto(s) selecionado(s)"
// sem o número (visto em produção, 2026-11-11).
describe('BulkActionBar — contagem', () => {
  it('com label, o número vem antes do texto da tela', () => {
    render(
      <BulkActionBar selectedCount={2} onClear={() => {}} label="produto(s) selecionado(s)">
        <button type="button">Informar NCM</button>
      </BulkActionBar>,
    );
    expect(screen.getByText('2 produto(s) selecionado(s)')).toBeTruthy();
  });

  it('sem label, segue a frase padrão no singular e no plural', () => {
    const { rerender } = render(<BulkActionBar selectedCount={1} onClear={() => {}}>x</BulkActionBar>);
    expect(screen.getByText('1 item selecionado')).toBeTruthy();
    rerender(<BulkActionBar selectedCount={3} onClear={() => {}}>x</BulkActionBar>);
    expect(screen.getByText('3 itens selecionados')).toBeTruthy();
  });

  it('sem nada selecionado, a barra não aparece', () => {
    const { container } = render(<BulkActionBar selectedCount={0} onClear={() => {}} label="tasks">x</BulkActionBar>);
    expect(container.innerHTML).toBe('');
  });
});
