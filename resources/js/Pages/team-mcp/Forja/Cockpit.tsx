// @memcofre
//   tela: /forja (+ /forja/{backlog,quadro,changelog,mcp,saude})
//   module: TeamMcp — cockpit Forja (cowork loop).
//   forja: PR-A shell + Triagem REAL (esta PR). A aba Triagem projeta mcp_tasks
//          project=FORJA em estado de triagem + dossiê lateral (reusa o padrão
//          Analista de ProjectMgmt apontando pros endpoints /forja/*). As outras
//          5 abas (backlog/quadro/changelog/mcp/saude) seguem placeholder (1 PR cada).
//          visual-comparison aprovada (F1.5 ADR 0114):
//          memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
//   permissao: copiloto.mcp.usage.all
//
// Projeta estado que JÁ existe (mcp_tasks + git/PR/ADR/sessão + gates) — sem dado
// fantasma. As 6 rotas renderizam este shell com a aba ativa em `tab`; o topnav
// de 6 abas vem de config/core_topnavs.php['Forja'] (useAutoModuleNav).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { Activity, Bell, Code2, Columns3, History, Inbox, LayoutGrid, List, Plug, Search, Users } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import { cn } from '@/Lib/utils';
import ForjaTriage, { type ForjaTicket } from './_components/ForjaTriage';
import ForjaBacklog, { type BacklogTask } from './_components/ForjaBacklog';
import ForjaQuadro, { type QuadroData } from './_components/ForjaQuadro';
import ForjaChangelog, { type ChangelogEntry } from './_components/ForjaChangelog';
import ForjaMcp from './_components/ForjaMcp';

interface Meta {
  generated_at: string;
  onda: string;
}

interface Props {
  tab: string;
  tabLabel: string;
  subtitle: string;
  meta: Meta;
  // Props por aba — chegam via Inertia::defer só na aba ativa. Opcionais
  // (undefined nas outras abas e no 1º paint — default-guard nos componentes).
  tickets?: ForjaTicket[];
  triagemCount?: number;
  backlog?: BacklogTask[];
  quadro?: QuadroData;
  changelog?: ChangelogEntry[];
}

const COCKPIT_SUBTITLE =
  'Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente).';

// Tab-strip in-page das 6 abas (contrato pixel). O module-nav nativo do AppShellV2
// é dropdown no breadcrumb — não bate com a barra de abas do protótipo —, então a
// faixa é renderizada aqui. Active por `tab`; Triagem mostra o contador vivo.
const FORJA_TABS = [
  { key: 'triagem',   label: 'Triagem',     href: '/forja',                icon: Inbox },
  { key: 'backlog',   label: 'Backlog',     href: '/forja/backlog',        icon: List },
  { key: 'quadro',    label: 'Quadro',      href: '/forja/quadro',         icon: LayoutGrid },
  { key: 'changelog', label: 'Changelog',   href: '/forja/changelog',      icon: History },
  { key: 'mcp',       label: 'MCP',         href: '/forja/mcp',            icon: Plug },
  // Telas TeamMcp absorvidas (fusão 2026-06-16) — reusam as canônicas ricas.
  { key: 'tarefas',   label: 'Tarefas',     href: '/team-mcp/tasks',       icon: Columns3 },
  { key: 'equipe',    label: 'Equipe',      href: '/team-mcp/team',        icon: Users },
  { key: 'cc',        label: 'CC Sessions', href: '/team-mcp/cc-sessions', icon: Code2 },
  { key: 'saude',     label: 'Saúde',       href: '/team-mcp/scorecard',   icon: Activity },
] as const;

// Abre a command palette global (dona do AppShellV2, atalho ⌘K) sintetizando o
// keydown que o shell escuta no window. Sem plumbing de estado novo no shell.
function openCommandPalette() {
  window.dispatchEvent(
    new KeyboardEvent('keydown', { key: 'k', metaKey: true, ctrlKey: true, bubbles: true }),
  );
}

function ForjaCockpit({ tab, subtitle, tickets, triagemCount, backlog, quadro, changelog }: Props) {
  const sinoBadge = tab === 'triagem' ? triagemCount : undefined;

  const loading = (txt: string) => (
    <div className="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-dashed py-16 text-sm text-muted-foreground">
      Carregando {txt}…
    </div>
  );

  return (
    <>
      {/* Eyebrow / breadcrumb (contrato pixel) */}
      <p className="px-6 pt-4 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
        Desenvolvimento · MCP · Projeção do git
      </p>

      <PageHeader
        title="Forja"
        subtitle={COCKPIT_SUBTITLE}
        actions={
          <>
            {/* Sino com badge contador */}
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

            {/* Busca ⌘K — trigger do command palette global */}
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

            {/* Primária roxa "+ Novo issue" */}
            <PageHeaderPrimary label="Novo issue" href="/forja" data-testid="forja-novo-issue" />
          </>
        }
      />

      {/* Faixa de 6 abas in-page (Triagem ativa + badge do contador vivo). */}
      <nav className="mt-2 inline-flex w-full items-center gap-1 overflow-x-auto border-b px-6" data-testid="forja-tabs">
        {FORJA_TABS.map((t) => {
          const active = t.key === tab;
          const Icon = t.icon;
          const badge = t.key === 'triagem' ? triagemCount : undefined;
          return (
            <Link
              key={t.key}
              href={t.href}
              aria-current={active ? 'page' : undefined}
              className={cn(
                '-mb-px inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-xs font-medium transition-colors',
                active
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

      <section className="px-6 pt-4" data-testid={`forja-tab-${tab}`}>
        {/* Intro da aba (texto-âncora). Triagem renderiza o seu próprio; MCP tem banner. */}
        {tab !== 'triagem' && tab !== 'mcp' && (
          <p className="text-xs leading-relaxed text-muted-foreground">{subtitle}</p>
        )}

        {tab === 'triagem' && (
          <Deferred data={['tickets', 'triagemCount']} fallback={loading('propostas')}>
            <ForjaTriage tickets={tickets} />
          </Deferred>
        )}
        {tab === 'backlog' && (
          <Deferred data={['backlog']} fallback={loading('backlog')}>
            <ForjaBacklog backlog={backlog} />
          </Deferred>
        )}
        {tab === 'quadro' && (
          <Deferred data={['quadro']} fallback={loading('quadro')}>
            <ForjaQuadro quadro={quadro} />
          </Deferred>
        )}
        {tab === 'changelog' && (
          <Deferred data={['changelog']} fallback={loading('changelog')}>
            <ForjaChangelog changelog={changelog} />
          </Deferred>
        )}
        {tab === 'mcp' && <ForjaMcp />}
      </section>
    </>
  );
}

ForjaCockpit.layout = (page: ReactNode) => (
  <AppShellV2
    title="Forja — cockpit do cowork loop"
    breadcrumbItems={[{ label: 'Desenvolvimento' }, { label: 'Forja' }]}
  >
    {page}
  </AppShellV2>
);

export default ForjaCockpit;
