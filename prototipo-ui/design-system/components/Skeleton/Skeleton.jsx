/**
 * Skeleton — shimmer placeholder while loading (DS v4).
 * Pure, dependency-free. Injects the @keyframes once on first render.
 * variant: 'text' | 'title' | 'caption' | 'avatar' | 'avatar-md' | 'row' | 'card'
 * width:   override width (e.g. '60%', 240). count: repeat N stacked.
 */
function ensureShimmer() {
  if (typeof document === 'undefined') return;
  if (document.getElementById('ds-skel-kf')) return;
  const s = document.createElement('style');
  s.id = 'ds-skel-kf';
  s.textContent = '@keyframes ds-shimmer{0%{background-position:-200px 0}100%{background-position:calc(200px + 100%) 0}}';
  document.head.appendChild(s);
}

export function Skeleton({ variant = 'text', width, count = 1 }) {
  ensureShimmer();
  const base = {
    display: 'block',
    backgroundColor: 'color-mix(in oklch, var(--text-mute) 55%, var(--bg-2))',
    backgroundImage: 'linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.45) 50%, transparent 100%)',
    backgroundSize: '200px 100%', backgroundRepeat: 'no-repeat',
    borderRadius: 'var(--radius-sm, 4px)',
    animation: 'ds-shimmer 1.4s linear infinite',
    color: 'transparent', userSelect: 'none',
  };
  const V = {
    text:       { height: 12, width: '80%' },
    title:      { height: 18, width: '60%' },
    caption:    { height: 10, width: '40%' },
    avatar:     { height: 24, width: 24, borderRadius: '50%', display: 'inline-block', flexShrink: 0 },
    'avatar-md':{ height: 32, width: 32, borderRadius: '50%', display: 'inline-block', flexShrink: 0 },
    row:        { height: 38, width: '100%', borderRadius: 6 },
    card:       { height: 80, width: '100%', borderRadius: 'var(--radius-md, 6px)' },
  };
  const style = { ...base, ...(V[variant] || V.text), ...(width != null ? { width } : {}) };
  if (count <= 1) return <span style={style} aria-hidden />;
  return (
    <span style={{ display: 'flex', flexDirection: 'column', gap: 6, width: '100%' }} aria-hidden>
      {Array.from({ length: count }).map((_, i) => <span key={i} style={style} />)}
    </span>
  );
}
