// @memcofre
//   tela: /showcase/components
//   module: _DesignSystem
//   status: showcase
//   stories: (showcase, não mapeada)

import AppShellV2 from '@/Layouts/AppShellV2';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import PageHeader from '@/Components/shared/PageHeader';
import { Box, Stack, Inline, Grid, Container, Text } from '@/Components/layout';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import StatusBadge from '@/Components/shared/StatusBadge';
import PageFilters from '@/Components/shared/PageFilters';
import EmptyState from '@/Components/shared/EmptyState';
import BulkActionBar from '@/Components/shared/BulkActionBar';
import PresenceStrip from '@/Pages/Ponto/_components/PresenceStrip';
import ActivityFeed from '@/Pages/Ponto/_components/ActivityFeed';
import AlertInbox from '@/Pages/Ponto/_components/AlertInbox';
import MonthHeatmap from '@/Pages/Ponto/_components/MonthHeatmap';
import { Plus, Download, CheckCheck } from 'lucide-react';
import { formatMinutes } from '@/Lib/utils';

// Mock data para componentes de ponto

const mockPresenca = [
  { id: 1, nome: 'Larissa Fernandes', matricula: '001', iniciais: 'LF', status: 'presente' as const,  entrada: '08:05', saida: null,   ultima: '08:05', marcacoes: 1 },
  { id: 2, nome: 'Wagner Rocha',      matricula: '002', iniciais: 'WR', status: 'presente' as const,  entrada: '07:55', saida: null,   ultima: '12:30', marcacoes: 3 },
  { id: 3, nome: 'Eduarda Silva',     matricula: '003', iniciais: 'ES', status: 'atrasado' as const,  entrada: null,    saida: null,   ultima: null,    marcacoes: 0 },
  { id: 4, nome: 'João Pereira',      matricula: '004', iniciais: 'JP', status: 'saiu' as const,      entrada: '08:00', saida: '17:45', ultima: '17:45', marcacoes: 4 },
  { id: 5, nome: 'Ana Beatriz',       matricula: '005', iniciais: 'AB', status: 'ausente' as const,   entrada: null,    saida: null,   ultima: null,    marcacoes: 0 },
  { id: 6, nome: 'Carlos Mendes',     matricula: '006', iniciais: 'CM', status: 'presente' as const,  entrada: '08:10', saida: null,   ultima: '13:00', marcacoes: 3 },
];

const mockAtividade = [
  { id: 1, tipo: 'ENTRADA',          momento: '13:00', origem: 'REP', tempo: '5min',  colaborador: { id: 6, nome: 'Carlos Mendes',     matricula: '006' }, rep: { identificador: 'BL0001', tipo: 'REP-P' } },
  { id: 2, tipo: 'INTERVALO_FIM',    momento: '12:55', origem: 'REP', tempo: '10min', colaborador: { id: 2, nome: 'Wagner Rocha',      matricula: '002' }, rep: { identificador: 'BL0001', tipo: 'REP-P' } },
  { id: 3, tipo: 'INTERVALO_INICIO', momento: '12:00', origem: 'APP', tempo: '1h',    colaborador: { id: 2, nome: 'Wagner Rocha',      matricula: '002' }, rep: { identificador: null, tipo: 'APP' } },
  { id: 4, tipo: 'SAIDA',            momento: '17:45', origem: 'REP', tempo: '15min', colaborador: { id: 4, nome: 'João Pereira',      matricula: '004' }, rep: { identificador: 'BL0001', tipo: 'REP-P' } },
  { id: 5, tipo: 'ENTRADA',          momento: '08:05', origem: 'REP', tempo: '5h',    colaborador: { id: 1, nome: 'Larissa Fernandes', matricula: '001' }, rep: { identificador: 'BL0001', tipo: 'REP-P' } },
];

const mockAlertas = [
  { tipo: 'atraso',           titulo: 'Atraso de 35min',            subtitulo: 'Eduarda Silva',   acao_label: 'Ver espelho', acao_href: '#', severidade: 'warning' as const },
  { tipo: 'aprovacao_parada', titulo: 'Aprovação parada há 2 dias', subtitulo: 'Larissa Fernandes', acao_label: 'Aprovar',     acao_href: '#', severidade: 'danger'  as const },
  { tipo: 'falta',            titulo: 'Falta de 240min',            subtitulo: 'Ana Beatriz',     acao_label: 'Justificar',   acao_href: '#', severidade: 'danger'  as const },
];

