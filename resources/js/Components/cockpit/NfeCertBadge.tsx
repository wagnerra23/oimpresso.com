// @memcofre
//   modulo: Cockpit (NfeCertBadge)
//   stories: US-NFE-001 (último item: badge sidebar quando cert ≤30d)
//   adrs: UI-0008 (cockpit como layout-mae)
//   nota: alerta visual no Sidebar quando cert A1 NFe está vencendo (≤30d)
//         ou já vencido. Lê de `usePage().props.shell.nfe_cert_status`
//         (HandleInertiaRequests::nfeCertStatus). Click leva pra
//         /fiscal/config (tela unificada — Wagner 2026-05-27 consolidação).

import { usePage } from '@inertiajs/react';
import { AlertTriangle, ShieldAlert } from 'lucide-react';

interface NfeCertStatus {
  status: 'sem_cert' | 'ok' | 'vencendo' | 'vencido';
  dias_restantes: number | null;
}

/**
 * Badge alerta cert NFe vencendo/vencido na Sidebar.
 *
 * Renderiza somente nos estados críticos (`vencendo` ou `vencido`) —
 * estados `ok` e `sem_cert` ficam silent (sem cert é OK pra business
 * que não emite NFe; vencendo é pra avisar com antecedência).
 *
 * Posição recomendada: após CompanyPicker, antes do SidebarMenu.
 */
export function NfeCertBadge() {
  const props = usePage().props as {
    shell?: { nfe_cert_status?: NfeCertStatus | null };
  };
  const cert = props?.shell?.nfe_cert_status;
  if (!cert) return null;
  if (cert.status !== 'vencendo' && cert.status !== 'vencido') return null;

  const dias = cert.dias_restantes ?? 0;
  const isVencido = cert.status === 'vencido';

  // Cores semânticas (R-DS-002 exceção: status fixo de alerta).
  const colors = isVencido
    ? {
        border: 'oklch(0.55 0.20 25)',
        bg: 'oklch(0.96 0.04 25 / 0.85)',
        bgDark: 'oklch(0.32 0.10 25 / 0.40)',
        fg: 'oklch(0.40 0.18 25)',
        fgDark: 'oklch(0.78 0.10 25)',
      }
    : {
        border: 'oklch(0.78 0.15 80)',
        bg: 'oklch(0.96 0.05 80 / 0.85)',
        bgDark: 'oklch(0.32 0.08 80 / 0.40)',
        fg: 'oklch(0.42 0.12 80)',
        fgDark: 'oklch(0.82 0.10 80)',
      };

  const Icon = isVencido ? ShieldAlert : AlertTriangle;
  const label = isVencido ? 'Certificado vencido' : 'Cert vence em breve';
  const detail = isVencido
    ? `há ${Math.abs(dias)} dia${Math.abs(dias) === 1 ? '' : 's'}`
    : `${dias} dia${dias === 1 ? '' : 's'} restantes`;

  return (
    <a
      href="/fiscal/config"
      className="nfe-cert-badge"
      title={`${label} — ${detail}. Clique pra renovar.`}
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        padding: '8px 10px',
        margin: '6px 8px',
        borderRadius: 8,
        border: `1px solid ${colors.border}`,
        background: `light-dark(${colors.bg}, ${colors.bgDark})`,
        color: `light-dark(${colors.fg}, ${colors.fgDark})`,
        fontSize: 11,
        fontWeight: 500,
        textDecoration: 'none',
        lineHeight: 1.3,
      }}
    >
      <Icon size={14} style={{ flexShrink: 0 }} aria-hidden />
      <div style={{ minWidth: 0, flex: 1 }}>
        <div style={{ fontWeight: 600, fontSize: 11 }}>{label}</div>
        <div style={{ fontSize: 10, opacity: 0.85 }}>{detail}</div>
      </div>
    </a>
  );
}
