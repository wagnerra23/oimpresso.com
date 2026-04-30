// @docvault
//   tela: /ponto/escalas/form
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-005
//   rules: R-PONT-001, R-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/EscalasFormTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, CalendarDays, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

interface Turno {
  id: number;
  dia_semana: number;
  entrada: string | null;
  saida: string | null;
  almoco_inicio: string | null;
  almoco_fim: string | null;
}

interface Escala {
  id: number;
  nome: string;
  codigo: string | null;
  tipo: string;
  carga_diaria_minutos: number;
  carga_semanal_minutos: number;
  permite_banco_horas: boolean;
  turnos: Turno[];
}

interface Props { escala: Escala | null; }

const DIAS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

export default function EscalaForm({ escala }: Props) {
  const isEdit = !!escala;
  const form = useForm({
    nome:                  escala?.nome ?? '',
    codigo:                escala?.codigo ?? '',
    tipo:                  escala?.tipo ?? 'FIXA',
    carga_diaria_minutos:  escala?.carga_diaria_minutos ?? 480,
    carga_semanal_minutos: escala?.carga_semanal_minutos ?? 2400,
    permite_banco_horas:   escala?.permite_banco_horas ?? false,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const cb = {
      onSuccess: () => toast.success(isEdit ? 'Escala atualizada.' : 'Escala criada.'),
      onError:   () => toast.error('Verifique os campos.'),
    };
    if (isEdit) form.put(`/ponto/escalas/${escala!.id}`, cb);
    else form.post('/ponto/escalas', cb);
  };

  return (
    <>
      <Head title={isEdit ? `Editar ${escala!.nome}` : 'Nova escala'} />
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <CalendarDays size={22} /> {isEdit ? 'Editar escala' : 'Nova escala'}
            </h1>
          </div>
          <Button variant="outline" size="sm" asChild>
            <a href="/ponto/escalas"><ArrowLeft size={14} className="mr-1.5" /> Voltar</a>
          </Button>
        </header>

        <form onSubmit={submit}>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Dados da escala</CardTitle>
              <CardDescription className="text-xs">
                Defina nome, tipo e cargas. Depois de salvar você pode configurar os turnos por dia da semana.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <Label htmlFor="nome">Nome *</Label>
                  <Input id="nome" value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} maxLength={120} />
                  {form.errors.nome && <p className="text-xs text-destructive mt-1">{form.errors.nome}</p>}
                </div>
                <div>
                  <Label htmlFor="codigo">Código</Label>
                  <Input id="codigo" value={form.data.codigo ?? ''} onChange={(e) => form.setData('codigo', e.target.value)} maxLength={30} />
                </div>
                <div>
                  <Label htmlFor="tipo">Tipo *</Label>
                  <Select value={form.data.tipo} onValueChange={(v) => form.setData('tipo', v)}>
                    <SelectTrigger id="tipo"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="FIXA">Fixa</SelectItem>
                      <SelectItem value="FLEXIVEL">Flexível</SelectItem>
                      <SelectItem value="ESCALA_12X36">12x36</SelectItem>
                      <SelectItem value="ESCALA_6X1">6x1</SelectItem>
                      <SelectItem value="ESCALA_5X2">5x2</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label>Banco de Horas</Label>
                  <label className="flex items-center gap-2 h-9 cursor-pointer">
                    <input type="checkbox" className="size-4"
                           checked={form.data.permite_banco_horas}
                           onChange={(e) => form.setData('permite_banco_horas', e.target.checked)} />
                    <span className="text-sm">Permite acúmulo de banco de horas</span>
                  </label>
                </div>
                <div>
                  <Label htmlFor="diaria">Carga diária (minutos)</Label>
                  <Input id="diaria" type="number" min={60} max={600}
                         value={form.data.carga_diaria_minutos}
                         onChange={(e) => form.setData('carga_diaria_minutos', parseInt(e.target.value || '0', 10))} />
                  <p className="text-[10px] text-muted-foreground mt-1">
                    Ex.: 480 = 8h (60–600 min)
                  </p>
                </div>
                <div>
                  <Label htmlFor="semanal">Carga semanal (minutos)</Label>
                  <Input id="semanal" type="number" min={0} max={3600}
                         value={form.data.carga_semanal_minutos}
                         onChange={(e) => form.setData('carga_semanal_minutos', parseInt(e.target.value || '0', 10))} />
                  <p className="text-[10px] text-muted-foreground mt-1">
                    Ex.: 2640 = 44h (0–3600 min)
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          {isEdit && escala!.turnos && (
            <Card className="mt-4">
              <CardHeader className="pb-3">
                <CardTitle className="text-base">Turnos por dia da semana</CardTitle>
                <CardDescription className="text-xs">
                  Gestão dos turnos aparece aqui. (UI completa de CRUD de turnos em iteração futura)
                </CardDescription>
              </CardHeader>
              <CardContent>
                {escala!.turnos.length === 0 ? (
                  <p className="text-xs text-muted-foreground italic">Nenhum turno configurado ainda.</p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-xs">
                      <thead className="text-muted-foreground">
                        <tr>
                          <th className="text-left p-2 font-medium">Dia</th>
                          <th className="text-left p-2 font-medium">Entrada</th>
                          <th className="text-left p-2 font-medium">Almoço</th>
                          <th className="text-left p-2 font-medium">Saída</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border">
                        {escala!.turnos.map((t) => (
                          <tr key={t.id}>
                            <td className="p-2 font-medium">{DIAS[t.dia_semana] ?? t.dia_semana}</td>
                            <td className="p-2 font-mono">{t.entrada ?? '—'}</td>
                            <td className="p-2 font-mono">{t.almoco_inicio ?? '—'} – {t.almoco_fim ?? '—'}</td>
                            <td className="p-2 font-mono">{t.saida ?? '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          <div className="flex justify-end gap-2 mt-4">
            <Button type="button" variant="outline" asChild>
              <a href="/ponto/escalas">Cancelar</a>
            </Button>
            <Button type="submit" disabled={form.processing} className="gap-1.5">
              <Save size={14} />
              {form.processing ? 'Salvando…' : (isEdit ? 'Salvar alterações' : 'Criar escala')}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

EscalaForm.layout = (page: ReactNode) => (
  <AppShellV2 breadcrumbItems={[
    { label: 'Ponto WR2' },
    { label: 'Escalas', href: '/ponto/escalas' },
  ]}>
    {page}
  </AppShellV2>
);
