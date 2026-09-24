// UC-BENS-04 / UC-MANU-04 / UC-PAT-09 · Patrimônio — nenhum link leva a página em branco.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Medido em produção (biz=1, 2026-09-23): `AssetController::{create,edit}`,
// `AssetAllocationController::create` e `AssetMaitenanceController::{create,edit}` só
// respondem dentro de `if (request()->ajax())`. Numa navegação direta devolvem 200 com
// **0 bytes** — as views são fragmentos de modal jQuery da lista Blade que não existe mais.
// Até esta data, Bens, Painel e Manutenções tinham 7 `<a href>` pra esses endpoints
// (e o menu tinha um 8º, o primary "+ Novo ativo" — defendido no Pest
// `MenuGhostsContratoTest`, porque ele nasce no `DataController`, não no `.tsx`).
//
// ── O QUE ELE MEDE ───────────────────────────────────────────────────────────
// A tela REAL renderizada com uma linha e TODAS as permissões ligadas — o cenário em que
// os links apareciam. Varre todo `a[href]` do DOM contra o padrão dos endpoints só-ajax.
// O `PatrimonioSubNav` é mockado (o primary do menu é o Pest que mede).
//
// ── CONTROLE POSITIVO ────────────────────────────────────────────────────────
// Um `not->toContain` sozinho passaria se a tabela nem renderizasse. Cada caso asserta
// também que a LINHA chegou (o nome do bem/manutenção está no DOM) e que o EXCLUIR, que
// funciona (`router.delete`), continua lá — a retirada foi cirúrgica, não um apagão.
//
// ── A MORDIDA ────────────────────────────────────────────────────────────────
// No commit anterior ao conserto, os 3 casos de "sem link" caem (Bens: 5 hrefs; Painel: 1;
// Manutenções: 1). O recibo vai no corpo do PR.
//
// @see resources/js/Pages/Patrimonio/Bens.casos.md (UC-BENS-04)
// @see resources/js/Pages/Patrimonio/Manutencoes.casos.md (UC-MANU-04)
// @see resources/js/Pages/Patrimonio/Index.casos.md (UC-PAT-09)

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: { get: vi.fn(), delete: vi.fn(), reload: vi.fn(), visit: vi.fn() },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Bens from '@/Pages/Patrimonio/Bens';
import Manutencoes from '@/Pages/Patrimonio/Manutencoes';
import PainelPatrimonio from '@/Pages/Patrimonio/Index';

/** Os endpoints que só respondem sob `ajax()` — create e edit dos três controllers. */
const SO_AJAX = /\/asset\/(assets|allocation|asset-maintenance)\/(create|\d+\/edit)(\?|$)/;

const hrefsSoAjax = () =>
  [...document.querySelectorAll<HTMLAnchorElement>('a[href]')]
    .map((a) => a.getAttribute('href') ?? '')
    .filter((h) => SO_AJAX.test(h));

afterEach(() => cleanup());

const paginator = <T,>(data: T[]) => ({
  data,
  total: data.length,
  current_page: 1,
  last_page: 1,
  from: 1,
  to: data.length,
  links: [],
});

