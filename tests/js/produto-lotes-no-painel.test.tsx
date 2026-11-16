/**
 * UC-PIDX-49 (CV3): os lotes do produto no painel, com o saldo de cada lote (numa comunicação visual, cada
 * rolo é um lote) e o botão que abre o relatório de Lotes filtrado no produto. A aba só aparece com lote de
 * saldo; o saldo chega do servidor (a consulta do relatório de Lotes) e aqui só se formata.
 */
import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import type { AbaDoDetalhe, DetalheProdutoDados, LotesDoProdutoNoPainel } from '@/Pages/Produto/_components/lista/tipos';

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

const LOTES: LotesDoProdutoNoPainel = {
  lotes: [
    { lote: 'ROLO-01', variacao: null, validade: '31/12/2099', vencido: false, estoque: 42.5, unidade: 'm²' },
    { lote: 'ROLO-02', variacao: null, validade: '01/01/2020', vencido: true, estoque: 80, unidade: 'm²' },
  ],
  mais: false,
  link: '/reports/lot-report?tela=nova&product_id=7',
};

const secaoLotes = (painel: ReturnType<typeof within>) =>
  within(painel.getByText((_, el) => el?.getAttribute('data-contract') === 'produto-detalhe-lotes'));

describe('UC-PIDX-49 · aba Lotes no painel', () => {
  it('sem lote com saldo (ou sem permissão) a aba não existe; com lotes ela aparece, com a contagem', () => {
    const vazio = abrir(detalhe({ lotes: { ...LOTES, lotes: [] } }));
    expect(vazio.painel.queryByRole('tab', { name: /Lotes/ })).toBeNull();
    cleanup();
    const semPermissao = abrir(detalhe({ lotes: null }));
    expect(semPermissao.painel.queryByRole('tab', { name: /Lotes/ })).toBeNull();
    cleanup();
    const com = abrir(detalhe({ lotes: LOTES }));
    expect(com.painel.getByRole('tab', { name: /Lotes/ }).textContent).toContain('2');
  });

  it('cada lote com a validade e o saldo; o vencido leva a marca; sem variação, a coluna não aparece', () => {
    const { painel } = abrir(detalhe({ lotes: LOTES }), { abaInicial: 'lotes' });
    const tabela = secaoLotes(painel).getByRole('table', { name: 'Lotes do produto com saldo' });
    const linhas = tabela.querySelectorAll('tbody tr');
    expect(linhas).toHaveLength(2);
    expect(linhas[0].textContent).toContain('ROLO-01');
    expect(linhas[0].textContent).toContain('31/12/2099');
    expect(linhas[0].textContent).toContain('42,5');
    expect(linhas[0].textContent).not.toContain('Vencido');
    expect(linhas[1].textContent).toContain('Vencido');
    expect(tabela.querySelector('thead')!.textContent).not.toContain('Variação');
  });

  it('com variação, a coluna aparece; com mais lotes que os mostrados, o aviso aponta o relatório', () => {
    const { painel } = abrir(
      detalhe({ lotes: { ...LOTES, mais: true, lotes: [{ ...LOTES.lotes[0], variacao: 'Fosca' }] } }),
      { abaInicial: 'lotes' },
    );
    const secao = secaoLotes(painel);
    expect(secao.getByRole('table').querySelector('thead')!.textContent).toContain('Variação');
    expect(secao.getByRole('table').querySelector('tbody tr')!.textContent).toContain('Fosca');
    expect(secao.getByText('Há mais lotes com saldo. O relatório mostra todos.')).toBeTruthy();
  });

  it('"Abrir no relatório de Lotes" leva ao relatório filtrado no produto', () => {
    const { painel, irPara } = abrir(detalhe({ lotes: LOTES }), { abaInicial: 'lotes' });
    fireEvent.click(painel.getByRole('button', { name: 'Abrir no relatório de Lotes' }));
    expect(irPara).toHaveBeenCalledWith('/reports/lot-report?tela=nova&product_id=7');
  });
});
