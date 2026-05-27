import * as React from 'react';
import { Plus } from 'lucide-react';

/**
 * @deprecated ADR 0190 (2026-05-25) — primary INTERNO das telas é SEMPRE roxo médio
 * universal `oklch(0.55 0.15 295)`, independente do grupo. Use
 * `<PageHeaderPrimary>` de `@/Components/PageHeader` em código novo.
 *
 * Este wrapper continua funcionando nas telas Jana legadas — agora RENDERIZA
 * ROXO 295 universal (não mais azul 220 do grupo `ia`). API preservada pra compat.
 *
 * Comportamento NOVO (ADR 0190):
 *   bg:     oklch(0.55 0.15 295)   roxo médio universal
 *   border: oklch(0.45 0.15 295)   roxo escuro
 *   color:  oklch(0.99 0 0)        branco
 *
 * Migrar de:
 *   <JanaPrimaryButton onClick={x}>Conversar com Jana</JanaPrimaryButton>
 *
 * Pra:
 *   <PageHeaderPrimary label="Conversar com Jana" onClick={x} />
 */
interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  hideIcon?: boolean;
}

export default function JanaPrimaryButton({
  children,
  hideIcon,
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
