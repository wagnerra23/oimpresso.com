// Prontuário do paciente (US-FISIO-005 · Res. COFFITO 414/2012).
//
// Só inclusão: registro assinado não tem botão de editar nem de apagar — corrigir é
// acrescentar um ADENDO que aponta o original. O carimbo (profissional, CREFITO, data e
// hora) é do servidor; a tela só envia o CREFITO de quem assina. `telemonitoramento` vem
// do portal (US-FISIO-007) e aqui só é lido.
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { FilePlus2, MessageSquarePlus } from 'lucide-react';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Textarea } from '@/Components/ui/textarea';
import type { Opcao } from './PerfilClinico';

export interface Registro {
  id: number; kind: 'avaliacao' | 'evolucao' | 'telemonitoramento' | 'adendo';
  body: Record<string, unknown>; modality: string | null; channel: string | null; amends_id: number | null;
  signed_by: number | null; signed_by_nome: string | null; signed_crefito: string | null; signed_at: string | null;
}

const TIPO: Record<Registro['kind'], string> = {
  avaliacao: 'Avaliação', evolucao: 'Evolução', telemonitoramento: 'Telemonitoramento', adendo: 'Adendo',
};
const TOM: Record<Registro['kind'], 'info' | 'success' | 'neutral' | 'warning'> = {
  avaliacao: 'info', evolucao: 'success', telemonitoramento: 'neutral', adendo: 'warning',
};

/** Rótulos dos campos do corpo (os mesmos nomes do ProntuarioRegras do servidor). */
const CAMPO: Record<string, string> = {
  queixa_principal: 'Queixa principal', historia_doenca: 'História da doença atual', antecedentes: 'Antecedentes',
  exame_fisico: 'Exame físico-funcional', diagnostico_cinesiologico: 'Diagnóstico cinesiológico funcional',
  prognostico: 'Prognóstico', objetivos: 'Objetivos', plano_tratamento: 'Plano de tratamento (conduta)',
  sessoes_previstas: 'Sessões previstas', red_flags: 'Sinais de alerta', condition_flags: 'Marcadores de condição',
  conduta: 'Conduta', resposta_paciente: 'Resposta do paciente', dor: 'Dor (0–10)', observacoes: 'Observações', texto: 'Correção',
};

const quando = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const CREFITO_LS = 'oimpresso.fisio.crefito';

function lerCrefito(): string {
  try { return window.localStorage.getItem(CREFITO_LS) ?? ''; } catch { return ''; }
}

type Modo = 'avaliacao' | 'prontuario';
type Novo = { kind: 'avaliacao' | 'evolucao' | 'adendo'; amends?: Registro } | null;

