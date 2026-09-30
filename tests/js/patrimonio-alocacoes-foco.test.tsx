// UC-ALOC-07 · Patrimônio/Alocações — fechar o drawer devolve o foco a quem o abriu.
//
// Medido em produção em 2026-09-30: `/asset/allocation/create` aberto direto pela URL, Cancelar
// → foco no BODY. Fechar é NAVEGAÇÃO (volta pra lista), então a devolução de foco do Radix não
// tem trigger a quem devolver. Estes casos simulam o ciclo do Inertia: a visita termina
// (`onSuccess`) e a página re-renderiza SEM a prop `formulario`.
//
// @see resources/js/Pages/Patrimonio/Alocacoes.casos.md (UC-ALOC-07)

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98 } } }),
  router: { get: (...a: unknown[]) => get(...a), post: vi.fn(), put: vi.fn(), delete: vi.fn(), reload: vi.fn(), visit: vi.fn() },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Alocacoes from '@/Pages/Patrimonio/Alocacoes';

afterEach(() => { cleanup(); get.mockReset(); });

const permissoes = { alocar: true, editar: true, excluir: true, devolver: true };
const base = { filtros: { situacao: 'ativas' }, permissoes, formato_data: 'd/m/Y', hora_12: false };
const linha = {
  id: 42, ref_no: 'ALO-42', bem: 'Plotter', modelo: null, categoria: null, recebido_por: 'Ana', alocado_por: 'W',
  quantidade: 4, devolvido: 1, alocado_em: '29/09/2026', prazo: null, motivo: null, situacao: 'parcial' as const, vencido: false,
};
const lista = (data: typeof linha[]) => ({ data, total: data.length, current_page: 1, last_page: 1, from: 1, to: data.length, links: [] });

/** Faz o `router.get` da lista se comportar como a visita real: re-renderiza sem o drawer e chama `onSuccess`. */
function navegarAoFechar(rerender: (ui: React.ReactElement) => void, depois: React.ReactElement) {
  get.mockImplementation((_url: string, _q: unknown, opt: { onSuccess?: () => void }) => {
    rerender(depois);
    opt.onSuccess?.();
  });
}

describe('UC-ALOC-07 · foco devolvido ao fechar o drawer', () => {
  it('alocar aberto direto pela URL: Cancelar devolve o foco a "Alocar recurso"', async () => {
    const formulario = { modo: 'alocar' as const, bens: [], pessoas: [], asset_id: null };
    const { rerender } = render(<Alocacoes {...base} alocacoes={lista([])} formulario={formulario} />);
    navegarAoFechar(rerender, <Alocacoes {...base} alocacoes={lista([])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Alocar recurso' })));
  });

  it('devolver: o foco volta ao botão Devolver DA PRÓPRIA LINHA', async () => {
    const formulario = {
      modo: 'devolver' as const,
      alocacao: { id: 42, ref_no: 'ALO-42', bem: 'Plotter', recebido_por: 'Ana', quantidade: 4, devolvido: 1, restante: 3 },
      devolucoes: [],
    };
    const { rerender } = render(<Alocacoes {...base} alocacoes={lista([linha])} formulario={formulario} />);
    navegarAoFechar(rerender, <Alocacoes {...base} alocacoes={lista([linha])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Devolver alocação ALO-42' })));
  });

  it('editar com a lista ainda não carregada (prop deferida): cai em "Alocar recurso", não no BODY', async () => {
    const formulario = {
      modo: 'editar' as const, bens: [], pessoas: [], asset_id: 1,
      alocacao: { id: 77, ref_no: 'ALO-77', receiver: 1, quantidade: 1, alocado_em: '2026-09-30T08:00', prazo: '', motivo: '' },
    };
    const { rerender } = render(<Alocacoes {...base} alocacoes={lista([])} formulario={formulario} />);
    navegarAoFechar(rerender, <Alocacoes {...base} alocacoes={lista([])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Alocar recurso' })), { timeout: 3000 });
  });
});
