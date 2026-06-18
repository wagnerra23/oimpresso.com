import { AlertTriangle, QrCode } from 'lucide-react';

import { Inline, Stack } from '@/Components/layout';

import type { UnhealthyChannel } from './helpers';

/**
 * ChannelHealthBanner — banner "canal caiu — religar" no topo da Caixa Unificada.
 *
 * US-WA-308 (incidente 2026-06-18): canal whatsmeow deslogou ("401: logged out
 * from another device") e o app nunca soube → `channel_health` ficou `healthy` e
 * a tela não avisava. Agora o `whatsmeow:health-probe` (cron 3min) converge o
 * health real e este banner o exibe — com clique direto pro re-parear (QR) que
 * já existe em `/atendimento/canais/{id}`.
 *
 * Lê o prop EAGER `unhealthyChannels` (não-deferred) pra aparecer no first-paint.
 * Layout via primitivos `<Stack>`/`<Inline>` (ADR 0253); cor de status via token
 * semântico `text-destructive` (ds/no-adhoc-status-text).
 */
const HEALTH_LABEL: Record<string, string> = {
  disconnected: 'desconectado',
  banned: 'bloqueado pela Meta',
  degraded: 'instável',
};

export default function ChannelHealthBanner({ channels }: { channels: UnhealthyChannel[] }) {
  if (!channels || channels.length === 0) return null;

  return (
    <Stack gap={1} className="shrink-0 px-1" data-testid="caixa-unif-health-banner">
      {channels.map((ch) => {
        const label = HEALTH_LABEL[ch.channel_health] ?? 'desconectado';
        const caiu = ch.last_health_check_at
          ? new Date(ch.last_health_check_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
          : null;

        return (
          <Inline
            key={ch.id}
            gap={3}
            role="alert"
            className="rounded-md border border-red-300 bg-red-50 px-3.5 py-2.5 dark:border-red-900/60 dark:bg-red-950/40"
            data-testid={`caixa-unif-health-banner-${ch.id}`}
          >
            <AlertTriangle size={18} className="shrink-0 text-destructive" aria-hidden />
            <div className="min-w-0 flex-1 leading-tight">
              <p className="text-[13px] font-semibold text-destructive">
                WhatsApp {ch.label} {label}
              </p>
              <p className="text-[12px] text-destructive/80">
                Novas mensagens não estão entrando até você religar
                {caiu ? ` · caiu às ${caiu}` : ''}.
              </p>
            </div>
            <a
              href={route('atendimento.channels.show', ch.id)}
              className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-red-300 bg-white px-3 py-1.5 text-[12.5px] font-medium text-destructive transition-colors hover:bg-red-100 dark:border-red-900/60 dark:bg-transparent dark:hover:bg-red-950"
              data-testid={`caixa-unif-health-banner-religar-${ch.id}`}
            >
              <QrCode size={15} aria-hidden />
              Religar agora
            </a>
          </Inline>
        );
      })}
    </Stack>
  );
}
