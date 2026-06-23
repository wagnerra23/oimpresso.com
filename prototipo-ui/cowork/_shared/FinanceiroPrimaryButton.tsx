import * as React from 'react';
import { Plus } from 'lucide-react';
import { SIDEBAR_GROUP_HUE } from '@/Components/cockpit/shared';

/**
 * @deprecated ADR 0190 (2026-05-25) — primary INTERNO das telas é SEMPRE roxo médio
 * universal `oklch(0.55 0.15 295)`, independente do grupo. Use
 * `<PageHeaderPrimary>` de `@/Components/PageHeader` em código novo.
 *
 * Este wrapper continua funcionando pras 12 telas Financeiro legadas — agora
 * RENDERIZA ROXO 295 universal (não mais verde 145 do grupo `financas`). API
 * preservada pra compat. Migração de imports é Wave 2.
 *
 * Histórico:
 * - 2026-05-21 ADR 0182: nasceu como override hue 145 (verde Financeiro) pra
 *   sobrescrever magenta canon UPOS `.os-btn.primary` legacy.
 * - 2026-05-25 ADR 0190: roxo 295 universal supersede hue-per-grupo. Wrapper
 *   mantido como DEPRECATED pra não quebrar 12 telas Financeiro de uma vez.
 *
 * Comportamento NOVO (ADR 0190):
 *   bg:     oklch(0.55 0.15 295)   roxo médio universal
 *   border: oklch(0.45 0.15 295)   roxo escuro
 *   color:  oklch(0.99 0 0)        branco
 *
 * Migrar de:
 *   <FinanceiroPrimaryButton onClick={x}>Novo título</FinanceiroPrimaryButton>
 *
 * Pra:
 *   <PageHeaderPrimary label="Novo título" onClick={x} />
 */
interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  hideIcon?: boolean;
  /** @deprecated ADR 0190 — hue per grupo não se aplica mais ao primary. Prop ignorada. */
  group?: keyof typeof SIDEBAR_GROUP_HUE;
}

export default function FinanceiroPrimaryButton({
  children,
  hideIcon,
  className = '',
  style,
  group: _group,  // ADR 0190 — ignorado (compat de assinatura)
  ...rest
}: Props) {
  return (
    <button
      type="button"
      className={`os-btn primary ${className}`.trim()}
      style={{
        backgroundColor: 'oklch(0.55 0.15 295)',  // ADR 0190 roxo universal
        borderColor: 'oklch(0.45 0.15 295)',
        color: 'oklch(0.99 0 0)',
        fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
        ...style,
      }}
      {...rest}
    >
      {!hideIcon && <Plus size={13} />}
      {children}
    </button>
  );
}
