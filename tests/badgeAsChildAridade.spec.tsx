// Badge · `asChild` entrega UM filho ao Slot (regressão de aridade, 2026-09-01)
//
// POR QUE EXISTE
// --------------
// O #6510 deu ao `Badge` a 3ª perna do AP7 (o dot) e tentou proteger o `asChild`
// com `{dot && !asChild && <span/>}{children}`. A intenção estava certa; a forma,
// não. Com DUAS expressões irmãs no corpo do JSX, `children` chega ao componente
// como ARRAY — `[false, children]` quando a guarda é falsa. `Slot.Root` roda
// `React.Children.only`, que exige um ELEMENTO e não um array, e lança:
//
//   Uncaught Error: React.Children.only expected to receive a single React element child.
//
// Em produção isso não degrada: derruba o render da página inteira. Medido no
// visual-regression de 2026-09-01 — `/ia`, `/ia/conversa` e `/ia/memoria` pararam
// de exibir QUALQUER texto (as 3 telas que renderizam `JanaPlanoBadge`, o único
// `<Badge asChild>` do repo; `Jana/Pro`, que não o renderiza, seguiu passando).
//
// O defeito é de ARIDADE, não do dot: `dot` era `false` nas 3 telas. Por isso o
// teste abaixo cobre justamente o caso SEM dot — que é o que quebrava — e não só
// o caso com dot, que parece o suspeito e nunca esteve envolvido.
//
// BITE-TEST (feito antes de commitar, não presumido): revertendo o corpo do
// `Badge` para a forma do #6510, o 1º caso abaixo falha com a mensagem do
// `Children.only`; com a correção, os 4 passam.

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { Badge } from '@/Components/ui/badge';

describe('Badge · aridade do asChild', () => {
  it('asChild SEM dot renderiza o filho (era o crash: children virava array)', () => {
    render(
      <Badge asChild variant="info">
        <button type="button" data-testid="alvo">plano Grátis</button>
      </Badge>,
    );

    const alvo = screen.getByTestId('alvo');
    expect(alvo.tagName).toBe('BUTTON');
    expect(alvo.textContent).toContain('plano Grátis');
    // O Slot funde as props do Badge no filho — prova que delegou, não envolveu.
    expect(alvo.getAttribute('data-slot')).toBe('badge');
  });

  it('asChild COM dot também entrega um filho só (o dot não entra no Slot)', () => {
    render(
      <Badge asChild dot variant="success">
        <button type="button" data-testid="alvo-dot">ativo</button>
      </Badge>,
    );

    const alvo = screen.getByTestId('alvo-dot');
    expect(alvo.tagName).toBe('BUTTON');
    // Contrato do Radix: sob asChild o dot NÃO é injetado — seria um 2º filho.
    expect(alvo.querySelector('[data-slot="badge-dot"]')).toBeNull();
  });

  it('sem asChild e com dot, o dot aparece (a perna do AP7 segue viva)', () => {
    const { container } = render(<Badge dot variant="warning">pendente</Badge>);

    expect(container.querySelector('[data-slot="badge-dot"]')).not.toBeNull();
    expect(container.textContent).toContain('pendente');
  });

  it('sem asChild e sem dot, nada de dot — o default não vira ruído', () => {
    const { container } = render(<Badge variant="neutral">rótulo</Badge>);

    expect(container.querySelector('[data-slot="badge-dot"]')).toBeNull();
    expect(container.textContent).toContain('rótulo');
  });
});
