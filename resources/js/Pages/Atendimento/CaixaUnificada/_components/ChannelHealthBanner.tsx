import { useState } from 'react';

import { router } from '@inertiajs/react';
import { AlertTriangle, PlugZap, RefreshCw, X } from 'lucide-react';

import { Box, Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';

import { relativeTimeBR } from './helpers';
import type { UnhealthyChannel } from './helpers';

/**
 * ChannelHealthBanner — banner "canal caiu — reconectar" no topo da Caixa Unificada.
 *
 * US-WA-308 (incidente 2026-06-18): canal whatsmeow deslogou ("401: logged out
 * from another device") e o app nunca soube → `channel_health` ficou `healthy` e
 * a tela não avisava por ~3h. O `whatsmeow:health-probe` (cron 3min) passou a
 * convergir o health real e este banner o exibe.
 *
 * Redesign Cowork ([CC]→[CL] 2026-06-18, [W] escolheu trocar o visual): tom
 * graduado warn/err, dispensável, resumo multi-canal e CTA Reconectar. Continua
 * lendo o prop EAGER `unhealthyChannels` (não-deferred → aparece no first-paint),
 * mapeado aos estados REAIS que o probe emite — `disconnected`/`banned` (fora do
 * ar, err) e `degraded` (instável, warn). Não existe estado "down": o backend
 * nunca o emite.
 *
 * Cor 100% via tokens semânticos `warning`/`destructive` (ds/no-adhoc-status-text
 * + ui:lint R1 · ADR 0281) — flipam sozinhos no dark. Reconectar navega pra
 * `/atendimento/canais/{id}` (re-parear/QR já existente).
 */
const ERR_STATES = new Set(['disconnected', 'banned']);

const STATE_VERB: Record<string, string> = {
  disconnected: 'está fora do ar',
  banned: 'foi bloqueado pela Meta',
  degraded: 'está instável',
};

const STATE_LABEL: Record<string, string> = {
  disconnected: 'fora do ar',
  banned: 'bloqueado pela Meta',
  degraded: 'instável',
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

/** "verificado há 5min" / "verificado agora" / "verificado 14:32" / "" */
function checkLabel(iso: string | null): string {
  const rel = relativeTimeBR(iso);
  if (!rel) return '';
  if (rel === 'agora') return 'verificado agora';
  if (/^\d+min$/.test(rel)) return `verificado há ${rel}`;
  return `verificado ${rel}`;
}

export default function ChannelHealthBanner({ channels }: { channels: UnhealthyChannel[] }) {
  const [dismissed, setDismissed] = useState(false);
  if (dismissed || !channels || channels.length === 0) return null;

  const worst = channels.some((c) => isErr(c.channel_health)) ? 'err' : 'warn';
  const multi = channels.length > 1;
  const tone =
    worst === 'err'
      ? 'bg-destructive-soft text-destructive-fg border-destructive/40'
      : 'bg-warning-soft text-warning-fg border-warning/40';
  const iconWrap = worst === 'err' ? 'bg-destructive/15' : 'bg-warning/20';

  const reconnect = (id: number) => router.visit(route('atendimento.channels.show', id));
  const c0 = channels[0]!;
  const chk = checkLabel(c0.last_health_check_at);

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        'mx-2.5 mb-0.5 mt-2.5 flex shrink-0 flex-col gap-1.5 rounded-lg border px-3 py-2.5',
        tone,
      )}
      data-testid="caixa-unif-health-banner"
    >
      <Inline align="start" className="gap-2.5">
        <Box rounded="md" className={cn('mt-px grid place-items-center h-6 w-6 shrink-0', iconWrap)}>
          {worst === 'err' ? <PlugZap size={14} aria-hidden /> : <AlertTriangle size={14} aria-hidden />}
        </Box>
        <div className="min-w-0 flex-1">
          {multi ? (
            <>
              <b className="block text-[12.5px] font-semibold">
                {channels.length} canais com problema de conexão
              </b>
              <span className="text-[11.5px] opacity-90">
                Novas mensagens podem não estar entrando até você reconectar.
              </span>
            </>
          ) : (
            <>
              <b className="block text-[12.5px] font-semibold">
                {providerName(c0.type)} · {c0.label} {STATE_VERB[c0.channel_health] ?? 'está fora do ar'}.
              </b>
              <span className="text-[11.5px] opacity-90">
                {isErr(c0.channel_health)
                  ? 'Novas mensagens não estão entrando até você reconectar.'
                  : 'Sincronização instável — pode haver atraso na entrega.'}
                {chk ? ` · ${chk}.` : ''}
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
          {channels.map((c) => (
            <Inline key={c.id} gap={2} className="text-[11.5px]">
              <span
                className={cn(
                  'h-1.5 w-1.5 shrink-0 animate-pulse rounded-full',
                  isErr(c.channel_health) ? 'bg-destructive' : 'bg-warning',
                )}
                aria-hidden
              />
              <span className="min-w-0 flex-1 truncate">
                <b className="font-semibold">
                  {providerName(c.type)} · {c.label}
                </b>
                <span className="opacity-85"> — {STATE_LABEL[c.channel_health] ?? 'fora do ar'}</span>
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
          ))}
        </Stack>
      )}
    </div>
  );
}
