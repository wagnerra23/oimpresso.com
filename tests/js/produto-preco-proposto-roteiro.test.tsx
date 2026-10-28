// Consulta de produto (Produto/Index): preço proposto pela compra (UC-PIDX-28) e roteiro com o setor
// de cada etapa (UC-PIDX-29) — o que a tela DESENHA com o que o servidor mandou.
//
// O contrato dos dados (quem vê, Tier 0, ordem das etapas) é provado no
// tests/Feature/Produto/ProdutoPrecoPropostoERoteiroContratoTest.php. Aqui:
//   - a célula "Preço de venda" mostra o preço gravado e, abaixo, o proposto aguardando;
//   - o painel lista a proposta com a compra de origem; "Abrir a compra" leva ao detalhe da compra e
//     some sem o link (sem `purchase.view`); NÃO há Aprovar nem Recusar no painel (charter: a consulta
//     não altera preço);
//   - a aba Roteiro só existe com roteiro e mostra cada etapa com o posto e o setor.
//
// Cobre UC-PIDX-28 e UC-PIDX-29 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-preco-proposto-roteiro.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState, type ReactNode } from 'react';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import { colunasDaLista } from '@/Pages/Produto/_components/lista/colunas';
import type { AbaDoDetalhe, DetalheProdutoDados, LinhaProduto } from '@/Pages/Produto/_components/lista/tipos';
import { brl } from '@/Pages/Produto/Unificado/_components/catalogo';

const detalhe = (parte: Partial<DetalheProdutoDados> = {}): DetalheProdutoDados => ({
  id: 7,
  nome: 'Lona front 440 g',
  sku: 'LON-440',
  tipo: 'single',
  inativo: false,
  imagem: null,
  dados: {
    codigo_de_barras: 'C128', unidade: 'Metro quadrado', unidade_curta: 'm²', marca: '', categoria: 'Mídias', subcategoria: '',
    imposto: '', tipo_imposto: 'inclusive', peso: '', controla_estoque: true, alerta: null, validade: null, locais: ['Matriz'],
    nao_para_venda: false, garantia: null, tempo_preparo: null, descricao: '', campos: [],
  },
  variacoes: [{ nome: 'DUMMY', sku: 'LON-440', imagens: [], venda_exc: 16.5, venda_inc: 16.5, grupos: [] }],
  composicao: [],
  composicao_total_exc: null,
  estoque: [],
  estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '', origem: '0', origem_por_produto: true, link_modulo: null },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
  roteiro: null,
  ...parte,
});

function Painel({ d, abaInicial = 'dados', irPara }: { d: DetalheProdutoDados; abaInicial?: AbaDoDetalhe; irPara: (u: string) => void }) {
  const [aba, setAba] = useState<AbaDoDetalhe>(abaInicial);
  return (
    <PainelDetalheProduto aberto detalhe={d} aba={aba} aoMudarAba={setAba} aoFechar={() => {}} podeEditar={false} podePrecos={false} emiteNota irPara={irPara} />
  );
}

function abrir(d: DetalheProdutoDados, abaInicial: AbaDoDetalhe = 'dados') {
  const irPara = vi.fn();
  render(<Painel d={d} abaInicial={abaInicial} irPara={irPara} />);
  return { irPara, painel: within(screen.getByRole('dialog')) };
}

const proposta = {
  id: 31, variacao: null, preco_atual: 16.5, preco_proposto: 18.15, preco_mudou: false,
  compra_ref: 'PC-0042', compra_data: '2026-10-12', compra_url: '/purchases/42',
};

afterEach(() => cleanup());

