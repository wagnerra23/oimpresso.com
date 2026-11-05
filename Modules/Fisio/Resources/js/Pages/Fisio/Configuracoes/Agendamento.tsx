// @memcofre
//   tela: /fisio/configuracoes/agendamento
//   module: Fisio
//   stories: US-FISIO-015 (UC-FCA-01..04)
//   permissao: fisio.access (salvar: fisio.editar)
//
// Configurações › Agendamento pelo app. Fonte visual:
// prototipo-ui/cowork/Wagner/prototipos/fisio/ConfigAgendamentoOnline.dc.html (canvas Prescreve Pro).
// O que o paciente pode marcar, remarcar e cancelar sozinho. Sem promoção, venda extra nem nota
// pública: o app é da clínica, não vitrine (ADR 0437 §13.7). Sinal por Pix depende do Financeiro.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Switch } from '@/Components/ui/switch';

interface Tipo { id: number; nome: string; duracao: number; online: boolean }
interface Regras {
  online_enabled: boolean;
  who_can_book: 'pacote' | 'todos' | 'ninguem';
  open_slots: Record<string, string[]>;
  min_notice_hours: number;
  cancel_min_hours: number;
  max_days_ahead: number;
  no_show_block_after: number;
  waitlist_enabled: boolean;
  checkin_enabled: boolean;
  policy_version: number;
}
interface Props { pode_editar: boolean; regras: Regras; tipos: Tipo[] }

const QUEM: [Regras['who_can_book'], string, string][] = [
  ['pacote', 'Pacientes com pacote ou plano ativo', 'Recomendado para começar.'],
  ['todos', 'Todos os pacientes em tratamento', 'Inclui avulsos com retorno marcado.'],
  ['ninguem', 'Ninguém — só ver as consultas', 'O app mostra e remarca pela clínica.'],
];
const DIAS: [string, string][] = [['1', 'seg'], ['2', 'ter'], ['3', 'qua'], ['4', 'qui'], ['5', 'sex'], ['6', 'sáb']];
const HORAS_PADRAO = ['08:00', '08:30', '10:00', '14:00', '16:00', '18:00'];

