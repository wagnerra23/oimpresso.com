// @docvault
//   tela: /essentials/todo/create
//   module: Essentials
//   status: implementada
//   stories: US-ESSE-001
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/TodoCreateTest

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

interface Props {
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

export default function TodoCreate({ users, statuses, priorities, can }: Props) {
  const form = useForm<FormData>({
    task: '',
    users: [],
    priority: '',
    status: 'new',
    date: new Date().toISOString().slice(0, 10),
    end_date: '',
    estimated_hours: '',
    description: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/essentials/todo', {
      onSuccess: () => toast.success('Tarefa criada.'),
      onError: () => toast.error('Verifique os campos destacados.'),
    });
  };

  const toggleUser = (id: number) => {
    const has = form.data.users.includes(id);
    form.setData('users', has ? form.data.users.filter((u) => u !== id) : [...form.data.users, id]);
  };

  return (
    <AppShell
      title="Nova tarefa"
      breadcrumb={[
        { label: 'Essentials' },
        { label: 'Tarefas', href: '/essentials/todo' },
        { label: 'Nova' },
      ]}
    >
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <ClipboardList size={22} /> Nova tarefa
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Após criar, você pode adicionar comentários e anexos na tela de detalhe.
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href="/essentials/todo"><ArrowLeft size={14} className="mr-1.5" /> Voltar</Link>
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
                  placeholder="Ex: Enviar relatório mensal ao financeiro"
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
                    {form.data.users.length} usuário(s) selecionado(s). Se nenhum for escolhido, a tarefa fica atribuída só a você.
                  </p>
                  {form.errors.users && <p className="text-xs text-destructive">{form.errors.users}</p>}
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
                  placeholder="Ex: 4"
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
                  placeholder="Detalhe o que precisa ser feito, critérios de aceite, contexto…"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" asChild>
                  <Link href="/essentials/todo">Cancelar</Link>
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
