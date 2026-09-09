// Governance — os 4 itens de front que os GAP-SPEC marcavam "Decidir"
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Os cinco `memory/requisitos/Governance/governance-*-gap.md` (2026-09-06) fecharam
// item a item com recibo de ausência, e sobraram 7 marcados "**Decidir.** Construir ou
// rejeitar por escrito". Este PR constrói 4 deles — todos front puro, zero fonte nova.
// Sem este spec eles seriam INERTES: typecheck e build verdes não provam render (LC-30,
// §5 2026-08-28 "correção que passa no CI inteiro e é inerte no runtime").
//
// ── O QUE ELE MEDE (componente REAL, mock só na casca) ───────────────────────
// Importa as três Pages de produção. Nenhuma lógica é reimplementada — o `useMemo` da
// busca, o `hasFilter` e o `filteredGrades` são os de verdade. Reimplementar seria medir
// a minha cópia, não a tela (§5 2026-06-05, teste tautológico).
//
// ── A MORDIDA (provada por mutação, não afirmada) ────────────────────────────
// Rodada em 2026-09-09 contra o código real, uma de cada vez, com restauro conferido por
// hash (sha256 antes == depois nas três) e verde reconfirmado ao fim — baseline 16/16:
//   M1. `hasFilter` fixo em `false` (Audit)                            → 4 failed | 12 passed
//   M2. `filteredGroups` devolvendo `rules_by_category` cru (Policies) → 3 failed | 13 passed
//   M3. `onClick` do "Limpar" virando no-op (ModuleGrades)             → 1 failed | 15 passed
// Os controles positivos seguiram verdes nas três — é o que separa "o harness quebrou"
// de "o comportamento sumiu". Sem esse par o arquivo seria carimbo: verde que não sabe
// ficar vermelho (§5 2026-07-17, drift-sentinel tautológico).
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre o SERVIDOR. Que o AuditController aceite querystring vazia e volte ao
//     default '24h' é do Pest, que roda no CT 100 — não aqui.
//   - Nada sobre VALOR ou ESTOQUE. Zero cálculo é tocado (REGRA MESTRE, proibicoes.md).
//   - Não prova pixel. O smoke real em prod é pós-merge (R1).
//
// Comando: npx vitest run tests/js/governance-filtros.test.tsx
// @see memory/requisitos/Governance/governance-audit-gap.md ("Limpar filtros")
// @see memory/requisitos/Governance/governance-policies-gap.md (busca + aviso de rastro)
// @see memory/requisitos/Governance/governance-module-grades-gap.md (botão "Limpar")

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';

