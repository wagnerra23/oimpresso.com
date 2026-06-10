// ChannelsDrawer.tsx — drawer "Canais e contas" da Caixa Unificada V4
// (US-WA-304 · charter §5).
//
// Substitui o context-switch pra /atendimento/canais por um Sheet in-place:
// lista agrupada por TYPE de canal (glyph hue do catálogo) + contas com
// status ativo/em-breve + link "Gerenciar canais" pra página completa.
// Referência visual: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
// (om-drawer "Canais e contas").
//
// ZERO backend novo — reusa payloads deferred `availableChannels` (catálogo
// 7 types com count) e `availableAccounts` (instâncias com handle/status/
// health) que a tela já carrega. Cobertura server-side: R-WA-CAIXA-UNIF-001/002.

import { ExternalLink, Settings } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import type { AccountItem, ChannelCatalogItem } from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  channels: ChannelCatalogItem[];
  accounts: AccountItem[];
  /** Permission whatsapp.settings.manage — mostra link "Gerenciar". */
  canManageChannels: boolean;
}

const HEALTH_LABELS: Record<string, string> = {
  healthy: 'saudável',
  degraded: 'degradado',
  down: 'fora do ar',
  never_checked: 'sem verificação',
};

export default function ChannelsDrawer({ open, onOpenChange, channels, accounts, canManageChannels }: Props) {
  const activeCount = accounts.filter(a => a.status === 'ativo').length;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Canais e contas</SheetTitle>
          <SheetDescription>
            {activeCount} {activeCount === 1 ? 'conta ativa' : 'contas ativas'}
            {accounts.length - activeCount > 0 && ` · ${accounts.length - activeCount} em homologação`}
          </SheetDescription>
        </SheetHeader>

        <Stack gap={4} className="mt-4">
          {channels.map(ch => {
            const accs = accounts.filter(a => a.channel_type === ch.id);
            return (
              <section key={ch.id} data-testid={`caixa-unif-drawer-chan-${ch.id}`}>
                <Inline gap={2} align="center" className="mb-1.5">
                  <span
                    className="inline-grid place-items-center w-4 h-4 rounded-full text-white text-[9px] font-bold flex-shrink-0"
                    style={{ background: `oklch(0.62 0.14 ${ch.hue})` }}
                    aria-hidden
                  >
                    {ch.glyph}
                  </span>
                  <small className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
                    {ch.label}
                  </small>
                  {ch.status === 'em_breve' && (
                    <span className="inline-flex text-[9px] font-medium text-muted-foreground bg-muted border rounded-full px-1.5">
                      em breve
                    </span>
                  )}
                  {ch.count > 0 && (
                    <small className="font-mono text-[10px] text-muted-foreground ml-auto">
                      {ch.count} {ch.count === 1 ? 'conversa' : 'conversas'}
                    </small>
                  )}
                </Inline>
                {accs.length === 0 ? (
                  <p className="text-[11px] text-muted-foreground italic px-1">
                    Nenhuma conta conectada deste canal.
                  </p>
                ) : (
                  <Stack gap={1}>
                    {accs.map(acc => (
                      <Inline
                        key={acc.id}
                        gap={2}
                        align="center"
                        justify="between"
                        className={cn(
                          'border rounded-md px-3 py-2',
                          acc.status !== 'ativo' && 'opacity-65',
                        )}
                        data-testid={`caixa-unif-drawer-acc-${acc.id}`}
                      >
                        <span className="min-w-0">
                          <b className="block text-[12px] font-semibold truncate">{acc.label}</b>
                          <small className="block font-mono text-[10.5px] text-muted-foreground truncate">
                            {acc.handle}
                            {acc.status === 'ativo' && acc.channel_health !== 'healthy' && (
                              <span title={`Health: ${acc.channel_health}`}>
                                {' · '}{HEALTH_LABELS[acc.channel_health] ?? acc.channel_health}
                              </span>
                            )}
                          </small>
                        </span>
                        <Inline gap={1} align="center" className="flex-shrink-0">
                          {acc.status === 'ativo' ? (
                            <span
                              className="inline-flex text-[9.5px] font-semibold rounded-full px-2 py-px"
                              style={{ background: 'oklch(0.93 0.06 145)', color: 'oklch(0.25 0.10 145)' }}
                            >
                              ativo
                            </span>
                          ) : (
                            <span className="inline-flex text-[9.5px] font-medium text-muted-foreground bg-muted border rounded-full px-2 py-px">
                              em breve
                            </span>
                          )}
                          {canManageChannels && (
                            <a
                              href={route('atendimento.channels.show', acc.id)}
                              className="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted"
                              title={`Configurar ${acc.label}`}
                              data-testid={`caixa-unif-drawer-acc-manage-${acc.id}`}
                            >
                              <Settings size={12} aria-hidden />
                            </a>
                          )}
                        </Inline>
                      </Inline>
                    ))}
                  </Stack>
                )}
              </section>
            );
          })}

          {canManageChannels && (
            <a
              href={route('atendimento.channels.index')}
              className="inline-flex items-center justify-center gap-1.5 border rounded-md px-3 py-2 text-[12px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
              data-testid="caixa-unif-drawer-manage-all"
            >
              <ExternalLink size={13} aria-hidden />
              Gerenciar canais (página completa)
            </a>
          )}
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
