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
import { Deferred } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { Bell, Construction, Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import ForjaTriage, { type ForjaTicket } from './_components/ForjaTriage';

interface Meta {
  generated_at: string;
  onda: string;
}

interface Props {
  tab: string;
  tabLabel: string;
  subtitle: string;
  meta: Meta;
  // Triagem (aba 1) — chegam via Inertia::defer só na aba triagem. Opcionais
  // (undefined nas outras abas e no 1º paint da Triagem — default-guard).
  tickets?: ForjaTicket[];
  triagemCount?: number;
}

const COCKPIT_SUBTITLE =
  'Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente).';

// Abre a command palette global (dona do AppShellV2, atalho ⌘K) sintetizando o
// keydown que o shell escuta no window. Sem plumbing de estado novo no shell.
function openCommandPalette() {
  window.dispatchEvent(
    new KeyboardEvent('keydown', { key: 'k', metaKey: true, ctrlKey: true, bubbles: true }),
  );
}

function ForjaCockpit({ tab, tabLabel, subtitle, tickets, triagemCount }: Props) {
  const isTriagem = tab === 'triagem';
  const sinoBadge = isTriagem ? triagemCount : undefined;

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

      <section className="px-6 pt-4" data-testid={`forja-tab-${tab}`}>
        {isTriagem ? (
          // Aba Triagem REAL — tickets deferidos (skeleton até resolver).
          <Deferred
            data={['tickets', 'triagemCount']}
            fallback={
              <div className="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-dashed py-16 text-sm text-muted-foreground">
                Carregando propostas…
              </div>
            }
          >
            <ForjaTriage tickets={tickets} />
          </Deferred>
        ) : (
          // Demais abas: placeholder (cada uma vira 1 PR própria desta onda).
          <>
            <h2 className="text-sm font-semibold text-foreground">{tabLabel}</h2>
            <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>
            <div className="mt-4 inline-flex w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/30 px-6 py-12 text-center">
              <Construction size={22} className="text-muted-foreground" />
              <p className="text-sm font-medium text-foreground">Aba “{tabLabel}” em construção</p>
              <p className="max-w-md text-xs text-muted-foreground">
                O shell do cockpit (sidebar + 6 abas + rotas) está no ar. Cada aba entra como
                uma PR própria desta onda, com seu gate visual — referência aprovada na
                visual-comparison do cockpit Forja.
              </p>
            </div>
          </>
        )}
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
