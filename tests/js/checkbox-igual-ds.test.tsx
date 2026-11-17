/**
 * `ui/checkbox` = o `Checkbox` do DS (`prototipo-ui/design-system/components/Checkbox/Checkbox.jsx`).
 *
 * Medido no navegador em 2026-11-12, a caixinha do app ao lado da do DS, no `.cockpit` claro e
 * escuro: 16×16, canto 6px, borda 1,5px `--border` sobre `--surface` desmarcada, `--accent` marcada,
 * ✓ de 11px com traço 3,5, opacidade .55 desabilitada — iguais. Antes a borda era roxa até desmarcada.
 *
 * O vitest roda em jsdom, sem CSS: aqui se prova a CLASSE que produz cada medida e o desenho do ✓.
 */
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Checkbox } from '@/Components/ui/checkbox';

const tokens = (el: Element) => (el.getAttribute('class') ?? '').split(/\s+/);

describe('Checkbox do app = Checkbox do DS', () => {
  it('desmarcada: borda de 1,5px cinza (--border) sobre --surface, sem a borda roxa nem sombra', () => {
    render(<Checkbox aria-label="x" />);
    const t = tokens(screen.getByRole('checkbox', { name: 'x' }));
    expect(t).toEqual(expect.arrayContaining([
      'h-4', 'w-4', 'rounded-sm', 'border-[1.5px]',
      'border-[var(--border,var(--color-border))]', 'bg-[var(--surface,var(--color-background))]',
      'disabled:opacity-[.55]', 'duration-150',
    ]));
    expect(t).not.toContain('border-primary');
    expect(t).not.toContain('shadow-sm');
  });

  it('marcada: fundo e borda --accent e o ✓ do DS (11px, traço 3,5) na cor --accent-fg', () => {
    render(<Checkbox aria-label="x" checked />);
    const caixa = screen.getByRole('checkbox', { name: 'x' });
    expect(tokens(caixa)).toEqual(expect.arrayContaining([
      'data-[state=checked]:border-[var(--accent,var(--color-primary))]',
      'data-[state=checked]:bg-[var(--accent,var(--color-primary))]',
    ]));
    const svg = caixa.querySelector('svg')!;
    expect(svg.getAttribute('width')).toBe('11');
    expect(svg.getAttribute('stroke-width')).toBe('3.5');
    expect(svg.querySelector('path')!.getAttribute('d')).toBe('M5 12l5 5 9-9');
    expect(tokens(svg.parentElement!)).toContain('text-[var(--accent-fg,var(--color-primary-foreground))]');
  });

  it('parcial: o mesmo preenchimento do marcado, com tracinho no lugar do ✓', () => {
    render(<Checkbox aria-label="x" checked="indeterminate" />);
    const caixa = screen.getByRole('checkbox', { name: 'x' });
    expect(caixa.getAttribute('aria-checked')).toBe('mixed');
    expect(tokens(caixa)).toContain('data-[state=indeterminate]:bg-[var(--accent,var(--color-primary))]');
    expect(caixa.querySelector('path')!.getAttribute('d')).toBe('M6 12h12');
  });
});
