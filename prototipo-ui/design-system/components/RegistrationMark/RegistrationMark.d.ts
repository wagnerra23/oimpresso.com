export interface RegistrationMarkProps {
  /** Glyph size in px (default 18). */
  size?: number;
  /** Stroke color (defaults to currentColor). */
  color?: string;
  /** Stroke width (default 1.5). */
  strokeWidth?: number;
}

/** Press registration target (mira) — the print-craft signature glyph (DS). */
export declare function RegistrationMark(props: RegistrationMarkProps): JSX.Element;
