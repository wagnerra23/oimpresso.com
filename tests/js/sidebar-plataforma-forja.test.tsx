/**
 * Sidebar — o grupo PLATAFORMA e a Forja dentro dele.
 *
 * Âncora: `prototipo-ui/cowork/Wagner/data.jsx` (grupo `PLATAFORMA`, entry `projects`
 * label "Forja") + `prototipo-ui/cowork/Wagner/sidebar.jsx` (o accordion abre com
 * `return entry.group !== "PLATAFORMA"`). Conferidos contra o Cowork VIVO por
 * ID em 2026-09-08 (`DesignSync.get_file` de `data.jsx`, `truncated: false`).
 * Contrato do shell: `resources/js/Layouts/AppShellV2.charter.md`.
 *
 * Por que RENDER e não leitura de constante: o que [W] pediu — "colocar a Forja
 * na PLATAFORMA" — é sobre o que APARECE no menu, e três camadas independentes
 * decidem isso (o `group` que o DataController declara, o `findGroupKey` que o
 * resolve, e o `hasVisibleItem` que decide se o cabeçalho sai). Assertar a
 * constante `SIDEBAR_GROUPS` passaria com qualquer uma das três quebrada.
 *
 * Os rótulos abaixo são escritos à mão de propósito: importá-los do componente
 * tornaria o caso tautológico (§5 2026-06-05).
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

/** Menu mínimo com um item em cada grupo que os casos citam. */
const MENU: ShellMenuItem[] = [
  { label: 'Visão geral', href: '/home', group: 'landing' },
  { label: 'Clientes', href: '/contacts?type=customer', group: 'cadastro' },
  { label: 'Auditoria', href: '/auditoria', group: 'sistema' },
  // O que o DataController da Forja declara desde 2026-09-08.
  { label: 'Forja', href: '/forja', group: 'plataforma' },
];

const cabecalhosDeGrupo = (): string[] =>
  Array.from(document.querySelectorAll('.sb-group-h .sb-group-l')).map(
    (el) => el.textContent?.trim() ?? ''
  );

/** Botão de cabeçalho de um grupo, pelo rótulo visível. */
const cabecalho = (rotulo: string): HTMLButtonElement | undefined =>
  Array.from(document.querySelectorAll<HTMLButtonElement>('.sb-group-h')).find(
    (b) => b.querySelector('.sb-group-l')?.textContent?.trim() === rotulo
  );

beforeEach(() => {
  // O accordion persiste `expanded` por grupo em localStorage; sem limpar, o
  // caso do "fechado por default" leria a preferência do caso anterior.
  localStorage.clear();
});

describe('Sidebar · grupo PLATAFORMA (design: data.jsx + sidebar.jsx)', () => {
  it('renderiza a Forja como item do grupo PLATAFORMA', () => {
    render(<SidebarMenu items={MENU} />);

    expect(cabecalhosDeGrupo()).toContain('PLATAFORMA');
    // O grupo nasce fechado (caso abaixo), então o item só aparece ao abrir —
    // é o caminho que o usuário percorre.
    fireEvent.click(cabecalho('PLATAFORMA')!);

    const link = screen.getByRole('link', { name: /Forja/ });
    expect(link.getAttribute('href')).toBe('/forja');
    // O link mora DENTRO do bloco do grupo — não solto no menu.
    const grupo = link.closest('.sb-group');
    expect(grupo?.querySelector('.sb-group-l')?.textContent?.trim()).toBe('PLATAFORMA');
  });

  it('é NEUTRO — sem hue, como o `hue: null` do GROUP_META do design', () => {
    render(<SidebarMenu items={MENU} />);

    const plataforma = cabecalho('PLATAFORMA')!.closest('.sb-group') as HTMLElement;
    expect(plataforma.style.getPropertyValue('--gh')).toBe('');
    // O ícone do grupo (`.sb-group-ic`, não o chevron) sai sem `color` inline.
    const icone = cabecalho('PLATAFORMA')!.querySelector<SVGElement>('.sb-group-ic');
    expect(icone).not.toBeNull();
    expect(icone!.style.color).toBe('');

    // Controle positivo: um grupo COM hue declarado pinta — sem isto, os casos
    // acima passariam mesmo que a coloração tivesse parado de funcionar.
    const cadastro = cabecalho('CADASTRO')!.closest('.sb-group') as HTMLElement;
    expect(cadastro.style.getPropertyValue('--gh')).not.toBe('');
    expect(
      cabecalho('CADASTRO')!.querySelector<SVGElement>('.sb-group-ic')!.style.color
    ).not.toBe('');
  });

  it('PLATAFORMA é o ÚLTIMO grupo e vem depois de SISTEMA', () => {
    render(<SidebarMenu items={MENU} />);

    const grupos = cabecalhosDeGrupo();
    expect(grupos[grupos.length - 1]).toBe('PLATAFORMA');
    expect(grupos.indexOf('SISTEMA')).toBeLessThan(grupos.indexOf('PLATAFORMA'));
  });

  it('nasce FECHADO — e só ele (os canon nascem abertos)', () => {
    render(<SidebarMenu items={MENU} />);

    expect(cabecalho('PLATAFORMA')?.getAttribute('aria-expanded')).toBe('false');
    expect(cabecalho('CADASTRO')?.getAttribute('aria-expanded')).toBe('true');
    expect(cabecalho('SISTEMA')?.getAttribute('aria-expanded')).toBe('true');
  });

  it('a Forja NÃO fica também nos atalhos de topo (o design tem três, sem ela)', () => {
    render(<SidebarMenu items={MENU} />);

    const atalhos = Array.from(
      document.querySelectorAll<HTMLAnchorElement>('.sb-shortcuts .sb-shortcut')
    );
    expect(atalhos.map((a) => a.textContent?.trim())).toEqual([
      'IA',
      'Visão geral',
      'Atendimento',
    ]);
    // Anti-duplicação (ADR UI-0013): um destino, uma porta no menu.
    expect(atalhos.filter((a) => a.getAttribute('href') === '/forja')).toHaveLength(0);
  });

  it('no modo rail os atalhos são os MESMOS três do expandido', () => {
    // O rail tinha um 3º botão apontando /team-mcp/team ("Equipe") enquanto o
    // expandido apontava /forja ("Forja") — os dois modos discordavam.
    render(<SidebarMenu items={MENU} mode="rail" />);

    const botoes = Array.from(
      document.querySelectorAll<HTMLAnchorElement>('.sb-menu-rail > a.sb-rail-btn')
    );
    expect(botoes.map((a) => a.getAttribute('data-tip'))).toEqual([
      'IA',
      'Visão geral',
      'Atendimento',
    ]);
  });
});
