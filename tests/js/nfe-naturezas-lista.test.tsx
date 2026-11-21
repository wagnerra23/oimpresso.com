/**
 * Naturezas de operação na Tributação — onda 3b (RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-51 UC-NFTR-52 UC-NFTR-69
 *
 * Por que RENDER: o servidor já prova a conferência e o bloqueio de edição (UC-NFTR-44..49). O que
 * só o render prova é a TELA obedecer: a natureza que o cálculo usa abre só para leitura, e o botão
 * de salvar trava enquanto a conferência do servidor apontar erro.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import NaturezasSecao from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/NaturezasSecao';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
// O SubNav lê a URL ativa do Inertia; aqui não há app Inertia montado.
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ url: '/nfe-brasil/tributacao', props: {} }), Link: () => null }));

const linhas = [
  { id: 1, editavel: false, padrao: true, pendencias: null,
    natureza: { nome: 'Venda', tipo: 'S', fin: '1', ativo: true, ref: false, cfop: { in: '?102', out: '', ext: '' } } },
  { id: 2, editavel: true, padrao: false, pendencias: 1,
    natureza: { nome: 'Remessa em garantia', tipo: 'S', fin: '1', ativo: true, ref: true, cfop: { in: '5949', out: '6949', ext: '' },
      icms: { cst: '10', origem: 'uf' }, ipi: { on: true, cst: '50' }, pis: { cst: '01' }, ibs: { on: true, cst: '000' } } },
  { id: 3, editavel: true, padrao: false, pendencias: 0,
    natureza: { nome: 'Bonificação e brinde', tipo: 'S', fin: '1', ativo: false, ref: false, cfop: { in: '5910', out: '6910', ext: '' } } },
];

function servidor(alertas: unknown[] = []) {
  const fetchMock = vi.fn(async (url: string) => {
    const corpo = url.endsWith('/conferir') ? { alertas } : {
      regime: 'LP', naturezas: linhas,
      empresa: { nome: 'Empresa Teste', cnpj: null, municipio: 'Tubarão', uf: 'SC', ano: 2026, ibs: 0.001, cbs: 0.009 },
    };
    return { ok: true, status: 200, json: async () => corpo } as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

afterEach(() => vi.unstubAllGlobals());

// O nome da natureza também aparece no seletor da prévia; as asserções da LISTA olham só a tabela.
const tabela = async () => within(await screen.findByRole('table'));

describe('Naturezas de operação', () => {
  it('UC-NFTR-51 · a lista mostra a situação de cada natureza e a do cálculo abre só para leitura', async () => {
    servidor();
    render(<NaturezasSecao />);

    expect((await tabela()).getByText('Remessa em garantia')).toBeTruthy();
    expect(screen.getByText('1 pendência')).toBeTruthy();
    expect(screen.getByText('Usada no cálculo')).toBeTruthy();
    // Inativa fica fora de "Todas" e aparece no filtro próprio.
    expect(screen.queryByText('Bonificação e brinde')).toBeNull();
    fireEvent.click(screen.getByRole('tab', { name: /Inativas/ }));
    expect(screen.getByText('Bonificação e brinde')).toBeTruthy();

    fireEvent.click(screen.getByRole('tab', { name: /Todas/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Ver Venda' }));
    expect(screen.getByText('Só leitura')).toBeTruthy();
    expect((screen.getByLabelText('Nome') as HTMLInputElement).disabled).toBe(true);
    expect(screen.queryByRole('button', { name: /Salvar natureza|para salvar/ })).toBeNull();
  });

  it('UC-NFTR-52 · o salvar trava enquanto a conferência do servidor apontar erro', async () => {
    const fetchMock = servidor([{ nivel: 'erro', secao: 'ibs', mensagem: 'Informe o cClassTrib do IBS/CBS com 6 dígitos.' }]);
    render(<NaturezasSecao />);

    fireEvent.click(await screen.findByRole('button', { name: 'Editar Remessa em garantia' }));

    const botao = await screen.findByRole('button', { name: '1 erro para salvar' });
    expect((botao as HTMLButtonElement).disabled).toBe(true);
    // A conferência foi pedida ao servidor, não calculada na tela.
    await waitFor(() => expect(fetchMock.mock.calls.some(([u]) => String(u).endsWith('/naturezas/conferir'))).toBe(true));
  });

  it('UC-NFTR-69 · a lista segue o protótipo: resumo da empresa, abas com contagem e a coluna Tributos', async () => {
    servidor();
    render(<NaturezasSecao />);

    const t = await tabela();
    expect(t.getByText('Remessa em garantia')).toBeTruthy();
    expect(screen.getByRole('heading', { name: 'Tributação padrão' })).toBeTruthy();
    // Faixa de resumo: empresa, regime, origem e IBS/CBS do ano.
    expect(screen.getByText('Empresa Teste')).toBeTruthy();
    expect(screen.getByText('Lucro Presumido')).toBeTruthy();
    expect(screen.getByText('Tubarão · SC')).toBeTruthy();
    expect(screen.getByText('0,1% IBS · 0,9% CBS em 2026')).toBeTruthy();
    // Abas com a mesma contagem do filtro: 2 ativas em Todas, 1 inativa.
    expect(screen.getByRole('tab', { name: /Todas/ }).textContent).toContain('2');
    expect(screen.getByRole('tab', { name: /Inativas/ }).textContent).toContain('1');
    // Colunas do protótipo e as etiquetas de tributo da natureza com ST, IPI, PIS/COFINS e IBS/CBS.
    expect(screen.getByRole('columnheader', { name: 'Tributos' })).toBeTruthy();
    const remessa = within(t.getByText('Remessa em garantia').closest('tr') as HTMLElement);
    for (const tag of ['ICMS', 'ST', 'IPI 50', 'PIS/COFINS 01', 'IBS/CBS 000']) {
      expect(remessa.getByText(tag)).toBeTruthy();
    }
    // A Venda padrão não tem os tributos da tela: não inventa etiqueta para ela.
    const venda = within(t.getByText('Venda').closest('tr') as HTMLElement);
    expect(venda.queryByText('ICMS')).toBeNull();
  });
});
