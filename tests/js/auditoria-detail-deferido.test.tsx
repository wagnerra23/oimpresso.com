// Carga deferida da tela Auditoria/Detail — UC-AUDD-10 e UC-AUDD-11.
//
// O QUE MEDE: `activity` chega por Inertia::defer. Enquanto ela não chegou (`undefined`) a tela
// mostra o esqueleto e NÃO diz "não encontrada"; quando chega `null` (registro inexistente ou de
// outra empresa) mostra o estado de não encontrada e o esqueleto some; quando chega o registro,
// mostra o conteúdo. O Pest cobre o lado do servidor, com os headers do cliente Inertia.
//
// O `<Deferred>` falso usa o mesmo critério do real (`@inertiajs/react` 3.6.1): renderiza o
// children quando a prop é diferente de `undefined`, e por isso `null` conta como carregada.

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

const pagina: { props: Record<string, unknown> } = { props: {} };

vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children, data, fallback }: any) =>
    pagina.props[data] !== undefined ? <>{children}</> : <>{fallback}</>,
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Components/PageHeader', () => ({
  PageHeader: ({ title, actions }: any) => <header><h1>{title}</h1>{actions}</header>,
}));
vi.mock('@/Components/shared/EmptyState', () => ({
  default: ({ title, description }: any) => <div><p>{title}</p><p>{description}</p></div>,
}));

import Detail from '@/Pages/Auditoria/Detail';

const registro = {
  id: 321,
  log_name: 'default',
  description: 'alteração de teste',
  subject_type: 'App\\Exemplo',
  subject_id: 7,
  causer_type: null,
  causer_id: null,
  properties: { old: { status: 'a' }, attributes: { status: 'b' } },
  business_id: 98,
  created_at: '2026-11-09T12:00:00Z',
};

function montar(activity: unknown) {
  pagina.props = activity === undefined ? {} : { activity };
  render(<Detail activity={activity as never} />);
}

const carregando = () => document.querySelector('[aria-busy="true"]');
const naoEncontrada = () => screen.queryAllByText('Atividade não encontrada');

afterEach(cleanup);

describe('Auditoria/Detail · carga deferida', () => {
  it('UC-AUDD-10 · enquanto a prop não chegou mostra o esqueleto e não diz "não encontrada"', () => {
    montar(undefined);

    expect(carregando()).not.toBeNull();
    expect(naoEncontrada()).toHaveLength(0);
  });

  it('UC-AUDD-10 · quando o registro chega mostra o conteúdo, sem esqueleto', () => {
    montar(registro);

    expect(screen.getByRole('heading', { name: 'Atividade #321' })).toBeTruthy();
    expect(screen.getByText('Propriedades (diff)')).toBeTruthy();
    expect(carregando()).toBeNull();
    expect(naoEncontrada()).toHaveLength(0);
  });

  it('UC-AUDD-11 · registro inexistente mostra não encontrada depois de carregar, sem esqueleto', () => {
    montar(null);

    expect(naoEncontrada().length).toBeGreaterThan(0);
    expect(carregando()).toBeNull();
  });
});
