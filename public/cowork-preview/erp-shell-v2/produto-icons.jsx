// Icon set for Produto module — stroke 1.75, lucide-style.
const PIcon = ({ children, size = 16, className = "", strokeWidth = 1.75 }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth={strokeWidth}
    strokeLinecap="round"
    strokeLinejoin="round"
    className={className}
    aria-hidden="true"
  >
    {children}
  </svg>
);

const PI = {
  Search: (p) => <PIcon {...p}><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></PIcon>,
  Plus: (p) => <PIcon {...p}><path d="M12 5v14M5 12h14" /></PIcon>,
  Filter: (p) => <PIcon {...p}><path d="M3 6h18M6 12h12M10 18h4" /></PIcon>,
  Download: (p) => <PIcon {...p}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M7 10l5 5 5-5" /><path d="M12 15V3" /></PIcon>,
  Upload: (p) => <PIcon {...p}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M17 8l-5-5-5 5" /><path d="M12 3v12" /></PIcon>,
  Check: (p) => <PIcon {...p}><path d="M20 6 9 17l-5-5" /></PIcon>,
  X: (p) => <PIcon {...p}><path d="M18 6 6 18M6 6l12 12" /></PIcon>,
  ChevronDown: (p) => <PIcon {...p}><path d="m6 9 6 6 6-6" /></PIcon>,
  ChevronRight: (p) => <PIcon {...p}><path d="m9 6 6 6-6 6" /></PIcon>,
  ChevronLeft: (p) => <PIcon {...p}><path d="m15 18-6-6 6-6" /></PIcon>,
  More: (p) => <PIcon {...p}><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /><circle cx="5" cy="12" r="1" /></PIcon>,
  ArrowUp: (p) => <PIcon {...p}><path d="M12 19V5M5 12l7-7 7 7" /></PIcon>,
  ArrowDown: (p) => <PIcon {...p}><path d="M12 5v14M5 12l7 7 7-7" /></PIcon>,
  TrendUp: (p) => <PIcon {...p}><path d="m22 7-8.5 8.5-5-5L2 17" /><path d="M16 7h6v6" /></PIcon>,
  Tag: (p) => <PIcon {...p}><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z" /><circle cx="7.5" cy="7.5" r=".5" fill="currentColor" /></PIcon>,
  Box: (p) => <PIcon {...p}><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" /><path d="m3.3 7 8.7 5 8.7-5M12 22V12" /></PIcon>,
  Layers: (p) => <PIcon {...p}><path d="m12 2 10 6-10 6L2 8z" /><path d="m2 14 10 6 10-6" /><path d="m2 11 10 6 10-6" /></PIcon>,
  Clock: (p) => <PIcon {...p}><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></PIcon>,
  Pencil: (p) => <PIcon {...p}><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z" /></PIcon>,
  Copy: (p) => <PIcon {...p}><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></PIcon>,
  Trash: (p) => <PIcon {...p}><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></PIcon>,
  LayoutDashboard: (p) => <PIcon {...p}><rect x="3" y="3" width="7" height="9" rx="1" /><rect x="14" y="3" width="7" height="5" rx="1" /><rect x="14" y="12" width="7" height="9" rx="1" /><rect x="3" y="16" width="7" height="5" rx="1" /></PIcon>,
  ShoppingBag: (p) => <PIcon {...p}><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" /><path d="M3 6h18M16 10a4 4 0 0 1-8 0" /></PIcon>,
  Wrench: (p) => <PIcon {...p}><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6.3 6.3a1 1 0 0 0 0 1.4l1.4 1.4a1 1 0 0 0 1.4 0l6.3-6.3a4 4 0 0 0 5.4-5.4l-3 3-2-2 3-3a4 4 0 0 0-1.4 0z" /></PIcon>,
  Wallet: (p) => <PIcon {...p}><path d="M19 7H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2" /><path d="M3 9V6a2 2 0 0 1 2-2h12v5" /><path d="M19 13h.01" /></PIcon>,
  Users: (p) => <PIcon {...p}><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></PIcon>,
  FileText: (p) => <PIcon {...p}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" /></PIcon>,
  Settings: (p) => <PIcon {...p}><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></PIcon>,
  Bell: (p) => <PIcon {...p}><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></PIcon>,
  Grid: (p) => <PIcon {...p}><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></PIcon>,
  List: (p) => <PIcon {...p}><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></PIcon>,
  Image: (p) => <PIcon {...p}><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-5-5L5 21" /></PIcon>,
  Star: (p) => <PIcon {...p}><path d="m12 2 3.1 6.3 7 1-5 4.9 1.2 6.9L12 17.8 5.7 21l1.2-6.9-5-4.9 7-1z" /></PIcon>,
  Flame: (p) => <PIcon {...p}><path d="M8.5 14.5A2.5 2.5 0 0 0 11 17c1.5 0 3-1.5 3-3.5 0-1-.5-2-1-3-1-2 .5-4 .5-4 .5 2 2 2.5 3.5 4.5 1 1.3 1.5 3 1.5 4.5a7 7 0 1 1-14 0c0-1.5.5-3 1-4 .5-1 1-3 .5-4 0 0 .5 1.5 2 2 1 .5 2 1.5 2 3 0 1-.5 2-1 3z" /></PIcon>,
  Beaker: (p) => <PIcon {...p}><path d="M4.5 3h15M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3" /><path d="M6 14h12" /></PIcon>,
  Refresh: (p) => <PIcon {...p}><path d="M21 12a9 9 0 0 1-15 6.7L3 16M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 3v5h-5M3 21v-5h5" /></PIcon>,
  ArrowRight: (p) => <PIcon {...p}><path d="M5 12h14M13 5l7 7-7 7" /></PIcon>,
  Zap: (p) => <PIcon {...p}><path d="M13 2 3 14h9l-1 8 10-12h-9z" /></PIcon>,
};

window.PI = PI;
