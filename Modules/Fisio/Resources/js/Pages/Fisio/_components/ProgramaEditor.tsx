// Editor do programa de exercícios na ficha do paciente (US-FISIO-006; UC-FPS-06, 08, 09, 10).
//
// O programa é o que o paciente executa sozinho em casa. A instrução de parada é texto do
// PROFISSIONAL (o paciente vê só isso com dor alta — ADR 0437 §5). Contraindicação AVISA e não
// bloqueia: o servidor responde 409 com os marcadores, o profissional decide e confirma, e a
// confirmação fica gravada no item (quem, quando, quais marcadores, justificativa).
// O software não decide conduta.
//
// As escritas são JSON (editor interativo); depois de cada uma, recarrega só a prop `programa`.
import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ArrowDown, ArrowUp, Trash2, TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Textarea } from '@/Components/ui/textarea';
import { rotuloCondicao } from './condicoes';

export interface ItemPrograma {
  id: number;
  exercise_id: number;
  nome: string | null;
  regiao: string | null;
  sets: number | null;
  reps: number | null;
  hold_s: number | null;
  times_per_day: number;
  notes: string | null;
  position: number;
  contraindicacao_confirmada: string[];
  contraindicacao_confirmada_em: string | null;
}
export interface Programa {
  id: number;
  phase: string | null;
  starts_at: string | null;
  stop_instruction: string | null;
  limiar_dor: number;
  itens: ItemPrograma[];
}
export interface ExercicioPrescrevivel {
  id: number;
  nome: string;
  regiao: string | null;
  contraindicacoes: string[];
}

type Resposta = { status: number; json: Record<string, unknown> };
type Erros = Record<string, string>;

function csrf(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function enviarJson(method: 'POST' | 'PUT' | 'DELETE', url: string, body?: unknown): Promise<Resposta> {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const json = (await res.json().catch(() => ({}))) as Record<string, unknown>;
  return { status: res.status, json };
}

/** Primeira mensagem de cada campo de um 422 do Laravel. */
function errosDe(json: Record<string, unknown>): Erros {
  const errors = (json.errors ?? {}) as Record<string, string[]>;
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0] ?? '']));
}

const recarregar = () => router.reload({ only: ['programa'] });

function NovoPrograma({ pacienteId, substitui, onCancelar }: { pacienteId: number; substitui: boolean; onCancelar?: () => void }) {
  const [dados, setDados] = useState({ phase: '', starts_at: '', stop_instruction: '' });
  const [erros, setErros] = useState<Erros>({});
  const [enviando, setEnviando] = useState(false);

  const criar = async (e: FormEvent) => {
    e.preventDefault();
    setEnviando(true);
    const r = await enviarJson('POST', `/fisio/pacientes/${pacienteId}/programas`, {
      phase: dados.phase || null,
      starts_at: dados.starts_at || null,
      stop_instruction: dados.stop_instruction,
    });
    setEnviando(false);
    if (r.status === 201) {
      onCancelar?.();
      recarregar();
      return;
    }
    setErros(r.status === 422 ? errosDe(r.json) : { geral: String(r.json.message ?? `Erro ${r.status}`) });
  };

  return (
    <form onSubmit={criar} className="flex flex-col gap-3 rounded-lg border border-border p-4" data-contract="fisio-programa-novo">
      {substitui && <p className="text-sm text-muted-foreground">Criar um programa novo encerra o atual. O histórico de check-ins continua guardado.</p>}
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="prog-fase">Fase</Label>
          <Input id="prog-fase" placeholder="Ex.: fase 1 — proteção" value={dados.phase} onChange={(e) => setDados({ ...dados, phase: e.target.value })} />
          {erros.phase && <p className="text-xs text-destructive">{erros.phase}</p>}
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="prog-inicio">Início</Label>
          <Input id="prog-inicio" type="date" value={dados.starts_at} onChange={(e) => setDados({ ...dados, starts_at: e.target.value })} />
          {erros.starts_at && <p className="text-xs text-destructive">{erros.starts_at}</p>}
        </div>
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="prog-parada">Instrução de parada (o paciente vê este texto se sentir dor forte ou um sinal de alerta)</Label>
        <Textarea id="prog-parada" rows={3} value={dados.stop_instruction} onChange={(e) => setDados({ ...dados, stop_instruction: e.target.value })} />
        {erros.stop_instruction && <p className="text-xs text-destructive">{erros.stop_instruction}</p>}
      </div>
      {erros.geral && <p className="text-xs text-destructive">{erros.geral}</p>}
      <div className="flex gap-2">
        <Button type="submit" disabled={enviando}>Criar programa</Button>
        {onCancelar && <Button type="button" variant="ghost" onClick={onCancelar}>Cancelar</Button>}
      </div>
    </form>
  );
}

