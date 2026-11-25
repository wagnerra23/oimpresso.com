// Ctrl+K / ⌘K da lista de produtos (PaletaProduto do protótipo `cowork/Felipe/produto-blade.jsx`):
// telas do módulo lidas do menu do shell, busca de produto e o atalho tomado da busca geral.
//
// O último bloco monta o AppShellV2 de verdade (com a paleta global trocada por um marcador) para
// provar a guarda `defaultPrevented` no próprio shell, e não numa cópia dela.
// Cobre UC-PIDX-51 (resources/js/Pages/Produto/Index.casos.md).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, act, waitFor } from '@testing-library/react';

const MENU = [
  { label: 'Vendas', icon: 'x', children: [{ label: 'Todas as vendas', icon: '', href: 'https://oimpresso.test/sells' }] },
  {
    label: 'Produtos',
    icon: 'x',
    children: [
      { label: 'Lista de produtos', icon: '', href: 'https://oimpresso.test/products' },
      { label: 'Adicionar produto', icon: '', href: 'https://oimpresso.test/products/create' },
      { label: 'Importar produtos', icon: '', href: 'https://oimpresso.test/import-products' },
    ],
  },
];

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: '/products', props: { shell: { menu: MENU, topnavs: {}, cockpit: {} }, auth: { user: null } } }),
  Head: () => null,
  Link: (p: { children?: unknown }) => p.children ?? null,
  router: { visit: vi.fn(), reload: vi.fn(), get: vi.fn(), on: vi.fn(() => () => {}) },
}));

import PaletaProduto from '@/Pages/Produto/_components/lista/PaletaProduto';

const ctrlK = () => fireEvent.keyDown(window, { key: 'k', ctrlKey: true });

beforeEach(() => {
  // jsdom não tem estes; o cmdk e o Radix usam.
  globalThis.ResizeObserver ??= class { observe() {} unobserve() {} disconnect() {} } as never;
  Element.prototype.scrollIntoView ??= () => {};
});
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('Paleta Ctrl+K da lista de produtos', () => {
  it('as telas são as do grupo do menu que contém /products, e só elas', async () => {
    render(<PaletaProduto irPara={vi.fn()} abrirProduto={vi.fn()} />);
    act(() => { ctrlK(); });
    const itens = await screen.findAllByRole('option');
    expect(itens.map((i) => i.textContent)).toEqual(['Lista de produtos', 'Adicionar produto', 'Importar produtos']);
    expect(screen.queryByText('Todas as vendas')).toBeNull();
  });

  it('Ctrl+K abre com as telas do módulo, e escolher uma navega para ela', async () => {
    const irPara = vi.fn();
    render(<PaletaProduto irPara={irPara} abrirProduto={vi.fn()} />);
    expect(screen.queryByText('Importar produtos')).toBeNull();
    act(() => { ctrlK(); });
    fireEvent.click(await screen.findByText('Importar produtos'));
    expect(irPara).toHaveBeenCalledWith('https://oimpresso.test/import-products');
  });

  it('o atalho chega à escuta de bolha já com defaultPrevented', () => {
    render(<PaletaProduto irPara={vi.fn()} abrirProduto={vi.fn()} />);
    let visto: boolean | null = null;
    const bolha = (e: KeyboardEvent) => { visto = e.defaultPrevented; };
    window.addEventListener('keydown', bolha);
    act(() => { ctrlK(); });
    window.removeEventListener('keydown', bolha);
    expect(visto).toBe(true);
  });

  it('busca o produto a partir de 2 letras e abre o escolhido', async () => {
    const f = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify([{ id: 42, name: 'Camiseta Azul', sku: 'CAM-01' }]), { status: 200 }),
    );
    const abrirProduto = vi.fn();
    render(<PaletaProduto irPara={vi.fn()} abrirProduto={abrirProduto} />);
    act(() => { ctrlK(); });
    const campo = await screen.findByPlaceholderText(/nome \/ SKU/);
    fireEvent.change(campo, { target: { value: 'c' } });
    await new Promise((r) => setTimeout(r, 300));
    expect(f).not.toHaveBeenCalled();
    fireEvent.change(campo, { target: { value: 'cam' } });
    fireEvent.click(await screen.findByText('Camiseta Azul', {}, { timeout: 2000 }));
    expect(String(f.mock.calls[0]?.[0])).toBe('/products/list-no-variation?term=cam');
    expect(abrirProduto).toHaveBeenCalledWith(42);
  });
});

describe('AppShellV2 cede o Ctrl+K a quem já tratou o evento', () => {
  it('sem a paleta da tela abre a busca geral; com ela, não', async () => {
    vi.doMock('@/Components/CommandPalette', () => ({
      default: ({ open }: { open: boolean }) => (open ? <div>BUSCA-GERAL</div> : null),
    }));
    const { default: AppShellV2 } = await import('@/Layouts/AppShellV2');

    const { unmount } = render(<AppShellV2>{null}</AppShellV2>);
    act(() => { ctrlK(); });
    expect(await screen.findByText('BUSCA-GERAL')).toBeTruthy();
    unmount();

    render(
      <AppShellV2>
        <PaletaProduto irPara={vi.fn()} abrirProduto={vi.fn()} />
      </AppShellV2>,
    );
    act(() => { ctrlK(); });
    await screen.findByText('Importar produtos');
    await waitFor(() => expect(screen.queryByText('BUSCA-GERAL')).toBeNull());
  });
});