describe('UC-BENS-04 · Bens não linka pra formulário que só existe como modal', () => {
  it('UC-BENS-04: com uma linha e todas as permissões, nenhum href aponta pra create/edit só-ajax', () => {
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
    render(
      <Bens
        bens={paginator([bem]) as any}
        filtros={{}}
        opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
        permissoes={{ criar: true, editar: true, excluir: true, manutencao: true }}
      />,
    );

    expect(screen.getAllByText('Furadeira de bancada').length).toBeGreaterThan(0);
    expect(document.querySelector('button[title="Excluir — Furadeira de bancada"]')).not.toBeNull();
    expect(hrefsSoAjax()).toEqual([]);
  });

  it('UC-BENS-04: o estado vazio também não oferece "Cadastrar o primeiro bem" pra página em branco', () => {
    render(
      <Bens
        bens={paginator([]) as any}
        filtros={{}}
        opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
        permissoes={{ criar: true, editar: true, excluir: true, manutencao: true }}
      />,
    );

    expect(screen.getByText('Nenhum bem cadastrado ainda')).toBeTruthy();
    expect(hrefsSoAjax()).toEqual([]);
  });

  it('UC-BENS-05: "Adicionar o primeiro recurso" e "Adicionar recurso" abrem o drawer de cadastro na própria tela', () => {
    render(
      <Bens
        bens={paginator([]) as any}
        filtros={{}}
        opcoes={{ locais: { 3: 'Matriz' }, categorias: { 7: 'Máquinas' }, tipos_compra: { owned: 'Próprio' } }}
        formato_data="d/m/Y"
        permissoes={{ criar: true, editar: true, excluir: true, manutencao: true }}
      />,
    );

    expect(screen.queryByRole('button', { name: 'Cadastrar bem' })).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Adicionar o primeiro recurso' }));
    expect(screen.getByRole('button', { name: 'Cadastrar bem' })).toBeTruthy();
    expect(screen.getByRole('dialog').textContent).toContain('Código gerado pelo prefixo do módulo');
  });

  it('UC-BENS-05: sem permissão de criar, nenhum botão de cadastro aparece', () => {
    render(
      <Bens
        bens={paginator([]) as any}
        filtros={{}}
        opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
        formato_data="d/m/Y"
        permissoes={{ criar: false, editar: false, excluir: false, manutencao: false }}
      />,
    );

    expect(screen.queryByRole('button', { name: 'Adicionar recurso' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Adicionar o primeiro recurso' })).toBeNull();
  });
});

describe('UC-MANU-04 · Manutenções não linka pro editar que só existe como modal', () => {
  it('UC-MANU-04: com uma linha, nenhum href aponta pra edit só-ajax — e o excluir fica', () => {
    const manutencao = {
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
    render(
      <Manutencoes
        manutencoes={paginator([manutencao]) as any}
        filtros={{}}
        opcoes={{ status: {}, prioridades: {}, responsaveis: {} }}
        permissoes={{ vejo_todas: true }}
      />,
    );

    expect(screen.getAllByText('Compressor de ar').length).toBeGreaterThan(0);
    expect(document.querySelector('button[title="Excluir manutenção — Compressor de ar"]')).not.toBeNull();
    expect(hrefsSoAjax()).toEqual([]);
  });
});

describe('UC-PAT-09 · Painel não oferece cadastro que abre página em branco', () => {
  it('UC-PAT-09: com permissão de criar, o header não linka pra /asset/assets/create — "Adicionar recurso" abre o drawer, "Alocar recurso" vai pra lista', () => {
    render(
      <PainelPatrimonio
        {...({
          is_admin: true,
          pode: { ver: true, criar: true },
          apurado_em: '2026-09-08T09:42:00-03:00',
          porCategoria: [],
          garantia: [],
          manutencoes: [],
          meusBens: [],
          kpis: { bruto: 0, valorResidual: null, unidades: 0, totalBens: 0, alocados: 0, alocaveis: 0, garantiaCritica: 0 },
        } as any)}
      />,
    );

    const alocar = [...document.querySelectorAll<HTMLAnchorElement>('a[href]')].find(
      (a) => a.textContent?.trim() === 'Alocar recurso',
    );
    expect(alocar?.getAttribute('href')).toBe('/asset/allocation');
    // O cadastro voltou em 2026-09-23 — pelo drawer da lista de Bens, não pelo endpoint só-ajax.
    const adicionar = [...document.querySelectorAll<HTMLAnchorElement>('a[href]')].find(
      (a) => a.textContent?.trim() === 'Adicionar recurso',
    );
    expect(adicionar?.getAttribute('href')).toBe('/asset/assets?novo=1');
    expect(hrefsSoAjax()).toEqual([]);
  });
});
