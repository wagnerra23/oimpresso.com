// Manufacturing — a moldura comum das gavetas (`_components/Gaveta.tsx`): ordem, receita, insumo.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Em 2026-10-19 as gavetas de Receitas e Insumos ainda desenhavam a moldura antiga do bundle
// (`.mfg-drw`, montada na própria página) enquanto a da ordem já usava a do protótipo. As três
// passaram a usar `Gaveta.tsx`. Dois fatos que o código precisa manter:
//   1. o portal monta DENTRO do `.cockpit` (é dele que vêm os tokens e a fonte do DS);
//   2. o `Esc` chama `onClose` UMA vez. A aba Insumos tinha um listener próprio de `Esc` que,
//      somado ao do Sheet, fazia duas visitas ao servidor por tecla.
//
// ── O QUE **NÃO** PROVA ──────────────────────────────────────────────────────
//   - Nada sobre cor, borda ou fonte: o jsdom não resolve `var(--…)`. A comparação com o
//     protótipo foi medida no navegador (Recipes/Insumos-visual-comparison.md, 2026-10-19).
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/manufacturing-gaveta.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import Gaveta, { SecaoGaveta } from '@/Pages/Manufacturing/_components/Gaveta';

afterEach(() => cleanup());

const montar = (onClose: () => void) =>
  render(
    <div className="cockpit">
      <Gaveta aberta onClose={onClose} titulo="Vinil adesivo brilho" subtitulo="PROD-022" rodape={<button type="button">Fechar</button>}>
        <SecaoGaveta titulo="Receitas afetadas">
          <p>conteúdo</p>
        </SecaoGaveta>
      </Gaveta>
    </div>,
  );

describe('Gaveta da Fabricação', () => {
  it('monta dentro do .cockpit, com título, seção e rodapé', () => {
    montar(() => {});
    const painel = document.querySelector<HTMLElement>('[data-contract="drawer"]');
    expect(painel).not.toBeNull();
    expect(painel!.closest('.cockpit')).not.toBeNull();
    expect(screen.getByRole('dialog', { name: 'Vinil adesivo brilho' })).toBe(painel);
    expect(screen.getByRole('heading', { name: 'Receitas afetadas' })).toBeTruthy();
    // o × do canto e o "Fechar" do rodapé
    expect(screen.getAllByRole('button', { name: 'Fechar' })).toHaveLength(2);
  });

  it('Esc chama onClose uma vez só', () => {
    const onClose = vi.fn();
    montar(onClose);
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