// Gera um mês sintético pra heatmap — 2026-04 (30 dias), padrões variados
const mockLinhasEspelho = Array.from({ length: 30 }, (_, i) => {
  const dia = i + 1;
  const date = new Date(2026, 3, dia);
  const dow = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sab'][date.getDay()];
  const isWeekend = date.getDay() === 0 || date.getDay() === 6;
  // Padrão fictício:
  let trabalhado = 0, atraso = 0, falta = 0, he = 0, divergencia = false;
  if (!isWeekend) {
    trabalhado = 480;
    if (dia === 7)  { atraso = 45; }
    if (dia === 14) { falta = 240; divergencia = true; }
    if (dia === 10) { he = 90; }
    if (dia === 17) { he = 180; } // HE alta
    if (dia === 22) { atraso = 120; divergencia = true; }
    if (dia === 28) { he = 60; }
  }
  return {
    data: `2026-04-${String(dia).padStart(2, '0')}`,
    dow,
    dia,
    is_weekend: isWeekend,
    trabalhado,
    atraso,
    falta,
    he,
    divergencia,
    marcacoes: isWeekend ? [] : [{ hora: '08:00', tipo: 'ENTRADA', origem: 'REP' }],
  };
});

/**
 * Showcase das primitivas de produto (shared).
 *
 * Rota: /showcase/components (só superadmin)
 *
 * Objetivo: ponto único pra ver todos os componentes shared em ação, testar
 * dark mode, validar acessibilidade e tirar print pra design review. Não
 * consome nenhum dado real — tudo mockado nesta file.
 */
