// @docvault
//   tela: /ponto/aprovacoes
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-001
//   rules: R-PONT-001, R-PONT-004
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/AprovacoesIndexTest

import AppShell from '@/Layouts/AppShell';
import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Check,
  CheckCheck,
  Clock,
  FileCheck,
  Inbox,
  Search,
  Settings2,
  X,
  XCircle,
} from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/Lib/utils';

interface Aprovacao {
  id: number | string;
  codigo: string;
  tipo: string;
  estado: string;
  prioridade: string;
  data: string | null;
  dia_todo: boolean;
  intervalo_inicio: string | null;
  intervalo_fim: string | null;
  justificativa: string;
  impacta_apuracao: boolean;
  descontar_banco_horas: boolean;
  created_at_human: string | null;
  created_at: string | null;
  colaborador: { id: number | null; matricula: string | null; nome: string };
  solicitante: { nome: string };
}

interface PaginatedAprovacoes {
  data: Aprovacao[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  aprovacoes: PaginatedAprovacoes;
  filtros: { estado: string | null; tipo: string | null; prioridade: string | null };
  contagens: Record<string, number>;
  tipos: Array<{ value: string; label: string }>;
}

const estadoConfig: Record<
  string,
  { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: React.ComponentType<{ size?: number; className?: string }> }
> = {
  RASCUNHO:  { label: 'Rascunho',  variant: 'outline',     icon: FileCheck },
  PENDENTE:  { label: 'Pendente',  variant: 'default',     icon: Clock },
  APROVADA:  { label: 'Aprovada',  variant: 'default',     icon: Check },
  REJEITADA: { label: 'Rejeitada', variant: 'destructive', icon: X },
  APLICADA:  { label: 'Aplicada',  variant: 'secondary',   icon: CheckCheck },
  CANCELADA: { label: 'Cancelada', variant: 'outline',     icon: XCircle },
};

const prioridadeConfig: Record<string, { label: string; variant: 'default' | 'destructive' | 'secondary' }> = {
  URGENTE: { label: 'Urgente', variant: 'destructive' },
  NORMAL:  { label: 'Normal',  variant: 'secondary' },
};

export default function AprovacoesIndex({ aprovacoes, filtros, contagens, tipos }: Props) {
  const [approveTarget, setApproveTarget] = useState<Aprovacao | null>(null);
  const [rejectTarget, setRejectTarget] = useState<Aprovacao | null>(null);
  const [rejectMotivo, setRejectMotivo] = useState('');
  const [processing, setProcessing] = useState(false);

  const filterChange = (key: string, value: string) => {
    const params: Record<string, string> = {};
    if (filtros.estado && key !== 'estado') params.estado = filtros.estado;
    if (filtros.tipo && key !== 'tipo') params.tipo = filtros.tipo;
    if (filtros.prioridade && key !== 'prioridade') params.prioridade = filtros.prioridade;
    if (value) params[key] = value;
    router.get('/ponto/aprovacoes', params, { preserveState: true, preserveScroll: true });
  };

  const handleAprovar = () => {
    if (!approveTarget) return;
    setProcessing(true);
    router.post(
      `/ponto/aprovacoes/${approveTarget.id}/aprovar`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Intercorrência ${approveTarget.codigo} aprovada.`);
          setApproveTarget(null);
        },
        onError: () => toast.error('Falha ao aprovar.'),
        onFinish: () => setProcessing(false),
      },
    );
  };

  const handleRejeitar = (e: FormEvent) => {
    e.preventDefault();
    if (!rejectTarget) return;
    if (rejectMotivo.trim().length < 5) {
      toast.error('Digite um motivo (min 5 caracteres).');
      return;
    }
    setProcessing(true);
    router.post(
      `/ponto/aprovacoes/${rejectTarget.id}/rejeitar`,
      { motivo: rejectMotivo },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Intercorrência ${rejectTarget.codigo} rejeitada.`);
          setRejectTarget(null);
          setRejectMotivo('');
        },
        onError: () => toast.error('Falha ao rejeitar.'),
        onFinish: () => setProcessing(false),
      },
    );
  };

  return (
    <AppShell
      title="Aprovações · Ponto WR2"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Aprovações' }]}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <CheckCheck size={22} /> Aprovações
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Fila de intercorrências aguardando decisão do RH / gestor.
          </p>
        </header>

        {/* KPIs por estado */}
        <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
          {(['PENDENTE', 'APROVADA', 'REJEITADA', 'APLICADA', 'RASCUNHO', 'CANCELADA'] as const).map(
            (estado) => {
              const cfg = estadoConfig[estado];
              const IconEl = cfg.icon;
              const active = filtros.estado === estado;
              return (
                <button
                  key={estado}
                  onClick={() => filterChange('estado', active ? '' : estado)}
                  className={cn(
                    'rounded-lg border p-3 text-left transition-colors',
                    active
                      ? 'border-primary bg-primary/10'
                      : 'border-border bg-card hover:border-primary/40',
                  )}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] uppercase tracking-wide text-muted-foreground">
                      {cfg.label}
                    </span>
                    <IconEl size={12} className="text-muted-foreground" />
                  </div>
                  <p className="text-xl font-bold mt-1">{contagens[estado] ?? 0}</p>
                </button>
              );
            },
          )}
        </div>

        {/* Filtros adicionais */}
        <Card>
          <CardContent className="pt-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-1 flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5">
                <Search size={14} className="text-muted-foreground" />
                <Input
                  disabled
                  placeholder="Busca por colaborador/justificativa (em breve)"
                  className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                />
              </div>

              <div className="flex gap-2">
                <Select
                  value={filtros.tipo ?? 'ALL'}
                  onValueChange={(v) => filterChange('tipo', v === 'ALL' ? '' : v)}
                >
                  <SelectTrigger className="w-48">
                    <SelectValue placeholder="Tipo" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ALL">Todos os tipos</SelectItem>
                    {tipos.map((t) => (
                      <SelectItem key={t.value} value={t.value}>
                        {t.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Select
                  value={filtros.prioridade ?? 'ALL'}
                  onValueChange={(v) => filterChange('prioridade', v === 'ALL' ? '' : v)}
                >
                  <SelectTrigger className="w-36">
                    <SelectValue placeholder="Prioridade" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ALL">Todas</SelectItem>
                    <SelectItem value="URGENTE">Urgente</SelectItem>
                    <SelectItem value="NORMAL">Normal</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tabela */}
        <Card>
          <CardContent className="p-0">
            {aprovacoes.data.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground">
                <Inbox size={32} className="mx-auto mb-2 opacity-50" />
                <p className="text-sm">Nenhuma intercorrência com esses filtros.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Colaborador</th>
                      <th className="text-left p-3 font-medium">Tipo</th>
                      <th className="text-left p-3 font-medium">Data</th>
                      <th className="text-left p-3 font-medium">Estado</th>
                      <th className="text-left p-3 font-medium">Prioridade</th>
                      <th className="text-left p-3 font-medium">Criada</th>
                      <th className="text-right p-3 font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {aprovacoes.data.map((a) => {
                      const estadoCfg = estadoConfig[a.estado] ?? estadoConfig.PENDENTE;
                      const prioCfg = prioridadeConfig[a.prioridade] ?? prioridadeConfig.NORMAL;
                      const IconEstado = estadoCfg.icon;
                      const canActOn = a.estado === 'PENDENTE';
                      return (
                        <tr key={a.id} className="hover:bg-accent/30 transition-colors">
                          <td className="p-3">
                            <div className="font-medium">{a.colaborador.nome}</div>
                            {a.colaborador.matricula && (
                              <div className="text-xs text-muted-foreground">
                                mat. {a.colaborador.matricula}
                              </div>
                            )}
                          </td>
                          <td className="p-3 text-xs">
                            <div>{tipoLabel(a.tipo, tipos)}</div>
                            {a.impacta_apuracao && (
                              <div className="text-[10px] text-amber-600 dark:text-amber-400 mt-0.5">
                                impacta apuração
                              </div>
                            )}
                          </td>
                          <td className="p-3 text-xs">
                            {a.data ?? '—'}
                            {!a.dia_todo && a.intervalo_inicio && (
                              <div className="text-[10px] text-muted-foreground">
                                {a.intervalo_inicio} – {a.intervalo_fim}
                              </div>
                            )}
                            {a.dia_todo && (
                              <div className="text-[10px] text-muted-foreground">dia todo</div>
                            )}
                          </td>
                          <td className="p-3">
                            <Badge variant={estadoCfg.variant} className="gap-1 text-[10px]">
                              <IconEstado size={10} />
                              {estadoCfg.label}
                            </Badge>
                          </td>
                          <td className="p-3">
                            <Badge variant={prioCfg.variant} className="text-[10px]">
                              {prioCfg.label}
                            </Badge>
                          </td>
                          <td className="p-3 text-xs text-muted-foreground" title={a.created_at ?? ''}>
                            {a.created_at_human ?? '—'}
                          </td>
                          <td className="p-3 text-right">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="outline" asChild>
                                <Link href={`/ponto/intercorrencias/${a.id}`} className="text-xs">
                                  Ver
                                </Link>
                              </Button>
                              {canActOn && (
                                <>
                                  <Button
                                    size="sm"
                                    variant="default"
                                    onClick={() => setApproveTarget(a)}
                                    className="gap-1 text-xs"
                                  >
                                    <Check size={12} /> Aprovar
                                  </Button>
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setRejectTarget(a)}
                                    className="gap-1 text-xs text-destructive hover:text-destructive"
                                  >
                                    <X size={12} /> Rejeitar
                                  </Button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            {/* Paginação */}
            {aprovacoes.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {aprovacoes.current_page} de {aprovacoes.last_page} · {aprovacoes.total}{' '}
                  item(s)
                </span>
                <div className="flex gap-1">
                  {aprovacoes.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                    >
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* ==================== Dialog: Aprovar ==================== */}
      <AlertDialog open={approveTarget !== null} onOpenChange={(o) => !o && setApproveTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <Check size={16} className="text-emerald-600" /> Aprovar intercorrência
            </AlertDialogTitle>
            <AlertDialogDescription>
              Confirma a aprovação de <strong>{approveTarget?.codigo}</strong> de{' '}
              <strong>{approveTarget?.colaborador.nome}</strong>?
              {approveTarget?.impacta_apuracao && (
                <span className="block mt-2 text-xs text-amber-700 dark:text-amber-400">
                  ⚠️ Esta intercorrência <strong>impacta a apuração</strong> — minutos de trabalho serão ajustados.
                </span>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={processing}>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleAprovar} disabled={processing}>
              {processing ? 'Aprovando…' : 'Aprovar'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ==================== Dialog: Rejeitar (com motivo) ==================== */}
      <Dialog open={rejectTarget !== null} onOpenChange={(o) => !o && (setRejectTarget(null), setRejectMotivo(''))}>
        <DialogContent>
          <form onSubmit={handleRejeitar}>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <X size={16} className="text-destructive" /> Rejeitar intercorrência
              </DialogTitle>
              <DialogDescription>
                <strong>{rejectTarget?.codigo}</strong> de <strong>{rejectTarget?.colaborador.nome}</strong>.
                Informe o motivo da rejeição — o colaborador será notificado.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-2 py-4">
              <Label htmlFor="motivo">Motivo da rejeição *</Label>
              <Textarea
                id="motivo"
                value={rejectMotivo}
                onChange={(e) => setRejectMotivo(e.target.value)}
                placeholder="Ex.: Anexo ilegível, pedido duplicado, etc."
                rows={4}
                required
                minLength={5}
                maxLength={500}
              />
              <p className="text-[10px] text-muted-foreground">
                {rejectMotivo.length}/500 · mínimo 5 caracteres
              </p>
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => (setRejectTarget(null), setRejectMotivo(''))}
                disabled={processing}
              >
                Cancelar
              </Button>
              <Button type="submit" variant="destructive" disabled={processing}>
                {processing ? 'Rejeitando…' : 'Rejeitar'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppShell>
  );
}

function tipoLabel(value: string, tipos: Array<{ value: string; label: string }>): string {
  return tipos.find((t) => t.value === value)?.label ?? value;
}
