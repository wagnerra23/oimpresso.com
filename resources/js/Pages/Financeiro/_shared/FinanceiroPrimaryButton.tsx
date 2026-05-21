import * as React from 'react';
import { Plus } from 'lucide-react';
import { SIDEBAR_GROUP_HUE } from '@/Components/cockpit/shared';

/**
 * FinanceiroPrimaryButton — botão primary canon das 12 telas Financeiro.
 *
 * ADR 0182 + Wagner 2026-05-21 review: o `.os-btn.primary` canon UltimatePOS
 * usa magenta `oklch(0.58 0.12 330)` que NÃO harmoniza com o hue 145 (verde
 * financas) dos ghost tabs ARIA. Resultado: header com botão "rosa-vermelho"
 * conflitando com sidebar/ghosts verdes.
 *
 * Este componente sobrescreve o background-color do `.os-btn.primary` pelo
 * hue do grupo `financas` (145) lido de SIDEBAR_GROUP_HUE — fica visualmente
 * harmônico com sidebar v3 + ghost tabs.
 *
 * Mesma assinatura de `<button>` HTML — pode usar `onClick`, `disabled`,
 * `type`, etc. Conteúdo `children` renderiza ao lado do ícone Plus (passe
 * sem `<Plus/>` próprio).
 *
 * Uso canon nas 12 telas Financeiro (sempre no canto direito do header,
 * depois do `<FinanceiroSubNav hidePrimary .../>` — Zona R ADR 0182):
 *
 *   <FinanceiroPrimaryButton onClick={() => router.visit('/financeiro/x/novo')}>
 *     Nova X
 *   </FinanceiroPrimaryButton>
 */
interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Esconde o ícone Plus (default render). */
  hideIcon?: boolean;
  /** Override do hue (default: 145 financas). Útil pra primary de outros grupos no futuro. */
  group?: keyof typeof SIDEBAR_GROUP_HUE;
}

export default function FinanceiroPrimaryButton({
  children,
  hideIcon,
  group = 'financas',
  className = '',
  style,
  ...rest
}: Props) {
  const hue = SIDEBAR_GROUP_HUE[group] ?? 145;
  return (
    <button
      type="button"
      className={`os-btn primary ${className}`.trim()}
      style={{
        backgroundColor: `oklch(0.55 0.15 ${hue})`,
        color: 'oklch(0.99 0 0)',
        ...style,
      }}
      {...rest}
    >
      {!hideIcon && <Plus size={13} />}
      {children}
    </button>
  );
}
