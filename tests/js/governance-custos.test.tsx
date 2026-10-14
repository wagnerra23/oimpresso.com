// Governance — /governance/custos, metade de FRONT do contrato (Custos.casos.md)
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
// Importa a Page de produção; mock só na casca (layout, sub-nav, KpiCard, Select).
// A metade de servidor (tenant, permissão, período, partial honrado) está no Pest
// `Modules/Jana/Tests/Feature/Governance/GovernanceCustosContratoTest.php`, na lane MySQL `jana-pest`.
//
// ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
//   - Nada sobre VALOR: as fixtures carregam zero no campo de custo de propósito.
//   - Nada sobre pixel nem sobre o servidor honrar o `only`.
//
// Comando: npx vitest run tests/js/governance-custos.test.tsx

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  router: { get: routerGet, post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
  Deferred: ({ children }: any) => <>{children}</>,
  Head: () => null,
  Link: ({ children, ...p }: any) => <a {...p}>{children}</a>,
}));
vi.mock('@/Pages/governance/_shared/GovernancaSubNav', () => ({ default: () => null }));
vi.mock('@/Components/shared/KpiGrid', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Components/shared/KpiCard', () => ({ default: ({ label }: any) => <div>{label}</div> }));
vi.mock('@/Components/ui/select', () => ({
  Select: ({ children }: any) => <div>{children}</div>,
  SelectContent: ({ children }: any) => <div>{children}</div>,
  SelectItem: ({ children, value }: any) => <div data-value={value}>{children}</div>,
  SelectTrigger: ({ children }: any) => <div>{children}</div>,
  SelectValue: () => null,
}));

import CustosPage from '@/Pages/governance/Custos';

afterEach(() => { cleanup(); routerGet.mockClear(); });

function montar(preset: 'mes_atual' | 'custom' = 'custom') {
  return render(
    <CustosPage
      kpis={{ custo_brl: 0, mensagens: 3, tokens: 1500, usuarios_ativos: 1 }}
      por_usuario={[{ user_id: 7, nome: 'Usuária Fictícia', conversas: 1, mensagens: 3, tokens: 1500, custo_brl: 0 }]}
      serie_diaria={[{ data: '2001-01-15', mensagens: 3, tokens: 1500, custo_brl: 0 }]}
      periodo={{ inicio: '2001-01-01', fim: '2001-01-31', label: '01/01/2001 — 31/01/2001' }}
      filters={{ preset, de: null, ate: null }}
      pricing={{ modelo_default: 'modelo-ficticio', cambio_brl_usd: 1 }}
    />,
  );
}

describe('governance/Custos — troca de filtro', () => {
  it('UC-GCUS-05 · CONTROLE POSITIVO: a tela renderiza (o harness está de pé)', () => {
    montar();
    expect(screen.getByText('Custos de IA')).toBeTruthy();
    expect(screen.getByText('Usuária Fictícia')).toBeTruthy();
  });

  it('UC-GCUS-05 · aplicar o range custom é partial reload que NÃO pede o pricing de volta', () => {
    montar('custom');
    fireEvent.change(screen.getByLabelText('De'), { target: { value: '2001-01-01' } });
    fireEvent.change(screen.getByLabelText('Até'), { target: { value: '2001-01-31' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(routerGet).toHaveBeenCalledTimes(1);
    const [url, params, opts] = routerGet.mock.calls[0] as any[];
    expect(url).toBe('/governance/custos');
    expect(params).toMatchObject({ preset: 'custom', de: '2001-01-01', ate: '2001-01-31' });
    expect(opts.only).not.toContain('pricing');
    expect([...opts.only].sort()).toEqual(['filters', 'kpis', 'periodo', 'por_usuario', 'serie_diaria']);
    expect(opts.preserveState).toBe(true);
  });

  it('UC-GCUS-05 · fora do modo custom o form De/Até não aparece', () => {
    montar('mes_atual');
    expect(screen.queryByLabelText('De')).toBeNull();
  });
});

describe('governance/Custos — fronteira de módulo', () => {
  it('UC-GCUS-10 · a tela não importa nada de @/Pages/Jana', () => {
    const fonte = readFileSync(resolve(process.cwd(), 'resources/js/Pages/governance/Custos.tsx'), 'utf8');
    const imports = fonte.split('\n').filter((l) => /^\s*import\b/.test(l) || /\bfrom\s+['"]/.test(l));
    expect(imports.length).toBeGreaterThan(0); // controle: a varredura enxergou os imports
    expect(imports.filter((l) => /@\/Pages\/Jana\b/.test(l))).toEqual([]);
  });
});