export default function Showcase() {
  const [filtroMes, setFiltroMes] = useState<string>('abril-2026');
  const [filtroEstado, setFiltroEstado] = useState<string>('');
  const [query, setQuery] = useState('');
  const [selected, setSelected] = useState<number[]>([]);

  const activeChips = [
    ...(filtroMes ? [{ label: `Mês: ${filtroMes}`, onRemove: () => setFiltroMes('') }] : []),
    ...(filtroEstado ? [{ label: `Estado: ${filtroEstado}`, onRemove: () => setFiltroEstado('') }] : []),
    ...(query ? [{ label: `Busca: "${query}"`, onRemove: () => setQuery('') }] : []),
  ];

  return (
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-8">
        {/* ========================================= PAGE HEADER ========================================= */}
        <Section title="1. PageHeader">
          <PageHeader
            icon="layout-dashboard"
            title="Aprovações pendentes"
            description="12 solicitações aguardando revisão de intercorrências e folgas"
            action={
              <div className="flex gap-2">
                <Button variant="outline" size="sm">
                  <Download size={14} className="mr-1" /> Exportar
                </Button>
                <Button size="sm">
                  <Plus size={14} className="mr-1" /> Nova aprovação
                </Button>
              </div>
            }
          />
        </Section>

        {/* ========================================= KPI GRID ========================================= */}
        <Section title="2. KpiGrid + KpiCard (todas as variantes)">
          <KpiGrid cols={4}>
            <KpiCard label="Colaboradores ativos" value={142} icon="users" tone="default" />
            <KpiCard
              label="Presentes agora"
              value={98}
              icon="user-check"
              tone="success"
              delta={{ value: 5, label: 'vs ontem' }}
            />
            <KpiCard
              label="Atrasos hoje"
              value={7}
              icon="clock-alert"
              tone="warning"
              delta={{ value: -2, label: 'vs ontem' }}
              deltaIsGood={false}
            />
            <KpiCard
              label="Faltas hoje"
              value={3}
              icon="user-x"
              tone="danger"
              description="1 falta justificada aguardando apresentação de atestado"
            />
          </KpiGrid>
          <div className="mt-4">
            <h3 className="mb-2 text-sm font-medium text-muted-foreground">Size variants:</h3>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <KpiCard label="Compacto" value="2h 15m" icon="timer" size="compact" tone="info" />
              <KpiCard label="Default" value="R$ [redacted Tier 0]" icon="dollar-sign" tone="success" />
              <KpiCard
                label="Large (destaque)"
                value="+38h"
                icon="trending-up"
                tone="info"
                size="large"
                description="Horas extras acumuladas no banco de horas"
              />
            </div>
          </div>
        </Section>

        {/* ========================================= STATUS BADGE ========================================= */}
        <Section title="3. StatusBadge (domínio + value)">
          <div className="flex flex-wrap gap-2">
            <Wrap>Intercorrência:</Wrap>
            <StatusBadge kind="intercorrencia" value="rascunho" />
            <StatusBadge kind="intercorrencia" value="pendente" />
            <StatusBadge kind="intercorrencia" value="aprovada" />
            <StatusBadge kind="intercorrencia" value="rejeitada" />
            <StatusBadge kind="intercorrencia" value="aplicada" />
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            <Wrap>Prioridade:</Wrap>
            <StatusBadge kind="prioridade" value="baixa" />
            <StatusBadge kind="prioridade" value="normal" />
            <StatusBadge kind="prioridade" value="alta" />
            <StatusBadge kind="prioridade" value="urgente" />
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            <Wrap>Payment:</Wrap>
            <StatusBadge kind="payment" value="pending" />
            <StatusBadge kind="payment" value="partial" />
            <StatusBadge kind="payment" value="paid" />
            <StatusBadge kind="payment" value="due" />
            <StatusBadge kind="payment" value="overdue" />
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            <Wrap>REP:</Wrap>
            <StatusBadge kind="rep" value="REP_P" />
            <StatusBadge kind="rep" value="REP_C" />
            <StatusBadge kind="rep" value="REP_A" />
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            <Wrap>Fallback (value desconhecido):</Wrap>
            <StatusBadge kind="intercorrencia" value="zumbi" />
          </div>
        </Section>

        {/* ========================================= BADGE (variants base) ========================================= */}
        <Section title="3b. Badge (variants base — UI primitive · Onda G status pills)">
          <div className="flex flex-wrap items-center gap-2">
            <Wrap>Base:</Wrap>
            <Badge variant="default">default</Badge>
            <Badge variant="secondary">secondary</Badge>
            <Badge variant="outline">outline</Badge>
            <Badge variant="ghost">ghost</Badge>
            <Badge variant="link">link</Badge>
            <Badge variant="destructive">destructive (ação)</Badge>
          </div>
          <div className="flex flex-wrap items-center gap-2 mt-2">
            <Wrap>Status soft (Onda G):</Wrap>
            <Badge variant="success">success</Badge>
            <Badge variant="warning">warning</Badge>
            <Badge variant="danger">danger (estado)</Badge>
            <Badge variant="info">info</Badge>
            <Badge variant="neutral">neutral</Badge>
          </div>
        </Section>

        {/* ========================================= PAGE FILTERS ========================================= */}
        <Section title="4. PageFilters + FilterChip">
          <PageFilters
            activeChips={activeChips}
            onReset={() => {
              setFiltroMes('');
              setFiltroEstado('');
              setQuery('');
            }}
            cols={4}
          >
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">Mês</label>
              <Select value={filtroMes} onValueChange={setFiltroMes}>
                <SelectTrigger><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="abril-2026">Abril/2026</SelectItem>
                  <SelectItem value="marco-2026">Março/2026</SelectItem>
                  <SelectItem value="fevereiro-2026">Fevereiro/2026</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">Estado</label>
              <Select value={filtroEstado} onValueChange={setFiltroEstado}>
                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pendente">Pendente</SelectItem>
                  <SelectItem value="aprovada">Aprovada</SelectItem>
                  <SelectItem value="rejeitada">Rejeitada</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">Colaborador</label>
              <Select><SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger><SelectContent><SelectItem value="all">Todos</SelectItem></SelectContent></Select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">Busca</label>
              <Input
                placeholder="Buscar por nome, matrícula..."
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
            </div>
          </PageFilters>
        </Section>

        {/* ========================================= EMPTY STATE ========================================= */}
        <Section title="5. EmptyState (4 variants)">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="rounded-lg border border-border">
              <EmptyState
                icon="inbox"
                title="Nenhuma aprovação pendente"
                description="Todas as solicitações foram processadas. Bom trabalho!"
                action={<Button variant="outline" size="sm">Ver histórico</Button>}
                variant="default"
              />
            </div>
            <div className="rounded-lg border border-border">
              <EmptyState
                icon="search-x"
                title="Nenhum resultado encontrado"
                description={`Tente ajustar os filtros ou remover termos da busca.`}
                action={<Button variant="ghost" size="sm">Limpar filtros</Button>}
                variant="search"
              />
            </div>
            <div className="rounded-lg border border-border">
              <EmptyState
                icon="alert-triangle"
                title="Erro ao carregar"
                description="Verifique sua conexão e tente novamente. Se persistir, contate o suporte."
                action={<Button variant="destructive" size="sm">Tentar novamente</Button>}
                variant="error"
              />
            </div>
            <div className="rounded-lg border border-border">
              <EmptyState
                icon="check-check"
                title="Tudo em dia!"
                description="Nenhum pendente na sua caixa de aprovações."
                variant="success"
              />
            </div>
          </div>
        </Section>

        {/* ========================================= BULK ACTION BAR ========================================= */}
        <Section title="6. BulkActionBar (ativa: selecionar linhas fake abaixo)">
          <div className="rounded-lg border border-border">
            {[1, 2, 3, 4, 5].map((id) => (
              <label
                key={id}
                className="flex items-center gap-3 px-4 py-3 border-b border-border last:border-b-0 cursor-pointer hover:bg-muted/30 transition-colors"
              >
                <input
                  type="checkbox"
                  checked={selected.includes(id)}
                  onChange={(e) =>
                    setSelected((prev) =>
                      e.target.checked ? [...prev, id] : prev.filter((x) => x !== id),
                    )
                  }
                  className="h-4 w-4"
                />
                <div className="flex-1">
                  <div className="text-sm font-medium">Aprovação #{id}</div>
                  <div className="text-xs text-muted-foreground">
                    Larissa Fernandes · Intercorrência · R$ [redacted Tier 0]
                  </div>
                </div>
                <StatusBadge kind="intercorrencia" value="pendente" />
              </label>
            ))}
          </div>
          <BulkActionBar selectedCount={selected.length} onClear={() => setSelected([])}>
            <Button variant="default" size="sm" className="bg-success hover:bg-success/90">
              <CheckCheck size={14} className="mr-1" /> Aprovar em lote
            </Button>
            <Button variant="destructive" size="sm">
              Rejeitar
            </Button>
          </BulkActionBar>
        </Section>

        {/* ========================================= PONTO: PRESENÇA STRIP ========================================= */}
        <Section title="7. Ponto · PresenceStrip (dashboard vivo)">
          <PresenceStrip colaboradores={mockPresenca} />
        </Section>

        {/* ========================================= PONTO: ACTIVITY FEED + ALERT INBOX ========================================= */}
        <Section title="8. Ponto · ActivityFeed + AlertInbox (side by side)">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <ActivityFeed marcacoes={mockAtividade} />
            <AlertInbox alertas={mockAlertas} />
          </div>
        </Section>

        {/* ========================================= PONTO: MONTH HEATMAP ========================================= */}
        <Section title="9. Ponto · MonthHeatmap (Espelho do mês)">
          <MonthHeatmap
            mes="2026-04"
            linhas={mockLinhasEspelho}
            onDayClick={(l) => alert(`Click no dia ${l.dia} · ${formatMinutes(l.trabalhado)} trabalhado`)}
          />
        </Section>

        {/* ========================================= DESIGN SYSTEM RULES ========================================= */}
        <Section title="10. Conformidade — Design System">
          <div className="rounded-lg border border-border bg-card p-4 text-sm space-y-2">
            <p className="text-muted-foreground">Todos os componentes obedecem:</p>
            <ul className="list-disc list-inside space-y-1 text-foreground/80">
              <li>R-DS-001 · Reusa primitivas <code className="text-xs bg-muted px-1 rounded">shadcn/ui</code></li>
              <li>R-DS-002 · Cores via tokens semânticos (<code className="text-xs bg-muted px-1 rounded">bg-primary</code>, <code className="text-xs bg-muted px-1 rounded">text-muted-foreground</code>)</li>
              <li>R-DS-003 · Ícones via <code className="text-xs bg-muted px-1 rounded">lucide-react</code></li>
              <li>R-DS-004 · Espaçamento em múltiplos de 4px</li>
              <li>R-DS-005 · Dark mode automático (testar com toggle no rodapé)</li>
              <li>R-DS-006 · Focus visível em todos interativos</li>
              <li>R-DS-007 · Zero CSS custom</li>
            </ul>
          </div>
        </Section>

        {/* ========================================= LAYOUT PRIMITIVES ========================================= */}
        <Section title="11. Primitivos de Layout (ADR 0253 · F3 — props = token, zero flex solto)">
          <div className="space-y-6">
            {/* Text */}
            <div>
              <h3 className="mb-2 text-sm font-medium text-muted-foreground">Text — tipografia 100% type-scale token:</h3>
              <Stack gap={1}>
                <Text as="h2" size="2xl" weight="semibold">Título 2xl semibold</Text>
                <Text size="base">Corpo base · tom default</Text>
                <Text size="sm" tone="muted">Legenda sm · tom muted</Text>
                <Text size="sm" tone="primary" weight="medium">Destaque primary</Text>
                <Text size="sm" tone="destructive">Erro destructive</Text>
              </Stack>
            </div>

            {/* Stack */}
            <div>
              <h3 className="mb-2 text-sm font-medium text-muted-foreground">Stack — empilha vertical com gap-token (gap=2 vs gap=6):</h3>
              <Inline gap={6} align="start">
                <Stack gap={2}>
                  <Tile>gap=2</Tile><Tile>·</Tile><Tile>·</Tile>
                </Stack>
                <Stack gap={6}>
                  <Tile>gap=6</Tile><Tile>·</Tile><Tile>·</Tile>
                </Stack>
              </Inline>
            </div>

            {/* Inline */}
            <div>
              <h3 className="mb-2 text-sm font-medium text-muted-foreground">Inline — alinha horizontal + wrap (chips):</h3>
              <Inline gap={2} wrap>
                {['Aberta', 'Orçamento', 'Aprovada', 'Em execução', 'Concluída', 'Faturada'].map((s) => (
                  <Badge key={s} variant="secondary">{s}</Badge>
                ))}
              </Inline>
            </div>

            {/* Grid */}
            <div>
              <h3 className="mb-2 text-sm font-medium text-muted-foreground">Grid — cols=3, gap=4:</h3>
              <Grid cols={3} gap={4}>
                {['A', 'B', 'C', 'D', 'E', 'F'].map((c) => (
                  <Box key={c} p={4} className="rounded-md border border-border bg-muted/40">
                    <Text size="sm" tone="muted">Card {c}</Text>
                  </Box>
                ))}
              </Grid>
            </div>

            {/* Box + Container */}
            <div>
              <h3 className="mb-2 text-sm font-medium text-muted-foreground">Box (padding p=6) dentro de Container (size=sm, centralizado):</h3>
              <Container size="sm" px={0}>
                <Box p={6} className="rounded-md border border-dashed border-primary/40 bg-primary/5">
                  <Text size="sm" tone="primary">Container size=sm · Box p=6 — toda a medida vem de token</Text>
                </Box>
              </Container>
            </div>

            {/* Composição — a mesma "tela-cartão" do teste, agora visível */}
            <div>
              <h3 className="mb-2 text-sm font-medium text-muted-foreground">Composição 100% primitivos (zero className=&quot;flex gap&quot; solto):</h3>
              <Box p={4} className="rounded-lg border border-border bg-card">
                <Stack gap={4}>
                  <Text as="h3" size="xl" weight="semibold">Resumo da OS</Text>
                  <Grid cols={2} gap={4}>
                    <Box p={3} className="rounded-md bg-muted/40"><Text size="sm" tone="muted">Peças · R$ [redacted Tier 0]</Text></Box>
                    <Box p={3} className="rounded-md bg-muted/40"><Text size="sm" tone="muted">Mão de obra · R$ [redacted Tier 0]</Text></Box>
                  </Grid>
                  <Inline gap={2} justify="end">
                    <Button variant="outline" size="sm">Cancelar</Button>
                    <Button size="sm">Faturar</Button>
                  </Inline>
                </Stack>
              </Box>
            </div>
          </div>
        </Section>
      </div>
    </>
  );
}

function Tile({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-md border border-border bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground">
      {children}
    </div>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-3">
      <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border pb-2">
        {title}
      </h2>
      {children}
    </section>
  );
}

function Wrap({ children }: { children: ReactNode }) {
  return <span className="text-xs text-muted-foreground mr-1">{children}</span>;
}

Showcase.layout = (page: ReactNode) => (
  <AppShellV2 title="Showcase · Componentes" breadcrumbItems={[{ label: 'Design System' }, { label: 'Showcase' }]}>
    {page}
  </AppShellV2>
);
