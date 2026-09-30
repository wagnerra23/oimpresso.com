// UC-MANU-06 · Patrimônio/Manutenções — o drawer de manutenção (thread 19).
//
// O QUE MEDE: a Page renderizada com a prop `cadastro` (vinda do `create()`) ou `edicao`
// (vinda do `edit()`) abre o drawer, e o "Registrar/Salvar" posta nas chaves que o
// `store()`/`update()` leem (`request->only(...)` do `AssetMaintenanceService`). O backend
// dessas rotas é defendido no Pest (`ManutencoesContratoTest.php`, UC-MANU-05/06).
//
// CONTROLE: sem `cadastro`/`edicao`, o drawer NÃO existe — senão os asserts de presença
// passariam por uma tela que sempre o renderiza.

import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const post = vi.fn();
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: { get: vi.fn(), delete: vi.fn(), reload: vi.fn(), visit: vi.fn(), post: (...a: unknown[]) => post(...a) },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Manutencoes from '@/Pages/Patrimonio/Manutencoes';

const base = {
  manutencoes: { data: [], total: 0, current_page: 1, last_page: 1, from: null, to: null, links: [] },
  filtros: {},
  opcoes: {
    status: { new: 'Nova', in_progress: 'Em andamento' },
    prioridades: { high: 'Alta', low: 'Baixa' },
    responsaveis: { 5: 'Técnico' },
  },
  permissoes: { vejo_todas: true },
};

beforeEach(() => post.mockReset());
afterEach(() => cleanup());

describe('UC-MANU-06 · drawer de manutenção', () => {
  it('UC-MANU-06: sem cadastro nem edição, o drawer não existe (controle)', () => {
    render(<Manutencoes {...(base as any)} />);
    expect(screen.queryByTestId('manutencao-drawer')).toBeNull();
    // O CTA do rodapé da âncora está lá — é ele que leva ao `create()`.
    expect(screen.getByRole('button', { name: '+ Enviar bem pra manutenção' })).toBeTruthy();
  });

  it('UC-MANU-06: com cadastro, abre "Enviar pra manutenção" e posta asset_id/status/priority/nota no store()', () => {
    render(
      <Manutencoes
        {...(base as any)}
        cadastro={{ bens: [{ id: 7, nome: 'Compressor', codigo: 'PAT-0007', garantia_ate: '2027-03-01' }], bem_id: 7 }}
      />,
    );

    const drawer = screen.getByTestId('manutencao-drawer');
    expect(drawer.textContent).toContain('Enviar pra manutenção');
    expect(drawer.textContent).toContain('Em garantia até 01/03/2027');
    // Nenhum campo de dinheiro: a tabela não tem coluna de valor (UC-MANU-03, decisão [W]).
    expect(drawer.textContent).not.toMatch(/custo|R\$/i);

    fireEvent.change(screen.getByLabelText('Nota da manutenção'), { target: { value: 'Barulho no motor' } });
    fireEvent.click(screen.getByRole('button', { name: 'Registrar manutenção' }));

    expect(post).toHaveBeenCalledTimes(1);
    const [url, dados, opcoes] = post.mock.calls[0] as [string, Record<string, unknown>, Record<string, unknown>];
    expect(url).toBe('/asset/asset-maintenance');
    expect(dados).toEqual({ asset_id: '7', status: '', priority: '', maintenance_note: 'Barulho no motor' });
    expect(opcoes.forceFormData).toBe(true);
  });

  it('UC-MANU-06: cadastro sem bem escolhido não posta e avisa no campo', () => {
    render(<Manutencoes {...(base as any)} cadastro={{ bens: [{ id: 7, nome: 'Compressor', codigo: 'PAT-0007', garantia_ate: null }], bem_id: null }} />);

    fireEvent.click(screen.getByRole('button', { name: 'Registrar manutenção' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.getByRole('alert').textContent).toContain('Escolha o bem');
  });

  it('UC-MANU-06: com edição, posta _method=put com status/priority/assigned_to/details no update()', () => {
    render(
      <Manutencoes
        {...(base as any)}
        edicao={{
          id: 12,
          codigo: 'MAN-2026/0012',
          bem: 'Compressor (PAT-0007)',
          status: 'in_progress',
          prioridade: 'high',
          atribuido_a: '5',
          nota: 'Nota do envio',
          detalhes: 'Troca de rolamento',
          anexos: [{ id: 1, nome: 'orcamento.pdf', url: '/uploads/media/orcamento.pdf' }],
        }}
      />,
    );

    const drawer = screen.getByTestId('manutencao-drawer');
    expect(drawer.textContent).toContain('Manutenção MAN-2026/0012');
    expect((screen.getByLabelText('Nota da manutenção') as HTMLTextAreaElement).readOnly).toBe(true);
    expect(screen.getByRole('link', { name: 'orcamento.pdf' }).getAttribute('href')).toBe('/uploads/media/orcamento.pdf');

    fireEvent.change(screen.getByLabelText('Detalhes do envio'), { target: { value: 'Rolamento trocado' } });
    fireEvent.click(screen.getByRole('button', { name: 'Salvar manutenção' }));

    const [url, dados] = post.mock.calls[0] as [string, Record<string, unknown>];
    expect(url).toBe('/asset/asset-maintenance/12');
    expect(dados).toEqual({ _method: 'put', status: 'in_progress', priority: 'high', assigned_to: '5', details: 'Rolamento trocado' });
  });

  it('UC-MANU-06: os controles do drawer declaram alvo de toque de 44px (min-h-11)', () => {
    render(<Manutencoes {...(base as any)} cadastro={{ bens: [], bem_id: null }} />);
    const drawer = screen.getByTestId('manutencao-drawer');
    const controles = [
      ...drawer.querySelectorAll('button[role="combobox"], input, textarea:not([readonly])'),
      screen.getByRole('button', { name: 'Registrar manutenção' }),
      screen.getByRole('button', { name: 'Cancelar' }),
    ];
    expect(controles.length).toBeGreaterThanOrEqual(5);
    for (const c of controles) expect(c.className).toContain('min-h-11');
  });
});
