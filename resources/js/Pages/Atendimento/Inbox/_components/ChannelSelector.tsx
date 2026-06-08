// @memcofre
//   tela: /atendimento/inbox (header)
//   stories: US-WA-040 (multi-phone UI per-business — CYCLE-08 PR-A)
//   adrs: 0117 (multiplos numeros) + 0135 (omnichannel) + 0110 (Cockpit V2)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   permissao: implícito via ACL — só lista canais com acesso ativo
//
// Dropdown topbar pra alternar canal ativo no Inbox quando user tem acesso a
// múltiplos canais. Single-channel users veem apenas o label do canal (sem
// dropdown). Zero-channel users veem CTA "Sem canais — peça acesso ao admin".
//
// Estado vem 100% dos query params (?channel_id=N) — sem LS persistence nesta
// versão (Wagner valida UX antes de cookie/session). Selecionar canal dispara
// router.get preserveState pra evitar refetch desnecessário.

import { router } from '@inertiajs/react';
import {
  ChevronDown,
  Inbox as InboxIcon,
  CheckCircle2,
  Circle,
  AlertTriangle,
  Loader2,
} from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export interface AvailableChannel {
  id: number;
  label: string;
  type: string;
  display_identifier: string | null;
  channel_health: 'healthy' | 'degraded' | 'disconnected' | 'banned' | 'never_checked';
  unread_count: number;
}

interface Props {
  availableChannels: AvailableChannel[];
  selectedChannelId: number | null;
}

/**
 * Formata E.164 BR (+5511999999999) → +55 11 99999-9999 visual-only.
 * Não-BR cai no fallback (mostra o E.164 cru). Conservador — nunca quebra UI.
 */
function formatPhone(e164: string | null): string {
  if (!e164) return '';
  const digits = e164.replace(/\D/g, '');
  if (digits.startsWith('55') && (digits.length === 12 || digits.length === 13)) {
    const country = digits.slice(0, 2);
    const ddd = digits.slice(2, 4);
    const rest = digits.slice(4);
    if (rest.length === 9) {
      return `+${country} ${ddd} ${rest.slice(0, 5)}-${rest.slice(5)}`;
    }
    if (rest.length === 8) {
      return `+${country} ${ddd} ${rest.slice(0, 4)}-${rest.slice(4)}`;
    }
  }
  return e164;
}

/**
 * Ícone de saúde do canal. Cores espelham o badge na tela /atendimento/canais
 * pra consistência cognitiva (atendente vê mesmo símbolo nos dois lugares).
 */
function HealthIcon({ health }: { health: AvailableChannel['channel_health'] }) {
  if (health === 'healthy') {
    return <CheckCircle2 size={12} className="text-emerald-600 shrink-0" aria-label="Saudável" />;
  }
  if (health === 'degraded') {
    return <AlertTriangle size={12} className="text-amber-500 shrink-0" aria-label="Degradado" />;
  }
  if (health === 'disconnected') {
    return <Circle size={12} className="text-red-500 shrink-0 fill-red-500" aria-label="Desconectado" />;
  }
  if (health === 'banned') {
    return <AlertTriangle size={12} className="text-red-700 shrink-0" aria-label="Banido" />;
  }
  return <Loader2 size={12} className="text-muted-foreground shrink-0" aria-label="Não verificado" />;
}

