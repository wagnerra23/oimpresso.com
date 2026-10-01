// UC-MANU-06 · Patrimônio/Manutenções — fechar o drawer devolve o foco a quem o abriu.
//
// Medido em produção em 2026-09-30 (`_saida-19b`): `/asset/asset-maintenance/create` aberto
// direto pela URL, Cancelar → foco no BODY. Mesmo defeito que Alocações teve (#8292). Estes
// casos simulam o ciclo do Inertia: a visita termina (`onSuccess`) e a página re-renderiza SEM
// o drawer.
//
// @see resources/js/Pages/Patrimonio/Manutencoes.casos.md (UC-MANU-06)

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

const get = vi.fn();
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: { get: (...a: unknown[]) => get(...a), delete: vi.fn(), reload: vi.fn(), visit: vi.fn(), post: vi.fn() },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Manutencoes from '@/Pages/Patrimonio/Manutencoes';

afterEach(() => { cleanup(); get.mockReset(); });

const linha = {
  id: 12, codigo: 'MAN-2026/0012', bem: 'Compressor', bem_id: 7, status: 'in_progress', status_label: 'Em andamento',
  prioridade: 'high', prioridade_label: 'Alta', garantia: null, detalhes: null, nota: null,
  criado_em: '30/09/2026', criado_ha: 'hoje', atribuido_a: null, criado_por: null,
};
const lista = (data: typeof linha[]) => ({ data, total: data.length, current_page: 1, last_page: 1, from: 1, to: data.length, links: [] });
const base = {
  filtros: {},
  opcoes: { status: { in_progress: 'Em andamento' }, prioridades: { high: 'Alta' }, responsaveis: { 5: 'Técnico' } },
  permissoes: { vejo_todas: true },
};
const edicao = {
  id: 12, codigo: 'MAN-2026/0012', bem: 'Compressor (PAT-0007)', status: 'in_progress', prioridade: 'high',
  atribuido_a: '5', nota: null, detalhes: '', anexos: [],
};

/** O `router.get` da lista se comporta como a visita real: re-renderiza sem o drawer e chama `onSuccess`. */
function navegarAoFechar(rerender: (ui: React.ReactElement) => void, depois: React.ReactElement) {
  get.mockImplementation((_url: string, _q: unknown, opt: { onSuccess?: () => void }) => {
    rerender(depois);
    opt.onSuccess?.();
  });
}

describe('UC-MANU-06 · foco devolvido ao fechar o drawer', () => {
  it('cadastro aberto direto pela URL: Cancelar devolve o foco a "+ Enviar bem pra manutenção"', async () => {
    const { rerender } = render(
      <Manutencoes {...(base as any)} manutencoes={lista([])} cadastro={{ bens: [], bem_id: null }} />,
    );
    navegarAoFechar(rerender, <Manutencoes {...(base as any)} manutencoes={lista([])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: '+ Enviar bem pra manutenção' })));
  });

  it('edição: o foco volta ao lápis DA PRÓPRIA LINHA', async () => {
    const { rerender } = render(<Manutencoes {...(base as any)} manutencoes={lista([linha])} edicao={edicao} />);
    navegarAoFechar(rerender, <Manutencoes {...(base as any)} manutencoes={lista([linha])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => expect((document.activeElement as HTMLElement).getAttribute('data-acao')).toBe('editar-12'));
  });

  it('edição com a lista ainda não carregada (prop deferida): cai em "+ Enviar bem pra manutenção", não no BODY', async () => {
    const { rerender } = render(<Manutencoes {...(base as any)} manutencoes={lista([])} edicao={edicao} />);
    navegarAoFechar(rerender, <Manutencoes {...(base as any)} manutencoes={lista([])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    await waitFor(
      () => expect(document.activeElement).toBe(screen.getByRole('button', { name: '+ Enviar bem pra manutenção' })),
      { timeout: 3000 },
    );
  });
});
