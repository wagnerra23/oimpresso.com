// Nova transferência de estoque — os itens (busca de produto + linha + o que vai no salvar).
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// Até 2026-10-11 a tela React montava a linha como texto livre, sem `product_id`: o salvar não
// tinha o que movimentar. O componente passou a buscar o produto e a linha no servidor, com as
// regras da tela Blade (`public/js/stock_transfer.js`). Typecheck e build verdes não provam que
// a linha nasce certa nem que o envio sai no formato que o `num_uf` lê — isto prova.
//
// ── O QUE MEDE (componente REAL; só o `fetch` é simulado) ───────────────────
//   - a busca só abre com a filial de origem, e chama `/products/list` com o local;
//   - produto sem controle de estoque não aparece; sem saldo aparece desabilitado;
//   - a linha escolhida vem de `/stock-transfers/produto-linha` e nasce com o saldo e o custo;
//   - o mesmo produto não entra duas vezes;
//   - `paraEnvio`: os campos da linha da Blade, com vírgula decimal e sem milhar;
//   - `maximoDaLinha` / `linhaInvalida`: a quantidade não passa do saldo (nem do lote), na unidade.
//
// ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
// Nada do servidor: que o estoque sai da origem e entra no destino é do Pest
// (`tests/Feature/Estoque/EstoqueTransferenciaStoreTest.php`, lane Estoque · MySQL).
//
// ── A MORDIDA (provada por mutação em 2026-10-11, restauro conferido por hash) ──
//   M1. lista quem não controla estoque      → 1 failed | 9 passed
//   M2. preço sem `paraNumUf` (`String(n)`)  → 2 failed | 8 passed
//   M3. aceita o produto repetido            → 1 failed | 9 passed
//
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Cobre UC-TRCRT-06 (resources/js/Pages/StockTransfer/Create.casos.md).
//
// Comando: npx vitest run tests/js/transferencia-itens.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import { useState } from 'react';
import ItensDaTransferencia, {
  linhaInvalida,
  maximoDaLinha,
  paraEnvio,
  type LinhaTransferencia,
} from '@/Pages/StockTransfer/_components/ItensDaTransferencia';

const dadosDaLinha = {
  product_id: 7,
  variation_id: 70,
  nome: 'Camiseta básica',
  sub_sku: 'CAM-001',
  enable_stock: 1,
  qty_available: 10,
  unidade: 'Un',
  unit_id: 3,
  permite_decimal: false,
  custo: 8.3333,
  sub_unidades: [{ id: 3, nome: 'Un', multiplicador: 1, permite_decimal: false }],
  usa_lote: false,
  usa_validade: false,
  lotes: [],
};

const linha = (parte: Partial<LinhaTransferencia> = {}): LinhaTransferencia => ({
  ...dadosDaLinha,
  chave: 'k',
  sub_unit_id: 3,
  lot_no_line_id: null,
  quantity: 3,
  unit_price: 12.5,
  ...parte,
});

const resposta = (corpo: unknown, ok = true) => Promise.resolve({ ok, json: () => Promise.resolve(corpo) } as Response);

/** Simula as duas rotas que o componente chama e guarda as URLs pedidas. */
function simularServidor(busca: unknown[], linhaDoProduto: unknown = dadosDaLinha) {
  const pedidos: string[] = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string) => {
      pedidos.push(url);
      if (url.startsWith('/products/list')) return resposta(busca);
      if (url.startsWith('/stock-transfers/produto-linha')) return resposta(linhaDoProduto);
      return resposta({}, false);
    }),
  );
  return pedidos;
}

function Tela({ origemId = '5' }: { origemId?: string }) {
  const [linhas, setLinhas] = useState<LinhaTransferencia[]>([]);
  return (
    <>
      <ItensDaTransferencia origemId={origemId} linhas={linhas} aoMudar={setLinhas} verCusto editarPreco />
      <output data-testid="envio">{JSON.stringify(linhas.map(paraEnvio))}</output>
    </>
  );
}

