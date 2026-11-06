// Ações da agenda da clínica: mudar o status da sessão, desmarcar, liberar o app e a lista de espera
// (UC-FAG-06..08). O protótipo Agenda.dc.html não desenha estes diálogos; seguem os primitivos do DS.
// Cada botão chama a rota que já existe; a regra (transição, prazo, quem serve na vaga) é do servidor.
import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ROTULO_STATUS, podeDesmarcar, proximosStatus, type Atendimento } from './semana';

const hora = (iso: string) => new Date(iso).toLocaleString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
const ACAO: Partial<Record<Atendimento['status'], string>> = {
  confirmado: 'Confirmar', chegou: 'Chegou', em_atendimento: 'Iniciar atendimento', faltou: 'Marcar falta',
};

export function AtendimentoAcoes({ a, fechar }: { a: Atendimento; fechar: () => void }) {
  const [por, setPor] = useState<'clinica' | 'paciente'>('paciente');
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [ocupado, setOcupado] = useState(false);
  const opcoes = { preserveScroll: true, onStart: () => setOcupado(true), onFinish: () => setOcupado(false), onSuccess: fechar,
    onError: (e: Record<string, string>) => setErro(Object.values(e)[0] ?? 'Não deu certo.') };
  const status = (s: string) => router.post(`/fisio/agenda/atendimentos/${a.id}/status`, { status: s }, opcoes);
  const desmarcar = () => router.post(`/fisio/agenda/atendimentos/${a.id}/cancelar`, { por, motivo: motivo || null }, opcoes);
  const liberar = () => router.post(`/fisio/pacientes/${a.patient_id}/liberar-agendamento`, {}, opcoes);

  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{a.paciente}</DialogTitle>
          <DialogDescription>{a.tipo ?? 'Atendimento'} · {hora(a.inicio)} · {ROTULO_STATUS[a.status]}</DialogDescription>
        </DialogHeader>
        {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
        {proximosStatus(a.status).length > 0 && (
          <div className="flex flex-wrap gap-2" aria-label="Mudar status">
            {proximosStatus(a.status).map((s) => (
              <Button key={s} variant={s === 'faltou' ? 'outline' : 'secondary'} disabled={ocupado} onClick={() => status(s)}>{ACAO[s]}</Button>
            ))}
          </div>
        )}
        {podeDesmarcar(a.status) && (
          <section className="flex flex-col gap-2 border-t border-border pt-3" aria-label="Desmarcar">
            <div className="flex flex-col gap-1">
              <Label htmlFor="ds-por">Quem desmarcou</Label>
              <Select value={por} onValueChange={(v) => setPor(v as 'clinica' | 'paciente')}>
                <SelectTrigger id="ds-por"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="paciente">O paciente</SelectItem>
                  <SelectItem value="clinica">A clínica</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="ds-mot">Motivo (opcional)</Label>
              <Input id="ds-mot" maxLength={280} value={motivo} onChange={(e) => setMotivo(e.target.value)} />
            </div>
            <Button variant="outline" className="self-start" disabled={ocupado} onClick={desmarcar}>Desmarcar sessão</Button>
          </section>
        )}
        {a.app_bloqueado && (
          <section className="flex flex-col gap-2 border-t border-border pt-3" aria-label="Agendamento pelo app">
            <p className="text-sm text-muted-foreground">O paciente faltou sem avisar e não consegue marcar pelo app.</p>
            <Button variant="outline" className="self-start" disabled={ocupado} onClick={liberar}>Liberar agendamento pelo app</Button>
          </section>
        )}
        <DialogFooter>
          <Button variant="ghost" asChild><Link href={`/fisio/pacientes/${a.patient_id}`}>Abrir ficha</Link></Button>
          <Button variant="outline" onClick={fechar}>Fechar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

const DIAS = [['1', 'Seg'], ['2', 'Ter'], ['3', 'Qua'], ['4', 'Qui'], ['5', 'Sex'], ['6', 'Sáb']] as const;
const TURNOS = [['manha', 'Manhã'], ['tarde', 'Tarde'], ['noite', 'Noite']] as const;

export function EntrarNaEspera({ pacientes, fechar }: { pacientes: { id: number; nome: string }[]; fechar: () => void }) {
  const f = useForm<{ patient_id: string; dias: number[]; turnos: string[]; ate: string }>({ patient_id: '', dias: [], turnos: [], ate: '' });
  const alterna = <T,>(lista: T[], v: T) => (lista.includes(v) ? lista.filter((x) => x !== v) : [...lista, v]);
  const enviar = (e: FormEvent) => { e.preventDefault(); f.post('/fisio/agenda/espera', { preserveScroll: true, onSuccess: fechar }); };

  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Pôr na lista de espera</DialogTitle></DialogHeader>
        <form id="espera" onSubmit={enviar} className="flex flex-col gap-3">
          <div className="flex flex-col gap-1">
            <Label htmlFor="es-pac">Paciente</Label>
            <Select value={f.data.patient_id} onValueChange={(v) => f.setData('patient_id', v)}>
              <SelectTrigger id="es-pac"><SelectValue placeholder="Escolha o paciente" /></SelectTrigger>
              <SelectContent>{pacientes.map((x) => <SelectItem key={x.id} value={String(x.id)}>{x.nome}</SelectItem>)}</SelectContent>
            </Select>
            {f.errors.patient_id && <p className="text-sm text-destructive" role="alert">{f.errors.patient_id}</p>}
          </div>
          <fieldset className="flex flex-wrap gap-3"><legend className="mb-1 text-sm font-medium">Dias que servem</legend>
            {DIAS.map(([v, r]) => (
              <Label key={v} className="flex items-center gap-1.5 font-normal">
                <Checkbox checked={f.data.dias.includes(Number(v))} onCheckedChange={() => f.setData('dias', alterna(f.data.dias, Number(v)))} />{r}
              </Label>
            ))}
          </fieldset>
          <fieldset className="flex flex-wrap gap-3"><legend className="mb-1 text-sm font-medium">Turnos que servem</legend>
            {TURNOS.map(([v, r]) => (
              <Label key={v} className="flex items-center gap-1.5 font-normal">
                <Checkbox checked={f.data.turnos.includes(v)} onCheckedChange={() => f.setData('turnos', alterna(f.data.turnos, v))} />{r}
              </Label>
            ))}
          </fieldset>
          <div className="flex flex-col gap-1">
            <Label htmlFor="es-ate">Espera até (opcional)</Label>
            <Input id="es-ate" type="date" value={f.data.ate} onChange={(e) => f.setData('ate', e.target.value)} />
          </div>
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button type="submit" form="espera" disabled={f.processing}>Pôr na lista</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
