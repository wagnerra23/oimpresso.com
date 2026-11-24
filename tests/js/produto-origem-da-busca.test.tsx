// "Você veio de… / Voltar e usar" da lista de produtos (UC-PIDX-53): o contrato de ida e volta
// (`origem-de-busca.ts`) e o aviso (`AvisoDeOrigem.tsx`, o `.pb-volta` do protótipo
// `cowork/Felipe/produto-blade.jsx`).
//
// Cobre UC-PIDX-53 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import AvisoDeOrigem from '@/Pages/Produto/_components/lista/AvisoDeOrigem';
import { caminhoInterno, lerOrigem, urlDeVolta } from '@/Pages/Produto/_components/lista/origem-de-busca';

afterEach(() => cleanup());

describe('UC-PIDX-53 · contrato de ida e volta', () => {
  it('só aceita caminho do próprio sistema como volta', () => {
    expect(caminhoInterno('/sells/create-v3')).toBe('/sells/create-v3');
    for (const ruim of ['//evil.test/x', '/' + String.fromCharCode(92) + 'evil.test', 'https://evil.test', 'javascript:alert(1)', '/' + String.fromCharCode(10) + '/evil.test', 'sells', '', null]) {
      expect(caminhoInterno(ruim as string | null)).toBeNull();
    }
  });

  it('lê a origem da URL, e sem ela a guardada na aba', () => {
    const daUrl = lerOrigem('?de=%2Fsells%2Fcreate-v3&de_rotulo=Nova%20venda&para=Banner%201x2', null);
    expect(daUrl).toEqual({ de: '/sells/create-v3', rotulo: 'Nova venda', para: 'Banner 1x2' });

    const guardada = JSON.stringify({ de: '/producao/os/9', rotulo: 'OS 9', para: null });
    expect(lerOrigem('?q=lona', guardada)).toEqual({ de: '/producao/os/9', rotulo: 'OS 9', para: null });

    // A URL vence a guardada; volta de fora do sistema é ignorada nas duas.
    expect(lerOrigem('?de=%2Fa', guardada)?.de).toBe('/a');
    expect(lerOrigem('?de=https%3A%2F%2Fevil.test', null)).toBeNull();
    expect(lerOrigem('', JSON.stringify({ de: '//evil.test', rotulo: 'x' }))).toBeNull();
    expect(lerOrigem('', 'não é json')).toBeNull();
    expect(lerOrigem('', null)).toBeNull();
  });

  it('a volta leva o produto escolhido, preservando a query e o fragmento de quem chamou', () => {
    expect(urlDeVolta('/sells/create-v3', 42)).toBe('/sells/create-v3?produto_escolhido=42');
    expect(urlDeVolta('/cv/orcamentos/7/edit?aba=itens#item-3', 42)).toBe('/cv/orcamentos/7/edit?aba=itens&produto_escolhido=42#item-3');
    expect(urlDeVolta('/x?produto_escolhido=1', 42)).toBe('/x?produto_escolhido=42');
  });
});

describe('UC-PIDX-53 · o aviso', () => {
  const ORIGEM = { de: '/sells/create-v3', rotulo: 'Nova venda', para: 'Banner 1x2' };

  it('diz de onde veio e, sem produto escolhido, desliga o "Voltar e usar" dizendo o que fazer', () => {
    render(<AvisoDeOrigem origem={ORIGEM} escolhido={null} aoVoltar={vi.fn()} aoDispensar={vi.fn()} />);
    const aviso = screen.getByRole('status');
    expect(aviso.textContent).toContain('Você veio de Nova venda procurando um produto para “Banner 1x2”.');
    expect(aviso.textContent).toContain('Marque um produto ou abra o painel dele para usar.');
    expect((screen.getByRole('button', { name: 'Voltar e usar' }) as HTMLButtonElement).disabled).toBe(true);
  });

  it('com o produto escolhido, volta com o id dele; e o X dispensa', () => {
    const aoVoltar = vi.fn();
    const aoDispensar = vi.fn();
    render(<AvisoDeOrigem origem={{ ...ORIGEM, para: null }} escolhido={{ id: 42, nome: 'Lona 440g' }} aoVoltar={aoVoltar} aoDispensar={aoDispensar} />);
    expect(screen.getByRole('status').textContent).toContain('Você veio de Nova venda.');
    fireEvent.click(screen.getByRole('button', { name: 'Voltar e usar “Lona 440g”' }));
    expect(aoVoltar).toHaveBeenCalledWith(42);
    fireEvent.click(screen.getByRole('button', { name: 'Dispensar' }));
    expect(aoDispensar).toHaveBeenCalledTimes(1);
  });
});
