// @docvault
//   tela: /ponto/aprovacoes
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-001
//   rules: R-PONT-001, R-PONT-004
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/AprovacoesIndexTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Check, CheckCheck, X } from 'lucide-react';
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
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import PageFilters from '@/Components/shared/PageFilters';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';
import BulkActionBar from '@/Components/shared/BulkActionBar';

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

const estadoOrder = ['PENDENTE', 'APROVADA', 'REJEITADA', 'APLICADA', 'RASCUNHO', 'CANCELADA'] as const;

const estadoIconMap: Record<string, string> = {
  PENDENTE:  'clock',
  APROVADA:  'check',
  REJEITADA: 'x',
  APLICADA:  'check-check',
  RASCUNHO:  'file-edit',
  CANCELADA: 'x-circle',
};

const estadoToneMap: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'info'> = {
  PENDENTE:  'warning',
  APROVADA:  'success',
  REJEITADA: 'danger',
  APLICADA:  'info',
  RASCUNHO:  'default',
  CANCELADA: 'default',
};

const estadoLabelMap: Record<string, string> = {
  PENDENTE:  'Pendente',
  APROVADA:  'Aprovada',
  REJEITADA: 'Rejeitada',
  APLICADA:  'Aplicada',
  RASCUNHO:  'Rascunho',
  CANCELADA: 'Cancelada',
};

