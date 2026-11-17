/**
 * UC-PIDX-46 (resto da CV1): o painel diz "O que é" o produto — o uso, pela regra do filtro "Uso"
 * (UC-PIDX-44), e a forma do cadastro só quando muda alguma coisa. A coluna Tipo sai do padrão da lista
 * e segue no menu Colunas. Fonte: backlog CV1 (shopVOX produtos × materiais; Calcgraf insumos e acabados).
 */
import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import { colunasOpcionais, oQueE, type AbaDoDetalhe, type DetalheProdutoDados } from '@/Pages/Produto/_components/lista/tipos';

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

const oQueEDoPainel = (d: DetalheProdutoDados) =>
  abrir(d).painel.getByText((_, el) => el?.getAttribute('data-contract') === 'produto-detalhe-o-que-e').textContent;

describe('UC-PIDX-46 · "O que é" no painel', () => {
  it('produto único para vender diz só "Para vender", sem "Único"', () => {
    expect(oQueEDoPainel(detalhe({ uso: ['vender'] }))).toBe('Para vender');
  });

  it('material com variações: o uso e quantas variações', () => {
    const variacoes = [1, 2, 3].map((n) => ({ nome: `Cor ${n}`, sku: `V-${n}`, imagens: [], grupos: [] }));
    expect(oQueEDoPainel(detalhe({ uso: ['material'], tipo: 'variable', variacoes }))).toBe('Material · com 3 variações');
  });

  it('kit: o uso e quantos itens; material e serviço juntos aparecem os dois', () => {
    expect(oQueE(['material', 'servico'], 'combo', 1, 4)).toBe('Material e Serviço · kit com 4 itens');
    expect(oQueE(['servico'], 'combo', 1, 1)).toBe('Serviço · kit com 1 item');
  });

  it('sem o uso no detalhe (servidor antigo), cai em "Para vender"', () => {
    expect(oQueE(undefined, 'single', 1, 0)).toBe('Para vender');
  });
});

describe('UC-PIDX-46 · a coluna Tipo sai do padrão da lista', () => {
  it('Tipo existe no menu Colunas, mas nasce desligada; as outras do padrão seguem ligadas', () => {
    const cols = colunasOpcionais({});
    expect(cols.find((c) => c.id === 'tipo')).toEqual({ id: 'tipo', rotulo: 'Tipo', padrao: false });
    expect(cols.filter((c) => c.padrao).map((c) => c.id)).toEqual(['imagem', 'locais', 'compra', 'venda', 'estoque', 'categoria', 'marca', 'sku']);
  });
});
