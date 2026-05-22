import * as React from 'react';
import { Plus } from 'lucide-react';
import { SIDEBAR_GROUP_HUE } from '@/Components/cockpit/shared';

/**
 * PontoPrimaryButton — botão primary canon das telas Ponto.
 *
 * ADR 0182 + Wave Ponto 2026-05-22: o `.os-btn.primary` canon UltimatePOS
 * usa magenta `oklch(0.58 0.12 330)` que NÃO harmoniza com o hue 295 (roxo
 * claro pessoas) dos ghost tabs ARIA. Resultado: header com botão "rosa"
 * conflitando com sidebar/ghosts roxos.
 *
 * Este componente sobrescreve o background-color do `.os-btn.primary` pelo
 * hue do grupo `pessoas` (295) lido de SIDEBAR_GROUP_HUE — fica visualmente
 * harmônico com sidebar v3 + ghost tabs.
 *
 * Mesma assinatura de `<button>` HTML — pode usar `onClick`, `disabled`,
 * `type`, etc. Conteúdo `children` renderiza ao lado do ícone Plus (passe
 * sem `<Plus/>` próprio).
 *
 * Uso canon nas telas Ponto (sempre no canto direito do header,
 * depois do `<PontoSubNav hidePrimary .../>` — Zona R ADR 0182):
 *
 *   <PontoPrimaryButton onClick={() => router.visit('/ponto')}>
 *     Bater ponto
 *   </PontoPrimaryButton>
 */
interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Esconde o ícone Plus (default render). */
  hideIcon?: boolean;
  /** Override do hue (default: 295 pessoas). Útil pra primary de outros grupos no futuro. */
  group?: keyof typeof SIDEBAR_GROUP_HUE;
}

export default function PontoPrimaryButton({
  children,
  hideIcon,
  group = 'pessoas',
  className = '',
  style,
  ...rest
}: Props) {
  const hue = SIDEBAR_GROUP_HUE[group] ?? 295;
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
