// CRM-33 — gaveta do acompanhamento na forma do protótipo (`crm-blade.jsx` → Drawer do DS).
//
// Medido em produção em 2026-11-25: 512px, fundo da página, título 16px, seções em texto corrido
// com rótulo 14px, sem "Informação adicional", botões de 36px com texto escuro no principal.
// O que NÃO prova: px renderizados e cores (jsdom não resolve CSS). Medir no smoke pós-deploy.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } }));
globalThis.fetch = vi.fn(() => Promise.resolve({ json: () => Promise.resolve({ success: true, registros: [] }) })) as unknown as typeof fetch;

import DrawerAcompanhamento, { type Detalhe } from '../../Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/_components/DrawerAcompanhamento';

const item = (extra: string | null) => ({
  id: 7, titulo: 'Ligar para a gráfica', contato: 'Contato fictício', inicio: '25/11/2026 09:00', fim: null, status: 'scheduled',
  tipo: null, categoria: null, atribuidos: ['Usuário fictício'], descricao: 'Confirmar medidas.', informacao_adicional: extra,
  adicionado_por: 'Usuário fictício', adicionado_em: '25/11/2026',
  editar: { notify_via: { sms: false, mail: false }, allow_notification: false },
}) as unknown as Detalhe;
const OPCOES = { status: [{ value: 'scheduled', label: 'Agendado' }] };

afterEach(() => cleanup());

describe('Gaveta do acompanhamento · forma do protótipo', () => {
  it('CRM-33 · 480px, selo no tom de destaque, seção em caixa alta e valor mono', () => {
    render(<DrawerAcompanhamento item={item(null)} opcoes={OPCOES} onFechar={() => {}} onRegistrar={() => {}} onConcluido={() => {}} />);
    const gaveta = document.querySelector('[data-contract="crm-acompanhamento-drawer"]') as HTMLElement;
    expect(gaveta.className).toContain('sm:max-w-[480px]');
    expect(screen.getByText('Agendado').getAttribute('style')).toContain('var(--accent)');
    expect(screen.getByRole('heading', { name: 'Informações de acompanhamento' }).className).toContain('uppercase');
    expect(screen.getByText('25/11/2026 09:00').className).toContain('font-mono');
  });

  it('CRM-33 · "Informação adicional" aparece só quando há', () => {
    render(<DrawerAcompanhamento item={item('Origem: Indicação')} opcoes={OPCOES} onFechar={() => {}} onRegistrar={() => {}} onConcluido={() => {}} />);
    expect(screen.getByText('Origem: Indicação')).toBeTruthy();
    cleanup();
    render(<DrawerAcompanhamento item={item(null)} opcoes={OPCOES} onFechar={() => {}} onRegistrar={() => {}} onConcluido={() => {}} />);
    expect(screen.queryByText('Informação adicional:')).toBeNull();
  });
});
