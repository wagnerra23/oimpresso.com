/**
 * ModuleTabs — as abas de topo de um módulo saem da MESMA lista que o sidebar mostra.
 *
 * Pedido [W] 2026-11-06 ("deveria ser o sidebar e as abas iguais?"): uma fonte só, os `ghosts` do
 * item do módulo no `shell.menu`. Rótulos e rotas escritos à mão de propósito (§5 2026-06-05).
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

let pagina: { url: string; props: unknown } = { url: '/', props: {} };
vi.mock('@inertiajs/react', () => ({
  usePage: () => pagina,
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
  router: { visit: vi.fn(), get: vi.fn(), reload: vi.fn() },
}));

import ModuleTabs from '@/Components/shared/ModuleTabs';
import { ghostAtivo } from '@/Lib/ghost-ativo';

const REPAIR = {
  label: 'Assistência técnica',
  href: '/repair/dashboard',
  group: 'producao',
  primary: { label: 'Nova OS', href: '/repair/repair/create' },
  ghosts: [
    { key: 'dashboard', label: 'Dashboard', href: '/repair/dashboard' },
    { key: 'repair', label: 'OS de Reparo', href: '/repair/repair' },
    { key: 'job-sheet', label: 'Job Sheets', href: '/repair/job-sheet' },
  ],
};
const comMenu = (url: string, menu: unknown[]) => {
  pagina = { url, props: { shell: { menu } } };
};

afterEach(() => {
  pagina = { url: '/', props: {} };
});

describe('ModuleTabs', () => {
  it('desenha os ghosts do item, na ordem do menu, e marca a aba da rota atual', () => {
    comMenu('/repair/job-sheet', [{ label: 'Outro', ghosts: [{ key: 'x', label: 'X', href: '/x' }] }, REPAIR]);
    render(<ModuleTabs modulo="Assistência técnica" />);

    const abas = screen.getAllByRole('tab');
    expect(abas.map((a) => a.textContent?.trim())).toEqual(['Dashboard', 'OS de Reparo', 'Job Sheets']);
    expect(screen.getByRole('tab', { name: /Job Sheets/ }).getAttribute('aria-selected')).toBe('true');
    expect(screen.getByRole('tab', { name: /Dashboard/ }).getAttribute('aria-selected')).toBe('false');
    // não puxa a lista de outro módulo
    expect(screen.queryByRole('tab', { name: 'X' })).toBeNull();
  });

  it('acha o item sem diferenciar maiúscula, e aceita apelido', () => {
    comMenu('/repair/dashboard', [REPAIR]);
    render(<ModuleTabs modulo={['Repair', 'assistência TÉCNICA']} />);
    expect(screen.getAllByRole('tab')).toHaveLength(3);
  });

  it('sem item no menu, ou item sem ghosts, não renderiza nada', () => {
    comMenu('/repair/dashboard', [{ label: 'Assistência técnica', href: '/repair/dashboard' }]);
    const { container } = render(<ModuleTabs modulo="Assistência técnica" />);
    expect(container.innerHTML).toBe('');

    comMenu('/repair/dashboard', []);
    const vazio = render(<ModuleTabs modulo="Assistência técnica" />);
    expect(vazio.container.innerHTML).toBe('');
  });

  it('o primário do item só aparece quando pedido', () => {
    comMenu('/repair/dashboard', [REPAIR]);
    const sem = render(<ModuleTabs modulo="Assistência técnica" />);
    expect(sem.container.textContent).not.toContain('Nova OS');
    sem.unmount();

    render(<ModuleTabs modulo="Assistência técnica" showPrimary />);
    expect(document.body.textContent).toContain('Nova OS');
  });
});

describe('ghostAtivo', () => {
  const g = [
    { key: 'lista', label: 'Lista', href: '/oficina-auto/ordens-servico' },
    { key: 'quadro', label: 'Quadro', href: '/oficina-auto/ordens-servico/board' },
  ];
  it('vence o caminho mais longo, inclusive em sub-rota', () => {
    expect(ghostAtivo(g, '/oficina-auto/ordens-servico/board')).toBe('quadro');
    expect(ghostAtivo(g, '/oficina-auto/ordens-servico/42')).toBe('lista');
    expect(ghostAtivo(g, '/oficina-auto/ordens-servico?status=aberta')).toBe('lista');
  });
  it('controle negativo: rota de fora não marca aba, nem por prefixo de texto', () => {
    expect(ghostAtivo(g, '/oficina-auto/veiculos')).toBeUndefined();
    expect(ghostAtivo(g, '/oficina-auto/ordens-servico-antigas')).toBeUndefined();
  });
});
