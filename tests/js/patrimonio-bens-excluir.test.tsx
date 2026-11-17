// UC-BENS-13 / UC-BENS-14 · Patrimônio/Bens — excluir pelo AlertDialog do DS.
//
// O QUE MEDE:
//   · UC-BENS-13 — o "Excluir" da linha abre o AlertDialog (nunca `window.confirm`) com o código e
//     o nome do bem; Cancelar fecha sem falar com o servidor; confirmar manda o DELETE no contrato
//     ajax `{success, msg}` do `destroy`, fecha o diálogo e recarrega só as props da lista.
//   · UC-BENS-14 — quando o servidor recusa (`success: false`), responde fora de 2xx ou a rede cai,
//     o diálogo FICA aberto com a mensagem em `role="alert"`, o botão vira "Tentar de novo" e a
//     lista não é recarregada como se tivesse dado certo.
//
// CONTROLE: o "Editar" da linha existe — o caso não passa porque a tabela nem renderizou.

import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

const reload = vi.fn();
const toastSucesso = vi.fn();
vi.mock('sonner', () => ({ toast: { success: (...a: unknown[]) => toastSucesso(...a) } }));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: { shell: { cockpit: { businessNome: 'Tenant 98' } }, business: { id: 98, name: 'Tenant 98' } } }),
  router: { get: vi.fn(), delete: vi.fn(), reload: (...a: unknown[]) => reload(...a), visit: vi.fn(), post: vi.fn(), on: () => () => {} },
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
  alocado: 0,
  alocavel: true,
  valor_unitario: 100,
  compra_em: '2026-01-10',
  garantia: null,
  em_manutencao: 0,
  imagem_url: null,
};

const renderizar = () =>
  render(
    <Bens
      bens={{ data: [bem], total: 1, current_page: 1, last_page: 1, from: 1, to: 1, links: [] } as any}
      filtros={{}}
      opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
      formato_data="d/m/Y"
      permissoes={{ criar: false, editar: true, excluir: true, manutencao: false }}
    />,
  );

const abrir = () => {
  expect(document.querySelector('button[title="Editar — Furadeira de bancada"]')).not.toBeNull();
  fireEvent.click(document.querySelector<HTMLButtonElement>('button[title="Excluir — Furadeira de bancada"]')!);
};

const respostaJson = (status: number, corpo: unknown) =>
  Promise.resolve({ ok: status >= 200 && status < 300, status, json: () => Promise.resolve(corpo) } as Response);

let fetchMock: ReturnType<typeof vi.fn>;
let confirmSpy: ReturnType<typeof vi.spyOn>;
beforeEach(() => {
  reload.mockReset();
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

describe('UC-BENS-13 · excluir o bem pelo AlertDialog do DS', () => {
  it('UC-BENS-13: o Excluir da linha abre o AlertDialog com código e nome, sem window.confirm', () => {
    renderizar();
    abrir();

    const dialogo = screen.getByRole('alertdialog');
    expect(dialogo.textContent).toContain('Excluir AST-0007?');
    expect(dialogo.textContent).toContain('Furadeira de bancada');
    expect(confirmSpy).not.toHaveBeenCalled();
  });

  it('UC-BENS-13: Cancelar fecha o diálogo sem falar com o servidor', () => {
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('UC-BENS-13: confirmar manda o DELETE no contrato ajax, fecha e recarrega só a lista', async () => {
    fetchMock.mockReturnValue(respostaJson(200, { success: true, msg: 'Success' }));
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Excluir' }));

    await waitFor(() => expect(screen.queryByRole('alertdialog')).toBeNull());
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/asset/assets/7');
    expect(init.method).toBe('POST');
    expect(String(init.body)).toBe('_method=DELETE');
    expect((init.headers as Record<string, string>)['X-Requested-With']).toBe('XMLHttpRequest');
    expect(reload).toHaveBeenCalledWith({ only: ['bens', 'recortes_contagem', 'abas_contadores'] });
    expect(toastSucesso).toHaveBeenCalledWith('Bem "Furadeira de bancada" excluído.');
  });
});

describe('UC-BENS-14 · a exclusão que falha avisa e deixa tentar de novo', () => {
  it('UC-BENS-14: success false mantém o diálogo aberto com a mensagem do servidor e não recarrega', async () => {
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

  it('UC-BENS-14: HTTP 500 sem JSON também vira aviso, com o status', async () => {
    fetchMock.mockReturnValue(
      Promise.resolve({ ok: false, status: 500, json: () => Promise.reject(new Error('html')) } as unknown as Response),
    );
    renderizar();
    abrir();

    fireEvent.click(screen.getByRole('button', { name: 'Excluir' }));

    expect((await screen.findByRole('alert')).textContent).toContain('HTTP 500');
    expect(reload).not.toHaveBeenCalled();
  });

  it('UC-BENS-14: rede caída avisa, e Tentar de novo refaz o pedido até dar certo', async () => {
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
