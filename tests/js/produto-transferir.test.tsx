// "Transferir entre locais" do menu da linha da lista de produtos (UC-PIDX-52) e os botões
// "Adicionar" que ele cria na transferência de estoque (UC-TRCRT-07).
//
// O que o servidor sugere (produto da empresa, variações, sem kit nem produto sem estoque) é provado em
// tests/Feature/Stock/MovimentacoesTelasContratoTest.php; aqui, o menu e o formulário.
//
// Cobre UC-PIDX-52 e UC-PIDX-54 (resources/js/Pages/Produto/Index.casos.md) e UC-TRCRT-07
// (resources/js/Pages/StockTransfer/Create.casos.md). A origem que o `?origem=` escolhe
// (UC-TRCRT-08) é provada em MovimentacoesTelasContratoTest.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { useState } from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

// `Link` para as abas do painel (PageHeaderTabs); o painel navega pelo `irPara` que recebe.
vi.mock('@inertiajs/react', () => ({
  router: { get: vi.fn(), visit: vi.fn() },
  Link: ({ children, href, ...resto }: { children?: import('react').ReactNode; href?: string }) => <a href={href} {...resto}>{children}</a>,
}));
// A busca de produto da venda (react-query) não está sob teste: o botão "Adicionar" é que está.
vi.mock('@/Pages/Sells/_components/ProductSearchAutocomplete', () => ({ default: () => null }));

import AcoesProduto from '@/Pages/Produto/_components/lista/AcoesProduto';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import LinhasDeEstoque from '@/Pages/Estoque/_components/LinhasDeEstoque';
import type { DetalheProdutoDados, LinhaProduto, LinhaRelatorioEstoque, PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';
import type { LinhaEstoque } from '@/Pages/Estoque/_components/linhas-estoque-dominio';

const LINHA: LinhaProduto = {
  id: 7, nome: 'Lona 440g', sku: 'LON-7', tipo: 'single', categoria: 'Mídias', subcategoria: null, unidade: 'Metro quadrado', unidade_curta: 'm²',
  marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 18, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
} as LinhaProduto;
const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true, transferir: true,
};

function itensDoMenu(linha: LinhaProduto, permissoes: PermissoesLista = TUDO) {
  const aoEscolher = vi.fn();
  render(<AcoesProduto linha={linha} permissoes={permissoes} gruposDePreco={1} emiteNota aoEscolher={aoEscolher} />);
  fireEvent.keyDown(screen.getByRole('button', { name: `Ações do produto ${linha.nome}` }), { key: 'Enter' });
  return { aoEscolher, itens: () => screen.getAllByRole('menuitem').map((i) => i.textContent) };
}

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('UC-PIDX-52 · "Transferir entre locais" no menu da linha', () => {
  it('aparece no grupo de "Dados fiscais" e escolhe a ação "transferir"', () => {
    const { aoEscolher, itens } = itensDoMenu(LINHA);
    const lista = itens();
    expect(lista.indexOf('Transferir entre locais')).toBe(lista.indexOf('Dados fiscais (NCM/CFOP)') - 1);
    fireEvent.click(screen.getByRole('menuitem', { name: 'Transferir entre locais' }));
    expect(aoEscolher).toHaveBeenCalledWith('transferir', LINHA);
  });

  it('some sem a permissão da transferência, sem estoque controlado, no kit e no excluído', () => {
    const casos: [LinhaProduto, PermissoesLista][] = [
      [LINHA, { ...TUDO, transferir: false }],
      [{ ...LINHA, controla_estoque: false }, TUDO],
      [{ ...LINHA, tipo: 'combo' }, TUDO],
      [{ ...LINHA, excluido: true, inativo: true }, TUDO],
    ];
    for (const [linha, permissoes] of casos) {
      const lista = itensDoMenu(linha, permissoes).itens();
      expect(lista.length).toBeGreaterThan(0);
      expect(lista).not.toContain('Transferir entre locais');
      cleanup();
    }
  });
});

const DADOS = {
  product_id: 7, variation_id: 70, nome: 'Lona 440g', sub_sku: 'LON-7', enable_stock: 1, qty_available: 18,
  unidade: 'm²', unit_id: 4, permite_decimal: true, custo: 10, sub_unidades: [], usa_lote: false, usa_validade: false, lotes: [],
};

