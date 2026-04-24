// @docvault
//   tela: /showcase/components
//   module: _DesignSystem
//   status: showcase
//   stories: (showcase, não mapeada)

import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import StatusBadge from '@/Components/shared/StatusBadge';
import PageFilters from '@/Components/shared/PageFilters';
import EmptyState from '@/Components/shared/EmptyState';
import BulkActionBar from '@/Components/shared/BulkActionBar';
import { Plus, Download, CheckCheck } from 'lucide-react';

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
      <Head title="Showcase · Componentes shared" />

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
              <KpiCard label="Default" value="R$ 12.450" icon="dollar-sign" tone="success" />
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
                    Larissa Fernandes · Intercorrência · R$ 125,00
                  </div>
                </div>
                <StatusBadge kind="intercorrencia" value="pendente" />
              </label>
            ))}
          </div>
          <BulkActionBar selectedCount={selected.length} onClear={() => setSelected([])}>
            <Button variant="default" size="sm" className="bg-emerald-600 hover:bg-emerald-700">
              <CheckCheck size={14} className="mr-1" /> Aprovar em lote
            </Button>
            <Button variant="destructive" size="sm">
              Rejeitar
            </Button>
          </BulkActionBar>
        </Section>

        {/* ========================================= DESIGN SYSTEM RULES ========================================= */}
        <Section title="7. Conformidade — Design System">
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
      </div>
    </>
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
  <AppShell breadcrumb={[{ label: 'Design System' }, { label: 'Showcase' }]}>{page}</AppShell>
);
