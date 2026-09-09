/**
 * RegistrationMark — the press registration target (mira de registro).
 * The signature glyph of the print-craft system: a ring crossed by four ticks.
 * Use as a section marker, an empty-state motif, or the app's brand glyph.
 * Pure, dependency-free.
 *
 * size (px) · color (defaults currentColor) · strokeWidth
 */
export function RegistrationMark({ size = 18, color = 'currentColor', strokeWidth = 1.5 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={strokeWidth} aria-hidden>
      <circle cx="12" cy="12" r="6" />
      <path d="M12 1v6M12 17v6M1 12h6M17 12h6" />
    </svg>
  );
}