function AdicionarExercicio({ programaId, exercicios }: { programaId: number; exercicios: ExercicioPrescrevivel[] }) {
  const [exercicioId, setExercicioId] = useState('');
  const [aviso, setAviso] = useState<{ flags: string[]; mensagem: string } | null>(null);
  const [justificativa, setJustificativa] = useState('');
  const [erro, setErro] = useState('');
  const [enviando, setEnviando] = useState(false);

  const adicionar = async (confirmar: boolean) => {
    if (!exercicioId) return;
    setEnviando(true);
    setErro('');
    const r = await enviarJson('POST', `/fisio/programas/${programaId}/itens`, {
      exercise_id: Number(exercicioId),
      ...(confirmar ? { confirmar_contraindicacao: true, justificativa: justificativa || null } : {}),
    });
    setEnviando(false);
    if (r.status === 201) {
      setExercicioId('');
      setAviso(null);
      setJustificativa('');
      recarregar();
      return;
    }
    if (r.status === 409 && r.json.erro === 'contraindicacao') {
      setAviso({ flags: (r.json.flags as string[]) ?? [], mensagem: String(r.json.mensagem ?? '') });
      return;
    }
    setErro(r.status === 422 ? Object.values(errosDe(r.json))[0] ?? 'Verifique o exercício.' : String(r.json.message ?? `Erro ${r.status}`));
  };

  return (
    <div className="flex flex-col gap-3" data-contract="fisio-programa-adicionar">
      <div className="flex flex-wrap items-end gap-2">
        <div className="flex min-w-64 flex-1 flex-col gap-1.5">
          <Label htmlFor="prog-exercicio">Adicionar exercício</Label>
          <Select value={exercicioId} onValueChange={(v) => { setExercicioId(v); setAviso(null); setErro(''); }}>
            <SelectTrigger id="prog-exercicio"><SelectValue placeholder="Escolha da biblioteca" /></SelectTrigger>
            <SelectContent>
              {exercicios.map((e) => (
                <SafeSelectItem key={e.id} value={String(e.id)}>{e.nome}{e.regiao ? ` · ${e.regiao}` : ''}</SafeSelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <Button type="button" disabled={!exercicioId || enviando || aviso !== null} onClick={() => adicionar(false)}>Adicionar</Button>
      </div>
      {erro && <p className="text-xs text-destructive">{erro}</p>}
      {aviso && (
        <Alert data-contract="fisio-contraindicacao-aviso">
          <TriangleAlert />
          <AlertTitle>Aviso de contraindicação</AlertTitle>
          <AlertDescription>
            <p>{aviso.mensagem}</p>
            <div className="mt-2 flex flex-wrap gap-1">
              {aviso.flags.map((f) => <Badge key={f} variant="outline">{rotuloCondicao(f)}</Badge>)}
            </div>
            <div className="mt-3 flex w-full flex-col gap-1.5">
              <Label htmlFor="prog-justificativa">Justificativa (fica registrada com a sua confirmação)</Label>
              <Textarea id="prog-justificativa" rows={2} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} />
            </div>
            <p className="mt-2 text-xs">A decisão clínica é do fisioterapeuta. O aviso não impede a prescrição.</p>
            <div className="mt-2 flex gap-2">
              <Button type="button" size="sm" disabled={enviando} onClick={() => adicionar(true)}>Prescrever mesmo assim</Button>
              <Button type="button" size="sm" variant="ghost" onClick={() => { setAviso(null); setExercicioId(''); }}>Não prescrever</Button>
            </div>
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
}

const CAMPOS_DOSE: [keyof Pick<ItemPrograma, 'sets' | 'reps' | 'hold_s' | 'times_per_day'>, string][] = [
  ['sets', 'Séries'], ['reps', 'Repetições'], ['hold_s', 'Segurar (s)'], ['times_per_day', 'Vezes/dia'],
];

function LinhaItem({ programaId, item, podeEditar, primeiro, ultimo, onMover }: {
  programaId: number; item: ItemPrograma; podeEditar: boolean; primeiro: boolean; ultimo: boolean; onMover: (direcao: -1 | 1) => void;
}) {
  const [dose, setDose] = useState({
    sets: String(item.sets ?? ''), reps: String(item.reps ?? ''), hold_s: String(item.hold_s ?? ''),
    times_per_day: String(item.times_per_day), notes: item.notes ?? '',
  });
  const [erros, setErros] = useState<Erros>({});
  const [enviando, setEnviando] = useState(false);

  const salvar = async () => {
    setEnviando(true);
    const corpo: Record<string, unknown> = { notes: dose.notes || null };
    for (const [campo] of CAMPOS_DOSE) {
      if (dose[campo] !== '') corpo[campo] = Number(dose[campo]);
    }
    const r = await enviarJson('PUT', `/fisio/programas/${programaId}/itens/${item.id}`, corpo);
    setEnviando(false);
    if (r.status === 200) { setErros({}); recarregar(); return; }
    setErros(r.status === 422 ? errosDe(r.json) : { geral: String(r.json.message ?? `Erro ${r.status}`) });
  };
  const remover = async () => {
    const r = await enviarJson('DELETE', `/fisio/programas/${programaId}/itens/${item.id}`);
    if (r.status === 200) recarregar();
    else setErros({ geral: String(r.json.message ?? `Erro ${r.status}`) });
  };

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border p-3" data-contract="fisio-programa-item">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="tabular-nums text-muted-foreground">{item.position}.</span>
        <span className="font-semibold">{item.nome ?? `Exercício ${item.exercise_id}`}</span>
        {item.regiao && <Badge variant="secondary">{item.regiao}</Badge>}
        {item.contraindicacao_confirmada.length > 0 && (
          <Badge variant="outline">Contraindicação confirmada: {item.contraindicacao_confirmada.map(rotuloCondicao).join(', ')}</Badge>
        )}
        {podeEditar && (
          <div className="ml-auto flex gap-1">
            <Button type="button" size="icon" variant="ghost" aria-label="Subir" disabled={primeiro} onClick={() => onMover(-1)}><ArrowUp className="size-4" aria-hidden="true" /></Button>
            <Button type="button" size="icon" variant="ghost" aria-label="Descer" disabled={ultimo} onClick={() => onMover(1)}><ArrowDown className="size-4" aria-hidden="true" /></Button>
            <Button type="button" size="icon" variant="ghost" aria-label="Remover do programa" onClick={remover}><Trash2 className="size-4" aria-hidden="true" /></Button>
          </div>
        )}
      </div>
      {podeEditar ? (
        <div className="flex flex-wrap items-end gap-2">
          {CAMPOS_DOSE.map(([campo, rotulo]) => (
            <div key={campo} className="flex flex-col gap-1">
              <Label htmlFor={`dose-${item.id}-${campo}`}>{rotulo}</Label>
              <Input id={`dose-${item.id}-${campo}`} type="number" inputMode="numeric" className="w-24" value={dose[campo]} onChange={(e) => setDose({ ...dose, [campo]: e.target.value })} />
              {erros[campo] && <p className="text-xs text-destructive">{erros[campo]}</p>}
            </div>
          ))}
          <div className="flex min-w-48 flex-1 flex-col gap-1">
            <Label htmlFor={`dose-${item.id}-notes`}>Observação ao paciente</Label>
            <Input id={`dose-${item.id}-notes`} value={dose.notes} onChange={(e) => setDose({ ...dose, notes: e.target.value })} />
          </div>
          <Button type="button" size="sm" variant="outline" disabled={enviando} onClick={salvar}>Salvar dose</Button>
        </div>
      ) : (
        <p className="text-sm text-muted-foreground">
          {item.sets ?? '—'} × {item.reps ?? '—'}{item.hold_s ? ` · segurar ${item.hold_s}s` : ''} · {item.times_per_day}×/dia{item.notes ? ` · ${item.notes}` : ''}
        </p>
      )}
      {erros.geral && <p className="text-xs text-destructive">{erros.geral}</p>}
    </li>
  );
}

export default function ProgramaEditor({ pacienteId, programa, exercicios, podeEditar }: {
  pacienteId: number; programa: Programa | null; exercicios: ExercicioPrescrevivel[]; podeEditar: boolean;
}) {
  const [novo, setNovo] = useState(false);
  const [erroOrdem, setErroOrdem] = useState('');

  if (!programa) {
    return podeEditar
      ? <NovoPrograma pacienteId={pacienteId} substitui={false} />
      : <p className="text-sm text-muted-foreground">Nenhum programa ativo.</p>;
  }

  const mover = async (indice: number, direcao: -1 | 1) => {
    const ids = programa.itens.map((i) => i.id);
    const alvo = indice + direcao;
    const a = ids[indice];
    const b = ids[alvo];
    if (a === undefined || b === undefined) return;
    ids[indice] = b;
    ids[alvo] = a;
    const r = await enviarJson('PUT', `/fisio/programas/${programa.id}/itens/ordem`, { ids });
    if (r.status === 200) { setErroOrdem(''); recarregar(); return; }
    setErroOrdem(r.status === 422 ? Object.values(errosDe(r.json))[0] ?? 'Não foi possível reordenar.' : String(r.json.message ?? `Erro ${r.status}`));
  };

  return (
    <div className="flex flex-col gap-4" data-contract="fisio-programa">
      <div className="flex flex-col gap-1 text-sm">
        <div className="flex flex-wrap items-center gap-2">
          {programa.phase && <Badge variant="secondary">{programa.phase}</Badge>}
          {programa.starts_at && <span className="text-muted-foreground">desde {new Date(`${programa.starts_at}T00:00:00`).toLocaleDateString('pt-BR')}</span>}
          <span className="text-muted-foreground">· alerta de dor a partir de {programa.limiar_dor}/10</span>
        </div>
        <p><span className="text-muted-foreground">Instrução de parada: </span>{programa.stop_instruction}</p>
      </div>
      {programa.itens.length === 0 ? (
        <p className="text-sm text-muted-foreground">Nenhum exercício no programa.</p>
      ) : (
        <ol className="flex flex-col gap-2">
          {programa.itens.map((item, i) => (
            <LinhaItem
              key={`${item.id}-${item.position}-${item.sets}-${item.reps}-${item.hold_s}-${item.times_per_day}`}
              programaId={programa.id}
              item={item}
              podeEditar={podeEditar}
              primeiro={i === 0}
              ultimo={i === programa.itens.length - 1}
              onMover={(d) => mover(i, d)}
            />
          ))}
        </ol>
      )}
      {erroOrdem && <p className="text-xs text-destructive">{erroOrdem}</p>}
      {podeEditar && <AdicionarExercicio programaId={programa.id} exercicios={exercicios} />}
      {podeEditar && (novo
        ? <NovoPrograma pacienteId={pacienteId} substitui onCancelar={() => setNovo(false)} />
        : <div><Button type="button" variant="outline" onClick={() => setNovo(true)}>Novo programa</Button></div>)}
    </div>
  );
}
