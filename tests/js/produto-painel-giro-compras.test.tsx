// Painel do produto (Produto/Index): giro de 90 dias e últimas compras na aba Estoque (UC-PIDX-41 · D8).
// As contas são do servidor e são provadas em tests/Feature/Produto/ProdutoGiroComprasTest.php; aqui,
// o que a aba mostra com o que o servidor manda.
//
// Cobre UC-PIDX-41 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import type { AbaDoDetalhe, DetalheProdutoDados } from '@/Pages/Produto/_components/lista/tipos';

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


const COMPRAS: NonNullable<DetalheProdutoDados['ultimas_compras']> = [
  { id: 31, numero: 'CP-0031', data: '2026-10-28', fornecedor: 'Oracal Brasil', local: 'Matriz', quantidade: 20, url: '/purchases/31' },
  { id: 27, numero: 'CP-0027', data: '2026-10-02', fornecedor: null, local: 'Filial', quantidade: 5.5, url: '/purchases/27' },
];

describe('UC-PIDX-41 · giro e últimas compras no painel', () => {
  it('mostra o vendido em 90 dias e as compras na ordem do servidor, com link para a compra', () => {
    const { painel, irPara } = abrir(detalhe({ vendido_90_dias: 12, ultimas_compras: COMPRAS }), { abaInicial: 'estoque' });
    const giro = painel.getByText(/Vendido nos últimos 90 dias/).closest('[data-contract="produto-detalhe-giro"]') as HTMLElement;
    expect(giro).toBeTruthy();
    expect(giro.textContent).toBe('Vendido nos últimos 90 dias: 12 Un');

    const bloco = painel.getByText('Últimas compras').closest('[data-contract="produto-detalhe-ultimas-compras"]') as HTMLElement;
    expect(bloco).toBeTruthy();
    const linhas = within(bloco).getAllByRole('row').slice(1).map((r) => r.textContent);
    expect(linhas).toEqual(['28/10/2026Oracal BrasilMatriz20 UnCP-0031', '02/10/2026—Filial5,5 UnCP-0027']);
    within(bloco).getByRole('button', { name: 'CP-0027' }).click();
    expect(irPara).toHaveBeenCalledWith('/purchases/27');
  });

  it('nenhuma compra recebida mostra o aviso; zero vendido mostra 0', () => {
    const { painel } = abrir(detalhe({ vendido_90_dias: 0, ultimas_compras: [] }), { abaInicial: 'estoque' });
    expect(painel.getByText('Nenhuma compra recebida deste produto.')).toBeTruthy();
    expect(painel.getByText(/Vendido nos últimos 90 dias/).textContent).toBe('Vendido nos últimos 90 dias: 0 Un');
  });

  it('sem as chaves (sem permissão) nada aparece', () => {
    const { painel } = abrir(detalhe({ vendido_90_dias: null, ultimas_compras: null }), { abaInicial: 'estoque' });
    // Controle positivo: a aba Estoque abriu.
    expect(painel.getByText('Detalhes de estoque')).toBeTruthy();
    expect(painel.queryByText(/Vendido nos últimos 90 dias/)).toBeNull();
    expect(painel.queryByText('Últimas compras')).toBeNull();
  });
});
