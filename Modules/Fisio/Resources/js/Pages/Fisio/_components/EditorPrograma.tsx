// Editor do programa de exercícios na ficha do paciente (US-FISIO-006; ADR 0437 §5 e §13.2).
//
// O profissional escolhe tudo: exercício, dose, ordem, instrução de parada, limiar de dor e versão
// de crise. O software não sugere conduta. A contraindicação AVISA e nunca bloqueia: o servidor
// responde 409 com as marcações que cruzaram e o profissional confirma com justificativa escrita,
// que vai para o prontuário. Nada deste aviso aparece para o paciente.
import { router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import EmptyState from '@/Components/shared/EmptyState';
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
  contraindicacao: string[];
  confirmado_em: string | null;
  justificativa: string | null;
}
export interface Programa {
  id: number;
  phase: string | null;
  origin: 'manual' | 'protocolo';
  stop_instruction: string | null;
  pain_alert_threshold: number | null;
  pain_alert_mode: 'absoluto' | 'sobre_basal';
  limiar_efetivo: number;
  crisis_items: string[];
  starts_at: string | null;
  itens: ItemPrograma[];
}
export interface ExercicioBiblioteca {
  id: number;
  nome: string;
  regiao: string;
  proprio: boolean;
  dose: [number | null, number | null, number | null];
  contraindicacao: string[];
}
export interface ProtocoloModelo {
  id: number;
  nome: string;
  regiao: string | null;
  modelo_global: boolean;
  copia_de: number | null;
  qtd_itens: number;
  notes: string | null;
}

interface Props {
  pacienteId: number;
  podeEditar: boolean;
  programa: Programa | null;
  exercicios: ExercicioBiblioteca[];
  protocolos: ProtocoloModelo[];
}

type Erros = Record<string, string>;
interface Aviso {
  rota: string;
  corpo: Record<string, unknown>;
  itens: { exercise_id: number; nome: string; flags: string[] }[];
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/** Chamada JSON às rotas do editor. Devolve status e corpo; quem chama decide. */
async function chamar(metodo: 'POST' | 'PUT' | 'DELETE', url: string, corpo?: unknown): Promise<{ status: number; json: Record<string, unknown> }> {
  const res = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: corpo === undefined ? undefined : JSON.stringify(corpo),
  });
  const json = (await res.json().catch(() => ({}))) as Record<string, unknown>;
  return { status: res.status, json };
}

/** Primeira mensagem de cada campo de um 422 do Laravel. */
const errosDe = (json: Record<string, unknown>): Erros =>
  Object.fromEntries(Object.entries((json.errors as Record<string, string[]>) ?? {}).map(([k, v]) => [k, v[0] ?? '']));

const recarregar = () => router.reload({ only: ['programa', 'exercicios', 'protocolos', 'prontuario'] });
const linhas = (t: string) => t.split('\n').map((l) => l.trim()).filter(Boolean);
const doseTexto = (s: number | null, r: number | null, h: number | null) =>
  [s ? `${s}×` : null, r ? `${r}` : null, h ? `${h}s` : null].filter(Boolean).join(' ') || '—';

function Erro({ texto }: { texto?: string }) {
  return texto ? <p className="text-xs text-destructive">{texto}</p> : null;
}

