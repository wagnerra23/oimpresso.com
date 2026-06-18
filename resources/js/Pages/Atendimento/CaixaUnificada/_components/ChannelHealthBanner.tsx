import { useState } from 'react';

import { router } from '@inertiajs/react';
import { PlugZap, RefreshCw, WifiOff, X } from 'lucide-react';

import { Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';

import type { AccountItem, ChannelCatalogItem, UnhealthyChannel } from './helpers';

/**
 * ChannelHealthBanner — banner "canal caiu — reconectar" no topo da LISTA da Caixa.
 *
 * US-WA-308 (incidente 2026-06-18): canal whatsmeow deslogou ("401: logged out
 * from another device") e o app nunca soube → `channel_health` ficou `healthy` e
 * a tela não avisava por ~3h. O `whatsmeow:health-probe` (cron 3min) passou a
 * convergir o health real e este banner o exibe.
 *
 * Design Cowork ([CC]→[CL] 2026-06-18, [W] escolheu trocar o visual + posicionar
 * no topo da COLUNA de conversas — fiel ao protótipo): tom graduado warn/err,
 * dispensável, resumo multi-canal e CTA Reconectar.
 *
 * Fonte autoritativa = prop EAGER `unhealthyChannels` (cron-convergido, Tier 0 +
 * ACL no Controller). Como ele não carrega contagem nem glyph, o banner ENRIQUECE
 * `count` (conversas afetadas) e `short` (label do canal) a partir de `accounts` +
 * `catalog` — os mesmos `availableAccounts`/`availableChannels` que a lista já
 * recebe (zero backend novo). Estados REAIS do probe: `disconnected`/`banned`
 * (fora do ar, err) e `degraded` (warn). Não existe estado "down".
 *
 * Layout por primitivos `<Stack>`/`<Inline>` (ADR 0253 — sem flex/grid solto; a
 * centragem de ícone usa o idioma permitido `grid place-items-center`). Cor 100%
 * via tokens semânticos `warning`/`destructive` (R1 + ADR 0281 → flip dark).
 * Reconectar navega pra `/atendimento/canais/{id}` (re-parear/QR já existente).
 */
const ERR_STATES = new Set(['disconnected', 'banned']);

const STATE_VERB: Record<string, string> = {
  disconnected: 'está fora do ar',
  banned: 'foi bloqueado pela Meta',
  degraded: 'está degradado',
};

const STATE_LABEL: Record<string, string> = {
  disconnected: 'fora do ar',
  banned: 'bloqueado pela Meta',
  degraded: 'degradado',
};

const isErr = (health: string) => ERR_STATES.has(health);

function providerName(type: string | null | undefined): string {
  if (!type) return 'Canal';
  if (type.startsWith('whatsapp')) return 'WhatsApp';
  if (type.startsWith('email')) return 'Email';
  if (type.startsWith('instagram')) return 'Instagram';
  if (type.startsWith('messenger')) return 'Messenger';
  return 'Canal';
}

const plural = (n: number) => (n === 1 ? 'conversa afetada' : 'conversas afetadas');

interface Props {
  channels: UnhealthyChannel[];
  accounts?: AccountItem[];
  catalog?: ChannelCatalogItem[];
}

export default function ChannelHealthBanner({ channels, accounts = [], catalog = [] }: Props) {
  const [dismissed, setDismissed] = useState(false);
  if (dismissed || !channels || channels.length === 0) return null;

  // Enriquecimento (count + short) a partir do que a lista já tem em mãos.
  const countById = new Map(accounts.map((a) => [a.id, a.count]));
  const shortByType = new Map(catalog.map((c) => [c.id, c.short]));
  const nameOf = (ch: UnhealthyChannel) => shortByType.get(ch.type) ?? providerName(ch.type);
  const countOf = (ch: UnhealthyChannel) => countById.get(ch.id) ?? 0;

  const worst = channels.some((c) => isErr(c.channel_health)) ? 'err' : 'warn';
  const multi = channels.length > 1;
  const tone =
    worst === 'err'
      ? 'bg-destructive-soft text-destructive-fg border-destructive/40'
      : 'bg-warning-soft text-warning-fg border-warning/40';
  const iconWrap = worst === 'err' ? 'bg-destructive/15' : 'bg-warning/20';

  const reconnect = (id: number) => router.visit(route('atendimento.channels.show', id));
  const c0 = channels[0]!;
  const c0Count = countOf(c0);
  const totalConvs = channels.reduce((sum, c) => sum + countOf(c), 0);

  return (
    <Stack
      role="status"
      aria-live="polite"
      className={cn('mx-2.5 mb-0.5 mt-2.5 shrink-0 gap-1.5 rounded-lg border px-3 py-2.5', tone)}
      data-testid="caixa-unif-health-banner"
    >
      <Inline align="start" className="gap-2.5">
        <span className={cn('mt-px grid place-items-center h-6 w-6 shrink-0 rounded-md', iconWrap)}>
          {worst === 'err' ? <PlugZap size={14} aria-hidden /> : <WifiOff size={14} aria-hidden />}
        </span>
        <div className="min-w-0 flex-1">
          {multi ? (
            <>
              <b className="block text-[12.5px] font-semibold">
                {channels.length} canais com problema de conexão
              </b>
              <span className="text-[11.5px] opacity-90">
                {totalConvs > 0
                  ? `${totalConvs} ${totalConvs === 1 ? 'conversa pode' : 'conversas podem'} não receber mensagens novas.`
                  : 'Conversas podem não receber mensagens novas.'}
              </span>
            </>
          ) : (
            <>
              <b className="block text-[12.5px] font-semibold">
                {nameOf(c0)} · {c0.label} {STATE_VERB[c0.channel_health] ?? 'está fora do ar'}.
              </b>
              <span className="text-[11.5px] opacity-90">
                {isErr(c0.channel_health)
                  ? 'Mensagens novas não estão chegando.'
                  : 'Sincronização lenta — pode haver atraso.'}
                {c0Count > 0 ? ` ${c0Count} ${plural(c0Count)}.` : ''}
              </span>
            </>
          )}
        </div>
        <button
          type="button"
          onClick={() => setDismissed(true)}
          className="grid place-items-center h-5 w-5 shrink-0 rounded opacity-60 hover:opacity-100"
          title="Dispensar até a próxima verificação"
          aria-label="Dispensar aviso"
          data-testid="caixa-unif-health-dismiss"
        >
          <X size={13} aria-hidden />
        </button>
      </Inline>

      {!multi ? (
        <div className="pl-[34px]">
          <button
            type="button"
            onClick={() => reconnect(c0.id)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11.5px] font-semibold transition-colors',
              worst === 'err'
                ? 'border-destructive/40 bg-destructive/10 hover:bg-destructive/20'
                : 'border-warning/40 bg-warning/15 hover:bg-warning/25',
            )}
            data-testid="caixa-unif-health-reconnect"
          >
            <RefreshCw size={12} aria-hidden /> Reconectar canal
          </button>
        </div>
      ) : (
        <Stack gap={1} className="pl-[34px]">
          {channels.map((c) => {
            const n = countOf(c);
            return (
              <Inline key={c.id} className="gap-2 text-[11.5px]">
                <span
                  className={cn(
                    'h-1.5 w-1.5 shrink-0 animate-pulse rounded-full',
                    isErr(c.channel_health) ? 'bg-destructive' : 'bg-warning',
                  )}
                  aria-hidden
                />
                <span className="min-w-0 flex-1 truncate">
                  <b className="font-semibold">
                    {nameOf(c)} · {c.label}
                  </b>
                  <span className="opacity-85">
                    {' '}
                    — {STATE_LABEL[c.channel_health] ?? 'fora do ar'}
                    {n > 0 ? `, ${n} conversas` : ''}
                  </span>
                </span>
                <button
                  type="button"
                  onClick={() => reconnect(c.id)}
                  className="shrink-0 font-semibold underline underline-offset-2 hover:opacity-80"
                  data-testid={`caixa-unif-health-reconnect-${c.id}`}
                >
                  Reconectar
                </button>
              </Inline>
            );
          })}
        </Stack>
      )}
    </Stack>
  );
}