const campoDeBusca = () => screen.getByLabelText('Buscar produto para transferir') as HTMLInputElement;
const digitar = (texto: string) => fireEvent.change(campoDeBusca(), { target: { value: texto } });

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('UC-TRCRT-06 · busca de produto', () => {
  it('fica fechada sem a filial de origem', () => {
    simularServidor([]);
    render(<Tela origemId="" />);
    expect(campoDeBusca().disabled).toBe(true);
    expect(campoDeBusca().placeholder).toBe('Escolha a filial de origem primeiro');
  });

  it('procura no local de origem, esconde quem não controla estoque e desabilita quem não tem saldo', async () => {
    const pedidos = simularServidor([
      { product_id: 7, name: 'Camiseta básica', type: 'single', enable_stock: 1, variation_id: 70, variation: 'DUMMY', qty_available: '10.0000', sub_sku: 'CAM-001' },
      { product_id: 8, name: 'Camiseta polo', type: 'variable', enable_stock: 1, variation_id: 80, variation: 'P', qty_available: '0.0000', sub_sku: 'POL-P' },
      { product_id: 9, name: 'Camiseta serviço', type: 'single', enable_stock: 0, variation_id: 90, variation: 'DUMMY', qty_available: null, sub_sku: 'SRV-1' },
    ]);
    render(<Tela />);
    digitar('cami');

    await screen.findByText('Camiseta básica (CAM-001)');
    expect(pedidos[0]).toBe('/products/list?term=cami&location_id=5');
    // Variável mostra a variação, como na Blade; sem saldo fica desabilitado.
    const semSaldo = screen.getByText('Camiseta polo-P (POL-P)').closest('[cmdk-item]');
    expect(semSaldo?.getAttribute('aria-disabled')).toBe('true');
    expect(screen.getByText('sem estoque')).toBeTruthy();
    // Sem controle de estoque não entra na lista.
    expect(screen.queryByText(/Camiseta serviço/)).toBeNull();
  });

  it('um único resultado com saldo entra direto como linha, com o saldo e o custo do servidor', async () => {
    const pedidos = simularServidor([
      { product_id: 7, name: 'Camiseta básica', type: 'single', enable_stock: 1, variation_id: 70, variation: 'DUMMY', qty_available: '10.0000', sub_sku: 'CAM-001' },
    ]);
    render(<Tela />);
    digitar('CAM-001');

    await screen.findByText('Camiseta básica');
    expect(pedidos).toContain('/stock-transfers/produto-linha?variation_id=70&location_id=5');
    expect(screen.getByText(/Estoque atual: 10 Un/)).toBeTruthy();

    // A linha nasce com quantidade 1 e o último custo em 2 casas (8,3333 → 8,33), como o campo da Blade.
    await waitFor(() =>
      expect(JSON.parse(screen.getByTestId('envio').textContent ?? '[]')).toEqual([
        { product_id: 7, variation_id: 70, enable_stock: 1, quantity: '1', base_unit_multiplier: 1, product_unit_id: 3, unit_price: '8,33', sub_unit_id: 3 },
      ]),
    );
  });

  it('o mesmo produto não entra duas vezes', async () => {
    simularServidor([
      { product_id: 7, name: 'Camiseta básica', type: 'single', enable_stock: 1, variation_id: 70, variation: 'DUMMY', qty_available: '10.0000', sub_sku: 'CAM-001' },
    ]);
    render(<Tela />);
    digitar('CAM-001');
    await screen.findByText('Camiseta básica');

    digitar('CAM-001');
    await screen.findByText('Este produto já está na transferência. Ajuste a quantidade na linha dele.');
    expect(JSON.parse(screen.getByTestId('envio').textContent ?? '[]')).toHaveLength(1);
  });
});

describe('UC-TRCRT-06 · o que vai no salvar (paraEnvio)', () => {
  it('manda os campos da linha da Blade, com vírgula decimal e sem milhar', () => {
    expect(paraEnvio(linha({ quantity: 3, unit_price: 12.5 }))).toEqual({
      product_id: 7, variation_id: 70, enable_stock: 1, quantity: '3', base_unit_multiplier: 1, product_unit_id: 3, unit_price: '12,5', sub_unit_id: 3,
    });
    // Milhar não leva ponto: "1500", nunca "1.500" (o num_uf leria 1,5).
    expect(paraEnvio(linha({ unit_price: 1500 })).unit_price).toBe('1500');
    expect(paraEnvio(linha({ unit_price: 1234.56 })).unit_price).toBe('1234,56');
  });

  it('só manda o lote quando um lote foi escolhido', () => {
    expect(paraEnvio(linha())).not.toHaveProperty('lot_no_line_id');
    expect(paraEnvio(linha({ lot_no_line_id: 44 })).lot_no_line_id).toBe(44);
  });

  it('com subunidade manda o multiplicador dela', () => {
    const caixa = linha({
      sub_unidades: [
        { id: 3, nome: 'Un', multiplicador: 1, permite_decimal: false },
        { id: 4, nome: 'Cx', multiplicador: 12, permite_decimal: false },
      ],
      sub_unit_id: 4,
    });
    expect(paraEnvio(caixa).base_unit_multiplier).toBe(12);
    expect(paraEnvio(caixa).sub_unit_id).toBe(4);
  });
});

describe('UC-TRCRT-06 · limite da quantidade', () => {
  it('é o saldo do local de origem', () => {
    expect(maximoDaLinha(linha())).toBe(10);
    expect(linhaInvalida(linha({ quantity: 10 }))).toBe(false);
    expect(linhaInvalida(linha({ quantity: 11 }))).toBe(true);
    expect(linhaInvalida(linha({ quantity: 0 }))).toBe(true);
  });

  it('na subunidade o saldo é dividido pelo multiplicador (10 un = 0,83 cx)', () => {
    const caixa = linha({
      sub_unidades: [
        { id: 3, nome: 'Un', multiplicador: 1, permite_decimal: false },
        { id: 4, nome: 'Cx', multiplicador: 12, permite_decimal: true },
      ],
      sub_unit_id: 4,
      quantity: 1,
    });
    expect(maximoDaLinha(caixa)).toBeCloseTo(10 / 12, 6);
    expect(linhaInvalida(caixa)).toBe(true);
  });

  it('com lote escolhido o limite é o saldo do lote', () => {
    const comLote = linha({
      lotes: [{ purchase_line_id: 44, lot_number: 'L1', exp_date: null, qty_available: 4 }],
      lot_no_line_id: 44,
      quantity: 5,
    });
    expect(maximoDaLinha(comLote)).toBe(4);
    expect(linhaInvalida(comLote)).toBe(true);
  });
});
