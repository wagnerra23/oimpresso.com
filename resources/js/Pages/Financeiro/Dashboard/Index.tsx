// @memcofre
//   tela: /financeiro
//   module: Financeiro
//   status: implementada
//   stories: US-FIN-013
//   rules: R-FIN-001, R-FIN-002
//   adrs: ui/0002, arq/0005
//   tests: Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowDown, ArrowUp, CheckCircle2, AlertTriangle, Plus, Search } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface KpiAberto {
  valor: number;
  qtd: number;
  vencidos_qtd: number;
  vencidos_valor: number;
}

interface KpiMensal {
  valor: number;
  qtd: number;
}

interface Kpis {
  receber_aberto: KpiAberto;
  pagar_aberto: KpiAberto;
  recebido_mes: KpiMensal;
  pago_mes: KpiMensal;
}

interface Titulo {
  id: number;
  numero: string;
  tipo: 'receber' | 'pagar';
  status: 'aberto' | 'parcial' | 'quitado' | 'cancelado';
  cliente_nome: string;
  vencimento: string | null;
  vencimento_label: string | null;
  valor_total: number;
  valor_aberto: number;
  aging_bucket: string;
  origem: string;
  origem_id: number | null;
}

interface PaginatedTitulos {
  data: Titulo[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
  links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
  tipo: 'all' | 'receber' | 'pagar';
  status: 'all' | 'aberto' | 'parcial' | 'quitado' | 'cancelado';
  busca: string;
}

interface Props {
  kpis: Kpis;
  titulos: PaginatedTitulos;
  filters: Filters;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

function FinanceiroDashboard({ kpis, titulos, filters }: Props) {
  const [busca, setBusca] = useState(filters.busca);

  const aplicarFiltro = (patch: Partial<Filters>) => {
    router.get('/financeiro', { ...filters, ...patch }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault();
    aplicarFiltro({ busca });
  };

  return (
    <>
      <Head title="Financeiro — Dashboard" />

      <PageHeader
        title="Financeiro"
        subtitle="Visão geral das contas a pagar e a receber"
        actions={
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => aplicarFiltro({ tipo: 'all', status: 'all', busca: '' })}>
              Limpar filtros
            </Button>
            <Button size="sm">
              <Plus className="w-4 h-4 mr-1" />
              Novo título
            </Button>
          </div>
        }
      />

      {/* KPI Grid (UI-0002) */}
      <KpiGrid columns={4} className="mb-6">
        <KpiCard
          icon={<ArrowDown className="w-5 h-5 text-emerald-600" />}
          label="A RECEBER"
          value={brl(kpis.receber_aberto.valor)}
          sublabel={`${kpis.receber_aberto.qtd} títulos`}
          warning={kpis.receber_aberto.vencidos_qtd > 0
            ? `${kpis.receber_aberto.vencidos_qtd} vencidos · ${brl(kpis.receber_aberto.vencidos_valor)}`
            : undefined}
          onClick={() => aplicarFiltro({ tipo: 'receber', status: 'aberto' })}
          active={filters.tipo === 'receber' && filters.status === 'aberto'}
        />
        <KpiCard
          icon={<ArrowUp className="w-5 h-5 text-rose-600" />}
          label="A PAGAR"
          value={brl(kpis.pagar_aberto.valor)}
          sublabel={`${kpis.pagar_aberto.qtd} títulos`}
          warning={kpis.pagar_aberto.vencidos_qtd > 0
            ? `${kpis.pagar_aberto.vencidos_qtd} vencidos · ${brl(kpis.pagar_aberto.vencidos_valor)}`
            : undefined}
          onClick={() => aplicarFiltro({ tipo: 'pagar', status: 'aberto' })}
          active={filters.tipo === 'pagar' && filters.status === 'aberto'}
        />
        <KpiCard
          icon={<CheckCircle2 className="w-5 h-5 text-emerald-700" />}
          label="RECEBIDOS"
          value={brl(kpis.recebido_mes.valor)}
          sublabel={`${kpis.recebido_mes.qtd} no mês`}
          onClick={() => aplicarFiltro({ tipo: 'receber', status: 'quitado' })}
          active={filters.tipo === 'receber' && filters.status === 'quitado'}
        />
        <KpiCard
          icon={<CheckCircle2 className="w-5 h-5 text-slate-700" />}
          label="PAGOS"
          value={brl(kpis.pago_mes.valor)}
          sublabel={`${kpis.pago_mes.qtd} no mês`}
          onClick={() => aplicarFiltro({ tipo: 'pagar', status: 'quitado' })}
          active={filters.tipo === 'pagar' && filters.status === 'quitado'}
        />
      </KpiGrid>

      {/* Filtros */}
      <Card className="mb-4">
        <CardContent className="pt-6 flex flex-col md:flex-row gap-3 items-end">
          <div className="flex-1">
            <label className="text-xs font-medium text-muted-foreground">Tipo</label>
            <Select value={filters.tipo} onValueChange={(v) => aplicarFiltro({ tipo: v as Filters['tipo'] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="receber">A receber</SelectItem>
                <SelectItem value="pagar">A pagar</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="flex-1">
            <label className="text-xs font-medium text-muted-foreground">Status</label>
            <Select value={filters.status} onValueChange={(v) => aplicarFiltro({ status: v as Filters['status'] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="aberto">Aberto</SelectItem>
                <SelectItem value="parcial">Parcial</SelectItem>
                <SelectItem value="quitado">Quitado</SelectItem>
                <SelectItem value="cancelado">Cancelado</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <form onSubmit={onSearch} className="flex-1 md:flex-2 flex gap-2">
            <div className="flex-1">
              <label className="text-xs font-medium text-muted-foreground">Busca</label>
              <Input
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Número ou cliente..."
              />
            </div>
            <Button type="submit" size="sm" className="self-end">
              <Search className="w-4 h-4" />
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Tabela única */}
      <Card>
        <CardHeader>
          <CardTitle>{titulos.meta.total} títulos</CardTitle>
          <CardDescription>
            Página {titulos.meta.current_page} de {titulos.meta.last_page}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {titulos.data.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              Nenhum título encontrado com os filtros atuais.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-2">#</th>
                    <th className="text-left py-2 px-2">Cliente / Fornecedor</th>
                    <th className="text-center py-2 px-2">Tipo</th>
                    <th className="text-center py-2 px-2">Status</th>
                    <th className="text-left py-2 px-2">Vencimento</th>
                    <th className="text-right py-2 px-2">Valor</th>
                    <th className="text-right py-2 px-2">Saldo</th>
                  </tr>
                </thead>
                <tbody>
                  {titulos.data.map((t) => (
                    <tr key={t.id} className="border-b hover:bg-muted/40">
                      <td className="py-2 px-2 font-mono text-xs">{t.numero}</td>
                      <td className="py-2 px-2">{t.cliente_nome}</td>
                      <td className="text-center py-2 px-2">
                        <Badge variant={t.tipo === 'receber' ? 'default' : 'secondary'}>
                          {t.tipo === 'receber' ? '📥 R' : '📤 P'}
                        </Badge>
                      </td>
                      <td className="text-center py-2 px-2">
                        <StatusBadge status={t.status} />
                      </td>
                      <td className="py-2 px-2">
                        {t.vencimento_label}
                        {t.aging_bucket !== 'em_dia' && t.status !== 'quitado' && (
                          <Badge variant="destructive" className="ml-2 text-xs">
                            {t.aging_bucket}
                          </Badge>
                        )}
                      </td>
                      <td className="text-right py-2 px-2 font-mono">{brl(t.valor_total)}</td>
                      <td className="text-right py-2 px-2 font-mono font-semibold">{brl(t.valor_aberto)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Paginação */}
              <div className="flex justify-center gap-1 mt-4">
                {titulos.links.map((link, i) => (
                  link.url ? (
                    <Link
                      key={i}
                      href={link.url}
                      preserveState
                      preserveScroll
                      className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ) : (
                    <span
                      key={i}
                      className="px-3 py-1 text-sm text-muted-foreground"
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  )
                ))}
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}

function StatusBadge({ status }: { status: Titulo['status'] }) {
  const map = {
    aberto: { label: 'Aberto', variant: 'default' as const },
    parcial: { label: 'Parcial', variant: 'secondary' as const },
    quitado: { label: 'Quitado', variant: 'outline' as const },
    cancelado: { label: 'Cancelado', variant: 'destructive' as const },
  };
  const cfg = map[status];
  return <Badge variant={cfg.variant}>{cfg.label}</Badge>;
}

FinanceiroDashboard.layout = (page: ReactNode) => <AppShell children={page} />;

export default FinanceiroDashboard;
