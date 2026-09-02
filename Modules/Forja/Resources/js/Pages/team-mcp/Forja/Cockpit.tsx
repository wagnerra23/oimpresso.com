// @memcofre
//   tela: /forja (+ /forja/{backlog,quadro,changelog,mcp})
//   module: TeamMcp — cockpit Forja (cowork loop · hub ÚNICO pós-fusão).
//   forja: header vem do <ForjaHub> compartilhado (mesmo header em TODAS as abas,
//          inclusive nas telas TeamMcp absorvidas). Eyebrow removido (Wagner 2026-06-16).
//          Abas: Triagem/Backlog/Quadro/Changelog/MCP (próprias) + Tarefas/Equipe/
//          CC Sessions/Saúde (telas ricas /team-mcp/* reusadas).
//   permissao: copiloto.mcp.usage.all
//
// Projeta estado que JÁ existe (mcp_tasks + git/PR/ADR/sessão + gates) — sem dado fantasma.

import AppShellV2 from '@/Layouts/AppShellV2';
// Bundle CSS do prototipo (forja-page.css, copia integral — PARIDADE §11 Onda 1). As classes
// fj-/ap-/tf- entram aqui para TODAS as rotas do Cockpit; as views passam a usa-las onda a onda.
import '../../../../../../../resources/css/cowork-forja-bundle.css';
import { Deferred } from '@inertiajs/react';
import { type ReactNode } from 'react';
import ForjaHub from './_components/ForjaHub';
import ForjaTriage, { type ForjaTicket } from './_components/ForjaTriage';
import ForjaBacklog, { type BacklogTask } from './_components/ForjaBacklog';
import ForjaQuadro, { type QuadroData } from './_components/ForjaQuadro';
import ForjaChangelog, { type ChangelogEntry } from './_components/ForjaChangelog';
import ForjaMcp from './_components/ForjaMcp';
// Handoffs virou tela própria (/forja/handoffs) em 2026-08-08 — dado vivo não
// mora dentro da vitrine mockada do contrato. Os tipos foram junto.
import ForjaHandoffs, { type HandoffItem, type HeartbeatInfo } from './_components/ForjaHandoffs';
// Integrador — view `integra` do protótipo, nasce na Onda 2 (PARIDADE §11): estática por construção.
import ForjaIntegrador from './_components/ForjaIntegrador';

interface Meta {
  generated_at: string;
  onda: string;
}

interface Props {
  tab: string;
  tabLabel: string;
  subtitle: string;
  meta: Meta;
  tickets?: ForjaTicket[];
  triagemCount?: number;
  backlog?: BacklogTask[];
  quadro?: QuadroData;
  changelog?: ChangelogEntry[];
  handoffs?: HandoffItem[];
  heartbeat?: HeartbeatInfo;
}

function ForjaCockpit({
  tab,
  subtitle,
  tickets,
  triagemCount,
  backlog,
  quadro,
  changelog,
  handoffs,
  heartbeat,
}: Props) {
  const loading = (txt: string) => (
    <div className="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-dashed py-16 text-sm text-muted-foreground">
      Carregando {txt}…
    </div>
  );

  return (
    <>
      <ForjaHub active={tab} triagemCount={triagemCount} />

      <section className="px-6 pt-4" data-testid={`forja-tab-${tab}`}>
        {/* Intro da aba (texto-âncora). Triagem renderiza o seu próprio; MCP tem banner. */}
        {tab !== 'triagem' && tab !== 'mcp' && tab !== 'integrador' && (
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
        {tab === 'handoffs' && <ForjaHandoffs handoffs={handoffs} heartbeat={heartbeat} />}
        {tab === 'integrador' && <ForjaIntegrador />}
      </section>
    </>
  );
}

ForjaCockpit.layout = (page: ReactNode) => (
  <AppShellV2 title="Forja — cockpit do cowork loop" breadcrumbItems={[{ label: 'Forja' }]}>
    {page}
  </AppShellV2>
);

export default ForjaCockpit;
