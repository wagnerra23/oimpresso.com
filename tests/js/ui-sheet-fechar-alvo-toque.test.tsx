// Sheet (DS) — o X de fechar tem alvo de toque de 44×44px, sem mudar onde o ícone aparece.
//
// Medido em produção em 2026-09-30 (`_saida-19b`, Patrimônio/Manutenções): o X media 16×16px —
// só o ícone, sem área de clique em volta —, abaixo do alvo de 44px. O mesmo `Sheet` serve 41
// telas. O conserto: `size-11` (44px) com `-m-3.5` (−14px), que mantém o CENTRO do ícone onde
// estava: antes `top-4` + metade de 16px = 24px da borda; depois 16 − 14 + metade de 44 = 24px.
//
// ⚠️ O jsdom não calcula layout, então este teste fixa as CLASSES que produzem a medida. A
// medida em pixels é feita no smoke de produção depois do deploy (getBoundingClientRect).

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';

afterEach(() => cleanup());

function abrir(showCloseButton?: boolean) {
  render(
    <Sheet open>
      <SheetContent side="right" showCloseButton={showCloseButton}>
        <SheetTitle>Título</SheetTitle>
        <SheetDescription>Descrição</SheetDescription>
      </SheetContent>
    </Sheet>,
  );
}

describe('Sheet · X de fechar', () => {
  it('a área de clique é 44px e o ícone continua no mesmo centro (top-4/right-4 com -m-3.5)', () => {
    abrir();
    const x = screen.getByRole('button', { name: /close|fechar/i });
    const classes = x.className.split(/\s+/);
    for (const c of ['size-11', '-m-3.5', 'top-4', 'right-4', 'inline-flex', 'items-center', 'justify-center']) {
      expect(classes).toContain(c);
    }
    // O ícone em si segue com 16px — muda a área, não o desenho.
    expect(x.querySelector('svg')?.getAttribute('class')).toContain('size-4');
  });

  it('controle: com showCloseButton={false} não há X', () => {
    abrir(false);
    expect(screen.queryByRole('button', { name: /close|fechar/i })).toBeNull();
  });
});
