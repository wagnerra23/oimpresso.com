// Consulta de produto (Produto/Index): "Informar NCM" nos selecionados (UC-PIDX-43). A gravação, a regra no
// servidor e o isolamento por empresa são provados em tests/Feature/Produto/ProdutoNcmEmLoteTest.php; aqui,
// o diálogo: o confirmar só liga com NCM válido e manda só os dígitos.
//
// Cobre UC-PIDX-43 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import NcmEmLote, { ncmDigitado } from '@/Pages/Produto/_components/lista/NcmEmLote';

function montar() {
  const aoConfirmar = vi.fn();
  const aoFechar = vi.fn();
  render(<NcmEmLote aberto quantidade={3} aoFechar={aoFechar} aoConfirmar={aoConfirmar} />);
  return { aoConfirmar, aoFechar };
}

const campo = () => screen.getByLabelText('NCM');
const confirmar = () => screen.getByRole('button', { name: /informar em 3/i });

afterEach(() => cleanup());

describe('UC-PIDX-43 · Informar NCM nos selecionados', () => {
  it('o confirmar só liga com 8 dígitos e manda só os dígitos', () => {
    const { aoConfirmar } = montar();
    expect(confirmar().hasAttribute('disabled')).toBe(true);
    fireEvent.change(campo(), { target: { value: '3921.90.90' } });
    expect(confirmar().hasAttribute('disabled')).toBe(false);
    confirmar().click();
    expect(aoConfirmar).toHaveBeenCalledWith('39219090');
  });

  it('NCM curto ou 0000.00.00 deixa o confirmar desligado e mostra o aviso', () => {
    const { aoConfirmar } = montar();
    fireEvent.change(campo(), { target: { value: '3921.90' } });
    expect(confirmar().hasAttribute('disabled')).toBe(true);
    expect(screen.getByText('O NCM tem 8 dígitos e não pode ser 0000.00.00.')).toBeTruthy();
    fireEvent.change(campo(), { target: { value: '0000.00.00' } });
    expect(confirmar().hasAttribute('disabled')).toBe(true);
    confirmar().click();
    expect(aoConfirmar).not.toHaveBeenCalled();
  });

  it('diz quantos produtos recebem o NCM', () => {
    montar();
    expect(screen.getByText(/3 produto\(s\) selecionado\(s\)/)).toBeTruthy();
  });

  it('a regra do NCM é a da emissão: 8 dígitos, não 00000000', () => {
    expect(ncmDigitado('39219090')).toBe('39219090');
    expect(ncmDigitado('3921.90.90')).toBe('39219090');
    expect(ncmDigitado('3921909')).toBeNull();
    expect(ncmDigitado('392190901')).toBeNull();
    expect(ncmDigitado('00000000')).toBeNull();
    expect(ncmDigitado('')).toBeNull();
  });
});
