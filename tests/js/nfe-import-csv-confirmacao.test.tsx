/**
 * Importar regras por CSV — o resumo aparece e aplicar pede confirmação.
 *
 * @covers-us UC-NFIM-12 UC-NFIM-13
 *
 * Por que RENDER: o Pest prova que a página RECEBE o resumo (UC-NFIM-12) e o que o servidor grava ao
 * aplicar (UC-NFIM-01). Só o render prova que a tela MOSTRA o resumo vindo da prop `preview` e que o
 * clique em "Aplicar" não chega ao servidor sem o "OK" de quem está na tela.
 *
 * Contrato: ImportCsv.charter.md §Goals ("Aplicação idempotente sob `confirm()`") e §Anti-hooks
 * ("Não aplica o import sem `confirm()` explícito do usuário").
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => children,
  usePage: () => ({ url: '/nfe-brasil/tributacao/import', props: { flash: {} } }),
  useForm: () => ({ data: { arquivo: null }, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }),
  router: { post: (...a: unknown[]) => post(...a), get: vi.fn() },
}));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ImportCsv from '@/Pages/NfeBrasil/Tributacao/ImportCsv';

const colunas = ['ncm', 'uf_origem', 'uf_destino', 'cfop', 'csosn', 'cst', 'aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi'];
const preview = {
  total_validas: 2,
  total_erros: 1,
  amostras: [
    { ncm: '84439100', uf_origem: 'SP', uf_destino: null, cfop: '5102', csosn: '102', cst: null,
      aliquota_icms: 0.18, aliquota_pis: 0.0065, aliquota_cofins: 0.03, aliquota_ipi: 0 },
    { ncm: '84439100', uf_origem: 'SP', uf_destino: 'RJ', cfop: '6102', csosn: '102', cst: null,
      aliquota_icms: 0.12, aliquota_pis: 0.0065, aliquota_cofins: 0.03, aliquota_ipi: 0 },
  ],
  erros: [{ linha: 4, motivo: 'NCM deve ter 8 dígitos' }],
};

beforeEach(() => post.mockClear());
afterEach(() => vi.unstubAllGlobals());

describe('Importar regras por CSV', () => {
  it('UC-NFIM-12 · com a prop preview, a tela mostra o resumo e o botão de aplicar', () => {
    render(<ImportCsv colunas_obrigatorias={colunas} preview={preview} />);

    expect(screen.getByText('NCM deve ter 8 dígitos', { exact: false })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Aplicar 2 regra(s)' })).toBeTruthy();
  });

  it('UC-NFIM-12 · sem resumo, não há botão de aplicar', () => {
    render(<ImportCsv colunas_obrigatorias={colunas} preview={null} />);

    expect(screen.queryByRole('button', { name: /Aplicar/ })).toBeNull();
  });

  it('UC-NFIM-13 · cancelar a confirmação não envia nada ao servidor', () => {
    const confirmar = vi.fn(() => false);
    vi.stubGlobal('confirm', confirmar);

    render(<ImportCsv colunas_obrigatorias={colunas} preview={preview} />);
    fireEvent.click(screen.getByRole('button', { name: 'Aplicar 2 regra(s)' }));

    expect(confirmar).toHaveBeenCalledTimes(1);
    expect(String(confirmar.mock.calls[0][0])).toContain('Aplicar 2 regra(s)?');
    expect(post).not.toHaveBeenCalled();
  });

  it('UC-NFIM-13 · com o OK, o pedido vai para a rota de aplicar', () => {
    vi.stubGlobal('confirm', vi.fn(() => true));

    render(<ImportCsv colunas_obrigatorias={colunas} preview={preview} />);
    fireEvent.click(screen.getByRole('button', { name: 'Aplicar 2 regra(s)' }));

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/nfe-brasil/tributacao/import/aplicar');
  });
});
