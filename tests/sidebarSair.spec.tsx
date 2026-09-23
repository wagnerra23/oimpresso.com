// Sair no menu do usuário — confirma antes de encerrar, e encerrar é o logout REAL.
//
// Thread 02 do playbook `cowork-inbox/shell-usermenu`. O playbook afirmava que o
// item "não tem handler"; medido em 2026-09-23 isso era falso — no main o item era
// `<a href="/logout">` e encerrava direto, sem confirmação. O que este arquivo trava:
//
//   1. Clicar "Sair" NÃO encerra: abre a pergunta inline no próprio menu.
//   2. "Encerrar" é o logout real do app — `GET /logout` → LoginController@logout,
//      a mesma rota do layout legado (header.blade.php). NÃO o `reload()` do
//      protótipo, que só existe porque lá não há auth.
//   3. "Cancelar" volta ao menu sem efeito; clicar FORA com a pergunta aberta fecha
//      o menu sem encerrar (controle negativo do playbook).
//
// Asserts relacionais: medem antes→depois de cada clique. O vetor de regressão é
// um link pra /logout visível ANTES da confirmação — contado explicitamente.

import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { SidebarFooter } from '@/Components/cockpit/Sidebar';

afterEach(cleanup);

function renderFooter() {
  return render(
    <SidebarFooter
      nome="Wagner Rocha"
      email="wagner@oimpresso.com.br"
      cargo="Administrador"
      iniciais="WR"
      superadminItems={[]}
      userMenuItems={[]}
    />,
  );
}

const linksDeLogout = () =>
  Array.from(document.querySelectorAll('a')).filter((a) => a.getAttribute('href') === '/logout');

function abrirMenu() {
  fireEvent.click(screen.getByRole('button', { name: /wagner rocha/i }));
  return document.querySelector('.user-menu') as HTMLElement;
}

describe('Sair — pede confirmação antes de encerrar', () => {
  it('antes do clique não há NENHUM caminho de logout no menu; Sair é um botão', () => {
    renderFooter();
    const menu = abrirMenu();

    const sair = within(menu).getByRole('button', { name: /^sair$/i });
    expect(sair.tagName).toBe('BUTTON');
    expect(linksDeLogout()).toHaveLength(0);
  });

  it('clicar Sair abre a pergunta inline com Encerrar (logout real) e Cancelar', () => {
    renderFooter();
    const menu = abrirMenu();

    fireEvent.click(within(menu).getByRole('button', { name: /^sair$/i }));

    const grupo = within(menu).getByRole('group', { name: /encerrar a sessão\?/i });
    expect(grupo.textContent).toContain('Encerrar a sessão?');

    const encerrar = within(grupo).getByRole('link', { name: /encerrar/i });
    expect(encerrar.getAttribute('href')).toBe('/logout');
    expect(linksDeLogout()).toHaveLength(1);

    expect(within(grupo).getByRole('button', { name: /cancelar/i })).toBeTruthy();
    expect(within(menu).queryByRole('button', { name: /^sair$/i })).toBeNull();
  });

  it('Cancelar volta ao menu sem efeito — menu aberto, Sair de volta, zero logout', () => {
    renderFooter();
    const menu = abrirMenu();

    fireEvent.click(within(menu).getByRole('button', { name: /^sair$/i }));
    fireEvent.click(within(menu).getByRole('button', { name: /cancelar/i }));

    expect(document.querySelector('.user-menu')).not.toBeNull();
    expect(within(menu).getByRole('button', { name: /^sair$/i })).toBeTruthy();
    expect(within(menu).queryByRole('group', { name: /encerrar a sessão\?/i })).toBeNull();
    expect(linksDeLogout()).toHaveLength(0);
  });

  it('controle negativo: com a pergunta aberta, clicar FORA fecha o menu sem encerrar', () => {
    renderFooter();
    const menu = abrirMenu();

    fireEvent.click(within(menu).getByRole('button', { name: /^sair$/i }));
    expect(linksDeLogout()).toHaveLength(1);

    fireEvent.mouseDown(document.body);

    expect(document.querySelector('.user-menu')).toBeNull();
    expect(linksDeLogout()).toHaveLength(0);

    // Reabrir não herda a pergunta — volta ao estado inicial.
    const reaberto = abrirMenu();
    expect(within(reaberto).getByRole('button', { name: /^sair$/i })).toBeTruthy();
    expect(linksDeLogout()).toHaveLength(0);
  });
});
