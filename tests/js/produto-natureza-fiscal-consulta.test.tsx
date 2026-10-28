// Consulta de produto (Produto/Index): natureza fiscal (UC-PIDX-31) — o que a tela DESENHA com o que o servidor
// mandou. O contrato dos dados (filtro no servidor e Tier 0) é provado em
// tests/Feature/Produto/ProdutoNaturezaFiscalConsultaContratoTest.php.
//
// Cobre UC-PIDX-31 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-natureza-fiscal-consulta.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState, type ReactNode } from 'react';
import { render, screen, cleanup, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import FiltrosListaProdutos from '@/Pages/Produto/_components/lista/FiltrosLista';
import { colunasDaLista } from '@/Pages/Produto/_components/lista/colunas';
import {
  chipsDosFiltros,
  type AbaDoDetalhe,
  type DetalheProdutoDados,
  type FiltrosLista,
  type LinhaProduto,
} from '@/Pages/Produto/_components/lista/tipos';

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

afterEach(() => cleanup());

describe('UC-PIDX-31 · natureza fiscal', () => {
  it('a linha marca serviço e "Fiscal a decidir"; mercadoria não ganha selo', () => {
    celula('produto', linha({ natureza_fiscal: 'pendente' }));
    expect(screen.getByText('Fiscal a decidir')).toBeTruthy();
    cleanup();
    celula('produto', linha({ natureza_fiscal: 'servico' }));
    expect(screen.getByText('Serviço')).toBeTruthy();
    cleanup();
    celula('produto', linha({ natureza_fiscal: 'mercadoria' }));
    expect(screen.queryByText('Serviço')).toBeNull();
    expect(screen.queryByText('Fiscal a decidir')).toBeNull();
  });

  it('a aba Fiscal mostra a natureza e, quando pendente, o aviso da emissão separada', () => {
    const { painel } = abrir(detalhe({ fiscal: { ...detalhe().fiscal, natureza: { natureza: 'pendente', codigo_servico: null } } }), 'fiscal');
    expect(painel.getByText('A decidir pelo contador')).toBeTruthy();
    expect(painel.getByText(/emissão\s+separada \(NF-e \+ NFS-e\) fica bloqueada/)).toBeTruthy();
    cleanup();
    const outro = abrir(detalhe({ fiscal: { ...detalhe().fiscal, natureza: { natureza: 'servico', codigo_servico: '24.01' } } }), 'fiscal');
    expect(outro.painel.getByText('Serviço (NFS-e) · item 24.01 da LC 116')).toBeTruthy();
  });

  it('o filtro só aparece com o módulo Fiscal, e o chip diz qual natureza', () => {
    const filtros = {
      type: null, category_id: null, brand_id: null, unit_id: null, tax_id: null, location_id: null, active_state: null,
      not_for_selling: false, woocommerce_enabled: false, repair_model_id: null, natureza_fiscal: 'pendente', q: '', sort: 'produto',
      dir: 'asc', per_page: 25, page: 1, aba: 'lista', estoque_pagina: 1, estoque_por_pagina: 25,
    } as FiltrosLista;
    const opcoes = { categorias: [], marcas: [], unidades: [], impostos: [], locais: [], modelos_dispositivo: null };
    render(<FiltrosListaProdutos filtros={filtros} opcoes={opcoes} woocommerce={false} aplicar={() => {}} />);
    expect(screen.queryByLabelText('Natureza fiscal')).toBeNull();
    cleanup();
    render(<FiltrosListaProdutos filtros={filtros} opcoes={opcoes} woocommerce={false} naturezaFiscal aplicar={() => {}} />);
    expect(screen.getByLabelText('Natureza fiscal')).toBeTruthy();
    expect(chipsDosFiltros(filtros, opcoes).map((c) => c.rotulo)).toContain('Natureza fiscal: A decidir pelo contador');
  });
});