describe('UC-PIDX-28 · preço proposto pela compra recebida', () => {
  const linha = (parte: Partial<LinhaProduto>): LinhaProduto => ({
    id: 7, nome: 'Lona front 440 g', sku: 'LON-440', tipo: 'single', categoria: null, subcategoria: null, unidade: null, unidade_curta: null,
    marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 0, alerta: null, abaixo_do_alerta: false, inativo: false,
    nao_para_venda: false, serie: false, locais: [], variacoes: 1, campos: {}, anexo: null, venda_min: 16.5, venda_max: 16.5, ...parte,
  });
  const celulaDaVenda = (l: LinhaProduto) => {
    const coluna = colunasDaLista({
      visiveis: { venda: true }, densa: false,
      permissions: { access_default_selling_price: true } as never,
      contexto: { grupos_de_preco: [], rotulos_campos: {} } as never, aoEscolher: () => undefined,
    }).find((c) => c.id === 'venda');
    expect(coluna).toBeTruthy();
    const Celula = coluna!.cell as (p: unknown) => ReactNode;
    render(<div>{Celula({ row: { original: l } })}</div>);
  };

  it('a célula mostra o preço gravado e, abaixo, o proposto aguardando', () => {
    celulaDaVenda(linha({ preco_proposto: { quantidade: 1, preco: 18.15 } }));
    // O preço gravado continua na célula (o `title` guarda o texto inteiro, antes dos espaços inseparáveis).
    expect(screen.getByTitle(brl(16.5))).toBeTruthy();
    expect(screen.getByText(`proposto ${brl(18.15)} · aguardando`)).toBeTruthy();
  });

  it('com mais de uma proposta, conta as propostas', () => {
    celulaDaVenda(linha({ preco_proposto: { quantidade: 3, preco: null } }));
    expect(screen.getByText('3 preços propostos · aguardando')).toBeTruthy();
  });

  it('sem proposta, só o preço', () => {
    celulaDaVenda(linha({ preco_proposto: null }));
    expect(screen.queryByText(/aguardando/)).toBeNull();
  });

  it('o painel lista a proposta com a compra de origem e leva ao detalhe da compra', () => {
    const { painel, irPara } = abrir(detalhe({ precos_propostos: [proposta] }));
    const secao = within(painel.getByRole('region', { name: 'Preço proposto pela compra' }));
    expect(secao.getByText(brl(18.15))).toBeTruthy();
    expect(secao.getByText('PC-0042 · 12/10/2026')).toBeTruthy();
    fireEvent.click(secao.getByRole('button', { name: 'Abrir a compra' }));
    expect(irPara).toHaveBeenCalledWith('/purchases/42');
    // A consulta não altera preço: nada de Aprovar ou Recusar aqui.
    expect(painel.queryByRole('button', { name: /aprovar|recusar/i })).toBeNull();
  });

  it('sem o link (sem purchase.view) o botão some; preço mudado avisa que só dá para recusar', () => {
    const { painel } = abrir(detalhe({ precos_propostos: [{ ...proposta, compra_url: null, preco_mudou: true }] }));
    expect(painel.queryByRole('button', { name: 'Abrir a compra' })).toBeNull();
    expect(painel.getByText(/só pode ser recusada/)).toBeTruthy();
  });

  it('sem proposta, a seção não aparece', () => {
    const { painel } = abrir(detalhe({ precos_propostos: [] }));
    expect(painel.queryByRole('region', { name: 'Preço proposto pela compra' })).toBeNull();
  });
});

describe('UC-PIDX-29 · roteiro com o setor de cada etapa', () => {
  const roteiro = {
    nome: 'Banner impresso',
    ativo: true,
    etapas: [
      { nome: 'Arte', posto: 'Estação de arte', setor: 'Criação', horas: 1, opcional: false, paralela: false },
      { nome: 'Impressão', posto: 'Plotter 1,60 m', setor: 'Impressão digital', horas: 2, opcional: false, paralela: false },
      { nome: 'Laminação', posto: 'Laminadora', setor: 'Acabamento', horas: 0.5, opcional: true, paralela: false },
    ],
  };

  it('sem roteiro, não há aba Roteiro', () => {
    const { painel } = abrir(detalhe({ roteiro: null }));
    expect(painel.queryByRole('tab', { name: /Roteiro/ })).toBeNull();
    expect(painel.queryByText('Roteiro')).toBeNull();
  });

  it('a aba Roteiro mostra as etapas na ordem, com o posto e o setor', () => {
    const { painel } = abrir(detalhe({ roteiro }), 'roteiro');
    const tabela = within(painel.getByRole('table', { name: 'Etapas do roteiro' }));
    const linhas = tabela.getAllByRole('row').slice(1).map((r) => within(r).getAllByRole('cell').map((c) => c.textContent));
    expect(linhas.map((l) => [l[1], l[2], l[3]])).toEqual([
      ['Arte', 'Estação de arte', 'Criação'],
      ['Impressão', 'Plotter 1,60 m', 'Impressão digital'],
      ['Laminação · opcional', 'Laminadora', 'Acabamento'],
    ]);
  });
});