/** Instrução de parada, alerta de dor e versão de crise — texto do profissional, nunca do software. */
function CamposCabecalho({ dados, set, erros }: {
  dados: { stop_instruction: string; pain_alert_threshold: string; pain_alert_mode: string; crisis: string; phase: string };
  set: (campo: string, v: string) => void;
  erros: Erros;
}) {
  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="prog-stop">Instrução de parada (o paciente vê só isto em dia de dor forte ou sinal de alerta)</Label>
        <Textarea id="prog-stop" rows={2} value={dados.stop_instruction} onChange={(e) => set('stop_instruction', e.target.value)} />
        <Erro texto={erros.stop_instruction} />
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="prog-fase">Fase</Label>
          <Input id="prog-fase" value={dados.phase} onChange={(e) => set('phase', e.target.value)} />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="prog-limiar">Limiar de alerta de dor (1 a 10)</Label>
          <Input id="prog-limiar" type="number" inputMode="numeric" min={1} max={10} placeholder="Padrão 7" value={dados.pain_alert_threshold} onChange={(e) => set('pain_alert_threshold', e.target.value)} />
          <Erro texto={erros.pain_alert_threshold} />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="prog-modo">Como comparar a dor</Label>
          <Select value={dados.pain_alert_mode} onValueChange={(v) => set('pain_alert_mode', v)}>
            <SelectTrigger id="prog-modo"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SafeSelectItem value="absoluto">Valor absoluto</SafeSelectItem>
              <SafeSelectItem value="sobre_basal">Acima da dor de base do paciente</SafeSelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="prog-crise">Versão de crise (um exercício ou orientação por linha; o paciente escolhe &quot;hoje estou em crise&quot;)</Label>
        <Textarea id="prog-crise" rows={3} value={dados.crisis} onChange={(e) => set('crisis', e.target.value)} />
        <Erro texto={erros.crisis_items} />
      </div>
    </div>
  );
}

function cabecalhoDe(p: Programa | null) {
  return {
    stop_instruction: p?.stop_instruction ?? '',
    pain_alert_threshold: p?.pain_alert_threshold ? String(p.pain_alert_threshold) : '',
    pain_alert_mode: p?.pain_alert_mode ?? 'absoluto',
    crisis: (p?.crisis_items ?? []).join('\n'),
    phase: p?.phase ?? '',
  };
}
const corpoCabecalho = (d: ReturnType<typeof cabecalhoDe>) => ({
  stop_instruction: d.stop_instruction,
  pain_alert_threshold: d.pain_alert_threshold === '' ? null : Number(d.pain_alert_threshold),
  pain_alert_mode: d.pain_alert_mode,
  crisis_items: linhas(d.crisis),
  phase: d.phase || null,
});

function NovoPrograma({ pacienteId, temAnterior }: { pacienteId: number; temAnterior: boolean }) {
  const [dados, setDados] = useState(cabecalhoDe(null));
  const [erros, setErros] = useState<Erros>({});
  const [enviando, setEnviando] = useState(false);

  const criar = async (e: FormEvent) => {
    e.preventDefault();
    setEnviando(true);
    const r = await chamar('POST', `/fisio/pacientes/${pacienteId}/programas`, corpoCabecalho(dados));
    setEnviando(false);
    if (r.status === 201) recarregar();
    else setErros(errosDe(r.json));
  };

  return (
    <form onSubmit={criar} className="flex flex-col gap-3 rounded-lg border border-border p-4" data-contract="fisio-programa-novo">
      <h3 className="text-sm font-semibold">{temAnterior ? 'Novo programa (encerra o atual)' : 'Criar programa'}</h3>
      <CamposCabecalho dados={dados} set={(c, v) => setDados({ ...dados, [c]: v })} erros={erros} />
      <Erro texto={erros.paciente} />
      <div><Button type="submit" disabled={enviando}>Criar programa</Button></div>
    </form>
  );
}

