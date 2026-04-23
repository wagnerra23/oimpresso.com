// @docvault
//   tela: /hrm/holiday
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/HolidaysIndexTest

import AppShell from '@/Layouts/AppShell';
import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import {
  CalendarDays,
  Edit,
  Filter,
  MapPin,
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

interface Holiday {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  days: number;
  location_id: number | null;
  location_name: string | null;
  note: string | null;
}

interface LocationOption { id: number; label: string; }

interface Filters {
  location_id: number | null;
  start_date: string | null;
  end_date: string | null;
}

interface Props {
  holidays: Holiday[];
  locations: LocationOption[];
  filtros: Filters;
  can_manage: boolean;
}

type FormData = {
  name: string;
  start_date: string;
  end_date: string;
  location_id: number | null;
  note: string;
};

const emptyForm: FormData = {
  name: '',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  location_id: null,
  note: '',
};

export default function HolidaysIndex({ holidays, locations, filtros, can_manage }: Props) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Holiday | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Holiday | null>(null);

  const form = useForm<FormData>(emptyForm);

  const setFilter = (key: keyof Filters, value: string | number | null) => {
    router.get('/hrm/holiday', {
      ...filtros,
      [key]: value === '' || value === null || value === 'ALL' ? undefined : value,
    }, { preserveState: true, preserveScroll: true, replace: true });
  };

  const clearFilters = () => router.get('/hrm/holiday', {}, { preserveScroll: true });

  const openCreate = () => {
    form.setData(emptyForm);
    setEditTarget(null);
    setDialogOpen(true);
  };

  const openEdit = (h: Holiday) => {
    form.setData({
      name: h.name,
      start_date: h.start_date,
      end_date: h.end_date,
      location_id: h.location_id,
      note: h.note ?? '',
    });
    setEditTarget(h);
    setDialogOpen(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const url = editTarget ? `/hrm/holiday/${editTarget.id}` : '/hrm/holiday';
    const method = editTarget ? 'put' : 'post';
    form[method](url, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editTarget ? 'Feriado atualizado.' : 'Feriado criado.');
        setDialogOpen(false);
      },
      onError: () => toast.error('Verifique os campos.'),
    });
  };

  const confirmDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/hrm/holiday/${deleteTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Feriado removido.');
        setDeleteTarget(null);
      },
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  const activeFilters =
    !!filtros.location_id || !!filtros.start_date || !!filtros.end_date;

  return (
    <AppShell
      title="Feriados"
      breadcrumb={[{ label: 'HRM' }, { label: 'Feriados' }]}
    >
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <CalendarDays size={22} /> Feriados
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Feriados do negócio — opcionalmente escopados por localidade.
            </p>
          </div>
          {can_manage && (
            <Button onClick={openCreate}>
              <Plus size={14} className="mr-1.5" /> Novo feriado
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
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              {locations.length > 0 && (
                <Select
                  value={filtros.location_id ? String(filtros.location_id) : 'ALL'}
                  onValueChange={(v) => setFilter('location_id', v === 'ALL' ? null : Number(v))}
                >
                  <SelectTrigger><SelectValue placeholder="Localidade" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ALL">Todas as localidades</SelectItem>
                    {locations.map((l) => (
                      <SelectItem key={l.id} value={String(l.id)}>{l.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              <div className="space-y-1">
                <Label htmlFor="f-start" className="text-xs text-muted-foreground">De</Label>
                <Input
                  id="f-start"
                  type="date"
                  value={filtros.start_date ?? ''}
                  onChange={(e) => setFilter('start_date', e.target.value || null)}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="f-end" className="text-xs text-muted-foreground">Até</Label>
                <Input
                  id="f-end"
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
            {holidays.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">
                <CalendarDays size={32} className="mx-auto mb-2 opacity-50" />
                Nenhum feriado cadastrado.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Nome</th>
                      <th className="text-left p-3 font-medium">Período</th>
                      <th className="text-left p-3 font-medium">Localidade</th>
                      <th className="text-left p-3 font-medium">Nota</th>
                      {can_manage && <th className="text-right p-3 font-medium">Ações</th>}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {holidays.map((h) => (
                      <tr key={h.id} className="hover:bg-accent/30">
                        <td className="p-3 font-medium">{h.name}</td>
                        <td className="p-3 text-xs">
                          <div>{h.start_date} – {h.end_date}</div>
                          <Badge variant="secondary" className="text-[10px] mt-0.5">
                            {h.days} {h.days === 1 ? 'dia' : 'dias'}
                          </Badge>
                        </td>
                        <td className="p-3 text-xs">
                          {h.location_name ? (
                            <span className="inline-flex items-center gap-1">
                              <MapPin size={10} /> {h.location_name}
                            </span>
                          ) : (
                            <span className="text-muted-foreground">Todas</span>
                          )}
                        </td>
                        <td className="p-3 text-xs text-muted-foreground max-w-xs truncate">
                          {h.note ?? '—'}
                        </td>
                        {can_manage && (
                          <td className="p-3 text-right">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => openEdit(h)}>
                                <Edit size={12} />
                              </Button>
                              <Button size="sm" variant="ghost" className="h-7 w-7 p-0 text-destructive" onClick={() => setDeleteTarget(h)}>
                                <Trash2 size={12} />
                              </Button>
                            </div>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <form onSubmit={submit}>
            <DialogHeader>
              <DialogTitle>{editTarget ? 'Editar feriado' : 'Novo feriado'}</DialogTitle>
            </DialogHeader>
            <div className="py-4 space-y-3">
              <div className="space-y-1">
                <Label htmlFor="h-name">Nome *</Label>
                <Input
                  id="h-name"
                  value={form.data.name}
                  onChange={(e) => form.setData('name', e.target.value)}
                  required
                  autoFocus
                />
                {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="h-start">Início *</Label>
                  <Input
                    id="h-start"
                    type="date"
                    value={form.data.start_date}
                    onChange={(e) => form.setData('start_date', e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="h-end">Fim *</Label>
                  <Input
                    id="h-end"
                    type="date"
                    value={form.data.end_date}
                    onChange={(e) => form.setData('end_date', e.target.value)}
                    required
                  />
                </div>
              </div>
              {locations.length > 0 && (
                <div className="space-y-1">
                  <Label htmlFor="h-loc">Localidade</Label>
                  <Select
                    value={form.data.location_id ? String(form.data.location_id) : 'ALL'}
                    onValueChange={(v) => form.setData('location_id', v === 'ALL' ? null : Number(v))}
                  >
                    <SelectTrigger id="h-loc">
                      <SelectValue placeholder="Todas as localidades" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="ALL">Todas as localidades</SelectItem>
                      {locations.map((l) => (
                        <SelectItem key={l.id} value={String(l.id)}>{l.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
              <div className="space-y-1">
                <Label htmlFor="h-note">Nota (opcional)</Label>
                <Textarea
                  id="h-note"
                  rows={3}
                  value={form.data.note}
                  onChange={(e) => form.setData('note', e.target.value)}
                />
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
            <AlertDialogTitle>Remover feriado?</AlertDialogTitle>
            <AlertDialogDescription>
              "{deleteTarget?.name}" será apagado permanentemente.
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
