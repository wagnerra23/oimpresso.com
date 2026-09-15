// Icon — minimal Lucide-style inline icon set (1.5 stroke, 24 grid)
const ICONS = {
  'home': <path d="M3 12 12 3l9 9M5 10v10h14V10"/>,
  'clock': <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
  'calendar-clock': <><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h12"/><circle cx="17" cy="17" r="3"/><path d="M17 15.5V17l1 1"/></>,
  'dollar-sign': <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>,
  'package': <><path d="m21 8-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></>,
  'shopping-cart': <><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.5 12h11l2-8H6"/></>,
  'bar-chart-3': <><path d="M3 3v18h18"/><path d="M7 13v5M12 8v10M17 4v14"/></>,
  'users': <><circle cx="9" cy="8" r="3.5"/><path d="M2 21c0-3 3-5.5 7-5.5S16 18 16 21"/><path d="M16 4a3.5 3.5 0 0 1 0 7M22 21c0-2.5-2-4.5-5-5"/></>,
  'lock': <><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></>,
  'file-text': <><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/></>,
  'inbox': <><path d="M3 13h5l1 3h6l1-3h5"/><path d="M5 4h14l3 9v5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5z"/></>,
  'log-in': <><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/></>,
  'log-out': <><path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></>,
  'coffee': <><path d="M3 8h14v6a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5z"/><path d="M17 9h2a3 3 0 1 1 0 6h-2"/><path d="M7 4v2M11 4v2M15 4v2"/></>,
  'chevron-down': <path d="m6 9 6 6 6-6"/>,
  'chevron-right': <path d="m9 6 6 6-6 6"/>,
  'arrow-up-right': <><path d="M7 17 17 7"/><path d="M8 7h9v9"/></>,
  'arrow-down-right': <><path d="M7 7l10 10"/><path d="M17 8v9H8"/></>,
  'plus': <path d="M12 5v14M5 12h14"/>,
  'search': <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>,
  'bell': <><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9z"/><path d="M10 21a2 2 0 0 0 4 0"/></>,
  'check': <path d="M5 12l5 5 9-9"/>,
  'menu': <path d="M3 6h18M3 12h18M3 18h18"/>,
  'user-circle': <><circle cx="12" cy="12" r="9"/><circle cx="12" cy="10" r="3"/><path d="M6 19a6 6 0 0 1 12 0"/></>,
};

function Icon({ name, size = 16, className = '' }) {
  const path = ICONS[name];
  if (!path) return <span style={{display:'inline-block', width: size, height: size}}/>;
  return (
    <svg className={className} width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      {path}
    </svg>
  );
}
window.Icon = Icon;