function Cabecalho({ programa, podeEditar }: { programa: Programa; podeEditar: boolean }) {
  const [editando, setEditando] = useState(false);
  const [dados, setDados] = useState(cabecalhoDe(programa));
  const [erros, setErros] = useState<Erros>({});

  if (!editando) {
    return (
      <dl className="flex flex-col gap-2 text-sm">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">{programa.origin === 'protocolo' ? 'A partir de protocolo' : 'Montado manualmente'}</Badge>
          {programa.phase && <Badge variant="outline">{programa.phase}</Badge>}
          <span className="text-muted-foreground">
            Alerta de dor: {programa.limiar_efetivo}{programa.pain_alert_threshold ? '' : ' (padrão)'} · {programa.pain_alert_mode === 'sobre_basal' ? 'acima da dor de base' : 'valor absoluto'}
          </span>
          {podeEditar && <Button size="sm" variant="ghost" className="ml-auto" onClick={() => setEditando(true)}>Editar instruções</Button>}
        </div>
        <div><dt className="text-muted-foreground">Instrução de parada</dt><dd>{programa.stop_instruction || '—'}</dd></div>
        <div><dt className="text-muted-foreground">Versão de crise</dt><dd>{programa.crisis_items.length ? programa.crisis_items.join('; ') : 'Não escrita'}</dd></div>
      </dl>
    );
  }

  const salvar = async (e: FormEvent) => {
    e.preventDefault();
    const r = await chamar('PUT', `/fisio/programas/${programa.id}`, corpoCabecalho(dados));
    if (r.status === 200) { setEditando(false); recarregar(); } else setErros(errosDe(r.json));
  };
  return (
    <form onSubmit={salvar} className="flex flex-col gap-3">
      <CamposCabecalho dados={dados} set={(c, v) => setDados({ ...dados, [c]: v })} erros={erros} />
      <div className="flex gap-2">
        <Button type="submit">Salvar</Button>
        <Button type="button" variant="ghost" onClick={() => { setDados(cabecalhoDe(programa)); setEditando(false); }}>Cancelar</Button>
      </div>
    </form>
  );
}

function LinhaItem({ programaId, item, podeEditar, primeiro, ultimo, onMover }: {
  programaId: number; item: ItemPrograma; podeEditar: boolean; primeiro: boolean; ultimo: boolean; onMover: (dir: -1 | 1) => void;
}) {
  const [dose, setDose] = useState({
    sets: String(item.sets ?? ''), reps: String(item.reps ?? ''), hold_s: String(item.hold_s ?? ''),
    times_per_day: String(item.times_per_day), notes: item.notes ?? '',
  });
  const [erros, setErros] = useState<Erros>({});
  const mudou = dose.sets !== String(item.sets ?? '') || dose.reps !== String(item.reps ?? '') || dose.hold_s !== String(item.hold_s ?? '')
    || dose.times_per_day !== String(item.times_per_day) || dose.notes !== (item.notes ?? '');

  const salvar = async () => {
    const num = (v: string) => (v === '' ? null : Number(v));
    const r = await chamar('PUT', `/fisio/programas/${programaId}/itens/${item.id}`, {
      sets: num(dose.sets) ?? 1, reps: num(dose.reps), hold_s: num(dose.hold_s), times_per_day: num(dose.times_per_day) ?? 1, notes: dose.notes || null,
    });
    if (r.status === 200) { setErros({}); recarregar(); } else setErros(errosDe(r.json));
  };
  const remover = async () => { if ((await chamar('DELETE', `/fisio/programas/${programaId}/itens/${item.id}`)).status === 200) recarregar(); };
  const campo = (chave: keyof typeof dose, rotulo: string) => (
    <div className="flex flex-col gap-1">
      <Label htmlFor={`it-${item.id}-${chave}`} className="text-xs text-muted-foreground">{rotulo}</Label>
      <Input id={`it-${item.id}-${chave}`} type="number" inputMode="numeric" className="w-20" disabled={!podeEditar} value={dose[chave]} onChange={(e) => setDose({ ...dose, [chave]: e.target.value })} />
      <Erro texto={erros[chave]} />
    </div>
  );

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border p-3" data-contract="fisio-programa-item">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="font-medium">{item.position}. {item.nome ?? 'Exercício'}</span>
        {item.regiao && <span className="text-muted-foreground">{item.regiao}</span>}
        {item.contraindicacao.map((f) => <Badge key={f} variant="warning">{rotuloCondicao(f)}</Badge>)}
        {podeEditar && (
          <div className="ml-auto flex gap-1">
            <Button size="icon" variant="ghost" aria-label="Subir" disabled={primeiro} onClick={() => onMover(-1)}><ArrowUp className="size-4" aria-hidden="true" /></Button>
            <Button size="icon" variant="ghost" aria-label="Descer" disabled={ultimo} onClick={() => onMover(1)}><ArrowDown className="size-4" aria-hidden="true" /></Button>
            <Button size="icon" variant="ghost" aria-label="Remover do programa" onClick={remover}><Trash2 className="size-4" aria-hidden="true" /></Button>
          </div>
        )}
      </div>
      {item.contraindicacao.length > 0 && (
        <p className="text-xs text-muted-foreground">
          Mantido com contraindicação marcada{item.justificativa ? `: ${item.justificativa}` : ''}. Registrado no prontuário.
        </p>
      )}
      <div className="flex flex-wrap items-end gap-3">
        {campo('sets', 'Séries')}
        {campo('reps', 'Repetições')}
        {campo('hold_s', 'Sustentação (s)')}
        {campo('times_per_day', 'Vezes por dia')}
        <div className="flex min-w-48 flex-1 flex-col gap-1">
          <Label htmlFor={`it-${item.id}-notes`} className="text-xs text-muted-foreground">Observação</Label>
          <Input id={`it-${item.id}-notes`} disabled={!podeEditar} value={dose.notes} onChange={(e) => setDose({ ...dose, notes: e.target.value })} />
        </div>
        {podeEditar && mudou && <Button size="sm" onClick={salvar}>Salvar dose</Button>}
      </div>
    </li>
  );
}

