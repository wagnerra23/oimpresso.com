// ChannelChipsRow.tsx — chips horizontais de canais (acima da shell 3-col).
//
// Replica visual do `.om-filter` Cowork (inbox-page.css L7-50):
//   - 1 chip "Todos" + 7 chips por TIPO de canal (Baileys/Meta/Z-API/IG/FB/Email/ML)
//   - Cada chip mostra glyph + nome curto + count (em mono) ou tag "em breve"
//   - Hover/sel borda primary; canais inativos ficam mais discretos (cor mute)
//   - Sub-row aparece quando type selecionado tem 2+ contas (accounts filter)
//
// `data-testid` consistente: `caixa-unif-channel-chip-{id}` + `caixa-unif-account-chip-{id}`.

import { useMemo } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import type { AccountItem, ChannelCatalogItem } from './helpers';

interface Props {
  channels: ChannelCatalogItem[];
  accounts: AccountItem[];
  channelTypeFilter: string | null;
  accountFilter: number | null;
  /** Total convs com filtros zerados (canal=all) — pra mostrar no chip "Todos". */
  totalAll: number;
}

export default function ChannelChipsRow({
  channels, accounts, channelTypeFilter, accountFilter, totalAll,
}: Props) {
  // Apenas contas do TYPE selecionado (sub-row condicional)
  const accountsForType = useMemo(
    () => (channelTypeFilter ? accounts.filter(a => a.channel_type === channelTypeFilter) : []),
    [channelTypeFilter, accounts],
  );

  function navigateTo(channel: string | null, accountId: number | null) {
    const params: Record<string, string | number> = {};
    if (channel) params.channel = channel;
    if (accountId !== null) params.account_id = accountId;
    router.get(
      route('atendimento.caixa-unificada.index'),
      params,
      { preserveScroll: true, preserveState: true, only: ['conversations', 'stats'] },
    );
  }

  return (
    <div className="flex flex-col">
      {/* Linha 1 — chips por TYPE */}
      <div
        className="flex flex-wrap items-center gap-1.5 border-b px-4 py-2 bg-muted/30"
        role="tablist"
        aria-label="Filtrar conversas por canal"
      >
        <button
          type="button"
          role="tab"
          data-testid="caixa-unif-channel-chip-all"
          aria-selected={channelTypeFilter === null}
          onClick={() => navigateTo(null, null)}
          className={cn(
            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11.5px] font-medium transition-colors',
            channelTypeFilter === null
              ? 'border-primary bg-primary/10 text-primary'
              : 'border-border bg-card text-foreground hover:border-muted-foreground',
          )}
        >
          <span>Todos</span>
          <em className="not-italic font-mono text-[10px] text-muted-foreground bg-muted px-1.5 py-px rounded-full min-w-[16px] text-center">
            {totalAll}
          </em>
        </button>

        {channels.map(ch => {
          const isSel = channelTypeFilter === ch.id;
          const isComing = ch.status === 'em_breve';
          return (
            <button
              key={ch.id}
              type="button"
              role="tab"
              data-testid={`caixa-unif-channel-chip-${ch.id}`}
              aria-selected={isSel}
              title={ch.label}
              onClick={() => navigateTo(ch.id, null)}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11.5px] font-medium transition-colors',
                isSel
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-border bg-card text-foreground hover:border-muted-foreground',
                isComing && 'text-muted-foreground',
              )}
            >
              {/* Glyph circular colorido (background OKLCH per hue) */}
              <span
                className="inline-grid place-items-center rounded-full text-white font-bold flex-shrink-0"
                style={{
                  width: 14, height: 14, fontSize: 9,
                  background: `oklch(0.62 0.14 ${ch.hue})`,
                }}
                aria-hidden
              >
                {ch.glyph}
              </span>
              <span>{ch.short}</span>
              {isComing ? (
                <em className="not-italic text-[9.5px] text-muted-foreground/70 font-normal">em breve</em>
              ) : (
                <em className="not-italic font-mono text-[10px] text-muted-foreground bg-muted px-1.5 py-px rounded-full min-w-[16px] text-center">
                  {ch.count}
                </em>
              )}
            </button>
          );
        })}
      </div>

      {/* Linha 2 — contas (sub-filtro), só quando TYPE selecionado tem 2+ contas */}
      {channelTypeFilter && accountsForType.length > 1 && (
        <div
          className="flex flex-wrap items-center gap-1 border-b px-4 py-1.5 bg-muted/15"
          role="tablist"
          aria-label="Filtrar por conta"
        >
          <button
            type="button"
            role="tab"
            data-testid="caixa-unif-account-chip-all"
            aria-selected={accountFilter === null}
            onClick={() => navigateTo(channelTypeFilter, null)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10.5px] font-medium transition-colors',
              accountFilter === null
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border bg-card text-foreground hover:border-muted-foreground',
            )}
          >
            <span>Todas as contas</span>
            <em className="not-italic font-mono text-[10px] text-muted-foreground">
              {accountsForType.length}
            </em>
          </button>

          {accountsForType.map(acc => {
            const isSel = accountFilter === acc.id;
            const isComing = acc.status === 'em_breve';
            return (
              <button
                key={acc.id}
                type="button"
                role="tab"
                data-testid={`caixa-unif-account-chip-${acc.id}`}
                aria-selected={isSel}
                title={`${acc.label} · ${acc.handle}`}
                onClick={() => navigateTo(channelTypeFilter, acc.id)}
                className={cn(
                  'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10.5px] font-medium transition-colors',
                  isSel
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border bg-card text-foreground hover:border-muted-foreground',
                  isComing && 'text-muted-foreground',
                )}
              >
                <b className="font-semibold">{acc.label}</b>
                <span className="font-mono text-[10px] text-muted-foreground">{acc.handle}</span>
                {isComing ? (
                  <em className="not-italic text-[9.5px] text-muted-foreground/70 font-normal">em breve</em>
                ) : (
                  <em className="not-italic font-mono text-[10px] text-muted-foreground">
                    {acc.count}
                  </em>
                )}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
