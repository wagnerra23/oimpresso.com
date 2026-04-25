// @memcofre tela=/financeiro/contas-receber module=Financeiro

import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Receipt, AlertTriangle, CheckCircle2, Circle, Hourglass } from 'lucide-react';
import { toast } from 'sonner';

interface BoletoInfo {
  id: number;
  status: string;
  linha_digitavel: string | null;
  nosso_numero: string | null;
}

interface Titulo {
  id: number;
  numero: string;
  cliente_descricao: string | null;
  cliente_id: number | null;
  valor_total: string;
  valor_aberto: string;
  vencimento: string | null;
  status: 'aberto' | 'parcial' | 'quitado' | 'cancelado';
  origem: string;
  origem_id: number | null;
  boleto: BoletoInfo | null;
}

interface Props {
  titulos: Titulo[];
  filtros: { status: string | null; vence_em: string | null };
}

const STATUS_LABELS: Record<string, { label: string; color: string; icon: typeof Circle }> = {
  aberto: { label: 'Aberto', color: 'bg-blue-100 text-blue-900 dark:bg-blue-900/30 dark:text-blue-200', icon: Circle },
  parcial: { label: 'Parcial', color: 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200', icon: Hourglass },
  quitado: { label: 'Quitado', color: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200', icon: CheckCircle2 },
  cancelado: { label: 'Cancelado', color: 'bg-muted text-muted-foreground', icon: AlertTriangle },
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

function StatusPill({ status }: { status: Titulo['status'] }) {
  const cfg = STATUS_LABELS[status];
  const Icon = cfg.icon;
  return (
    <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded ${cfg.color}`}>
      <Icon className="h-3 w-3" /> {cfg.label}
    </span>
  );
}

function Index({ titulos, filtros }: Props) {
  const emitirForm = useForm({});

  const filtrar = (key: string, value: string | null) => {
    router.get(
      '/financeiro/contas-receber',
      { ...filtros, [key]: value },
      { preserveScroll: true, preserveState: true }
    );
  };

  const emitirBoleto = (titulo: Titulo) => {
    if (titulo.boleto) {
      toast.info(`Já tem boleto: ${titulo.boleto.nosso_numero}`);
      return;
    }
    emitirForm.post(`/financeiro/contas-receber/${titulo.id}/boleto`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Boleto emitido'),
      onError: (errs) => toast.error(typeof errs === 'string' ? errs : 'Erro ao emitir boleto'),
    });
  };

  return (
    <>
      <Head title="Contas a Receber" />

      <div className="p-6 max-w-6xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Contas a Receber</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Títulos a receber gerados de vendas (auto) ou cadastrados manualmente.
          </p>
        </div>

        {/* Filtros simples */}
        <div className="flex flex-wrap gap-2">
          <span className="text-xs text-muted-foreground self-center mr-2">Filtros:</span>
          {[
            { k: 'status', v: null, label: 'Todos' },
            { k: 'status', v: 'aberto', label: 'Abertos' },
            { k: 'status', v: 'parcial', label: 'Parciais' },
            { k: 'status', v: 'quitado', label: 'Quitados' },
          ].map((f) => (
            <Button
              key={`${f.k}-${f.v}`}
              size="sm"
              variant={filtros.status === f.v ? 'default' : 'outline'}
              onClick={() => filtrar('status', f.v)}
            >
              {f.label}
            </Button>
          ))}
          <span className="self-center mx-2 text-muted-foreground">·</span>
          {[
            { v: null, label: 'Qualquer venc.' },
            { v: 'hoje', label: 'Hoje' },
            { v: 'semana', label: 'Próx. 7 dias' },
            { v: 'atrasado', label: 'Atrasados' },
          ].map((f) => (
            <Button
              key={`venc-${f.v}`}
              size="sm"
              variant={filtros.vence_em === f.v ? 'default' : 'outline'}
              onClick={() => filtrar('vence_em', f.v)}
            >
              {f.label}
            </Button>
          ))}
        </div>

        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Nº</th>
                <th className="px-4 py-2 font-medium">Cliente</th>
                <th className="px-4 py-2 font-medium">Vencimento</th>
                <th className="px-4 py-2 font-medium text-right">Valor aberto</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Boleto</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {titulos.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                    Nenhum título encontrado com esses filtros.
                  </td>
                </tr>
              )}
              {titulos.map((t) => (
                <tr key={t.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-3 font-mono text-xs">{t.numero}</td>
                  <td className="px-4 py-3">
                    <div className="font-medium">{t.cliente_descricao ?? '(sem cliente)'}</div>
                    <div className="text-xs text-muted-foreground">
                      {t.origem === 'venda' && t.origem_id ? `Venda #${t.origem_id}` : t.origem}
                    </div>
                  </td>
                  <td className="px-4 py-3">{formatDate(t.vencimento)}</td>
                  <td className="px-4 py-3 text-right font-medium">{formatBRL(t.valor_aberto)}</td>
                  <td className="px-4 py-3">
                    <StatusPill status={t.status} />
                  </td>
                  <td className="px-4 py-3">
                    {t.boleto ? (
                      <span className="text-xs">
                        <span className="font-mono">{t.boleto.nosso_numero}</span>
                        <div className="text-muted-foreground">{t.boleto.status}</div>
                      </span>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {t.status === 'aberto' && !t.boleto && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => emitirBoleto(t)}
                        disabled={emitirForm.processing}
                      >
                        <Receipt className="h-4 w-4 mr-1" />
                        Emitir boleto
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <Card>
          <CardContent className="pt-6 text-xs text-muted-foreground">
            Mostrando até 100 títulos. Use os filtros acima pra refinar. Para emitir boleto, configure a conta bancária em{' '}
            <a href="/financeiro/contas-bancarias" className="underline">/financeiro/contas-bancarias</a>.
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Index.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
export default Index;
