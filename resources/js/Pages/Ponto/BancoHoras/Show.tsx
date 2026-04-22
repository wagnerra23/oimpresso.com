import AppShell from '@/Layouts/AppShell';
import { router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, Info, PiggyBank, Save } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { cn, formatMinutes } from '@/Lib/utils';

interface Saldo {
  colaborador_id: number;
  matricula: string | null;
  nome: string;
  saldo_minutos: number;
}

interface Movimento {
  id: number;
  minutos: number;
  tipo: string;
  data_referencia: string | null;
  observacao: string | null;
  created_at: string | null;
  created_at_human: string | null;
}

interface Paginated {
  data: Movimento[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  saldo: Saldo;
  movimentos: Paginated;
}

const tipoVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  CREDITO_HE:    'default',
  DEBITO_FOLGA:  'destructive',
  AJUSTE_MANUAL: 'secondary',
  EXPIRACAO:     'outline',
  PAGAMENTO:     'outline',
};

export default function BancoHorasShow({ saldo, movimentos }: Props) {
  const form = useForm({
    minutos: 0,
    observacao: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (form.data.minutos === 0) {
      toast.error('Informe minutos diferente de zero.');
      return;
    }
    if (form.data.observacao.trim().length < 5) {
      toast.error('Observação precisa ter pelo menos 5 caracteres.');
      return;
    }
    form.post(`/ponto/banco-horas/${saldo.colaborador_id}/ajuste`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Ajuste registrado no ledger.');
        form.reset();
      },
      onError: () => toast.error('Falha ao ajustar.'),
    });
  };

  return (
    <AppShell
      title={`BH · ${saldo.nome}`}
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Banco de Horas', href: '/ponto/banco-horas' },
        { label: saldo.nome },
      ]}
    >
      <div className="mx-auto max-w-5xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <PiggyBank size={22} /> Banco de Horas — {saldo.nome}
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              {saldo.matricula && `Matrícula ${saldo.matricula} · `}
              Ledger append-only — cada ajuste é um novo movimento.
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={() => router.visit('/ponto/banco-horas')}>
            <ArrowLeft size={14} className="mr-1.5" /> Voltar
          </Button>
        </header>

        <Card>
          <CardContent className="pt-6 pb-6 text-center">
            <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Saldo atual</p>
            <p className={cn(
              'text-4xl font-bold font-mono mt-1',
              saldo.saldo_minutos > 0 && 'text-emerald-600 dark:text-emerald-400',
              saldo.saldo_minutos < 0 && 'text-red-600 dark:text-red-400',
            )}>
              {formatMinutes(saldo.saldo_minutos)}
            </p>
          </CardContent>
        </Card>

        {/* Ajuste manual */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Ajuste manual</CardTitle>
            <CardDescription className="text-xs">
              Positivo credita, negativo debita. Observação obrigatória — é registrada no ledger e auditada.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <Label htmlFor="minutos">Minutos (±)</Label>
                  <Input
                    id="minutos"
                    type="number"
                    value={form.data.minutos}
                    onChange={(e) => form.setData('minutos', parseInt(e.target.value || '0', 10))}
                    placeholder="ex: 30 ou -60"
                    className="font-mono"
                  />
                  {form.errors.minutos && <p className="text-xs text-destructive mt-1">{form.errors.minutos}</p>}
                </div>
                <div className="md:col-span-2">
                  <Label htmlFor="obs">Observação</Label>
                  <Input
                    id="obs"
                    value={form.data.observacao}
                    onChange={(e) => form.setData('observacao', e.target.value)}
                    placeholder="Motivo do ajuste (mín 5 caracteres)"
                  />
                  {form.errors.observacao && <p className="text-xs text-destructive mt-1">{form.errors.observacao}</p>}
                </div>
              </div>
              <div className="flex justify-end">
                <Button type="submit" disabled={form.processing} className="gap-1.5">
                  <Save size={14} />
                  {form.processing ? 'Salvando…' : 'Registrar ajuste'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Alert>
          <Info size={14} />
          <AlertTitle>Append-only</AlertTitle>
          <AlertDescription className="text-xs">
            Este ledger nunca atualiza/remove movimentos anteriores. O "saldo" é calculado
            pela soma de todos os movimentos. Qualquer correção vira um novo movimento reverso.
          </AlertDescription>
        </Alert>

        {/* Histórico */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Histórico de movimentos</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {movimentos.data.length === 0 ? (
              <div className="p-8 text-center text-sm text-muted-foreground">Sem movimentos.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                    <tr>
                      <th className="text-left p-2 font-medium">Data ref.</th>
                      <th className="text-left p-2 font-medium">Tipo</th>
                      <th className="text-right p-2 font-medium">Minutos</th>
                      <th className="text-left p-2 font-medium">Observação</th>
                      <th className="text-left p-2 font-medium">Registrado</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {movimentos.data.map((m) => (
                      <tr key={m.id} className="hover:bg-accent/30">
                        <td className="p-2">{m.data_referencia ?? '—'}</td>
                        <td className="p-2">
                          <Badge variant={tipoVariant[m.tipo] ?? 'outline'} className="text-[10px]">
                            {m.tipo}
                          </Badge>
                        </td>
                        <td className={cn(
                          'p-2 text-right font-mono font-semibold',
                          m.minutos > 0 && 'text-emerald-600 dark:text-emerald-400',
                          m.minutos < 0 && 'text-red-600 dark:text-red-400',
                        )}>
                          {formatMinutes(m.minutos)}
                        </td>
                        <td className="p-2 text-muted-foreground max-w-xs truncate" title={m.observacao ?? ''}>
                          {m.observacao ?? '—'}
                        </td>
                        <td className="p-2 text-muted-foreground" title={m.created_at ?? ''}>
                          {m.created_at_human ?? '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
