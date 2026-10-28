// Consulta de produto (Produto/Index): requisição de compra aberta (UC-PIDX-30) — o que a tela DESENHA com o que o servidor
// mandou. O contrato dos dados (permissões e Tier 0) é provado em
// tests/Feature/Produto/ProdutoRequisicaoAbertaContratoTest.php.
//
// Cobre UC-PIDX-30 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-requisicao-aberta.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState, type ReactNode } from 'react';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import { colunasDaLista } from '@/Pages/Produto/_components/lista/colunas';
import { type AbaDoDetalhe, type DetalheProdutoDados, type LinhaProduto } from '@/Pages/Produto/_components/lista/tipos';

const detalhe = (parte: Partial<DetalheProdutoDados> = {}): DetalheProdutoDados => ({
  id: 7,
  nome: 'Adesivo vinil branco',
  sku: 'ADV-BRB',
  tipo: 'single',
  inativo: false,
  imagem: null,
  dados: {
    codigo_de_barras: 'C128', unidade: 'Metro quadrado', unidade_curta: 'm²', marca: '', categoria: 'Adesivos', subcategoria: '',
    imposto: '', tipo_imposto: 'inclusive', peso: '', controla_estoque: true, alerta: 20, validade: null, locais: ['Matriz'],
    nao_para_venda: false, garantia: null, tempo_preparo: null, descricao: '', campos: [],
  },
  variacoes: [{ nome: 'DUMMY', sku: 'ADV-BRB', imagens: [], compra_exc: 11.5, compra_inc: 11.5, grupos: [] }],
  composicao: [],
  composicao_total_exc: null,
  estoque: [],
  estoque_totais: null,
  fiscal: { ncm: '', cest: '', cfop_interno: '', cfop_externo: '', cst_csosn: '', cst_pis: '', cst_cofins: '', cst_ipi: '', origem: '0', origem_por_produto: true, link_modulo: null },
  prateleiras: { colunas: { prateleira: false, fileira: false, posicao: false }, linhas: [] },
  roteiro: null,
  ...parte,
});

function Painel({ d, abaInicial, irPara }: { d: DetalheProdutoDados; abaInicial: AbaDoDetalhe; irPara: (u: string) => void }) {
  const [aba, setAba] = useState<AbaDoDetalhe>(abaInicial);
  return (
    <PainelDetalheProduto aberto detalhe={d} aba={aba} aoMudarAba={setAba} aoFechar={() => {}} podeEditar={false} podePrecos={false} emiteNota irPara={irPara} />
  );
}

function abrir(d: DetalheProdutoDados, aba: AbaDoDetalhe) {
  const irPara = vi.fn();
  render(<Painel d={d} abaInicial={aba} irPara={irPara} />);
  return { irPara, painel: within(screen.getByRole('dialog')) };
}

const linha = (parte: Partial<LinhaProduto>): LinhaProduto => ({
  id: 7, nome: 'Adesivo vinil branco', sku: 'ADV-BRB', tipo: 'single', categoria: null, subcategoria: null, unidade: 'Metro quadrado',
  unidade_curta: 'm²', marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 12, alerta: 20, abaixo_do_alerta: true,
  inativo: false, nao_para_venda: false, serie: false, locais: [], variacoes: 1, campos: {}, anexo: null, ...parte,
});

function celula(id: string, l: LinhaProduto) {
  const coluna = colunasDaLista({
    visiveis: { estoque: true }, densa: false,
    permissions: {} as never,
    contexto: { grupos_de_preco: [], rotulos_campos: {} } as never, aoEscolher: () => undefined,
  }).find((c) => c.id === id);
  expect(coluna).toBeTruthy();
  const Celula = coluna!.cell as (p: unknown) => ReactNode;
  render(<div>{Celula({ row: { original: l } })}</div>);
}

const requisicao = { id: 153, numero: 'RQ-0153', data: '2026-10-21', fornecedor: 'Oracal', url: '/purchase-requisition/153' };

afterEach(() => cleanup());

describe('UC-PIDX-30 · requisição de compra aberta', () => {
  it('a célula de estoque mostra o saldo e, abaixo, a requisição com a data e o fornecedor', () => {
    celula('estoque', linha({ requisicao }));
    expect(screen.getByText('Requisição RQ-0153 aberta em 21/10 · Oracal')).toBeTruthy();
  });

  it('sem requisição, só o saldo', () => {
    celula('estoque', linha({ requisicao: null }));
    expect(screen.queryByText(/Requisição/)).toBeNull();
  });

  it('o painel, na aba Estoque, mostra a requisição e abre a tela dela', () => {
    const { painel, irPara } = abrir(detalhe({ requisicao }), 'estoque');
    expect(painel.getByText('RQ-0153')).toBeTruthy();
    expect(painel.getByText(/aberta em 21\/10\/2026, para Oracal/)).toBeTruthy();
    fireEvent.click(painel.getByRole('button', { name: 'Abrir a requisição' }));
    expect(irPara).toHaveBeenCalledWith('/purchase-requisition/153');
  });
});
