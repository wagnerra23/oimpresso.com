// @memcofre
//   tela: /fisio/agenda
//   module: Fisio
//   stories: US-FISIO-015 (UC-FAG-01..05), regras US-FISIO-014
//   permissao: fisio.access (escrever: fisio.editar)
//
// Agenda da clínica. Fonte visual: prototipo-ui/cowork/Wagner/prototipos/fisio/Agenda.dc.html
// (canvas Prescreve Pro, ADR 0438). Semana do profissional, legenda de status, resumo do dia, vaga
// liberada e lista de espera. Conflito, encaixe, prazo e faltas são do servidor (AgendaService).
// Com fisio.editar, clicar na sessão abre status/desmarcar/liberar app (UC-FAG-06, UC-FAG-08).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import SemanaHoras, { type Atendimento, type Bloqueio } from './_components/SemanaHoras';
import { AtendimentoAcoes, EntrarNaEspera } from './_components/AcoesAgenda';

interface Opcao { id: number; nome: string }
interface Tipo extends Opcao { duracao: number; modalidade: string }
interface Resumo { sessoes: number; confirmadas: number; faltas_mes: number }
interface Espera { id: number; patient_id: number; paciente: string; preferencia: string }
interface Vaga { appointment_id: number; inicio: string; quem_cancelou: string; candidatos: number }
interface Props {
  semana: string;
  profissional_id: number;
  profissionais: Opcao[];
  pode_editar: boolean;
  tipos?: Tipo[];
  pacientes?: Opcao[];
  atendimentos?: Atendimento[];
  bloqueios?: Bloqueio[];
  resumo?: Resumo;
  espera?: Espera[];
  vaga?: Vaga | null;
}

const hojeIso = () => new Date().toLocaleDateString('en-CA');
const somaDias = (iso: string, n: number) => {
  const d = new Date(`${iso}T12:00:00`);
  d.setDate(d.getDate() + n);
  return d.toLocaleDateString('en-CA');
};
const ddmm = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long' });
const quando = (iso: string) => new Date(iso).toLocaleString('pt-BR', { weekday: 'short', hour: '2-digit', minute: '2-digit' });