export default function AprovacoesIndex({ aprovacoes, filtros, contagens, tipos }: Props) {
  const [approveTarget, setApproveTarget] = useState<Aprovacao | null>(null);
  const [rejectTarget, setRejectTarget] = useState<Aprovacao | null>(null);
  const [rejectMotivo, setRejectMotivo] = useState('');
  const [processing, setProcessing] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Array<number | string>>([]);

  const filterChange = (key: string, value: string) => {
    const params: Record<string, string> = {};
    if (filtros.estado && key !== 'estado') params.estado = filtros.estado;
    if (filtros.tipo && key !== 'tipo') params.tipo = filtros.tipo;
    if (filtros.prioridade && key !== 'prioridade') params.prioridade = filtros.prioridade;
    if (value) params[key] = value;
    router.get('/ponto/aprovacoes', params, { preserveState: true, preserveScroll: true });
  };

  const resetFilters = () => {
    router.get('/ponto/aprovacoes', {}, { preserveScroll: true });
  };

  const activeChips = [
    ...(filtros.tipo
      ? [{
          label: `Tipo: ${tipoLabel(filtros.tipo, tipos)}`,
          onRemove: () => filterChange('tipo', ''),
        }]
      : []),
    ...(filtros.prioridade
      ? [{
          label: `Prioridade: ${filtros.prioridade === 'URGENTE' ? 'Urgente' : 'Normal'}`,
          onRemove: () => filterChange('prioridade', ''),
        }]
      : []),
    ...(filtros.estado
      ? [{
          label: `Estado: ${estadoLabelMap[filtros.estado] ?? filtros.estado}`,
          onRemove: () => filterChange('estado', ''),
        }]
      : []),
  ];

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

  const handleBulkApprove = () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`Aprovar ${selectedIds.length} intercorrência(s) em lote?`)) return;
    setProcessing(true);
    router.post(
      '/ponto/aprovacoes/lote',
      { ids: selectedIds },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`${selectedIds.length} intercorrência(s) aprovadas em lote.`);
          setSelectedIds([]);
        },
        onError: () => toast.error('Falha no lote.'),
        onFinish: () => setProcessing(false),
      },
    );
  };

  const pendentes = aprovacoes.data.filter((a) => a.estado === 'PENDENTE');
  const allPendentesSelected =
    pendentes.length > 0 && pendentes.every((a) => selectedIds.includes(a.id));
  const toggleAllPendentes = () => {
    if (allPendentesSelected) {
      setSelectedIds((prev) => prev.filter((id) => !pendentes.some((p) => p.id === id)));
    } else {
      setSelectedIds((prev) => [
        ...prev,
        ...pendentes.map((p) => p.id).filter((id) => !prev.includes(id)),
      ]);
    }
  };

  return (
    <>
      <Head title="Aprovações · Ponto WR2" />
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <PageHeader
          icon="check-check"
          title="Aprovações"
          description="Fila de intercorrências aguardando decisão do RH / gestor."
        />

        {/* KPIs por estado — cada card filtra quando clicado */}
        <KpiGrid cols={6}>
          {estadoOrder.map((estado) => {
            const active = filtros.estado === estado;
            return (
              <KpiCard
                key={estado}
                label={estadoLabelMap[estado]}
                value={contagens[estado] ?? 0}
                icon={estadoIconMap[estado]}
                tone={estadoToneMap[estado]}
                size="compact"
                selected={active}
                onClick={() => filterChange('estado', active ? '' : estado)}
              />
            );
          })}
        </KpiGrid>

        {/* Filtros adicionais */}
        <PageFilters activeChips={activeChips} onReset={activeChips.length > 0 ? resetFilters : undefined} cols={2}>
          <div>
            <label className="text-xs font-medium text-muted-foreground mb-1 block">Tipo</label>
            <Select
              value={filtros.tipo ?? 'ALL'}
              onValueChange={(v) => filterChange('tipo', v === 'ALL' ? '' : v)}
            >
              <SelectTrigger>
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
          </div>
          <div>
            <label className="text-xs font-medium text-muted-foreground mb-1 block">Prioridade</label>
            <Select
              value={filtros.prioridade ?? 'ALL'}
              onValueChange={(v) => filterChange('prioridade', v === 'ALL' ? '' : v)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Prioridade" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="ALL">Todas</SelectItem>
                <SelectItem value="URGENTE">Urgente</SelectItem>
                <SelectItem value="NORMAL">Normal</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </PageFilters>

        {/* Tabela */}
        <Card>
          <CardContent className="p-0">
            {aprovacoes.data.length === 0 ? (
              <EmptyState
                icon={activeChips.length > 0 || filtros.estado ? 'search-x' : 'inbox'}
                title={activeChips.length > 0 || filtros.estado ? 'Nenhum resultado' : 'Caixa vazia'}
                description={
                  activeChips.length > 0 || filtros.estado
                    ? 'Nenhuma intercorrência com esses filtros. Tente limpar os filtros ou ampliar a busca.'
                    : 'Nenhuma intercorrência foi registrada. Quando os colaboradores submeterem solicitações, elas aparecerão aqui.'
                }
                variant={activeChips.length > 0 || filtros.estado ? 'search' : 'default'}
                action={
                  activeChips.length > 0 || filtros.estado ? (
                    <Button variant="outline" size="sm" onClick={resetFilters}>
                      Limpar filtros
                    </Button>
                  ) : undefined
                }
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      {pendentes.length > 0 && (
                        <th className="w-10 p-3">
                          <input
                            type="checkbox"
                            checked={allPendentesSelected}
                            onChange={toggleAllPendentes}
                            aria-label="Selecionar todas as pendentes"
                            className="h-4 w-4"
                          />
                        </th>
                      )}
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
                      const canActOn = a.estado === 'PENDENTE';
                      const isSelected = selectedIds.includes(a.id);
                      return (
                        <tr key={a.id} className="hover:bg-accent/30 transition-colors">
                          {pendentes.length > 0 && (
                            <td className="p-3">
                              {canActOn ? (
                                <input
                                  type="checkbox"
                                  checked={isSelected}
                                  onChange={(e) =>
                                    setSelectedIds((prev) =>
                                      e.target.checked
                                        ? [...prev, a.id]
                                        : prev.filter((id) => id !== a.id),
                                    )
                                  }
                                  aria-label={`Selecionar ${a.codigo}`}
                                  className="h-4 w-4"
                                />
                              ) : null}
                            </td>
                          )}
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
                            <StatusBadge kind="intercorrencia" value={a.estado} />
                          </td>
                          <td className="p-3">
                            <StatusBadge kind="prioridade" value={a.prioridade} />
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

      {/* ==================== BulkActionBar ==================== */}
      <BulkActionBar selectedCount={selectedIds.length} onClear={() => setSelectedIds([])}>
        <Button
          size="sm"
          onClick={handleBulkApprove}
          disabled={processing}
          className="bg-emerald-600 hover:bg-emerald-700"
        >
          <CheckCheck size={14} className="mr-1" />
          Aprovar selecionadas
        </Button>
      </BulkActionBar>

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
    </>
  );
}

AprovacoesIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Aprovações' }]}>
    {page}
  </AppShell>
);

function tipoLabel(value: string, tipos: Array<{ value: string; label: string }>): string {
  return tipos.find((t) => t.value === value)?.label ?? value;
}
