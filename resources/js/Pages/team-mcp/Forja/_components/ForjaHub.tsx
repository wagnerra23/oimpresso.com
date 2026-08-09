// ForjaHub — header ÚNICO do hub Forja, usado por TODAS as abas (cockpit /forja/*
// + telas absorvidas /team-mcp/*) pra que o header seja IDÊNTICO em tudo:
// título "Forja" + ações (sino/⌘K/Novo issue) + tab-strip de 10 abas.
// SEM eyebrow (removido a pedido do Wagner 2026-06-16).
//
// Fonte única da tab-strip — antes vivia inline no Cockpit; extraída pra
// reuso nas telas TeamMcp absorvidas (Equipe/Tarefas/CC Sessions/Saúde).

import { Fragment } from 'react';
import { Link } from '@inertiajs/react';
import { Activity, Bell, CalendarRange, Code2, Columns3, Gavel, History, Inbox, LayoutGrid, List, Plug, Search, Users, Workflow } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import { cn } from '@/Lib/utils';

const COCKPIT_SUBTITLE =
  'Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente).';

/**
 * Os 3 grupos da faixa (2026-08-08). 11 destinos chapados misturavam três
 * trabalhos diferentes, e a barra virava uma lista pra ler inteira toda vez:
 *
 *   Trabalho  — o fluxo do issue: o que decidir, o que fazer, quando vence.
 *   Esteira   — a operação da máquina: contrato MCP, quem é quem, saúde.
 *   Histórico — o registro: o que shippou, o que as sessões fizeram.
 *
 * É **só apresentação**: nenhuma rota muda, nenhum item some, e
 * `config/core_topnavs.php` segue chapado (é o que alimenta o SHELL; esta faixa
 * é a do HUB — as duas superfícies, lápide §5 2026-08-06). A ordem dentro do
 * grupo é a de uso, não a alfabética.
 */
export const FORJA_GRUPOS = [
  { key: 'trabalho',  label: 'Trabalho' },
  { key: 'esteira',   label: 'Esteira' },
  { key: 'historico', label: 'Histórico' },
] as const;

