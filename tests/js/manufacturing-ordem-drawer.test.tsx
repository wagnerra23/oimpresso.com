// UC-OP-11 · Manufacturing/Index — o painel da ordem monta dentro do `.cockpit`.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// O painel é um portal. No <body>, fora do `.cockpit`, os tokens do Design System chegam
// vazios: medido em produção em 2026-10-19, a caixa dos ingredientes e o quadro do custo
// sumiam (borda com 0px), os cinzas viravam brancos e a fonte caía para a do sistema. Pôr a
// classe `mfg-root` no painel não resolvia: ela faz os seletores do bundle alcançarem o
// painel, mas os valores que eles usam moram no `.cockpit`. A correção é a prop `container`.
//
// ── O QUE ELE MEDE (componente REAL) ─────────────────────────────────────────
// Onde o painel fica na árvore — que é o que decide se os tokens chegam. O 1º caso é o
// CONTROLE: sem `.cockpit` na página o painel cai no <body>. Se o harness não distinguisse os
// dois lugares, os dois casos sairiam iguais e o 2º não provaria nada.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre cor, borda ou fonte: o jsdom não resolve `var(--…)` de folha de estilo. Os
//     valores foram medidos no navegador (Index-visual-comparison.md, 2026-10-19) e se medem
//     de novo no smoke pós-merge (LC-30).
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/manufacturing-ordem-drawer.test.tsx
// @see resources/js/Pages/Manufacturing/Index.casos.md (UC-OP-11)

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import OrdemDrawer, { type OrdemDetalhe, type OrdemResumo } from '@/Pages/Manufacturing/_components/OrdemDrawer';

const ordem = (final: number): OrdemResumo => ({
  id: 7,
  ref_no: 'MFG2026/0041',
  transaction_date: '18/08/2026',
  location_name: 'Matriz',
  produto: 'Adesivo vinil recortado',
  unidade: 'm²',
  quantidade: 15,
  perdidas: 0,
  criado_por: 'Wagner',
  mfg_is_final: final,
});

const detalhe = (finalizada: boolean): OrdemDetalhe => ({
  id: 7,
  ref_no: 'MFG2026/0041',
  finalizada,
  quantidade: 15,
  unidade: 'm²',
  linhas: [
    { nome: 'Vinil adesivo brilho', sku: 'PROD-022', grupo: 'Substrato', quantidade: 16.8, unidade: 'm²', custo_unitario: 5.8, subtotal: 97.44 },
    { nome: 'Tinta solvente CMYK', sku: 'INS-022', grupo: 'Tinta', quantidade: 0.42, unidade: 'L', custo_unitario: 108, subtotal: 45.36 },
  ],
  custo: { ingredientes: 142.8, extra: 0, total_hoje: 142.8, gravado: 140, por_unidade: 9.52 },
});

const painel = () => document.querySelector<HTMLElement>('[data-contract="drawer"]');

afterEach(() => cleanup());

describe('UC-OP-11 · o painel da ordem monta dentro do .cockpit', () => {
  it('UC-OP-11 controle: sem .cockpit na página, o painel cai no body', () => {
    render(<OrdemDrawer ordem={ordem(0)} detalhe={detalhe(false)} onClose={() => {}} />);
    expect(painel()).not.toBeNull();
    expect(painel()!.closest('.cockpit')).toBeNull();
  });

  it('UC-OP-11 com .cockpit na página, o painel monta dentro dele', () => {
    render(
      <div className="cockpit">
        <OrdemDrawer ordem={ordem(0)} detalhe={detalhe(false)} onClose={() => {}} />
      </div>,
    );
    expect(painel()).not.toBeNull();
    expect(painel()!.closest('.cockpit')).not.toBeNull();
  });

  it('UC-OP-11 o fechar do canto é o × do protótipo, sem o "Close" em inglês do Sheet', () => {
    render(
      <div className="cockpit">
        <OrdemDrawer ordem={ordem(0)} detalhe={detalhe(false)} onClose={() => {}} />
      </div>,
    );
    expect(screen.queryByText('Close')).toBeNull();
    // Dois "Fechar", como no protótipo: o × do canto e o botão do rodapé.
    const fechar = screen.getAllByRole('button', { name: 'Fechar' });
    expect(fechar).toHaveLength(2);
    expect(fechar.some((b) => b.textContent === '×')).toBe(true);
  });

  it('UC-OP-11 rascunho mostra "Editar ordem" com o lápis; finalizada não mostra', () => {
    const { unmount } = render(
      <div className="cockpit">
        <OrdemDrawer ordem={ordem(0)} detalhe={detalhe(false)} onClose={() => {}} />
      </div>,
    );
    const editar = screen.getByRole('link', { name: 'Editar ordem' });
    expect(editar.getAttribute('href')).toBe('/manufacturing/production/7/edit');
    expect(editar.querySelector('svg')).not.toBeNull();
    unmount();

    render(
      <div className="cockpit">
        <OrdemDrawer ordem={ordem(1)} detalhe={detalhe(true)} onClose={() => {}} />
      </div>,
    );
    expect(screen.queryByRole('link', { name: 'Editar ordem' })).toBeNull();
  });
});
