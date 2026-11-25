/**
 * Editor de natureza em 7 passos — forma do protótipo (RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-71 UC-NFTR-72 UC-NFTR-79 UC-NFTR-80 UC-NFTR-81 UC-NFTR-82
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
    expect((screen.getByRole('button', { name: /^(Criar|Salvar) natureza$/ }) as HTMLButtonElement).disabled).toBe(true);
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

const XML_VENDA = '<?xml version="1.0" encoding="UTF-8"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe><infNFe versao="4.00">'
  + '<ide><natOp>Venda de mercadoria</natOp><nNF>1</nNF><tpNF>1</tpNF><finNFe>1</finNFe></ide><emit><xNome>Emitente Ficticio Ltda</xNome><CRT>3</CRT></emit>'
  + '<det nItem="1"><prod><cProd>1</cProd><xProd>Produto 1</xProd><CFOP>5102</CFOP></prod><imposto>'
  + '<ICMS><ICMS00><orig>0</orig><CST>00</CST><modBC>3</modBC><pICMS>17.00</pICMS></ICMS00></ICMS></imposto></det></infNFe></NFe></nfeProc>';

const botao = (nome: RegExp | string) => screen.getByRole('button', { name: nome }) as HTMLButtonElement;

describe('Natureza nova e passos do protótipo', () => {
  it('UC-NFTR-79 · natureza nova trava passos 2 a 7, o Próximo e o salvar até escolher como preencher', async () => {
    servidor([{ nivel: 'erro', secao: 'nat', mensagem: 'Dê um nome à natureza. É o nome que aparece no lançamento e vai no campo natOp da nota.' }]);
    render(<NaturezaEditor inicial={vazia()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);

    // Antes de qualquer escolha: o aviso do nome já aparece, os passos 2 a 7 e o Próximo ficam travados.
    expect(await screen.findByText(/Dê um nome à natureza\./)).toBeTruthy();
    expect(passos().slice(1).every((b) => (b as HTMLButtonElement).disabled)).toBe(true);
    expect((passos()[0] as HTMLButtonElement).disabled).toBe(false);
    expect(botao(/Próximo: CFOP/).disabled).toBe(true);
    // A lateral pede a escolha e o salvar fica travado.
    expect(screen.getByText('Escolha começar em branco ou importar uma nota.')).toBeTruthy();
    expect(botao('Criar natureza').disabled).toBe(true);

    // Importar: o Próximo segue travado até ler o XML, e então vira "Revisar passo a passo".
    fireEvent.click(screen.getByRole('button', { name: /Importar nota de referência/ }));
    expect(botao(/Revisar passo a passo/).disabled).toBe(true);
    expect(passos()[1].hasAttribute('disabled')).toBe(false);
    fireEvent.change(screen.getByLabelText('Arquivo XML da nota de referência'), {
      target: { files: [new File([XML_VENDA], 'nota.xml', { type: 'text/xml' })] },
    });
    await screen.findByText('nota.xml');
    expect(botao(/Revisar passo a passo/).disabled).toBe(false);
    fireEvent.click(botao(/Revisar passo a passo/));
    expect(passos()[1].getAttribute('aria-current')).toBe('step');
  });

  it('UC-NFTR-80 · sem ICMS próprio (CSOSN 102 do Simples) o passo ICMS não mostra "Entra na base do ICMS"', () => {
    servidor();
    const { unmount } = render(<NaturezaEditor inicial={existente()} editavel regime="SN" empresa={EMPRESA} onFechar={() => {}} />);
    fireEvent.click(passos()[2]);
    expect(screen.getByLabelText('CSOSN')).toBeTruthy();
    expect(screen.queryByText('Entra na base do ICMS')).toBeNull();
    unmount();

    // Com ICMS próprio (CST 00 no Presumido) o grupo aparece.
    render(<NaturezaEditor inicial={existente()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);
    fireEvent.click(passos()[2]);
    expect(screen.getByText('Entra na base do ICMS')).toBeTruthy();
  });

  it('UC-NFTR-81 · a Revisão tem o salvar também no rodapé do passo', async () => {
    servidor();
    render(<NaturezaEditor inicial={existente()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);
    fireEvent.click(passos()[6]);
    const salvar = screen.getAllByRole('button', { name: 'Salvar natureza' });
    // Um no rodapé do passo, outro na lateral.
    expect(salvar).toHaveLength(2);
    expect(screen.queryByRole('button', { name: /Próximo:/ })).toBeNull();
    await waitFor(() => expect(salvar.every((b) => !(b as HTMLButtonElement).disabled)).toBe(true));
  });

  it('UC-NFTR-82 · a lateral "No lançamento" só fica ao lado do passo acima de 1080px', () => {
    servidor();
    render(<NaturezaEditor inicial={existente()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);
    // jsdom não faz layout: o que se confere é a regra de colunas da grade, que é o que o navegador aplica.
    const grade = screen.getByRole('complementary', { name: 'No lançamento' }).parentElement as HTMLElement;
    expect(grade.className).toContain('min-[1081px]:grid-cols-[minmax(0,1fr)_336px]');
    expect(grade.className).not.toMatch(/(^|\s)lg:grid-cols/);
  });
});