export const FORJA_TABS = [
  // Aprovações — 2026-08-08. Superfície do funil de admissão (ADR 0368): o que
  // espera por decisão de [W], mais antigo primeiro. Vem PRIMEIRO de propósito —
  // é a fila que custa dinheiro parada. O 301 de `/forja` pra cá e a absorção da
  // Triagem como tipo "Proposta" são o reagrupamento (PR seguinte), não isto.
  { key: 'aprovacoes', grupo: 'trabalho', label: 'Aprovações', href: '/forja/aprovacoes',    icon: Gavel,
    hint: 'O que espera por uma decisão sua' },
  { key: 'triagem',   grupo: 'trabalho', label: 'Triagem',     href: '/forja',                icon: Inbox,
    hint: 'Propostas sem dono ou sem prioridade' },
  // O par Backlog × Tarefas é a sobreposição conhecida (US-FORJA-006). Enquanto a
  // decisão de qual implementação sobrevive não sai, o `hint` faz o mínimo: diz em
  // voz alta que um é o recorte do projeto FORJA e o outro é o universo. Dois itens
  // com o mesmo nome e escopo diferente, sem rótulo, é o que confunde.
  { key: 'backlog',   grupo: 'trabalho', label: 'Backlog',     href: '/forja/backlog',        icon: List,
    hint: 'Issues do projeto FORJA' },
  { key: 'quadro',    grupo: 'trabalho', label: 'Quadro',      href: '/forja/quadro',         icon: LayoutGrid,
    hint: 'Fluxo por fase F0→F3.5' },
  // Roadmap (Gantt) — 2026-08-06. A tela chegou da Jana no PR 5310 (ADR 0366 §D-C item 3).
  // ESTA é a fonte da faixa do hub: `AppShellV2` NÃO renderiza topnav aqui (o Cockpit
  // esconde a barra do shell e desenha a própria). Provado em runtime: `shell.topnavs
  // .Forja__core` já entregava 10 itens com este, e o DOM tinha ZERO `.topnav-chip` —
  // registrar em `config/core_topnavs.php` (PR 5339) alimentou o shell e não a tela.
  // Quem adicionar aba aqui: adicione também `<ForjaHub>` na Page nova, senão ela abre
  // sem faixa (foi o caso do Gantt até hoje).
  { key: 'roadmap-gantt', grupo: 'trabalho', label: 'Roadmap (Gantt)', href: '/forja/roadmap-gantt', icon: CalendarRange,
    hint: 'Tasks no tempo, por módulo' },
  // Telas TeamMcp absorvidas (fusão) — reusam as canônicas ricas.
  { key: 'tarefas',   grupo: 'trabalho', label: 'Tarefas',     href: '/team-mcp/tasks',       icon: Columns3,
    hint: 'Todas as tasks do time, sem recorte de projeto' },

  // Handoffs vem ANTES do MCP no grupo: é o dado vivo da esteira (o loop rodando
  // agora); o MCP ao lado é a vitrine do contrato. Saiu de dentro dele em 2026-08-08.
  { key: 'handoffs',  grupo: 'esteira',  label: 'Handoffs',    href: '/forja/handoffs',       icon: Workflow,
    hint: 'Loop de design Cowork → Code: pendente, travado no gate, envelhecido' },
  { key: 'mcp',       grupo: 'esteira',  label: 'MCP',         href: '/forja/mcp',            icon: Plug,
    hint: 'Contrato de ferramentas, tokens e auditoria' },
  { key: 'equipe',    grupo: 'esteira',  label: 'Equipe',      href: '/team-mcp/team',        icon: Users,
    hint: 'Quem é quem, tokens e quota' },
  { key: 'saude',     grupo: 'esteira',  label: 'Saúde',       href: '/team-mcp/scorecard',   icon: Activity,
    hint: 'Scorecard do sistema' },

  { key: 'changelog', grupo: 'historico', label: 'Changelog',   href: '/forja/changelog',      icon: History,
    hint: 'O que shippou — PRs, ADRs e ondas' },
  { key: 'cc',        grupo: 'historico', label: 'CC Sessions', href: '/team-mcp/cc-sessions', icon: Code2,
    hint: 'Sessões Claude Code do time' },
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

      {/* Tab-strip — idêntica em todas as telas do hub, agora em 3 grupos.
          shrink-0: o slot do AppShellV2 é flex-column de altura limitada; sem isto
          o flex-shrink esmaga o nav (que tem overflow-x-auto) a ~3px nas telas
          longas absorvidas (/team-mcp/*). Mantém o tab-strip sempre em altura cheia.

          O `<nav>` segue sendo o ÚNICO contêiner flex: os grupos entram como
          irmãos no mesmo eixo (rótulo + itens + divisor), via `<Fragment>` e sem
          wrapper novo. É de propósito — um contêiner de layout por grupo somaria
          hits no `layout-primitives-guard` (ADR 0253) sem ganho nenhum de layout.
          (E o guard varre o arquivo inteiro, comentário incluso: descrever o
          anti-padrão citando a classe literal já o faz disparar.) */}
      <nav className="mt-2 inline-flex w-full shrink-0 items-center gap-1 overflow-x-auto border-b px-6" data-testid="forja-tabs">
        {FORJA_GRUPOS.map((grupo, gi) => {
          const tabs = FORJA_TABS.filter((t) => t.grupo === grupo.key);
          if (tabs.length === 0) {
            return null; // grupo sem itens não desenha rótulo órfão
          }

          return (
            <Fragment key={grupo.key}>
              {gi > 0 && (
                <span className="mx-1.5 h-4 w-px shrink-0 bg-border" aria-hidden />
              )}

              {/* Rótulo do grupo — PROGRESSIVE ENHANCEMENT, e o motivo é medido.
                  A faixa é horizontal e já vinha apertada: com 11 itens o conteúdo
                  dá 1140px, e o nav dispõe de (viewport − sidebar 272 − padding 48).
                  Medido em produção logo após o merge:

                    viewport 1280 → scroll +180px  (JÁ existia antes dos grupos)
                    viewport 1440 → scroll  +20px  (idem)
                    viewport 1512 → CABIA, e os rótulos quebraram (+132px)

                  Os rótulos custam 158px e os divisores 26px. Então o texto só
                  entra a partir de `2xl` (1536px), onde sobra espaço de verdade;
                  abaixo disso o DIVISOR sozinho já agrupa visualmente por 26px, e
                  a barra volta a caber exatamente como antes deste PR.

                  `aria-hidden` porque é ornamento: quem usa leitor de tela recebe o
                  destino pelo texto do link, e um "TRABALHO" solto na nav atrapalha.
                  Como é aria-hidden, escondê-lo por breakpoint não tira informação
                  de ninguém — só de quem tem pixels sobrando. */}
              <span
                aria-hidden
                className="hidden shrink-0 select-none text-[9.5px] font-semibold uppercase tracking-tight text-muted-foreground/70 2xl:inline"
                data-testid={`forja-grupo-${grupo.key}`}
              >
                {grupo.label}
              </span>

              {tabs.map((t) => {
                const isActive = t.key === active;
                const Icon = t.icon;
                const badge = t.key === 'triagem' ? triagemCount : undefined;
                return (
                  <Link
                    key={t.key}
                    href={t.href}
                    title={t.hint}
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
            </Fragment>
          );
        })}
      </nav>
    </>
  );
}
