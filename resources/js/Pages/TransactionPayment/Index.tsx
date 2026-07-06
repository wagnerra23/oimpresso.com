// @memcofre tela=/payments/v2 module=TransactionPayment
// MWART Wave Blade T1 Migration B (2026-05-17) — coexiste com Blade /payments
// Charter: ./Index.charter.md

import { useEffect, useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { Badge } from '@/Components/ui/badge';
import { Eye, Pencil, Receipt, TrendingUp, TrendingDown, AlertCircle } from 'lucide-react';

interface Pagamento {
  id: number;
  amount: string | number;
  method: string;
  paid_on: string;
  payment_ref_no: string | null;
  transaction_id: number;
  transaction_ref_no: string | null;
  transaction_type: string;
  payment_status: 'paid' | 'partial' | 'due' | null;
  contact_name: string | null;
  contact_type: string | null;
}

interface PaginatorMeta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  pagamentos: { data: Pagamento[] } & PaginatorMeta;
  filtros: {
    tipo: 'recebido' | 'pago' | null;
    status: 'paid' | 'partial' | 'due' | null;
    from: string | null;
    to: string | null;
  };
  kpis?: {
    recebido_30d: number;
    pago_30d: number;
    pendentes_count: number;
  };
}

const METHOD_LABELS: Record<string, string> = {
  cash: 'Dinheiro',
  card: 'Cartão',
  cheque: 'Cheque',
  bank_transfer: 'Transferência',
  pix: 'PIX',
  boleto: 'Boleto',
  advance: 'Adiantamento',
  custom_pay_1: 'Outro 1',
  custom_pay_2: 'Outro 2',
  custom_pay_3: 'Outro 3',
};

const STATUS_LABELS: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
  paid: { label: 'Pago', variant: 'default' },
  partial: { label: 'Parcial', variant: 'secondary' },
  due: { label: 'Em aberto', variant: 'destructive' },
};

