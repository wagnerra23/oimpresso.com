// Gaveta da lista de contatos — pendências medidas em 2026-11-25
// (memory/requisitos/Crm/cliente-index-visual-comparison.md, seção A).
//
// Cobre UC-CIDX-25 (Operações › Pessoas sai do "Carregando…") e UC-CIDX-26 (o aviso de e-mail
// inválido só aparece depois que a pessoa digita) — resources/js/Pages/Cliente/Index.casos.md.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/cliente-gaveta-pendencias.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import PessoasContatoTab from '@/Pages/Cliente/_show/PessoasContatoTab';
import ContatoTab from '@/Pages/Cliente/_drawer/ContatoTab';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

function responder(corpo: unknown, status = 200) {
  const fetchFalso = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(corpo), { status, headers: { 'Content-Type': 'application/json' } }),
  );
  vi.stubGlobal('fetch', fetchFalso);
  return fetchFalso;
}

describe('UC-CIDX-25 · Operações › Pessoas na gaveta', () => {
  it('UC-CIDX-25 sem a lista por prop, busca /cliente/{id}/pessoas-json e mostra a pessoa', async () => {
    const fetchFalso = responder([
      { id: 9, username: 'ana', email: null, full_name: 'Ana Souza', department: 'Compras', designation: 'Gerente' },
    ]);
    render(<PessoasContatoTab contactId={42} />);

    await waitFor(() => expect(screen.getByText('Ana Souza')).toBeTruthy());
    expect(fetchFalso.mock.calls[0][0]).toBe('/cliente/42/pessoas-json');
    expect(screen.queryByTestId('persons-tab-skeleton')).toBeNull();
  });

  it('UC-CIDX-25 cliente sem pessoa cai no estado vazio, não fica em "Carregando…"', async () => {
    responder([]);
    render(<PessoasContatoTab contactId={42} />);

    await waitFor(() => expect(screen.getByTestId('persons-tab-empty')).toBeTruthy());
    expect(screen.queryByText(/Carregando pessoas/)).toBeNull();
  });

  it('UC-CIDX-25 recusa do servidor mostra aviso, não fica em "Carregando…"', async () => {
    responder({ message: 'Unauthorized action.' }, 403);
    render(<PessoasContatoTab contactId={42} />);

    await waitFor(() => expect(screen.getByTestId('persons-tab-error')).toBeTruthy());
  });

  it('UC-CIDX-25 com a lista por prop (ficha cheia), não busca nada', () => {
    const fetchFalso = responder([]);
    render(<PessoasContatoTab contactId={42} contact_persons={[]} />);

    expect(screen.getByTestId('persons-tab-empty')).toBeTruthy();
    expect(fetchFalso).not.toHaveBeenCalled();
  });
});

describe('UC-CIDX-26 · e-mail legado com dois endereços não acusa erro ao abrir a aba', () => {
  const legado = { id: 7, email: 'compras@exemplo.com.br; financeiro@exemplo.com.br' };

  it('UC-CIDX-26 a aba abre sem "Formato de e-mail inválido"', () => {
    render(<ContatoTab contact={legado} />);

    expect(screen.queryByText('Formato de e-mail inválido.')).toBeNull();
    expect(document.getElementById('ct-email')!.getAttribute('aria-invalid')).toBe('false');
  });

  it('UC-CIDX-26 depois que a pessoa digita, o aviso aparece (controle: a validação segue viva)', () => {
    render(<ContatoTab contact={legado} />);

    fireEvent.change(document.getElementById('ct-email')!, { target: { value: 'sem-arroba' } });

    expect(screen.getByText('Formato de e-mail inválido.')).toBeTruthy();
  });
});
