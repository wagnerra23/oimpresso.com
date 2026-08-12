// InboxCheatSheet — overlay "?" com os atalhos REAIS da tela (Polish V2 §3).
//
// Lista só o que existe de verdade (anti M-AP-2): J/K, /, E, A, ⌘⇧N, ⌘K
// (palette global PMG-002), ? (este overlay). Esc fecha.

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Inline, Stack } from '@/Components/layout';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const SHORTCUTS: { keys: string; label: string }[] = [
  { keys: 'J / K', label: 'Próxima / anterior conversa na lista' },
  { keys: '/', label: 'Focar a busca de conversas' },
  { keys: 'E', label: 'Resolver a conversa aberta' },
  { keys: 'A', label: 'Marcar como aguardando humano' },
  { keys: '⌘⇧N', label: 'Alternar Resposta / Nota interna no composer' },
  { keys: '⌘K', label: 'Busca global (tasks/epics — palette do sistema)' },
  { keys: '?', label: 'Abrir/fechar este guia de atalhos' },
];

export default function InboxCheatSheet({ open, onOpenChange }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-sm" data-testid="caixa-unif-cheatsheet">
        <DialogHeader>
          <DialogTitle>Atalhos de teclado</DialogTitle>
          <DialogDescription>Funcionam fora de campos de texto.</DialogDescription>
        </DialogHeader>
        <Stack gap={1}>
          {SHORTCUTS.map(s => (
            <Inline key={s.keys} gap={3} align="baseline" justify="between" className="py-0.5">
              <kbd className="font-mono text-[11px] bg-muted border rounded px-1.5 py-0.5 flex-shrink-0">{s.keys}</kbd>
              <span className="text-[12px] text-muted-foreground text-right">{s.label}</span>
            </Inline>
          ))}
        </Stack>
      </DialogContent>
    </Dialog>
  );
}
