/**
 * `/team-mcp/scorecard` — o semáforo geral reflete os checks.
 *
 * Cita: UC-SC-02 (Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.casos.md).
 *
 * Contrato, tirado do texto do UC (não do componente): banner verde "Tudo verde — N/N
 * checks OK" quando todos os checks passam; amarelo "N de M checks falhando" quando não.
 * O juízo vem do backend (`ScorecardBuilderService::buildChecks`, coberto pelo UC-SC-04);
 * o que só a TELA pode errar é a conta e a cor do banner — por isso é teste de RENDER.
 *
 * Por que jsdom e não Pest: o banner é derivado no React a partir da prop `checks`. Um
 * Pest só veria o payload, que é o UC-SC-04. Ler o `.tsx` como texto seria derivar o
 * teste do código (§5 2026-06-05).
 *
 * Discriminação: os casos usam totais DIFERENTES (3/3, 1 de 4, 2 de 5) — um banner com
 * número escrito à mão, ou que contasse os ok no lugar dos falhando, reprova em pelo
 * menos um. A cor é lida pela classe de token semântico (success × warning), que é o
 * que o UC chama de verde/amarelo.
 *
 * ForjaHub e PageHeader são substituídos por stubs: não estão sob teste e puxam
 * `usePage` do shell. O `router` do Inertia é mockado (o atalho R e o botão chamam
 * `router.reload`, fora deste UC).
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn() },
  usePage: () => ({ props: {} }),
  Link: (p: { children?: unknown }) => p.children ?? null,
}));
vi.mock('../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHub', () => ({
  default: () => null,
}));
vi.mock('@/Components/PageHeader', () => ({
  PageHeader: () => null,
}));

import ScorecardIndex from '../../Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index';

const facts = {
  tokens_ativos: 1,
  calls_7d: 2,
  cost_7d_brl: 0,
  users_ativos_7d: 1,
  top_tools_7d: [],
  audit_log_present: true,
  tokens_table_present: true,
};
const meta = { generated_at: '2026-10-07T12:00:00Z', period_days: 7, pattern: 'facts+checks', source: 'mcp' };

function checks(okFlags: boolean[]) {
  return okFlags.map((ok, i) => ({ name: `check-${i}`, ok, detail: ok ? 'ok' : 'falhou' }));
}

function banner(okFlags: boolean[]): HTMLElement {
  render(<ScorecardIndex facts={facts} checks={checks(okFlags)} meta={meta} />);
  return screen.getByTestId('scorecard-semaphore');
}

describe('UC-SC-02 · semáforo geral reflete os checks', () => {
  it('todos os checks ok → verde "Tudo verde — N/N checks OK"', () => {
    const el = banner([true, true, true]);
    expect(el.textContent).toContain('Tudo verde — 3/3 checks OK');
    expect(el.className).toContain('bg-success/10');
    expect(el.className).not.toContain('bg-warning-soft');
  });

  it('um check falhando → amarelo "1 de 4 checks falhando", nunca "Tudo verde"', () => {
    const el = banner([true, false, true, true]);
    expect(el.textContent).toContain('1 de 4 checks falhando');
    expect(el.textContent).not.toContain('Tudo verde');
    expect(el.className).toContain('bg-warning-soft');
    expect(el.className).not.toContain('bg-success/10');
  });

  it('a conta acompanha os dados: 2 falhando de 5', () => {
    const el = banner([false, true, true, false, true]);
    expect(el.textContent).toContain('2 de 5 checks falhando');
  });
});
