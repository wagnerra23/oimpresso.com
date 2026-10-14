// Governance — /governance/qualidade-ia · casos de UI do QualidadeIa.casos.md
//
// ── DE ONDE VÊM OS CASOS ─────────────────────────────────────────────────────
// Os UCs saem do charter (QualidadeIa.charter.md §Goals) e do RUNBOOK
// (memory/requisitos/Governance/RUNBOOK-qualidade-ia.md §1, §4.4, §5) — o .tsx
// entra só para ser renderizado, nunca para ditar o esperado (§5 2026-06-05).
// Os alvos dos gates usados nas fixtures são os da ADR 0049/0050 (RUNBOOK §1).
//
// ── O QUE MEDE ───────────────────────────────────────────────────────────────
// Renderiza a Page de PRODUÇÃO. Mock só na casca (layout, router, KpiCard,
// Select, ScrollArea). O KpiCard mockado expõe `label`/`tone`/`value` no DOM —
// é o único jeito de ler o veredito de gate sem reimplementar `gateStatus`.
//
// ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
//   - Nada do SERVIDOR (permissão, clamp da janela, KPI = última leitura): isso é
//     o Pest `Modules/Governance/Tests/Feature/QualidadeIaControllerTest.php`.
//   - Não prova pixel nem as cores do DS.
//
// Comando: npx vitest run tests/js/governance-qualidade-ia.test.tsx

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
vi.mock('@/Components/shared/KpiCard', () => ({
  default: ({ label, value, tone }: any) => (
    <div data-testid="kpi" data-label={label} data-tone={tone}>{value}</div>
  ),
}));
vi.mock('@/Components/ui/scroll-area', () => ({ ScrollArea: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Components/ui/select', () => ({
  Select: ({ children }: any) => <div>{children}</div>,
  SelectContent: ({ children }: any) => <div>{children}</div>,
  SelectItem: ({ children }: any) => <div>{children}</div>,
  SelectTrigger: ({ children }: any) => <div>{children}</div>,
  SelectValue: () => null,
}));

import QualidadeIaPage from '@/Pages/governance/QualidadeIa';

afterEach(() => { cleanup(); routerGet.mockClear(); });

// Alvos canônicos — RUNBOOK §1 / ADR 0049-0050 (contrato), não lidos do controller.
const GATES = {
  recall_at_3: { op: '>=', alvo: 0.8, unit: '', label: 'Recall@3' },
  precision_at_3: { op: '>=', alvo: 0.6, unit: '', label: 'Precision@3' },
  mrr: { op: '>=', alvo: 0.7, unit: '', label: 'MRR' },
  faithfulness: { op: '>=', alvo: 0.85, unit: '', label: 'Faithfulness' },
  latencia_p95_ms: { op: '<=', alvo: 2000, unit: 'ms', label: 'Latência p95' },
  tokens_medio: { op: '<=', alvo: 3000, unit: 'tk', label: 'Tokens/interação' },
  memory_bloat: { op: '>=', alvo: 0.6, unit: '', label: 'Bloat ratio' },
  taxa_contradicoes_pct: { op: '<=', alvo: 2.0, unit: '%', label: 'Contradições' },
  cross_tenant_violations: { op: '==', alvo: 0, unit: '', label: 'Cross-tenant' },
} as const;

function ponto(data: string, over: Record<string, unknown> = {}) {
  return {
    data, recall_at_3: 0.85, precision_at_3: 0.7, mrr: 0.75, latencia_p95_ms: 900,
    tokens_medio: 1200, memory_bloat: 0.7, taxa_contradicoes_pct: 0.5,
    cross_tenant_violations: 0, faithfulness: 0.9, answer_relevancy: 0.8,
    context_precision: 0.8, total_interacoes_dia: 10, total_memorias_ativas: 50,
    ...over,
  };
}

function kpi(over: Record<string, unknown> = {}) {
  return {
    business_id: 98, label: 'Business #98', apurado_em: '2026-10-13',
    recall_at_3: 0.85, precision_at_3: 0.7, mrr: 0.75, faithfulness: 0.9,
    latencia_p95_ms: 900, tokens_medio: 1200, taxa_contradicoes_pct: 0.5,
    cross_tenant_violations: 0, total_interacoes_dia: 10,
    ...over,
  };
}

function montar(over: Record<string, unknown> = {}) {
  const props: any = {
    series: [{ business_id: 98, label: 'Business #98', pontos: [ponto('2026-10-13')] }],
    kpis: [kpi()],
    gates: GATES,
    filtros: { dias: 30, business_id: null },
    gabarito_total: 12,
    gabarito_por_categoria: { factual: 7, temporal: 5 },
    ...over,
  };
  return render(<QualidadeIaPage {...props} />);
}

const card = (label: string) =>
  screen.getAllByTestId('kpi').find((el) => el.getAttribute('data-label') === label)!;

