// @memcofre
//   tela: /fisio/pesquisa
//   module: Fisio
//   stories: etapa 07 P-04 (UC-FES-01..08)
//   permissao: fisio.pesquisa · o nome do paciente só aparece para quem também tem fisio.editar
//
// Área do estudo (fonte: prototipo-ui/cowork/Wagner/prototipos/fisio/Pesquisa.dc.html).
// Estudo, participantes, pendências, eventos adversos (P-06), exportação (P-07) e assistente
// da pesquisadora (P-08, só sugestões que ela aceita ou descarta). O software organiza o estudo; não decide conduta.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import SubNav from '@/Components/shared/SubNav';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';

interface Momento { label: string; day_offset: number; window_days: number; instruments: string[] }
interface Estudo { id: number; title: string; status: string; cep_opinion_number: string; protocol_version: string; tcle_version: string; starts_on: string; ends_on: string | null; locked_at: string | null; lock_reason: string | null; momentos: Momento[] }
interface Participante { id: number; code: string; nome: string | null; entered_on: string; exited_on: string | null; exit_type: string | null; exit_reason: string | null }
interface Pendencia { participant_id: number; code: string; moment: string; inicio: string; fim: string; faltando: string[]; situacao: 'futuro' | 'aberto' | 'vencido' }
interface EventoAdverso { id: number; code: string; origin: 'crise_48h' | 'alerta_dor'; started_on: string; ended_on: string; detail: { dor?: number; limiar?: number; dias?: number } | null; reviewed_at: string | null; review_note: string | null }
type Aba = 'part' | 'pend' | 'ea' | 'exp' | 'ia';
const ABAS: { value: Aba; label: string }[] = [{ value: 'part', label: 'Participantes' }, { value: 'pend', label: 'Pendências' }, { value: 'ea', label: 'Eventos adversos' }, { value: 'exp', label: 'Exportação' }, { value: 'ia', label: 'Assistente' }];
const descreverEvento = (e: EventoAdverso) => e.origin === 'crise_48h'
  ? `Crise registrada por ${e.detail?.dias ?? 3} dias seguidos (mais de 48 h)`
  : `Dor ${e.detail?.dor ?? '?'} no limiar em vigor (${e.detail?.limiar ?? '?'})`;
interface Sugestao { id: number; kind: 'qualidade' | 'resumo' | 'padrao'; text: string; status: 'pendente' | 'aceita' | 'descartada'; decided_at: string | null }
const TIPO_SUGESTAO: Record<Sugestao['kind'], string> = { qualidade: 'Qualidade', resumo: 'Resumo', padrao: 'Padrão' };
interface Props {
  estudos: { id: number; title: string; status: string }[];
  estudo: Estudo | null;
  ve_nome: boolean;
  instrumentos: string[];
  rotulos: Record<string, string>;
  participantes?: Participante[];
  pendencias?: Pendencia[];
  pacientes?: { id: number; nome: string }[];
  eventos?: EventoAdverso[];
  assistente_ligado: boolean;
  sugestoes?: Sugestao[];
}

/** Recusa de regra vem do servidor em `errors.geral`, fora dos campos do formulário. */
const erroGeral = (errors: object): string | undefined => (errors as Record<string, string | undefined>).geral;
const data = (d: string) => new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR');
const SAIDAS: Record<string, string> = { perda_seguimento: 'perda de seguimento', revogacao: 'revogou', conclusao: 'concluiu', outro: 'outro motivo' };
const SITUACAO: Record<Pendencia['situacao'], 'outline' | 'secondary' | 'destructive'> = { futuro: 'outline', aberto: 'secondary', vencido: 'destructive' };
const MOMENTOS_PADRAO: Momento[] = [
  { label: 'Entrada', day_offset: 0, window_days: 3, instruments: [] },
  { label: '4 semanas', day_offset: 28, window_days: 7, instruments: [] },
  { label: '8 semanas', day_offset: 56, window_days: 7, instruments: [] },
  { label: '12 semanas', day_offset: 84, window_days: 7, instruments: [] },
];

