// FxShell.tsx — wrapper das 7 páginas do módulo Fiscal
// Port do design fiscal-page.jsx §6 FxShell (sub-nav horizontal + footer cheats)
// ⌘K palette + atalhos 1-7 são placeholders no PR #1 (entrega completa em PR #3).

import { router } from '@inertiajs/react';
import { Archive, FileText, Receipt, RefreshCw, Search, Shield, ShieldAlert } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';

interface FxPage {
  id: string;
  label: string;
  icon: ReactNode;
  short: string;
  url: string;
}

// 7 sub-páginas do Fiscal — PR #1 só implementa "nfe" (segunda).
// Restantes apontam pra "#" e ficam disabled visualmente até serem entregues.
const FX_PAGES: FxPage[] = [
  { id: 'fiscal',          label: 'Cockpit',        icon: <ShieldAlert size={13}/>, short: '1', url: '/fiscal' },
  { id: 'nfe',             label: 'NF-e · NFC-e',   icon: <Receipt size={13}/>,    short: '2', url: '/fiscal/nfe' },
  { id: 'nfse',            label: 'NFS-e',          icon: <FileText size={13}/>,   short: '3', url: '/fiscal/nfse' },
  { id: 'dfe',             label: 'Manifesto DF-e', icon: <ShieldAlert size={13}/>,short: '4', url: '/fiscal/dfe' },
  { id: 'fiscal_eventos',  label: 'Eventos',        icon: <RefreshCw size={13}/>,  short: '5', url: '/fiscal/eventos' },
  { id: 'fiscal_config',   label: 'Certif. & Cfg.', icon: <Shield size={13}/>,     short: '6', url: '/fiscal/config' },
  { id: 'sped',            label: 'SPED & Livros',  icon: <Archive size={13}/>,    short: '7', url: '/fiscal/sped' },
];

interface FxShellProps {
  route: string;
  title: string;
  crumb?: string;
  env?: string;
  envTone?: 'ok' | 'warn' | 'bad';
  actions?: ReactNode;
  cheats?: Array<{ keys: string[]; label: string }>;
  counts?: Partial<Record<string, number | null>>;
  children: ReactNode;
}

const DEFAULT_CHEATS = [
  { keys: ['⌘', 'K'], label: 'buscar tudo (em breve)' },
  { keys: ['2'],      label: 'NF-e' },
  { keys: ['J', 'K'], label: 'navegar lista' },
  { keys: ['?'],      label: 'todos os atalhos' },
];

export default function FxShell({
  route,
  title,
  crumb,
  env,
  envTone = 'ok',
  actions,
  cheats = DEFAULT_CHEATS,
  counts = {},
  children,
}: FxShellProps) {
  // Atalhos 1-7 pra navegar entre sub-páginas (placeholder pra # → noop)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const isTyping =
        target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
      if (isTyping || e.metaKey || e.ctrlKey || e.altKey) return;

      const page = FX_PAGES.find(p => p.short === e.key);
      if (page && page.url !== '#') {
        e.preventDefault();
        router.visit(page.url, { preserveScroll: false });
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  return (
    <div className="fx-page" data-screen-label={`00 ${route}`}>
      <header className="fx-hero">
        <div className="fx-hero-l">
          <h1>{title}</h1>
          {crumb && <span className="fx-hero-crumb">{crumb}</span>}
        </div>
        <div className="fx-hero-r">
          {env && <span className={`fx-env ${envTone}`}>{env}</span>}
          <button className="fx-btn ghost fx-cmdk-btn" disabled title="Em PR #3">
            <Search size={13}/>
            <span>Buscar</span>
            <kbd>⌘K</kbd>
          </button>
          {actions}
        </div>
      </header>

      <nav className="fx-subnav" aria-label="Páginas do módulo Fiscal">
        {FX_PAGES.map(p => {
          const active = route === p.id;
          const disabled = p.url === '#';
          const n = counts[p.id];
          return (
            <button
              key={p.id}
              type="button"
              className={`fx-subnav-chip${active ? ' active' : ''}${disabled ? ' disabled' : ''}`}
              onClick={() => !disabled && router.visit(p.url)}
              disabled={disabled}
              title={disabled ? 'Em PR seguinte' : `Atalho: ${p.short}`}
            >
              {p.icon}
              <span>{p.label}</span>
              {n != null && n > 0 && <span className="n">{n}</span>}
              <kbd>{p.short}</kbd>
            </button>
          );
        })}
      </nav>

      <div className="fx-body">{children}</div>

      <footer className="fx-shell-foot">
        <div className="fx-cheatsheet" role="region" aria-label="Atalhos de teclado">
          {cheats.map((it, i) => (
            <span key={i} className="fx-cs-item">
              {it.keys.map((k, j) => <kbd key={j}>{k}</kbd>)}
              <span>{it.label}</span>
            </span>
          ))}
        </div>
      </footer>
    </div>
  );
}
