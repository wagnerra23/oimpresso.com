// Governance · /governance/ds-rollout — o que a TELA faz com a prop `census`, e o censo de verdade.
//
// Casos: resources/js/Pages/governance/DsRollout.casos.md (UC-DSR-01..07).
// Derivado do .casos.md (o "Pronto quando" de cada UC), não do .tsx.
// O lado servidor (rota, fallback do Controller, contrato da prop) é do Pest
// Modules/Governance/Tests/Feature/DsRolloutControllerTest.php.
//
// Importa a Page REAL; mock só na casca (shell, header, subnav).
//
// Comando: npx vitest run tests/js/governance-ds-rollout.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  router: { get: vi.fn(), post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
  Head: () => null,
  Link: ({ children, ...p }: any) => <a {...p}>{children}</a>,
}));
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }));
vi.mock('@/Pages/governance/_shared/GovernancaSubNav', () => ({ default: () => null }));

import DsRollout from '@/Pages/governance/DsRollout';

afterEach(() => cleanup());

const linha = (screen: string, tokens: string, prim: string, extra: Record<string, unknown> = {}) => ({
  screen,
  cells: { tokens, primitivos: prim, probe: 'na', dark: 'na', approved: 'no' },
  ...extra,
});

const MEDIDO = {
  ledger: [
    linha('Atendimento', 'ref', 'ref', { reference: true }),
    linha('Produto', 'no', 'no'),
    linha('Vendas', 'yes', 'yes'),
  ],
  progressPct: 50,
  progressLabel: 'adoção tokens + primitivos',
  measured: true,
  measuredAgainstSha: 'abc1234',
  generatedAt: '2026-11-19T10:00:00Z',
  treeGuard: { pass: true, violations: 0 },
  counts: { screens: 2, done: 1, references: 1 },
};

const tabelaDoLedger = (c: HTMLElement) =>
  Array.from(c.querySelectorAll('table')).find((t) => t.textContent?.includes('Probe G1–G13'))!;

describe('DS Rollout · o placar (Ledger)', () => {
  it('UC-DSR-01 · sem medição a tela mostra TODO ledger e 0%; com medição, o carimbo @sha', () => {
    const fallback = { ...MEDIDO, measured: false, measuredAgainstSha: null, generatedAt: null, progressPct: 0 };
    const { container, unmount } = render(<DsRollout census={fallback as any} />);
    expect(container.textContent).toContain('snapshot estático · TODO ledger');
    expect(container.textContent).not.toContain('● medido');
    unmount();

    const { container: c2 } = render(<DsRollout census={MEDIDO as any} />);
    expect(c2.textContent).toContain('● medido @abc1234');
    expect(c2.textContent).not.toContain('TODO ledger');
  });

  it('UC-DSR-03 · probe e dark não medidos aparecem como – com o tooltip, nunca como ✓', () => {
    const { container } = render(<DsRollout census={MEDIDO as any} />);
    const linhas = Array.from(tabelaDoLedger(container).querySelectorAll('tbody tr'));
    for (const tr of linhas) {
      const celulas = tr.querySelectorAll('td');
      for (const idx of [3, 4]) {          // colunas Probe e Dark
        const marca = celulas[idx].querySelector('span')!;
        expect(marca.textContent).toBe('–');
        expect(marca.getAttribute('title')).toContain('não medido por censo estático');
      }
    }
  });

  it('UC-DSR-04 · nº de linhas = telas + referências e a barra tem a largura de progressPct', () => {
    const { container } = render(<DsRollout census={MEDIDO as any} />);
    const linhas = tabelaDoLedger(container).querySelectorAll('tbody tr');
    expect(linhas.length).toBe(MEDIDO.counts.screens + MEDIDO.counts.references);
    // a linha-referência leva ★ e fica destacada
    expect(linhas[0].textContent).toContain('★');
    expect(linhas[0].className).toContain('bg-warning');
    const barra = container.querySelector('div[style*="width"]') as HTMLElement;
    expect(barra.style.width).toBe('50%');
  });

  it('UC-DSR-05 · o badge da árvore segue o treeGuard (✓ canônica × ✗ N violações)', () => {
    const { container, unmount } = render(<DsRollout census={MEDIDO as any} />);
    expect(container.textContent).toContain('árvore Components/: ✓ canônica');
    unmount();

    const falha = { ...MEDIDO, treeGuard: { pass: false, violations: 3 } };
    const { container: c2 } = render(<DsRollout census={falha as any} />);
    expect(c2.textContent).toContain('árvore Components/: ✗ 3 violação(ões)');
    expect(c2.textContent).not.toContain('✓ canônica');
  });

  it('UC-DSR-07 · a tela é só leitura: nenhum botão, formulário ou campo', () => {
    const { container } = render(<DsRollout census={MEDIDO as any} />);
    expect(container.querySelectorAll('button, form, input, select, textarea').length).toBe(0);
  });
});

describe('DS Rollout · o censo e a conformidade da própria tela', () => {
  it('UC-DSR-02 · ds-ledger.mjs --json mede o piloto Produto como fora em tokens e primitivos', () => {
    const out = execFileSync('node', ['scripts/ds-ledger.mjs', '--json'], { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 });
    const census = JSON.parse(out);
    const produto = census.ledger.find((r: any) => r.screen === 'Produto');
    expect(produto, 'linha Produto ausente do censo').toBeTruthy();
    expect(produto.cells.tokens).toBe('no');
    expect(produto.cells.primitivos).toBe('no');
    // e o censo nunca mede probe/dark (UC-DSR-03 do lado do gerador)
    expect(census.ledger.filter((r: any) => !r.reference).every((r: any) => r.cells.probe === 'na' && r.cells.dark === 'na')).toBe(true);
  }, 180_000);

  it('UC-DSR-06 · eslint na própria tela dá zero mensagens ds/*', () => {
    let out = '';
    try {
      // `node <bin>` e não `npx`: no Windows o npx é .cmd e execFileSync não o acha.
      // O bin sai do resolver do node (num worktree o node_modules pode morar acima).
      const bin = join(dirname(createRequire(import.meta.url).resolve('eslint/package.json')), 'bin', 'eslint.js');
      out = execFileSync('node', [bin, 'resources/js/Pages/governance/DsRollout.tsx', '-f', 'json'], { encoding: 'utf8' });
    } catch (e: any) {
      if (!e.stdout) throw e; // não rodou: erro de verdade, não "0 mensagens"
      out = e.stdout;         // eslint sai 1 quando acha problema: o JSON continua no stdout
    }
    const [resultado] = JSON.parse(out);
    const ds = resultado.messages.filter((m: any) => String(m.ruleId ?? '').startsWith('ds/'));
    expect(ds).toEqual([]);
  }, 120_000);
});
