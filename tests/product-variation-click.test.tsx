// Regressão — clicar/tocar num tamanho no popover "Escolha o tamanho" adiciona
// a variação à venda (bug Larissa @ Rota Livre, 2026-06-18).
//
// Bug: o handler de "clique fora" (mousedown no document) tratava o mousedown num
// item do popover de variação como clique-fora — porque o Radix PopoverContent é
// renderizado num Portal em document.body, FORA do containerRef. Resultado:
// setOpen(false)+setExpandedProductId(null) disparavam no mousedown, o Popover
// desmontava, e o onClick do <button> da variação nunca rodava → onSelect não era
// chamado. A operadora só conseguia adicionar digitando o SKU (caminho de teclado,
// que não passa por clique no portal).
//
// Este teste reproduz o TOQUE real: mousedown (que borbulha pro document, onde o
// handler de clique-fora vive) seguido de click. SEM o guard, o mousedown fecha o
// popover e onSelect não é chamado; COM o guard, a variação é adicionada. O Pest
// estrutural (tests/Feature/Sells/) não exercita esse encadeamento mousedown→click
// dentro do portal.
//
// Mesma estratégia do product-keyboard-nav.test.tsx: timers reais + stub direto de
// `fetch` + queries assíncronas (findBy/waitFor).

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  render,
  screen,
  cleanup,
  fireEvent,
  waitFor,
} from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import ProductSearchAutocomplete, {
  type ProductSearchResult,
} from '@/Pages/Sells/_components/ProductSearchAutocomplete';

// Camiseta com 3 tamanhos (P/M/G) — o caso comum da Larissa (vestuário): produto
// type='variable' com ≥2 variações, que abre o popover "Escolha o tamanho".
const PRODUCTS: ProductSearchResult[] = [
  { product_id: 2, variation_id: 21, name: 'Camiseta', type: 'variable', variation: 'P', sku: 'CAM-P', selling_price: 50, qty_available: 3, unit: 'un' },
  { product_id: 2, variation_id: 22, name: 'Camiseta', type: 'variable', variation: 'M', sku: 'CAM-M', selling_price: 50, qty_available: 2, unit: 'un' },
  { product_id: 2, variation_id: 23, name: 'Camiseta', type: 'variable', variation: 'G', sku: 'CAM-G', selling_price: 50, qty_available: 1, unit: 'un' },
];

beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn(
      async () =>
        ({
          ok: true,
          json: async () => PRODUCTS,
        }) as unknown as Response,
    ),
  );
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

function renderAutocomplete() {
  const onSelect = vi.fn();
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0, staleTime: 0 } },
  });
  render(
    <QueryClientProvider client={queryClient}>
      <ProductSearchAutocomplete locationId={1} onSelect={onSelect} />
    </QueryClientProvider>,
  );
  return { onSelect };
}

describe('ProductSearchAutocomplete — seleção de variação por clique (regressão 2026-06-18)', () => {
  it('clicar (mousedown+click) num tamanho no popover adiciona a variação à venda', async () => {
    const { onSelect } = renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;

    // Abre o dropdown (debounce 250ms + fetch) com o grupo Camiseta multi-variação.
    fireEvent.change(input, { target: { value: 'cam' } });
    const options = await screen.findAllByRole('option', undefined, { timeout: 2000 });
    const grupo = options.find((o) => o.textContent?.includes('Camiseta'));
    expect(grupo).toBeTruthy();

    // Abre o popover de tamanhos (Radix Portal em document.body).
    fireEvent.click(grupo!);

    // O tamanho M aparece no popover.
    const mNome = await screen.findByText('M', { exact: true }, { timeout: 2000 });
    const mBotao = mNome.closest('button');
    expect(mBotao).toBeTruthy();

    // TOQUE real: mousedown borbulha pro document (handler de clique-fora) e o
    // click seleciona. É o mousedown que disparava o bug ao fechar o popover.
    fireEvent.mouseDown(mBotao!);
    fireEvent.click(mBotao!);

    await waitFor(() => expect(onSelect).toHaveBeenCalledTimes(1));
    expect(onSelect).toHaveBeenCalledWith(
      expect.objectContaining({ variation_id: 22, variation: 'M' }),
    );
  });
});
