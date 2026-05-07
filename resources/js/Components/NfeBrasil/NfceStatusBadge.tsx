// @memcofre
//   modulo: NfeBrasil (NfceStatusBadge)
//   stories: US-NFE-002 fase 2C (badge UI status NFC-e pós-venda)
//   adrs: UI-0008 (cockpit), 0058 (Centrifugo CT 100), 0062 (Hostinger sem daemons)
//   nota: badge reativo que polla status NFC-e via useNfceStatus + renderiza
//         estado visual semântico (loading spinner / OK verde / erro vermelho).
//         Plugável em qualquer Page Inertia que tenha transaction_id em props.

import { useNfceStatus } from '@/Hooks/useNfceStatus';
import { CheckCircle2, Clock, Loader2, XCircle } from 'lucide-react';

interface NfceStatusBadgeProps {
  transactionId: number;
  /** Texto custom em vez de "NFC-e". Útil quando integrar tela com NFe55 também. */
  label?: string;
  /** Compact mode = só ícone + chave. Default false (mostra detalhes). */
  compact?: boolean;
}

export function NfceStatusBadge({
  transactionId,
  label = 'NFC-e',
  compact = false,
}: NfceStatusBadgeProps) {
  const { data, isPolling, hasGivenUp } = useNfceStatus(transactionId);

  // Estado: ainda emitindo (sem dados ou status pendente/null)
  if (!data || data.status === null || data.status === 'pendente') {
    if (hasGivenUp) {
      return (
        <Banner
          tone="warn"
          Icon={Clock}
          title={`${label}: aguardando SEFAZ`}
          detail="A SEFAZ pode estar lenta. Atualize em alguns minutos."
        />
      );
    }
    return (
      <Banner
        tone="info"
        Icon={isPolling ? Loader2 : Clock}
        spin={isPolling}
        title={`Emitindo ${label}…`}
        detail={
          data?.status === 'pendente'
            ? 'Job processando — aguarde retorno SEFAZ.'
            : 'Aguardando job NFC-e iniciar.'
        }
      />
    );
  }

  if (data.status === 'autorizada') {
    return (
      <Banner
        tone="ok"
        Icon={CheckCircle2}
        title={`${label} #${data.numero} autorizada`}
        detail={
          compact
            ? data.chave_44 ?? ''
            : `Chave ${data.chave_44 ?? '—'} · cstat ${data.cstat ?? '—'}`
        }
      />
    );
  }

  // rejeitada / denegada
  return (
    <Banner
      tone="error"
      Icon={XCircle}
      title={`${label} ${data.status}`}
      detail={
        compact
          ? data.motivo ?? `cstat ${data.cstat ?? '—'}`
          : `cstat ${data.cstat ?? '—'} — ${data.motivo ?? 'sem motivo'}`
      }
    />
  );
}

// ── Internal banner ──────────────────────────────────────────────────────

type Tone = 'info' | 'ok' | 'warn' | 'error';

interface BannerProps {
  tone: Tone;
  Icon: typeof CheckCircle2;
  title: string;
  detail: string;
  spin?: boolean;
}

function Banner({ tone, Icon, title, detail, spin = false }: BannerProps) {
  // Cores semânticas oklch (R-DS-002 exceção status).
  const palette: Record<Tone, { border: string; bg: string; bgDark: string; fg: string; fgDark: string }> = {
    info: {
      border: 'oklch(0.78 0.10 230)',
      bg: 'oklch(0.96 0.03 230 / 0.85)',
      bgDark: 'oklch(0.32 0.06 230 / 0.40)',
      fg: 'oklch(0.42 0.10 230)',
      fgDark: 'oklch(0.82 0.08 230)',
    },
    ok: {
      border: 'oklch(0.70 0.18 145)',
      bg: 'oklch(0.96 0.05 145 / 0.85)',
      bgDark: 'oklch(0.32 0.10 145 / 0.40)',
      fg: 'oklch(0.40 0.16 145)',
      fgDark: 'oklch(0.80 0.10 145)',
    },
    warn: {
      border: 'oklch(0.78 0.15 80)',
      bg: 'oklch(0.96 0.05 80 / 0.85)',
      bgDark: 'oklch(0.32 0.08 80 / 0.40)',
      fg: 'oklch(0.42 0.12 80)',
      fgDark: 'oklch(0.82 0.10 80)',
    },
    error: {
      border: 'oklch(0.55 0.20 25)',
      bg: 'oklch(0.96 0.04 25 / 0.85)',
      bgDark: 'oklch(0.32 0.10 25 / 0.40)',
      fg: 'oklch(0.40 0.18 25)',
      fgDark: 'oklch(0.78 0.10 25)',
    },
  };
  const c = palette[tone];

  return (
    <div
      role="status"
      aria-live="polite"
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        padding: '10px 12px',
        borderRadius: 8,
        border: `1px solid ${c.border}`,
        background: `light-dark(${c.bg}, ${c.bgDark})`,
        color: `light-dark(${c.fg}, ${c.fgDark})`,
        fontSize: 12,
      }}
    >
      <Icon
        size={18}
        aria-hidden
        style={{ flexShrink: 0, animation: spin ? 'spin 1s linear infinite' : undefined }}
      />
      <div style={{ minWidth: 0, flex: 1 }}>
        <div style={{ fontWeight: 600, fontSize: 13, lineHeight: 1.3 }}>{title}</div>
        <div style={{ fontSize: 11, opacity: 0.85, marginTop: 2 }}>{detail}</div>
      </div>
    </div>
  );
}
