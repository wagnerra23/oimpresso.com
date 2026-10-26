// Consulta de produto (Produto/Index): método de custo do insumo (UC-PIDX-32) — o que a tela DESENHA com o que o servidor
// mandou. O contrato dos dados (quando o método vale) é provado em
// tests/Feature/Produto/ProdutoMetodoDeCustoContratoTest.php.
//
// Cobre UC-PIDX-32 (resources/js/Pages/Produto/Index.casos.md).
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-metodo-custo.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, within } from '@testing-library/react';
import PainelDetalheProduto from '@/Pages/Produto/_components/lista/PainelDetalhe';
import { type AbaDoDetalhe, type DetalheProdutoDados } from '@/Pages/Produto/_components/lista/tipos';

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

afterEach(() => cleanup());

describe('UC-PIDX-32 · método de custo do insumo', () => {
  it('com a regra gravada, a aba Variações mostra o método e a data da última compra', () => {
    const v = { ...detalhe().variacoes[0]!, custo_metodo: { metodo: 'medio' as const, ultima_compra_em: '2026-10-12' } };
    const { painel } = abrir(detalhe({ variacoes: [v] }), 'variacoes');
    expect(painel.getByRole('columnheader', { name: 'Método do custo' })).toBeTruthy();
    expect(painel.getByText('custo médio · 12/10/2026')).toBeTruthy();
  });

  it('sem a regra (o servidor não manda o campo), a coluna não existe', () => {
    const { painel } = abrir(detalhe(), 'variacoes');
    expect(painel.queryByRole('columnheader', { name: 'Método do custo' })).toBeNull();
  });
});
