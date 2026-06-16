// @memcofre
//   tela: /forja (+ /forja/{backlog,quadro,changelog,mcp,saude})
//   module: TeamMcp — cockpit Forja (cowork loop). Onda Forja PR-A (shell).
//   forja: PR-A — shell navegável (sidebar + 6 abas + rotas). Abas reais entram
//          1 PR cada. visual-comparison aprovada:
//          memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md (F1.5 ADR 0114)
//   permissao: copiloto.mcp.usage.all
//
// Projeta estado que JÁ existe (mcp_tasks + git/PR/ADR/sessão + gates) — sem dado
// fantasma. As 6 rotas renderizam este shell com a aba ativa em `tab`; o topnav
// de 6 abas vem de config/core_topnavs.php['Forja'] (useAutoModuleNav).

import AppShellV2 from '@/Layouts/AppShellV2';
import { type ReactNode } from 'react';
import { Hammer, Construction } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';

interface Meta {
  generated_at: string;
  onda: string;
}

interface Props {
  tab: string;
  tabLabel: string;
  subtitle: string;
  meta: Meta;
}

const COCKPIT_SUBTITLE =
  'Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente).';

function ForjaCockpit({ tab, tabLabel, subtitle }: Props) {
  return (
    <>
      <PageHeader title="Forja" subtitle={COCKPIT_SUBTITLE} />

      <section className="mt-6" data-testid={`forja-tab-${tab}`}>
        <h2 className="inline-flex items-center gap-2 text-sm font-semibold text-foreground">
          <Hammer size={15} className="text-primary" /> {tabLabel}
        </h2>
        <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>

        {/* Onda Forja PR-A: shell de pé; cada aba real entra numa PR própria. */}
        <div className="mt-4 inline-flex w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/30 px-6 py-12 text-center">
          <Construction size={22} className="text-muted-foreground" />
          <p className="text-sm font-medium text-foreground">Aba “{tabLabel}” em construção</p>
          <p className="max-w-md text-xs text-muted-foreground">
            O shell do cockpit (sidebar + 6 abas + rotas) está no ar. Cada aba entra como
            uma PR própria desta onda, com seu gate visual — referência aprovada na
            visual-comparison do cockpit Forja.
          </p>
        </div>
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
