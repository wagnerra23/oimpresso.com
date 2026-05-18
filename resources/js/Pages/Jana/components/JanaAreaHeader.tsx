// JanaAreaHeader — header sticky compartilhado entre Chat.tsx e Dashboard.tsx.
//
// Espelha `prototipo-ui/_cowork-export-2026-05-15/app.jsx` Header function
// (linhas 247-336 do protótipo Cockpit) — Wagner aprovou em PR #295 (ADR 0114).
// Adaptações vs protótipo (documentadas em Chat-header-tabs-visual-comparison.md):
//
// - Tabs viram Inertia <Link> (não <button>) → navegação canônica entre rotas
// - Emojis 📊 🤖 → ícones lucide (LayoutDashboard + MessageSquare)
//   (Charter §UX Anti-patterns: "Avatar circular emoji-style")
// - Active state via data-active attribute + Tailwind utilities (não inline style)
// - Sticky `top-0 z-10 backdrop-blur` (Charter §UX Targets — referência visual
//   durante scroll thread)
// - Search/bell buttons à direita: omitidos (Charter §UX Anti-patterns + dupes
//   com Tarefas shortcut + composer `/` shortcut existentes)
//
// Refs:
// - Chat.charter.md (charter_version: 2)
// - Dashboard.charter.md (charter_version: 2)
// - memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md (gate F1.5)
// - PR #1053 (Fase 1+2 sidebar reordenada)

import { Link } from '@inertiajs/react';
import { LayoutDashboard, MessageSquare } from 'lucide-react';
import type { ReactNode } from 'react';

const TABS = [
  { key: 'dashboard', href: '/jana/dashboard', label: 'Dashboard', Icon: LayoutDashboard },
  { key: 'chat',      href: '/jana',           label: 'Chat',      Icon: MessageSquare   },
] as const;

export type JanaAreaTab = (typeof TABS)[number]['key'];

export function JanaAreaHeader({ active }: { active: JanaAreaTab }): ReactNode {
  return (
    <header
      className="sticky top-0 z-10 flex items-center gap-4 border-b border-border bg-card/95 px-4 py-2 backdrop-blur"
      aria-label="Área Jana"
    >
      {/* Left — area dot + label (hue 220 = SIDEBAR_GROUP_HUE.ia) */}
      <div className="flex shrink-0 items-center gap-2">
        <span
          aria-hidden
          className="inline-block size-2 rounded-full"
          style={{ background: 'oklch(0.62 0.13 220)' }}
        />
        <span className="text-[13px] font-semibold uppercase tracking-wide text-foreground/80">
          JANA
        </span>
      </div>

      {/* Center — tabs (Inertia navigation) */}
      <nav className="flex flex-1 items-center gap-1" aria-label="Modo do Jana">
        {TABS.map((t) => {
          const isActive = active === t.key;
          return (
            <Link
              key={t.key}
              href={t.href}
              data-active={isActive}
              aria-current={isActive ? 'page' : undefined}
              className={
                'inline-flex items-center gap-1.5 border-b-2 px-3 py-1.5 text-[13px] font-medium transition-colors ' +
                (isActive
                  ? 'border-primary text-primary'
                  : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground')
              }
            >
              <t.Icon size={13} aria-hidden />
              <span>{t.label}</span>
            </Link>
          );
        })}
      </nav>

      {/* Right — placeholder (search/bell omitidos conforme Charter Non-Goals) */}
      <div className="shrink-0" aria-hidden />
    </header>
  );
}