export default function FisioAgenda(p: Props) {
  const [nova, setNova] = useState(false);
  const [bloquear, setBloquear] = useState(false);
  const [aberto, setAberto] = useState<Atendimento | null>(null);
  const [espera, setEspera] = useState(false);
  const nomeProf = p.profissionais.find((x) => x.id === p.profissional_id)?.nome ?? '';
  const ir = (q: Record<string, string | number>) =>
    router.get('/fisio/agenda', { semana: p.semana, profissional: p.profissional_id, ...q }, { preserveState: true });

  return (
    <>
      <PageHeader
        title="Agenda"
        subtitle={`Semana de ${ddmm(p.semana)} a ${ddmm(somaDias(p.semana, 5))}${nomeProf ? ` · ${nomeProf}` : ''}`}
        actions={p.pode_editar ? (
          <>
            <Button variant="ghost" asChild><Link href="/fisio/configuracoes/agendamento">Agendamento pelo app</Link></Button>
            <Button variant="outline" onClick={() => setBloquear(true)}>Bloquear horário</Button>
            <Button onClick={() => setNova(true)}>Nova sessão</Button>
          </>
        ) : undefined}
      />
      <div className="flex flex-wrap items-stretch">
        <main className="flex min-w-0 flex-[999_1_520px] flex-col gap-5 p-6">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div className="flex flex-wrap gap-2" aria-label="Legenda">
              <Badge variant="success">confirmado pelo WhatsApp</Badge>
              <Badge variant="warning">aguardando</Badge>
              <Badge variant="info">teleconsulta</Badge>
              <Badge variant="danger">falta</Badge>
            </div>
            <div className="flex items-center gap-2">
              {p.profissionais.length > 1 && (
                <Select value={String(p.profissional_id)} onValueChange={(v) => ir({ profissional: v })}>
                  <SelectTrigger className="w-56" aria-label="Profissional"><SelectValue /></SelectTrigger>
                  <SelectContent>{p.profissionais.map((x) => <SelectItem key={x.id} value={String(x.id)}>{x.nome}</SelectItem>)}</SelectContent>
                </Select>
              )}
              <Button variant="outline" size="icon" aria-label="Semana anterior" onClick={() => ir({ semana: somaDias(p.semana, -7) })}><ChevronLeft className="size-4" /></Button>
              <Button variant="outline" onClick={() => ir({ semana: hojeIso() })}>Hoje</Button>
              <Button variant="outline" size="icon" aria-label="Próxima semana" onClick={() => ir({ semana: somaDias(p.semana, 7) })}><ChevronRight className="size-4" /></Button>
            </div>
          </div>
          <Deferred data={['atendimentos', 'bloqueios']} fallback={<Skeleton className="h-[620px] w-full rounded-lg" />}>
            <SemanaHoras segunda={p.semana} hoje={hojeIso()} atendimentos={p.atendimentos ?? []} bloqueios={p.bloqueios ?? []} abrir={p.pode_editar ? setAberto : undefined} />
          </Deferred>
        </main>
        <aside aria-label="Lista de espera" className="flex max-w-xs flex-[1_1_280px] flex-col border-l border-border bg-card">
          <Deferred data="resumo" fallback={<Skeleton className="m-4 h-16" />}>
            <section className="flex flex-col gap-2 border-b border-border px-4 py-3.5">
              <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Hoje</h2>
              <div className="grid grid-cols-3 gap-2 text-center">
                <Numero v={p.resumo?.sessoes ?? 0} r="sessões" />
                <Numero v={`${p.resumo?.confirmadas ?? 0}/${p.resumo?.sessoes ?? 0}`} r="confirmadas" />
                <Numero v={p.resumo?.faltas_mes ?? 0} r="faltas no mês" />
              </div>
            </section>
          </Deferred>
          <Deferred data="vaga" fallback={null}>
            {p.vaga ? (
              <section className="flex flex-col gap-2 border-b border-border bg-primary/10 px-4 py-3.5" data-vaga={p.vaga.appointment_id}>
                <h2 className="text-xs font-semibold uppercase tracking-wide text-primary">Vaga liberada · {quando(p.vaga.inicio)}</h2>
                <p className="text-sm">{p.vaga.quem_cancelou} cancelou. {p.vaga.candidatos} {p.vaga.candidatos === 1 ? 'paciente da lista de espera serve' : 'pacientes da lista de espera servem'} neste horário.</p>
                <p className="text-xs text-muted-foreground">Quem responder primeiro fica com a vaga.</p>
              </section>
            ) : <></>}
          </Deferred>
          <Deferred data="espera" fallback={<Skeleton className="m-4 h-24" />}>
            <section className="flex flex-col gap-2.5 px-4 py-3.5">
              <div className="flex items-center justify-between gap-2">
                <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lista de espera · {p.espera?.length ?? 0}</h2>
                {p.pode_editar && <Button size="sm" variant="outline" onClick={() => setEspera(true)}>Pôr na lista</Button>}
              </div>
              {(p.espera ?? []).length === 0 && <p className="text-sm text-muted-foreground">Ninguém esperando vaga.</p>}
              {(p.espera ?? []).map((w) => (
                <div key={w.id} data-espera={w.id} className="flex items-start justify-between gap-2 border-b border-border/60 pb-2">
                  <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="font-medium">{w.paciente}</span>
                    <span className="text-xs text-muted-foreground">{w.preferencia}</span>
                  </div>
                  {p.pode_editar && (
                    <Button size="sm" variant="ghost" aria-label={`Tirar ${w.paciente} da lista de espera`}
                      onClick={() => router.delete(`/fisio/agenda/espera/${w.id}`, { preserveScroll: true })}>Tirar</Button>
                  )}
                </div>
              ))}
            </section>
          </Deferred>
        </aside>
      </div>
      {nova && <NovaSessao p={p} fechar={() => setNova(false)} />}
      {bloquear && <BloquearHorario p={p} fechar={() => setBloquear(false)} />}
      {aberto && <AtendimentoAcoes a={aberto} fechar={() => setAberto(null)} />}
      {espera && <EntrarNaEspera pacientes={p.pacientes ?? []} fechar={() => setEspera(false)} />}
    </>
  );
}

