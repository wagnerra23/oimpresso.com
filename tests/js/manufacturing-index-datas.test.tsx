// UC-OP-06 · Manufacturing/Index — o intervalo De/Até aplica ao escolher, sem botão.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// D-MFG-DATA ([W] 2026-09-25): a data era o único filtro da tela que pedia 2 gestos
// (escolher + blur ou lupa). Local e "Só finalizadas" já aplicavam no change. O conserto
// tirou o `onBlur` e o botão "Aplicar intervalo de datas" — e trocou um risco por outro:
// aplicar no `onChange` pode disparar um request por TECLA, porque o `<input type="date">`
// emite `0002-…`, `0020-…`, `0202-…` enquanto o ano é digitado à mão. Este spec mede os
// dois lados: aplica quando deve, e NÃO aplica no meio da digitação.
//
// ── O QUE ELE MEDE (componente REAL) ─────────────────────────────────────────
// Importa a Page de produção; `vi.mock` cobre só o `router` do Inertia (é o que se conta)
// e o `Link`. O `applyFilter`, o guard de ano e o partial reload são os de verdade.
//
// O 1º caso é CONTROLE POSITIVO do harness: o select de Local — que já aplicava no change
// antes desta mudança — tem de chamar o `router.get`. Se os mocks tivessem quebrado o render
// ou o contador, ele falharia, e aí nenhum "0 chamadas" abaixo significaria alguma coisa.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre o SERVIDOR filtrar por data — isso é do Pest do módulo (lane MySQL).
//   - jsdom não reproduz o widget nativo de data; o `0002-…` é o valor que os navegadores
//     emitem, injetado via `fireEvent.change`. O comportamento no browser real se mede no
//     smoke pós-merge (LC-30), não aqui.
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/manufacturing-index-datas.test.tsx
// @see resources/js/Pages/Manufacturing/Index.casos.md (UC-OP-06)

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { get: (...a: unknown[]) => get(...a), visit: vi.fn(), reload: vi.fn() },
  Link: ({ children, href, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
  usePage: () => ({ props: {} }),
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));

import Index from '@/Pages/Manufacturing/Index';

const baseProps = {
  productions: [],
  summary: { total_count: 0, final_count: 0, pending_count: 0, total_value: 0 },
  business_locations: { 1: 'Matriz', 2: 'Filial' },
  recipes_count: 0,
};

const de = () => screen.getByLabelText('De') as HTMLInputElement;
const ate = () => screen.getByLabelText('Até') as HTMLInputElement;

beforeEach(() => get.mockReset());
afterEach(() => cleanup());

describe('UC-OP-06 · o intervalo De/Até aplica ao escolher, sem botão', () => {
  it('UC-OP-06 controle positivo: o select de Local aplica no change (harness vivo)', () => {
    render(<Index {...baseProps} filters={{}} />);
    fireEvent.change(screen.getByLabelText('Local'), { target: { value: '2' } });
    expect(get).toHaveBeenCalledTimes(1);
  });

  it('UC-OP-06 escolher De e Até aplica sozinho, via partial reload', () => {
    render(<Index {...baseProps} filters={{}} />);
    fireEvent.change(de(), { target: { value: '2026-09-01' } });
    expect(get).not.toHaveBeenCalled(); // só um preenchido: não aplica
    fireEvent.change(ate(), { target: { value: '2026-09-30' } });
    expect(get).toHaveBeenCalledTimes(1);
    const [rota, params, opts] = get.mock.calls[0];
    expect(rota).toBe('/manufacturing/production');
    expect(params).toMatchObject({ start_date: '2026-09-01', end_date: '2026-09-30' });
    expect(opts.only).toEqual(['productions', 'summary', 'filters']);
  });

  it('UC-OP-06 digitar o ano à mão não dispara request no meio (0002 · 0020 · 0202)', () => {
    render(<Index {...baseProps} filters={{}} />);
    fireEvent.change(de(), { target: { value: '2026-09-01' } });
    for (const parcial of ['0002-09-30', '0020-09-30', '0202-09-30']) {
      fireEvent.change(ate(), { target: { value: parcial } });
    }
    expect(get).not.toHaveBeenCalled();
    fireEvent.change(ate(), { target: { value: '2026-09-30' } });
    expect(get).toHaveBeenCalledTimes(1);
  });

  it('UC-OP-06 apagar as duas datas volta a lista inteira', () => {
    render(<Index {...baseProps} filters={{ start_date: '2026-09-01', end_date: '2026-09-30' }} />);
    fireEvent.change(de(), { target: { value: '' } });
    expect(get).not.toHaveBeenCalled(); // Até ainda preenchido
    fireEvent.change(ate(), { target: { value: '' } });
    expect(get).toHaveBeenCalledTimes(1);
    expect(get.mock.calls[0][1]).toMatchObject({ start_date: undefined, end_date: undefined });
  });

  it('UC-OP-06 não existe mais o botão "Aplicar intervalo de datas"', () => {
    render(<Index {...baseProps} filters={{}} />);
    expect(screen.queryByRole('button', { name: 'Aplicar intervalo de datas' })).toBeNull();
  });
});