function formatBRL(v: string | number) {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  if (Number.isNaN(n)) return 'R$ [redacted Tier 0]';
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDateTime(v: string | null) {
  if (!v) return '—';
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return v;
  return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function KpiCard({ icon: Icon, label, value, color }: { icon: typeof TrendingUp; label: string; value: string; color: string }) {
  return (
    <Card>
      <CardContent className="pt-6">
        <div className="flex items-center gap-3">
          <div className={`p-2 rounded-md ${color}`}>
            <Icon className="h-5 w-5" />
          </div>
          <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-xl font-semibold tracking-tight">{value}</div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function KpiSkeleton() {
  return (
    <Card>
      <CardContent className="pt-6">
        <Skeleton className="h-12 w-full" />
      </CardContent>
    </Card>
  );
}

function Index({ pagamentos, filtros, kpis }: Props) {
  const [tipoLocal, setTipoLocal] = useState<Props['filtros']['tipo']>(filtros.tipo);
  const [statusLocal, setStatusLocal] = useState<Props['filtros']['status']>(filtros.status);

  // Persistência localStorage (UX)
  useEffect(() => {
    const tipoSaved = localStorage.getItem('oimpresso.transaction_payment.index.tipo');
    const statusSaved = localStorage.getItem('oimpresso.transaction_payment.index.status');
    if (!filtros.tipo && tipoSaved && ['recebido', 'pago'].includes(tipoSaved)) {
      setTipoLocal(tipoSaved as Props['filtros']['tipo']);
    }
    if (!filtros.status && statusSaved && ['paid', 'partial', 'due'].includes(statusSaved)) {
      setStatusLocal(statusSaved as Props['filtros']['status']);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const aplicarFiltro = (key: 'tipo' | 'status', value: string | null) => {
    if (value) {
      localStorage.setItem(`oimpresso.transaction_payment.index.${key}`, value);
    } else {
      localStorage.removeItem(`oimpresso.transaction_payment.index.${key}`);
    }
    if (key === 'tipo') setTipoLocal(value as Props['filtros']['tipo']);
    if (key === 'status') setStatusLocal(value as Props['filtros']['status']);

    // D-14: partial reload — só re-busca o que muda com filtro.
    // kpis são Inertia::defer no controller (janela 30d fixa) — não re-buscam.
    router.get(
      '/payments/v2',
      { ...filtros, [key]: value },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['pagamentos', 'filtros'],
      }
    );
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Pagamentos</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Recebidos (vendas) e Pagos (compras/despesas) — visão consolidada cross-transação.
        </p>
      </div>

      {/* KPIs deferred — fallback skeleton enquanto Inertia::defer carrega */}
      <Deferred data="kpis" fallback={
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <KpiSkeleton /><KpiSkeleton /><KpiSkeleton />
        </div>
      }>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <KpiCard
            icon={TrendingUp}
            label="Recebido 30d"
            value={formatBRL(kpis?.recebido_30d ?? 0)}
            color="bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200"
          />
          <KpiCard
            icon={TrendingDown}
            label="Pago 30d"
            value={formatBRL(kpis?.pago_30d ?? 0)}
            color="bg-rose-100 text-rose-900 dark:bg-rose-900/30 dark:text-rose-200"
          />
          <KpiCard
            icon={AlertCircle}
            label="Pendentes"
            value={String(kpis?.pendentes_count ?? 0)}
            color="bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200"
          />
        </div>
      </Deferred>

      {/* Filter chips */}
      <div className="flex flex-wrap gap-2">
        <span className="text-xs text-muted-foreground self-center mr-2">Tipo:</span>
        {[
          { v: null, label: 'Todos' },
          { v: 'recebido', label: 'Recebidos' },
          { v: 'pago', label: 'Pagos' },
        ].map((f) => (
          <Button
            key={`tipo-${f.v}`}
            size="sm"
            variant={tipoLocal === f.v ? 'default' : 'outline'}
            onClick={() => aplicarFiltro('tipo', f.v)}
          >
            {f.label}
          </Button>
        ))}
        <span className="self-center mx-2 text-muted-foreground">·</span>
        <span className="text-xs text-muted-foreground self-center mr-2">Status:</span>
        {[
          { v: null, label: 'Todos' },
          { v: 'paid', label: 'Pago' },
          { v: 'partial', label: 'Parcial' },
          { v: 'due', label: 'Em aberto' },
        ].map((f) => (
          <Button
            key={`status-${f.v}`}
            size="sm"
            variant={statusLocal === f.v ? 'default' : 'outline'}
            onClick={() => aplicarFiltro('status', f.v)}
          >
            {f.label}
          </Button>
        ))}
      </div>

      {/* Tabela */}
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50">
            <tr className="text-left">
              <th className="px-4 py-2 font-medium">Data</th>
              <th className="px-4 py-2 font-medium">Ref</th>
              <th className="px-4 py-2 font-medium">Cliente/Fornecedor</th>
              <th className="px-4 py-2 font-medium">Método</th>
              <th className="px-4 py-2 font-medium text-right">Valor</th>
              <th className="px-4 py-2 font-medium">Status</th>
              <th className="px-4 py-2 font-medium text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            {pagamentos.data.length === 0 && (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                  <Receipt className="h-8 w-8 mx-auto mb-2 opacity-50" />
                  Nenhum pagamento encontrado com esses filtros.
                </td>
              </tr>
            )}
            {pagamentos.data.map((p) => (
              <tr key={p.id} className="border-t hover:bg-muted/30">
                <td className="px-4 py-3 whitespace-nowrap">{formatDateTime(p.paid_on)}</td>
                <td className="px-4 py-3 font-mono text-xs">{p.payment_ref_no ?? '—'}</td>
                <td className="px-4 py-3">
                  <div className="font-medium">{p.contact_name ?? '—'}</div>
                  <div className="text-xs text-muted-foreground">
                    {p.transaction_ref_no ? `Tx ${p.transaction_ref_no}` : `Tx #${p.transaction_id}`}
                  </div>
                </td>
                <td className="px-4 py-3">{METHOD_LABELS[p.method] ?? p.method}</td>
                <td className="px-4 py-3 text-right font-medium">{formatBRL(p.amount)}</td>
                <td className="px-4 py-3">
                  {p.payment_status && STATUS_LABELS[p.payment_status] ? (
                    <Badge variant={STATUS_LABELS[p.payment_status].variant}>
                      {STATUS_LABELS[p.payment_status].label}
                    </Badge>
                  ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                  )}
                </td>
                <td className="px-4 py-3 text-right space-x-1">
                  <Link href={`/payments/v2/${p.id}`}>
                    <Button size="sm" variant="ghost" title="Ver">
                      <Eye className="h-4 w-4" />
                    </Button>
                  </Link>
                  <Link href={`/payments/v2/${p.id}/edit`}>
                    <Button size="sm" variant="ghost" title="Editar">
                      <Pencil className="h-4 w-4" />
                    </Button>
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Paginação */}
      {pagamentos.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <div className="text-muted-foreground">
            Página {pagamentos.current_page} de {pagamentos.last_page} · {pagamentos.total} pagamentos
          </div>
          <div className="flex gap-1">
            {pagamentos.links.map((l, i) => (
              <Button
                key={i}
                size="sm"
                variant={l.active ? 'default' : 'outline'}
                disabled={!l.url}
                // D-14: partial reload — paginação só re-busca a página da lista.
                onClick={() => l.url && router.visit(l.url, { preserveScroll: true, only: ['pagamentos', 'filtros'] })}
                dangerouslySetInnerHTML={{ __html: l.label }}
              />
            ))}
          </div>
        </div>
      )}

      <Card>
        <CardContent className="pt-6 text-xs text-muted-foreground">
          Versão Inertia (v2). A versão Blade original segue em <code>/payments</code> sem alteração — coexistência durante migração MWART.
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Pagamentos" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Pagamentos' }]}>
    {page}
  </AppShellV2>
);

export default Index;
