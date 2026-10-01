// UC-S04 (resources/js/Pages/Sells/Create.casos.md) — PDV React: produto entra pelo preço
// do GRUPO quando a venda tem grupo de preço.
//
// Defeito (2026-10-01): o ProductSearchAutocomplete chamava `/products/list` sem
// `price_group`, então o produto adicionado entrava pelo preço base mesmo com grupo
// escolhido. Só a TROCA de grupo reprecificava (e só as linhas já no carrinho). O Blade
// (`pos.js` → getProductRow) aplica o grupo ao adicionar.
//
// Prova 1 de 2 (REGRA MESTRE — dupla confirmação): o caminho do FRONT. O MSW faz o papel
// do backend e devolve a linha no formato do ProductUtil::filterProduct, com os números da
// fórmula. A prova 2 é o backend real: tests/Feature/Sells/BuscaProdutoPrecoDeGrupoContratoTest.php
// (filterProduct × getVariationGroupPrice, dois caminhos independentes no MySQL).

import { describe, it, expect, beforeAll, afterAll, afterEach, vi } from 'vitest';
import { render, screen, cleanup, act, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { setupServer } from 'msw/node';
import { http, HttpResponse } from 'msw';
import ProductSearchAutocomplete from '@/Pages/Sells/_components/ProductSearchAutocomplete';
import { precoDaBusca } from '@/Pages/Sells/_components/precoDaBusca';

const SKU = '7890000000017';
const BASE = 50; // variations.sell_price_inc_tax
const GRUPO = 6;
// Grupo percentual de 90 → 90% de 50 = 45 (fórmula do filterProduct:
// percentage → price_inc_tax * sell_price_inc_tax / 100). O MySQL devolve decimal como string.
const PRECO_GRUPO = '45.0000';

let urls: URL[] = [];

const server = setupServer(
  http.get('/products/list', ({ request }) => {
    const url = new URL(request.url);
    urls.push(url);
    const comGrupo = url.searchParams.has('price_group');
    return HttpResponse.json([
      {
        product_id: 7,
        variation_id: 70,
        name: 'Blusa',
        type: 'single',
        sku: SKU,
        sub_sku: SKU,
        selling_price: BASE,
        // filterProduct só seleciona a coluna quando recebe price_group.
        ...(comGrupo ? { variation_group_price: PRECO_GRUPO } : {}),
      },
    ]);
  }),
);

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => {
  server.resetHandlers();
  cleanup();
  urls = [];
  vi.useRealTimers();
});
afterAll(() => server.close());

async function adicionarPorScanner(priceGroupId: number | null) {
  vi.useFakeTimers();
  const onSelect = vi.fn();
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0, staleTime: 0 } },
  });
  render(
    <QueryClientProvider client={queryClient}>
      <ProductSearchAutocomplete locationId={4} priceGroupId={priceGroupId} onSelect={onSelect} />
    </QueryClientProvider>,
  );
  const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;
  await act(async () => {
    fireEvent.change(input, { target: { value: SKU } });
  });
  await act(async () => {
    fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' });
    await vi.advanceTimersByTimeAsync(0);
  });
  expect(onSelect).toHaveBeenCalledTimes(1);
  return onSelect.mock.calls[0]![0];
}

describe('UC-S04 · PDV React — preço do grupo ao adicionar produto', () => {
  it('com grupo: a busca leva price_group e a linha entra pelo preço do grupo (45, não 50)', async () => {
    const produto = await adicionarPorScanner(GRUPO);

    expect(urls.length).toBeGreaterThan(0);
    for (const u of urls) expect(u.searchParams.get('price_group')).toBe(String(GRUPO));
    expect(precoDaBusca(produto)).toBe(45);
  });

  it('sem grupo: a busca NÃO leva price_group e a linha entra pelo preço base (50)', async () => {
    const produto = await adicionarPorScanner(null);

    expect(urls.length).toBeGreaterThan(0);
    for (const u of urls) expect(u.searchParams.has('price_group')).toBe(false);
    expect(precoDaBusca(produto)).toBe(BASE);
  });
});

describe('precoDaBusca — regra única do Create.tsx', () => {
  it('preço de grupo presente vence o base', () => {
    expect(precoDaBusca({ selling_price: 50, variation_group_price: '45.0000' })).toBe(45);
  });
  it('variação sem preço no grupo (LEFT JOIN → null) cai no preço base', () => {
    expect(precoDaBusca({ selling_price: 50, variation_group_price: null })).toBe(50);
  });
  it('sem nenhum preço usa o fallback (troca de grupo mantém o preço atual da linha)', () => {
    expect(precoDaBusca({}, 33)).toBe(33);
  });
  it('preço de grupo 0 é preço válido (diferença conhecida com o Blade — ver precoDaBusca.ts)', () => {
    expect(precoDaBusca({ selling_price: 50, variation_group_price: 0 })).toBe(0);
  });
});