function Formulario({ localId }: { localId: number | null }) {
  // Estado de verdade, como no Create: a linha que entra volta pelo onChange.
  const [linhas, setLinhas] = useState<LinhaEstoque[]>([]);
  return (
    <LinhasDeEstoque
      localId={localId}
      linhas={linhas}
      onChange={setLinhas}
      verCusto
      editarCusto
      sugeridas={[{ variation_id: 70, nome: 'Lona 440g' }]}
    />
  );
}

describe('UC-TRCRT-07 · botão "Adicionar" do produto sugerido', () => {
  it('fica desligado até escolher a origem', () => {
    render(<Formulario localId={null} />);
    expect((screen.getByRole('button', { name: 'Adicionar Lona 440g' }) as HTMLButtonElement).disabled).toBe(true);
  });

  it('com a origem, busca a linha pelo mesmo caminho da busca, põe na lista e o botão some', async () => {
    const f = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify(DADOS), { status: 200 }));
    render(<Formulario localId={3} />);
    fireEvent.click(screen.getByRole('button', { name: 'Adicionar Lona 440g' }));
    await waitFor(() => expect(screen.getByText(/saldo 18/)).toBeTruthy());
    expect(String(f.mock.calls[0]?.[0])).toBe('/stock-transfers/produto-linha?variation_id=70&location_id=3');
    expect(screen.queryByRole('button', { name: 'Adicionar Lona 440g' })).toBeNull();
  });
});

// ---------- UC-PIDX-54 · "Transferir" por local na aba Estoque do painel ----------

const local = (nome: string, localId: number | null, estoque: number | null): LinhaRelatorioEstoque => ({
  sku: 'LON-7', produto: 'Lona 440g', variacao: '', categoria: 'Mídias', local: nome, local_id: localId, preco: null,
  estoque, unidade: 'm²', valor_compra: null, valor_venda: null, lucro_potencial: null, vendido: 0, transferido: 0, ajustado: 0, alerta: false,
});

const DETALHE: DetalheProdutoDados = {
  id: 7, nome: 'Lona 440g', sku: 'LON-7', tipo: 'single', inativo: false, imagem: null,
  dados: {
    codigo_de_barras: 'C128', unidade: 'Metro quadrado', unidade_curta: 'm²', marca: '', categoria: 'Mídias', subcategoria: '',
    imposto: '', tipo_imposto: 'inclusive', peso: '', controla_estoque: true, alerta: null, validade: null, locais: ['Matriz', 'Filial'],
    nao_para_venda: false, garantia: null, tempo_preparo: null, descricao: '', campos: [],
  },
  variacoes: [{ nome: 'DUMMY', sku: 'LON-7', imagens: [], grupos: [] }],
  composicao: [], composicao_total_exc: null,
  estoque: [local('Matriz', 3, 12), local('Filial', 5, 0)],
  estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '', origem: '0', origem_por_produto: false, link_modulo: null },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
} as DetalheProdutoDados;

function abaEstoque(d: DetalheProdutoDados, podeTransferir = true) {
  const irPara = vi.fn();
  render(
    <PainelDetalheProduto aberto detalhe={d} aba="estoque" aoMudarAba={() => {}} aoFechar={() => {}}
      podeEditar podePrecos podeTransferir={podeTransferir} emiteNota irPara={irPara} />,
  );
  return { irPara, botoes: () => screen.queryAllByRole('button', { name: /^Transferir de / }).map((b) => b.getAttribute('aria-label')) };
}

describe('UC-PIDX-54 · "Transferir" por local na aba Estoque', () => {
  it('o local com saldo abre a transferência com o produto e aquele local de origem', () => {
    const { irPara, botoes } = abaEstoque(DETALHE);
    // A Filial está com saldo 0: sem saldo, a transferência não aceita a linha — o botão não aparece.
    expect(botoes()).toEqual(['Transferir de Matriz']);
    fireEvent.click(screen.getByRole('button', { name: 'Transferir de Matriz' }));
    expect(irPara).toHaveBeenCalledWith('/stock-transfers/create?produto=7&origem=3');
  });

  it('some sem a permissão, sem estoque controlado, no kit e no excluído', () => {
    const casos: [DetalheProdutoDados, boolean][] = [
      [DETALHE, false],
      [{ ...DETALHE, dados: { ...DETALHE.dados, controla_estoque: false } }, true],
      [{ ...DETALHE, tipo: 'combo' }, true],
      [{ ...DETALHE, excluido: true }, true],
    ];
    for (const [d, pode] of casos) {
      const { botoes } = abaEstoque(d, pode);
      expect(botoes()).toEqual([]);
      cleanup();
    }
  });
});
