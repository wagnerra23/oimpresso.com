/**
 * Paginação do histórico no extrato de banco de horas (Ponto/BancoHoras/Show).
 *
 * @covers-us UC-BHSHOW-04
 *
 * FONTE DO CONTRATO: `Show.charter.md` §Goals — "Histórico paginado (50/pág) de
 * movimentos". O servidor (`BancoHorasController@show`) sempre paginou em 50; a tela
 * declarava `last_page`/`links` na interface e não os renderizava, então do 51º
 * movimento em diante nada aparecia. O lado servidor (a 2ª página existe e traz o que
 * faltou) é provado pelo Pest `UC-BHSHOW-04`; ESTE arquivo prova o lado tela.
 *
 * POR QUE RENDER (e não assert sobre o texto do `.tsx`): o contrato é "o operador
 * consegue chegar à página 2". Grep no fonte provaria que o botão foi ESCRITO —
 * presença, não comportamento (LC-11). Aqui cada caso conta as `<tr>` renderizadas,
 * lê o "Página X de Y" que o operador vê e clica o botão de verdade.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

const routerGet = vi.fn();

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { visit: vi.fn(), get: (...a: unknown[]) => routerGet(...a), post: vi.fn() },
  useForm: () => ({
    data: { minutos: 0, observacao: '' },
    setData: vi.fn(),
    post: vi.fn(),
    reset: vi.fn(),
    processing: false,
    errors: {},
  }),
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));
vi.mock('@/Pages/Ponto/_shared/PontoSubNav', () => ({ default: () => null }));

import BancoHorasShow from '@/Pages/Ponto/BancoHoras/Show';
import { fmtDataHoraBr } from '@/Lib/datetime-br';

const saldo = { colaborador_id: 7, matricula: 'M-1', nome: 'Colaborador Teste', saldo_minutos: 51 };

const mov = (n: number) => ({
  id: n,
  minutos: 1,
  tipo: 'CREDITO_HE',
  data_referencia: '2019-03-11',
  observacao: 'Movimento ' + n,
  created_at: '2019-03-11 08:00',
  created_at_human: 'há 1 dia',
});

const URL_BASE = 'http://localhost/ponto/banco-horas/7';

/** Paginator do Laravel: 51 movimentos, 50/pág => 2 páginas. */
const pagina = (atual: 1 | 2, lastPage = 2) => ({
  data: atual === 1 ? Array.from({ length: 50 }, (_, i) => mov(51 - i)) : [mov(1)],
  total: lastPage === 1 ? 50 : 51,
  current_page: atual,
  last_page: lastPage,
  links: [
    { url: atual === 1 ? null : `${URL_BASE}?page=1`, label: '&laquo; Anterior', active: false },
    { url: `${URL_BASE}?page=1`, label: '1', active: atual === 1 },
    ...(lastPage > 1 ? [{ url: `${URL_BASE}?page=2`, label: '2', active: atual === 2 }] : []),
    { url: atual === 1 && lastPage > 1 ? `${URL_BASE}?page=2` : null, label: 'Próximo &raquo;', active: false },
  ],
});

const linhas = () => Array.from(document.querySelectorAll('tbody tr'));

describe('UC-BHSHOW-04 · com mais de 50 movimentos a 2ª página do extrato é alcançável', () => {
  beforeEach(() => routerGet.mockClear());

  it('página 1 mostra 50 linhas e o controle "Página 1 de 2"', () => {
    render(<BancoHorasShow saldo={saldo} movimentos={pagina(1)} />);

    expect(linhas()).toHaveLength(50);
    expect(screen.getByText(/Página 1 de 2/)).toBeTruthy();
    expect(screen.getByText(/51 movimento\(s\)/)).toBeTruthy();
  });

  it('clicar "2" pede só `movimentos` por partial reload, preservando o scroll', () => {
    render(<BancoHorasShow saldo={saldo} movimentos={pagina(1)} />);

    fireEvent.click(screen.getByRole('button', { name: '2' }));

    expect(routerGet).toHaveBeenCalledTimes(1);
    expect(routerGet).toHaveBeenCalledWith(`${URL_BASE}?page=2`, {}, {
      preserveScroll: true,
      only: ['movimentos'],
    });
  });

  it('página 2 renderiza o 51º movimento — o que a tela escondia', () => {
    render(<BancoHorasShow saldo={saldo} movimentos={pagina(2)} />);

    expect(linhas()).toHaveLength(1);
    expect(screen.getByText('Movimento 1')).toBeTruthy();
    expect(screen.getByText(/Página 2 de 2/)).toBeTruthy();
  });

  it('link sem url (Anterior na página 1) fica desabilitado e não navega', () => {
    render(<BancoHorasShow saldo={saldo} movimentos={pagina(1)} />);

    const anterior = screen.getByRole('button', { name: /Anterior/ }) as HTMLButtonElement;
    expect(anterior.disabled).toBe(true);
    fireEvent.click(anterior);
    expect(routerGet).not.toHaveBeenCalled();
  });

  it('controle negativo: com uma página só, não há controle de paginação', () => {
    render(<BancoHorasShow saldo={saldo} movimentos={pagina(1, 1)} />);

    expect(linhas()).toHaveLength(50);
    expect(screen.queryByText(/Página \d+ de \d+/)).toBeNull();
    expect(screen.queryByRole('button', { name: '1' })).toBeNull();
  });
});

describe('coluna "Registrado" mostra data-hora ABSOLUTA, não "há X"', () => {
  it('a célula traz dd/mm/aaaa HH:mm e deixa a relativa no hover', () => {
    render(<BancoHorasShow saldo={saldo} movimentos={pagina(2)} />);

    const celula = screen.getByText('11/03/2019 08:00');
    expect(celula.tagName).toBe('TD');
    expect(celula.getAttribute('title')).toBe('há 1 dia');
    expect(screen.queryByText('há 1 dia')).toBeNull();
  });

  it('fmtDataHoraBr: formato do servidor, separador T, vazio e fora do padrão', () => {
    expect(fmtDataHoraBr('2026-08-19 21:30')).toBe('19/08/2026 21:30');
    expect(fmtDataHoraBr('2026-08-19T21:30:59-03:00')).toBe('19/08/2026 21:30');
    expect(fmtDataHoraBr(null)).toBe('—');
    expect(fmtDataHoraBr('')).toBe('—');
    expect(fmtDataHoraBr('ontem')).toBe('ontem');
  });
});
