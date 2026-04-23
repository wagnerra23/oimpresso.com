// @docvault
//   tela: /essentials/todo/edit
//   module: Essentials
//   status: implementada
//   stories: US-ESSE-001
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/TodoEditTest

import AppShell from '@/Layouts/AppShell';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, ClipboardList, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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

interface Option { value: string; label: string; }
interface UserOption { id: number; label: string; }

interface TodoDetail {
  id: number;
  task_id: string | null;
  task: string;
  description: string | null;
  status: string | null;
  priority: string | null;
  date: string | null;
  end_date: string | null;
  estimated_hours: string | null;
  assigned_user_ids: number[];
}

interface Props {
  todo: TodoDetail;
  users: UserOption[];
  statuses: Option[];
  priorities: Option[];
  can: { add: boolean; edit: boolean; delete: boolean; assign: boolean };
}

type FormData = {
  task: string;
  users: number[];
  priority: string;
  status: string;
  date: string;
  end_date: string;
  estimated_hours: string;
  description: string;
};

const toDateInput = (value: string | null): string => {
  if (!value) return '';
  // Recebe "Y-m-d H:i" do backend — pega só a parte Y-m-d
  return value.slice(0, 10);
};

export default function TodoEdit({ todo, users, statuses, priorities, can }: Props) {
  const form = useForm<FormData>({
    task: todo.task,
    users: todo.assigned_user_ids,
    priority: todo.priority ?? '',
    status: todo.status ?? 'new',
    date: toDateInput(todo.date),
    end_date: toDateInput(todo.end_date),
    estimated_hours: todo.estimated_hours ?? '',
    description: todo.description ?? '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.put(`/essentials/todo/${todo.id}`, {
      onSuccess: () => toast.success('Tarefa atualizada.'),
      onError: () => toast.error('Verifique os campos destacados.'),
    });
  };

  const toggleUser = (id: number) => {
    const has = form.data.users.includes(id);
    form.setData('users', has ? form.data.users.filter((u) => u !== id) : [...form.data.users, id]);
  };

  return (
    <AppShell
      title={`Editar tarefa ${todo.task_id ?? ''}`}
      breadcrumb={[
        { label: 'Essentials' },
        { label: 'Tarefas', href: '/essentials/todo' },
        { label: todo.task_id ?? '#' + todo.id, href: `/essentials/todo/${todo.id}` },
        { label: 'Editar' },
      ]}
    >
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <ClipboardList size={22} /> Editar tarefa
              <span className="text-muted-foreground font-mono text-sm">{todo.task_id}</span>
            </h1>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href={`/essentials/todo/${todo.id}`}>
              <ArrowLeft size={14} className="mr-1.5" /> Voltar
            </Link>
          </Button>
        </header>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Detalhes</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="space-y-1">
                <Label htmlFor="task">Tarefa *</Label>
                <Input
                  id="task"
                  value={form.data.task}
                  onChange={(e) => form.setData('task', e.target.value)}
                  required
                />
                {form.errors.task && <p className="text-xs text-destructive">{form.errors.task}</p>}
              </div>

              {can.assign && users.length > 0 && (
                <div className="space-y-1">
                  <Label>Atribuir a *</Label>
                  <div className="flex flex-wrap gap-2 p-2 border border-border rounded-md max-h-48 overflow-y-auto">
                    {users.map((u) => {
                      const selected = form.data.users.includes(u.id);
                      return (
                        <button
                          key={u.id}
                          type="button"
                          onClick={() => toggleUser(u.id)}
                          className={`px-2.5 py-1 rounded text-xs border transition ${
                            selected
                              ? 'bg-primary text-primary-foreground border-primary'
                              : 'bg-background hover:bg-accent border-border'
                          }`}
                        >
                          {u.label}
                        </button>
                      );
                    })}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {form.data.users.length} usuário(s) selecionado(s).
                  </p>
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <Label htmlFor="priority">Prioridade</Label>
                  <Select
                    value={form.data.priority}
                    onValueChange={(v) => form.setData('priority', v)}
                  >
                    <SelectTrigger id="priority">
                      <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                      {priorities.map((p) => (
                        <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <Label htmlFor="status">Status</Label>
                  <Select
                    value={form.data.status}
                    onValueChange={(v) => form.setData('status', v)}
                  >
                    <SelectTrigger id="status">
                      <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                      {statuses.map((s) => (
                        <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <Label htmlFor="date">Início *</Label>
                  <Input
                    id="date"
                    type="date"
                    value={form.data.date}
                    onChange={(e) => form.setData('date', e.target.value)}
                    required
                  />
                  {form.errors.date && <p className="text-xs text-destructive">{form.errors.date}</p>}
                </div>
                <div className="space-y-1">
                  <Label htmlFor="end_date">Previsão de término</Label>
                  <Input
                    id="end_date"
                    type="date"
                    value={form.data.end_date}
                    onChange={(e) => form.setData('end_date', e.target.value)}
                  />
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="estimated_hours">Horas estimadas</Label>
                <Input
                  id="estimated_hours"
                  value={form.data.estimated_hours}
                  onChange={(e) => form.setData('estimated_hours', e.target.value)}
                  className="max-w-xs"
                />
              </div>

              <div className="space-y-1">
                <Label htmlFor="description">Descrição</Label>
                <Textarea
                  id="description"
                  rows={5}
                  value={form.data.description}
                  onChange={(e) => form.setData('description', e.target.value)}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" asChild>
                  <Link href={`/essentials/todo/${todo.id}`}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={form.processing} className="gap-1.5">
                  <Save size={14} /> Salvar
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
