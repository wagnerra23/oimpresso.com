// @docvault
//   tela: /essentials/reminder
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/RemindersIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Bell, ChevronLeft, ChevronRight, Edit, Plus, Repeat, Trash2 } from 'lucide-react';
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
import { Inline } from '@/Components/layout';
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

const MESES = [
  'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];
const DIAS_SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

/** `date` vem cru do model (sem $casts): pode ser 'YYYY-MM-DD' ou ISO com hora. */
const soData = (d: string) => String(d).slice(0, 10);

/** Data local a partir de 'YYYY-MM-DD'. `new Date('2026-08-21')` seria UTC e
 *  voltaria um dia em fuso negativo — o Brasil inteiro cairia na célula errada. */
const dataLocal = (iso: string) => {
  const [a, m, d] = soData(iso).split('-');
  return new Date(Number(a) || 1970, (Number(m) || 1) - 1, Number(d) || 1);
};

export default function RemindersIndex({ reminders, repeats }: Props) {
  const [editTarget, setEditTarget] = useState<Reminder | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Reminder | null>(null);
  const [detalhe, setDetalhe] = useState<Reminder | null>(null);
  const hoje = useMemo(() => new Date(), []);
  const [mes, setMes] = useState(hoje.getMonth());
  const [ano, setAno] = useState(hoje.getFullYear());
  const [foco, setFoco] = useState(hoje.getDate());
  const grade = useRef<HTMLDivElement>(null);

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

  // -- grade do mes (essenciais-page.jsx:498-511) ------------------------------
  const primeiroDiaSemana = new Date(ano, mes, 1).getDay();
  const diasNoMes = new Date(ano, mes + 1, 0).getDate();
  const celulas: Array<number | null> = [
    ...Array<null>(primeiroDiaSemana).fill(null),
    ...Array.from({ length: diasNoMes }, (_, i) => i + 1),
  ];

  // Ocorrencias do dia. A regra de recorrencia e a do LEGADO
  // (`Reminder::getReminders`), nao a do prototipo: la a recorrencia aparece ate
  // ANTES da data de inicio -- aqui so a partir dela, que e o comportamento que o
  // FullCalendar entregava e o que o usuario espera.
  const doDia = (dia: number) => {
    const alvo = new Date(ano, mes, dia);
    return reminders.filter((r) => {
      const inicio = dataLocal(r.date);
      if (alvo < inicio) return r.repeat === 'one_time' && +inicio === +alvo;
      switch (r.repeat) {
        case 'every_day':   return true;
        case 'every_week':  return inicio.getDay() === alvo.getDay();
        case 'every_month': return inicio.getDate() === dia;
        default:            return +inicio === +alvo;
      }
    });
  };

  const ehHoje = (dia: number) =>
    hoje.getFullYear() === ano && hoje.getMonth() === mes && hoje.getDate() === dia;

  const mudarMes = (delta: number) => {
    const d = new Date(ano, mes + delta, 1);
    setMes(d.getMonth());
    setAno(d.getFullYear());
    setFoco(1);
  };

  // Setas percorrem os dias, Enter abre o 1o lembrete (essenciais-page.jsx:464-478)
  const teclas = (e: React.KeyboardEvent, dia: number) => {
    const passo = ({ ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 } as Record<string, number>)[e.key];
    if (passo) {
      e.preventDefault();
      const alvo = Math.min(diasNoMes, Math.max(1, dia + passo));
      setFoco(alvo);
      grade.current?.querySelector<HTMLElement>(`[data-dia="${alvo}"]`)?.focus();
      return;
    }
    if (e.key === 'Enter' || e.key === ' ') {
      const primeiro = doDia(dia)[0];
      if (primeiro) { e.preventDefault(); setDetalhe(primeiro); }
    }
  };

  return (
    <>
      <div className="mx-auto max-w-4xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
              <Bell size={22} /> Lembretes
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Avisos pessoais com data e repetição (cada usuário vê só os seus).
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus size={14} className="mr-1.5" /> Adicionar lembrete
          </Button>
        </header>

        {/* -- navegacao de mes (essenciais-page.jsx:485-495) -- */}
        <Card>
          <CardContent className="py-3" data-contract="toolbar-mes">
            <Inline gap={2} align="center" wrap>
              <Button variant="outline" size="sm" onClick={() => mudarMes(-1)} aria-label="Mês anterior">
                <ChevronLeft size={14} />
              </Button>
              <span className="min-w-44 text-center text-sm font-medium">
                {MESES[mes]} de {ano}
              </span>
              <Button variant="outline" size="sm" onClick={() => mudarMes(1)} aria-label="Próximo mês">
                <ChevronRight size={14} />
              </Button>
              {(mes !== hoje.getMonth() || ano !== hoje.getFullYear()) && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => { setMes(hoje.getMonth()); setAno(hoje.getFullYear()); setFoco(hoje.getDate()); }}
                >
                  Voltar pra hoje
                </Button>
              )}
              <span className="ml-auto text-xs text-muted-foreground">
                {reminders.length} lembrete(s) cadastrado(s)
              </span>
            </Inline>
          </CardContent>
        </Card>

        {/* -- grade do mes (essenciais-page.jsx:498-511) -- */}
        <Card className="overflow-hidden">
          <CardContent className="p-0" data-contract="grade-mes">
            <div className="grid grid-cols-7 border-b border-border bg-muted/30">
              {DIAS_SEMANA.map((d) => (
                <span key={d} className="p-2 text-[11px] uppercase tracking-wide text-muted-foreground">
                  {d}
                </span>
              ))}
            </div>
            <div
              ref={grade}
              role="grid"
              aria-label={`Lembretes de ${MESES[mes]} de ${ano}`}
              className="grid grid-cols-7"
            >
              {celulas.map((dia, i) => {
                // Celula fora do mes: sem foco, sem handler, sem rotulo. Variante
                // propria em vez de `role` dinamico -- o lint nao consegue provar
                // role calculado, e a11y de grid quer o handler no que E gridcell.
                if (!dia) {
                  return (
                    <div
                      key={i}
                      role="presentation"
                      className="min-h-26 border-b border-r border-border bg-muted/30"
                    />
                  );
                }
                const eventos = doDia(dia);
                return (
                  <div
                    key={i}
                    role="gridcell"
                    data-dia={dia}
                    tabIndex={dia === foco ? 0 : -1}
                    aria-label={`${dia} de ${MESES[mes]}, ${eventos.length} lembrete(s)`}
                    onFocus={() => setFoco(dia)}
                    onKeyDown={(e) => teclas(e, dia)}
                    className={[
                      'flex min-h-26 min-w-0 flex-col gap-1 border-b border-r border-border p-1.5',
                      'focus-visible:outline focus-visible:-outline-offset-2 focus-visible:outline-primary',
                      ehHoje(dia) ? 'bg-primary/5' : '',
                    ].join(' ')}
                  >
                    {(
                      <>
                        <span className={`text-[11px] tabular-nums ${ehHoje(dia) ? 'font-semibold text-primary' : 'text-muted-foreground'}`}>
                          {dia}
                        </span>
                        {eventos.slice(0, 3).map((r) => (
                          <button
                            key={`${r.id}-${dia}`}
                            type="button"
                            tabIndex={-1}
                            onClick={() => setDetalhe(r)}
                            title={`${r.name} · ${repeatLabel(r.repeat)}`}
                            className="truncate rounded border border-primary/25 bg-primary/10 px-1 py-0.5 text-left text-[11px] leading-tight hover:border-primary"
                          >
                            {r.time && <span className="tabular-nums">{r.time} </span>}
                            {r.name}
                          </button>
                        ))}
                        {eventos.length > 3 && (
                          <span className="text-[10px] text-muted-foreground">+{eventos.length - 3}</span>
                        )}
                      </>
                    )}
                  </div>
                );
              })}
            </div>
          </CardContent>
        </Card>

        {reminders.length === 0 ? (
          <p className="text-sm text-muted-foreground" data-contract="vazio">
            Nenhum lembrete cadastrado. Use <strong>Adicionar lembrete</strong> — ele aparece no dia
            marcado, e repete conforme a recorrência escolhida.
          </p>
        ) : (
          <p className="text-xs text-muted-foreground">
            Setas percorrem os dias · Enter abre o primeiro lembrete do dia
          </p>
        )}
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

      {/* -- detalhe do lembrete (o Drawer do prototipo, essenciais-page.jsx:521-534).
             Existe porque a grade substituiu a lista: sem ele, Editar e Excluir
             sairiam da tela junto com as linhas. -- */}
      <Dialog open={detalhe !== null} onOpenChange={(open) => !open && setDetalhe(null)}>
        <DialogContent data-contract="drawer">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Bell size={16} /> {detalhe?.name}
            </DialogTitle>
          </DialogHeader>
          {detalhe && (
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 py-2 text-sm">
              <dt className="text-muted-foreground">Data</dt>
              <dd className="tabular-nums">{soData(detalhe.date).split('-').reverse().join('/')}</dd>
              <dt className="text-muted-foreground">Hora de início</dt>
              <dd className="tabular-nums">{detalhe.time ?? '—'}</dd>
              <dt className="text-muted-foreground">Hora de término</dt>
              <dd className="tabular-nums">{detalhe.end_time ?? '—'}</dd>
              <dt className="text-muted-foreground">Repetição</dt>
              <dd>
                <Badge variant="secondary" className="gap-1 text-[10px]">
                  <Repeat size={10} /> {repeatLabel(detalhe.repeat)}
                </Badge>
              </dd>
            </dl>
          )}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDetalhe(null)}>Fechar</Button>
            <Button
              variant="outline"
              onClick={() => { const r = detalhe; setDetalhe(null); if (r) openEdit(r); }}
            >
              <Edit size={14} className="mr-1.5" /> Editar
            </Button>
            <Button
              variant="destructive"
              onClick={() => { const r = detalhe; setDetalhe(null); if (r) setDeleteTarget(r); }}
            >
              <Trash2 size={14} className="mr-1.5" /> Excluir
            </Button>
          </DialogFooter>
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
  <AppShellV2 title="Lembretes" breadcrumbItems={[{ label: 'Essentials' }, { label: 'Lembretes' }]}>
    {page}
  </AppShellV2>
);