export default function Prontuario({ modo, pacienteId, registros, podeRegistrar, bloqueado, marcadores, sinaisDeAlerta }: {
  modo: Modo; pacienteId: number; registros: Registro[]; podeRegistrar: boolean; bloqueado: boolean;
  marcadores: Opcao[]; sinaisDeAlerta: Opcao[];
}) {
  const [novo, setNovo] = useState<Novo>(null);
  const rotulos = useMemo(() => Object.fromEntries([...marcadores, ...sinaisDeAlerta].map((o) => [o.value, o.label])), [marcadores, sinaisDeAlerta]);
  const lista = modo === 'avaliacao'
    ? registros.filter((r) => r.kind === 'avaliacao' || (r.kind === 'adendo' && registros.some((o) => o.id === r.amends_id && o.kind === 'avaliacao')))
    : registros;
  const porId = useMemo<Record<number, Registro | undefined>>(() => Object.fromEntries(registros.map((r) => [r.id, r])), [registros]);
  const podeNovo = podeRegistrar && !bloqueado;

  return (
    <div className="flex flex-col gap-3">
      {podeNovo && (
        <div className="flex flex-wrap gap-2">
          {modo === 'avaliacao'
            ? <Button onClick={() => setNovo({ kind: 'avaliacao' })}><FilePlus2 className="size-4" aria-hidden="true" /> Nova avaliação</Button>
            : <>
                <Button onClick={() => setNovo({ kind: 'evolucao' })}><FilePlus2 className="size-4" aria-hidden="true" /> Registrar evolução</Button>
                <Button variant="outline" onClick={() => setNovo({ kind: 'avaliacao' })}>Nova avaliação</Button>
              </>}
        </div>
      )}

      {lista.length === 0 ? (
        <EmptyState icon="file-text" title={modo === 'avaliacao' ? 'Sem avaliação registrada' : 'Prontuário vazio'}
          description={modo === 'avaliacao'
            ? 'A avaliação inicial traz queixa, história, exame físico, diagnóstico cinesiológico, objetivos e plano (Res. COFFITO 414/2012).'
            : 'Cada atendimento vira uma evolução assinada com nome e CREFITO. Registro assinado não se edita: corrige-se com adendo.'} />
      ) : (
        <ol className="flex flex-col gap-3" aria-label="Linha do tempo do prontuário">
          {lista.map((r) => (
            <li key={r.id}>
              <Card data-contract="fisio-registro"><CardContent className="flex flex-col gap-2 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant={TOM[r.kind]}>{TIPO[r.kind]}</Badge>
                  {r.amends_id !== null && porId[r.amends_id] !== undefined && (
                    <span className="text-xs text-muted-foreground">corrige {TIPO[porId[r.amends_id]!.kind].toLowerCase()} de {quando(porId[r.amends_id]!.signed_at)}</span>
                  )}
                  <span className="flex-1" />
                  {podeRegistrar && r.kind !== 'telemonitoramento' && (
                    <Button size="sm" variant="ghost" onClick={() => setNovo({ kind: 'adendo', amends: r })}>
                      <MessageSquarePlus className="size-3.5" aria-hidden="true" /> Adendo
                    </Button>
                  )}
                </div>
                <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[minmax(10rem,auto)_1fr]">
                  {Object.entries(r.body).map(([k, v]) => (
                    <div key={k} className="contents">
                      <dt className="text-muted-foreground">{CAMPO[k] ?? k}</dt>
                      <dd className="whitespace-pre-wrap">{Array.isArray(v) ? v.map((x) => rotulos[String(x)] ?? String(x)).join(' · ') : String(v)}</dd>
                    </div>
                  ))}
                </dl>
                <p className="text-xs text-muted-foreground tabular-nums">
                  Assinado por {r.signed_by_nome ?? (r.kind === 'telemonitoramento' ? 'registro do paciente' : '—')}
                  {r.signed_crefito ? ` · ${r.signed_crefito}` : ''} · {quando(r.signed_at)}
                </p>
              </CardContent></Card>
            </li>
          ))}
        </ol>
      )}

      {novo && <NovoRegistro pacienteId={pacienteId} novo={novo} marcadores={marcadores} sinaisDeAlerta={sinaisDeAlerta} onClose={() => setNovo(null)} />}
    </div>
  );
}

const VAZIO_AVALIACAO = {
  queixa_principal: '', historia_doenca: '', antecedentes: '', exame_fisico: '', diagnostico_cinesiologico: '',
  prognostico: '', objetivos: [''] as string[], plano_tratamento: '', sessoes_previstas: '' as string | number,
  red_flags: [] as string[], condition_flags: [] as string[],
};

