// Onde diálogos, gavetas e menus flutuantes montam: dentro do `.cockpit`.
//
// Portal do Radix monta no <body>, fora do `.cockpit`, e lá os tokens do Design System (`--bg`,
// `--surface`, `--text`, `--border`, `--accent`…), o `data-theme` e a fonte do app não chegam.
// O conserto é o da lápide §5 2026-10-19: passar este elemento na prop `container` do `ui/sheet`,
// `ui/dialog`, `ui/alert-dialog`, `ui/dropdown-menu`, `ui/select` e `ui/popover`, sem regra de
// token nova (ADR UI-0013).
//
// Layout effect, e não effect: gaveta ou janela que já nasce aberta precisa nascer no `.cockpit`,
// em vez de montar no <body> e mudar de lugar depois.
//
// Nasceu na Venda V3 (`Sells/_components/v3/cockpit-portal.ts`, que agora reexporta daqui) e subiu
// para cá quando o CRM precisou do mesmo (CRM-03, medido em produção em 2026-11-25).
import { useLayoutEffect, useState } from 'react';

export function useCockpit(): HTMLElement | null {
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  return cockpit;
}
