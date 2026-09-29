/**
 * Sidebar — ordem dos itens DENTRO de um grupo.
 *
 * Âncora: `prototipo-ui/cowork/Wagner/data.jsx`, grupo RH: `ponto` → `hrm` → `essenciais`.
 * O protótipo é soberano no eixo FORMA (ADR UI-0029).
 *
 * Por que existe: em produção (biz=1, 2026-09-29) o Ponto aparecia DEPOIS de HRM e Essenciais.
 * O `shell.menu` chega na ordem em que os módulos registram o menu — o `->order(N)` dos
 * DataControllers não passa pelo LegacyMenuAdapter (`getItems()`). Mudar o `order` do Ponto
 * (#8116) não teve efeito; o teste PHP daquele PR só comparava com o HRM quando o HRM estava
 * no menu, e no CI ele não está — por isso a falha passou. Aqui a ordem é medida no RENDER.
 *
 * Rótulos escritos à mão de propósito: importá-los do componente tornaria o caso tautológico
 * (§5 2026-06-05).
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

/** Na ORDEM DE REGISTRO medida em produção: HRM e Essenciais antes do Ponto. */
const MENU: ShellMenuItem[] = [
  { label: 'Visão geral', href: '/home', group: 'landing' },
  { label: 'Vendas', href: '/sells' },
  { label: 'Crm', href: '/crm' },
  { label: 'HRM', href: '/hrm/dashboard' },
  { label: 'Essenciais', href: '/essentials' },
  { label: 'Ponto', href: '/ponto' },
];

/** Rótulos dos itens do grupo, na ordem em que aparecem. Abre o grupo se vier fechado. */
function itensDoGrupo(rotulo: string): string[] {
  const grupo = Array.from(document.querySelectorAll<HTMLElement>('.sb-group')).find(
    (g) => g.querySelector('.sb-group-l')?.textContent?.trim() === rotulo
  );
  expect(grupo, `grupo ${rotulo} não renderizou`).toBeTruthy();
  const ler = () =>
    Array.from(grupo!.querySelectorAll('.sb-item .label')).map((el) => el.textContent?.trim() ?? '');
  if (ler().length === 0) fireEvent.click(grupo!.querySelector('.sb-group-h')!);
  return ler();
}

beforeEach(() => localStorage.clear());

describe('Sidebar · ordem dentro do grupo (design: data.jsx)', () => {
  it('UC-SB-14 · no grupo RH o Ponto vem primeiro, depois HRM e Essenciais', () => {
    render(<SidebarMenu items={MENU} />);
    expect(itensDoGrupo('RH')).toEqual(['Ponto', 'HRM', 'Essenciais']);
  });

  it('UC-SB-14 · grupo sem ordem declarada mantém a ordem em que os itens chegaram', () => {
    // Controle: a ordem explícita é opt-in por grupo. COMERCIAL não declara `ordem`,
    // então Vendas continua antes do Crm, como no menu entregue.
    render(<SidebarMenu items={MENU} />);
    expect(itensDoGrupo('COMERCIAL')).toEqual(['Vendas', 'Crm']);
  });
});
