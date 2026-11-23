/**
 * Importar nota de referência na natureza — porta de `lerXml()`, `passoNat()` e `painelImport()` do protótipo
 * aprovado (`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`, RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-73 UC-NFTR-74 UC-NFTR-75 UC-NFTR-76 UC-NFTR-77
 *
 * Os casos vêm do protótipo e do Index.casos.md, não do componente. XML 100% fictício: sem CNPJ, CPF ou nome real.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import NaturezaEditor from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/NaturezaEditor';
import { lerNotaReferencia, type Importacao } from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/importarXml';
import { vazia, type EmpresaResumo, type Natureza } from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/natureza';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const EMPRESA: EmpresaResumo = { nome: 'Empresa Teste', cnpj: null, municipio: 'Tubarão', uf: 'SC', ano: 2026, ibs: 0.001, cbs: 0.009 };
const DESTINATARIO = 'Destinatario Ficticio da Silva';

const ICMS00 = '<ICMS><ICMS00><orig>0</orig><CST>00</CST><modBC>3</modBC><vBC>987.65</vBC><pICMS>17.00</pICMS><vICMS>167.90</vICMS></ICMS00></ICMS>';
const PIS_LP = '<PIS><PISAliq><CST>01</CST><vBC>987.65</vBC><pPIS>0.6500</pPIS><vPIS>6.42</vPIS></PISAliq></PIS>';
const COFINS_LP = '<COFINS><COFINSAliq><CST>01</CST><vBC>987.65</vBC><pCOFINS>3.0000</pCOFINS><vCOFINS>29.63</vCOFINS></COFINSAliq></COFINS>';

function det(n: number, cfop: string, imposto: string) {
  return `<det nItem="${n}"><prod><cProd>${n}</cProd><xProd>Produto ${n}</xProd><CFOP>${cfop}</CFOP><vProd>987.65</vProd></prod><imposto>${imposto}</imposto></det>`;
}

function nota({ tpNF = '1', finNFe = '1', natOp = 'Venda de mercadoria', itens = [det(1, '5102', ICMS00 + PIS_LP + COFINS_LP)] } = {}) {
  return '<?xml version="1.0" encoding="UTF-8"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe><infNFe versao="4.00">'
    + `<ide><natOp>${natOp}</natOp><nNF>4821</nNF><tpNF>${tpNF}</tpNF><finNFe>${finNFe}</finNFe></ide>`
    + '<emit><xNome>Emitente Ficticio Ltda</xNome><CRT>3</CRT></emit>'
    + `<dest><xNome>${DESTINATARIO}</xNome></dest>${itens.join('')}<total><ICMSTot><vNF>987.65</vNF></ICMSTot></total>`
    + '</infNFe></NFe></nfeProc>';
}

function ler(xml: string, base: Natureza = vazia(), regime: 'LP' | 'LR' | 'SN' = 'LP'): Importacao {
  const r = lerNotaReferencia(xml, base, regime, EMPRESA);
  if ('erro' in r) throw new Error(r.erro);
  return r;
}
const linha = (r: Importacao, campo: string) => r.linhas.find((l) => l.campo === campo);

function servidor() {
  const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
    void init;
    return { ok: true, status: 200, json: async () => (url.endsWith('/conferir') ? { alertas: [] } : { id: 99 }) } as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

afterEach(() => vi.unstubAllGlobals());

const passos = () => within(screen.getByRole('list', { name: 'Passos' })).getAllByRole('button');

function importarNaTela(xml: string, arquivo = 'nota-4821.xml') {
  fireEvent.click(screen.getByRole('button', { name: /Importar nota de referência/ }));
  fireEvent.change(screen.getByLabelText('Arquivo XML da nota de referência'), {
    target: { files: [new File([xml], arquivo, { type: 'text/xml' })] },
  });
}

describe('Importar nota de referência', () => {
  it('UC-NFTR-73 · preenche os passos pelo 1º item e diz o que copiou, ajustou e não achou', () => {
    const r = ler(nota({ itens: [det(1, '5102', ICMS00 + PIS_LP + COFINS_LP), det(2, '5405', ICMS00.replace(/00/g, '60') + PIS_LP + COFINS_LP)] }));
    const n = r.natureza;

    expect([n.nome, n.tipo, n.fin]).toEqual(['Venda de mercadoria', 'S', '1']);
    expect(n.cfop).toEqual({ in: '5102', out: '6102', ext: '' });
    expect(linha(r, 'Dentro do estado')?.situacao).toBe('ok');
    expect(linha(r, 'Outro estado')).toMatchObject({ situacao: 'ajuste', obs: 'Deduzido do CFOP lido; confira.' });

    // ICMS: o CST é copiado; a alíquota da nota vira "pela UF" (muda com o destino do cliente).
    expect([n.icms.cst, n.icms.modbc, n.icms.origem]).toEqual(['00', '3', 'uf']);
    expect(linha(r, 'Alíquota do ICMS')?.situacao).toBe('ajuste');

    // PIS/COFINS iguais ao padrão do Lucro Presumido ficam "padrão do regime".
    expect([n.pis.origem, n.cofins.origem]).toEqual(['regime', 'regime']);

    // Sem IPI e sem IBSCBS na nota: os passos ficam desligados e aparecem como não encontrados.
    expect([n.ipi.on, n.ibs.on]).toEqual([false, false]);
    expect(linha(r, 'IPI')?.situacao).toBe('nao');
    expect(r.passos).not.toContain('ipi');

    // Item 2 com CFOP e CST diferentes do 1º: aviso, e o do item 1 vale.
    expect(r.linhas.filter((l) => l.campo === 'Outros itens').map((l) => l.valor)).toEqual(['5405', '00, 60']);
    expect(r.info).toEqual({ natOp: 'Venda de mercadoria', nNF: '4821', emitente: 'Emitente Ficticio Ltda', itens: 2 });
  });

  it('UC-NFTR-73 · IBS/CBS iguais ao padrão do ano ficam "padrão"; diferentes ficam fixas', () => {
    const ibs = (uf: string, cbs: string) => `<IBSCBS><CST>000</CST><cClassTrib>000001</cClassTrib><gIBSCBS><vBC>987.65</vBC>`
      + `<gIBSUF><pIBSUF>${uf}</pIBSUF></gIBSUF><gIBSMun><pIBSMun>0.0000</pIBSMun></gIBSMun><gCBS><pCBS>${cbs}</pCBS></gCBS></gIBSCBS></IBSCBS>`;
    const padrao = ler(nota({ itens: [det(1, '5102', ICMS00 + PIS_LP + COFINS_LP + ibs('0.1000', '0.9000'))] })).natureza.ibs;
    expect(padrao).toMatchObject({ on: true, cst: '000', cClass: '000001', origem: 'padrao' });

    const fixa = ler(nota({ itens: [det(1, '5102', ICMS00 + PIS_LP + COFINS_LP + ibs('0.0500', '0.4500'))] })).natureza.ibs;
    expect(fixa).toMatchObject({ origem: 'fixa', pUF: 0.05, pMun: 0, pCBS: 0.45 });
  });

  it('UC-NFTR-74 · arquivo que não é o XML de uma NF-e é recusado e a natureza fica como estava', async () => {
    expect(lerNotaReferencia('%PDF-1.7 não é xml', vazia(), 'LP', EMPRESA)).toEqual({ erro: expect.stringMatching(/não é um XML válido/) });
    expect(lerNotaReferencia('<x><ide/></x>', vazia(), 'LP', EMPRESA)).toEqual({ erro: expect.stringMatching(/tags ide e det/) });

    servidor();
    render(<NaturezaEditor inicial={vazia()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);
    importarNaTela('%PDF-1.7 não é xml', 'danfe.pdf');
    expect((await screen.findByRole('alert')).textContent).toMatch(/não é um XML válido/);
    expect((screen.getByLabelText('Nome da natureza') as HTMLInputElement).value).toBe('');
  });

  it('UC-NFTR-75 · devolução escolhida antes mantém a finalidade e converte o CFOP da nota original', () => {
    const devolucao = (tipo: 'S' | 'E'): Natureza => ({ ...vazia(), tipo, fin: '4', ref: true });

    // Devolução de compra (saída) importando a compra original: 1102 → 5202.
    const compra = ler(nota({ tpNF: '0', itens: [det(1, '1102', ICMS00 + PIS_LP + COFINS_LP)] }), devolucao('S'));
    expect([compra.natureza.fin, compra.natureza.tipo, compra.natureza.cfop.in]).toEqual(['4', 'S', '5202']);
    expect([compra.natureza.icms.origem, compra.natureza.pis.origem, compra.natureza.cofins.origem]).toEqual(['origem', 'origem', 'origem']);
    expect(linha(compra, 'Finalidade')?.situacao).toBe('ajuste');

    // Devolução de venda (entrada) importando a venda original: 5102 → 1202.
    const venda = ler(nota(), devolucao('E'));
    expect([venda.natureza.tipo, venda.natureza.cfop.in, venda.natureza.ref]).toEqual(['E', '1202', true]);
  });

  it('UC-NFTR-76 · o XML não sai do navegador e nenhum valor da nota fica na natureza', async () => {
    const fetchMock = servidor();
    render(<NaturezaEditor inicial={vazia()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);
    importarNaTela(nota());

    await screen.findByText('nota-4821.xml');
    await waitFor(() => expect(fetchMock).toHaveBeenCalled());
    await waitFor(() => expect(fetchMock.mock.calls.some(([, init]) => String(init?.body ?? '').includes('Venda de mercadoria'))).toBe(true));

    for (const [, init] of fetchMock.mock.calls) {
      const corpo = String(init?.body ?? '');
      expect(corpo).not.toContain('nfeProc');
      expect(corpo).not.toContain(DESTINATARIO);
      expect(corpo).not.toContain('987.65');
    }
    expect(JSON.stringify(ler(nota()).natureza)).not.toMatch(/987\.65|167\.9|Emitente|Destinatario/);
  });

  it('UC-NFTR-77 · a pergunta só aparece em natureza nova, e o passo preenchido mostra "do XML, revisar"', async () => {
    servidor();
    const { unmount } = render(
      <NaturezaEditor inicial={{ ...vazia(), id: 7, nome: 'Remessa em garantia' }} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />,
    );
    expect(screen.queryByText('Como você quer preencher os tributos?')).toBeNull();
    unmount();

    render(<NaturezaEditor inicial={vazia()} editavel regime="LP" empresa={EMPRESA} onFechar={() => {}} />);
    expect(screen.getByText('Como você quer preencher os tributos?')).toBeTruthy();
    importarNaTela(nota());
    await screen.findByText('nota-4821.xml');
    expect(within(passos()[1]!).getByText('do XML, revisar')).toBeTruthy();
    expect((screen.getByLabelText('Nome da natureza') as HTMLInputElement).value).toBe('Venda de mercadoria');

    fireEvent.click(screen.getByRole('button', { name: /Iniciar em branco/ }));
    expect(passos()[1]!.getAttribute('aria-current')).toBe('step');
  });
});
