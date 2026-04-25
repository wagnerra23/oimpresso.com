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
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import StatusBadge from '@/Components/shared/StatusBadge';

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
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
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
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

function FinanceiroDashboard({ kpis, titulos, filters }: Props) {
  const [busca, setBusca] = useState(filters.busca ?? '');

  // Inertia paginator pode vir achatado (current_page direto) ou em meta
  const meta = titulos.meta ?? {
    current_page: titulos.current_page ?? 1,
    per_page: titulos.per_page ?? 25,
    total: titulos.total ?? titulos.data.length,
    last_page: titulos.last_page ?? 1,
  };

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

  const r = kpis.receber_aberto;
  const p = kpis.pagar_aberto;
  const rm = kpis.recebido_mes;
  const pm = kpis.pago_mes;

  return (
    <>
      <Head title="Financeiro — Dashboard" />

      <PageHeader
        icon="coins"
        title="Financeiro"
        description="Visão geral das contas a pagar e a receber"
        action={
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => aplicarFiltro({ tipo: 'all', status: 'all', busca: '' })}
            >
              Limpar filtros
            </Button>
            <Button size="sm">Novo título</Button>
          </div>
        }
      />

      {/* KPI Grid (UI-0002) — props CORRETOS: cols, icon string, description, selected, tone */}
      <KpiGrid cols={4} className="mt-6">
        <KpiCard
          icon="arrow-down-circle"
          tone="success"
          label="A receber"
          value={brl(r.valor)}
          description={
            r.vencidos_qtd > 0
              ? `${r.qtd} títulos · ${r.vencidos_qtd} vencidos (${brl(r.vencidos_valor)})`
              : `${r.qtd} títulos abertos`
          }
          onClick={() => aplicarFiltro({ tipo: 'receber', status: 'aberto' })}
          selected={filters.tipo === 'receber' && filters.status === 'aberto'}
        />
        <KpiCard
          icon="arrow-up-circle"
          tone={p.vencidos_qtd > 0 ? 'warning' : 'default'}
          label="A pagar"
          value={brl(p.valor)}
          description={
            p.vencidos_qtd > 0
              ? `${p.qtd} títulos · ${p.vencidos_qtd} vencidos (${brl(p.vencidos_valor)})`
              : `${p.qtd} títulos abertos`
          }
          onClick={() => aplicarFiltro({ tipo: 'pagar', status: 'aberto' })}
          selected={filters.tipo === 'pagar' && filters.status === 'aberto'}
        />
        <KpiCard
          icon="check-circle-2"
          tone="success"
          label="Recebidos no mês"
          value={brl(rm.valor)}
          description={`${rm.qtd} baixas`}
          onClick={() => aplicarFiltro({ tipo: 'receber', status: 'quitado' })}
          selected={filters.tipo === 'receber' && filters.status === 'quitado'}
        />
        <KpiCard
          icon="check-circle-2"
          tone="info"
          label="Pagos no mês"
          value={brl(pm.valor)}
          description={`${pm.qtd} baixas`}
          onClick={() => aplicarFiltro({ tipo: 'pagar', status: 'quitado' })}
          selected={filters.tipo === 'pagar' && filters.status === 'quitado'}
        />
      </KpiGrid>

      {/* Filtros */}
      <Card className="mt-6 mb-4">
        <CardContent className="pt-6 flex flex-col md:flex-row gap-3 md:items-end">
          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">Tipo</label>
            <Select
              value={filters.tipo}
              onValueChange={(v) => aplicarFiltro({ tipo: v as Filters['tipo'] })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="receber">A receber</SelectItem>
                <SelectItem value="pagar">A pagar</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">Status</label>
            <Select
              value={filters.status}
              onValueChange={(v) => aplicarFiltro({ status: v as Filters['status'] })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="aberto">Aberto</SelectItem>
                <SelectItem value="parcial">Parcial</SelectItem>
                <SelectItem value="quitado">Quitado</SelectItem>
                <SelectItem value="cancelado">Cancelado</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <form onSubmit={onSearch} className="flex-[2] flex gap-2 items-end">
            <div className="flex-1">
              <label className="text-xs font-medium text-muted-foreground block mb-1">Busca</label>
              <Input
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Número ou cliente..."
              />
            </div>
            <Button type="submit" size="sm">
              Buscar
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Tabela única */}
      <Card>
        <CardHeader>
          <CardTitle>{meta.total} títulos</CardTitle>
          <CardDescription>
            Página {meta.current_page} de {meta.last_page}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {titulos.data.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              Nenhum título encontrado com os filtros atuais.
              <br />
              <span className="text-xs mt-2 block">
                Vendas finalizadas com pagamento devido aparecerão aqui automaticamente.
              </span>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-2 font-medium">#</th>
                    <th className="text-left py-2 px-2 font-medium">Cliente / Fornecedor</th>
                    <th className="text-center py-2 px-2 font-medium">Tipo</th>
                    <th className="text-center py-2 px-2 font-medium">Status</th>
                    <th className="text-left py-2 px-2 font-medium">Vencimento</th>
                    <th className="text-right py-2 px-2 font-medium">Valor</th>
                    <th className="text-right py-2 px-2 font-medium">Saldo</th>
                  </tr>
                </thead>
                <tbody>
                  {titulos.data.map((t) => (
                    <tr key={t.id} className="border-b hover:bg-muted/40">
                      <td className="py-2 px-2 font-mono text-xs">{t.numero}</td>
                      <td className="py-2 px-2">{t.cliente_nome}</td>
                      <td className="text-center py-2 px-2">
                        <Badge variant={t.tipo === 'receber' ? 'default' : 'secondary'}>
                          {t.tipo === 'receber' ? 'Receber' : 'Pagar'}
                        </Badge>
                      </td>
                      <td className="text-center py-2 px-2">
                        <StatusBadge kind="financeiro_titulo" value={t.status} />
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
                      <td className="text-right py-2 px-2 font-mono font-semibold">
                        {brl(t.valor_aberto)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Paginação */}
              {meta.last_page > 1 && (
                <div className="flex justify-center gap-1 mt-4">
                  {titulos.links.map((link, i) =>
                    link.url ? (
                      <Link
                        key={i}
                        href={link.url}
                        preserveState
                        preserveScroll
                        className={`px-3 py-1 text-sm rounded ${
                          link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ) : (
                      <span
                        key={i}
                        className="px-3 py-1 text-sm text-muted-foreground"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ),
                  )}
                </div>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}

FinanceiroDashboard.layout = (page: ReactNode) => <AppShell children={page} />;

export default FinanceiroDashboard;
