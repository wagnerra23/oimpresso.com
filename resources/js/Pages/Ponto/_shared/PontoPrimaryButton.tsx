import * as React from 'react';
import { Plus } from 'lucide-react';
import { SIDEBAR_GROUP_HUE } from '@/Components/cockpit/shared';

/**
 * @deprecated ADR 0190 (2026-05-25) — primary INTERNO das telas é SEMPRE roxo médio
 * universal `oklch(0.55 0.15 295)`, independente do grupo. Use
 * `<PageHeaderPrimary>` de `@/Components/PageHeader` em código novo.
 *
 * Este wrapper continua funcionando nas 4 telas Ponto legadas — agora RENDERIZA
 * ROXO 295 universal (era `pessoas=88 limão` no canon SIDEBAR_GROUP_HUE atual).
 * Prop `group` mantida na assinatura por compat mas IGNORADA.
 *
 * Comportamento NOVO (ADR 0190):
 *   bg:     oklch(0.55 0.15 295)   roxo médio universal
 *   border: oklch(0.45 0.15 295)   roxo escuro
 *   color:  oklch(0.99 0 0)        branco
 *
 * Migrar de:
 *   <PontoPrimaryButton onClick={x}>Bater ponto</PontoPrimaryButton>
 *
 * Pra:
 *   <PageHeaderPrimary label="Bater ponto" onClick={x} />
 */
interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Esconde o ícone Plus (default render). */
  hideIcon?: boolean;
  /** @deprecated ADR 0190 — hue per grupo não se aplica mais ao primary. Prop ignorada. */
  group?: keyof typeof SIDEBAR_GROUP_HUE;
}

export default function PontoPrimaryButton({
  children,
  hideIcon,
  group: _group,  // ADR 0190 — ignorado (compat de assinatura)
  className = '',
  style,
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
