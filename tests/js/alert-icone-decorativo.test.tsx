/**
 * `ui/alert` — o ícone do alerta é DECORATIVO (playbook `ds-atomos` thread 06, achado 2 ·
 * decisão [W] 2026-09-28).
 *
 * "Ícone" = `svg` filho direto do `Alert`, o mesmo critério que o layout do componente já usa
 * (`has-[>svg]`). Os casos abaixo cobrem as três formas que o repo usa de fato — `lucide-react`,
 * o wrapper `@/Components/Icon` e `svg` cru — e as quatro formas de dar NOME a um ícone, que
 * têm de ser respeitadas.
 */
import * as React from 'react';
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Icon } from '@/Components/Icon';

const svgDireto = (c: HTMLElement) => c.querySelector('[data-slot="alert"] > svg') as SVGElement;

describe('ícone decorativo — as formas que o repo usa', () => {
  it('lucide-react vira aria-hidden + focusable=false', () => {
    const { container } = render(
      <Alert>
        <TriangleAlert />
        <AlertTitle>Certificado vence em 5 dias</AlertTitle>
      </Alert>,
    );
    const svg = svgDireto(container);
    expect(svg.getAttribute('aria-hidden')).toBe('true');
    expect(svg.getAttribute('focusable')).toBe('false');
  });

  it('o wrapper @/Components/Icon também', () => {
    const { container } = render(
      <Alert>
        <Icon name="info" />
        <AlertDescription>Sem movimento no período.</AlertDescription>
      </Alert>,
    );
    expect(svgDireto(container).getAttribute('aria-hidden')).toBe('true');
  });

  it('e um svg cru', () => {
    const { container } = render(
      <Alert>
        <svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z" /></svg>
        <AlertTitle>Aviso</AlertTitle>
      </Alert>,
    );
    expect(svgDireto(container).getAttribute('aria-hidden')).toBe('true');
  });

  it('o alerta continua anunciado pelo texto, sem figura anônima', () => {
    render(
      <Alert>
        <TriangleAlert />
        <AlertTitle>Certificado vence em 5 dias</AlertTitle>
      </Alert>,
    );
    const alerta = screen.getByRole('alert');
    expect(alerta.textContent).toBe('Certificado vence em 5 dias');
    expect(screen.queryByRole('img')).toBeNull();
  });
});

describe('ícone com NOME é respeitado', () => {
  it.each([
    ['aria-label', { 'aria-label': 'Atenção' }],
    ['aria-labelledby', { 'aria-labelledby': 'x' }],
    ['role=img', { role: 'img' }],
    ['aria-hidden="false" explícito', { 'aria-hidden': 'false' }],
  ] as const)('%s fica intocado', (_nome, attrs) => {
    const { container } = render(
      <Alert>
        <svg viewBox="0 0 24 24" {...(attrs as Record<string, string>)}><path d="M0 0" /></svg>
        <AlertTitle>Aviso</AlertTitle>
      </Alert>,
    );
    const svg = svgDireto(container);
    expect(svg.getAttribute('focusable')).toBeNull();
    if (!('aria-hidden' in attrs)) expect(svg.getAttribute('aria-hidden')).toBeNull();
    else expect(svg.getAttribute('aria-hidden')).toBe('false');
  });

  it('svg com <title> fica intocado', () => {
    const { container } = render(
      <Alert>
        <svg viewBox="0 0 24 24"><title>Atenção</title><path d="M0 0" /></svg>
        <AlertTitle>Aviso</AlertTitle>
      </Alert>,
    );
    expect(svgDireto(container).getAttribute('aria-hidden')).toBeNull();
  });

  it('svg que NÃO é filho direto (dentro da descrição) fica intocado', () => {
    const { container } = render(
      <Alert>
        <AlertDescription>
          Veja <svg data-testid="interno" viewBox="0 0 24 24"><path d="M0 0" /></svg> o gráfico.
        </AlertDescription>
      </Alert>,
    );
    expect(container.querySelector('[data-testid="interno"]')!.getAttribute('aria-hidden')).toBeNull();
  });
});

describe('ciclo de vida e contrato do componente', () => {
  it('ícone que entra num re-render também é marcado', () => {
    const Caso = ({ comIcone }: { comIcone: boolean }) => (
      <Alert>
        {comIcone && <TriangleAlert />}
        <AlertTitle>Aviso</AlertTitle>
      </Alert>
    );
    const { container, rerender } = render(<Caso comIcone={false} />);
    expect(svgDireto(container)).toBeNull();
    rerender(<Caso comIcone />);
    expect(svgDireto(container).getAttribute('aria-hidden')).toBe('true');
  });

  it('ref de objeto e ref de callback recebem o elemento', () => {
    const obj = React.createRef<HTMLDivElement>();
    let cb: HTMLDivElement | null = null;
    render(
      <>
        <Alert ref={obj}>
          <AlertTitle>A</AlertTitle>
        </Alert>
        <Alert ref={(el) => { cb = el; }}>
          <AlertTitle>B</AlertTitle>
        </Alert>
      </>,
    );
    expect(obj.current?.getAttribute('data-slot')).toBe('alert');
    expect((cb as HTMLDivElement | null)?.textContent).toBe('B');
  });

  it('role="alert", data-slot e as classes de variante seguem iguais', () => {
    const { container } = render(<Alert variant="destructive"><AlertTitle>X</AlertTitle></Alert>);
    const el = container.firstElementChild!;
    expect(el.getAttribute('role')).toBe('alert');
    expect(el.getAttribute('data-slot')).toBe('alert');
    expect(el.className).toContain('text-destructive');
  });
});
