/**
 * Avatar — initials on a deterministic color (DS v4 canon).
 * Same name → same color, always (hash → one of 8 palette tokens).
 * Pure, dependency-free (global React + inline token styles). No photos.
 *
 * size: 'sm' (24) | 'md' (32) | 'lg' (40)  ·  color auto-derived from `name`
 * (override with `c` = 1..8). Pass `initials` to override the derived ones.
 */
const AV_PALETTE = ['var(--av-c1)','var(--av-c2)','var(--av-c3)','var(--av-c4)','var(--av-c5)','var(--av-c6)','var(--av-c7)','var(--av-c8)'];

function avHash(s) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = ((h * 31 + s.charCodeAt(i)) >>> 0);
  return h;
}
function avInitials(name) {
  const p = (name || '').trim().split(/\s+/).filter(Boolean);
  if (p.length === 0) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[p.length - 1][0]).toUpperCase();
}

export function Avatar({ name = '', initials, c, size = 'sm', title, status }) {
  const dim = size === 'lg' ? 40 : size === 'md' ? 32 : 24;
  const fs = size === 'lg' ? 14 : size === 'md' ? 12 : 11;
  const idx = (c != null ? (c - 1) : (avHash(name) % 8) + 0) % 8;
  const bg = AV_PALETTE[((idx % 8) + 8) % 8];
  const av = (
    <span
      title={title ?? name ?? undefined}
      aria-label={name || undefined}
      style={{
        width: dim, height: dim, flexShrink: 0,
        display: 'inline-grid', placeItems: 'center',
        borderRadius: 'var(--radius-md, 6px)',
        background: bg, color: '#fff',
        fontSize: fs, fontWeight: 600, lineHeight: 1,
        fontFamily: 'var(--font-sans)', letterSpacing: '.01em',
        userSelect: 'none',
      }}
    >
      {initials || avInitials(name)}
    </span>
  );
  if (!status) return av;
  const SC = { online: 'oklch(0.70 0.14 150)', busy: 'var(--color-destructive)', away: 'oklch(0.80 0.13 75)', offline: 'var(--text-mute)' };
  const dotS = size === 'lg' ? 11 : size === 'md' ? 9 : 7;
  return (
    <span style={{ position: 'relative', display: 'inline-flex', flexShrink: 0 }}>
      {av}
      <span aria-hidden title={status} style={{ position: 'absolute', right: -1, bottom: -1, width: dotS, height: dotS, borderRadius: '50%', background: SC[status] || SC.offline, border: '2px solid var(--surface)' }} />
    </span>
  );
}
