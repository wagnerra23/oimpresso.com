// UC-BENS-12 · Patrimônio/Bens — "Enviar pra manutenção" pela linha do bem.
//
// O QUE MEDE: com `permissoes.manutencao`, a linha oferece o botão, e ele navega (router.get,
// não `<a href>`) para `/asset/asset-maintenance/create` com o `asset_id` DAQUELE bem — o
// destino que a thread 19 transformou em drawer. Sem a permissão, o botão não aparece: é a
// mesma que o `create()` de manutenção exige, então a tela não promete o que o servidor nega.
//
// CONTROLE: o "Editar" da mesma linha continua lá nos dois casos — o sumiço do botão sem
// permissão não pode passar por uma linha que nem renderizou.

import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const get = vi.fn();
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: { get: (...a: unknown[]) => get(...a), delete: vi.fn(), reload: vi.fn(), visit: vi.fn(), post: vi.fn() },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Bens from '@/Pages/Patrimonio/Bens';

const bem = {
  id: 7,
  asset_code: 'AST-0007',
  nome: 'Furadeira de bancada',
  modelo: 'FB-13',
  categoria: 'Ferramentas',
  local: 'Matriz',
  quantidade: 3,
  alocado: 1,
  alocavel: true,
  valor_unitario: 100,
  compra_em: '2026-01-10',
  garantia: null,
  em_manutencao: 0,
  imagem_url: null,
};

const renderizar = (manutencao: boolean) =>
  render(
    <Bens
      bens={{ data: [bem], total: 1, current_page: 1, last_page: 1, from: 1, to: 1, links: [] } as any}
      filtros={{}}
      opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
      formato_data="d/m/Y"
      permissoes={{ criar: false, editar: true, excluir: false, manutencao }}
    />,
  );

const TITULO = 'button[title="Enviar pra manutenção — Furadeira de bancada"]';

beforeEach(() => get.mockReset());
afterEach(() => cleanup());

describe('UC-BENS-12 · enviar pra manutenção pela linha do bem', () => {
  it('UC-BENS-12: com a permissão de manutenção, o botão navega para o create com o asset_id do bem', () => {
    renderizar(true);

    expect(document.querySelector('button[title="Editar — Furadeira de bancada"]')).not.toBeNull();
    const botao = document.querySelector<HTMLButtonElement>(TITULO);
    expect(botao).not.toBeNull();

    fireEvent.click(botao!);

    expect(get).toHaveBeenCalledWith('/asset/asset-maintenance/create', { asset_id: 7 });
    // Nenhum `<a href>` para o create: UC-BENS-04 continua valendo.
    const hrefs = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href') ?? '');
    expect(hrefs.some((h) => h.includes('/asset/asset-maintenance/create'))).toBe(false);
  });

  it('UC-BENS-12: sem a permissão de manutenção, o botão não aparece (e a linha renderizou)', () => {
    renderizar(false);

    expect(document.querySelector('button[title="Editar — Furadeira de bancada"]')).not.toBeNull();
    expect(document.querySelector(TITULO)).toBeNull();
  });
});
