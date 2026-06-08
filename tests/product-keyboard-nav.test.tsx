// Keyboard navigation test — G3 (2026-05-31, tela-venda-arte gap P0).
//
// Larissa @ Rota Livre opera teclado + scanner; antes do G3 o dropdown de
// produto só respondia a Enter (match exato/scanner), forçando o mouse pra
// escolher entre vários resultados ou pra abrir o popover de tamanhos. Este
// teste exercita a navegação por seta que o G3 adicionou — comportamento que o
// Pest estrutural (tests/Feature/Sells/) não consegue verificar.
//
// Estratégia: TIMERS REAIS + stub direto de `fetch` + queries assíncronas
// (findBy/waitFor). Diferente do scanner-race.test.tsx (fake timers + MSW pra
// controlar o race), aqui o caminho exercitado é o do debounce/useQuery
// populando o dropdown — fake timers + MSW + react-query nesse encadeamento é
// instável (o commit do react-query não settla determinística­mente sob
// advanceTimersByTimeAsync). findBy espera o async naturalmente; o fetch stub
// resolve na hora e o debounce de 250ms passa em tempo real.
//
// 3 cenários (o gap pedia exatamente estes):
//   1) ↓ destaca o 1º item do dropdown (aria-selected)
//   2) Enter no item destacado (variação única) seleciona o produto
//   3) Enter num grupo multi-variação abre o popover e ↓+Enter escolhe o tamanho

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

// Catálogo espelhando a Larissa (vestuário): 1 item de variação única (boné) +
// 1 item multi-variação com 3 tamanhos (camiseta P/M/G — o caso comum dela).
const PRODUCTS: ProductSearchResult[] = [
  {
    product_id: 1,
    variation_id: 11,
    name: 'Boné Liso',
    type: 'single',
    sku: 'BONE-1',
    selling_price: 30,
    qty_available: 5,
    unit: 'un',
  },
  {
    product_id: 2,
    variation_id: 21,
    name: 'Camiseta',
    type: 'variable',
    variation: 'P',
    sku: 'CAM-P',
    selling_price: 50,
    qty_available: 3,
    unit: 'un',
  },
  {
    product_id: 2,
    variation_id: 22,
    name: 'Camiseta',
    type: 'variable',
    variation: 'M',
    sku: 'CAM-M',
    selling_price: 50,
    qty_available: 2,
    unit: 'un',
  },
  {
    product_id: 2,
    variation_id: 23,
    name: 'Camiseta',
    type: 'variable',
    variation: 'G',
    sku: 'CAM-G',
    selling_price: 50,
    qty_available: 1,
    unit: 'un',
  },
];

beforeEach(() => {
  // Stub direto de fetch — resolve na hora (sem MSW). react-query consome o array.
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

// Digita um termo e espera o dropdown popular (debounce 250ms + fetch).
async function typeAndOpen(input: HTMLInputElement, term: string) {
  fireEvent.change(input, { target: { value: term } });
  // findAllByRole aguarda (até 2s) o debounce + react-query abrirem o dropdown.
  return screen.findAllByRole('option', undefined, { timeout: 2000 });
}

describe('ProductSearchAutocomplete — navegação por teclado (G3)', () => {
  it('1) seta ↓ destaca o primeiro item do dropdown', async () => {
    renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;
    const before = await typeAndOpen(input, 'ca');

    // Grupos na ordem do payload: [Boné (single), Camiseta (multi)].
    expect(before.length).toBeGreaterThanOrEqual(2);
    expect(before[0]?.getAttribute('aria-selected')).toBe('false');

    fireEvent.keyDown(input, { key: 'ArrowDown' });

    const after = screen.getAllByRole('option');
    expect(after[0]?.getAttribute('aria-selected')).toBe('true');
    expect(after[1]?.getAttribute('aria-selected')).toBe('false');
  });

  it('2) Enter no item destacado (variação única) seleciona o produto e fecha o dropdown', async () => {
    const { onSelect } = renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;
    await typeAndOpen(input, 'ca');

    fireEvent.keyDown(input, { key: 'ArrowDown' }); // destaca Boné (índice 0)
    fireEvent.keyDown(input, { key: 'Enter' });

    await waitFor(() => expect(onSelect).toHaveBeenCalledTimes(1));
    expect(onSelect).toHaveBeenCalledWith(
      expect.objectContaining({ product_id: 1, variation_id: 11 }),
    );
    // Seleção limpa a query e fecha o dropdown.
    await waitFor(() => expect(screen.queryByRole('listbox')).toBeNull());
  });

  it('3) Enter num grupo multi-variação abre o popover; ↓+Enter escolhe o tamanho por teclado', async () => {
    const { onSelect } = renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;
    await typeAndOpen(input, 'ca');

    // Navega até a Camiseta (índice 1, multi-variação).
    fireEvent.keyDown(input, { key: 'ArrowDown' }); // 0 = Boné
    fireEvent.keyDown(input, { key: 'ArrowDown' }); // 1 = Camiseta

    // Enter abre o popover de tamanhos — NÃO seleciona ainda.
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(onSelect).not.toHaveBeenCalled();

    // As variações (P, M, G) aparecem no popover Radix (portal — espera 1 tick).
    await screen.findByText('M', undefined, { timeout: 2000 });

    // ↓ destaca a 2ª variação (M) e Enter seleciona.
    fireEvent.keyDown(input, { key: 'ArrowDown' }); // variação 0 (P) -> 1 (M)
    fireEvent.keyDown(input, { key: 'Enter' });

    await waitFor(() => expect(onSelect).toHaveBeenCalledTimes(1));
    expect(onSelect).toHaveBeenCalledWith(
      expect.objectContaining({ variation_id: 22, variation: 'M' }),
    );
  });
});
