// Consulta de produto (Produto/Index): excluir sem perder e restaurar (UC-PIDX-45 · D13 · AR-PROD-022). O que o
// servidor guarda e devolve está em tests/Feature/Produto/ProdutoExcluirRestaurarTest.php; aqui, o menu da
// linha (excluído só oferece "Restaurar"), a situação "Excluídos" e a 2ª linha da célula.
//
// Cobre UC-PIDX-45 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import AcoesProduto from '@/Pages/Produto/_components/lista/AcoesProduto';
import { SITUACOES, chipsDosFiltros, subLinhaProduto, type FiltrosLista, type LinhaProduto, type OpcoesLista, type PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

const LINHA: LinhaProduto = {
  id: 7, nome: 'Lona 440g', sku: 'LON-7', tipo: 'single', categoria: 'Mídias', subcategoria: null, unidade: 'Metro quadrado', unidade_curta: 'm²',
  marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 18, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
};
const EXCLUIDA: LinhaProduto = { ...LINHA, inativo: true, excluido: true };
const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
};
const OPCOES = { categorias: [], unidades: [], impostos: [], marcas: [], locais: [], modelos_dispositivo: null } as unknown as OpcoesLista;

function abrirMenu(linha: LinhaProduto, permissoes: PermissoesLista = TUDO) {
  const aoEscolher = vi.fn();
  render(<AcoesProduto linha={linha} permissoes={permissoes} gruposDePreco={1} emiteNota aoEscolher={aoEscolher} />);
  fireEvent.keyDown(screen.getByRole('button', { name: `Ações do produto ${linha.nome}` }), { key: 'Enter' });
  return { aoEscolher, itens: () => screen.getAllByRole('menuitem').map((i) => i.textContent) };
}

afterEach(() => cleanup());

describe('UC-PIDX-45 · excluir sem perder e restaurar', () => {
  it('o produto ativo oferece Excluir, e não Restaurar', () => {
    const lista = abrirMenu(LINHA).itens();
    expect(lista).toContain('Excluir');
    expect(lista).toContain('Desativar');
    expect(lista).not.toContain('Restaurar');
  });

  it('o excluído oferece só Restaurar no lugar de Ativar e Excluir, e avisa a tela com a ação', () => {
    const { aoEscolher, itens } = abrirMenu(EXCLUIDA);
    const lista = itens();
    expect(lista).toContain('Restaurar');
    expect(lista).not.toContain('Excluir');
    expect(lista).not.toContain('Ativar');
    fireEvent.click(screen.getByRole('menuitem', { name: 'Restaurar' }));
    expect(aoEscolher).toHaveBeenCalledWith('restaurar', EXCLUIDA);
  });

  it('sem permissão de excluir não há Restaurar (o menu abriu: Editar está lá)', () => {
    const lista = abrirMenu(EXCLUIDA, { ...TUDO, delete: false }).itens();
    expect(lista).toContain('Editar');
    expect(lista).not.toContain('Restaurar');
  });

  it('"Excluídos" é uma situação do filtro e vira chip; a célula diz "excluído"', () => {
    expect(SITUACOES.map((s) => s.id)).toContain('excluido');
    const filtros = { active_state: 'excluido' } as unknown as FiltrosLista;
    expect(chipsDosFiltros(filtros, OPCOES)).toContainEqual({ chave: 'active_state', rotulo: 'Situação: Excluídos' });
    expect(subLinhaProduto(EXCLUIDA)).toContain('excluído');
    expect(subLinhaProduto(EXCLUIDA)).not.toContain('inativo');
  });
});
