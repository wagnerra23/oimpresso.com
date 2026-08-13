// ShortcutsOverlay — overlay de ajuda dos atalhos do Board (PMG-008, tecla `?`).
//
// Por que nasceu aqui e não reusou um dos cheat-sheets existentes: o projeto tem
// hoje 3 (Sells/_components/SellsCheatSheet · Financeiro/Cobranca/_components/
// CheatSheet · Atendimento/CaixaUnificada/_components/InboxCheatSheet). O do
// Sells é o mais maduro e É parametrizável — mas depende do CSS escopado
// resources/css/sells-kb975-cheatsheet.css (classes .vd-cheat-*), e importar CSS
// de outro módulo acopla os dois. Promover pra Components/shared/ é o caminho
// canônico (components.md: composto cross-módulo → shared/), porém mexeria na
// tela de venda — PR separado. Aqui: primitivo DS Dialog + tokens, zero CSS novo.
//
// Duas catracas do repo moldaram este arquivo (rodadas antes do commit):
//  - layout-primitives-guard (ADR 0253): nada de `<div className="flex ...">`
//    solto — a linha do atalho compõe com <Inline>.
//  - tokens dark-aware (DS v6, ADR 0249): só bg-background/muted/border. O
//    CheatSheet do Financeiro usa stone-* hardcoded e não sobrevive ao dark.

import { type ReactNode } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Inline } from '@/Components/layout';

interface BoardShortcut {
  kbd: string;
  label: string;
  area: string;
}

// Ordem das seções = ordem de leitura do overlay.
const AREAS = ['Navegar', 'Ações no card selecionado', 'Ajuda'] as const;

const BOARD_SHORTCUTS: BoardShortcut[] = [
  { area: 'Navegar', kbd: 'J', label: 'Próximo card' },
  { area: 'Navegar', kbd: 'K', label: 'Card anterior' },
  { area: 'Navegar', kbd: '/', label: 'Focar a busca' },

  { area: 'Ações no card selecionado', kbd: 'Enter', label: 'Abrir o painel de detalhe' },
  { area: 'Ações no card selecionado', kbd: 'E', label: 'Avançar status (todo → doing → review → done)' },
  { area: 'Ações no card selecionado', kbd: 'A', label: 'Voltar status' },

  { area: 'Ajuda', kbd: '?', label: 'Abrir/fechar esta lista' },
  { area: 'Ajuda', kbd: 'Esc', label: 'Fechar' },
];

export interface ShortcutsOverlayProps {
  open: boolean;
  onClose: () => void;
}

export default function ShortcutsOverlay({ open, onClose }: ShortcutsOverlayProps): ReactNode {
  return (
    <Dialog open={open} onOpenChange={(next) => { if (!next) onClose(); }}>
      <DialogContent className="sm:max-w-lg" data-testid="board-shortcuts-overlay">
        <DialogHeader>
          <DialogTitle>Atalhos do Board</DialogTitle>
          <DialogDescription>
            O board opera sem mouse. Os atalhos valem quando nenhum campo de texto está em foco.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {AREAS.map((area) => {
            const items = BOARD_SHORTCUTS.filter((s) => s.area === area);
            if (!items.length) return null;

            return (
              <section key={area}>
                <h3 className="text-[10px] uppercase tracking-widest text-muted-foreground mb-1.5">
                  {area}
                </h3>
                <ul className="space-y-1">
                  {items.map((s) => (
                    <li
                      key={`${area}-${s.kbd}`}
                      className="border-b border-border/60 py-1 last:border-b-0"
                    >
                      <Inline gap={4} align="center" justify="between">
                        <span className="text-sm text-foreground">{s.label}</span>
                        <kbd className="shrink-0 rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                          {s.kbd}
                        </kbd>
                      </Inline>
                    </li>
                  ))}
                </ul>
              </section>
            );
          })}
        </div>
      </DialogContent>
    </Dialog>
  );
}