function Numero({ v, r }: { v: ReactNode; r: string }) {
  return <div><div className="font-mono text-xl">{v}</div><div className="text-[11px] text-muted-foreground">{r}</div></div>;
}

/** Nova sessão em 3 campos: paciente, tipo, horário (etapa 04 §3.2 UX). */
function NovaSessao({ p, fechar }: { p: Props; fechar: () => void }) {
  const f = useForm({ patient_id: '', type_id: String(p.tipos?.find((t) => t.nome === 'Sessão')?.id ?? ''), professional_id: String(p.profissional_id), starts_at: '', encaixe: false });
  const conflito = typeof f.errors.starts_at === 'string' && f.errors.starts_at.includes('encaixe');
  const enviar = (e: FormEvent) => { e.preventDefault(); f.post('/fisio/agenda/atendimentos', { preserveScroll: true, onSuccess: fechar }); };

  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Nova sessão</DialogTitle></DialogHeader>
        <form id="nova-sessao" onSubmit={enviar} className="flex flex-col gap-3">
          <Campo id="ns-pac" rotulo="Paciente" erro={f.errors.patient_id}>
            <Select value={f.data.patient_id} onValueChange={(v) => f.setData('patient_id', v)}>
              <SelectTrigger id="ns-pac"><SelectValue placeholder="Escolha o paciente" /></SelectTrigger>
              <SelectContent>{(p.pacientes ?? []).map((x) => <SelectItem key={x.id} value={String(x.id)}>{x.nome}</SelectItem>)}</SelectContent>
            </Select>
          </Campo>
          <Campo id="ns-tipo" rotulo="Tipo" erro={f.errors.type_id}>
            <Select value={f.data.type_id} onValueChange={(v) => f.setData('type_id', v)}>
              <SelectTrigger id="ns-tipo"><SelectValue placeholder="Tipo de atendimento" /></SelectTrigger>
              <SelectContent>{(p.tipos ?? []).map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.nome} · {t.duracao} min</SelectItem>)}</SelectContent>
            </Select>
          </Campo>
          <Campo id="ns-hora" rotulo="Dia e hora" erro={f.errors.starts_at}>
            <Input id="ns-hora" type="datetime-local" value={f.data.starts_at} onChange={(e) => f.setData('starts_at', e.target.value)} />
          </Campo>
          {conflito && (
            <div className="flex items-center gap-2 text-sm">
              <Checkbox id="ns-encaixe" checked={f.data.encaixe} onCheckedChange={(v) => f.setData('encaixe', v === true)} />
              <Label htmlFor="ns-encaixe">Marcar como encaixe</Label>
            </div>
          )}
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button type="submit" form="nova-sessao" disabled={f.processing}>Marcar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function BloquearHorario({ p, fechar }: { p: Props; fechar: () => void }) {
  const f = useForm({ professional_id: String(p.profissional_id), starts_at: '', ends_at: '', motivo: '' });
  const enviar = (e: FormEvent) => { e.preventDefault(); f.post('/fisio/agenda/bloqueios', { preserveScroll: true, onSuccess: fechar }); };

  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Bloquear horário</DialogTitle></DialogHeader>
        <form id="bloquear" onSubmit={enviar} className="flex flex-col gap-3">
          <Campo id="bl-ini" rotulo="De" erro={f.errors.starts_at}><Input id="bl-ini" type="datetime-local" value={f.data.starts_at} onChange={(e) => f.setData('starts_at', e.target.value)} /></Campo>
          <Campo id="bl-fim" rotulo="Até" erro={f.errors.ends_at}><Input id="bl-fim" type="datetime-local" value={f.data.ends_at} onChange={(e) => f.setData('ends_at', e.target.value)} /></Campo>
          <Campo id="bl-mot" rotulo="Motivo (opcional)" erro={f.errors.motivo}><Input id="bl-mot" maxLength={120} placeholder="Almoço, curso, férias" value={f.data.motivo} onChange={(e) => f.setData('motivo', e.target.value)} /></Campo>
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button type="submit" form="bloquear" disabled={f.processing}>Bloquear</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Campo({ id, rotulo, erro, children }: { id: string; rotulo: string; erro?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
    </div>
  );
}

FisioAgenda.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