/** Aviso de contraindicação: o profissional decide; para manter, escreve a justificativa. */
function AvisoContraindicacao({ aviso, onFechar }: { aviso: Aviso; onFechar: () => void }) {
  const [justificativa, setJustificativa] = useState('');
  const [crefito, setCrefito] = useState('');
  const [erros, setErros] = useState<Erros>({});

  const manter = async () => {
    if (justificativa.trim().length < 5) { setErros({ justificativa: 'Escreva por que o exercício é mantido.' }); return; }
    const r = await chamar('POST', aviso.rota, {
      ...aviso.corpo, confirmar_contraindicacao: true, contraindication_justification: justificativa, crefito: crefito || null,
    });
    if (r.status === 201) { onFechar(); recarregar(); } else setErros(errosDe(r.json));
  };

  return (
    <div role="alertdialog" aria-labelledby="aviso-ci-titulo" className="flex flex-col gap-3 rounded-lg border border-warning bg-warning-soft p-4" data-contract="fisio-aviso-contraindicacao">
      <h3 id="aviso-ci-titulo" className="text-sm font-semibold text-warning-fg">Contraindicação marcada para este paciente</h3>
      <ul className="flex flex-col gap-1 text-sm">
        {aviso.itens.map((i) => (
          <li key={i.exercise_id}>{i.nome}: {i.flags.map(rotuloCondicao).join(', ')}</li>
        ))}
      </ul>
      <p className="text-xs text-muted-foreground">É um aviso: a decisão clínica é sua. Para manter, escreva a justificativa — ela vai para o prontuário. O paciente não vê este aviso.</p>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="ci-just">Justificativa</Label>
        <Textarea id="ci-just" rows={2} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} />
        <Erro texto={erros.justificativa ?? erros.contraindication_justification} />
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="ci-crefito">CREFITO de quem confirma</Label>
        <Input id="ci-crefito" className="max-w-xs" value={crefito} onChange={(e) => setCrefito(e.target.value)} />
      </div>
      <div className="flex gap-2">
        <Button onClick={manter}>Manter e registrar</Button>
        <Button variant="ghost" onClick={onFechar}>Não adicionar</Button>
      </div>
    </div>
  );
}

