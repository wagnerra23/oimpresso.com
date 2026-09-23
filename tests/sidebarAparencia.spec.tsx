// Aparência no menu do usuário — a cascata abre, escolhe, e o ✓ SEGUE a escolha.
//
// Thread 01 do playbook `cowork-inbox/shell-usermenu`. O playbook afirmava que o
// botão "não tem handler"; medido em produção em 2026-09-23 isso era falso — a
// cascata abria (Claro/Escuro/Sistema). O que este arquivo trava é o que sobrou:
//
//   1. Forma do protótipo (`sidebar.jsx` TEMAS, soberano por UI-0029): DUAS opções,
//      Escuro primeiro, cada uma com a descrição; o valor atual aparece no trigger.
//   2. Um defeito de estado que a leitura do código expôs: o ✓ era calculado pelo
//      `mode` do `useTheme`, que vem da prop `auth.user.ui_theme`. O `setTheme`
//      persiste por `fetch` sem reload do Inertia, então a prop fica velha e o ✓
//      continuava no tema ANTIGO depois do clique. Por isso os asserts abaixo são
//      RELACIONAIS — medem a transição antes→depois do clique, não um valor fixo.
//
// Um dono só pro estado: o teste confere que a escolha escreve o MESMO lugar que o
// shell lê (`data-theme` no <html> e no `.cockpit`, classe `.dark`, POST
// /user/preferences/theme → users.ui_theme). Nenhuma chave nova de localStorage.

import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

let temaGuardado: 'light' | 'dark' | null = 'light';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { auth: { user: { ui_theme: temaGuardado } } } }),
}));

import { SidebarFooter } from '@/Components/cockpit/Sidebar';

const fetchMock = vi.fn(() => Promise.resolve(new Response('{}')));

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock);
  fetchMock.mockClear();
  localStorage.clear();
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  document.documentElement.className = '';
  document.documentElement.removeAttribute('data-theme');
});

// "Recarregar a página" com um tema guardado = o servidor devolve ui_theme e o
// anti-flash do inertia.blade já aplicou a classe antes do React.
function recarregarCom(tema: 'light' | 'dark') {
  temaGuardado = tema;
  document.documentElement.classList.toggle('dark', tema === 'dark');
  document.documentElement.setAttribute('data-theme', tema);
  return render(
    <div className="cockpit" data-theme={tema}>
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

function abrirAparencia() {
  fireEvent.click(screen.getByRole('button', { name: /wagner rocha/i }));
  const trigger = screen.getByRole('button', { name: /apar[eê]ncia/i });
  fireEvent.click(trigger);
  const sub = document.querySelector('.user-menu-sub') as HTMLElement;
  return { trigger, sub };
}

function opcoes(sub: HTMLElement) {
  return within(sub).getAllByRole('button');
}

describe('Aparência — forma do protótipo', () => {
  it('duas opções, Escuro primeiro, cada uma com a descrição do protótipo', () => {
    recarregarCom('dark');
    const { sub } = abrirAparencia();

    const botoes = opcoes(sub);
    expect(botoes).toHaveLength(2);
    expect(botoes[0].textContent).toContain('Escuro');
    expect(botoes[0].textContent).toContain('Padrão do balcão');
    expect(botoes[1].textContent).toContain('Claro');
    expect(botoes[1].textContent).toContain('Escritório, luz alta');
    expect(sub.textContent).not.toContain('Sistema');
  });
});

describe('Aparência — escolher muda o estado e o ✓ acompanha', () => {
  it('controle positivo: recarregar com light guardado abre em light (trigger e ✓)', () => {
    recarregarCom('light');
    const { trigger, sub } = abrirAparencia();

    expect(trigger.textContent).toContain('claro');
    const [escuro, claro] = opcoes(sub);
    expect(claro.getAttribute('aria-pressed')).toBe('true');
    expect(escuro.getAttribute('aria-pressed')).toBe('false');
  });

  it('clicar Escuro vira o tema, persiste pelo dono do estado, e o ✓ MUDA de lugar', () => {
    recarregarCom('light');
    const { trigger, sub } = abrirAparencia();
    const [escuro, claro] = opcoes(sub);

    // antes
    expect(claro.getAttribute('aria-pressed')).toBe('true');
    expect(trigger.textContent).toContain('claro');

    fireEvent.click(escuro);

    // depois — o mesmo estado que o shell lê
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(document.querySelector('.cockpit')?.getAttribute('data-theme')).toBe('dark');

    // persistência vai pro servidor (users.ui_theme), não pra chave nova
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe('/user/preferences/theme');
    expect(init.method).toBe('POST');
    expect(JSON.parse(String(init.body))).toEqual({ theme: 'dark' });

    // o ✓ e o trigger seguem a escolha MESMO com a prop auth.user.ui_theme ainda 'light'
    expect(temaGuardado).toBe('light');
    const [escuroDepois, claroDepois] = opcoes(document.querySelector('.user-menu-sub') as HTMLElement);
    expect(escuroDepois.getAttribute('aria-pressed')).toBe('true');
    expect(claroDepois.getAttribute('aria-pressed')).toBe('false');
    expect(screen.getByRole('button', { name: /apar[eê]ncia/i }).textContent).toContain('escuro');
  });
});