export default function ConfigAgendamento({ pode_editar, regras, tipos }: Props) {
  const f = useForm({ ...regras, tipos_online: tipos.filter((t) => t.online).map((t) => t.id) });
  const [novaHora, setNovaHora] = useState('');
  const [extras, setExtras] = useState<string[]>([]);
  const horas = [...new Set([...HORAS_PADRAO, ...extras, ...Object.values(f.data.open_slots).flat()])].sort();
  const aberto = (dia: string, h: string) => (f.data.open_slots[dia] ?? []).includes(h);
  const alternar = (dia: string, h: string) => {
    const atual = f.data.open_slots[dia] ?? [];
    f.setData('open_slots', { ...f.data.open_slots, [dia]: aberto(dia, h) ? atual.filter((x) => x !== h) : [...atual, h] });
  };
  const tipoOn = (id: number) => f.data.tipos_online.includes(id);
  const enviar = (e: FormEvent) => { e.preventDefault(); f.put('/fisio/configuracoes/agendamento', { preserveScroll: true }); };
  const ro = !pode_editar;

  return (
    <>
      <PageHeader title="Agendamento pelo app" subtitle="O que o paciente pode marcar, remarcar e cancelar sozinho pelo app da clínica."
        actions={
          <div className="flex items-center gap-2">
            <Switch id="cfg-ligado" checked={f.data.online_enabled} disabled={ro} onCheckedChange={(v) => f.setData('online_enabled', v)} />
            <Label htmlFor="cfg-ligado">{f.data.online_enabled ? 'Ligado' : 'Desligado'}</Label>
          </div>
        } />
      <nav aria-label="Configurações" className="flex gap-4 border-b border-border px-6 text-sm">
        <Link href="/fisio/configuracoes/whatsapp" className="py-2 text-muted-foreground hover:text-foreground">WhatsApp</Link>
        <span aria-current="page" className="border-b-2 border-primary py-2 font-medium">Agendamento pelo app</span>
      </nav>
      <form onSubmit={enviar} className={`flex max-w-5xl flex-col gap-4 p-6 ${f.data.online_enabled ? '' : 'opacity-60'}`}>
        <Secao titulo="Quem pode agendar sozinho">
          <RadioGroup value={f.data.who_can_book} disabled={ro} onValueChange={(v) => f.setData('who_can_book', v as Regras['who_can_book'])} className="flex flex-col gap-3">
            {QUEM.map(([v, n, d]) => (
              <div key={v} className="flex items-start gap-2">
                <RadioGroupItem id={`quem-${v}`} value={v} />
                <Label htmlFor={`quem-${v}`} className="flex flex-col items-start gap-0.5"><span>{n}</span><span className="text-xs font-normal text-muted-foreground">{d}</span></Label>
              </div>
            ))}
          </RadioGroup>
          <p className="text-xs text-muted-foreground">Paciente novo marca a 1ª avaliação pelo link público da clínica. Os consentimentos vêm antes do atendimento.</p>
        </Secao>

        <Secao titulo="O que aparece no app">
          <div className="flex flex-col divide-y divide-border">
            {tipos.map((t) => (
              <div key={t.id} className="flex items-center justify-between py-2" data-tipo={t.id}>
                <Label htmlFor={`tipo-${t.id}`}>{t.nome} <span className="font-normal text-muted-foreground">· {t.duracao} min</span></Label>
                <Switch id={`tipo-${t.id}`} checked={tipoOn(t.id)} disabled={ro}
                  onCheckedChange={(v) => f.setData('tipos_online', v ? [...f.data.tipos_online, t.id] : f.data.tipos_online.filter((x) => x !== t.id))} />
              </div>
            ))}
          </div>
        </Secao>

        <Secao titulo="Horários abertos para o app" nota="O resto da agenda continua só pela recepção.">
          <div className="overflow-x-auto">
            <table className="min-w-[420px] border-separate border-spacing-1 text-sm" aria-label="Horários abertos para o app">
              <thead><tr><th />{DIAS.map(([, n]) => <th key={n} scope="col" className="text-xs font-semibold text-muted-foreground">{n}</th>)}</tr></thead>
              <tbody>
                {horas.map((h) => (
                  <tr key={h}>
                    <th scope="row" className="pr-2 text-left font-mono text-xs text-muted-foreground">{h}</th>
                    {DIAS.map(([dia, n]) => (
                      <td key={dia}>
                        <button type="button" disabled={ro} aria-pressed={aberto(dia, h)} aria-label={`${n} ${h}`} onClick={() => alternar(dia, h)}
                          className="h-7 w-14 rounded-md bg-muted text-xs text-primary aria-pressed:bg-primary/15 aria-pressed:font-medium">
                          {aberto(dia, h) ? 'app' : ''}
                        </button>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!ro && (
            <div className="flex items-end gap-2">
              <div className="flex flex-col gap-1"><Label htmlFor="nova-hora">Outro horário</Label>
                <Input id="nova-hora" type="time" className="w-32" value={novaHora} onChange={(e) => setNovaHora(e.target.value)} /></div>
              <Button type="button" variant="outline" disabled={!novaHora} onClick={() => { setExtras([...extras, novaHora]); setNovaHora(''); }}>Acrescentar</Button>
            </div>
          )}
          {f.errors['open_slots.1.0'] && <p className="text-sm text-destructive" role="alert">{f.errors['open_slots.1.0']}</p>}
        </Secao>

        <Secao titulo="Regras" nota="Lembrete, confirmação e aviso de vaga saem pelo canal escolhido em Configurações · WhatsApp.">
          <div className="grid gap-3 sm:grid-cols-2">
            <Numero id="r-ant" rotulo="Antecedência mínima para marcar (horas)" v={f.data.min_notice_hours} set={(n) => f.setData('min_notice_hours', n)} ro={ro} erro={f.errors.min_notice_hours} />
            <Numero id="r-canc" rotulo="Remarcar ou cancelar até (horas antes)" v={f.data.cancel_min_hours} set={(n) => f.setData('cancel_min_hours', n)} ro={ro} erro={f.errors.cancel_min_hours} />
            <Numero id="r-dias" rotulo="Agendar até quantos dias à frente" v={f.data.max_days_ahead} set={(n) => f.setData('max_days_ahead', n)} ro={ro} erro={f.errors.max_days_ahead} />
            <Numero id="r-faltas" rotulo="Faltas sem aviso antes de bloquear o app" v={f.data.no_show_block_after} set={(n) => f.setData('no_show_block_after', n)} ro={ro} erro={f.errors.no_show_block_after} />
            <Liga id="r-espera" rotulo="Lista de espera" v={f.data.waitlist_enabled} set={(b) => f.setData('waitlist_enabled', b)} ro={ro} />
            <Liga id="r-chegou" rotulo='Check-in "Cheguei" no app' v={f.data.checkin_enabled} set={(b) => f.setData('checkin_enabled', b)} ro={ro} />
          </div>
          <p className="text-xs text-muted-foreground">Sinal por Pix: desligado até a cobrança do Fisio entrar. Política em vigor: versão {regras.policy_version}.</p>
        </Secao>

        <p className="text-xs text-muted-foreground">Não existe opção de promoção, venda extra no checkout ou nota pública: o app é da clínica, não vitrine.</p>
        {pode_editar && <div><Button type="submit" disabled={f.processing}>Salvar</Button></div>}
      </form>
    </>
  );
}

function Secao({ titulo, nota, children }: { titulo: string; nota?: string; children: ReactNode }) {
  return (
    <Card>
      <CardHeader><CardTitle className="text-sm">{titulo}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-3">{children}{nota && <p className="text-xs text-muted-foreground">{nota}</p>}</CardContent>
    </Card>
  );
}

function Numero({ id, rotulo, v, set, ro, erro }: { id: string; rotulo: string; v: number; set: (n: number) => void; ro: boolean; erro?: string }) {
  return (
    <div className="flex flex-col gap-1">
      <Label htmlFor={id}>{rotulo}</Label>
      <Input id={id} type="number" min={0} className="w-28 font-mono" value={v} disabled={ro} onChange={(e) => set(Number(e.target.value))} />
      {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
    </div>
  );
}

function Liga({ id, rotulo, v, set, ro }: { id: string; rotulo: string; v: boolean; set: (b: boolean) => void; ro: boolean }) {
  return (
    <div className="flex items-center justify-between gap-2">
      <Label htmlFor={id}>{rotulo}</Label>
      <Switch id={id} checked={v} disabled={ro} onCheckedChange={set} />
    </div>
  );
}

ConfigAgendamento.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