function AdicionarExercicio({ programaId, exercicios, onAviso }: { programaId: number; exercicios: ExercicioBiblioteca[]; onAviso: (a: Aviso) => void }) {
  const [busca, setBusca] = useState('');
  const [erro, setErro] = useState('');
  const visiveis = useMemo(() => {
    const q = busca.trim().toLocaleLowerCase('pt-BR');
    return (q ? exercicios.filter((e) => e.nome.toLocaleLowerCase('pt-BR').includes(q) || e.regiao.toLocaleLowerCase('pt-BR').includes(q)) : exercicios).slice(0, 40);
  }, [busca, exercicios]);

  if (exercicios.length === 0) {
    return (
      <EmptyState
        icon="dumbbell"
        title="A biblioteca ainda não tem exercícios"
        description="A biblioteca do oimpresso aguarda a revisão clínica. Cadastre os exercícios da sua clínica na Biblioteca para montar o programa."
        action={<Button variant="outline" asChild><a href="/fisio/biblioteca">Abrir a Biblioteca</a></Button>}
      />
    );
  }

  const adicionar = async (ex: ExercicioBiblioteca) => {
    setErro('');
    const rota = `/fisio/programas/${programaId}/itens`;
    const r = await chamar('POST', rota, { exercise_id: ex.id });
    if (r.status === 201) recarregar();
    else if (r.status === 409) onAviso({ rota, corpo: { exercise_id: ex.id }, itens: (r.json.itens as Aviso['itens']) ?? [] });
    else setErro(Object.values(errosDe(r.json))[0] ?? 'Não foi possível adicionar.');
  };

  return (
    <div className="flex flex-col gap-2" data-contract="fisio-programa-adicionar">
      <Label htmlFor="ex-busca">Adicionar exercício</Label>
      <Input id="ex-busca" placeholder="Buscar por nome ou região" value={busca} onChange={(e) => setBusca(e.target.value)} />
      <Erro texto={erro} />
      <ul className="flex max-h-64 flex-col gap-1 overflow-y-auto">
        {visiveis.map((ex) => (
          <li key={ex.id} className="flex flex-wrap items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-muted">
            <span>{ex.nome}</span>
            <span className="text-muted-foreground">{ex.regiao} · {doseTexto(...ex.dose)}</span>
            {ex.proprio && <Badge variant="neutral">Da clínica</Badge>}
            {ex.contraindicacao.length > 0 && <Badge variant="warning">Contraindicação</Badge>}
            <Button size="sm" variant="outline" className="ml-auto" onClick={() => adicionar(ex)}>Adicionar</Button>
          </li>
        ))}
        {visiveis.length === 0 && <li className="px-2 text-sm text-muted-foreground">Nenhum exercício com esse termo.</li>}
      </ul>
    </div>
  );
}

