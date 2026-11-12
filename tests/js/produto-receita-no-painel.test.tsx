/**
 * UC-PIDX-48 (D-29, decisão [F] 2026-11-12): a receita da Fabricação no painel, só leitura — insumos,
 * quantidades, custo, custo por unidade e margem, com "Abrir receita" na Fabricação. A aba só aparece com
 * receita; os números chegam do servidor (a conta da tela de Receitas) e aqui só se formatam.
 */
import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import type { AbaDoDetalhe, DetalheProdutoDados, ReceitaDoProdutoNoPainel } from '@/Pages/Produto/_components/lista/tipos';
import { brl } from '@/Pages/Produto/Unificado/_components/catalogo';

const detalhe = (parte: Partial<DetalheProdutoDados> = {}): DetalheProdutoDados => ({
  id: 7,
  nome: 'Camiseta básica',
  sku: 'CAM-001',
  tipo: 'single',
  inativo: false,
  imagem: null,
  dados: {
    codigo_de_barras: 'C128',
    unidade: 'Unidade',
    unidade_curta: 'Un',
    marca: 'Hering',
    categoria: 'Camisetas',
    subcategoria: '',
    imposto: '',
    tipo_imposto: 'inclusive',
    peso: '',
    controla_estoque: true,
    alerta: null,
    validade: null,
    locais: ['Matriz'],
    nao_para_venda: false,
    garantia: null,
    tempo_preparo: null,
    descricao: '',
    campos: [],
  },
  variacoes: [{ nome: 'DUMMY', sku: 'CAM-001', imagens: [], grupos: [{ grupo: 'Atacado', preco: null }] }],
  composicao: [],
  composicao_total_exc: null,
  estoque: [],
  estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '', origem: '0', origem_por_produto: false, link_modulo: null },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
  ...parte,
});

/** O painel com o dono da aba do lado de fora, como em `Produto/Index.tsx`. */
function Painel({
  d, abaInicial = 'dados', emiteNota = true, aoFechar, irPara,
}: { d: DetalheProdutoDados; abaInicial?: AbaDoDetalhe; emiteNota?: boolean; aoFechar: () => void; irPara: (u: string) => void }) {
  const [aba, setAba] = useState<AbaDoDetalhe>(abaInicial);
  return (
    <PainelDetalheProduto
      aberto
      detalhe={d}
      aba={aba}
      aoMudarAba={setAba}
      aoFechar={aoFechar}
      podeEditar
      podePrecos
      emiteNota={emiteNota}
      irPara={irPara}
    />
  );
}

function abrir(d: DetalheProdutoDados, opcoes: { abaInicial?: AbaDoDetalhe; emiteNota?: boolean } = {}) {
  const aoFechar = vi.fn();
  const irPara = vi.fn();
  render(<Painel d={d} abaInicial={opcoes.abaInicial} emiteNota={opcoes.emiteNota} aoFechar={aoFechar} irPara={irPara} />);
  return { aoFechar, irPara, painel: within(screen.getByRole('dialog')) };
}

afterEach(() => cleanup());

const RECEITA: ReceitaDoProdutoNoPainel = {
  nome: 'Banner 1x1', sku: 'BAN-1', rendimento: 1, unidade: 'Un',
  insumos: [{ nome: 'Lona 440g', sku: 'LONA-440', quantidade: 2, unidade: 'm²' }],
  custo_total: 22, custo_unitario: 22, margem: 50, link: '/manufacturing/recipe?q=BAN-1',
};

describe('UC-PIDX-48 · aba Receita no painel', () => {
  it('sem receita a aba não existe; com receita ela aparece, com o número de insumos', () => {
    const sem = abrir(detalhe({ receitas: [] }));
    expect(sem.painel.queryByRole('tab', { name: /Receita/ })).toBeNull();
    cleanup();
    const com = abrir(detalhe({ receitas: [RECEITA] }));
    expect(com.painel.getByRole('tab', { name: /Receita/ }).textContent).toContain('1');
  });

  it('com receita: insumos, quantidades, custo, custo por unidade e margem', () => {
    const { painel } = abrir(detalhe({ receitas: [RECEITA] }), { abaInicial: 'receita' });
    const secao = within(painel.getByText((_, el) => el?.getAttribute('data-contract') === 'produto-detalhe-receita'));
    expect(secao.getByText('Receita: Banner 1x1')).toBeTruthy();
    const linha = secao.getByRole('table', { name: 'Insumos da receita' }).querySelector('tbody tr')!;
    expect(linha.textContent).toContain('Lona 440g');
    expect(linha.textContent).toContain('LONA-440');
    expect(linha.textContent).toContain('2');
    expect(secao.getByText('50%')).toBeTruthy();
    // Custo da receita e custo por unidade: 22 cada (o cenário rende 1), no formato de moeda do painel.
    expect(secao.getAllByText((t) => t.replace(/\s/g, ' ') === brl(22).replace(/\s/g, ' '))).toHaveLength(2);
  });

  it('"Abrir receita" leva à Fabricação; sem o link (sem permissão), o botão não aparece', () => {
    const { painel, irPara } = abrir(detalhe({ receitas: [RECEITA] }), { abaInicial: 'receita' });
    fireEvent.click(painel.getByRole('button', { name: 'Abrir receita' }));
    expect(irPara).toHaveBeenCalledWith('/manufacturing/recipe?q=BAN-1');
    cleanup();
    const sem = abrir(detalhe({ receitas: [{ ...RECEITA, link: null }] }), { abaInicial: 'receita' });
    expect(sem.painel.queryByRole('button', { name: 'Abrir receita' })).toBeNull();
  });

  it('sem ver o preço de compra: custo e margem aparecem como "—", com o motivo', () => {
    const { painel } = abrir(detalhe({ receitas: [{ ...RECEITA, custo_total: null, custo_unitario: null, margem: null }] }), { abaInicial: 'receita' });
    expect(painel.getAllByText('sem permissão para ver o custo')).toHaveLength(3);
    expect(painel.queryByText('50%')).toBeNull();
  });
});
