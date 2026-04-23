// @docvault
//   tela: /essentials/reminder
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/RemindersIndexTest

import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Bell, Clock, Edit, Plus, Repeat, Trash2 } from 'lucide-react';
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

interface Reminder {
  id: number;
  name: string;
  date: string;
  time: string | null;
  end_time: string | null;
  repeat: string;
}

interface Option { value: string; label: string; }

interface Props {
  reminders: Reminder[];
  repeats: Option[];
}

type FormData = {
  name: string;
  date: string;
  time: string;
  end_time: string;
  repeat: string;
};

const emptyForm: FormData = {
  name: '',
  date: new Date().toISOString().slice(0, 10),
  time: '09:00',
  end_time: '',
  repeat: 'one_time',
};

export default function RemindersIndex({ reminders, repeats }: Props) {
  const [editTarget, setEditTarget] = useState<Reminder | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Reminder | null>(null);

  const form = useForm<FormData>(emptyForm);

  const openCreate = () => {
    form.setData(emptyForm);
    setEditTarget(null);
    setDialogOpen(true);
  };

  const openEdit = (r: Reminder) => {
    form.setData({
      name: r.name,
      date: r.date,
      time: r.time ?? '09:00',
      end_time: r.end_time ?? '',
      repeat: r.repeat,
    });
    setEditTarget(r);
    setDialogOpen(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const url = editTarget ? `/essentials/reminder/${editTarget.id}` : '/essentials/reminder';
    const method = editTarget ? 'put' : 'post';
    form[method](url, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editTarget ? 'Lembrete atualizado.' : 'Lembrete criado.');
        setDialogOpen(false);
      },
      onError: () => toast.error('Verifique os campos.'),
    });
  };

  const confirmDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/essentials/reminder/${deleteTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Lembrete removido.');
        setDeleteTarget(null);
      },
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  const repeatLabel = (value: string) =>
    repeats.find((r) => r.value === value)?.label ?? value;

  return (
    <>
      <Head title="Lembretes" />
      <div className="mx-auto max-w-4xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <Bell size={22} /> Lembretes
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Avisos pessoais com data e repetição (cada usuário vê só os seus).
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus size={14} className="mr-1.5" /> Novo lembrete
          </Button>
        </header>

        <Card>
          <CardContent className="p-0">
            {reminders.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">
                <Bell size={32} className="mx-auto mb-2 opacity-50" />
                Nenhum lembrete cadastrado.
              </div>
            ) : (
              <ul className="divide-y divide-border">
                {reminders.map((r) => (
                  <li key={r.id} className="p-4 flex items-start gap-3 hover:bg-accent/30">
                    <div className="flex-shrink-0 mt-0.5 text-primary">
                      <Bell size={16} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="font-medium truncate">{r.name}</div>
                      <div className="text-xs text-muted-foreground mt-0.5 flex items-center gap-2 flex-wrap">
                        <span className="flex items-center gap-1">
                          <Clock size={10} /> {r.date} {r.time}
                          {r.end_time && ` – ${r.end_time}`}
                        </span>
                        <Badge variant="secondary" className="text-[10px] gap-1">
                          <Repeat size={10} /> {repeatLabel(r.repeat)}
                        </Badge>
                      </div>
                    </div>
                    <div className="flex-shrink-0 flex gap-1">
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => openEdit(r)}>
                        <Edit size={12} />
                      </Button>
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0 text-destructive" onClick={() => setDeleteTarget(r)}>
                        <Trash2 size={12} />
                      </Button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <form onSubmit={submit}>
            <DialogHeader>
              <DialogTitle>{editTarget ? 'Editar lembrete' : 'Novo lembrete'}</DialogTitle>
            </DialogHeader>
            <div className="py-4 space-y-3">
              <div className="space-y-1">
                <Label htmlFor="r-name">Nome *</Label>
                <Input
                  id="r-name"
                  value={form.data.name}
                  onChange={(e) => form.setData('name', e.target.value)}
                  required
                  autoFocus
                />
                {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="r-date">Data *</Label>
                  <Input
                    id="r-date"
                    type="date"
                    value={form.data.date}
                    onChange={(e) => form.setData('date', e.target.value)}
                    required
                  />
                  {form.errors.date && <p className="text-xs text-destructive">{form.errors.date}</p>}
                </div>
                <div className="space-y-1">
                  <Label htmlFor="r-time">Hora *</Label>
                  <Input
                    id="r-time"
                    type="time"
                    value={form.data.time}
                    onChange={(e) => form.setData('time', e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="r-end-time">Fim (opcional)</Label>
                  <Input
                    id="r-end-time"
                    type="time"
                    value={form.data.end_time}
                    onChange={(e) => form.setData('end_time', e.target.value)}
                  />
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="r-repeat">Repetição</Label>
                <Select
                  value={form.data.repeat}
                  onValueChange={(v) => form.setData('repeat', v)}
                >
                  <SelectTrigger id="r-repeat">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {repeats.map((r) => (
                      <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={form.processing}>
                Salvar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover lembrete?</AlertDialogTitle>
            <AlertDialogDescription>
              "{deleteTarget?.name}" será removido permanentemente.
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
    </>
  );
}

RemindersIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[{ label: 'Essentials' }, { label: 'Lembretes' }]}>
    {page}
  </AppShell>
);