// `vi.mock` é hoisted acima dos imports — a fn precisa nascer junto, senão o factory
// referencia uma variável ainda não inicializada.
const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  router: { get: routerGet, post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
  Deferred: ({ children }: any) => <>{children}</>,
  Head: () => null,
  Link: ({ children, ...p }: any) => <a {...p}>{children}</a>,
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/Components/shared/PageHeader', () => ({ default: () => null }));
vi.mock('@/Pages/governance/_shared/GovernancaSubNav', () => ({ default: () => null }));
vi.mock('@/Components/shared/KpiGrid', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Components/shared/KpiCard', () => ({ default: () => null }));
vi.mock('@/Components/ui/switch', () => ({ Switch: () => <input type="checkbox" readOnly /> }));
vi.mock('@/Components/ui/select', () => ({
  Select: ({ children }: any) => <div>{children}</div>,
  SelectContent: ({ children }: any) => <div>{children}</div>,
  SelectItem: ({ children }: any) => <div>{children}</div>,
  SelectTrigger: ({ children }: any) => <div>{children}</div>,
  SelectValue: () => null,
}));

import AuditPage from '@/Pages/governance/Audit';
import PoliciesPage from '@/Pages/governance/Policies';
import ModuleGradesPage from '@/Pages/governance/ModuleGrades/Index';

afterEach(() => { cleanup(); routerGet.mockClear(); });

// ─────────────────────────────────────────────────────────────────────────────
// Audit — "Limpar filtros" (governance-audit-gap.md)
// ─────────────────────────────────────────────────────────────────────────────
const ENTRY = {
  id: 1, user_id: 7, business_id: 1, endpoint: '/mcp/tools',
  tool_or_resource: 'brief-fetch', status: 'ok', duration_ms: 12,
  created_at: '2026-09-09 08:00:00',
};

function montarAudit(filtros: Record<string, unknown> = {}) {
  return render(
    <AuditPage
      entries={[ENTRY] as any}
      kpis={{ total: 1, errors: 0, unique_users: 1 }}
      filters={{ period: '24h', actor: null, endpoint: null, status: null, ...filtros } as any}
      available_endpoints={['/mcp/tools']}
      available_actors={[{ slug: 'wagner', display_name: 'Wagner' }]}
    />,
  );
}

describe('governance/Audit — Limpar filtros', () => {
  it('CONTROLE POSITIVO: a tela renderiza (o harness está de pé)', () => {
    montarAudit();
    // `brief-fetch` só existe na linha da tabela; `/mcp/tools` apareceria também
    // no SelectItem de endpoints e não distinguiria render de casca.
    expect(screen.getByText('brief-fetch')).toBeTruthy();
  });

  it('no default (period=24h, resto vazio) NÃO oferece limpar — nada a limpar', () => {
    montarAudit();
    expect(screen.queryByRole('button', { name: /limpar filtros/i })).toBeNull();
  });

  it('com filtro aplicado, oferece limpar', () => {
    montarAudit({ status: 'error' });
    expect(screen.getByRole('button', { name: /limpar filtros/i })).toBeTruthy();
  });

  it('período != 24h também conta como filtro (o default do controller é 24h)', () => {
    montarAudit({ period: '7d' });
    expect(screen.getByRole('button', { name: /limpar filtros/i })).toBeTruthy();
  });

  it('clicar em limpar pede a rota SEM filtro nenhum, com partial reload', () => {
    montarAudit({ status: 'error', actor: 'wagner' });
    fireEvent.click(screen.getByRole('button', { name: /limpar filtros/i }));

    expect(routerGet).toHaveBeenCalledTimes(1);
    const [url, params, opts] = routerGet.mock.calls[0] as any[];
    expect(url).toBe('/governance/audit');
    expect(params).toEqual({});
    // D-14: o mesmo partial reload dos outros filtros — não recarrega a página inteira.
    expect(opts.only).toEqual(['entries', 'kpis', 'filters']);
    expect(opts.preserveState).toBe(true);
  });

  it('sem entries E com filtro, o vazio explica a combinação e oferece a saída', () => {
    render(
      <AuditPage
        entries={[] as any}
        kpis={{ total: 0, errors: 0, unique_users: 0 }}
        filters={{ period: '7d', actor: null, endpoint: null, status: 'error' } as any}
        available_endpoints={[]}
        available_actors={[]}
      />,
    );
    expect(screen.getByText(/não devolve nada/i)).toBeTruthy();
    expect(screen.getByText(/Período 7d/)).toBeTruthy();
    expect(screen.getByText(/status error/)).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Policies — busca local + aviso de rastro (governance-policies-gap.md)
// ─────────────────────────────────────────────────────────────────────────────
const REGRA = (id: number, key: string, name: string, cat: string) => ({
  id, rule_key: key, name, description: `descrição de ${key}`, enabled: true,
  version: 1, triggered_count: 0, created_by: null, updated_at: '2026-09-09',
});

function montarPolicies(grupos?: any) {
  return render(
    <PoliciesPage
      rules_by_category={grupos ?? [
        { category: 'seguranca', rules: [REGRA(1, 'block_delete', 'Bloqueia delete', 'seguranca')] },
        { category: 'fiscal', rules: [REGRA(2, 'nfe_guard', 'Guarda NFe', 'fiscal')] },
      ]}
      kpis={{ total: 2, enabled: 2, triggered: 0, categories: 2 }}
    />,
  );
}

describe('governance/Policies — busca local e aviso de rastro', () => {
  it('CONTROLE POSITIVO: as duas regras aparecem sem busca', () => {
    montarPolicies();
    expect(screen.getByText('block_delete')).toBeTruthy();
    expect(screen.getByText('nfe_guard')).toBeTruthy();
  });

  it('avisa que alternar não deixa rastro — mcp_governance_rule_history não existe', () => {
    montarPolicies();
    expect(screen.getByText(/Alternar não deixa rastro/i)).toBeTruthy();
  });

  it('busca por chave filtra e mantém só a que bate', () => {
    montarPolicies();
    fireEvent.change(screen.getByLabelText(/buscar política/i), { target: { value: 'nfe' } });
    expect(screen.queryByText('block_delete')).toBeNull();
    expect(screen.getByText('nfe_guard')).toBeTruthy();
  });

  it('busca casa por CATEGORIA também, não só por chave', () => {
    montarPolicies();
    fireEvent.change(screen.getByLabelText(/buscar política/i), { target: { value: 'seguranca' } });
    expect(screen.getByText('block_delete')).toBeTruthy();
    expect(screen.queryByText('nfe_guard')).toBeNull();
  });

  it('busca sem resultado mostra o vazio e devolve o catálogo ao limpar', () => {
    montarPolicies();
    const campo = screen.getByLabelText(/buscar política/i);
    fireEvent.change(campo, { target: { value: 'zzzz-nao-existe' } });
    expect(screen.getByText(/Nenhuma política bate com essa busca/i)).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: /limpar busca/i }));
    expect(screen.getByText('block_delete')).toBeTruthy();
    expect(screen.getByText('nfe_guard')).toBeTruthy();
  });

  it('catálogo vazio NÃO é o vazio de busca — a mensagem é a do catálogo', () => {
    montarPolicies([]);
    expect(screen.getByText(/Sem rules ainda/i)).toBeTruthy();
    expect(screen.queryByLabelText(/buscar política/i)).toBeNull();
  });

  it('anti-hook do charter: desligada continua na lista quando não há busca', () => {
    montarPolicies([
      { category: 'seguranca', rules: [{ ...REGRA(1, 'off_rule', 'Desligada', 'seguranca'), enabled: false }] },
    ]);
    expect(screen.getByText('off_rule')).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// ModuleGrades — botão "Limpar" no vazio (governance-module-grades-gap.md)
// ─────────────────────────────────────────────────────────────────────────────
const GRADE = {
  module: 'Governance', score: 72, bucket: 'Bom', color: 'blue',
  dimensions: {
    multi_tenant: '10/10', pest_coverage: '8/10', documentation: '7/10',
    architecture: '8/10', client_real: '5/10',
  },
};

function montarGrades() {
  return render(
    <ModuleGradesPage
      grades={[GRADE] as any}
      kpis={{ average: 72, total: 1, by_bucket: { Bom: 1 } } as any}
      catalog={undefined as any}
    />,
  );
}

describe('governance/ModuleGrades — Limpar no vazio do filtro', () => {
  it('CONTROLE POSITIVO: o módulo aparece na tabela sem filtro', () => {
    montarGrades();
    expect(screen.getByText('Governance')).toBeTruthy();
  });

  it('busca que não bate esvazia a tabela e oferece Limpar', () => {
    montarGrades();
    fireEvent.change(screen.getByPlaceholderText(/buscar módulo/i), { target: { value: 'zzzz' } });
    expect(screen.getByText(/Nenhum módulo combina com o filtro/i)).toBeTruthy();
    expect(screen.getByRole('button', { name: /^limpar$/i })).toBeTruthy();
  });

  it('Limpar zera busca E faixa de uma vez — a linha volta', () => {
    montarGrades();
    const busca = screen.getByPlaceholderText(/buscar módulo/i) as HTMLInputElement;
    fireEvent.change(busca, { target: { value: 'zzzz' } });
    expect(screen.queryByText('Governance')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: /^limpar$/i }));
    expect(screen.getByText('Governance')).toBeTruthy();
    expect(busca.value).toBe('');
  });
});
