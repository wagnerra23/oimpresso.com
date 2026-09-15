import * as React from 'react';

export interface LogoProps {
  /** Altura do cubo em px. Default 32. */
  size?: number;
  /** Acrescenta o lettering "Office Impresso" (tratamento de aplicação, IBM Plex). */
  wordmark?: boolean;
  /** Com wordmark, acrescenta a assinatura "Software de Gestão para Comunicação Visual". */
  tagline?: boolean;
  /** Cor de "Office" + tagline (default currentColor). "Impresso" é sempre o azul de marca. */
  color?: string;
}

/**
 * Marca oficial Office Impresso — cubo CMYK isométrico em SVG vetorial escalável.
 * Para o wordmark de marketing exato (tipografia custom), use assets/brand/logo-full.svg.
 */
export declare function Logo(props: LogoProps): JSX.Element;
