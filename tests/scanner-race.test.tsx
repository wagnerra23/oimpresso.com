// Scanner race test — Onda 4 / ADR 0211 (Frente 1 ação F1-C).
//
// Reproduz o bug R7 da Larissa de forma DETERMINÍSTICA — algo que o Pest PHP
// estrutural (tests/Feature/Sells/ProductSearchAutocompleteRaceTest.php) não
// consegue: simula scanner USB físico (13 chars digitados em <50ms + Enter) e
// verifica comportamento, não só presença de strings.
//
// MSW intercepta GET /products/list. vi.useFakeTimers controla o debounce de
// 250ms (DEBOUNCE_MS) de forma determinística. advanceTimersByTimeAsync flusha
// os microtasks do fetch entre os ticks.
//
// 3 cenários:
//   (a) 1 bipa  → onSelect chamado 1× (qty 1 no parent), dropdown fecha
//   (b) 2 bipas mesmo SKU → onSelect chamado 2× (parent faria qty 2)
//   (c) Enter duplo durante loading → onSelect NÃO duplica (guard `if (loading)`)

import { describe, it, expect, beforeAll, afterAll, afterEach, vi } from 'vitest';
import { render, screen, cleanup, act } from '@testing-library/react';
import { fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { setupServer } from 'msw/node';
import { http, HttpResponse } from 'msw';
import ProductSearchAutocomplete, {
  type ProductSearchResult,
} from '@/Pages/Sells/_components/ProductSearchAutocomplete';

const SKU = '7891234567890'; // 13 dígitos — código de barras EAN-13 típico

const PRODUCT: ProductSearchResult = {
  product_id: 42,
  variation_id: 99,
  name: 'Caneta Azul',
  type: 'single',
  variation: undefined,
  sku: SKU,
  sub_sku: SKU,
  selling_price: 2.5,
  qty_available: 100,
  unit: 'un',
};

// Quantas vezes /products/list foi chamado (sanity de dedup).
let requestCount = 0;
// Permite cada teste customizar a resposta / latência.
let respondWith: () => ProductSearchResult[] = () => [PRODUCT];
let responseDelayMs = 0;

const server = setupServer(
  http.get('/products/list', async () => {
    requestCount += 1;
    if (responseDelayMs > 0) {
      await new Promise((r) => setTimeout(r, responseDelayMs));
    }
    return HttpResponse.json(respondWith());
  }),
);

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => {
  server.resetHandlers();
  cleanup();
  requestCount = 0;
  respondWith = () => [PRODUCT];
  responseDelayMs = 0;
});
afterAll(() => server.close());

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

// Simula um scanner USB: injeta os chars do código de barras de uma vez (o
// scanner "digita" tudo em <50ms) e em seguida dispara Enter — tudo antes do
// debounce de 250ms expirar (results ainda vazio → cai no scanner sync path).
async function scanBarcode(input: HTMLInputElement, code: string) {
  await act(async () => {
    fireEvent.change(input, { target: { value: code } });
  });
}

async function pressEnter(input: HTMLInputElement) {
  // O handler do Enter é async (await fetchProductsNow). Flush dos microtasks
  // do fetch MSW via advanceTimersByTimeAsync(0).
  await act(async () => {
    fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' });
    await vi.advanceTimersByTimeAsync(0);
  });
}

describe('ProductSearchAutocomplete — scanner race (R7 raiz, ADR 0211)', () => {
  it('(a) 1 bipa → onSelect chamado 1× e dropdown fecha', async () => {
    vi.useFakeTimers();
    const { onSelect } = renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;

    await scanBarcode(input, SKU);
    // Enter chega ANTES do debounce de 250ms → scanner sync path.
    await pressEnter(input);

    expect(onSelect).toHaveBeenCalledTimes(1);
    expect(onSelect).toHaveBeenCalledWith(
      expect.objectContaining({ sku: SKU, variation_id: 99 }),
    );
    // Dropdown não deve estar aberto (seleção limpou query + fechou).
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  it('(b) 2 bipas do mesmo SKU → onSelect chamado 2× (parent faz qty 2)', async () => {
    vi.useFakeTimers();
    const { onSelect } = renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;

    // 1ª bipa
    await scanBarcode(input, SKU);
    await pressEnter(input);
    // Após seleção, query é limpa. Espera passar a janela POST_SELECT_GRACE_MS
    // (500ms) pra simular a operadora bipando o 2º item logo depois.
    await act(async () => {
      await vi.advanceTimersByTimeAsync(600);
    });

    // 2ª bipa do mesmo código
    await scanBarcode(input, SKU);
    await pressEnter(input);

    expect(onSelect).toHaveBeenCalledTimes(2);
    // Mesmo produto/variação nas duas chamadas.
    expect(onSelect.mock.calls[0]?.[0]).toEqual(
      expect.objectContaining({ sku: SKU }),
    );
    expect(onSelect.mock.calls[1]?.[0]).toEqual(
      expect.objectContaining({ sku: SKU }),
    );
  });

  it('(c) Enter duplo durante loading → onSelect NÃO duplica (guard if (loading))', async () => {
    vi.useFakeTimers();
    // Backend lento — mantém loading=true entre os dois Enter.
    responseDelayMs = 200;
    const { onSelect } = renderAutocomplete();
    const input = screen.getByLabelText('Buscar produto') as HTMLInputElement;

    await scanBarcode(input, SKU);

    // 1º Enter — dispara fetch sync (loading vira true durante o await).
    await act(async () => {
      fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' });
      // Avança o suficiente pra entrar no fetch mas NÃO concluir (delay 200ms).
      await vi.advanceTimersByTimeAsync(50);
    });

    // 2º Enter (operadora insistindo) enquanto o 1º ainda está em vôo.
    await act(async () => {
      fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' });
      await vi.advanceTimersByTimeAsync(0);
    });

    // Conclui o(s) fetch(es) pendente(s).
    await act(async () => {
      await vi.advanceTimersByTimeAsync(300);
    });

    // O guard `if (loading) return` deve ter bloqueado o 2º Enter → 1 seleção só.
    expect(onSelect).toHaveBeenCalledTimes(1);
  });
});
