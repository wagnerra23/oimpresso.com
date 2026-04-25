// @memcofre tela=/financeiro/boletos module=Financeiro

import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Receipt, X, Copy, AlertTriangle, CheckCircle2, Hourglass, FileText } from 'lucide-react';
import { toast } from 'sonner';

interface Remessa {
  id: number;
  titulo_id: number;
  titulo_numero: string | null;
  cliente: string | null;
  nosso_numero: string;
  linha_digitavel: string;
  codigo_barras: string;
  valor_total: string;
  vencimento: string | null;
  status: string;
  strategy: string;
  enviado_em: string | null;
  pago_em: string | null;
  created_at: string;
}

interface Props {
  remessas: Remessa[];
  filtros: { status: string | null };
}

const STATUS_LABELS: Record<string, { label: string; color: string; icon: typeof CheckCircle2 }> = {
  gerado_mock: { label: 'Mock', color: 'bg-purple-100 text-purple-900 dark:bg-purple-900/30 dark:text-purple-200', icon: FileText },
  gerado: { label: 'Gerado', color: 'bg-blue-100 text-blue-900 dark:bg-blue-900/30 dark:text-blue-200', icon: FileText },
  enviado: { label: 'Enviado', color: 'bg-cyan-100 text-cyan-900 dark:bg-cyan-900/30 dark:text-cyan-200', icon: Hourglass },
  registrado: { label: 'Registrado', color: 'bg-cyan-100 text-cyan-900 dark:bg-cyan-900/30 dark:text-cyan-200', icon: Hourglass },
  pago: { label: 'Pago', color: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200', icon: CheckCircle2 },
  vencido: { label: 'Vencido', color: 'bg-red-100 text-red-900 dark:bg-red-900/30 dark:text-red-200', icon: AlertTriangle },
  cancelado: { label: 'Cancelado', color: 'bg-muted text-muted-foreground', icon: X },
};

function formatBRL(v: string | number) {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(v: string | null) {
  if (!v) return '—';
  const [y, m, d] = v.split('-');
  return `${d}/${m}/${y}`;
}

function Index({ remessas, filtros }: Props) {
  const cancelForm = useForm({});

  const filtrar = (status: string | null) => {
    router.get('/financeiro/boletos', { status }, { preserveScroll: true, preserveState: true });
  };

  const copiar = async (txt: string, label: string) => {
    try {
      await navigator.clipboard.writeText(txt);
      toast.success(`${label} copiado`);
    } catch {
      toast.error('Não foi possível copiar');
    }
  };

  const cancelar = (r: Remessa) => {
    if (!confirm(`Cancelar boleto ${r.nosso_numero}?`)) return;
    cancelForm.post(`/financeiro/boletos/${r.id}/cancelar`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Boleto cancelado'),
      onError: () => toast.error('Erro ao cancelar'),
    });
  };

  return (
    <>
      <Head title="Boletos Emitidos" />
      <div className="p-6 max-w-6xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Boletos Emitidos</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Lista de boletos gerados (CnabDirectStrategy mock-only no MVP).
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <span className="text-xs text-muted-foreground self-center mr-2">Filtros:</span>
          {[
            { v: null, label: 'Todos' },
            { v: 'gerado_mock', label: 'Mock' },
            { v: 'enviado', label: 'Enviados' },
            { v: 'pago', label: 'Pagos' },
            { v: 'cancelado', label: 'Cancelados' },
          ].map((f) => (
            <Button key={`s-${f.v}`} size="sm" variant={filtros.status === f.v ? 'default' : 'outline'}
              onClick={() => filtrar(f.v)}>{f.label}</Button>
          ))}
        </div>

        <div className="rounded-md border overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Nosso Nº</th>
                <th className="px-4 py-2 font-medium">Título</th>
                <th className="px-4 py-2 font-medium">Cliente</th>
                <th className="px-4 py-2 font-medium">Vencimento</th>
                <th className="px-4 py-2 font-medium text-right">Valor</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {remessas.length === 0 && (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">Nenhum boleto emitido.</td></tr>
              )}
              {remessas.map((r) => {
                const cfg = STATUS_LABELS[r.status] ?? STATUS_LABELS.gerado;
                const Icon = cfg.icon;
                return (
                  <tr key={r.id} className="border-t hover:bg-muted/30">
                    <td className="px-4 py-3 font-mono text-xs">{r.nosso_numero}</td>
                    <td className="px-4 py-3 font-mono text-xs">#{r.titulo_numero}</td>
                    <td className="px-4 py-3">{r.cliente ?? '—'}</td>
                    <td className="px-4 py-3">{formatDate(r.vencimento)}</td>
                    <td className="px-4 py-3 text-right font-medium">{formatBRL(r.valor_total)}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded ${cfg.color}`}>
                        <Icon className="h-3 w-3" /> {cfg.label}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right whitespace-nowrap">
                      <Button size="sm" variant="ghost"
                        onClick={() => copiar(r.linha_digitavel, 'Linha digitável')}
                        title="Copiar linha digitável">
                        <Copy className="h-4 w-4" />
                      </Button>
                      {!['pago', 'cancelado'].includes(r.status) && (
                        <Button size="sm" variant="ghost"
                          onClick={() => cancelar(r)}
                          disabled={cancelForm.processing}
                          title="Cancelar boleto">
                          <X className="h-4 w-4" />
                        </Button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <Card>
          <CardContent className="pt-6 text-xs text-muted-foreground space-y-1">
            <p>
              <strong>Mock:</strong> boletos gerados offline (linha digitável + código de barras válidos)
              mas sem envio CNAB ao banco. Onda 2 implementa envio real.
            </p>
            <p>Mostrando até 100 boletos. Cancelar é irreversível — boleto fica no histórico com status &quot;cancelado&quot;.</p>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Index.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
export default Index;
