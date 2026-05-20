// FinSubNav — Cowork KB-9.75 Financeiro Onda 10 (canon 100%)
// (sub-navegação horizontal das telas do Financeiro).
//
// Refs:
//  - prototipo-ui-patch/vendas-financeiro-completo/financeiro-app.jsx FIN_SUB (linha 71)
//
// 5 sub-rotas canon:
//   - unified → /financeiro/unificado (esta tela)
//   - fluxo   → /financeiro/fluxo
//   - concil  → /financeiro/extrato
//   - dre     → /financeiro/relatorios (DRE)
//   - pcontas → /financeiro/plano-contas
//
// Substitui acesso via sidebar lateral por tabs horizontais no topo da tela
// (canon Cowork). Sidebar pode coexistir mas user fica orientado contextualmente.

import { router } from '@inertiajs/react';

interface FinSubItem {
  id: 'unified' | 'fluxo' | 'concil' | 'dre' | 'pcontas';
  label: string;
  href: string;
}

const FIN_SUB: FinSubItem[] = [
  { id: 'unified', label: 'Visão unificada', href: '/financeiro/unificado' },
  { id: 'fluxo',   label: 'Fluxo de caixa',  href: '/financeiro/fluxo' },
  // Onda 16 (2026-05-19) — fix 404: extrato exige conta_id; plano-contas não existe.
  { id: 'concil',  label: 'Conciliação',      href: '/financeiro/contas-bancarias' },
  { id: 'dre',     label: 'DRE / Relatórios', href: '/financeiro/relatorios' },
  { id: 'pcontas', label: 'Plano de contas',  href: '/financeiro/categorias' },
];

export function FinSubNav({ active }: { active: FinSubItem['id'] }) {
  return (
    <nav className="fin-subnav" role="tablist" aria-label="Sub-rotas Financeiro">
      {FIN_SUB.map((item) => (
        <button
          key={item.id}
          type="button"
          role="tab"
          aria-selected={item.id === active}
          className={'fin-subnav-tab' + (item.id === active ? ' on' : '')}
          onClick={() => {
            if (item.id !== active) {
              router.visit(item.href);
            }
          }}
        >
          {item.label}
        </button>
      ))}
    </nav>
  );
}

export default FinSubNav;
