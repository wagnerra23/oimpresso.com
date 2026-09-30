// Patrimonio/Manutencoes — nenhuma coluna da tabela pode ser espremida até zero.
//
// Mesmo defeito medido em produção na tela irmã de Bens (2026-09-30, cabeçalho "Bategoria"):
// sob `table-layout: fixed`, a coluna "Bem" era a "fluida", sem largura, com
// `minTableWidth={1280}` — mas as outras 9 colunas somavam 1332px, então sobrava ZERO pra ela.
// Aqui não chegou a aparecer em produção só porque o business medido não tinha manutenção.
//
// Mede a tela REAL (só a casca é mockada): o `<colgroup>` que o `DataTable` monta de
// `meta.width` e o `min-width` que ele põe na tabela.
//   1. toda `<col>` declara largura > 0;  2. o piso é a SOMA delas;
//   3. "Bem" tem os 250px do protótipo (`patrimonio-page.jsx:489`).
// Mordida: contra o `Manutencoes.tsx` de 2026-09-29 os três caem (piso 1280 ≠ soma 1332).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, cleanup } from '@testing-library/react';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: { get: vi.fn(), delete: vi.fn(), reload: vi.fn(), visit: vi.fn() },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Manutencoes from '@/Pages/Patrimonio/Manutencoes';

afterEach(() => cleanup());

const MANUTENCAO = {
  id: 12,
  codigo: 'MNT-0012',
  bem: 'Compressor de ar',
  bem_id: 7,
  status: 'in_progress',
  status_label: 'Em andamento',
  prioridade: 'high',
  prioridade_label: 'Alta',
  garantia: null,
  detalhes: null,
  nota: null,
  criado_em: '2026-09-01',
  criado_ha: 'há 3 semanas',
  atribuido_a: null,
  criado_por: null,
};

function renderizar() {
  render(
    <Manutencoes
      manutencoes={{ data: [MANUTENCAO], total: 1, current_page: 1, last_page: 1, from: 1, to: 1, links: [] } as any}
      filtros={{}}
      opcoes={{ status: {}, prioridades: {}, responsaveis: {} }}
      permissoes={{ vejo_todas: true }}
    />,
  );
  const tabela = document.querySelector('table') as HTMLTableElement | null;
  expect(tabela, 'a tabela de Manutenções renderizou').not.toBeNull();
  const larguras = [...tabela!.querySelectorAll('colgroup col')].map((c) => parseFloat((c as HTMLElement).style.width) || 0);
  const titulos = [...tabela!.querySelectorAll('thead th')].map((th) => th.textContent?.trim() ?? '');
  return { tabela: tabela!, larguras, titulos };
}

describe('Manutenções · geometria da tabela — nenhuma coluna espremida até zero', () => {
  it('toda coluna declara largura, uma por cabeçalho', () => {
    const { larguras, titulos } = renderizar();
    // Controle positivo: sem ele, uma tabela sem colgroup passaria o filtro sobre lista vazia.
    expect(larguras.length).toBe(titulos.length);
    expect(larguras.length).toBeGreaterThan(0);
    expect(titulos.filter((_, i) => !(larguras[i] > 0)), 'colunas sem largura declarada').toEqual([]);
  });

  it('o piso da tabela é a soma das larguras declaradas', () => {
    const { tabela, larguras } = renderizar();
    expect(parseFloat(tabela.style.minWidth)).toBe(larguras.reduce((s, w) => s + w, 0));
  });

  it('"Bem" tem os 250px do protótipo', () => {
    const { larguras, titulos } = renderizar();
    expect(larguras[titulos.indexOf('Bem')]).toBe(250);
  });
});
