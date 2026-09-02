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

      {/* `changelog` é réplica do protótipo (Onda 9) e a view TRAZ o próprio padding
          (`.fj-changelog{padding:18px 32px 40px}`) — somar `px-6` daria 24+32=56px
          à esquerda contra os 32px do protótipo. O `integrador` tem a MESMA causa
          (`.fj-integra`, mesmo padding) e entra nesta lista na Onda 10, que é o PR
          dono do compare dele.
          A classe `fj-hub` aqui é ESCOPO DE TOKEN, não layout: a `<section>` é IRMÃ
          do `<ForjaHub>`, e o bundle escopa a família `--accent*` do dark a
          `[data-theme="dark"] .fj-hub, .fj-page` (Onda 2.1) — sem ela os chips
          herdariam o `--accent` 0,55 da fundação em vez do 0,70 do protótipo. Vem
          junto `.fj-hub button{line-height:normal}` (linha 1226), que é o mesmo fix
          que a Onda 2.1 mediu no botão do topnav (de 28px para 25px, por causa do preflight
          do Tailwind) e de que os `<button>` dos chips precisam. Medido: `.fj-hub`
          NÃO tem regra própria — as 20 regras dela são todas de descendente, então
          a classe não traz layout. `.fj-page` foi descartada aqui porque tem
          `height:100%; overflow:hidden` e clipa o feed (medido em harness). */}
      <section
        className={tab === 'changelog' ? 'fj-hub' : 'px-6 pt-4'}
        data-testid={`forja-tab-${tab}`}
      >
        {/* Intro da aba (texto-âncora). Triagem renderiza o seu próprio; MCP tem
            banner; Changelog e Integrador não têm parágrafo no protótipo. */}
        {tab !== 'triagem' && tab !== 'mcp' && tab !== 'integrador' && tab !== 'changelog' && (
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
          // O fallback herda o padding da view (a `<section>` não põe nenhum aqui),
          // senão o skeleton sai colado na borda enquanto o defer não resolve.
          <Deferred data={['changelog']} fallback={<div className="fj-changelog">{loading('changelog')}</div>}>
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
