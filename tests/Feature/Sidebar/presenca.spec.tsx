// Presença no menu do usuário — thread 13 do playbook da sidebar (decisão [W] 2026-09-25).
//
// Forma do protótipo (`sidebar.jsx` PRESENCAS + UserMenu, soberano por UI-0029): 4 estados
// clicáveis; o trigger mostra ponto + rótulo do estado atual; o subpainel marca o atual com
// `aria-pressed`. Estado com um dono só: lido de `auth.user.ui_presence`, gravado por
// POST /user/preferences/presence → users.ui_presence (mesma forma do tema).
//
// Os asserts de clique são RELACIONAIS (antes → depois) pelo mesmo motivo do
// `sidebarAparencia.spec.tsx`: o `fetch` não recarrega o Inertia, então a prop fica
// velha e só a transição prova que o menu segue a escolha.

import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

let presencaGuardada: string | undefined = 'disponivel';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { auth: { user: { ui_theme: 'dark', ui_presence: presencaGuardada } } } }),
}));

import { SidebarFooter } from '@/Components/cockpit/Sidebar';

const fetchMock = vi.fn(() => Promise.resolve(new Response('{}')));

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock);
  fetchMock.mockClear();
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

function montarCom(presenca: string | undefined) {
  presencaGuardada = presenca;
  return render(
    <div className="cockpit" data-theme="dark">
      <SidebarFooter
        nome="Wagner Rocha"
        email="wagner@oimpresso.com.br"
        cargo="Administrador"
        iniciais="WR"
        superadminItems={[]}
        userMenuItems={[]}
      />
    </div>,
  );
}

function abrirPresenca(rotuloAtual: RegExp) {
  fireEvent.click(screen.getByRole('button', { name: /wagner rocha/i }));
  const trigger = screen.getByRole('button', { name: rotuloAtual });
  fireEvent.click(trigger);
  const sub = document.querySelector('.user-menu-sub') as HTMLElement;
  return { trigger, sub, botoes: within(sub).getAllByRole('button') };
}

describe('Presença — forma do protótipo', () => {
  it('4 estados na ordem do protótipo, sem "Não perturbe"', () => {
    montarCom('disponivel');
    const { sub, botoes } = abrirPresenca(/^disponível$/i);

    expect(botoes.map((b) => b.textContent?.trim())).toEqual(['Disponível', 'Ocupado', 'Ausente', 'Invisível']);
    expect(sub.textContent).not.toContain('Não perturbe');
  });

  it('controle positivo: prop guardada "ausente" abre com Ausente no trigger e marcado', () => {
    montarCom('ausente');
    const { trigger, botoes } = abrirPresenca(/^ausente$/i);

    expect(trigger.textContent).toContain('Ausente');
    expect(botoes[2].getAttribute('aria-pressed')).toBe('true');
    expect(botoes.filter((b) => b.getAttribute('aria-pressed') === 'true')).toHaveLength(1);
  });

  it('valor desconhecido na prop cai em Disponível (não quebra o menu)', () => {
    montarCom('nao-perturbe');
    const { botoes } = abrirPresenca(/^disponível$/i);
    expect(botoes[0].getAttribute('aria-pressed')).toBe('true');
  });
});

describe('Presença — clicar grava e o menu segue a escolha', () => {
  it('Invisível: POST pro dono do estado, e trigger + aria-pressed MUDAM', () => {
    montarCom('disponivel');
    const { botoes } = abrirPresenca(/^disponível$/i);

    expect(botoes[0].getAttribute('aria-pressed')).toBe('true');

    fireEvent.click(botoes[3]);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe('/user/preferences/presence');
    expect(init.method).toBe('POST');
    expect(JSON.parse(String(init.body))).toEqual({ presence: 'invisivel' });

    // a prop segue velha — só a transição prova que o menu acompanha
    expect(presencaGuardada).toBe('disponivel');
    const depois = within(document.querySelector('.user-menu-sub') as HTMLElement).getAllByRole('button');
    expect(depois[3].getAttribute('aria-pressed')).toBe('true');
    expect(depois[0].getAttribute('aria-pressed')).toBe('false');
    // trigger + opção: os dois agora dizem "Invisível" (antes, só a opção)
    expect(screen.getAllByRole('button', { name: /^invisível$/i })).toHaveLength(2);
    expect(screen.queryAllByRole('button', { name: /^disponível$/i })).toHaveLength(1);
  });
});
