// Patrimonio/Bens — nenhuma coluna da tabela pode ser espremida até zero.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Medido em produção em 2026-09-30: sob `table-layout: fixed`, a coluna "Bem" ficava SEM
// largura (era a "fluida") com `minTableWidth={1280}`, mas as outras 11 colunas já somavam
// 1376px. Não sobrava nada: o `th` de "Bem" renderizava com 0px e o texto transbordava sobre o
// vizinho — o cabeçalho lia "Bategoria". tsc, eslint e build verdes; o defeito só existe na
// conta de larguras, e é ela que este teste faz.
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
// A tela REAL (só a casca é mockada, como nos irmãos). Lê o `<colgroup>` que o `DataTable`
// monta a partir de `meta.width`, e o `min-width` que ele põe na tabela:
//   1. toda `<col>` declara largura > 0 — sem coluna à mercê da sobra;
//   2. o piso da tabela é a SOMA delas — nenhuma largura declarada é espremida;
//   3. "Bem" tem os 250px do protótipo (`patrimonio-page.jsx:288`).
//
// ── A MORDIDA (provada, não afirmada) ────────────────────────────────────────
// Com o `Bens.tsx` de 2026-09-29 (sem `meta.width` em `nome` + `minTableWidth={1280}`), os
// casos 1 e 3 caem, e o caso 2 cai com piso 1280 ≠ soma 1376.

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

import Bens from '@/Pages/Patrimonio/Bens';

afterEach(() => cleanup());

const BEM = {
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

function renderizar() {
  render(
    <Bens
      bens={{ data: [BEM], total: 1, current_page: 1, last_page: 1, from: 1, to: 1, links: [] } as any}
      filtros={{}}
      opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
      formato_data="d/m/Y"
      permissoes={{ criar: false, editar: true, excluir: true, manutencao: true }}
    />,
  );
  const tabela = document.querySelector('[data-contract="tabela"] table') as HTMLTableElement | null;
  expect(tabela, 'a tabela de Bens renderizou').not.toBeNull();
  const larguras = [...tabela!.querySelectorAll('colgroup col')].map((c) => parseFloat((c as HTMLElement).style.width) || 0);
  const titulos = [...tabela!.querySelectorAll('thead th')].map((th) => th.textContent?.trim() ?? '');
  return { tabela: tabela!, larguras, titulos };
}

describe('Bens · geometria da tabela — nenhuma coluna espremida até zero', () => {
  it('toda coluna declara largura, uma por cabeçalho', () => {
    const { larguras, titulos } = renderizar();
    // Controle positivo: sem ele, uma tabela sem colgroup passaria o `every` sobre lista vazia.
    expect(larguras.length).toBe(titulos.length);
    expect(larguras.length).toBeGreaterThan(0);
    const semLargura = titulos.filter((_, i) => !(larguras[i] > 0));
    expect(semLargura, 'colunas sem largura declarada').toEqual([]);
  });

  it('o piso da tabela é a soma das larguras declaradas', () => {
    const { tabela, larguras } = renderizar();
    const soma = larguras.reduce((s, w) => s + w, 0);
    expect(parseFloat(tabela.style.minWidth)).toBe(soma);
  });

  it('"Bem" tem os 250px do protótipo', () => {
    const { larguras, titulos } = renderizar();
    expect(larguras[titulos.indexOf('Bem')]).toBe(250);
  });
});
