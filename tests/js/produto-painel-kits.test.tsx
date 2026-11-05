// Painel do produto (Produto/Index): quantos kits dá para vender em cada local (UC-PIDX-40).
// A conta é do servidor e é provada em tests/Feature/Produto/ProdutoKitsDisponiveisTest.php; aqui,
// o que a aba Composição mostra com o que o servidor manda.
//
// Cobre UC-PIDX-40 (resources/js/Pages/Produto/Index.casos.md).
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

const KIT = (kits: DetalheProdutoDados['kits_por_local']) => detalhe({
  tipo: 'combo',
  composicao: [
    { nome: 'Caneta', sku: 'CAN', quantidade: 2, unidade: 'Un', imagens: [] },
    { nome: 'Caderno', sku: 'CAD', quantidade: 1, unidade: 'Un', imagens: [] },
  ],
  kits_por_local: kits,
});

describe('UC-PIDX-40 · quantos kits dá para vender', () => {
  it('mostra os kits de cada local e qual componente limita', () => {
    const { painel } = abrir(KIT([
      { local: 'Filial', kits: 0, limita: 'Caneta' },
      { local: 'Matriz', kits: 3, limita: 'Caderno' },
    ]), { abaInicial: 'composicao' });
    const bloco = painel.getByText('Quantos kits dá para vender').closest('[data-contract="produto-detalhe-kits"]') as HTMLElement;
    expect(bloco).toBeTruthy();
    const linhas = within(bloco).getAllByRole('row').slice(1).map((r) => r.textContent);
    expect(linhas).toEqual(['Filial0 kitsCaneta', 'Matriz3 kitsCaderno']);
    // Zero kits fica em destaque.
    expect(within(bloco).getByText('0 kits').className).toContain('text-[var(--neg)]');
  });

  it('um kit no singular', () => {
    const { painel } = abrir(KIT([{ local: 'Matriz', kits: 1, limita: 'Caderno' }]), { abaInicial: 'composicao' });
    expect(painel.getByText('1 kit')).toBeTruthy();
  });

  it('sem kits_por_local (nenhum componente controla estoque) o bloco não aparece', () => {
    const { painel } = abrir(KIT(null), { abaInicial: 'composicao' });
    // Controle positivo: a composição aparece.
    expect(painel.getByText('Caneta')).toBeTruthy();
    expect(painel.queryByText('Quantos kits dá para vender')).toBeNull();
  });
});