function NovoRegistro({ pacienteId, novo, marcadores, sinaisDeAlerta, onClose }: {
  pacienteId: number; novo: NonNullable<Novo>; marcadores: Opcao[]; sinaisDeAlerta: Opcao[]; onClose: () => void;
}) {
  const [crefito, setCrefito] = useState(lerCrefito);
  const [aval, setAval] = useState(VAZIO_AVALIACAO);
  const [evo, setEvo] = useState({ conduta: '', resposta_paciente: '', dor: '' as string | number, observacoes: '' });
  const [texto, setTexto] = useState('');
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);
  const erro = (k: string) => erros[k];

  const corpo = () => {
    if (novo.kind === 'avaliacao') {
      return { ...aval, objetivos: aval.objetivos.filter((o) => o.trim() !== ''), sessoes_previstas: aval.sessoes_previstas === '' ? null : Number(aval.sessoes_previstas) };
    }
    if (novo.kind === 'evolucao') return { ...evo, dor: evo.dor === '' ? null : Number(evo.dor) };
    return { texto };
  };

  const assinar = async (e: React.FormEvent) => {
    e.preventDefault();
    setEnviando(true); setErros({});
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
    const r = await fetch(`/fisio/pacientes/${pacienteId}/registros`, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ kind: novo.kind, crefito, amends_id: novo.amends?.id, body: corpo() }),
    }).catch(() => null);
    setEnviando(false);
    if (r && r.status === 201) {
      try { window.localStorage.setItem(CREFITO_LS, crefito); } catch { /* sem storage: só não lembra */ }
      onClose();
      router.reload({ only: ['prontuario'] });
      return;
    }
    const d = r ? await r.json().catch(() => ({})) : {};
    const lista = (d.errors ?? {}) as Record<string, string[]>;
    setErros(Object.keys(lista).length ? Object.fromEntries(Object.entries(lista).map(([k, v]) => [k, v[0] ?? ''])) : { geral: 'Não foi possível assinar. Tente de novo.' });
  };

  const titulo = novo.kind === 'avaliacao' ? 'Avaliação (anamnese)' : novo.kind === 'evolucao' ? 'Evolução do atendimento' : 'Adendo de correção';
  const alternar = (lista: string[], v: string, on: boolean) => (on ? [...lista, v] : lista.filter((x) => x !== v));
  const campo = (k: keyof typeof VAZIO_AVALIACAO, rotulo: string, obrigatorio = true) => (
    <div className="flex flex-col gap-1">
      <Label htmlFor={`av-${k}`}>{rotulo}{obrigatorio ? ' *' : ''}</Label>
      <Textarea id={`av-${k}`} rows={3} value={String(aval[k] ?? '')} onChange={(e) => setAval({ ...aval, [k]: e.target.value })} />
      {erro(`body.${k}`) && <p className="text-xs text-destructive">{erro(`body.${k}`)}</p>}
    </div>
  );

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-[760px]">
        <form onSubmit={assinar} className="flex h-full flex-col">
          <SheetHeader>
            <SheetTitle>{titulo}</SheetTitle>
            <SheetDescription>
              {novo.kind === 'adendo'
                ? 'O registro original não muda. O adendo fica ao lado dele, assinado com a hora de agora.'
                : 'Ao assinar, o registro é carimbado com seu nome, CREFITO, data e hora e não pode mais ser alterado (Res. COFFITO 414/2012).'}
            </SheetDescription>
          </SheetHeader>
          <div className="flex flex-col gap-4 px-4">
            {novo.kind === 'avaliacao' && (
              <>
                {campo('queixa_principal', 'Queixa principal')}
                {campo('historia_doenca', 'História da doença atual (HDA)')}
                {campo('antecedentes', 'Antecedentes (pessoais, cirúrgicos, medicamentos)', false)}
                {campo('exame_fisico', 'Exame físico-funcional')}
                {campo('diagnostico_cinesiologico', 'Diagnóstico cinesiológico funcional')}
                {campo('prognostico', 'Prognóstico', false)}
                <div className="flex flex-col gap-2"><Label>Objetivos *</Label>
                  {aval.objetivos.map((o, i) => (
                    <Input key={i} aria-label={`Objetivo ${i + 1}`} value={o} onChange={(e) => setAval({ ...aval, objetivos: aval.objetivos.map((x, j) => (j === i ? e.target.value : x)) })} />
                  ))}
                  <Button type="button" size="sm" variant="ghost" className="self-start" onClick={() => setAval({ ...aval, objetivos: [...aval.objetivos, ''] })}>Mais um objetivo</Button>
                  {erro('body.objetivos') && <p className="text-xs text-destructive">{erro('body.objetivos')}</p>}
                </div>
                {campo('plano_tratamento', 'Plano de tratamento (conduta)')}
                <div className="flex flex-col gap-1"><Label htmlFor="av-sessoes">Sessões previstas</Label>
                  <Input id="av-sessoes" type="number" min={1} className="w-28 tabular-nums" value={aval.sessoes_previstas} onChange={(e) => setAval({ ...aval, sessoes_previstas: e.target.value })} /></div>
                <fieldset className="flex flex-col gap-2"><legend className="text-sm font-medium">Sinais de alerta (red flags) presentes</legend>
                  <div className="grid gap-2 sm:grid-cols-2">{sinaisDeAlerta.map((s) => (
                    <label key={s.value} className="flex items-center gap-2 text-sm"><Checkbox checked={aval.red_flags.includes(s.value)}
                      onCheckedChange={(v) => setAval({ ...aval, red_flags: alternar(aval.red_flags, s.value, v === true) })} />{s.label}</label>
                  ))}</div></fieldset>
                <fieldset className="flex flex-col gap-2"><legend className="text-sm font-medium">Marcadores de condição observados</legend>
                  <div className="grid gap-2 sm:grid-cols-2">{marcadores.map((m) => (
                    <label key={m.value} className="flex items-center gap-2 text-sm"><Checkbox checked={aval.condition_flags.includes(m.value)}
                      onCheckedChange={(v) => setAval({ ...aval, condition_flags: alternar(aval.condition_flags, m.value, v === true) })} />{m.label}</label>
                  ))}</div>
                  <p className="text-xs text-muted-foreground">Ficam no registro. Para disparar o aviso de contraindicação, marque também no perfil clínico.</p>
                </fieldset>
              </>
            )}

            {novo.kind === 'evolucao' && (
              <>
                <div className="flex flex-col gap-1"><Label htmlFor="ev-conduta">Conduta do atendimento *</Label>
                  <Textarea id="ev-conduta" rows={3} value={evo.conduta} onChange={(e) => setEvo({ ...evo, conduta: e.target.value })} />
                  {erro('body.conduta') && <p className="text-xs text-destructive">{erro('body.conduta')}</p>}</div>
                <div className="flex flex-col gap-1"><Label htmlFor="ev-resposta">Resposta do paciente *</Label>
                  <Textarea id="ev-resposta" rows={3} value={evo.resposta_paciente} onChange={(e) => setEvo({ ...evo, resposta_paciente: e.target.value })} />
                  {erro('body.resposta_paciente') && <p className="text-xs text-destructive">{erro('body.resposta_paciente')}</p>}</div>
                <div className="flex flex-col gap-1"><Label htmlFor="ev-dor">Dor no atendimento (0–10)</Label>
                  <Input id="ev-dor" type="number" min={0} max={10} className="w-24 tabular-nums" value={evo.dor} onChange={(e) => setEvo({ ...evo, dor: e.target.value })} />
                  {erro('body.dor') && <p className="text-xs text-destructive">{erro('body.dor')}</p>}</div>
                <div className="flex flex-col gap-1"><Label htmlFor="ev-obs">Observações</Label>
                  <Textarea id="ev-obs" rows={2} value={evo.observacoes} onChange={(e) => setEvo({ ...evo, observacoes: e.target.value })} /></div>
              </>
            )}

            {novo.kind === 'adendo' && (
              <div className="flex flex-col gap-1"><Label htmlFor="ad-texto">O que corrige *</Label>
                <Textarea id="ad-texto" rows={4} value={texto} onChange={(e) => setTexto(e.target.value)} />
                {(erro('body.texto') || erro('amends_id')) && <p className="text-xs text-destructive">{erro('body.texto') ?? erro('amends_id')}</p>}</div>
            )}

            <div className="flex flex-col gap-1"><Label htmlFor="assinatura-crefito">Seu CREFITO *</Label>
              <Input id="assinatura-crefito" className="w-64 tabular-nums" placeholder="CREFITO-10/000000-F" value={crefito} onChange={(e) => setCrefito(e.target.value)} />
              {erro('crefito') && <p className="text-xs text-destructive">{erro('crefito')}</p>}
              {erro('kind') && <p className="text-xs text-destructive">{erro('kind')}</p>}
              {erro('geral') && <p className="text-xs text-destructive">{erro('geral')}</p>}</div>
          </div>
          <SheetFooter className="flex-row justify-end">
            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={enviando}>Assinar registro</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}
