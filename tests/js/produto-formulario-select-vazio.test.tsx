// Formulário de produto (Produto/Create e Produto/Edit) — lista do servidor com chave vazia.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// Em produção (medido 2026-10-16) o servidor manda `units['']` = "Selecionar" e
// `taxes['']` = "nenhum". O <SelectItem value=""> do Radix lança erro e as duas telas
// ficavam em branco. Mesma classe da lápide §5 2026-06-29 (opções data-driven sem filtrar
// o valor vazio).
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
//   - o formulário inteiro renderiza com as listas como chegam de produção;
//   - "Nenhum" continua escolhível no imposto e volta ao formulário como '' (o que a Blade envia);
//   - as demais listas descartam a chave vazia, que é só placeholder.
//
// Comando: npx vitest run tests/js/produto-formulario-select-vazio.test.tsx

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import { ProdutoFormulario } from '@/Pages/Produto/_components/formulario/ProdutoFormulario';
import {
  entradasDeOpcoes,
  IMPOSTO_NENHUM,
  impostoDoSelect,
  selectDoImposto,
} from '@/Pages/Produto/_components/formulario/opcoes';
import type { CamposProduto, OpcoesProduto } from '@/Pages/Produto/_components/formulario/tipos';

afterEach(cleanup);

// As listas no formato de produção: chave vazia junto das reais.
const OPCOES_DE_PRODUCAO = {
  units: { 1: 'Unidade (UNID)', 42: 'Metro (m)', '': 'Selecionar' },
  categories: { 9: 'Camisetas' },
  brands: { 3: 'Vinilcor' },
  taxes: { 1: 'ICMS 18%', 2: 'ISS 5%', '': 'nenhum' },
  barcodeTypes: { C128: 'Code 128 (C128)' },
  warranties: { 5: '12 meses' },
  productTypes: { single: 'Único', variable: 'Variável', combo: 'Composição' },
} as unknown as OpcoesProduto;

const CAMPOS: CamposProduto = {
  name: '', sku: '', unit_id: '', brand_id: '', category_id: '', tax: '', tax_type: 'exclusive',
  origem_mercadoria: 0, barcode_type: 'C128', alert_quantity: '', weight: '', product_description: '',
  warranty_id: '', product_custom_field1: '', product_custom_field2: '', type: 'single',
  enable_stock: 1, not_for_selling: 0, expiry_period: '',
};

function renderizar(modo: 'novo' | 'editar') {
  return render(
    <ProdutoFormulario
      id="f"
      onSubmit={() => {}}
      modo={modo}
      data={CAMPOS}
      definir={() => {}}
      errors={{}}
      opcoes={OPCOES_DE_PRODUCAO}
      tipoAtual="Único"
      chaveMaisOpcoes="teste.mais-opcoes"
    />,
  );
}

// Fechado, o Radix não monta os itens — é ABRIR o select que derrubava a tela. Por isso cada
// caso abre o select (mesmo idioma de tests/js/transaction-payment-edit.test.tsx).
function abrir(id: string) {
  const trigger = document.getElementById(id);
  if (!trigger) throw new Error(`select #${id} não renderizou`);
  fireEvent.keyDown(trigger, { key: 'Enter' });
}

const opcoesVisiveis = () => screen.queryAllByRole('option').map((o) => o.textContent?.trim());

describe('formulário de produto com lista do servidor contendo chave vazia', () => {
  it('Novo: abrir Unidade lista só as unidades reais', () => {
    renderizar('novo');
    abrir('unit_id');
    expect(opcoesVisiveis()).toEqual(['Unidade (UNID)', 'Metro (m)']);
  });

  it('Novo: abrir Imposto lista "Nenhum" e os impostos reais', () => {
    renderizar('novo');
    abrir('tax');
    expect(opcoesVisiveis()).toEqual(['Nenhum', 'ICMS 18%', 'ISS 5%']);
  });

  it('Editar: abrir Imposto lista "Nenhum" e os impostos reais', () => {
    renderizar('editar');
    abrir('tax');
    expect(opcoesVisiveis()).toEqual(['Nenhum', 'ICMS 18%', 'ISS 5%']);
  });

  it('as listas descartam a chave vazia', () => {
    expect(entradasDeOpcoes(OPCOES_DE_PRODUCAO.units).map(([k]) => k)).toEqual(['1', '42']);
    expect(entradasDeOpcoes(OPCOES_DE_PRODUCAO.taxes).map(([k]) => k)).toEqual(['1', '2']);
  });

  it('imposto "Nenhum" vai e volta como vazio; imposto real vira número', () => {
    expect(selectDoImposto('')).toBe(IMPOSTO_NENHUM);
    expect(selectDoImposto(null)).toBe(IMPOSTO_NENHUM);
    expect(selectDoImposto(2)).toBe('2');
    expect(impostoDoSelect(IMPOSTO_NENHUM)).toBe('');
    expect(impostoDoSelect('2')).toBe(2);
  });
});
