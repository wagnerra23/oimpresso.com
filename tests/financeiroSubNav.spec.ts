// Regressão — pickFinanceiroEntry escolhe a entry CERTA do grupo FINANÇAS por `active`.
//
// Bug (detectado em prod 2026-06-16, WR2 Sistemas): pós-ADR 0180 (26/mai) o grupo FINANÇAS
// virou 4 entries flat. O FinanceiroSubNav fazia `.find(group === 'financas')` → pegava
// SEMPRE a 1ª (Caixa). Resultado: /financeiro/unificado (e Fluxo/DRE/Impostos/Plano de
// Contas/Contas a Pagar/Cobrança) mostrava as abas do CAIXA e não destacava nada. Só as
// 4 telas do próprio Caixa funcionavam, por sorte de ser a 1ª. O fix seleciona a entry
// DONA do `active` (keys são únicas por entry). Este teste FALHA se a regressão voltar.
//
// Fixture = shell.menu real lido ao vivo de prod (window.history.state.page.props.shell.menu).

import { describe, it, expect } from 'vitest';
import { pickFinanceiroEntry, type FinMenuEntry } from '@/Pages/Financeiro/_shared/financeiroMenu';

const MENU: FinMenuEntry[] = [
  {
    label: 'Caixa',
    group: 'financas',
    ghosts: [
      { key: 'caixa', label: 'Caixa', href: '/financeiro/caixa' },
      { key: 'conciliacao', label: 'Conciliação', href: '/financeiro/conciliacao' },
      { key: 'contas-bancarias', label: 'Contas Bancárias', href: '/financeiro/contas-bancarias' },
      { key: 'extrato', label: 'Extrato', href: '/financeiro/extrato' },
    ],
  },
  {
    label: 'Cobrança',
    group: 'financas',
    ghosts: [
      { key: 'cobranca', label: 'Cobrança', href: '/financeiro/cobranca' },
      { key: 'contas-receber', label: 'Contas a Receber', href: '/financeiro/contas-receber' },
      { key: 'gateway', label: 'Gateway', href: '/settings/payment-gateways' },
    ],
  },
  {
    label: 'Financeiro',
    group: 'financas',
    ghosts: [
      { key: 'unificado', label: 'Lançamentos', href: '/financeiro/unificado' },
      { key: 'contas-pagar', label: 'Contas a Pagar', href: '/financeiro/contas-pagar' },
      { key: 'fluxo', label: 'Fluxo de Caixa', href: '/financeiro/fluxo' },
      { key: 'dre', label: 'DRE', href: '/financeiro/dre' },
      { key: 'impostos', label: 'Impostos', href: '/financeiro/impostos' },
      { key: 'plano-contas', label: 'Plano de Contas', href: '/financeiro/plano-contas' },
    ],
  },
  {
    label: 'Cobrança Recorrente',
    group: 'financas',
    ghosts: [{ key: 'recurring-billing', label: 'Assinaturas', href: '/recurring-billing' }],
  },
];

describe('pickFinanceiroEntry — regressão ADR 0180 split (não cair sempre no Caixa)', () => {
  it('REGRESSÃO: active=unificado → entry "Financeiro" (hub), NÃO "Caixa"', () => {
    const e = pickFinanceiroEntry(MENU, 'unificado');
    expect(e?.label).toBe('Financeiro');
    expect(e?.label).not.toBe('Caixa'); // o bug pegava o Caixa (1ª do grupo)
    expect(e?.ghosts?.map((g) => g.key)).toEqual(
      expect.arrayContaining(['unificado', 'fluxo', 'dre', 'impostos', 'plano-contas']),
    );
  });

  it('cada página vai pra entry DONA do seu active key', () => {
    expect(pickFinanceiroEntry(MENU, 'caixa')?.label).toBe('Caixa');
    expect(pickFinanceiroEntry(MENU, 'conciliacao')?.label).toBe('Caixa');
    expect(pickFinanceiroEntry(MENU, 'extrato')?.label).toBe('Caixa');
    expect(pickFinanceiroEntry(MENU, 'cobranca')?.label).toBe('Cobrança');
    expect(pickFinanceiroEntry(MENU, 'contas-receber')?.label).toBe('Cobrança');
    expect(pickFinanceiroEntry(MENU, 'fluxo')?.label).toBe('Financeiro');
    expect(pickFinanceiroEntry(MENU, 'dre')?.label).toBe('Financeiro');
    expect(pickFinanceiroEntry(MENU, 'impostos')?.label).toBe('Financeiro');
    expect(pickFinanceiroEntry(MENU, 'plano-contas')?.label).toBe('Financeiro');
    expect(pickFinanceiroEntry(MENU, 'contas-pagar')?.label).toBe('Financeiro');
  });

  it('active desconhecido → fallback no hub "Financeiro"', () => {
    expect(pickFinanceiroEntry(MENU, 'xpto-inexistente')?.label).toBe('Financeiro');
  });

  it('ignora entries de OUTROS grupos (não vaza pra Vendas/etc)', () => {
    const noisy: FinMenuEntry[] = [
      { label: 'Vendas', group: 'comercial', ghosts: [{ key: 'unificado', label: 'x', href: '/x' }] },
      ...MENU,
    ];
    expect(pickFinanceiroEntry(noisy, 'unificado')?.label).toBe('Financeiro');
  });

  it('menu vazio/undefined → undefined (componente renderiza null)', () => {
    expect(pickFinanceiroEntry([], 'unificado')).toBeUndefined();
    expect(pickFinanceiroEntry(undefined, 'unificado')).toBeUndefined();
  });
});
