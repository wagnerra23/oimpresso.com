// ForjaHub — header ÚNICO do hub Forja, usado por TODAS as abas (cockpit /forja/*
// + telas absorvidas /team-mcp/*) pra que o header seja IDÊNTICO em tudo:
// título "Forja" + ações (sino/⌘K/Novo issue) + tab-strip de 9 abas.
// SEM eyebrow (removido a pedido do Wagner 2026-06-16).
//
// Fonte única da tab-strip — antes vivia inline no Cockpit; extraída pra
// reuso nas telas TeamMcp absorvidas (Equipe/Tarefas/CC Sessions/Saúde).

import { Link } from '@inertiajs/react';
import { Activity, Bell, Code2, Columns3, History, Inbox, LayoutGrid, List, Plug, Search, Users } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import { cn } from '@/Lib/utils';

const COCKPIT_SUBTITLE =
  'Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente).';

export const FORJA_TABS = [
  { key: 'triagem',   label: 'Triagem',     href: '/forja',                icon: Inbox },
  { key: 'backlog',   label: 'Backlog',     href: '/forja/backlog',        icon: List },
  { key: 'quadro',    label: 'Quadro',      href: '/forja/quadro',         icon: LayoutGrid },
  { key: 'changelog', label: 'Changelog',   href: '/forja/changelog',      icon: History },
  { key: 'mcp',       label: 'MCP',         href: '/forja/mcp',            icon: Plug },
  // Telas TeamMcp absorvidas (fusão) — reusam as canônicas ricas.
  { key: 'tarefas',   label: 'Tarefas',     href: '/team-mcp/tasks',       icon: Columns3 },
  { key: 'equipe',    label: 'Equipe',      href: '/team-mcp/team',        icon: Users },
  { key: 'cc',        label: 'CC Sessions', href: '/team-mcp/cc-sessions', icon: Code2 },
  { key: 'saude',     label: 'Saúde',       href: '/team-mcp/scorecard',   icon: Activity },
] as const;

// Abre a command palette global (dona do AppShellV2, atalho ⌘K) sintetizando o
// keydown que o shell escuta no window.
function openCommandPalette() {
  window.dispatchEvent(
    new KeyboardEvent('keydown', { key: 'k', metaKey: true, ctrlKey: true, bubbles: true }),
  );
}

export default function ForjaHub({ active, triagemCount }: { active: string; triagemCount?: number }) {
  const sinoBadge = active === 'triagem' ? triagemCount : undefined;

  return (
    <>
      <PageHeader
        title="Forja"
        subtitle={COCKPIT_SUBTITLE}
        actions={
          <>
            <button
              type="button"
              aria-label="Notificações"
              title="Notificações"
              className="relative inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
              data-testid="forja-sino"
            >
              <Bell size={16} />
              {sinoBadge != null && sinoBadge > 0 && (
                <span className="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-semibold tabular-nums text-primary-foreground">
                  {sinoBadge}
                </span>
              )}
            </button>

            <button
              type="button"
              onClick={openCommandPalette}
              aria-label="Buscar (⌘K)"
              title="Buscar (⌘K)"
              className="inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
              data-testid="forja-busca"
            >
              <Search size={14} />
              <kbd className="rounded bg-muted px-1 py-0.5 font-mono text-[10px]">⌘K</kbd>
            </button>

            <PageHeaderPrimary label="Novo issue" href="/forja" data-testid="forja-novo-issue" />
          </>
        }
      />

      {/* Tab-strip de 9 abas — idêntica em todas as telas do hub.
          shrink-0: o slot do AppShellV2 é flex-column de altura limitada; sem isto
          o flex-shrink esmaga o nav (que tem overflow-x-auto) a ~3px nas telas
          longas absorvidas (/team-mcp/*). Mantém o tab-strip sempre em altura cheia. */}
      <nav className="mt-2 inline-flex w-full shrink-0 items-center gap-1 overflow-x-auto border-b px-6" data-testid="forja-tabs">
        {FORJA_TABS.map((t) => {
          const isActive = t.key === active;
          const Icon = t.icon;
          const badge = t.key === 'triagem' ? triagemCount : undefined;
          return (
            <Link
              key={t.key}
              href={t.href}
              aria-current={isActive ? 'page' : undefined}
              className={cn(
                '-mb-px inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-xs font-medium transition-colors',
                isActive
                  ? 'border-primary text-primary'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
            >
              <Icon size={14} />
              {t.label}
              {badge != null && badge > 0 && (
                <span className="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-semibold tabular-nums text-primary-foreground">
                  {badge}
                </span>
              )}
            </Link>
          );
        })}
      </nav>
    </>
  );
}