function Protocolos({ programa, protocolos, podeEditar, onAviso }: {
  programa: Programa | null; protocolos: ProtocoloModelo[]; podeEditar: boolean; onAviso: (a: Aviso) => void;
}) {
  const [msg, setMsg] = useState('');
  const [nome, setNome] = useState('');

  const aplicar = async (p: ProtocoloModelo) => {
    if (!programa) return;
    const rota = `/fisio/programas/${programa.id}/protocolo`;
    const r = await chamar('POST', rota, { protocol_id: p.id });
    if (r.status === 201) {
      const ign = (r.json.ignorados as number[]) ?? [];
      setMsg(ign.length ? `Protocolo aplicado. ${ign.length} item(ns) sem exercício disponível nesta clínica foram pulados.` : 'Protocolo aplicado.');
      recarregar();
    } else if (r.status === 409) onAviso({ rota, corpo: { protocol_id: p.id }, itens: (r.json.itens as Aviso['itens']) ?? [] });
    else setMsg(Object.values(errosDe(r.json))[0] ?? 'Não foi possível aplicar.');
  };
  const copiar = async (p: ProtocoloModelo) => {
    const r = await chamar('POST', `/fisio/protocolos/${p.id}/copiar`);
    setMsg(r.status === 201 ? 'Cópia criada para a clínica. Edite a cópia; o modelo não muda.' : 'Não foi possível copiar.');
    if (r.status === 201) recarregar();
  };
  const salvarComo = async (e: FormEvent) => {
    e.preventDefault();
    if (!programa) return;
    const r = await chamar('POST', `/fisio/programas/${programa.id}/salvar-como-protocolo`, { name: nome });
    setMsg(r.status === 201 ? 'Programa salvo como protocolo da clínica.' : (Object.values(errosDe(r.json))[0] ?? 'Não foi possível salvar.'));
    if (r.status === 201) { setNome(''); recarregar(); }
  };

  return (
    <section className="flex flex-col gap-3" data-contract="fisio-protocolos">
      <h3 className="text-sm font-semibold">Protocolos-modelo</h3>
      <p className="text-xs text-muted-foreground">Modelo de referência. A decisão clínica é do fisioterapeuta.</p>
      {protocolos.length === 0 ? (
        <p className="text-sm text-muted-foreground">Nenhum protocolo disponível. Os modelos do oimpresso aguardam a revisão clínica; a clínica pode salvar um programa como protocolo próprio.</p>
      ) : (
        <ul className="flex flex-col gap-1">
          {protocolos.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center gap-2 rounded-md px-2 py-1 text-sm">
              <span>{p.nome}</span>
              <span className="text-muted-foreground">{p.qtd_itens} exercício(s){p.regiao ? ` · ${p.regiao}` : ''}</span>
              <Badge variant={p.modelo_global ? 'info' : 'neutral'}>{p.modelo_global ? 'Modelo oimpresso' : 'Da clínica'}</Badge>
              {podeEditar && (
                <div className="ml-auto flex gap-1">
                  {programa && <Button size="sm" variant="outline" onClick={() => aplicar(p)}>Aplicar</Button>}
                  {p.modelo_global && <Button size="sm" variant="ghost" onClick={() => copiar(p)}>Copiar para a clínica</Button>}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
      {podeEditar && programa && programa.itens.length > 0 && (
        <form onSubmit={salvarComo} className="flex flex-wrap items-end gap-2">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="prot-nome">Salvar este programa como protocolo da clínica</Label>
            <Input id="prot-nome" className="w-64" placeholder="Nome do protocolo" value={nome} onChange={(e) => setNome(e.target.value)} />
          </div>
          <Button type="submit" variant="outline" disabled={nome.trim() === ''}>Salvar protocolo</Button>
        </form>
      )}
      {msg && <p className="text-sm text-muted-foreground" role="status">{msg}</p>}
    </section>
  );
}

export default function EditorPrograma({ pacienteId, podeEditar, programa, exercicios, protocolos }: Props) {
  const [aviso, setAviso] = useState<Aviso | null>(null);

  const mover = async (index: number, dir: -1 | 1) => {
    if (!programa) return;
    const ids = programa.itens.map((i) => i.id);
    const alvo = index + dir;
    const a = ids[index];
    const b = ids[alvo];
    if (a === undefined || b === undefined) return;
    ids[index] = b;
    ids[alvo] = a;
    if ((await chamar('PUT', `/fisio/programas/${programa.id}/itens/ordem`, { ids })).status === 200) recarregar();
  };

  return (
    <div className="flex flex-col gap-4" data-contract="fisio-editor-programa">
      {programa ? (
        <>
          <Cabecalho key={programa.id} programa={programa} podeEditar={podeEditar} />
          {programa.itens.length === 0 ? (
            <p className="text-sm text-muted-foreground">O programa ainda não tem exercícios.</p>
          ) : (
            <ol className="flex flex-col gap-2">
              {programa.itens.map((item, i) => (
                <LinhaItem
                  key={`${item.id}-${item.position}-${item.sets}-${item.reps}-${item.hold_s}-${item.times_per_day}`}
                  programaId={programa.id} item={item} podeEditar={podeEditar}
                  primeiro={i === 0} ultimo={i === programa.itens.length - 1} onMover={(d) => mover(i, d)}
                />
              ))}
            </ol>
          )}
          {aviso && <AvisoContraindicacao aviso={aviso} onFechar={() => setAviso(null)} />}
          {podeEditar && !aviso && <AdicionarExercicio programaId={programa.id} exercicios={exercicios} onAviso={setAviso} />}
        </>
      ) : (
        !podeEditar && <p className="text-sm text-muted-foreground">Este paciente não tem programa ativo.</p>
      )}
      {podeEditar && <NovoPrograma pacienteId={pacienteId} temAnterior={programa !== null} />}
      <Protocolos programa={programa} protocolos={protocolos} podeEditar={podeEditar} onAviso={setAviso} />
    </div>
  );
}