export default function ChannelSelector({ availableChannels, selectedChannelId }: Props) {
  // Zero canais — atendente precisa pedir acesso. UX vazia com CTA.
  if (availableChannels.length === 0) {
    return (
      <div
        className="inline-flex items-center gap-1.5 px-2 py-1 rounded border border-dashed border-amber-300 bg-amber-50 text-amber-800 text-xs"
        role="status"
      >
        <AlertTriangle size={12} className="shrink-0" />
        <span>Sem canais — peça acesso ao admin</span>
      </div>
    );
  }

  // Único canal — sem dropdown, só label informativo (não tem o que escolher).
  if (availableChannels.length === 1) {
    const only = availableChannels[0]!;
    const unread = only.unread_count;
    return (
      <div
        className="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-muted/40 text-xs"
        title={only.display_identifier ?? undefined}
      >
        <HealthIcon health={only.channel_health} />
        <span className="font-medium truncate max-w-[140px]">{only.label}</span>
        {only.display_identifier && (
          <span className="text-muted-foreground hidden md:inline">{formatPhone(only.display_identifier)}</span>
        )}
        {unread > 0 && (
          <Badge variant="secondary" className="h-4 px-1 text-[10px] leading-none">
            {unread > 99 ? '99+' : unread}
          </Badge>
        )}
      </div>
    );
  }

  // Multi-canal — dropdown. Selected ou "Todos os canais"
  const selected = selectedChannelId !== null
    ? availableChannels.find((c) => c.id === selectedChannelId) ?? null
    : null;
  const totalUnread = availableChannels.reduce((acc, c) => acc + c.unread_count, 0);

  function selectChannel(channelId: number | null): void {
    // preserveState preserva sidebar collapsed, thread aberta, etc. Inertia
    // partial reload via only[] já é gerenciado pelo Index.tsx — aqui só
    // sinalizamos mudança de filtro.
    const params: Record<string, string | number | undefined> = {};
    if (channelId !== null) {
      params.channel_id = channelId;
    }
    router.get(route('atendimento.inbox.index'), params, {
      preserveState: true,
      preserveScroll: true,
    });
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className="h-7 gap-1.5 px-2 text-xs"
          aria-label="Selecionar canal de atendimento"
        >
          <InboxIcon size={12} className="shrink-0" />
          {selected ? (
            <>
              <HealthIcon health={selected.channel_health} />
              <span className="font-medium truncate max-w-[120px]">{selected.label}</span>
              {selected.unread_count > 0 && (
                <Badge variant="secondary" className="h-4 px-1 text-[10px] leading-none ml-0.5">
                  {selected.unread_count > 99 ? '99+' : selected.unread_count}
                </Badge>
              )}
            </>
          ) : (
            <>
              <span className="font-medium">Todos os canais</span>
              {totalUnread > 0 && (
                <Badge variant="secondary" className="h-4 px-1 text-[10px] leading-none ml-0.5">
                  {totalUnread > 99 ? '99+' : totalUnread}
                </Badge>
              )}
            </>
          )}
          <ChevronDown size={12} className="shrink-0 opacity-60" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="min-w-[260px]">
        <DropdownMenuLabel className="text-[11px] text-muted-foreground font-normal">
          Filtrar inbox por canal
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {/* "Todos os canais" — limpa filter */}
        <DropdownMenuItem
          onSelect={() => selectChannel(null)}
          className={`gap-2 cursor-pointer ${selectedChannelId === null ? 'bg-accent/50 font-medium' : ''}`}
        >
          <InboxIcon size={14} className="text-muted-foreground shrink-0" />
          <span className="flex-1">Todos os canais</span>
          {totalUnread > 0 && (
            <Badge variant="secondary" className="h-4 px-1 text-[10px] leading-none">
              {totalUnread > 99 ? '99+' : totalUnread}
            </Badge>
          )}
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        {availableChannels.map((ch) => {
          const isSelected = selectedChannelId === ch.id;
          return (
            <DropdownMenuItem
              key={ch.id}
              onSelect={() => selectChannel(ch.id)}
              className={`gap-2 cursor-pointer items-start py-1.5 ${isSelected ? 'bg-accent/50 font-medium' : ''}`}
            >
              <HealthIcon health={ch.channel_health} />
              <div className="flex flex-col min-w-0 flex-1">
                <span className="truncate text-sm">{ch.label}</span>
                {ch.display_identifier && (
                  <span className="text-[10px] text-muted-foreground truncate">
                    {formatPhone(ch.display_identifier)}
                  </span>
                )}
              </div>
              {ch.unread_count > 0 && (
                <Badge variant="secondary" className="h-4 px-1 text-[10px] leading-none mt-1">
                  {ch.unread_count > 99 ? '99+' : ch.unread_count}
                </Badge>
              )}
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
