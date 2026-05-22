import * as React from 'react';
import { Plus } from 'lucide-react';
import { SIDEBAR_GROUP_HUE } from '@/Components/cockpit/shared';

/**
 * JanaPrimaryButton — botão primary canon das telas do módulo IA (Jana).
 *
 * ADR 0182 + GUIA-SIDEBAR-V3: hue OKLCH 220 (azul — grupo `ia` topo)
 * em vez do magenta canon UPOS legacy. Visualmente harmônico com ghost
 * tabs ARIA do JanaSubNav + sidebar v3.
 *
 * Mesma API de `<button>` HTML — aceita `onClick`, `disabled`, etc.
 *
 * Uso:
 *
 *   <JanaPrimaryButton onClick={() => router.visit('/jana')}>
 *     Conversar com Jana
 *   </JanaPrimaryButton>
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
  const hue = SIDEBAR_GROUP_HUE['ia'] ?? 220;
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