describe('UC-GQIA-03 — cada KPI da última leitura mostra o veredito do gate contra o alvo canônico', () => {
  it('UC-GQIA-03: Recall@3 abaixo de 0.80 sai reprovado; acima, aprovado', () => {
    montar({ kpis: [kpi({ recall_at_3: 0.7 })] });
    expect(card('Recall@3').getAttribute('data-tone')).toBe('danger');
    cleanup();
    montar({ kpis: [kpi({ recall_at_3: 0.85 })] });
    expect(card('Recall@3').getAttribute('data-tone')).toBe('success');
  });

  it('UC-GQIA-03: uma única violação cross-tenant reprova o gate (alvo == 0)', () => {
    montar({ kpis: [kpi({ cross_tenant_violations: 1 })] });
    expect(card('Cross-tenant').getAttribute('data-tone')).toBe('danger');
    cleanup();
    montar({ kpis: [kpi({ cross_tenant_violations: 0 })] });
    expect(card('Cross-tenant').getAttribute('data-tone')).toBe('success');
  });

  it('UC-GQIA-03: latência p95 acima de 2000ms reprova (gate é <=)', () => {
    montar({ kpis: [kpi({ latencia_p95_ms: 2500 })] });
    expect(card('Latência p95').getAttribute('data-tone')).toBe('danger');
  });

  it('UC-GQIA-03: há um conjunto de cards por business da última leitura', () => {
    montar({
      kpis: [kpi(), kpi({ business_id: null, label: 'Plataforma' })],
    });
    expect(screen.getAllByTestId('kpi').filter((el) => el.getAttribute('data-label') === 'Recall@3')).toHaveLength(2);
  });
});

describe('UC-GQIA-05 — Aplicar filtro re-busca só o que muda com o filtro, na rota da Governança', () => {
  it('UC-GQIA-05: Aplicar faz partial reload de series/kpis/filtros em /governance/qualidade-ia', () => {
    montar();
    fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));
    expect(routerGet).toHaveBeenCalledTimes(1);
    const [url, params, opts] = routerGet.mock.calls[0];
    expect(url).toBe('/governance/qualidade-ia');
    expect(params).toEqual({ dias: 30 });
    expect([...opts.only].sort()).toEqual(['filtros', 'kpis', 'series']);
    // gates e gabarito NÃO retrafegam (charter §Goals)
    expect(opts.only).not.toContain('gates');
    expect(opts.only).not.toContain('gabarito_total');
  });
});

describe('UC-GQIA-06 — métrica ausente aparece como travessão, nunca como zero', () => {
  it('UC-GQIA-06: recall nulo mostra "—" e não "0.0%"', () => {
    montar({ kpis: [kpi({ recall_at_3: null, latencia_p95_ms: null })] });
    expect(card('Recall@3').textContent).toBe('—');
    expect(card('Latência p95').textContent).toBe('—');
  });
});

describe('UC-GQIA-07 — sem métricas na janela, a tela diz como popular', () => {
  it('UC-GQIA-07: trend vazio mostra a dica com o comando de apuração', () => {
    montar({ series: [], kpis: [] });
    const vazio = screen.getByText(/Sem dados de métricas/);
    expect(vazio.textContent).toContain('copiloto:metrics:apurar');
    expect(vazio.textContent).toContain('copiloto:eval --persist');
  });

  it('UC-GQIA-07 (controle): com série presente, a dica de vazio não aparece', () => {
    montar();
    expect(screen.queryByText(/Sem dados de métricas/)).toBeNull();
  });
});

describe('UC-GQIA-08 — o tamanho do gabarito de avaliação fica visível', () => {
  it('UC-GQIA-08: mostra total e contagem por categoria do gabarito', () => {
    montar();
    expect(screen.getByText(/Gabarito: 12 perguntas/).textContent).toMatch(/factual=7.*temporal=5/);
  });
});

describe('UC-GQIA-09 — runs recentes: as ~30 mais novas, da mais nova para a mais antiga', () => {
  it('UC-GQIA-09: 35 pontos viram 30 linhas, a primeira é a data mais recente', () => {
    const datas = Array.from({ length: 35 }, (_, i) => {
      const d = new Date(Date.UTC(2026, 8, 1 + i));
      return d.toISOString().slice(0, 10);
    });
    montar({ series: [{ business_id: 98, label: 'Business #98', pontos: datas.map((d) => ponto(d)) }] });
    // A tabela de runs é a que tem cabeçalho "Lat p95" (a de trend não tem).
    const tabela = screen.getByText('Lat p95').closest('table')!;
    const linhas = tabela.querySelectorAll('tbody tr');
    expect(linhas).toHaveLength(30);
    expect(linhas[0].querySelector('td')!.textContent).toBe(datas[34]);
    expect(linhas[29].querySelector('td')!.textContent).toBe(datas[5]);
  });
});