/** Depois do travamento, toda mudança no estudo pede justificativa (fica no log). */
function Justificativa({ estudo, valor, mudar, erro }: { estudo: Estudo; valor: string; mudar: (v: string) => void; erro?: string }) {
  if (!estudo.locked_at) return null;
  return (
    <div className="grid gap-1">
      <Textarea aria-label="Justificativa" placeholder="Estudo travado: por que mudar este dado?" value={valor} onChange={(e) => mudar(e.target.value)} />
      {erro && <span className="text-xs text-destructive">{erro}</span>}
    </div>
  );
}

const ARQUIVOS = [
  { arquivo: 'momentos', rotulo: 'Momentos (participante × momento × questionário)' },
  { arquivo: 'diario', rotulo: 'Série diária de dor e adesão' },
  { arquivo: 'participantes', rotulo: 'Participantes e saídas' },
  { arquivo: 'dicionario', rotulo: 'Dicionário de dados' },
];

function Travar({ estudo, fechar }: { estudo: Estudo; fechar: () => void }) {
  const f = useForm({ motivo: '' });
  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Travar os dados do estudo</DialogTitle></DialogHeader>
        <p className="text-sm text-muted-foreground">Não tem volta. A exportação passa a usar só o que está gravado agora, e mudar participante ou evento depois exige justificativa.</p>
        <Textarea aria-label="Motivo do travamento" value={f.data.motivo} onChange={(e) => f.setData('motivo', e.target.value)} placeholder="Ex.: fim da coleta do momento 12 semanas" />
        {(f.errors.motivo || erroGeral(f.errors)) && <p className="text-sm text-destructive">{f.errors.motivo ?? erroGeral(f.errors)}</p>}
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button variant="destructive" disabled={f.processing || f.data.motivo.trim() === ''}
            onClick={() => f.post(`/fisio/pesquisa/estudos/${estudo.id}/travar`, { preserveScroll: true, onSuccess: fechar })}>Travar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function NovoEstudo({ instrumentos, rotulos, fechar }: { instrumentos: string[]; rotulos: Record<string, string>; fechar: () => void }) {
  const f = useForm({ title: '', cep_opinion_number: '', protocol_version: '', tcle_version: '', starts_on: '', momentos: MOMENTOS_PADRAO });
  const alternar = (i: number, instr: string, on: boolean) => f.setData('momentos', f.data.momentos.map((m, k) => k !== i ? m
    : { ...m, instruments: on ? [...m.instruments, instr] : m.instruments.filter((x) => x !== instr) }));
  const campo = (k: 'title' | 'cep_opinion_number' | 'protocol_version' | 'tcle_version' | 'starts_on', rotulo: string, tipo = 'text') => (
    <div className="grid gap-1"><Label htmlFor={`est-${k}`}>{rotulo}</Label>
      <Input id={`est-${k}`} type={tipo} value={f.data[k]} onChange={(e) => f.setData(k, e.target.value)} />
      {f.errors[k] && <span className="text-xs text-destructive">{f.errors[k]}</span>}</div>
  );
  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>Novo estudo</DialogTitle></DialogHeader>
        <div className="grid gap-3 sm:grid-cols-2">
          {campo('title', 'Título')}{campo('cep_opinion_number', 'Número do parecer do CEP')}
          {campo('protocol_version', 'Versão do protocolo')}{campo('tcle_version', 'Versão do TCLE aprovada')}
          {campo('starts_on', 'Início', 'date')}
        </div>
        <p className="text-sm font-medium">Momentos de avaliação e questionários de cada um</p>
        <ul className="grid gap-2">{f.data.momentos.map((m, i) => (
          <li key={m.label} className="flex flex-wrap items-center gap-3 text-sm">
            <span className="w-28">{m.label} <span className="text-muted-foreground">(dia {m.day_offset} ± {m.window_days})</span></span>
            {instrumentos.map((instr) => (
              <label key={instr} className="flex items-center gap-1">
                <Checkbox checked={m.instruments.includes(instr)} onCheckedChange={(v) => alternar(i, instr, v === true)} />{rotulos[instr] ?? instr}
              </label>))}
          </li>))}</ul>
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button disabled={f.processing} onClick={() => f.post('/fisio/pesquisa/estudos', { onSuccess: fechar })}>Criar estudo</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Incluir({ estudo, pacientes, fechar }: { estudo: Estudo; pacientes: { id: number; nome: string }[]; fechar: () => void }) {
  const f = useForm({ patient_id: '', tcle_assinado: false, justificativa: '' });
  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Incluir participante</DialogTitle></DialogHeader>
        <Select value={f.data.patient_id} onValueChange={(v) => f.setData('patient_id', v)}>
          <SelectTrigger aria-label="Paciente"><SelectValue placeholder="Escolha o paciente" /></SelectTrigger>
          <SelectContent>{pacientes.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.nome}</SelectItem>)}</SelectContent>
        </Select>
        <label className="flex items-start gap-2 text-sm">
          <Checkbox checked={f.data.tcle_assinado} onCheckedChange={(v) => f.setData('tcle_assinado', v === true)} />
          O paciente assinou o TCLE na versão {estudo.tcle_version}, aprovada pelo CEP.
        </label>
        <Justificativa estudo={estudo} valor={f.data.justificativa} mudar={(v) => f.setData('justificativa', v)} />
        {erroGeral(f.errors) && <p className="text-sm text-destructive">{erroGeral(f.errors)}</p>}
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button disabled={f.processing || !f.data.patient_id || !f.data.tcle_assinado}
            onClick={() => f.post(`/fisio/pesquisa/estudos/${estudo.id}/participantes`, { preserveScroll: true, onSuccess: fechar })}>Incluir</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Saida({ estudo, p, fechar }: { estudo: Estudo; p: Participante; fechar: () => void }) {
  const f = useForm({ tipo: 'perda_seguimento', motivo: '', justificativa: '' });
  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Saída de {p.code}</DialogTitle></DialogHeader>
        <Select value={f.data.tipo} onValueChange={(v) => f.setData('tipo', v)}>
          <SelectTrigger aria-label="Tipo de saída"><SelectValue /></SelectTrigger>
          <SelectContent>{['perda_seguimento', 'conclusao', 'outro'].map((t) => <SelectItem key={t} value={t}>{SAIDAS[t]}</SelectItem>)}</SelectContent>
        </Select>
        <Textarea aria-label="Motivo" placeholder="Motivo (fica registrado e não é apagado)" value={f.data.motivo} onChange={(e) => f.setData('motivo', e.target.value)} />
        <Justificativa estudo={estudo} valor={f.data.justificativa} mudar={(v) => f.setData('justificativa', v)} />
        {(f.errors.motivo || erroGeral(f.errors)) && <p className="text-sm text-destructive">{f.errors.motivo ?? erroGeral(f.errors)}</p>}
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button disabled={f.processing || f.data.motivo.trim() === ''}
            onClick={() => f.post(`/fisio/pesquisa/estudos/${estudo.id}/participantes/${p.id}/saida`, { preserveScroll: true, onSuccess: fechar })}>Registrar saída</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Revisar({ estudo, e, fechar }: { estudo: Estudo; e: EventoAdverso; fechar: () => void }) {
  const f = useForm({ nota: '', justificativa: '' });
  return (
    <Dialog open onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Revisar evento de {e.code}</DialogTitle></DialogHeader>
        <p className="text-sm text-muted-foreground">{descreverEvento(e)}</p>
        <Textarea aria-label="Nota da revisão" placeholder="O que foi avaliado e o que será comunicado ao CEP" value={f.data.nota} onChange={(ev) => f.setData('nota', ev.target.value)} />
        <Justificativa estudo={estudo} valor={f.data.justificativa} mudar={(v) => f.setData('justificativa', v)} />
        {(f.errors.nota || erroGeral(f.errors)) && <p className="text-sm text-destructive">{f.errors.nota ?? erroGeral(f.errors)}</p>}
        <DialogFooter>
          <Button variant="outline" onClick={fechar}>Cancelar</Button>
          <Button disabled={f.processing || f.data.nota.trim() === ''}
            onClick={() => f.post(`/fisio/pesquisa/estudos/${estudo.id}/eventos/${e.id}/revisar`, { preserveScroll: true, onSuccess: fechar })}>Registrar revisão</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Assistente da pesquisadora (P-08): sugestão, não conclusão. Só nesta área; nunca vai ao paciente nem à ficha. */
function Assistente({ estudo, ligado, podeLigar, sugestoes }: { estudo: Estudo; ligado: boolean; podeLigar: boolean; sugestoes: Sugestao[] }) {
  const [ocupado, setOcupado] = useState(false);
  const { errors } = usePage().props as { errors?: Record<string, string> };
  const enviar = (url: string, dados: { ligado?: boolean } = {}) => {
    setOcupado(true);
    router.post(url, dados, { preserveScroll: true, onFinish: () => setOcupado(false) });
  };
  if (!ligado) {
    return (
      <div className="grid gap-3 text-sm">
        <p className="text-muted-foreground">O assistente lê só o dado sem identificação da exportação e devolve sugestões de qualidade do dado, resumo descritivo e padrões antes das crises. Ele começa desligado: quem liga é a clínica.</p>
        {podeLigar ? <div><Button variant="outline" disabled={ocupado} onClick={() => enviar('/fisio/pesquisa/assistente', { ligado: true })}>Ligar o assistente</Button></div>
          : <p className="text-muted-foreground">Peça à clínica para ligar.</p>}
      </div>
    );
  }
  return (
    <div className="grid gap-3 text-sm">
      <div className="flex flex-wrap items-center gap-2">
        <Button disabled={ocupado} onClick={() => enviar(`/fisio/pesquisa/estudos/${estudo.id}/sugestoes`)}>{ocupado ? 'Pedindo…' : 'Pedir sugestões'}</Button>
        {podeLigar && <Button variant="ghost" disabled={ocupado} onClick={() => enviar('/fisio/pesquisa/assistente', { ligado: false })}>Desligar</Button>}
        <span className="text-muted-foreground">Recebe só dado sem identificação. Não aparece para o paciente nem na ficha clínica.</span>
      </div>
      {errors?.geral && <p className="text-destructive">{errors.geral}</p>}
      {sugestoes.length === 0 ? <p className="text-muted-foreground">Nenhuma sugestão ainda.</p> : (
        <ul className="divide-y rounded-md border">{sugestoes.map((g) => (
          <li key={g.id} className="flex flex-wrap items-center gap-3 p-3">
            <Badge variant="outline" className="w-24 justify-center">{TIPO_SUGESTAO[g.kind]}</Badge>
            <span className="min-w-0 flex-1">{g.text}</span>
            {g.status === 'pendente' ? (
              <span className="flex gap-2">
                <Button size="sm" variant="outline" disabled={ocupado} onClick={() => enviar(`/fisio/pesquisa/estudos/${estudo.id}/sugestoes/${g.id}/descartar`)}>Descartar</Button>
                <Button size="sm" disabled={ocupado} onClick={() => enviar(`/fisio/pesquisa/estudos/${estudo.id}/sugestoes/${g.id}/aceitar`)}>Aceitar</Button>
              </span>
            ) : <span className="text-muted-foreground">{g.status}{g.decided_at ? ` em ${new Date(g.decided_at).toLocaleDateString('pt-BR')}` : ''}</span>}
          </li>))}</ul>)}
      <p className="text-xs text-muted-foreground">Sugestão, não conclusão. Quem aceitou ou descartou fica registrado.</p>
    </div>
  );
}

export default function PesquisaIndex(props: Props) {
  const { estudos, estudo, ve_nome } = props;
  const [aba, setAba] = useState<Aba>('part');
  const [revendo, setRevendo] = useState<EventoAdverso | null>(null);
  const [travando, setTravando] = useState(false);
  const [dialogo, setDialogo] = useState<'novo' | 'incluir' | null>(null);
  const [saindo, setSaindo] = useState<Participante | null>(null);
  const flash = (usePage().props as { flash?: { success?: string } }).flash?.success;

  return (
    <>
      <PageHeader
        title={estudo?.title ?? 'Pesquisa'}
        subtitle={estudo ? `Parecer CEP ${estudo.cep_opinion_number} · protocolo ${estudo.protocol_version} · TCLE ${estudo.tcle_version} · desde ${data(estudo.starts_on)}` : 'Estudos da clínica, com consentimento por estudo.'}
        actions={<>
          {estudo && ve_nome && estudo.status === 'ativo' && <Button variant="outline" onClick={() => setDialogo('incluir')}>Incluir participante</Button>}
          <Button onClick={() => setDialogo('novo')}>Novo estudo</Button>
        </>}
      />
      <div className="mx-auto grid w-full max-w-5xl gap-4 p-4" data-contract="fisio-pesquisa">
        <p className="rounded-md bg-muted p-3 text-sm text-muted-foreground">Área do pesquisador. O código do participante não vem do nome nem do CPF. A tabela que liga código a paciente fica só na clínica.</p>
        {flash && <p className="text-sm text-muted-foreground" role="status">{flash}</p>}
        {estudos.length > 1 && (
          <Select value={String(estudo?.id ?? '')} onValueChange={(v) => router.get('/fisio/pesquisa', { estudo: v })}>
            <SelectTrigger className="max-w-sm" aria-label="Estudo"><SelectValue /></SelectTrigger>
            <SelectContent>{estudos.map((e) => <SelectItem key={e.id} value={String(e.id)}>{e.title}</SelectItem>)}</SelectContent>
          </Select>
        )}
        {estudo === null ? <p className="text-sm text-muted-foreground">Nenhum estudo cadastrado.</p> : (
          <>
            <SubNav variant="segmented" ariaLabel="Seções do estudo" value={aba} onChange={(v) => setAba(ABAS.some((a) => a.value === v) ? (v as Aba) : 'part')}
              items={ABAS} />
            <Card><CardContent className="pt-6">
              {aba === 'part' && (
                <Deferred data="participantes" fallback={<Skeleton className="h-24 w-full" />}>
                  {(props.participantes ?? []).length === 0 ? <p className="text-sm text-muted-foreground">Nenhum participante ainda.</p> : (
                    <ul className="divide-y rounded-md border">{(props.participantes ?? []).map((p) => (
                      <li key={p.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
                        <span className="w-24 font-mono font-medium">{p.code}</span>
                        {ve_nome && <span className="min-w-0 flex-1 truncate">{p.nome ?? '—'}</span>}
                        <span className="font-mono text-muted-foreground">{data(p.entered_on)}</span>
                        {p.exited_on ? <span className="flex-1"><Badge variant="outline">{SAIDAS[p.exit_type ?? 'outro']} em {data(p.exited_on)}</Badge> <span className="text-muted-foreground">{p.exit_reason}</span></span>
                          : <><Badge variant="secondary">no estudo</Badge><Button size="sm" variant="outline" onClick={() => setSaindo(p)}>Registrar saída</Button></>}
                      </li>))}</ul>)}
                </Deferred>
              )}
              {aba === 'pend' && (
                <Deferred data="pendencias" fallback={<Skeleton className="h-24 w-full" />}>
                  {(props.pendencias ?? []).length === 0 ? <p className="text-sm text-muted-foreground">Nenhum questionário pendente.</p> : (
                    <ul className="divide-y rounded-md border">{(props.pendencias ?? []).map((q) => (
                      <li key={`${q.participant_id}-${q.moment}`} className="flex flex-wrap items-center gap-3 p-3 text-sm">
                        <span className="w-24 font-mono">{q.code}</span><span className="w-28">{q.moment}</span>
                        <span className="font-mono text-muted-foreground">{data(q.inicio)} a {data(q.fim)}</span>
                        <span className="flex-1">{q.faltando.map((c) => props.rotulos[c] ?? c).join(', ')}</span><Badge variant={SITUACAO[q.situacao]}>{q.situacao}</Badge>
                      </li>))}</ul>)}
                </Deferred>
              )}
              {aba === 'ea' && (
                <Deferred data="eventos" fallback={<Skeleton className="h-24 w-full" />}>
                  <p className="mb-3 text-sm text-muted-foreground">Registrados a partir da crise por mais de 48 h e do alerta de dor. A gravidade e o que comunicar ao CEP são da pesquisadora.</p>
                  {(props.eventos ?? []).length === 0 ? <p className="text-sm text-muted-foreground">Nenhum evento registrado.</p> : (
                    <ul className="divide-y rounded-md border">{(props.eventos ?? []).map((e) => (
                      <li key={e.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
                        <span className="w-24 font-mono">{e.code}</span>
                        <span className="min-w-0 flex-1">{descreverEvento(e)}</span>
                        <span className="font-mono text-muted-foreground">{data(e.started_on)}{e.ended_on !== e.started_on ? ` a ${data(e.ended_on)}` : ''}</span>
                        {e.reviewed_at ? <span className="text-muted-foreground" title={e.review_note ?? ''}>revisado</span>
                          : <Button size="sm" variant="outline" onClick={() => setRevendo(e)}>Revisar</Button>}
                      </li>))}</ul>)}
                </Deferred>
              )}
              {aba === 'exp' && (
                <div className="grid gap-3 text-sm">
                  <p className="text-muted-foreground">CSV sem nome, CPF, telefone nem data do calendário: cada linha usa o código do participante e o dia do estudo. Dia sem check-in sai como NA, nunca como zero. Cada download fica registrado no log.</p>
                  <ul className="grid gap-2">{ARQUIVOS.map((a) => (
                    <li key={a.arquivo}><a className="underline" href={`/fisio/pesquisa/estudos/${estudo.id}/exportar/${a.arquivo}`}>{a.rotulo}</a></li>))}</ul>
                  {estudo.locked_at
                    ? <p className="text-muted-foreground">Travado em {new Date(estudo.locked_at).toLocaleString('pt-BR')}: {estudo.lock_reason}</p>
                    : <div><Button variant="outline" onClick={() => setTravando(true)}>Travar dados</Button></div>}
                </div>
              )}
              {aba === 'ia' && (
                <Deferred data="sugestoes" fallback={<Skeleton className="h-24 w-full" />}>
                  <Assistente estudo={estudo} ligado={props.assistente_ligado} podeLigar={ve_nome} sugestoes={props.sugestoes ?? []} />
                </Deferred>
              )}
            </CardContent></Card>
          </>
        )}
      </div>
      {dialogo === 'novo' && <NovoEstudo instrumentos={props.instrumentos} rotulos={props.rotulos} fechar={() => setDialogo(null)} />}
      {dialogo === 'incluir' && estudo && <Deferred data="pacientes" fallback={null}><Incluir estudo={estudo} pacientes={props.pacientes ?? []} fechar={() => setDialogo(null)} /></Deferred>}
      {saindo && estudo && <Saida estudo={estudo} p={saindo} fechar={() => setSaindo(null)} />}
      {travando && estudo && <Travar estudo={estudo} fechar={() => setTravando(false)} />}
      {revendo && estudo && <Revisar estudo={estudo} e={revendo} fechar={() => setRevendo(null)} />}
    </>
  );
}

PesquisaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
