import AppShell from '@/Layouts/AppShell';
import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import {
  ArrowRight,
  ClipboardList,
  Filter,
  Flag,
  Inbox,
  Pencil,
  Plus,
  RefreshCw,
  Trash2,
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

interface TodoRow {
  id: number;
  task_id: string | null;
  task: string;
  status: string | null;
  priority: string | null;
  date: string | null;
  end_date: string | null;
  estimated_hours: string | null;
  assigned_by: string | null;
  users: Array<{ id: number; name: string }>;
  created_at_human: string | null;
  created_by: number | null;
}

interface Paginated {
  data: TodoRow[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Option { value: string; label: string; }
interface UserOption { id: number; label: string; }

interface Filters {
  status: string | null;
  priority: string | null;
  user_id: number | null;
  start_date: string | null;
  end_date: string | null;
}

interface Props {
  todos: Paginated;
  filtros: Filters;
  assignableUsers: UserOption[];
  statuses: Option[];
  priorities: Option[];
  can: { add: boolean; edit: boolean; delete: boolean; assign: boolean };
}

const statusTone: Record<string, string> = {
  new: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
  in_progress: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30',
  on_hold: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30',
  completed: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
};

const priorityTone: Record<string, string> = {
  low: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
  medium: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
  high: 'bg-orange-500/15 text-orange-700 dark:text-orange-300',
  urgent: 'bg-red-500/15 text-red-700 dark:text-red-300',
};

export default function TodoIndex({
  todos,
  filtros,
  assignableUsers,
  statuses,
  priorities,
  can,
}: Props) {
  const [deleteTarget, setDeleteTarget] = useState<TodoRow | null>(null);
  const [statusTarget, setStatusTarget] = useState<TodoRow | null>(null);

  const statusForm = useForm({
    only_status: true as boolean,
    status: '' as string,
  });

  const setFilter = (key: keyof Filters, value: string | number | null) => {
    router.get(
      '/essentials/todo',
      {
        ...filtros,
        [key]: value === 'ALL' || value === '' || value === null ? undefined : value,
      },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  };

  const clearFilters = () => {
    router.get('/essentials/todo', {}, { preserveScroll: true });
  };

  const openStatus = (row: TodoRow) => {
    statusForm.setData({ only_status: true, status: row.status ?? 'new' });
    setStatusTarget(row);
  };

  const submitStatus = (e: FormEvent) => {
    e.preventDefault();
    if (!statusTarget) return;
    statusForm.put(`/essentials/todo/${statusTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Status atualizado.');
        setStatusTarget(null);
      },
      onError: () => toast.error('Falha ao atualizar status.'),
    });
  };

  const confirmDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/essentials/todo/${deleteTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Tarefa removida.');
        setDeleteTarget(null);
      },
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  const activeFilters =
    !!filtros.status ||
    !!filtros.priority ||
    !!filtros.user_id ||
    !!filtros.start_date ||
    !!filtros.end_date;

  const labelFor = (opts: Option[], value: string | null) =>
    value ? opts.find((o) => o.value === value)?.label ?? value : '—';

  return (
    <AppShell
      title="Tarefas"
      breadcrumb={[{ label: 'Essentials' }, { label: 'Tarefas' }]}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <ClipboardList size={22} /> Tarefas (To-Do)
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Organize e acompanhe tarefas atribuídas a você ou à equipe.
            </p>
          </div>
          {can.add && (
            <Button asChild>
              <Link href="/essentials/todo/create">
                <Plus size={14} className="mr-1.5" /> Nova tarefa
              </Link>
            </Button>
          )}
        </header>

        <Card>
          <CardContent className="pt-4">
            <div className="flex items-center gap-2 mb-3 text-sm text-muted-foreground">
              <Filter size={14} />
              <span>Filtros</span>
              {activeFilters && (
                <Button variant="ghost" size="sm" className="h-7 px-2 ml-auto" onClick={clearFilters}>
                  <RefreshCw size={12} className="mr-1" /> Limpar
                </Button>
              )}
            </div>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
              <Select
                value={filtros.status ?? 'ALL'}
                onValueChange={(v) => setFilter('status', v)}
              >
                <SelectTrigger><SelectValue placeholder="Status" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="ALL">Todos os status</SelectItem>
                  {statuses.map((s) => (
                    <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select
                value={filtros.priority ?? 'ALL'}
                onValueChange={(v) => setFilter('priority', v)}
              >
                <SelectTrigger><SelectValue placeholder="Prioridade" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="ALL">Todas as prioridades</SelectItem>
                  {priorities.map((p) => (
                    <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {can.assign && assignableUsers.length > 0 && (
                <Select
                  value={filtros.user_id ? String(filtros.user_id) : 'ALL'}
                  onValueChange={(v) => setFilter('user_id', v === 'ALL' ? null : Number(v))}
                >
                  <SelectTrigger><SelectValue placeholder="Atribuído a" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ALL">Todos os usuários</SelectItem>
                    {assignableUsers.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>{u.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              <div className="space-y-1">
                <Label htmlFor="start_date" className="text-xs text-muted-foreground">De</Label>
                <Input
                  id="start_date"
                  type="date"
                  value={filtros.start_date ?? ''}
                  onChange={(e) => setFilter('start_date', e.target.value || null)}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="end_date" className="text-xs text-muted-foreground">Até</Label>
                <Input
                  id="end_date"
                  type="date"
                  value={filtros.end_date ?? ''}
                  onChange={(e) => setFilter('end_date', e.target.value || null)}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-0">
            {todos.data.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground">
                <Inbox size={32} className="mx-auto mb-2 opacity-50" />
                <p className="text-sm">Nenhuma tarefa com esses filtros.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Código</th>
                      <th className="text-left p-3 font-medium">Tarefa</th>
                      <th className="text-left p-3 font-medium">Status</th>
                      <th className="text-left p-3 font-medium">Prioridade</th>
                      <th className="text-left p-3 font-medium">Início</th>
                      <th className="text-left p-3 font-medium">Fim</th>
                      <th className="text-left p-3 font-medium">Atribuído a</th>
                      <th className="text-right p-3 font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {todos.data.map((t) => (
                      <tr key={t.id} className="hover:bg-accent/30">
                        <td className="p-3 font-mono text-xs">{t.task_id ?? '—'}</td>
                        <td className="p-3">
                          <Link href={`/essentials/todo/${t.id}`} className="font-medium hover:underline">
                            {t.task}
                          </Link>
                          {t.created_at_human && (
                            <div className="text-[10px] text-muted-foreground">
                              criada {t.created_at_human}
                            </div>
                          )}
                        </td>
                        <td className="p-3">
                          {t.status ? (
                            <button
                              type="button"
                              onClick={() => openStatus(t)}
                              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[10px] font-medium hover:opacity-80 ${statusTone[t.status] ?? 'bg-muted'}`}
                            >
                              {labelFor(statuses, t.status)}
                            </button>
                          ) : (
                            <span className="text-muted-foreground text-xs">—</span>
                          )}
                        </td>
                        <td className="p-3">
                          {t.priority ? (
                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium ${priorityTone[t.priority] ?? 'bg-muted'}`}>
                              <Flag size={10} /> {labelFor(priorities, t.priority)}
                            </span>
                          ) : (
                            <span className="text-muted-foreground text-xs">—</span>
                          )}
                        </td>
                        <td className="p-3 text-xs">{t.date ?? '—'}</td>
                        <td className="p-3 text-xs">{t.end_date ?? '—'}</td>
                        <td className="p-3 text-xs">
                          {t.users.length === 0 ? (
                            <span className="text-muted-foreground">—</span>
                          ) : (
                            <div className="flex flex-wrap gap-1">
                              {t.users.slice(0, 2).map((u) => (
                                <Badge key={u.id} variant="secondary" className="text-[10px]">{u.name}</Badge>
                              ))}
                              {t.users.length > 2 && (
                                <Badge variant="outline" className="text-[10px]">+{t.users.length - 2}</Badge>
                              )}
                            </div>
                          )}
                        </td>
                        <td className="p-3 text-right">
                          <div className="flex justify-end gap-1">
                            {can.edit && (
                              <Button size="sm" variant="ghost" asChild>
                                <Link href={`/essentials/todo/${t.id}/edit`} className="text-xs gap-1">
                                  <Pencil size={12} />
                                </Link>
                              </Button>
                            )}
                            <Button size="sm" variant="outline" asChild>
                              <Link href={`/essentials/todo/${t.id}`} className="text-xs gap-1">
                                Ver <ArrowRight size={12} />
                              </Link>
                            </Button>
                            {can.delete && (
                              <Button
                                size="sm"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setDeleteTarget(t)}
                              >
                                <Trash2 size={12} />
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {todos.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {todos.current_page} de {todos.last_page} · {todos.total} item(s)
                </span>
                <div className="flex gap-1">
                  {todos.links.map((link, i) => (
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

      {/* Modal: troca rápida de status */}
      <Dialog open={statusTarget !== null} onOpenChange={(open) => !open && setStatusTarget(null)}>
        <DialogContent>
          <form onSubmit={submitStatus}>
            <DialogHeader>
              <DialogTitle>Atualizar status</DialogTitle>
              <DialogDescription>
                Tarefa <strong>{statusTarget?.task_id ?? '#' + statusTarget?.id}</strong> · {statusTarget?.task}
              </DialogDescription>
            </DialogHeader>
            <div className="py-4 space-y-2">
              <Label htmlFor="new_status">Novo status</Label>
              <Select
                value={statusForm.data.status}
                onValueChange={(v) => statusForm.setData('status', v)}
              >
                <SelectTrigger id="new_status">
                  <SelectValue placeholder="Selecione o status" />
                </SelectTrigger>
                <SelectContent>
                  {statuses.map((s) => (
                    <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {statusForm.errors.status && (
                <p className="text-xs text-destructive">{statusForm.errors.status}</p>
              )}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setStatusTarget(null)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={statusForm.processing}>
                Salvar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Confirm delete */}
      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover tarefa?</AlertDialogTitle>
            <AlertDialogDescription>
              A tarefa <strong>{deleteTarget?.task_id ?? '#' + deleteTarget?.id}</strong> —
              "{deleteTarget?.task}" será excluída definitivamente, junto com seus comentários e anexos.
              Essa ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Remover
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AppShell>
  );
}
