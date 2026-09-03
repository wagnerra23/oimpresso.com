// paginas-fiscais.tsx — as 7 sub-páginas do módulo Fiscal (id · rótulo · atalho · rota)
//
// POR QUE MORA AQUI, E NÃO NO FxShell
// -----------------------------------
// Este mapa nasceu dentro do `FxShell.tsx`, que o usa para a sub-nav e para os
// atalhos numéricos. Só que ele também é o ÚNICO lugar que sabe traduzir o `goto`
// de um alerta (`nfe` · `fiscal_config` · `dfe` — ids, não caminhos) na rota real:
// o `CockpitController::computeAlerts()` fala esse mesmo vocabulário.
//
// Exportá-lo do `FxShell` resolveria o dono único, mas custa uma regressão medida:
// `react-refresh/only-export-components` sobe de 0 → 1 e o ratchet do `eslint-gate`
// (config/eslint-baseline.json) reprova o PR — arquivo de componente que exporta
// constante quebra o fast refresh. Regravar o baseline para passar seria editar a
// régua em vez do código.
//
// Em `_lib/` o mapa tem um dono só, sem duplicata e sem dívida de lint — é o mesmo
// endereço de `botao-fiscal.ts`, `chip-filtro.ts` e `fiscal-helpers.ts`. Extensão
// `.tsx` porque os ícones da sub-nav são JSX.

import { Archive, FileText, Receipt, RefreshCw, Shield, ShieldAlert } from 'lucide-react';
import { type ReactNode } from 'react';

export interface FxPage {
  id: string;
  label: string;
  icon: ReactNode;
  short: string;
  url: string;
}

// 7 sub-páginas do Fiscal — PR #1 só implementa "nfe" (segunda).
// Restantes apontam pra "#" e ficam disabled visualmente até serem entregues.
export const FX_PAGES: FxPage[] = [
  { id: 'fiscal',          label: 'Cockpit',        icon: <ShieldAlert size={13}/>, short: '1', url: '/fiscal' },
  { id: 'nfe',             label: 'NF-e · NFC-e',   icon: <Receipt size={13}/>,    short: '2', url: '/fiscal/nfe' },
  { id: 'nfse',            label: 'NFS-e',          icon: <FileText size={13}/>,   short: '3', url: '/fiscal/nfse' },
  { id: 'dfe',             label: 'Manifesto DF-e', icon: <ShieldAlert size={13}/>,short: '4', url: '/fiscal/dfe' },
  { id: 'fiscal_eventos',  label: 'Eventos',        icon: <RefreshCw size={13}/>,  short: '5', url: '/fiscal/eventos' },
  { id: 'fiscal_config',   label: 'Certif. & Cfg.', icon: <Shield size={13}/>,     short: '6', url: '/fiscal/config' },
  { id: 'sped',            label: 'SPED & Livros',  icon: <Archive size={13}/>,    short: '7', url: '/fiscal/sped' },
];

