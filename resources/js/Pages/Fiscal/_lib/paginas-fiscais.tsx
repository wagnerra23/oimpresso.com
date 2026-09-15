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
// RÓTULOS E ORDEM SEGUEM O PROTÓTIPO (eixo FORMA · ADR UI-0029: protótipo soberano).
// Fonte: `prototipo-ui/cowork/Wagner/fiscal-page.jsx` → `FX_TABS` — conferido em 2026-09-09
// contra o projeto Cowork VIVO por ID (não só contra o espelho): os dois são idênticos.
// Quatro divergiam — dois rótulos, um "&" no lugar de "e", e a ordem de duas abas — e
// todas eram da tela, não do protótipo. O par antes/depois de cada uma está no PR, e NÃO
// aqui, de propósito: `fiscal-subnav.contract.json` procura estas strings NESTE arquivo,
// então repetir a copy antiga (ou a nova) em comentário faz a catraca passar com a aba
// escrita errada. Medido em 2026-09-09: com a copy no comentário, 3 mutações de 3 NÃO
// morderam. Ao mexer nesta lista, não traga os rótulos de volta pra prosa.
//
// O `short` é renumerado junto com a ordem para manter os dígitos 1-7 contíguos na
// sequência visual — o listener do FxShell casa por `short`, não por posição, e os
// casos de uso descrevem "os dígitos 1-7" (não um dígito fixo por tela).
export const FX_PAGES: FxPage[] = [
  { id: 'fiscal',          label: 'Notas fiscais',  icon: <ShieldAlert size={13} aria-hidden="true"/>, short: '1', url: '/fiscal' },
  { id: 'nfe',             label: 'NF-e · NFC-e',   icon: <Receipt size={13} aria-hidden="true"/>,    short: '2', url: '/fiscal/nfe' },
  { id: 'nfse',            label: 'NFS-e',          icon: <FileText size={13} aria-hidden="true"/>,   short: '3', url: '/fiscal/nfse' },
  { id: 'fiscal_eventos',  label: 'Eventos',        icon: <RefreshCw size={13} aria-hidden="true"/>,  short: '4', url: '/fiscal/eventos' },
  { id: 'dfe',             label: 'Manifesto DF-e', icon: <ShieldAlert size={13} aria-hidden="true"/>,short: '5', url: '/fiscal/dfe' },
  { id: 'fiscal_config',   label: 'Certificado',    icon: <Shield size={13} aria-hidden="true"/>,     short: '6', url: '/fiscal/config' },
  { id: 'sped',            label: 'SPED e livros',  icon: <Archive size={13} aria-hidden="true"/>,    short: '7', url: '/fiscal/sped' },
];

