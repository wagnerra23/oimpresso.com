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
// Bundle CSS do protótipo: importado pelo <ForjaHub> (Onda 2), que é o componente que TODAS as
// Pages do hub renderizam — importar aqui deixava Aprovações/Trabalho/Gantt (chunks separados)
// sem o CSS: medido no .snap de 2026-09-02, header empilhado como texto cru.
import { Deferred } from '@inertiajs/react';
import { type ReactNode } from 'react';
import ForjaHub from './_components/ForjaHub';
import ForjaTriage, { type ForjaTicket } from './_components/ForjaTriage';
import ForjaBacklog, { type BacklogTask } from './_components/ForjaBacklog';
import ForjaQuadro, { type QuadroData } from './_components/ForjaQuadro';
import ForjaChangelog, { type ChangelogEntry } from './_components/ForjaChangelog';
import ForjaMcp from './_components/ForjaMcp';
// Handoffs: seção da aba MCP (PARIDADE §11 Onda 8) E tela própria em /forja/handoffs.
// O MESMO componente nos dois pontos — uma projeção (ForjaMcpService), zero duplicação.
// A tela própria nasceu em 2026-08-08 e continua viva; o protótipo põe o painel dentro
// da view `mcp`, e é o protótipo que manda no layout (ADR 0388).
import ForjaHandoffs, {
  HandoffsSkeleton,
  type HandoffItem,
  type HeartbeatInfo,
} from './_components/ForjaHandoffs';
// Integrador — view `integra` do protótipo, nasce na Onda 2 (PARIDADE §11): estática por construção.
import ForjaIntegrador from './_components/ForjaIntegrador';
// Saúde — view `saude` do protótipo, nasce na Onda 7 (PARIDADE §11): dado real (scorecard + tasks).
import ForjaSaude, { type SaudeData } from './_components/ForjaSaude';

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
  saude?: SaudeData;
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
  saude,
}: Props) {
  const loading = (txt: string) => (
    <div className="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-dashed py-16 text-sm text-muted-foreground">
      Carregando {txt}…
    </div>
  );

  return (
    <>
      <ForjaHub active={tab} triagemCount={triagemCount} />

      {/* Abas-réplica trazem o próprio padding do protótipo no root (`.fj-mcp` =
          `18px 32px 40px`, idêntico a `.fj-integra` e `.fj-saude`); somar o `px-6 pt-4`
          do wrapper daria 56px de recuo onde o protótipo tem 32px.
          Só `mcp` está na condicional: `saude` (Onda 7) e `integrador` (Onda 2) têm o
          mesmo padding próprio no bundle e receberiam o mesmo tratamento, mas tirá-los
          do wrapper agora mudaria o layout de telas de OUTRAS ondas — já medidas e
          mergeadas — sem medição nova. Cada uma entra na sua onda de compare. */}
      <section className={tab === 'mcp' ? '' : 'px-6 pt-4'} data-testid={`forja-tab-${tab}`}>
        {/* Intro da aba (texto-âncora). Triagem renderiza o seu próprio; MCP tem banner.
            Saúde entrou nesta lista na Onda 7: a view do protótipo abre com o seu próprio
            `fj-mcp-intro`, e manter o parágrafo genérico daria DOIS textos de abertura. */}
        {tab !== 'triagem' && tab !== 'mcp' && tab !== 'integrador' && tab !== 'saude' && (
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
        {/* MCP: a vitrine é estática e aparece na hora; só o painel de handoffs (dado
            vivo) espera o defer — o <Deferred> mora dentro do ForjaMcp, com skeleton. */}
        {tab === 'mcp' && <ForjaMcp handoffs={handoffs} heartbeat={heartbeat} />}
        {tab === 'handoffs' && (
          <Deferred data={['handoffs', 'heartbeat']} fallback={<HandoffsSkeleton />}>
            <ForjaHandoffs handoffs={handoffs} heartbeat={heartbeat} />
          </Deferred>
        )}
        {tab === 'integrador' && <ForjaIntegrador />}
        {tab === 'saude' && (
          <Deferred data={['saude']} fallback={loading('saúde')}>
            <ForjaSaude saude={saude} />
          </Deferred>
        )}
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
