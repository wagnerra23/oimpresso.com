// Governance · /governance/drift — o que a TELA faz com o payload do scan
//
// Casos: resources/js/Pages/governance/DriftAlerts.casos.md (UC-GDRF-08..12).
// Fonte: DriftAlerts.charter.md (Goals · Non-Goals · UX Anti-patterns) + RUNBOOK-drift-alerts.md §5.
// O lado servidor (scan, KPIs, rota só-leitura) é do Pest tests/Feature/Governance/DriftAlertsContratoTest.php.
//
// Importa a Page REAL; mock só na casca (shell, header, subnav). O KpiCard é trocado por
// um stub que expõe `tone` e `label` em atributo — é o único jeito de ler o veredito de cor
// sem depender de classe CSS (cor comunica veredito: charter §Goals "Tone semântico dinâmico").
//
// Comando: npx vitest run tests/js/governance-drift-alerts.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  router: { get: vi.fn(), post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
  Deferred: ({ children }: any) => <>{children}</>,
  Head: () => null,
  Link: ({ children, ...p }: any) => <a {...p}>{children}</a>,
}));
vi.mock('@/Components/shared/PageHeader', () => ({ default: () => null }));
vi.mock('@/Pages/governance/_shared/GovernancaSubNav', () => ({ default: () => null }));
vi.mock('@/Components/shared/KpiGrid', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Components/shared/KpiCard', () => ({
  default: ({ label, tone, value }: any) => (
    <div data-testid="kpi" data-label={label} data-tone={tone}>{value}</div>
  ),
}));

import DriftAlerts from '@/Pages/governance/DriftAlerts';

afterEach(() => cleanup());

const SEM_DRIFT = {
  kpis: { total_drift: 0, modules_with_drift: 0, modules_without_scope: 0, modules_total: 3 },
  report: [],
  modules_without_scope: [],
  persisted_alerts: [],
};

const COM_DRIFT = {
  kpis: { total_drift: 2, modules_with_drift: 1, modules_without_scope: 1, modules_total: 4 },
  report: [{ module: 'Fx', undeclared: ['BarController', 'Sub/BazController'], undeclared_count: 2, total_actual: 5 }],
  modules_without_scope: ['Orfao'],
  persisted_alerts: [],
};

function tomDe(label: string): string | null {
  const el = screen.getAllByTestId('kpi').find((k) => k.getAttribute('data-label') === label);
  return el ? el.getAttribute('data-tone') : null;
}

describe('Governance /governance/drift — a tela', () => {
  it('UC-GDRF-08 — histórico vazio mostra o empty state explicativo, não lista', () => {
    render(<DriftAlerts {...(SEM_DRIFT as any)} />);
    expect(screen.getByText('Sem alertas persistidos')).toBeTruthy();
  });

  it('UC-GDRF-09 — KPIs de drift ficam warning com drift e success sem drift', () => {
    render(<DriftAlerts {...(COM_DRIFT as any)} />);
    expect(tomDe('Controllers em drift')).toBe('warning');
    expect(tomDe('Módulos com drift')).toBe('warning');
    cleanup();

    render(<DriftAlerts {...(SEM_DRIFT as any)} />);
    expect(tomDe('Controllers em drift')).toBe('success');
    expect(tomDe('Módulos com drift')).toBe('success');
  });

  it('UC-GDRF-10 — zero drift mostra "Sem drift"; com drift lista os não declarados e as 3 saídas', () => {
    render(<DriftAlerts {...(SEM_DRIFT as any)} />);
    expect(screen.getByText('Sem drift')).toBeTruthy();
    cleanup();

    render(<DriftAlerts {...(COM_DRIFT as any)} />);
    expect(screen.queryByText('Sem drift')).toBeNull();
    expect(screen.getByText('Modules/Fx')).toBeTruthy();
    expect(screen.getByText(/BarController/)).toBeTruthy();
    expect(screen.getByText(/Sub\/BazController/)).toBeTruthy();
    expect(screen.getByText('2 de 5 controllers')).toBeTruthy();
    // Hint de remediação: contains[] · mover · drift_alerts[]
    expect(screen.getByText('SCOPE.md.contains[]')).toBeTruthy();
    expect(screen.getByText(/mova pro módulo correto/)).toBeTruthy();
    expect(screen.getByText('drift_alerts[]')).toBeTruthy();
  });

  it('UC-GDRF-11 — nenhuma ação de ignorar, suprimir, auto-corrigir ou editar SCOPE na tela', () => {
    render(<DriftAlerts {...(COM_DRIFT as any)} />);
    const proibido = /ignorar|suprimir|snooze|silenciar|corrigir|auto-?fix|editar/i;
    const acionaveis = [...screen.queryAllByRole('button'), ...screen.queryAllByRole('link')];
    // Controle positivo: há acionáveis (os links de remediação), então a varredura mede algo.
    expect(acionaveis.length).toBeGreaterThan(0);
    for (const el of acionaveis) {
      expect(el.textContent ?? '').not.toMatch(proibido);
    }
    expect(document.querySelector('form, textarea, input')).toBeNull();
  });

  it('UC-GDRF-12 — card "Módulos sem SCOPE.md" só aparece quando a lista não está vazia', () => {
    render(<DriftAlerts {...(COM_DRIFT as any)} />);
    expect(screen.getByText('Módulos sem SCOPE.md')).toBeTruthy();
    expect(screen.getByText('Orfao')).toBeTruthy();
    cleanup();

    render(<DriftAlerts {...(SEM_DRIFT as any)} />);
    expect(screen.queryByText('Módulos sem SCOPE.md')).toBeNull();
  });
});
