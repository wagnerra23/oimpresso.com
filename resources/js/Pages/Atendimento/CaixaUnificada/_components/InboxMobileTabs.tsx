// InboxMobileTabs — 3 tabs Conversas/Thread/Contexto abaixo de lg (Polish V2
// §5 · inbox-extras.jsx). No desktop a shell 3-col fica intacta (lg:); no
// mobile cada tab mostra UMA coluna em largura cheia.

import { MessageSquare, PanelRight, List } from 'lucide-react';
import { Grid } from '@/Components/layout';
import { cn } from '@/Lib/utils';

export type MobileView = 'list' | 'thread' | 'context';

interface Props {
  view: MobileView;
  onChange: (view: MobileView) => void;
  hasThread: boolean;
  unread: number;
}

const TABS: { id: MobileView; label: string; icon: typeof List }[] = [
  { id: 'list', label: 'Conversas', icon: List },
  { id: 'thread', label: 'Thread', icon: MessageSquare },
  { id: 'context', label: 'Contexto', icon: PanelRight },
];

export default function InboxMobileTabs({ view, onChange, hasThread, unread }: Props) {
  return (
    <Grid
      cols={3}
      gap={1}
      className="lg:hidden border-b bg-card px-1 py-1"
      role="tablist"
      aria-label="Painéis da caixa unificada (mobile)"
      data-testid="caixa-unif-mobile-tabs"
    >
      {TABS.map(t => {
        const Icon = t.icon;
        const disabled = t.id !== 'list' && !hasThread;
        const active = view === t.id;
        return (
          <button
            key={t.id}
            type="button"
            role="tab"
            aria-selected={active}
            disabled={disabled}
            onClick={() => onChange(t.id)}
            data-testid={`caixa-unif-mobile-tab-${t.id}`}
            className={cn(
              'inline-flex items-center justify-center gap-1.5 h-8 rounded text-[11.5px] font-medium transition-colors',
              active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground hover:bg-muted',
              disabled && 'opacity-40 cursor-not-allowed',
            )}
            title={disabled ? 'Selecione uma conversa primeiro' : undefined}
          >
            <Icon size={13} aria-hidden />
            {t.label}
            {t.id === 'list' && unread > 0 && (
              <span className={cn(
                'inline-flex items-center justify-center min-w-[15px] h-3.5 px-1 text-[9.5px] font-mono rounded-full',
                active ? 'bg-primary-foreground/25 text-primary-foreground' : 'bg-primary text-primary-foreground',
              )}>
                {unread > 99 ? '99+' : unread}
              </span>
            )}
          </button>
        );
      })}
    </Grid>
  );
}
