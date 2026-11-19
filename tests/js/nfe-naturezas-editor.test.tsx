/**
 * Editor de natureza em 7 passos — forma do protótipo (RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-71 UC-NFTR-72
 *
 * Casos derivados do protótipo aprovado (`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`,
 * `renderEditor()`, `stepsHtml()` e `renderAside()`), não do componente.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import NaturezaEditor from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/NaturezaEditor';
import { vazia, type EmpresaResumo, type Natureza } from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/natureza';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const EMPRESA: EmpresaResumo = { nome: 'Empresa Teste', cnpj: null, municipio: 'Tubarão', uf: 'SC', ano: 2026, ibs: 0.001, cbs: 0.009 };

function existente(): Natureza {
  return { ...vazia(), id: 7, nome: 'Remessa em garantia', cfop: { in: '5949', out: '6949', ext: '' } };
}

function servidor(alertas: unknown[] = []) {
  const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
    void init;
    const corpo = url.endsWith('/conferir') ? { alertas } : { id: 99, alertas: [] };
    return { ok: true, status: 200, json: async () => corpo } as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

afterEach(() => vi.unstubAllGlobals());

const passos = () => within(screen.getByRole('list', { name: 'Passos' })).getAllByRole('button');

describe('Editor de natureza', () => {
  it('UC-NFTR-71 · sete passos com o estado de cada um, navegação e revisão com a prévia', async () => {
    servidor([{ nivel: 'erro', secao: 'ibs', mensagem: 'Informe o cClassTrib do IBS/CBS com 6 dígitos.' }]);
    render(<NaturezaEditor inicial={existente()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);

    // Os 7 passos do protótipo, na ordem.
    expect(passos().map((b) => b.querySelector('b')?.textContent))
      .toEqual(['Natureza', 'CFOP', 'ICMS', 'IPI', 'PIS e COFINS', 'IBS e CBS', 'Revisão']);
    expect(screen.getByText(/Passo 1 de 7 · Natureza · sem alterações · Lucro Presumido/)).toBeTruthy();

    // O passo com erro na conferência do servidor fica marcado com a pendência.
    await waitFor(() => expect(within(passos()[5]).getByText('1 pendência')).toBeTruthy());

    // Próximo leva ao passo seguinte.
    fireEvent.click(screen.getByRole('button', { name: /Próximo: CFOP/ }));
    expect(passos()[1].getAttribute('aria-current')).toBe('step');
    expect(screen.getByLabelText('Dentro do estado')).toBeTruthy();

    // Revisão: prévia do lançamento e a conferência com o atalho para o passo do erro.
    fireEvent.click(passos()[6]);
    expect(screen.getAllByText('Alíquota interna de SC').length).toBeGreaterThan(0);
    fireEvent.click(screen.getByRole('button', { name: 'Passo 6 · IBS e CBS' }));
    expect(passos()[5].getAttribute('aria-current')).toBe('step');

    // A lateral "No lançamento" e o salvar travado pelo erro.
    expect(screen.getByRole('heading', { name: 'No lançamento' })).toBeTruthy();
    expect((screen.getByRole('button', { name: '1 erro para salvar' }) as HTMLButtonElement).disabled).toBe(true);
    expect(screen.getByText('1 pendência nos passos marcados em vermelho.')).toBeTruthy();
  });

  it('UC-NFTR-72 · Duplicar abre uma cópia sem id, e salvar a cópia cria natureza nova', async () => {
    const fetchMock = servidor();
    render(<NaturezaEditor inicial={existente()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);

    fireEvent.click(screen.getByRole('button', { name: /Duplicar/ }));
    expect(screen.getByRole('heading', { name: 'Remessa em garantia (cópia)' })).toBeTruthy();
    expect(screen.getByText(/natureza nova, ainda não salva/)).toBeTruthy();

    const criar = await screen.findByRole('button', { name: 'Criar natureza' });
    await waitFor(() => expect((criar as HTMLButtonElement).disabled).toBe(false));
    fireEvent.click(criar);

    await waitFor(() => {
      const gravou = fetchMock.mock.calls.find(([u, i]) => !String(u).endsWith('/conferir') && (i as RequestInit)?.method === 'POST');
      expect(gravou?.[0]).toBe('/nfe-brasil/tributacao/naturezas');
      expect(JSON.parse(String((gravou?.[1] as RequestInit).body)).id).toBeUndefined();
    });
  });
});
