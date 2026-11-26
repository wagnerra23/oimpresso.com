// CRM-03 — gavetas e janelas do CRM montam dentro do `.cockpit`.
//
// Medido em produção em 2026-11-25 (WR2, tema escuro, 1440px): a gaveta de detalhe do acompanhamento
// e as janelas Adicionar, Recorrente e Acompanhamento antecipado saíam com a fonte do sistema
// (`ui-sans-serif`) e com `--border`, `--accent`, `--text` e `--surface` vazios. Portal do Radix monta
// no <body>, fora do `.cockpit` onde vivem os tokens e a fonte. O conserto é a prop `container`
// pelo hook `@/Hooks/useCockpit` (lápide §5 2026-10-19), aplicado aos 28 portais das telas do CRM.
//
// O que prova: onde o conteúdo fica na árvore, com CONTROLE (sem `.cockpit` na página o conteúdo cai
// no <body>; se o harness não distinguisse, os dois casos sairiam iguais).
// O que NÃO prova: cor e fonte — o jsdom não resolve `var(--…)`. Isso se mede no smoke pós-deploy.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, cleanup, screen } from '@testing-library/react';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } }));

import DrawerAcompanhamento, { type Detalhe } from '../../Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/_components/DrawerAcompanhamento';
import FormRegistro from '../../Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/_components/FormRegistro';

globalThis.fetch = vi.fn(() => Promise.resolve({ json: () => Promise.resolve({ success: true, registros: [] }) })) as unknown as typeof fetch;

const ITEM = {
  id: 7, titulo: 'Ligar para a gráfica', contato: 'Contato fictício', inicio: null, fim: null, status: 'scheduled',
  tipo: null, categoria: null, atribuidos: [], descricao: '', adicionado_por: 'Usuário fictício', adicionado_em: null,
  editar: { notify_via: { sms: false, mail: false }, allow_notification: false },
} as unknown as Detalhe;

let cockpit: HTMLElement | null = null;
const montaCockpit = () => {
  cockpit = document.createElement('div');
  cockpit.className = 'cockpit';
  document.body.appendChild(cockpit);
};

afterEach(() => {
  cleanup();
  cockpit?.remove();
  cockpit = null;
});

describe('CRM-03 · portais do CRM dentro do .cockpit', () => {
  it('gaveta do acompanhamento monta dentro do .cockpit', () => {
    montaCockpit();
    render(<DrawerAcompanhamento item={ITEM} onFechar={() => {}} onRegistrar={() => {}} onConcluido={() => {}} />);
    const gaveta = document.querySelector('[data-contract="crm-acompanhamento-drawer"]');
    expect(gaveta).not.toBeNull();
    expect(gaveta!.closest('.cockpit')).toBe(cockpit);
  });

  it('CONTROLE · sem .cockpit na página a gaveta cai no <body>', () => {
    render(<DrawerAcompanhamento item={ITEM} onFechar={() => {}} onRegistrar={() => {}} onConcluido={() => {}} />);
    const gaveta = document.querySelector('[data-contract="crm-acompanhamento-drawer"]');
    expect(gaveta).not.toBeNull();
    expect(gaveta!.closest('.cockpit')).toBeNull();
  });

  it('janela de registro monta dentro do .cockpit e o × se anuncia "Fechar"', () => {
    montaCockpit();
    render(<FormRegistro acompanhamento={{ id: 7, titulo: 'Ligar', tipo: null, status: null }} onFechar={() => {}} onSalvo={() => {}} />);
    const janela = screen.getByRole('dialog');
    expect(janela.closest('.cockpit')).toBe(cockpit);
    expect(screen.queryByText('Close')).toBeNull();
    expect(screen.getAllByRole('button', { name: 'Fechar' }).length).toBeGreaterThan(0);
  });
});
