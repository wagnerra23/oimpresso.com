// UC-MANU-08 / UC-MANU-09 · Patrimônio/Manutenções — excluir pelo AlertDialog do DS.
//
// O QUE MEDE (mesmo contrato de Bens, UC-BENS-13/14):
//   · UC-MANU-08 — o "Excluir" da linha abre o AlertDialog (nunca `window.confirm`) com o código e
//     o bem; Cancelar fecha sem falar com o servidor; confirmar manda o DELETE no contrato ajax
//     `{success, msg}` do `destroy`, fecha o diálogo e recarrega só `manutencoes`.
//   · UC-MANU-09 — quando o servidor recusa, responde fora de 2xx ou a rede cai, o diálogo FICA
//     aberto com a mensagem em `role="alert"`, o botão vira "Tentar de novo" e a lista não é
//     recarregada como se tivesse dado certo.
//
// CONTROLE: o "Editar" da linha existe — o caso não passa porque a tabela nem renderizou.

import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

const reload = vi.fn();
const routerDelete = vi.fn();
const toastSucesso = vi.fn();
vi.mock('sonner', () => ({ toast: { success: (...a: unknown[]) => toastSucesso(...a) } }));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: {
    get: vi.fn(),
    delete: (...a: unknown[]) => routerDelete(...a),
    reload: (...a: unknown[]) => reload(...a),
    visit: vi.fn(),
    post: vi.fn(),
    on: () => () => {},
  },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));

import Manutencoes from '@/Pages/Patrimonio/Manutencoes';

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

const renderizar = () =>
  render(
    <Manutencoes
      manutencoes={{ data: [manutencao], total: 1, current_page: 1, last_page: 1, from: 1, to: 1, links: [] } as any}
      filtros={{}}
      opcoes={{ status: {}, prioridades: {}, responsaveis: {} } as any}
      permissoes={{ vejo_todas: true } as any}
    />,
  );

const abrir = () => {
  expect(document.querySelector('button[title="Editar manutenção — Compressor de ar"]')).not.toBeNull();
  fireEvent.click(document.querySelector<HTMLButtonElement>('button[title="Excluir manutenção — Compressor de ar"]')!);
};

const respostaJson = (status: number, corpo: unknown) =>
  Promise.resolve({ ok: status >= 200 && status < 300, status, json: () => Promise.resolve(corpo) } as Response);

let fetchMock: ReturnType<typeof vi.fn>;
let confirmSpy: ReturnType<typeof vi.spyOn>;
beforeEach(() => {
  reload.mockReset();
  routerDelete.mockReset();
  toastSucesso.mockReset();
  fetchMock = vi.fn();
  vi.stubGlobal('fetch', fetchMock);
  confirmSpy = vi.spyOn(window, 'confirm');
});
afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  confirmSpy.mockRestore();
});

describe('UC-MANU-08 · excluir a manutenção pelo AlertDialog do DS', () => {
  it('UC-MANU-08: o Excluir da linha abre o AlertDialog com código e bem, sem window.confirm', () => {
    renderizar();
    abrir();

    const dialogo = screen.getByRole('alertdialog');
    expect(dialogo.textContent).toContain('Excluir a manutenção MNT-0012?');
    expect(dialogo.textContent).toContain('Compressor de ar');
    expect(confirmSpy).not.toHaveBeenCalled();
  });

  it('UC-MANU-08: Cancelar fecha o diálogo sem falar com o servidor', () => {
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
    expect(routerDelete).not.toHaveBeenCalled();
  });

  it('UC-MANU-08: confirmar manda o DELETE no contrato ajax, fecha e recarrega só a lista', async () => {
    fetchMock.mockReturnValue(respostaJson(200, { success: true, msg: 'Success' }));
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Excluir' }));

    await waitFor(() => expect(screen.queryByRole('alertdialog')).toBeNull());
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/asset/asset-maintenance/12');
    expect(init.method).toBe('POST');
    expect(String(init.body)).toBe('_method=DELETE');
    expect((init.headers as Record<string, string>)['X-Requested-With']).toBe('XMLHttpRequest');
    expect(routerDelete).not.toHaveBeenCalled();
    expect(reload).toHaveBeenCalledWith({ only: ['manutencoes'] });
    expect(toastSucesso).toHaveBeenCalledWith('Manutenção de "Compressor de ar" excluída.');
  });
});

describe('UC-MANU-09 · a exclusão que falha avisa e deixa tentar de novo', () => {
  it('UC-MANU-09: success false mantém o diálogo aberto com a mensagem do servidor e não recarrega', async () => {
    fetchMock.mockReturnValue(respostaJson(200, { success: false, msg: 'Algo deu errado' }));
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Excluir' }));

    const alerta = await screen.findByRole('alert');
    expect(alerta.textContent).toContain('Não consegui excluir.');
    expect(alerta.textContent).toContain('Algo deu errado');
    expect(screen.getByRole('alertdialog')).not.toBeNull();
    expect(screen.getByRole('button', { name: 'Tentar de novo' })).not.toBeNull();
    expect(reload).not.toHaveBeenCalled();
    expect(toastSucesso).not.toHaveBeenCalled();
  });

  it('UC-MANU-09: HTTP 403 sem JSON também vira aviso, com o status', async () => {
    fetchMock.mockReturnValue(
      Promise.resolve({ ok: false, status: 403, json: () => Promise.reject(new Error('html')) } as unknown as Response),
    );
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Excluir' }));

    expect((await screen.findByRole('alert')).textContent).toContain('HTTP 403');
    expect(reload).not.toHaveBeenCalled();
  });

  it('UC-MANU-09: rede caída avisa, e Tentar de novo refaz o pedido até dar certo', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'));
    fetchMock.mockReturnValueOnce(respostaJson(200, { success: true }));
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Excluir' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Sem conexão com o servidor.');

    fireEvent.click(screen.getByRole('button', { name: 'Tentar de novo' }));

    await waitFor(() => expect(screen.queryByRole('alertdialog')).toBeNull());
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(reload).toHaveBeenCalledTimes(1);
  });
});
